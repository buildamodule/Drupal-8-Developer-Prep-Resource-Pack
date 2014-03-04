<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\pager\Full.
 */

namespace Drupal\views\Plugin\views\pager;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * The plugin to handle full pager.
 *
 * @ingroup views_pager_plugins
 *
 * @Plugin(
 *   id = "full",
 *   title = @Translation("Paged output, full pager"),
 *   short_title = @Translation("Full"),
 *   help = @Translation("Paged output, full Drupal style"),
 *   theme = "pager",
 *   register_theme = FALSE
 * )
 */
class Full extends SqlBase {

  /**
   * Overrides \Drupal\views\Plugin\views\SqlBase::defineOptions().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Use the same default quantity that core uses by default.
    $options['quantity'] = array('default' => 9);

    $options['tags']['contains']['first'] = array('default' => '« first', 'translatable' => TRUE);
    $options['tags']['contains']['last'] = array('default' => 'last »', 'translatable' => TRUE);

    return $options;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\SqlBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['quantity'] = array(
      '#type' => 'number',
      '#title' => t('Number of pager links visible'),
      '#description' => t('Specify the number of links to pages to display in the pager.'),
      '#default_value' => $this->options['quantity'],
    );

    $form['tags']['first'] = array(
      '#type' => 'textfield',
      '#title' => t('First page link text'),
      '#default_value' => $this->options['tags']['first'],
      '#weight' => -10,
    );

    $form['tags']['last'] = array(
      '#type' => 'textfield',
      '#title' => t('Last page link text'),
      '#default_value' => $this->options['tags']['last'],
      '#weight' => 10,
    );
  }

  /**
   * Overrides \Drupal\views\Plugin\views\pager\PagerPluginBase::summaryTitle().
   */
  public function summaryTitle() {
    if (!empty($this->options['offset'])) {
      return format_plural($this->options['items_per_page'], '@count item, skip @skip', 'Paged, @count items, skip @skip', array('@count' => $this->options['items_per_page'], '@skip' => $this->options['offset']));
    }
    return format_plural($this->options['items_per_page'], '@count item', 'Paged, @count items', array('@count' => $this->options['items_per_page']));
  }

  /**
   * {@inheritdoc}
   */
  public function render($input) {
    // The 0, 1, 3, 4 indexes are correct. See the template_preprocess_pager()
    // documentation.
    $tags = array(
      0 => $this->options['tags']['first'],
      1 => $this->options['tags']['previous'],
      3 => $this->options['tags']['next'],
      4 => $this->options['tags']['last'],
    );
    return array(
      '#theme' => $this->themeFunctions(),
      '#tags' => $tags,
      '#element' => $this->options['id'],
      '#parameters' => $input,
      '#quantity' => $this->options['quantity'],
    );
  }


}
