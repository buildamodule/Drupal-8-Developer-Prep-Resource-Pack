/**
 * @file
 * A Backbone View that decorates the in-place edited element.
 */
(function ($, Backbone, Drupal) {

"use strict";

Drupal.edit.EditorDecorationView = Backbone.View.extend({

  _widthAttributeIsEmpty: null,

  events: {
    'mouseenter.edit' : 'onMouseEnter',
    'mouseleave.edit' : 'onMouseLeave',
    'click': 'onClick',
    'tabIn.edit': 'onMouseEnter',
    'tabOut.edit': 'onMouseLeave'
  },

  /**
   * {@inheritdoc}
   *
   * @param Object options
   *   An object with the following keys:
   *   - Drupal.edit.EditorView editorView: the editor object view.
   */
  initialize: function (options) {
    this.editorView = options.editorView;

    this.model.on('change:state', this.stateChange, this);
    this.model.on('change:isChanged change:inTempStore', this.renderChanged, this);
  },

  /**
   * {@inheritdoc}
   */
  remove: function () {
    // The el property is the field, which should not be removed. Remove the
    // pointer to it, then call Backbone.View.prototype.remove().
    this.setElement();
    Backbone.View.prototype.remove.call(this);
  },

  /**
   * Determines the actions to take given a change of state.
   *
   * @param Drupal.edit.FieldModel model
   * @param String state
   *   The state of the associated field. One of Drupal.edit.FieldModel.states.
   */
  stateChange: function (model, state) {
    var from = model.previous('state');
    var to = state;
    switch (to) {
      case 'inactive':
        this.undecorate();
        break;
      case 'candidate':
        this.decorate();
        if (from !== 'inactive') {
          this.stopHighlight();
          if (from !== 'highlighted') {
            this.model.set('isChanged', false);
            this.stopEdit();
          }
        }
        this._unpad();
        break;
      case 'highlighted':
        this.startHighlight();
        break;
      case 'activating':
        // NOTE: this state is not used by every editor! It's only used by those
        // that need to interact with the server.
        this.prepareEdit();
        break;
      case 'active':
        if (from !== 'activating') {
          this.prepareEdit();
        }
        if (this.editorView.getEditUISettings().padding) {
          this._pad();
        }
        break;
      case 'changed':
        this.model.set('isChanged', true);
        break;
      case 'saving':
        break;
      case 'saved':
        break;
      case 'invalid':
        break;
    }
  },

  /**
   * Adds a class to the edited element that indicates whether the field has
   * been changed by the user (i.e. locally) or the field has already been
   * changed and stored before by the user (i.e. remotely, stored in TempStore).
   */
  renderChanged: function () {
    this.$el.toggleClass('edit-changed', this.model.get('isChanged') || this.model.get('inTempStore'));
  },

  /**
   * Starts hover; transitions to 'highlight' state.
   *
   * @param jQuery event
   */
  onMouseEnter: function (event) {
    var that = this;
    that.model.set('state', 'highlighted');
    event.stopPropagation();
  },

  /**
   * Stops hover; transitions to 'candidate' state.
   *
   * @param jQuery event
   */
  onMouseLeave: function (event) {
    var that = this;
    that.model.set('state', 'candidate', { reason: 'mouseleave' });
    event.stopPropagation();
  },

  /**
   * Transition to 'activating' stage.
   *
   * @param jQuery event
   */
  onClick: function (event) {
    this.model.set('state', 'activating');
    event.preventDefault();
    event.stopPropagation();
  },

  /**
   * Adds classes used to indicate an elements editable state.
   */
  decorate: function () {
    this.$el.addClass('edit-candidate edit-editable');
  },

  /**
   * Removes classes used to indicate an elements editable state.
   */
  undecorate: function () {
    this.$el.removeClass('edit-candidate edit-editable edit-highlighted edit-editing');
  },

  /**
   * Adds that class that indicates that an element is highlighted.
   */
  startHighlight: function () {
    // Animations.
    var that = this;
    // Use a timeout to grab the next available animation frame.
    that.$el.addClass('edit-highlighted');
  },

  /**
   * Removes the class that indicates that an element is highlighted.
   */
  stopHighlight: function () {
    this.$el.removeClass('edit-highlighted');
  },

  /**
   * Removes the class that indicates that an element as editable.
   */
  prepareEdit: function () {
    this.$el.addClass('edit-editing');
  },

  /**
   * Removes the class that indicates that an element is being edited.
   *
   * Reapplies the class that indicates that a candidate editable element is
   * again available to be edited.
   */
  stopEdit: function () {
    this.$el.removeClass('edit-highlighted edit-editing');

    // Make the other editors show up again.
    $('.edit-candidate').addClass('edit-editable');
  },

  /**
   * Adds padding around the editable element in order to make it pop visually.
   */
  _pad: function () {
    // Early return if the element has already been padded.
    if (this.$el.data('edit-padded')) {
      return;
    }
    var self = this;

    // Add 5px padding for readability. This means we'll freeze the current
    // width and *then* add 5px padding, hence ensuring the padding is added "on
    // the outside".
    // 1) Freeze the width (if it's not already set); don't use animations.
    if (this.$el[0].style.width === "") {
      this._widthAttributeIsEmpty = true;
      this.$el
        .addClass('edit-animate-disable-width')
        .css('width', this.$el.width())
        .css('background-color', this._getBgColor(this.$el));
    }

    // 2) Add padding; use animations.
    var posProp = this._getPositionProperties(this.$el);
    setTimeout(function () {
      // Re-enable width animations (padding changes affect width too!).
      self.$el.removeClass('edit-animate-disable-width');

      // Pad the editable.
      self.$el
      .css({
        'position': 'relative',
        'top':  posProp.top  - 5 + 'px',
        'left': posProp.left - 5 + 'px',
        'padding-top'   : posProp['padding-top']    + 5 + 'px',
        'padding-left'  : posProp['padding-left']   + 5 + 'px',
        'padding-right' : posProp['padding-right']  + 5 + 'px',
        'padding-bottom': posProp['padding-bottom'] + 5 + 'px',
        'margin-bottom':  posProp['margin-bottom'] - 10 + 'px'
      })
      .data('edit-padded', true);
    }, 0);
  },

  /**
   * Removes the padding around the element being edited when editing ceases.
   */
  _unpad: function () {
    // Early return if the element has not been padded.
    if (!this.$el.data('edit-padded')) {
      return;
    }
    var self = this;

    // 1) Set the empty width again.
    if (this._widthAttributeIsEmpty) {
      this.$el
        .addClass('edit-animate-disable-width')
        .css('width', '')
        .css('background-color', '');
    }

    // 2) Remove padding; use animations (these will run simultaneously with)
    // the fading out of the toolbar as its gets removed).
    var posProp = this._getPositionProperties(this.$el);
    setTimeout(function () {
      // Re-enable width animations (padding changes affect width too!).
      self.$el.removeClass('edit-animate-disable-width');

      // Unpad the editable.
      self.$el
      .css({
        'position': 'relative',
        'top':  posProp.top  + 5 + 'px',
        'left': posProp.left + 5 + 'px',
        'padding-top'   : posProp['padding-top']    - 5 + 'px',
        'padding-left'  : posProp['padding-left']   - 5 + 'px',
        'padding-right' : posProp['padding-right']  - 5 + 'px',
        'padding-bottom': posProp['padding-bottom'] - 5 + 'px',
        'margin-bottom': posProp['margin-bottom'] + 10 + 'px'
      });
    }, 0);
    // Remove the marker that indicates that this field has padding. This is
    // done outside the timed out function above so that we don't get numerous
    // queued functions that will remove padding before the data marker has
    // been removed.
    this.$el.removeData('edit-padded');
  },

  /**
   * Gets the background color of an element (or the inherited one).
   *
   * @param DOM $e
   */
  _getBgColor: function ($e) {
    var c;

    if ($e === null || $e[0].nodeName === 'HTML') {
      // Fallback to white.
      return 'rgb(255, 255, 255)';
    }
    c = $e.css('background-color');
    // TRICKY: edge case for Firefox' "transparent" here; this is a
    // browser bug: https://bugzilla.mozilla.org/show_bug.cgi?id=635724
    if (c === 'rgba(0, 0, 0, 0)' || c === 'transparent') {
      return this._getBgColor($e.parent());
    }
    return c;
  },

  /**
   * Gets the top and left properties of an element.
   *
   * Convert extraneous values and information into numbers ready for
   * subtraction.
   *
   * @param DOM $e
   */
  _getPositionProperties: function ($e) {
    var p,
        r = {},
        props = [
          'top', 'left', 'bottom', 'right',
          'padding-top', 'padding-left', 'padding-right', 'padding-bottom',
          'margin-bottom'
        ];

    var propCount = props.length;
    for (var i = 0; i < propCount; i++) {
      p = props[i];
      r[p] = parseInt(this._replaceBlankPosition($e.css(p)), 10);
    }
    return r;
  },

  /**
   * Replaces blank or 'auto' CSS "position: <value>" values with "0px".
   *
   * @param String pos
   *   (optional) The value for a CSS position declaration.
   */
  _replaceBlankPosition: function (pos) {
    if (pos === 'auto' || !pos) {
      pos = '0px';
    }
    return pos;
  }
});

})(jQuery, Backbone, Drupal);
