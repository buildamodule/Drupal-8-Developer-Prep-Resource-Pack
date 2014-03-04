(function ($) {

"use strict";

/**
 * A progressbar object. Initialized with the given id. Must be inserted into
 * the DOM afterwards through progressBar.element.
 *
 * method is the function which will perform the HTTP request to get the
 * progress bar state. Either "GET" or "POST".
 *
 * e.g. pb = new Drupal.ProgressBar('myProgressBar');
 *      some_element.appendChild(pb.element);
 */
Drupal.ProgressBar = function (id, updateCallback, method, errorCallback) {
  this.id = id;
  this.method = method || 'GET';
  this.updateCallback = updateCallback;
  this.errorCallback = errorCallback;

  // The WAI-ARIA setting aria-live="polite" will announce changes after users
  // have completed their current activity and not interrupt the screen reader.
  this.element = $('<div class="progress" aria-live="polite"></div>').attr('id', id);
  this.element.html('<div class="progress__label">&nbsp;</div>' +
                    '<div class="progress__track"><div class="progress__bar"></div></div>' +
                    '<div class="progress__percentage"></div>' +
                    '<div class="progress__description">&nbsp;</div>');
};

$.extend(Drupal.ProgressBar.prototype, {
  /**
   * Set the percentage and status message for the progressbar.
   */
  setProgress: function (percentage, message, label) {
    if (percentage >= 0 && percentage <= 100) {
      $(this.element).find('div.progress__bar').css('width', percentage + '%');
      $(this.element).find('div.progress__percentage').html(percentage + '%');
    }
    $('div.progress__description', this.element).html(message);
    $('div.progress__label', this.element).html(label);
    if (this.updateCallback) {
      this.updateCallback(percentage, message, this);
    }
  },

  /**
   * Start monitoring progress via Ajax.
   */
  startMonitoring: function (uri, delay) {
    this.delay = delay;
    this.uri = uri;
    this.sendPing();
  },

  /**
   * Stop monitoring progress via Ajax.
   */
  stopMonitoring: function () {
    clearTimeout(this.timer);
    // This allows monitoring to be stopped from within the callback.
    this.uri = null;
  },

  /**
   * Request progress data from server.
   */
  sendPing: function () {
    if (this.timer) {
      clearTimeout(this.timer);
    }
    if (this.uri) {
      var pb = this;
      // When doing a post request, you need non-null data. Otherwise a
      // HTTP 411 or HTTP 406 (with Apache mod_security) error may result.
      $.ajax({
        type: this.method,
        url: this.uri,
        data: '',
        dataType: 'json',
        success: function (progress) {
          // Display errors.
          if (progress.status === 0) {
            pb.displayError(progress.data);
            return;
          }
          // Update display.
          pb.setProgress(progress.percentage, progress.message, progress.label);
          // Schedule next timer.
          pb.timer = setTimeout(function () { pb.sendPing(); }, pb.delay);
        },
        error: function (xmlhttp) {
          var e = new Drupal.AjaxError(xmlhttp, pb.uri);
          pb.displayError('<pre>' + e.message + '</pre>');
        }
      });
    }
  },

  /**
   * Display errors on the page.
   */
  displayError: function (string) {
    var error = $('<div class="messages messages--error"></div>').html(string);
    $(this.element).before(error).hide();

    if (this.errorCallback) {
      this.errorCallback(this);
    }
  }
});


})(jQuery);
