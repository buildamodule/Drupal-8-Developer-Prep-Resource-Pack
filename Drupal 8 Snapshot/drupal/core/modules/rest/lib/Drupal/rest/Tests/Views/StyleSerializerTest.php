<?php

/**
 * @file
 * Contains \Drupal\rest\Tests\Views\StyleSerializerTest.
 */

namespace Drupal\rest\Tests\Views;

use Drupal\views\Tests\Plugin\PluginTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the serializer style plugin.
 *
 * @see \Drupal\rest\Plugin\views\display\RestExport
 * @see \Drupal\rest\Plugin\views\style\Serializer
 * @see \Drupal\rest\Plugin\views\row\DataEntityRow
 * @see \Drupal\rest\Plugin\views\row\DataFieldRow
 */
class StyleSerializerTest extends PluginTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_ui', 'entity_test', 'hal', 'rest_test_views');

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_serializer_display_field', 'test_serializer_display_entity');

  /**
   * A user with administrative privileges to look at test entity and configure views.
   */
  protected $adminUser;

  public static function getInfo() {
    return array(
      'name' => 'Style: Serializer plugin',
      'description' => 'Tests the serializer style plugin.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('rest_test_views'));

    $this->adminUser = $this->drupalCreateUser(array('administer views', 'administer entity_test content', 'access user profiles', 'view test entity'));

    // Save some entity_test entities.
    for ($i = 1; $i <= 10; $i++) {
      entity_create('entity_test', array('name' => 'test_' . $i, 'user_id' => $this->adminUser->id()))->save();
    }

    $this->enableViewsTestModule();
  }

  /**
   * Checks the behavior of the Serializer callback paths and row plugins.
   */
  public function testSerializerResponses() {
    // Test the serialize callback.
    $view = views_get_view('test_serializer_display_field');
    $view->initDisplay();
    $this->executeView($view);

    $actual_json = $this->drupalGet('test/serialize/field', array(), array('Accept: application/json'));
    $this->assertResponse(200);

    // Test the http Content-type.
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['content-type'], 'application/json', 'The header Content-type is correct.');

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($actual_json, json_encode($expected), 'The expected JSON output was found.');


    // Test that the rendered output and the preview output are the same.
    $view->destroy();
    $view->setDisplay('rest_export_1');
    // Mock the request content type by setting it on the display handler.
    $view->display_handler->setContentType('json');
    $output = $view->preview();
    $this->assertIdentical($actual_json, drupal_render($output), 'The expected JSON preview output was found.');

    // Test a 403 callback.
    $this->drupalGet('test/serialize/denied');
    $this->assertResponse(403);

    // Test the entity rows.

    $view = views_get_view('test_serializer_display_entity');
    $view->initDisplay();
    $this->executeView($view);

    // Get the serializer service.
    $serializer = $this->container->get('serializer');

    $entities = array();
    foreach ($view->result as $row) {
      $entities[] = $row->_entity;
    }

    $expected = $serializer->serialize($entities, 'json');

    $actual_json = $this->drupalGet('test/serialize/entity', array(), array('Accept: application/json'));
    $this->assertResponse(200);
    $this->assertIdentical($actual_json, $expected, 'The expected JSON output was found.');

    $expected = $serializer->serialize($entities, 'hal_json');
    $actual_json = $this->drupalGet('test/serialize/entity', array(), array('Accept: application/hal+json'));
    $this->assertIdentical($actual_json, $expected, 'The expected HAL output was found.');
  }

  /**
   * Tests the response format configuration.
   */
  public function testReponseFormatConfiguration() {
    $this->drupalLogin($this->adminUser);

    $style_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/style_options';

    // Select only 'xml' as an accepted format.
    $this->drupalPost($style_options, array('style_options[formats][xml]' => 'xml'), t('Apply'));
    $this->drupalPost(NULL, array(), t('Save'));

    // Should return a 406.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/json'));
    $this->assertResponse(406, 'A 406 response was returned when JSON was requested.');
     // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/xml'));
    $this->assertResponse(200, 'A 200 response was returned when XML was requested.');

    // Add 'json' as an accepted format, so we have multiple.
    $this->drupalPost($style_options, array('style_options[formats][json]' => 'json'), t('Apply'));
    $this->drupalPost(NULL, array(), t('Save'));

    // Should return a 200.
    // @todo This should be fixed when we have better content negotiation.
    $this->drupalGet('test/serialize/field', array(), array('Accept: */*'));
    $this->assertResponse(200, 'A 200 response was returned when any format was requested.');

    // Should return a 200. Emulates a sample Firefox header.
    $this->drupalGet('test/serialize/field', array(), array('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'));
    $this->assertResponse(200, 'A 200 response was returned when a browser accept header was requested.');

    // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/json'));
    $this->assertResponse(200, 'A 200 response was returned when JSON was requested.');
    // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/xml'));
    $this->assertResponse(200, 'A 200 response was returned when XML was requested');
    // Should return a 406.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/html'));
    $this->assertResponse(406, 'A 406 response was returned when HTML was requested.');
  }

  /**
   * Test the field ID alias functionality of the DataFieldRow plugin.
   */
  public function testUIFieldAlias() {
    $this->drupalLogin($this->adminUser);

    // Test the UI settings for adding field ID aliases.
    $this->drupalGet('admin/structure/views/view/test_serializer_display_field/edit/rest_export_1');
    $row_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/row_options';
    $this->assertLinkByHref($row_options);

    // Test an empty string for an alias, this should not be used. This also
    // tests that the form can be submitted with no aliases.
    $this->drupalPost($row_options, array('row_options[field_options][name][alias]' => ''), t('Apply'));
    $this->drupalPost(NULL, array(), t('Save'));

    $view = views_get_view('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($this->drupalGetJSON('test/serialize/field'), $expected);

    // Test a random aliases for fields, they should be replaced.
    $alias_map = array(
      'name' => $this->randomName(),
      // Use # to produce an invalid character for the validation.
      'nothing' => '#' . $this->randomName(),
      'created' => 'created',
    );

    $edit = array('row_options[field_options][name][alias]' => $alias_map['name'], 'row_options[field_options][nothing][alias]' => $alias_map['nothing']);
    $this->drupalPost($row_options, $edit, t('Apply'));
    $this->assertText(t('The machine-readable name must contain only letters, numbers, dashes and underscores.'));

    // Change the map alias value to a valid one.
    $alias_map['nothing'] = $this->randomName();

    $edit = array('row_options[field_options][name][alias]' => $alias_map['name'], 'row_options[field_options][nothing][alias]' => $alias_map['nothing']);
    $this->drupalPost($row_options, $edit, t('Apply'));

    $this->drupalPost(NULL, array(), t('Save'));

    $view = views_get_view('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$alias_map[$id]] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($this->drupalGetJSON('test/serialize/field'), $expected);
  }

  /**
   * Tests the raw output options for row field rendering.
   */
  public function testFieldRawOutput() {
    $this->drupalLogin($this->adminUser);

    // Test the UI settings for adding field ID aliases.
    $this->drupalGet('admin/structure/views/view/test_serializer_display_field/edit/rest_export_1');
    $row_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/row_options';
    $this->assertLinkByHref($row_options);

    // Test an empty string for an alias, this should not be used. This also
    // tests that the form can be submitted with no aliases.
    $this->drupalPost($row_options, array('row_options[field_options][created][raw_output]' => '1'), t('Apply'));
    $this->drupalPost(NULL, array(), t('Save'));

    $view = views_get_view('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    // Just test the raw 'created' value against each row.
    foreach ($this->drupalGetJSON('test/serialize/field') as $index => $values) {
      $this->assertIdentical($values['created'], $view->result[$index]->views_test_data_created, 'Expected raw created value found.');
    }
  }

  /**
   * Tests the preview output for json output.
   */
  public function testPreview() {
    $view = views_get_view('test_serializer_display_entity');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    // Get the serializer service.
    $serializer = $this->container->get('serializer');

    $entities = array();
    foreach ($view->result as $row) {
      $entities[] = $row->_entity;
    }

    $expected = check_plain($serializer->serialize($entities, 'json'));

    $view->display_handler->setContentType('json');
    $view->live_preview = TRUE;

    $build = $view->preview();
    $rendered_json = $build['#markup'];
    $this->assertEqual($rendered_json, $expected, 'Ensure the previewed json is escaped.');
  }

}
