<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Installer\InstallerLanguageTest.
 */

namespace Drupal\system\Tests\Installer;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\StringTranslation\Translator\FileTranslation;

/**
 * Tests installer language detection.
 */
class InstallerLanguageTest extends WebTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Installer language tests',
      'description' => 'Tests for installer language support.',
      'group' => 'Installer',
    );
  }

  function setUp() {
    parent::setUp();
    // The database is not available during this part of install. Use global
    // $conf to override the installation translations directory path.
    global $conf;
    $conf['locale.settings']['translation.path'] =  drupal_get_path('module', 'simpletest') . '/files/translations';
  }

  /**
   * Tests that the installer can find translation files.
   */
  function testInstallerTranslationFiles() {
    // Different translation files would be found depending on which language
    // we are looking for.
    $expected_translation_files = array(
      NULL => array('drupal-8.0.hu.po', 'drupal-8.0.de.po'),
      'de' => array('drupal-8.0.de.po'),
      'hu' => array('drupal-8.0.hu.po'),
      'it' => array(),
    );

    $file_translation = new FileTranslation($GLOBALS['conf']['locale.settings']['translation.path']);
    foreach ($expected_translation_files as $langcode => $files_expected) {
      $files_found = $file_translation->findTranslationFiles($langcode);
      $this->assertTrue(count($files_found) == count($files_expected), format_string('@count installer languages found.', array('@count' => count($files_expected))));
      foreach ($files_found as $file) {
        $this->assertTrue(in_array($file->filename, $files_expected), format_string('@file found.', array('@file' => $file->filename)));
      }
    }
  }

}
