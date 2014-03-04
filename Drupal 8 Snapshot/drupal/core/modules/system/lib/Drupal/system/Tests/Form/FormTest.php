<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Form\FormTest.
 */

namespace Drupal\system\Tests\Form;

use Drupal\simpletest\WebTestBase;

class FormTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('form_test', 'file', 'datetime');

  public static function getInfo() {
    return array(
      'name' => 'Form element validation',
      'description' => 'Tests various form element validation mechanisms.',
      'group' => 'Form API',
    );
  }

  function setUp() {
    parent::setUp();

    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ));
    $filtered_html_format->save();

    $filtered_html_permission = filter_permission_name($filtered_html_format);
    user_role_grant_permissions(DRUPAL_ANONYMOUS_RID, array($filtered_html_permission));
  }

  /**
   * Check several empty values for required forms elements.
   *
   * Carriage returns, tabs, spaces, and unchecked checkbox elements are not
   * valid content for a required field.
   *
   * If the form field is found in form_get_errors() then the test pass.
   */
  function testRequiredFields() {
    // Originates from http://drupal.org/node/117748
    // Sets of empty strings and arrays.
    $empty_strings = array('""' => "", '"\n"' => "\n", '" "' => " ", '"\t"' => "\t", '" \n\t "' => " \n\t ", '"\n\n\n\n\n"' => "\n\n\n\n\n");
    $empty_arrays = array('array()' => array());
    $empty_checkbox = array(NULL);

    $elements['textfield']['element'] = array('#title' => $this->randomName(), '#type' => 'textfield');
    $elements['textfield']['empty_values'] = $empty_strings;

    $elements['telephone']['element'] = array('#title' => $this->randomName(), '#type' => 'tel');
    $elements['telephone']['empty_values'] = $empty_strings;

    $elements['url']['element'] = array('#title' => $this->randomName(), '#type' => 'url');
    $elements['url']['empty_values'] = $empty_strings;

    $elements['search']['element'] = array('#title' => $this->randomName(), '#type' => 'search');
    $elements['search']['empty_values'] = $empty_strings;

    $elements['password']['element'] = array('#title' => $this->randomName(), '#type' => 'password');
    $elements['password']['empty_values'] = $empty_strings;

    $elements['password_confirm']['element'] = array('#title' => $this->randomName(), '#type' => 'password_confirm');
    // Provide empty values for both password fields.
    foreach ($empty_strings as $key => $value) {
      $elements['password_confirm']['empty_values'][$key] = array('pass1' => $value, 'pass2' => $value);
    }

    $elements['textarea']['element'] = array('#title' => $this->randomName(), '#type' => 'textarea');
    $elements['textarea']['empty_values'] = $empty_strings;

    $elements['radios']['element'] = array('#title' => $this->randomName(), '#type' => 'radios', '#options' => array('' => t('None'), $this->randomName(), $this->randomName(), $this->randomName()));
    $elements['radios']['empty_values'] = $empty_arrays;

    $elements['checkbox']['element'] = array('#title' => $this->randomName(), '#type' => 'checkbox', '#required' => TRUE);
    $elements['checkbox']['empty_values'] = $empty_checkbox;

    $elements['checkboxes']['element'] = array('#title' => $this->randomName(), '#type' => 'checkboxes', '#options' => array($this->randomName(), $this->randomName(), $this->randomName()));
    $elements['checkboxes']['empty_values'] = $empty_arrays;

    $elements['select']['element'] = array('#title' => $this->randomName(), '#type' => 'select', '#options' => array('' => t('None'), $this->randomName(), $this->randomName(), $this->randomName()));
    $elements['select']['empty_values'] = $empty_strings;

    $elements['file']['element'] = array('#title' => $this->randomName(), '#type' => 'file');
    $elements['file']['empty_values'] = $empty_strings;

    // Regular expression to find the expected marker on required elements.
    $required_marker_preg = '@<label.*<abbr class="form-required" title="This field is required\.">\*</abbr></label>@';

    // Go through all the elements and all the empty values for them.
    foreach ($elements as $type => $data) {
      foreach ($data['empty_values'] as $key => $empty) {
        foreach (array(TRUE, FALSE) as $required) {
          $form_id = $this->randomName();
          $form = array();
          $form_state = form_state_defaults();
          form_clear_error();
          $form['op'] = array('#type' => 'submit', '#value' => t('Submit'));
          $element = $data['element']['#title'];
          $form[$element] = $data['element'];
          $form[$element]['#required'] = $required;
          $form_state['input'][$element] = $empty;
          $form_state['input']['form_id'] = $form_id;
          $form_state['method'] = 'post';
          drupal_prepare_form($form_id, $form, $form_state);
          drupal_process_form($form_id, $form, $form_state);
          $errors = form_get_errors();
          // Form elements of type 'radios' throw all sorts of PHP notices
          // when you try to render them like this, so we ignore those for
          // testing the required marker.
          // @todo Fix this work-around (http://drupal.org/node/588438).
          $form_output = ($type == 'radios') ? '' : drupal_render($form);
          if ($required) {
            // Make sure we have a form error for this element.
            $this->assertTrue(isset($errors[$element]), "Check empty($key) '$type' field '$element'");
            if (!empty($form_output)) {
              // Make sure the form element is marked as required.
              $this->assertTrue(preg_match($required_marker_preg, $form_output), "Required '$type' field is marked as required");
            }
          }
          else {
            if (!empty($form_output)) {
              // Make sure the form element is *not* marked as required.
              $this->assertFalse(preg_match($required_marker_preg, $form_output), "Optional '$type' field is not marked as required");
            }
            if ($type == 'select') {
              // Select elements are going to have validation errors with empty
              // input, since those are illegal choices. Just make sure the
              // error is not "field is required".
              $this->assertTrue((empty($errors[$element]) || strpos('field is required', $errors[$element]) === FALSE), "Optional '$type' field '$element' is not treated as a required element");
            }
            else {
              // Make sure there is *no* form error for this element.
              $this->assertTrue(empty($errors[$element]), "Optional '$type' field '$element' has no errors with empty input");
            }
          }
        }
      }
    }
    // Clear the expected form error messages so they don't appear as exceptions.
    drupal_get_messages();
  }

  /**
   * Tests validation for required checkbox, select, and radio elements.
   *
   * Submits a test form containing several types of form elements. The form
   * is submitted twice, first without values for required fields and then
   * with values. Each submission is checked for relevant error messages.
   *
   * @see form_test_validate_required_form()
   */
  function testRequiredCheckboxesRadio() {
    $form = $form_state = array();
    $form = form_test_validate_required_form($form, $form_state);

    // Attempt to submit the form with no required fields set.
    $edit = array();
    $this->drupalPost('form-test/validate-required', $edit, 'Submit');

    // The only error messages that should appear are the relevant 'required'
    // messages for each field.
    $expected = array();
    foreach (array('textfield', 'checkboxes', 'select', 'radios') as $key) {
      if (isset($form[$key]['#required_error'])) {
        $expected[] = $form[$key]['#required_error'];
      }
      elseif (isset($form[$key]['#form_test_required_error'])) {
        $expected[] = $form[$key]['#form_test_required_error'];
      }
      else {
        $expected[] = t('!name field is required.', array('!name' => $form[$key]['#title']));
      }
    }

    // Check the page for error messages.
    $errors = $this->xpath('//div[contains(@class, "error")]//li');
    foreach ($errors as $error) {
      $expected_key = array_search($error[0], $expected);
      // If the error message is not one of the expected messages, fail.
      if ($expected_key === FALSE) {
        $this->fail(format_string("Unexpected error message: @error", array('@error' => $error[0])));
      }
      // Remove the expected message from the list once it is found.
      else {
        unset($expected[$expected_key]);
      }
    }

    // Fail if any expected messages were not found.
    foreach ($expected as $not_found) {
      $this->fail(format_string("Found error message: @error", array('@error' => $not_found)));
    }

    // Verify that input elements are still empty.
    $this->assertFieldByName('textfield', '');
    $this->assertNoFieldChecked('edit-checkboxes-foo');
    $this->assertNoFieldChecked('edit-checkboxes-bar');
    $this->assertOptionSelected('edit-select', '');
    $this->assertNoFieldChecked('edit-radios-foo');
    $this->assertNoFieldChecked('edit-radios-bar');
    $this->assertNoFieldChecked('edit-radios-optional-foo');
    $this->assertNoFieldChecked('edit-radios-optional-bar');
    $this->assertNoFieldChecked('edit-radios-optional-default-value-false-foo');
    $this->assertNoFieldChecked('edit-radios-optional-default-value-false-bar');

    // Submit again with required fields set and verify that there are no
    // error messages.
    $edit = array(
      'textfield' => $this->randomString(),
      'checkboxes[foo]' => TRUE,
      'select' => 'foo',
      'radios' => 'bar',
    );
    $this->drupalPost(NULL, $edit, 'Submit');
    $this->assertNoFieldByXpath('//div[contains(@class, "error")]', FALSE, 'No error message is displayed when all required fields are filled.');
    $this->assertRaw("The form_test_validate_required_form form was submitted successfully.", 'Validation form submitted successfully.');
  }

  /**
   * Tests validation for required textfield element without title.
   *
   * Submits a test form containing a textfield form element without title.
   * The form is submitted twice, first without value for the required field
   * and then with value. Each submission is checked for relevant error
   * messages.
   *
   * @see form_test_validate_required_form_no_title()
   */
  function testRequiredTextfieldNoTitle() {
    $form = $form_state = array();
    $form = form_test_validate_required_form_no_title($form, $form_state);

    // Attempt to submit the form with no required field set.
    $edit = array();
    $this->drupalPost('form-test/validate-required-no-title', $edit, 'Submit');
    $this->assertNoRaw("The form_test_validate_required_form_no_title form was submitted successfully.", 'Validation form submitted successfully.');

    // Check the page for the error class on the textfield.
    $this->assertFieldByXPath('//input[contains(@class, "error")]', FALSE, 'Error input form element class found.');

    // Check the page for the aria-invalid attribute on the textfield.
    $this->assertFieldByXPath('//input[contains(@aria-invalid, "true")]', FALSE, 'Aria invalid attribute found.');

    // Submit again with required fields set and verify that there are no
    // error messages.
    $edit = array(
      'textfield' => $this->randomString(),
    );
    $this->drupalPost(NULL, $edit, 'Submit');
    $this->assertNoFieldByXpath('//input[contains(@class, "error")]', FALSE, 'No error input form element class found.');
    $this->assertRaw("The form_test_validate_required_form_no_title form was submitted successfully.", 'Validation form submitted successfully.');
  }

  /**
   * Test default value handling for checkboxes.
   *
   * @see _form_test_checkbox()
   */
  function testCheckboxProcessing() {
    // First, try to submit without the required checkbox.
    $edit = array();
    $this->drupalPost('form-test/checkbox', $edit, t('Submit'));
    $this->assertRaw(t('!name field is required.', array('!name' => 'required_checkbox')), 'A required checkbox is actually mandatory');

    // Now try to submit the form correctly.
    $values = drupal_json_decode($this->drupalPost(NULL, array('required_checkbox' => 1), t('Submit')));
    $expected_values = array(
      'disabled_checkbox_on' => 'disabled_checkbox_on',
      'disabled_checkbox_off' => '',
      'checkbox_on' => 'checkbox_on',
      'checkbox_off' => '',
      'zero_checkbox_on' => '0',
      'zero_checkbox_off' => '',
    );
    foreach ($expected_values as $widget => $expected_value) {
      $this->assertEqual($values[$widget], $expected_value, format_string('Checkbox %widget returns expected value (expected: %expected, got: %value)', array(
        '%widget' => var_export($widget, TRUE),
        '%expected' => var_export($expected_value, TRUE),
        '%value' => var_export($values[$widget], TRUE),
      )));
    }
  }

  /**
   * Tests validation of #type 'select' elements.
   */
  function testSelect() {
    $form = $form_state = array();
    $form = form_test_select($form, $form_state);
    $error = '!name field is required.';
    $this->drupalGet('form-test/select');

    // Posting without any values should throw validation errors.
    $this->drupalPost(NULL, array(), 'Submit');
    $this->assertNoText(t($error, array('!name' => $form['select']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['select_required']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['select_optional']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['empty_value']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['empty_value_one']['#title'])));
    $this->assertText(t($error, array('!name' => $form['no_default']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['no_default_optional']['#title'])));
    $this->assertText(t($error, array('!name' => $form['no_default_empty_option']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['no_default_empty_option_optional']['#title'])));
    $this->assertText(t($error, array('!name' => $form['no_default_empty_value']['#title'])));
    $this->assertText(t($error, array('!name' => $form['no_default_empty_value_one']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['no_default_empty_value_optional']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['multiple']['#title'])));
    $this->assertNoText(t($error, array('!name' => $form['multiple_no_default']['#title'])));
    $this->assertText(t($error, array('!name' => $form['multiple_no_default_required']['#title'])));

    // Post values for required fields.
    $edit = array(
      'no_default' => 'three',
      'no_default_empty_option' => 'three',
      'no_default_empty_value' => 'three',
      'no_default_empty_value_one' => 'three',
      'multiple_no_default_required[]' => 'three',
    );
    $this->drupalPost(NULL, $edit, 'Submit');
    $values = drupal_json_decode($this->drupalGetContent());

    // Verify expected values.
    $expected = array(
      'select' => 'one',
      'empty_value' => 'one',
      'empty_value_one' => 'one',
      'no_default' => 'three',
      'no_default_optional' => 'one',
      'no_default_optional_empty_value' => '',
      'no_default_empty_option' => 'three',
      'no_default_empty_option_optional' => '',
      'no_default_empty_value' => 'three',
      'no_default_empty_value_one' => 'three',
      'no_default_empty_value_optional' => 0,
      'multiple' => array('two' => 'two'),
      'multiple_no_default' => array(),
      'multiple_no_default_required' => array('three' => 'three'),
    );
    foreach ($expected as $key => $value) {
      $this->assertIdentical($values[$key], $value, format_string('@name: @actual is equal to @expected.', array(
        '@name' => $key,
        '@actual' => var_export($values[$key], TRUE),
        '@expected' => var_export($value, TRUE),
      )));
    }
  }

  /**
   * Tests a select element when #options is not set.
   */
  function testEmptySelect() {
    $this->drupalGet('form-test/empty-select');
    $this->assertFieldByXPath("//select[1]", NULL, 'Select element found.');
    $this->assertNoFieldByXPath("//select[1]/option", NULL, 'No option element found.');
  }

  /**
   * Tests validation of #type 'number' and 'range' elements.
   */
  function testNumber() {
    $form = $form_state = array();
    $form = form_test_number($form, $form_state);

    // Array with all the error messages to be checked.
    $error_messages = array(
      'no_number' => '%name must be a number.',
      'too_low' => '%name must be higher or equal to %min.',
      'too_high' => '%name must be below or equal to %max.',
      'step_mismatch' => '%name is not a valid number.',
    );

    // The expected errors.
    $expected = array(
      'integer_no_number' => 'no_number',
      'integer_no_step' => 0,
      'integer_no_step_step_error' => 'step_mismatch',
      'integer_step' => 0,
      'integer_step_error' => 'step_mismatch',
      'integer_step_min' => 0,
      'integer_step_min_error' => 'too_low',
      'integer_step_max' => 0,
      'integer_step_max_error' => 'too_high',
      'integer_step_min_border' => 0,
      'integer_step_max_border' => 0,
      'integer_step_based_on_min' => 0,
      'integer_step_based_on_min_error' => 'step_mismatch',
      'float_small_step' => 0,
      'float_step_no_error' => 0,
      'float_step_error' => 'step_mismatch',
      'float_step_hard_no_error' => 0,
      'float_step_hard_error' => 'step_mismatch',
      'float_step_any_no_error' => 0,
    );

    // First test the number element type, then range.
    foreach (array('form-test/number', 'form-test/number/range') as $path) {
      // Post form and show errors.
      $this->drupalPost($path, array(), 'Submit');

      foreach ($expected as $element => $error) {
        // Create placeholder array.
        $placeholders = array(
          '%name' => $form[$element]['#title'],
          '%min' => isset($form[$element]['#min']) ? $form[$element]['#min'] : '0',
          '%max' => isset($form[$element]['#max']) ? $form[$element]['#max'] : '0',
        );

        foreach ($error_messages as $id => $message) {
          // Check if the error exists on the page, if the current message ID is
          // expected. Otherwise ensure that the error message is not present.
          if ($id === $error) {
            $this->assertRaw(format_string($message, $placeholders));
          }
          else {
            $this->assertNoRaw(format_string($message, $placeholders));
          }
        }
      }
    }
  }

  /**
   * Tests default value handling of #type 'range' elements.
   */
  function testRange() {
    $values = json_decode($this->drupalPost('form-test/range', array(), 'Submit'));
    $this->assertEqual($values->with_default_value, 18);
    $this->assertEqual($values->float, 10.5);
    $this->assertEqual($values->integer, 6);
    $this->assertEqual($values->offset, 6.9);

    $this->drupalPost('form-test/range/invalid', array(), 'Submit');
    $this->assertFieldByXPath('//input[@type="range" and contains(@class, "error")]', NULL, 'Range element has the error class.');
  }

  /**
   * Tests validation of #type 'color' elements.
   */
  function testColorValidation() {
    // Keys are inputs, values are expected results.
    $values = array(
      '' => '#000000',
      '#000' => '#000000',
      'AAA' => '#aaaaaa',
      '#af0DEE' => '#af0dee',
      '#99ccBc' => '#99ccbc',
      '#aabbcc' => '#aabbcc',
      '123456' => '#123456',
    );

    // Tests that valid values are properly normalized.
    foreach ($values as $input => $expected) {
      $edit = array(
        'color' => $input,
      );
      $result = json_decode($this->drupalPost('form-test/color', $edit, 'Submit'));
      $this->assertEqual($result->color, $expected);
    }

    // Tests invalid values are rejected.
    $values = array('#0008', '#1234', '#fffffg', '#abcdef22', '17', '#uaa');
    foreach ($values as $input) {
      $edit = array(
        'color' => $input,
      );
      $this->drupalPost('form-test/color', $edit, 'Submit');
      $this->assertRaw(t('%name must be a valid color.', array('%name' => 'Color')));
    }
  }

  /**
   * Test handling of disabled elements.
   *
   * @see _form_test_disabled_elements()
   */
  function testDisabledElements() {
    // Get the raw form in its original state.
    $form_state = array();
    $form = _form_test_disabled_elements(array(), $form_state);

    // Build a submission that tries to hijack the form by submitting input for
    // elements that are disabled.
    $edit = array();
    foreach (element_children($form) as $key) {
      if (isset($form[$key]['#test_hijack_value'])) {
        if (is_array($form[$key]['#test_hijack_value'])) {
          foreach ($form[$key]['#test_hijack_value'] as $subkey => $value) {
            $edit[$key . '[' . $subkey . ']'] = $value;
          }
        }
        else {
          $edit[$key] = $form[$key]['#test_hijack_value'];
        }
      }
    }

    // Submit the form with no input, as the browser does for disabled elements,
    // and fetch the $form_state['values'] that is passed to the submit handler.
    $this->drupalPost('form-test/disabled-elements', array(), t('Submit'));
    $returned_values['normal'] = drupal_json_decode($this->content);

    // Do the same with input, as could happen if JavaScript un-disables an
    // element. drupalPost() emulates a browser by not submitting input for
    // disabled elements, so we need to un-disable those elements first.
    $this->drupalGet('form-test/disabled-elements');
    $disabled_elements = array();
    foreach ($this->xpath('//*[@disabled]') as $element) {
      $disabled_elements[] = (string) $element['name'];
      unset($element['disabled']);
    }

    // All the elements should be marked as disabled, including the ones below
    // the disabled container.
    $this->assertEqual(count($disabled_elements), 39, 'The correct elements have the disabled property in the HTML code.');

    $this->drupalPost(NULL, $edit, t('Submit'));
    $returned_values['hijacked'] = drupal_json_decode($this->content);

    // Ensure that the returned values match the form's default values in both
    // cases.
    foreach ($returned_values as $type => $values) {
      $this->assertFormValuesDefault($values, $form);
    }
  }

  /**
   * Assert that the values submitted to a form matches the default values of the elements.
   */
  function assertFormValuesDefault($values, $form) {
    foreach (element_children($form) as $key) {
      if (isset($form[$key]['#default_value'])) {
        if (isset($form[$key]['#expected_value'])) {
          $expected_value = $form[$key]['#expected_value'];
        }
        else {
          $expected_value = $form[$key]['#default_value'];
        }

        if ($key == 'checkboxes_multiple') {
          // Checkboxes values are not filtered out.
          $values[$key] = array_filter($values[$key]);
        }
        $this->assertIdentical($expected_value, $values[$key], format_string('Default value for %type: expected %expected, returned %returned.', array('%type' => $key, '%expected' => var_export($expected_value, TRUE), '%returned' => var_export($values[$key], TRUE))));
      }

      // Recurse children.
      $this->assertFormValuesDefault($values, $form[$key]);
    }
  }

  /**
   * Verify markup for disabled form elements.
   *
   * @see _form_test_disabled_elements()
   */
  function testDisabledMarkup() {
    $this->drupalGet('form-test/disabled-elements');
    $form_state = array();
    $form = _form_test_disabled_elements(array(), $form_state);
    $type_map = array(
      'textarea' => 'textarea',
      'select' => 'select',
      'weight' => 'select',
      'datetime' => 'datetime',
    );

    foreach ($form as $name => $item) {
      // Skip special #types.
      if (!isset($item['#type']) || in_array($item['#type'], array('hidden', 'text_format'))) {
        continue;
      }
      // Setup XPath and CSS class depending on #type.
      if (in_array($item['#type'], array('button', 'submit'))) {
        $path = "//!type[contains(@class, :div-class) and @value=:value]";
        $class = 'form-button-disabled';
      }
      elseif (in_array($item['#type'], array('image_button'))) {
        $path = "//!type[contains(@class, :div-class) and @value=:value]";
        $class = 'image-button-disabled';
      }
      else {
        // starts-with() required for checkboxes.
        $path = "//div[contains(@class, :div-class)]/descendant::!type[starts-with(@name, :name)]";
        $class = 'form-disabled';
      }
      // Replace DOM element name in $path according to #type.
      $type = 'input';
      if (isset($type_map[$item['#type']])) {
        $type = $type_map[$item['#type']];
      }
      $path = strtr($path, array('!type' => $type));
      // Verify that the element exists.
      $element = $this->xpath($path, array(
        ':name' => check_plain($name),
        ':div-class' => $class,
        ':value' => isset($item['#value']) ? $item['#value'] : '',
      ));
      $this->assertTrue(isset($element[0]), format_string('Disabled form element class found for #type %type.', array('%type' => $item['#type'])));
    }

    // Verify special element #type text-format.
    $element = $this->xpath('//div[contains(@class, :div-class)]/descendant::textarea[@name=:name]', array(
      ':name' => 'text_format[value]',
      ':div-class' => 'form-disabled',
    ));
    $this->assertTrue(isset($element[0]), format_string('Disabled form element class found for #type %type.', array('%type' => 'text_format[value]')));
    $element = $this->xpath('//div[contains(@class, :div-class)]/descendant::select[@name=:name]', array(
      ':name' => 'text_format[format]',
      ':div-class' => 'form-disabled',
    ));
    $this->assertTrue(isset($element[0]), format_string('Disabled form element class found for #type %type.', array('%type' => 'text_format[format]')));
  }

  /**
   * Test Form API protections against input forgery.
   *
   * @see _form_test_input_forgery()
   */
  function testInputForgery() {
    $this->drupalGet('form-test/input-forgery');
    $checkbox = $this->xpath('//input[@name="checkboxes[two]"]');
    $checkbox[0]['value'] = 'FORGERY';
    $this->drupalPost(NULL, array('checkboxes[one]' => TRUE, 'checkboxes[two]' => TRUE), t('Submit'));
    $this->assertText('An illegal choice has been detected.', 'Input forgery was detected.');
  }

  /**
   * Tests required attribute.
   */
  function testRequiredAttribute() {
    $this->drupalGet('form-test/required-attribute');
    $expected = 'required';
    // Test to make sure the elements have the proper required attribute.
    foreach (array('textfield', 'password') as $type) {
      $element = $this->xpath('//input[@id=:id and @required=:expected]', array(
        ':id' => 'edit-' . $type,
        ':expected' => $expected,
      ));
      $this->assertTrue(!empty($element), format_string('The @type has the proper required attribute.', array('@type' => $type)));
    }

    // Test to make sure textarea has the proper required attribute.
    $element = $this->xpath('//textarea[@id=:id and @required=:expected]', array(
      ':id' => 'edit-textarea',
      ':expected' => $expected,
    ));
    $this->assertTrue(!empty($element), 'The textarea has the proper required attribute.');
  }

  /**
   *  Tests error border of multiple fields with same name in a page.
   */
  function testMultiFormSameNameErrorClass() {
    $this->drupalGet('form-test/double-form');
    $edit = array();
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertFieldByXpath('//input[@id="edit-name" and contains(@class, "error")]', NULL, 'Error input form element class found for first element.');
    $this->assertNoFieldByXpath('//input[@id="edit-name--2" and contains(@class, "error")]', NULL, 'No error input form element class found for second element.');
  }

  /**
   * Tests a form with a form state storing a database connection.
   */
  public function testFormStateDatabaseConnection() {
    $this->assertNoText('Database connection found');
    $this->drupalPost('form-test/form_state-database', array(), t('Submit'));
    $this->assertText('Database connection found');
  }
}
