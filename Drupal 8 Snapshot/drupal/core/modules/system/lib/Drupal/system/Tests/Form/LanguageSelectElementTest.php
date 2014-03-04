<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Form\LanguageSelectElementTest.
 */

namespace Drupal\system\Tests\Form;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Language\Language;

/**
 * Functional tests for the language select form element.
 */
class LanguageSelectElementTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('form_test', 'language');

  public static function getInfo() {
    return array(
      'name' => 'Language select form element',
      'description' => 'Checks that the language select form element prints and submits the right options.',
      'group' => 'Form API',
    );
  }

  /**
   * Tests that the options printed by the language select element are correct.
   */
  function testLanguageSelectElementOptions() {
    // Add some languages.
    $language = new Language(array(
      'id' => 'aaa',
      'name' => $this->randomName(),
    ));
    language_save($language);

    $language = new Language(array(
      'id' => 'bbb',
      'name' => $this->randomName(),
    ));
    language_save($language);

    $this->drupalGet('form-test/language_select');
    // Check that the language fields were rendered on the page.
    $ids = array('edit-languages-all' => Language::STATE_ALL,
                 'edit-languages-configurable' => Language::STATE_CONFIGURABLE,
                 'edit-languages-locked' => Language::STATE_LOCKED,
                 'edit-languages-config-and-locked' => Language::STATE_CONFIGURABLE | Language::STATE_LOCKED);
    foreach ($ids as $id => $flags) {
      $this->assertField($id, format_string('The @id field was found on the page.', array('@id' => $id)));
      $options = array();
      foreach (language_list($flags) as $langcode => $language) {
        $options[$langcode] = $language->locked ? t('- @name -', array('@name' => $language->name)) : $language->name;
      }
      $this->_testLanguageSelectElementOptions($id, $options);
    }

    // Test that the #options were not altered by #languages.
    $this->assertField('edit-language-custom-options', format_string('The @id field was found on the page.', array('@id' => 'edit-language-custom-options')));
    $this->_testLanguageSelectElementOptions('edit-language-custom-options', array('opt1' => 'First option', 'opt2' => 'Second option', 'opt3' => 'Third option'));
  }

  /**
   * Tests the case when the language select elements should not be printed.
   *
   * This happens when the language module is disabled.
   */
  function testHiddenLanguageSelectElement() {
    // Disable the language module, so that the language select field will not
    // be rendered.
    module_disable(array('language'));
    $this->drupalGet('form-test/language_select');
    // Check that the language fields were rendered on the page.
    $ids = array('edit-languages-all', 'edit-languages-configurable', 'edit-languages-locked', 'edit-languages-config-and-locked');
    foreach ($ids as $id) {
      $this->assertNoField($id, format_string('The @id field was not found on the page.', array('@id' => $id)));
    }

    // Check that the submitted values were the default values of the language
    // field elements.
    $edit = array();
    $this->drupalPost(NULL, $edit, t('Submit'));
    $values = drupal_json_decode($this->drupalGetContent());
    $this->assertEqual($values['languages_all'], 'xx');
    $this->assertEqual($values['languages_configurable'], 'en');
    $this->assertEqual($values['languages_locked'], Language::LANGCODE_NOT_SPECIFIED);
    $this->assertEqual($values['languages_config_and_locked'], 'dummy_value');
    $this->assertEqual($values['language_custom_options'], 'opt2');
  }

  /**
   * Helper function to check the options of a language select form element.
   *
   * @param string $id
   *   The id of the language select element to check.
   *
   * @param array $options
   *   An array with options to compare with.
   */
  protected function _testLanguageSelectElementOptions($id, $options) {
    // Check that the options in the language field are exactly the same,
    // including the order, as the languages sent as a parameter.
    $elements = $this->xpath("//select[@id='" . $id . "']");
    $count = 0;
    foreach ($elements[0]->option as $option) {
      $count++;
      $option_title = current($options);
      $this->assertEqual((string) $option, $option_title);
      next($options);
    }
    $this->assertEqual($count, count($options), format_string('The number of languages and the number of options shown by the language element are the same: @languages languages, @number options', array('@languages' => count($options), '@number' => $count)));
  }
}
