<?php

class Form {
  
  public $settings;
  
  function __construct($settings) {
    $this->settings = $settings;
  }
  
  function build() {
  }
  
  function validate() {
  }
  
  function submit() {
  }
}

class Page {
  
  public $settings;
  
  function __construct($settings) {
    $this->settings = $settings;
  }
  
  function build() {
  }
  
  function theme() {
  }
}



/**
 * Builds a form from an array.
 */
function build_form($elements) {
  static $form_number;
  $output = '';
  
  // For multiple forms, create a counter.
  $form_number = isset($form_number) ? 1 : $form_number + 1;
  
  // Check for submitted form and validate
  if (isset($_POST['action']) && $_POST['action'] == 'submit_' . $form_number) {
    if (validate_form($elements)) {
      submit_form($elements);
    }
  }

  // Loop through each form element and render it.
  foreach ($elements as $name => $settings) {
    switch ($settings['type']) {
      case 'textarea':
        $input = '<textarea name="' . $name . '" ></textarea>';
        break;
      case 'submit':
        $input = '<input type="submit" name="' . $name . '" value="' . $settings['title'] . '">';
        $label = '';
      default:
        $input = '<input type="' . $settings['type'] . '" name="' . $name . '" />';
        break;
    }
    $output .= '<label>' . $settings['title'] . '</label><p>' . $input . '</p>';
  }
  
  // Wrap a form around the inputs.
  $output = '
    <form action="' . $_SERVER['PHP_SELF'] . '" method="post">
      <input type="hidden" name="action" value="submit_' . $form_number . '" />
      ' . $output . '
    </form>';
  
  // Return the form.
  return $output;
}

/**
 * Validates the form base on the 'validations' attribute in the form array.
 */
function validate_form($elements) {
  foreach ($elements as $name => $settings) {
    $value = $_POST[$name];
    if (isset($settings['validations'])) {
      foreach ($settings['validations'] as $validation) {
        switch ($validation) {
          
          // Check to make sure the value is not empty.
          case 'not_empty':
            if (trim($value) == '') {
              return false;
            }
            break;
          
          // Check for a valid email address.
          case 'is_valid_email':
            if (!strstr($value, '@')) {
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
function submit_form($elements) {
  $output = '';
  foreach ($elements as $name => $settings) {
    $value = $_POST[$name];
    $output .= '<li>' . $settings['title'] . ': ' . $value . '</li>';
  }
  $output = '<p>You submitted the following:</p><ul>' . $output . '</ul><br />';
  print $output;
}

/**
 * Builds out the content of a page based on the type of elements passed to it.
 */
function build_page($elements) {
  $output = '';
  
  foreach ($elements as $id => $values) {
    switch ($values['type']) {
      case 'html':
        $output .= '<div id="' . $id . '">' . $values['value'] . '</div>';
        break;
      case 'form':
        $output .= build_form($values['value']);
        break;
    }
  }
  return $output;
}

/**
 * Renders the page content based on a simple template.
 */
function theme_page($output, $title) {
  return '
    <html>
      <head>
        <title>' . $title . '</title>
      </head>
      <body>
        ' . $output . '
      </body>
    </html>';
}

// Create an array for the contact form.
$contact_form = array(
  'name' => array(
    'title' => 'Name',
    'type' => 'text',
    'validations' => array('not_empty'),
  ),
  'email' => array(
    'title' => 'Email',
    'type' => 'email',
    'validations' => array('not_empty', 'is_valid_email'),
  ),
  'comment' => array(
    'title' => 'Comments',
    'type' => 'textarea',
    'validations' => array('not_empty'),
  ),
  'submit' => array(
    'title' => 'Submit me!',
    'type' => 'submit',
  ),
);

// Create an array for the page.
$page_elements = array(
  'header' => array(
    'type' => 'html',
    'value' => '<p>Please submit this form. You will make my day if you do.</p>',
  ),
  'contact_form' => array(
    'type' => 'form',
    'value' => $contact_form,
  ),
);

// Render the page content.
$page_content = build_page($page_elements);
print theme_page($page_content, 'Contact us');