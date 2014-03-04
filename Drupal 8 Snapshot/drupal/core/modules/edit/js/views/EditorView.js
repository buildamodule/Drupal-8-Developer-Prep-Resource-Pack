(function ($, _, Backbone, Drupal) {

"use strict";

/**
 * A base implementation that outlines the structure for in-place editors.
 *
 * Specific in-place editor implementations should subclass (extend) this View
 * and override whichever method they deem necessary to override.
 *
 * Look at Drupal.edit.editors.form and Drupal.edit.editors.direct for
 * examples.
 */
Drupal.edit.EditorView = Backbone.View.extend({

  /**
   * {@inheritdoc}
   *
   * Typically you would want to override this method to set the originalValue
   * attribute in the FieldModel to such a value that your in-place editor can
   * revert to the original value when necessary.
   *
   * If you override this method, you should call this method (the parent
   * class' initialize()) first, like this:
   *   Drupal.edit.EditorView.prototype.initialize.call(this, options);
   *
   * For an example, @see Drupal.edit.editors.direct.
   *
   * @param Object options
   *   An object with the following keys:
   *   - Drupal.edit.EditorModel model: the in-place editor state model
   *   - Drupal.edit.FieldModel fieldModel: the field model
   */
  initialize: function (options) {
    this.fieldModel = options.fieldModel;
    this.fieldModel.on('change:state', this.stateChange, this);
  },

  /**
   * {@inheritdoc}
   */
  remove: function () {
    // The el property is the field, which should not be removed. Remove the
    // pointer to it, then call Backbone.View.prototype.remove().
    this.setElement();
    this.fieldModel.off(null, null, this);
    Backbone.View.prototype.remove.call(this);
  },

  /**
   * Returns the edited element.
   *
   * For some single cardinality fields, it may be necessary or useful to
   * not in-place edit (and hence decorate) the DOM element with the
   * data-edit-id attribute (which is the field's wrapper), but a specific
   * element within the field's wrapper.
   * e.g. using a WYSIWYG editor on a body field should happen on the DOM
   * element containing the text itself, not on the field wrapper.
   *
   * For example, @see Drupal.edit.editors.direct.
   *
   * @return jQuery
   *   A jQuery-wrapped DOM element.
   */
  getEditedElement: function () {
    return this.$el;
  },

  /**
   * Returns 3 Edit UI settings that depend on the in-place editor:
   *  - Boolean padding: indicates whether padding should be applied to the
   *    edited element, to guarantee legibility of text.
   *  - Boolean unifiedToolbar: provides the in-place editor with the ability to
   *    insert its own toolbar UI into Edit's tightly integrated toolbar.
   *  - Boolean fullWidthToolbar: indicates whether Edit's tightly integrated
   *    toolbar should consume the full width of the element, rather than being
   *    just long enough to accomodate a label.
   */
  getEditUISettings: function () {
    return { padding: false, unifiedToolbar: false, fullWidthToolbar: false };
  },

  /**
   * Determines the actions to take given a change of state.
   *
   * @param Drupal.edit.FieldModel fieldModel
   * @param String state
   *   The state of the associated field. One of Drupal.edit.FieldModel.states.
   */
  stateChange: function (fieldModel, state) {
    var from = fieldModel.previous('state');
    var to = state;
    switch (to) {
      case 'inactive':
        // An in-place editor view will not yet exist in this state, hence
        // this will never be reached. Listed for sake of completeness.
        break;
      case 'candidate':
        // Nothing to do for the typical in-place editor: it should not be
        // visible yet.

        // Except when we come from the 'invalid' state, then we clean up.
        if (from === 'invalid') {
          this.removeValidationErrors();
        }
        break;
      case 'highlighted':
        // Nothing to do for the typical in-place editor: it should not be
        // visible yet.
        break;
      case 'activating':
        // The user has indicated he wants to do in-place editing: if
        // something needs to be loaded (CSS/JavaScript/server data/…), then
        // do so at this stage, and once the in-place editor is ready,
        // set the 'active' state.
        // A "loading" indicator will be shown in the UI for as long as the
        // field remains in this state.
        var loadDependencies = function (callback) {
          // Do the loading here.
          callback();
        };
        loadDependencies(function () {
          fieldModel.set('state', 'active');
        });
        break;
      case 'active':
        // The user can now actually use the in-place editor.
        break;
      case 'changed':
        // Nothing to do for the typical in-place editor. The UI will show an
        // indicator that the field has changed.
        break;
      case 'saving':
        // When the user has indicated he wants to save his changes to this
        // field, this state will be entered.
        // If the previous saving attempt resulted in validation errors, the
        // previous state will be 'invalid'. Clean up those validation errors
        // while the user is saving.
        if (from === 'invalid') {
          this.removeValidationErrors();
        }
        this.save();
        break;
      case 'saved':
        // Nothing to do for the typical in-place editor. Immediately after
        // being saved, a field will go to the 'candidate' state, where it
        // should no longer be visible (after all, the field will then again
        // just be a *candidate* to be in-place edited).
        break;
      case 'invalid':
        // The modified field value was attempted to be saved, but there were
        // validation errors.
        this.showValidationErrors();
        break;
    }
  },

  /**
   * Reverts the modified value back to the original value (before editing
   * started).
   */
  revert: function () {
    // A no-op by default; each editor should implement reverting itself.

    // Note that if the in-place editor does not cause the FieldModel's
    // element to be modified, then nothing needs to happen.
  },

  /**
   * Saves the modified value in the in-place editor for this field.
   */
  save: function () {
    var fieldModel = this.fieldModel;
    var editorModel = this.model;
    var backstageId = 'edit_backstage-' + this.fieldModel.id.replace(/[\/\_\s]/g, '-');

    function fillAndSubmitForm (value) {
      var $form = $('#' + backstageId).find('form');
      // Fill in the value in any <input> that isn't hidden or a submit
      // button.
      $form.find(':input[type!="hidden"][type!="submit"]:not(select)')
        // Don't mess with the node summary.
        .not('[name$="\\[summary\\]"]').val(value);
      // Submit the form.
      $form.find('.edit-form-submit').trigger('click.edit');
    }

    var formOptions = {
      fieldID: this.fieldModel.id,
      $el: this.$el,
      nocssjs: true,
      // Reset an existing entry for this entity in the TempStore (if any) when
      // saving the field. Logically speaking, this should happen in a separate
      // request because this is an entity-level operation, not a field-level
      // operation. But that would require an additional request, that might not
      // even be necessary: it is only when a user saves a first changed field
      // for an entity that this needs to happen: precisely now!
      reset: !this.fieldModel.get('entity').get('inTempStore')
    };

    var self = this;
    Drupal.edit.util.form.load(formOptions, function (form, ajax) {
      // Create a backstage area for storing forms that are hidden from view
      // (hence "backstage" — since the editing doesn't happen in the form, it
      // happens "directly" in the content, the form is only used for saving).
      var $backstage = $(Drupal.theme('editBackstage', { id: backstageId })).appendTo('body');
      // Direct forms are stuffed into the backstage container for this field.
      var $form = $(form).appendTo($backstage);
      // Disable the browser's HTML5 validation; we only care about server-
      // side validation. (Not disabling this will actually cause problems
      // because browsers don't like to set HTML5 validation errors on hidden
      // forms.)
      $form.prop('novalidate', true);
      var $submit = $form.find('.edit-form-submit');
      self.formSaveAjax = Drupal.edit.util.form.ajaxifySaving(formOptions, $submit);

      function removeHiddenForm () {
        Drupal.edit.util.form.unajaxifySaving(self.formSaveAjax);
        delete self.formSaveAjax;
        $backstage.remove();
      }

      // Successfully saved.
      self.formSaveAjax.commands.editFieldFormSaved = function (ajax, response, status) {
        removeHiddenForm();
        // First, transition the state to 'saved'.
        fieldModel.set('state', 'saved');
        // Then, set the 'html' attribute on the field model. This will cause
        // the field to be rerendered.
        fieldModel.set('html', response.data);
      };

      // Unsuccessfully saved; validation errors.
      self.formSaveAjax.commands.editFieldFormValidationErrors = function (ajax, response, status) {
        removeHiddenForm();
        editorModel.set('validationErrors', response.data);
        fieldModel.set('state', 'invalid');
      };

      // The editFieldForm AJAX command is only called upon loading the form
      // for the first time, and when there are validation errors in the form;
      // Form API then marks which form items have errors. This is useful for
      // the form-based in-place editor, but pointless for any other: the form
      // itself won't be visible at all anyway! So, we just ignore it.
      self.formSaveAjax.commands.editFieldForm = function () {};

      fillAndSubmitForm(editorModel.get('currentValue'));
    });
  },

  /**
   * Shows validation error messages.
   *
   * Should be called when the state is changed to 'invalid'.
   */
  showValidationErrors: function () {
    var $errors = $('<div class="edit-validation-errors"></div>')
      .append(this.model.get('validationErrors'));
    this.getEditedElement()
      .addClass('edit-validation-error')
      .after($errors);
  },

  /**
   * Cleans up validation error messages.
   *
   * Should be called when the state is changed to 'candidate' or 'saving'. In
   * the case of the latter: the user has modified the value in the in-place
   * editor again to attempt to save again. In the case of the latter: the
   * invalid value was discarded.
   */
  removeValidationErrors: function () {
    this.getEditedElement()
      .removeClass('edit-validation-error')
      .next('.edit-validation-errors')
      .remove();
  }

});

}(jQuery, _, Backbone, Drupal));
