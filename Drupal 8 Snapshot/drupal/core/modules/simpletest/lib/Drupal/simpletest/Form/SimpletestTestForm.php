<?php

/**
 * @file
 * Contains \Drupal\simpletest\Form\SimpletestTestForm.
 */

namespace Drupal\simpletest\Form;

use Drupal\Core\Form\FormInterface;

/**
 * List tests arranged in groups that can be selected and run.
 */
class SimpletestTestForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simpletest_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    // JavaScript-only table filters.
    $form['filters'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('table-filter', 'js-show'),
      ),
    );
    $form['filters']['text'] = array(
      '#type' => 'search',
      '#title' => t('Search'),
      '#size' => 30,
      '#placeholder' => t('Enter test name…'),
      '#attributes' => array(
        'class' => array('table-filter-text'),
        'data-table' => '#simpletest-test-form',
        'autocomplete' => 'off',
        'title' => t('Enter at least 3 characters of the test name or description to filter by.'),
      ),
    );

    $form['tests'] = array(
      '#type' => 'details',
      '#title' => t('Tests'),
      '#description' => t('Select the test(s) or test group(s) you would like to run, and click <em>Run tests</em>.'),
    );

    $form['tests']['table'] = array(
      '#theme' => 'simpletest_test_table',
    );

    // Generate the list of tests arranged by group.
    $groups = simpletest_test_get_all();
    $groups['PHPUnit'] = simpletest_phpunit_get_available_tests();
    $form_state['storage']['PHPUnit'] = $groups['PHPUnit'];

    foreach ($groups as $group => $tests) {
      $form['tests']['table'][$group] = array(
        '#collapsed' => TRUE,
      );

      foreach ($tests as $class => $info) {
        $form['tests']['table'][$group][$class] = array(
          '#type' => 'checkbox',
          '#title' => $info['name'],
          '#description' => $info['description'],
        );
      }
    }

    // Action buttons.
    $form['tests']['op'] = array(
      '#type' => 'submit',
      '#value' => t('Run tests'),
    );
    $form['clean'] = array(
      '#type' => 'fieldset',
      '#title' => t('Clean test environment'),
      '#description' => t('Remove tables with the prefix "simpletest" and temporary directories that are left over from tests that crashed. This is intended for developers when creating tests.'),
    );
    $form['clean']['op'] = array(
      '#type' => 'submit',
      '#value' => t('Clean environment'),
      '#submit' => array('simpletest_clean_environment'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // Get list of tests.
    $tests_list = array();
    simpletest_classloader_register();

    $phpunit_all = array_keys($form_state['storage']['PHPUnit']);

    foreach ($form_state['values'] as $class_name => $value) {
      // Since class_exists() will likely trigger an autoload lookup,
      // we do the fast check first.
      if ($value === 1 && class_exists($class_name)) {
        $test_type = in_array($class_name, $phpunit_all) ? 'UnitTest' : 'WebTest';
        $tests_list[$test_type][] = $class_name;
      }
    }
    if (count($tests_list) > 0 ) {
      $test_id = simpletest_run_tests($tests_list, 'drupal');
      $form_state['redirect'] = 'admin/config/development/testing/results/' . $test_id;
    }
    else {
      drupal_set_message(t('No test(s) selected.'), 'error');
    }
  }

}
