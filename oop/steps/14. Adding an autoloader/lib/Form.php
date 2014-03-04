<?php

namespace BAM\OOPExampleSite;

//use ThirdParty\Utilities\Validator;
//use ThirdParty\Utilities\Validator as OtherValidator;

class Form {
  
  public $settings;
  public $form_number = 1;
  
  public function __construct($settings) {
    $this->settings = $settings;
  }
  
  /**
   * Builds a form from an array.
   */
  public function build() {
    $output = '';

    // For multiple forms, create a counter.
    $this->form_number++;
    
    // Check for submitted form and validate
    if (isset($_POST['action']) && $_POST['action'] == 'submit_' . $this->form_number) {
      if ($this->validate()) {
        $this->submit();
      }
    }
    
    // Loop through each form element and render it.
    foreach ($this->settings as $name => $settings) {
      $label = '<label>' . $settings['title'] . '</label>';
      switch ($settings['type']) {
        case 'textarea':
          $input = '<textarea name="' . $name . '" ></textarea>';
          break;
        case 'submit':
          $input = '<input type="submit" name="' . $name . '" value="' . $settings['title'] . '">';
          $label = '';
          break;
        default:
          $input = '<input type="' . $settings['type'] . '" name="' . $name . '" />';
          break;
      }
      $output .= $label . '<p>' . $input . '</p>';
    }
    
    // Wrap a form around the inputs.
    $output = '
      <form action="' . $_SERVER['PHP_SELF'] . '" method="post">
        <input type="hidden" name="action" value="submit_' . $this->form_number . '" />
        ' . $output . '
      </form>';
    
    // Return the form.
    return $output;
  }
  
  /**
   * Validates the form based on the 'validations' attribute in the form array.
   */
  private function validate() {
    foreach ($this->settings as $name => $settings) {
      $value = $_POST[$name];
      if (isset($settings['validations'])) {
        foreach ($settings['validations'] as $validation) {
          switch ($validation) {
            
            case 'not_empty':
              //if (!\ThirdParty\Utilities\Validator::notEmpty($value)) {
              //if (!OtherValidator::notEmpty($value)) {
              if (!Validator::notEmpty($value)) {
                return false;
              }
              break;
            
            case 'is_valid_email':
              //if (!\ThirdParty\Utilities\Validator::isValidEmail($value)) {
              //if (!OtherValidator::isValidEmail($value)) {
              if (!Validator::isValidEmail($value)) {
                return false;
              }
              break;
          }
        }
      }
    }
    return true;
  }
  
  /**
   * Once validated, this processes the form.
   */
  private function submit() {
    $output = '';
    foreach ($this->settings as $name => $settings) {
      $value = $_POST[$name];
      $output .= '<li>' . $settings['title'] . ': ' . $value . '</li>';
    }
    $output = '<p>You submitted the following:</p><ul>' . $output . '</ul><br />';
    print $output;
  }
}