<?php

/**
 * @file
 * Definition of Drupal\locale\Tests\LocaleStringTest.
 */

namespace Drupal\locale\Tests;

use Drupal\Core\Language\Language;
use Drupal\locale\SourceString;
use Drupal\locale\TranslationString;
use Drupal\simpletest\WebTestBase;

/**
 * Tests for the locale string data API.
 */
class LocaleStringTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('locale');

  /**
   * The locale storage.
   *
   * @var Drupal\locale\StringStorageInterface
   */
  protected $storage;

  /**
   * @return multitype:string
   */
  public static function getInfo() {
    return array(
      'name' => 'String storage and objects',
      'description' => 'Tests the locale string storage, string objects and data API.',
      'group' => 'Locale',
    );
  }

  function setUp() {
    parent::setUp();
    // Add a default locale storage for all these tests.
    $this->storage = $this->container->get('locale.storage');
    // Create two languages: Spanish and German.
    foreach (array('es', 'de') as $langcode) {
      $language = new Language(array('id' => $langcode));
      $languages[$langcode] = language_save($language);
    }
  }

  /**
   * Test CRUD API.
   */
  function testStringCRUDAPI() {
    // Create source string.
    $source = $this->buildSourceString();
    $source->save();
    $this->assertTrue($source->lid, format_string('Successfully created string %string', array('%string' => $source->source)));

    // Load strings by lid and source.
    $string1 = $this->storage->findString(array('lid' => $source->lid));
    $this->assertEqual($source, $string1, 'Successfully retrieved string by identifier.');
    $string2 = $this->storage->findString(array('source' => $source->source, 'context' => $source->context));
    $this->assertEqual($source, $string2, 'Successfully retrieved string by source and context.');
    $string3 = $this->storage->findString(array('source' => $source->source, 'context' => ''));
    $this->assertFalse($string3, 'Cannot retrieve string with wrong context.');

    // Check version handling and updating.
    $this->assertEqual($source->version, 'none', 'String originally created without version.');
    $string = $this->storage->findTranslation(array('lid' => $source->lid));
    $this->assertEqual($string->version, VERSION, 'Checked and updated string version to Drupal version.');

    // Create translation and find it by lid and source.
    $langcode = 'es';
    $translation = $this->createTranslation($source, $langcode);
    $this->assertEqual($translation->customized, LOCALE_NOT_CUSTOMIZED, 'Translation created as not customized by default.');
    $string1 = $this->storage->findTranslation(array('language' => $langcode, 'lid' => $source->lid));
    $this->assertEqual($string1->translation, $translation->translation, 'Successfully loaded translation by string identifier.');
    $string2 = $this->storage->findTranslation(array('language' => $langcode, 'source' => $source->source, 'context' => $source->context));
    $this->assertEqual($string2->translation, $translation->translation, 'Successfully loaded translation by source and context.');
    $translation
      ->setCustomized()
      ->save();
    $translation = $this->storage->findTranslation(array('language' => $langcode, 'lid' => $source->lid));
    $this->assertEqual($translation->customized, LOCALE_CUSTOMIZED, 'Translation successfully marked as customized.');

    // Delete translation.
    $translation->delete();
    $deleted =  $this->storage->findTranslation(array('language' => $langcode, 'lid' => $source->lid));
    $this->assertFalse(isset($deleted->translation), 'Successfully deleted translation string.');

    // Create some translations and then delete string and all of its translations.
    $lid = $source->lid;
    $translations = $this->createAllTranslations($source);
    $search = $this->storage->getTranslations(array('lid' => $source->lid));
    $this->assertEqual(count($search), 3, 'Created and retrieved all translations for our source string.');

    $source->delete();
    $string = $this->storage->findString(array('lid' => $lid));
    $this->assertFalse($string, 'Successfully deleted source string.');
    $deleted = $search = $this->storage->getTranslations(array('lid' => $lid));
    $this->assertFalse($deleted, 'Successfully deleted all translation strings.');
  }

  /**
   * Test Search API loading multiple objects.
   */
  function testStringSearchAPI() {
    $language_count = 3;
    // Strings 1 and 2 will have some common prefix.
    // Source 1 will have all translations, not customized.
    // Source 2 will have all translations, customized.
    // Source 3 will have no translations.
    $prefix = $this->randomName(100);
    $source1 = $this->buildSourceString(array('source' => $prefix . $this->randomName(100)))->save();
    $source2 = $this->buildSourceString(array('source' => $prefix . $this->randomName(100)))->save();
    $source3 = $this->buildSourceString()->save();
    // Load all source strings.
    $strings = $this->storage->getStrings(array());
    $this->assertEqual(count($strings), 3, 'Found 3 source strings in the database.');
    // Load all source strings matching a given string
    $filter_options['filters'] = array('source' => $prefix);
    $strings = $this->storage->getStrings(array(), $filter_options);
    $this->assertEqual(count($strings), 2, 'Found 2 strings using some string filter.');

    // Not customized translations.
    $translate1 = $this->createAllTranslations($source1);
    // Customized translations.
    $translate2 = $this->createAllTranslations($source2, array('customized' => LOCALE_CUSTOMIZED));
    // Try quick search function with different field combinations.
    $langcode = 'es';
    $found = $this->storage->findTranslation(array('language' => $langcode, 'source' => $source1->source, 'context' => $source1->context));
    $this->assertTrue($found && isset($found->language) && isset($found->translation) && !$found->isNew(), 'Translation found searching by source and context.');
    $this->assertEqual($found->translation, $translate1[$langcode]->translation, 'Found the right translation.');
    // Now try a translation not found.
    $found = $this->storage->findTranslation(array('language' => $langcode,  'source' => $source3->source, 'context' => $source3->context));
    $this->assertTrue($found && $found->lid == $source3->lid && !isset($found->translation) && $found->isNew(), 'Translation not found but source string found.');

    // Load all translations. For next queries we'll be loading only translated strings.    $only_translated = array('untranslated' => FALSE);
    $translations = $this->storage->getTranslations(array('translated' => TRUE));
    $this->assertEqual(count($translations), 2 * $language_count, 'Created and retrieved all translations for source strings.');

    // Load all customized translations.
    $translations = $this->storage->getTranslations(array('customized' => LOCALE_CUSTOMIZED, 'translated' => TRUE));
    $this->assertEqual(count($translations), $language_count, 'Retrieved all customized translations for source strings.');

    // Load all Spanish customized translations
    $translations = $this->storage->getTranslations(array('language' => 'es', 'customized' => LOCALE_CUSTOMIZED, 'translated' => TRUE));
    $this->assertEqual(count($translations), 1, 'Found only Spanish and customized translations.');

    // Load all source strings without translation (1).
    $translations = $this->storage->getStrings(array('translated' => FALSE));
    $this->assertEqual(count($translations), 1, 'Found 1 source string without translations.');

    // Load Spanish translations using string filter.
    $filter_options['filters'] = array('source' => $prefix);
    $translations = $this->storage->getTranslations(array('language' => 'es'), $filter_options);
    $this->assertEqual(count($strings), 2, 'Found 2 translations using some string filter.');

  }

  /**
   * Creates random source string object.
   */
  function buildSourceString($values = array()) {
    return $this->storage->createString($values += array(
      'source' => $this->randomName(100),
      'context' => $this->randomName(20),
    ));
  }

  /**
   * Creates translations for source string and all languages.
   */
  function createAllTranslations($source, $values = array()) {
    $list = array();
    foreach (language_list() as $language) {
      $list[$language->id] = $this->createTranslation($source, $language->id, $values);
    }
    return $list;
  }

  /**
   * Creates single translation for source string.
   */
  function createTranslation($source, $langcode, $values = array()) {
    return $this->storage->createTranslation($values += array(
      'lid' => $source->lid,
      'language' => $langcode,
      'translation' => $this->randomName(100),
    ))->save();
  }
}
