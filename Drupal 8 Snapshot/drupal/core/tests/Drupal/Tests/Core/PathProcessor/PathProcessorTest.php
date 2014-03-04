<?php

/**
 * @file
 * Contains Drupal\Tests\Core\PathProcessor\PathProcessorTest.
 */

namespace Drupal\Tests\Core\PathProcessor;

use Drupal\Component\Utility\Settings;
use Drupal\Core\PathProcessor\PathProcessorAlias;
use Drupal\Core\PathProcessor\PathProcessorDecode;
use Drupal\Core\PathProcessor\PathProcessorFront;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Drupal\language\HttpKernel\PathProcessorLanguage;
use Symfony\Component\HttpFoundation\Request;

use Drupal\Tests\UnitTestCase;

/**
 * Tests path processor functionality.
 */
class PathProcessorTest extends UnitTestCase {

  protected $languages;
  protected $languageManager;

  public static function getInfo() {
    return array(
      'name' => t('Path Processor Unit Tests'),
      'description' => t('Tests processing of the inbound path.'),
      'group' => t('Path API'),
    );
  }

  public function setUp() {

    // Set up some languages to be used by the language-based path processor.
    $languages = array();
    foreach (array('en' => 'English', 'fr' => 'French') as $langcode => $language_name) {
      $language = new \stdClass();
      $language->id = $langcode;
      $language->name = $language_name;
      $languages[$langcode] = $language;
    }
    $this->languages = $languages;

    // Create a language manager stub.
    $language_manager = $this->getMock('Drupal\Core\Language\LanguageManager');
    $language_manager->expects($this->any())
      ->method('getLanguage')
      ->will($this->returnValue($languages['en']));

    $this->languageManager = $language_manager;
  }

  /**
   * Tests resolving the inbound path to the system path.
   */
  function testProcessInbound() {

    // Create an alias manager stub.
    $alias_manager = $this->getMockBuilder('Drupal\Core\Path\AliasManager')
      ->disableOriginalConstructor()
      ->getMock();

    $system_path_map = array(
      // Set up one proper alias that can be resolved to a system path.
      array('foo', NULL, 'user/1'),
      // Passing in anything else should return the same string.
      array('fr/foo', NULL, 'fr/foo'),
      array('fr', NULL, 'fr'),
      array('user', NULL, 'user'),
    );

    $alias_manager->expects($this->any())
      ->method('getSystemPath')
      ->will($this->returnValueMap($system_path_map));

    // Create a stub config factory with all config settings that will be checked
    // during this test.
    $language_prefixes = array_keys($this->languages);
    $config_factory_stub = $this->getConfigFactoryStub(
      array(
        'system.site' => array(
          'page.front' => 'user'
        ),
        'language.negotiation' => array(
          'url.prefixes' => array_combine($language_prefixes, $language_prefixes)
        )
      )
    );

    // Create the processors.
    $alias_processor = new PathProcessorAlias($alias_manager);
    $decode_processor = new PathProcessorDecode();
    $front_processor = new PathProcessorFront($config_factory_stub);
    $language_processor = new PathProcessorLanguage($config_factory_stub, new Settings(array()), $this->languageManager, $this->languages);

    // First, test the processor manager with the processors in the incorrect
    // order. The alias processor will run before the language processor, meaning
    // aliases will not be found.
    $priorities = array(
      1000 => $alias_processor,
      500 => $decode_processor,
      300 => $front_processor,
      200 => $language_processor,
    );

    // Create the processor manager and add the processors.
    $processor_manager = new PathProcessorManager();
    foreach ($priorities as $priority => $processor) {
      $processor_manager->addInbound($processor, $priority);
    }

    // Test resolving the French homepage using the incorrect processor order.
    $test_path = 'fr';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('', $processed, 'Processing in the incorrect order fails to resolve the system path from the empty path');

    // Test resolving an existing alias using the incorrect processor order.
    $test_path = 'fr/foo';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('foo', $processed, 'Processing in the incorrect order fails to resolve the system path from an alias');

    // Now create a new processor manager and add the processors, this time in
    // the correct order.
    $processor_manager = new PathProcessorManager();
    $priorities = array(
      1000 => $decode_processor,
      500 => $language_processor,
      300 => $front_processor,
      200 => $alias_processor,
    );
    foreach ($priorities as $priority => $processor) {
      $processor_manager->addInbound($processor, $priority);
    }

    // Test resolving the French homepage using the correct processor order.
    $test_path = 'fr';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('user', $processed, 'Processing in the correct order resolves the system path from the empty path.');

    // Test resolving an existing alias using the correct processor order.
    $test_path = 'fr/foo';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('user/1', $processed, 'Processing in the correct order resolves the system path from an alias.');
  }
}
