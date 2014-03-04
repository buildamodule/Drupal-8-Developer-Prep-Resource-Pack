<?php

/**
 * @file
 * Contains \Drupal\rdf\Tests\FileFieldAttributesTest.
 */

namespace Drupal\rdf\Tests;

use Drupal\Core\Language\Language;
use Drupal\file\Tests\FileFieldTestBase;

/**
 * Tests RDFa markup generation for File fields.
 */
class FileFieldAttributesTest extends FileFieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('rdf', 'file');

  /**
   * The name of the file field used in the test.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The file object used in the test.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * The node object used in the test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  public static function getInfo() {
    return array(
      'name' => 'RDFa markup for files',
      'description' => 'Tests the RDFa markup of filefields.',
      'group' => 'RDF',
    );
  }

  public function setUp() {
    parent::setUp();
    $this->fieldName = strtolower($this->randomName());

    $type_name = 'article';
    $this->createFileField($this->fieldName, $type_name);

    // Set the teaser display to show this field.
    entity_get_display('node', 'article', 'teaser')
      ->setComponent($this->fieldName, array('type' => 'file_default'))
      ->save();

    // Set the RDF mapping for the new field.
    $mapping = rdf_get_mapping('node', 'article');
    $mapping->setFieldMapping($this->fieldName, array('properties' => array('rdfs:seeAlso'), 'mapping_type' => 'rel'))->save();

    $test_file = $this->getTestFile('text');

    // Create a new node with the uploaded file.
    $nid = $this->uploadNodeFile($test_file, $this->fieldName, $type_name);

    $this->node = node_load($nid, TRUE);
    $this->file = file_load($this->node->{$this->fieldName}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id']);

  }

  /**
   * Tests if file fields in teasers have correct resources.
   *
   * Ensure that file fields have the correct resource as the object in RDFa
   * when displayed as a teaser.
   */
  function testNodeTeaser() {
    // Render the teaser.
    $node_render_array = entity_view_multiple(array($this->node), 'teaser');
    $html = drupal_render($node_render_array);

    // Parses front page where the node is displayed in its teaser form.
    $parser = new \EasyRdf_Parser_Rdfa();
    $graph = new \EasyRdf_Graph();
    $base_uri = url('<front>', array('absolute' => TRUE));
    $parser->parse($graph, $html, 'rdfa', $base_uri);

    $node_uri = url('node/' . $this->node->id(), array('absolute' => TRUE));
    $file_uri = file_create_url($this->file->getFileUri());

    // Node relation to attached file.
    $expected_value = array(
      'type' => 'uri',
      'value' => $file_uri,
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://www.w3.org/2000/01/rdf-schema#seeAlso', $expected_value), 'Node to file relation found in RDF output (rdfs:seeAlso).');
    $this->drupalGet('node');
  }

}
