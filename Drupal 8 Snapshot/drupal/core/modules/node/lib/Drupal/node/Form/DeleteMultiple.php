<?php

/**
 * @file
 * Contains \Drupal\node\Form\DeleteMultiple.
 */

namespace Drupal\node\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Controller\ControllerInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Component\Utility\String;
use Drupal\user\TempStoreFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a node deletion confirmation form.
 */
class DeleteMultiple extends ConfirmFormBase implements ControllerInterface {

  /**
   * The array of nodes to delete.
   *
   * @var array
   */
  protected $nodes = array();

  /**
   * The tempstore factory.
   *
   * @var \Drupal\user\TempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The node storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $manager;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\user\TempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityManager $manager
   *   The entity manager.
   */
  public function __construct(TempStoreFactory $temp_store_factory, EntityManager $manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->storageController = $manager->getStorageController('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.tempstore'),
      $container->get('plugin.manager.entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'node_multiple_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return format_plural(count($this->nodes), 'Are you sure you want to delete this item?', 'Are you sure you want to delete these items?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/content';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL) {
    $this->nodes = $this->tempStoreFactory->get('node_multiple_delete_confirm')->get($GLOBALS['user']->id());
    if (empty($this->nodes)) {
      return new RedirectResponse(url($this->getCancelPath(), array('absolute' => TRUE)));
    }

    $form['nodes'] = array(
      '#theme' => 'item_list',
      '#items' => array_map(function ($node) {
        return String::checkPlain($node->label());
      }, $this->nodes),
    );
    return parent::buildForm($form, $form_state, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    if ($form_state['values']['confirm'] && !empty($this->nodes)) {
      $this->storageController->delete($this->nodes);
      $this->tempStoreFactory->get('node_multiple_delete_confirm')->delete($GLOBALS['user']->id());
      $count = count($this->nodes);
      watchdog('content', 'Deleted @count posts.', array('@count' => $count));
      drupal_set_message(format_plural($count, 'Deleted 1 post.', 'Deleted @count posts.'));
    }
    $form_state['redirect'] = 'admin/content';
  }

}
