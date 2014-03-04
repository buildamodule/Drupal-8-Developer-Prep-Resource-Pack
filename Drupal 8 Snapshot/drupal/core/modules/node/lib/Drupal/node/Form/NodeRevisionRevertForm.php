<?php

/**
 * @file
 * Contains \Drupal\node\Form\NodeRevisionDeleteForm.
 */

namespace Drupal\node\Form;

use Drupal\Core\Controller\ControllerInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a form for reverting a node revision.
 */
class NodeRevisionRevertForm extends ConfirmFormBase implements ControllerInterface {

  /**
   * The node revision.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $revision;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new NodeRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $node_storage
   *   The node storage.
   */
  public function __construct(EntityStorageControllerInterface $node_storage) {
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity')->getStorageController('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'node_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to revert to the revision from %revision-date?', array('%revision-date' => format_date($this->revision->getRevisionCreationTime())));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'node/' . $this->revision->id() . '/revisions';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL, $node_revision = NULL) {
    $this->revision = $this->nodeStorage->loadRevision($node_revision);
    return parent::buildForm($form, $form_state, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->revision->setNewRevision();
    // Make this the new default revision for the node.
    $this->revision->isDefaultRevision(TRUE);

    // The revision timestamp will be updated when the revision is saved. Keep the
    // original one for the confirmation message.
    $original_revision_timestamp = $this->revision->getRevisionCreationTime();

    $this->revision->log = t('Copy of the revision from %date.', array('%date' => format_date($original_revision_timestamp)));

    $this->revision->save();

    watchdog('content', '@type: reverted %title revision %revision.', array('@type' => $this->revision->bundle(), '%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()));
    drupal_set_message(t('@type %title has been reverted back to the revision from %revision-date.', array('@type' => node_get_type_label($this->revision), '%title' => $this->revision->label(), '%revision-date' => format_date($original_revision_timestamp))));
    $form_state['redirect'] = 'node/' . $this->revision->id() . '/revisions';
  }

}
