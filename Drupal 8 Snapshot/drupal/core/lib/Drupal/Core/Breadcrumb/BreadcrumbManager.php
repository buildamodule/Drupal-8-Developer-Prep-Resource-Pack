<?php

/**
 * @file
 * Contains \Drupal\Core\Breadcrumb\BreadcrumbManager.
 */

namespace Drupal\Core\Breadcrumb;

/**
 * Provides a breadcrumb manager.
 *
 * Holds an array of path processor objects and uses them to sequentially process
 * a path, in order of processor priority.
 */
class BreadcrumbManager implements BreadcrumbBuilderInterface {

  /**
   * Holds arrays of breadcrumb builders, keyed by priority.
   *
   * @var array
   */
  protected $builders = array();

  /**
   * Holds the array of breadcrumb builders sorted by priority.
   *
   * Set to NULL if the array needs to be re-calculated.
   *
   * @var array|NULL
   */
  protected $sortedBuilders;

  /**
   * Adds another breadcrumb builder.
   *
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $builder
   *   The breadcrumb builder to add.
   * @param int $priority
   *   Priority of the breadcrumb builder.
   */
  public function addBuilder(BreadcrumbBuilderInterface $builder, $priority) {
    $this->builders[$priority][] = $builder;
    // Force the builders to be re-sorted.
    $this->sortedBuilders = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $attributes) {
    // Call the build method of registered breadcrumb builders,
    // until one of them returns an array.
    foreach ($this->getSortedBuilders() as $builder) {
      $breadcrumb = $builder->build($attributes);
      if (!isset($breadcrumb)) {
        // The builder returned NULL, so we continue with the other builders.
        continue;
      }
      elseif (is_array($breadcrumb)) {
        // The builder returned an array of breadcrumb links.
        return $breadcrumb;
      }
      else {
        throw new \UnexpectedValueException(format_string('Invalid breadcrumb returned by !class::build().', array('!class' => get_class($builder))));
      }
    }

    // Fall back to an empty breadcrumb.
    return array();
  }

  /**
   * Returns the sorted array of breadcrumb builders.
   *
   * @return array
   *   An array of breadcrumb builder objects.
   */
  protected function getSortedBuilders() {
    if (!isset($this->sortedBuilders)) {
      // Sort the builders according to priority.
      krsort($this->builders);
      // Merge nested builders from $this->builders into $this->sortedBuilders.
      $this->sortedBuilders = array();
      foreach ($this->builders as $builders) {
        $this->sortedBuilders = array_merge($this->sortedBuilders, $builders);
      }
    }
    return $this->sortedBuilders;
  }

}
