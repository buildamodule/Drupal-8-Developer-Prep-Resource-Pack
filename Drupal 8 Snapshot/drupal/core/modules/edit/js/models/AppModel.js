(function ($, _, Backbone, Drupal) {

"use strict";

Drupal.edit.AppModel = Backbone.Model.extend({
  defaults: {
    highlightedEditor: null,
    activeEditor: null,
    // Reference to a ModalView-instance if a state change requires
    // confirmation.
    activeModal: null
  }
});

}(jQuery, _, Backbone, Drupal));
