<?php

/**
 * @file
 * Contains Drupal\system\Tests\Menu\LocalTasksTest.
 */

namespace Drupal\system\Tests\Menu;

use Drupal\simpletest\WebTestBase;

/**
 * Tests local tasks derived from router and added/altered via hooks.
 */
class LocalTasksTest extends WebTestBase {

  public static $modules = array('menu_test');

  public static function getInfo() {
    return array(
      'name' => 'Local tasks',
      'description' => 'Tests local tasks derived from router and added/altered via hooks.',
      'group' => 'Menu',
    );
  }

  /**
   * Tests appearance of local tasks.
   *
   * @see menu_test_menu()
   * @see menu_test_menu_local_tasks()
   * @see menu_test_menu_local_tasks_alter()
   */
  function testLocalTasks() {
    // Verify that there is no local tasks markup if none are defined in the
    // router and no module adds any dynamically.
    $this->drupalGet('menu-test/tasks/empty');
    $this->assertNoRaw('tabs');
    $this->drupalGet('menu-test/tasks/default');
    $this->assertNoRaw('tabs');

    // Verify that local tasks appear as defined in the router.
    $this->drupalGet('menu-test/tasks/tasks');
    $this->assertLocalTasks(array(
      // MENU_DEFAULT_LOCAL_TASK is expected to get a default weight of -10
      // (without having to define it manually), so it should appear first,
      // despite that its label is "View".
      'menu-test/tasks/tasks',
      'menu-test/tasks/tasks/edit',
      'menu-test/tasks/tasks/settings',
    ));

    // Enable addition of tasks in menu_test_menu_local_tasks().
    \Drupal::config('menu_test.settings')->set('tasks.add', TRUE)->save();

    // Verify that the added tasks appear even if there are no tasks normally.
    $this->drupalGet('menu-test/tasks/empty');
    $this->assertLocalTasks(array(
      'task/foo',
      'task/bar',
    ));

    // Verify that the default local task appears before the added tasks.
    $this->drupalGet('menu-test/tasks/default');
    $this->assertLocalTasks(array(
      'menu-test/tasks/default',
      'task/foo',
      'task/bar',
    ));

    // Verify that the added tasks appear within normal tasks.
    $this->drupalGet('menu-test/tasks/tasks');
    $this->assertLocalTasks(array(
      'menu-test/tasks/tasks',
      // The Edit task defines no weight, which is expected to sort as 0.
      'menu-test/tasks/tasks/edit',
      'task/foo',
      'task/bar',
      'menu-test/tasks/tasks/settings',
    ));

    // Enable manipulation of tasks in menu_test_menu_local_tasks_alter().
    \Drupal::config('menu_test.settings')->set('tasks.alter', TRUE)->save();

    // Verify that the added tasks appear even if there are no tasks normally.
    $this->drupalGet('menu-test/tasks/empty');
    $this->assertLocalTasks(array(
      'task/bar',
      'task/foo',
    ));
    $this->assertNoText('Show it');
    $this->assertText('Advanced settings');

    // Verify that the default local task appears before the added tasks.
    $this->drupalGet('menu-test/tasks/default');
    $this->assertLocalTasks(array(
      'menu-test/tasks/default',
      'task/bar',
      'task/foo',
    ));
    $this->assertText('Show it');
    $this->assertText('Advanced settings');

    // Verify that the added tasks appear within normal tasks.
    $this->drupalGet('menu-test/tasks/tasks');
    $this->assertLocalTasks(array(
      'menu-test/tasks/tasks',
      'menu-test/tasks/tasks/edit',
      'task/bar',
      'menu-test/tasks/tasks/settings',
      'task/foo',
    ));
    $this->assertText('Show it');
    $this->assertText('Advanced settings');
  }

  /**
   * Asserts local tasks in the page output.
   *
   * @param array $hrefs
   *   A list of expected link hrefs of local tasks to assert on the page (in
   *   the given order).
   * @param int $level
   *   (optional) The local tasks level to assert; 0 for primary, 1 for
   *   secondary. Defaults to 0.
   */
  protected function assertLocalTasks(array $hrefs, $level = 0) {
    $elements = $this->xpath('//*[contains(@class, :class)]//a', array(
      ':class' => $level == 0 ? 'tabs primary' : 'tabs secondary',
    ));
    $this->assertTrue(count($elements), 'Local tasks found.');
    foreach ($hrefs as $index => $element) {
      $expected = url($hrefs[$index]);
      $method = ($elements[$index]['href'] == $expected ? 'pass' : 'fail');
      $this->{$method}(format_string('Task @number href @value equals @expected.', array(
        '@number' => $index + 1,
        '@value' => (string) $elements[$index]['href'],
        '@expected' => $expected,
      )));
    }
  }

  /**
   * Tests the plugin based local tasks.
   */
  public function testPluginLocalTask() {
    // Verify that local tasks appear as defined in the router.
    $this->drupalGet('menu-local-task-test/tasks');

    $this->drupalGet('menu-local-task-test/tasks/view');
    $this->assertLocalTasks(array(
      'menu-local-task-test/tasks/view',
      'menu-local-task-test/tasks/settings',
      'menu-local-task-test/tasks/edit',
    ));

    // Ensure the view tab is active.
    $result = $this->xpath('//ul[contains(@class, "tabs")]//a[contains(@class, "active")]');
    $this->assertEqual(1, count($result), 'There is just a single active tab.');
    $this->assertEqual('View', (string) $result[0], 'The view tab is active.');

    // Verify that local tasks in the second level appear.

    $this->drupalGet('menu-local-task-test/tasks/settings');
    $this->assertLocalTasks(array(
      'menu-local-task-test/tasks/settings/sub1',
      'menu-local-task-test/tasks/settings/sub2',
    ), 1);

    $result = $this->xpath('//ul[contains(@class, "tabs")]//a[contains(@class, "active")]');
    $this->assertEqual(1, count($result), 'There is just a single active tab.');
    $this->assertEqual('Settings', (string) $result[0], 'The settings tab is active.');


    $this->drupalGet('menu-local-task-test/tasks/settings/sub1');
    $this->assertLocalTasks(array(
      'menu-local-task-test/tasks/settings/sub1',
      'menu-local-task-test/tasks/settings/sub2',
    ), 1);

    $result = $this->xpath('//ul[contains(@class, "tabs")]//a[contains(@class, "active")]');
    $this->assertEqual(2, count($result), 'There are tabs active on both levels.');
    $this->assertEqual('Settings', (string) $result[0], 'The settings tab is active.');
    $this->assertEqual('sub1', (string) $result[1], 'The sub1 tab is active.');
  }

}
