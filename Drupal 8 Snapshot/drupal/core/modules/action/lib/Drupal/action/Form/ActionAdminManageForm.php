<?php

/**
 * @file
 * Contains \Drupal\action\Form\ActionAdminManageForm.
 */

namespace Drupal\action\Form;

use Drupal\Core\Controller\ControllerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Action\ActionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration form for configurable actions.
 */
class ActionAdminManageForm implements FormInterface, ControllerInterface {

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $manager;

  /**
   * Constructs a new ActionAdminManageForm.
   *
   * @param \Drupal\Core\Action\ActionManager $manager
   *   The action plugin manager.
   */
  public function __construct(ActionManager $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'action_admin_manage';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $actions = array();
    foreach ($this->manager->getDefinitions() as $id => $definition) {
      if (is_subclass_of($definition['class'], '\Drupal\Core\Plugin\PluginFormInterface')) {
        $key = Crypt::hashBase64($id);
        $actions[$key] = $definition['label'] . '...';
      }
    }
    $form['parent'] = array(
      '#type' => 'details',
      '#title' => t('Create an advanced action'),
      '#attributes' => array('class' => array('container-inline')),
    );
    $form['parent']['action'] = array(
      '#type' => 'select',
      '#title' => t('Action'),
      '#title_display' => 'invisible',
      '#options' => $actions,
      '#empty_option' => t('Choose an advanced action'),
    );
    $form['parent']['actions'] = array(
      '#type' => 'actions'
    );
    $form['parent']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Create'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    if ($form_state['values']['action']) {
      $form_state['redirect'] = 'admin/config/system/actions/add/' . $form_state['values']['action'];
    }
  }

}
