<?php

/**
 * @file
 * Definition of Drupal\user\Plugin\views\wizard\Users.
 */

namespace Drupal\user\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * @todo: replace numbers with constants.
 */

/**
 * Tests creating user views with the wizard.
 *
 * @Plugin(
 *   id = "users",
 *   module = "user",
 *   base_table = "users",
 *   title = @Translation("Users")
 * )
 */
class Users extends WizardPluginBase {

  /**
   * Set the created column.
   */
  protected $createdColumn = 'created';

  /**
   * Set default values for the path field options.
   */
  protected $pathField = array(
    'id' => 'uid',
    'table' => 'users',
    'field' => 'uid',
    'exclude' => TRUE,
    'link_to_user' => FALSE,
    'alter' => array(
      'alter_text' => TRUE,
      'text' => 'user/[uid]'
    )
  );

  /**
   * Set default values for the filters.
   */
  protected $filters = array(
    'status' => array(
      'value' => TRUE,
      'table' => 'users',
      'field' => 'status',
      'provider' => 'user'
    )
  );

  /**
   * Overrides Drupal\views\Plugin\views\wizard\WizardPluginBase::defaultDisplayOptions().
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['perm'] = 'access user profiles';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    /* Field: User: Name */
    $display_options['fields']['name']['id'] = 'name';
    $display_options['fields']['name']['table'] = 'users';
    $display_options['fields']['name']['field'] = 'name';
    $display_options['fields']['name']['provider'] = 'user';
    $display_options['fields']['name']['label'] = '';
    $display_options['fields']['name']['alter']['alter_text'] = 0;
    $display_options['fields']['name']['alter']['make_link'] = 0;
    $display_options['fields']['name']['alter']['absolute'] = 0;
    $display_options['fields']['name']['alter']['trim'] = 0;
    $display_options['fields']['name']['alter']['word_boundary'] = 0;
    $display_options['fields']['name']['alter']['ellipsis'] = 0;
    $display_options['fields']['name']['alter']['strip_tags'] = 0;
    $display_options['fields']['name']['alter']['html'] = 0;
    $display_options['fields']['name']['hide_empty'] = 0;
    $display_options['fields']['name']['empty_zero'] = 0;
    $display_options['fields']['name']['link_to_user'] = 1;
    $display_options['fields']['name']['overwrite_anonymous'] = 0;

    return $display_options;
  }

}
