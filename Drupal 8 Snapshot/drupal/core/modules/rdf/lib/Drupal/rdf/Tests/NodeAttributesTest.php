<?php

/**
 * @file
 * Contains \Drupal\rdf\Tests\NodeAttributesTest.
 */

namespace Drupal\rdf\Tests;

use Drupal\node\Tests\NodeTestBase;

/**
 * Tests the RDFa markup of Nodes.
 */
class NodeAttributesTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('rdf');

  public static function getInfo() {
    return array(
      'name' => 'RDFa markup for nodes',
      'description' => 'Tests the RDFa markup of nodes.',
      'group' => 'RDF',
    );
  }

  public function setUp() {
    parent::setUp();

    rdf_get_mapping('node', 'article')
      ->setBundleMapping(array(
        'types' => array('sioc:Item', 'foaf:Document'),
      ))
      ->setFieldMapping('title', array(
        'properties' => array('dc:title'),
      ))
      ->setFieldMapping('created', array(
        'properties' => array('dc:date', 'dc:created'),
        'datatype' => 'xsd:dateTime',
        'datatype_callback' => array('callable' => 'date_iso8601'),
      ))
      ->save();
  }

  /**
   * Creates a node of type article and tests its RDFa markup.
   */
  function testNodeAttributes() {
    $node = $this->drupalCreateNode(array('type' => 'article'));

    $node_uri = url('node/' . $node->id(), array('absolute' => TRUE));
    $base_uri = url('<front>', array('absolute' => TRUE));

    // Parses front page where the node is displayed in its teaser form.
    $parser = new \EasyRdf_Parser_Rdfa();
    $graph = new \EasyRdf_Graph();
    $parser->parse($graph, $this->drupalGet('node/' . $node->id()), 'rdfa', $base_uri);

    // Inspects RDF graph output.
    // Node type.
    $expected_value = array(
      'type' => 'uri',
      'value' => 'http://rdfs.org/sioc/ns#Item',
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $expected_value), 'Node type found in RDF output (sioc:Item).');
    // Node type.
    $expected_value = array(
      'type' => 'uri',
      'value' => 'http://xmlns.com/foaf/0.1/Document',
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $expected_value), 'Node type found in RDF output (foaf:Document).');
    // Node title.
    $expected_value = array(
      'type' => 'literal',
      'value' => $node->title,
      'lang' => 'en',
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://purl.org/dc/terms/title', $expected_value), 'Node title found in RDF output (dc:title).');
    // Node date.
    $expected_value = array(
      'type' => 'literal',
      'value' => date('c', $node->created),
      'datatype' => 'http://www.w3.org/2001/XMLSchema#dateTime',
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://purl.org/dc/terms/date', $expected_value), 'Node date found in RDF output (dc:date).');
    // Node date.
    $expected_value = array(
      'type' => 'literal',
      'value' => date('c', $node->created),
      'datatype' => 'http://www.w3.org/2001/XMLSchema#dateTime',
    );
    $this->assertTrue($graph->hasProperty($node_uri, 'http://purl.org/dc/terms/created', $expected_value), 'Node date found in RDF output (dc:created).');
  }
}
