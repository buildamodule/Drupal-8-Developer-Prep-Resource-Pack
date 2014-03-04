<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\BreakLockForm.
 */

namespace Drupal\views_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\user\TempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the form to break the lock of an edited view.
 */
class BreakLockForm extends EntityConfirmFormBase implements EntityControllerInterface {

  /**
   * Stores the Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Stores the user tempstore.
   *
   * @var \Drupal\user\TempStore
   */
  protected $tempStore;

  /**
   * Constructs a \Drupal\views_ui\Form\BreakLockForm object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The Entity manager.
   * @param \Drupal\user\TempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(EntityManager $entity_manager, TempStoreFactory $temp_store_factory) {
    $this->entityManager = $entity_manager;
    $this->tempStore = $temp_store_factory->get('views');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('plugin.manager.entity'),
      $container->get('user.tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'views_ui_break_lock_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Do you want to break the lock on view %name?', array('%name' => $this->entity->id()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $locked = $this->tempStore->getMetadata($this->entity->id());
    $account = $this->entityManager->getStorageController('user')->load($locked->owner);
    $username = array(
      '#theme' => 'username',
      '#account' => $account,
    );
    return t('By breaking this lock, any unsaved changes made by !user will be lost.', array('!user' => drupal_render($username)));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/structure/views/view/' . $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Break lock');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL) {
    if (!$this->tempStore->getMetadata($this->entity->id())) {
      $form['message']['#markup'] = t('There is no lock on view %name to break.', array('%name' => $this->entity->id()));
      return $form;
    }
    return parent::buildForm($form, $form_state, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $this->tempStore->delete($this->entity->id());
    $form_state['redirect'] = 'admin/structure/views/view/' . $this->entity->id();
    drupal_set_message(t('The lock has been broken and you may now edit this view.'));
  }

}
