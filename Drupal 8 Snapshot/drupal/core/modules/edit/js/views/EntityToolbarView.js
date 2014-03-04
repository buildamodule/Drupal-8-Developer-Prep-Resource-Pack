/**
 * @file
 * A Backbone View that provides an entity level toolbar.
 */
(function ($, Backbone, Drupal, debounce) {

"use strict";

Drupal.edit.EntityToolbarView = Backbone.View.extend({

  _fieldToolbarRoot: null,

  events: function () {
    var map = {
      'click.edit button.action-save': 'onClickSave',
      'click.edit button.action-cancel': 'onClickCancel',
      'mouseenter.edit': 'onMouseenter'
    };
    return map;
  },

  /**
   * {@inheritdoc}
   */
  initialize: function (options) {
    var that = this;
    this.appModel = options.appModel;
    this.$entity = $(this.model.get('el'));

    // Rerender whenever the entity state changes.
    this.model.on('change:isActive change:isDirty change:state', this.render, this);
    // Also rerender whenever the highlighted or active in-place editor changes.
    this.appModel.on('change:highlightedEditor change:activeEditor', this.render, this);
    // Rerender when a field of the entity changes state.
    this.model.get('fields').on('change:state', this.fieldStateChange, this);

    // Reposition the entity toolbar as the viewport and the position within the
    // viewport changes.
    $(window).on('resize.edit scroll.edit', debounce($.proxy(this.windowChangeHandler, this), 150));

    // Adjust the fence placement within which the entity toolbar may be
    // positioned.
    $(document).on('drupalViewportOffsetChange.edit', function (event, offsets) {
      if (that.$fence) {
        that.$fence.css(offsets);
      }
    });

    // Set the entity toolbar DOM element as the el for this view.
    var $toolbar = this.buildToolbarEl();
    this.setElement($toolbar);
    this._fieldToolbarRoot = $toolbar.find('.edit-toolbar-field').get(0);

    // Initial render.
    this.render();
  },

  /**
   * {@inheritdoc}
   */
  render: function () {
    if (this.model.get('isActive')) {
      // If the toolbar container doesn't exist, create it.
      var $body = $('body');
      if ($body.children('#edit-entity-toolbar').length === 0) {
        $body.append(this.$el);
      }
      // The fence will define a area on the screen that the entity toolbar
      // will be position within.
      if ($body.children('#edit-toolbar-fence').length === 0) {
        this.$fence = $(Drupal.theme('editEntityToolbarFence'))
          .css(Drupal.displace())
          .appendTo($body);
      }
      // Adds the entity title to the toolbar.
      this.label();

      // Show the save and cancel buttons.
      this.show('ops');
      // If render is being called and the toolbar is already visible, just
      // reposition it.
      this.position();
    }

    // The save button text and state varies with the state of the entity model.
    var $button = this.$el.find('.edit-button.action-save');
    var isDirty = this.model.get('isDirty');
    // Adjust the save button according to the state of the model.
    switch (this.model.get('state')) {
      // Quick editing is active, but no field is being edited.
      case 'opened':
        // The saving throbber is not managed by AJAX system. The
        // EntityToolbarView manages this visual element.
        $button
          .removeClass('action-saving icon-throbber icon-end')
          .text(Drupal.t('Save'))
          .removeAttr('disabled')
          .attr('aria-hidden', !isDirty);
        break;
      // The changes to the fields of the entity are being committed.
      case 'committing':
        $button
          .addClass('action-saving icon-throbber icon-end')
          .text(Drupal.t('Saving'))
          .attr('disabled', 'disabled');
        break;
      default:
        $button.attr('aria-hidden', true);
        break;
    }

    return this;
  },

  /**
   * {@inheritdoc}
   */
  remove: function () {
    this.$fence.remove();
    Backbone.View.prototype.remove.call(this);
  },

  /**
   * Repositions the entity toolbar on window scroll and resize.
   *
   * @param jQuery.Eevent event
   */
  windowChangeHandler: function (event) {
    this.position();
  },

  /**
   * Determines the actions to take given a change of state.
   *
   * @param Drupal.edit.FieldModel model
   * @param String state
   *   The state of the associated field. One of Drupal.edit.FieldModel.states.
   */
  fieldStateChange: function (model, state) {
    switch (state) {
      case 'active':
        this.render();
        break;
      case 'invalid':
        this.render();
        break;
    }
  },

  /**
   * Uses the jQuery.ui.position() method to position the entity toolbar.
   *
   * @param jQuery|DOM element
   *   (optional) The element against which the entity toolbar is positioned.
   */
  position: function (element) {
    clearTimeout(this.timer);

    var that = this;
    // Vary the edge of the positioning according to the direction of language
    // in the document.
    var edge = (document.documentElement.dir === 'rtl') ? 'right' : 'left';
    // A time unit to wait until the entity toolbar is repositioned.
    var delay = 0;
    // Determines what check in the series of checks below should be evaluated
    var check = 0;
    var of, activeField, highlightedField;
    // There are several elements in the page that the entity toolbar might be
    // positioned against. They are considered below in a priority order.
    do {
      switch (check) {
        case 0:
          // Position against a specific element.
          of = element;
          break;
        case 1:
          // Position against a form container.
          activeField = Drupal.edit.app.model.get('activeEditor');
          of = activeField && activeField.editorView && activeField.editorView.$formContainer && activeField.editorView.$formContainer.find('.edit-form');
          break;
        case 2:
          // Position against an active field.
          of = activeField && activeField.editorView && activeField.editorView.getEditedElement();
          break;
        case 3:
          // Position against a highlighted field.
          highlightedField = Drupal.edit.app.model.get('highlightedEditor');
          of = highlightedField && highlightedField.editorView && highlightedField.editorView.getEditedElement();
          delay = 250;
          break;
        default:
          // Position against the entity, or as a last resort, the body element.
          of = this.$entity || 'body';
          delay = 750;
          break;
      }
      // Prepare to check the next possible element to position against.
      check++;
    } while (!of);

    /**
     * Refines the positioning algorithm of jquery.ui.position().
     *
     * Invoked as the 'using' callback of jquery.ui.position() in
     * positionToolbar().
     *
     * @param Object suggested
     *   A hash of top and left values for the position that should be set. It
     *   can be forwarded to .css() or .animate().
     * @param Object info
     *   The position and dimensions of both the 'my' element and the 'of'
     *   elements, as well as calculations to their relative position. This
     *   object contains the following properties:
     *     - Object element: A hash that contains information about the HTML
     *     element that will be positioned. Also known as the 'my' element.
     *     - Object target: A hash that contains information about the HTML
     *     element that the 'my' element will be positioned against. Also known
     *     as the 'of' element.
     */
    function refinePosition (suggested, info) {
      info.element.element.css({
        left: Math.floor(suggested.left),
        top: Math.floor(suggested.top)
      });
      // Determine if the pointer should be on the top or bottom.
      info.element.element.toggleClass('edit-toolbar-pointer-top', info.element.top > info.target.top);
    }

    /**
     * Calls the jquery.ui.position() method on the $el of this view.
     */
    function positionToolbar () {
      that.$el
        .position({
          my: edge + ' bottom',
          // Move the toolbar 2px towards the start edge of the 'of' element.
          at: edge + '+1 top',
          of: of,
          collision: 'flipfit',
          using: refinePosition,
          within: that.$fence
        })
        // Resize the toolbar to match the dimensions of the field, up to a
        // maximum width that is equal to 90% of the field's width.
        .css({
          'max-width': (document.documentElement.clientWidth < 450) ? document.documentElement.clientWidth : 450,
          // Set a minimum width of 240px for the entity toolbar, or the width
          // of the client if it is less than 240px, so that the toolbar
          // never folds up into a squashed and jumbled mess.
          'min-width': (document.documentElement.clientWidth < 240) ? document.documentElement.clientWidth : 240,
          'width': '100%'
        });
    }

    // Uses the jQuery.ui.position() method. Use a timeout to move the toolbar
    // only after the user has focused on an editable for 250ms. This prevents
    // the toolbar from jumping around the screen.
    this.timer = setTimeout(function () {
      // Render the position in the next execution cycle, so that animations on
      // the field have time to process. This is not strictly speaking, a
      // guarantee that all animations will be finished, but it's a simple way
      // to get better positioning without too much additional code.
      _.defer(positionToolbar);
    }, delay);
  },

  /**
   * Set the model state to 'saving' when the save button is clicked.
   *
   * @param jQuery event
   */
  onClickSave: function (event) {
    event.stopPropagation();
    event.preventDefault();
    // Save the model.
    this.model.set('state', 'committing');
  },

  /**
   * Sets the model state to candidate when the cancel button is clicked.
   *
   * @param jQuery event
   */
  onClickCancel: function (event) {
    event.preventDefault();
    this.model.set('state', 'deactivating');
  },

  /**
   * Clears the timeout that will eventually reposition the entity toolbar.
   *
   * Without this, it may reposition itself, away from the user's cursor!
   *
   * @param jQuery event
   */
  onMouseenter: function (event) {
    clearTimeout(this.timer);
  },

  /**
   * Builds the entity toolbar HTML; attaches to DOM; sets starting position.
   */
  buildToolbarEl: function () {
    var $toolbar = $(Drupal.theme('editEntityToolbar', {
      id: 'edit-entity-toolbar'
    }));

    $toolbar
      .find('.edit-toolbar-entity')
      // Append the "ops" toolgroup into the toolbar.
      .prepend(Drupal.theme('editToolgroup', {
        classes: ['ops'],
        buttons: [
          {
            label: Drupal.t('Save'),
            type: 'submit',
            classes: 'action-save edit-button icon',
            attributes: {
              'aria-hidden': true
            }
          },
          {
            label: Drupal.t('Close'),
            classes: 'action-cancel edit-button icon icon-close icon-only'
          }
        ]
      }));

    // Give the toolbar a sensible starting position so that it doesn't animate
    // on to the screen from a far off corner.
    $toolbar
      .css({
        left: this.$entity.offset().left,
        top: this.$entity.offset().top
      });

    return $toolbar;
  },

  /**
   * Returns the DOM element that fields will attach their toolbars to.
   *
   * @return jQuery
   *   The DOM element that fields will attach their toolbars to.
   */
  getToolbarRoot: function () {
    return this._fieldToolbarRoot;
  },

  /**
   * Generates a state-dependent label for the entity toolbar.
   */
  label: function () {
    // The entity label.
    var label = '';
    var entityLabel = this.model.get('label');

    // Label of an active field, if it exists.
    var activeEditor = Drupal.edit.app.model.get('activeEditor');
    var activeFieldLabel = activeEditor && activeEditor.get('metadata').label;
    // Label of a highlighted field, if it exists.
    var highlightedEditor = Drupal.edit.app.model.get('highlightedEditor');
    var highlightedFieldLabel = highlightedEditor && highlightedEditor.get('metadata').label;
    // The label is constructed in a priority order.
    if (activeFieldLabel) {
      label = Drupal.theme('editEntityToolbarLabel', {
        entityLabel: entityLabel,
        fieldLabel: activeFieldLabel
      });
    }
    else if (highlightedFieldLabel) {
      label = Drupal.theme('editEntityToolbarLabel', {
        entityLabel: entityLabel,
        fieldLabel: highlightedFieldLabel
      });
    }
    else {
      label = entityLabel;
    }

    this.$el
      .find('.edit-toolbar-label')
      .html(label);
  },

  /**
   * Adds classes to a toolgroup.
   *
   * @param String toolgroup
   *   A toolgroup name.
   * @param String classes
   *   A string of space-delimited class names that will be applied to the
   *   wrapping element of the toolbar group.
   */
  addClass: function (toolgroup, classes) {
    this._find(toolgroup).addClass(classes);
  },

  /**
   * Removes classes from a toolgroup.
   *
   * @param String toolgroup
   *   A toolgroup name.
   * @param String classes
   *   A string of space-delimited class names that will be removed from the
   *   wrapping element of the toolbar group.
   */
  removeClass: function (toolgroup, classes) {
    this._find(toolgroup).removeClass(classes);
  },

  /**
   * Finds a toolgroup.
   *
   * @param String toolgroup
   *   A toolgroup name.
   * @return jQuery
   *   The toolgroup DOM element.
   */
  _find: function (toolgroup) {
    return this.$el.find('.edit-toolbar .edit-toolgroup.' + toolgroup);
  },

  /**
   * Shows a toolgroup.
   *
   * @param String toolgroup
   *   A toolgroup name.
   */
  show: function (toolgroup) {
    this.$el.removeClass('edit-animate-invisible');
  }
});

})(jQuery, Backbone, Drupal, Drupal.debounce);
