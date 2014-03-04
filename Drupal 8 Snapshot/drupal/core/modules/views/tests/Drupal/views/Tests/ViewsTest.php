<?php

/**
 * @file
 * Contains \Drupal\views\Tests\ViewsTest.
 */

namespace Drupal\views\Tests;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Views;
use Drupal\views\Plugin\Core\Entity\View;
use Drupal\views\ViewExecutableFactory;
use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;

class ViewsTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Views test',
      'description' => 'Tests the Drupal\views\Views class.',
      'group' => 'Views',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('views.executable', new ViewExecutableFactory());

    $this->view = new View(array('id' => 'test_view'), 'view');

    $view_storage_controller = $this->getMockBuilder('Drupal\views\ViewStorageController')
      ->disableOriginalConstructor()
      ->getMock();
    $view_storage_controller->expects($this->once())
      ->method('load')
      ->with('test_view')
      ->will($this->returnValue($this->view));

    $entity_manager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_manager->expects($this->once())
      ->method('getStorageController')
      ->with('view')
      ->will($this->returnValue($view_storage_controller));
    $container->set('plugin.manager.entity', $entity_manager);

    Drupal::setContainer($container);
  }

  /**
   * Tests the getView() method.
   */
  public function testGetView() {
    $executable = Views::getView('test_view');
    $this->assertInstanceOf('Drupal\views\ViewExecutable', $executable);
    $this->assertEquals($this->view->id(), $executable->storage->id());
    $this->assertEquals(spl_object_hash($this->view), spl_object_hash($executable->storage));
  }

}
