<?php

/**
 * @file
 * Definition of Drupal\system\Tests\System\DateTimeTest.
 */

namespace Drupal\system\Tests\System;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Language\Language;

/**
 * Tests generic date and time handling capabilities of Drupal.
 */
class DateTimeTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language');

  public static function getInfo() {
    return array(
      'name' => 'Date and time',
      'description' => 'Configure date and time settings. Test date formatting and time zone handling, including daylight saving time.',
      'group' => 'System',
    );
  }

  function setUp() {
    parent::setUp();

    // Create admin user and log in admin user.
    $this->admin_user = $this->drupalCreateUser(array('administer site configuration'));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Test time zones and DST handling.
   */
  function testTimeZoneHandling() {
    // Setup date/time settings for Honolulu time.
    $config = \Drupal::config('system.date')
      ->set('timezone.default', 'Pacific/Honolulu')
      ->set('timezone.user.configurable', 0)
      ->save();
    entity_load('date_format', 'medium')
      ->setPattern('Y-m-d H:i:s O')
      ->save();

    // Create some nodes with different authored-on dates.
    $date1 = '2007-01-31 21:00:00 -1000';
    $date2 = '2007-07-31 21:00:00 -1000';
    $node1 = $this->drupalCreateNode(array('created' => strtotime($date1), 'type' => 'article'));
    $node2 = $this->drupalCreateNode(array('created' => strtotime($date2), 'type' => 'article'));

    // Confirm date format and time zone.
    $this->drupalGet('node/' . $node1->id());
    $this->assertText('2007-01-31 21:00:00 -1000', 'Date should be identical, with GMT offset of -10 hours.');
    $this->drupalGet('node/' . $node2->id());
    $this->assertText('2007-07-31 21:00:00 -1000', 'Date should be identical, with GMT offset of -10 hours.');

    // Set time zone to Los Angeles time.
    $config->set('timezone.default', 'America/Los_Angeles')->save();

    // Confirm date format and time zone.
    $this->drupalGet('node/' . $node1->id());
    $this->assertText('2007-01-31 23:00:00 -0800', 'Date should be two hours ahead, with GMT offset of -8 hours.');
    $this->drupalGet('node/' . $node2->id());
    $this->assertText('2007-08-01 00:00:00 -0700', 'Date should be three hours ahead, with GMT offset of -7 hours.');
  }

  /**
   * Test date format configuration.
   */
  function testDateFormatConfiguration() {
    // Confirm 'no custom date formats available' message appears.
    $this->drupalGet('admin/config/regional/date-time');

    // Add custom date format.
    $this->clickLink(t('Add format'));
    $date_format_id = strtolower($this->randomName(8));
    $name = ucwords($date_format_id);
    $date_format = 'd.m.Y - H:i';
    $edit = array(
      'id' => $date_format_id,
      'label' => $name,
      'date_format_pattern' => $date_format,
    );
    $this->drupalPost('admin/config/regional/date-time/formats/add', $edit, t('Add format'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/date-time', array('absolute' => TRUE)), 'Correct page redirection.');
    $this->assertText(t('Custom date format added.'), 'Date format added confirmation message appears.');
    $this->assertText($date_format_id, 'Custom date format appears in the date format list.');
    $this->assertText(t('Delete'), 'Delete link for custom date format appears.');

    // Edit custom date format.
    $this->drupalGet('admin/config/regional/date-time');
    $this->clickLink(t('Edit'));
    $edit = array(
      'date_format_pattern' => 'Y m',
    );
    $this->drupalPost($this->getUrl(), $edit, t('Save format'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/date-time', array('absolute' => TRUE)), 'Correct page redirection.');
    $this->assertText(t('Custom date format updated.'), 'Custom date format successfully updated.');

    // Delete custom date format.
    $this->clickLink(t('Delete'));
    $this->drupalPost('admin/config/regional/date-time/formats/manage/' . $date_format_id . '/delete', array(), t('Remove'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/date-time', array('absolute' => TRUE)), 'Correct page redirection.');
    $this->assertText(t('Removed date format ' . $name), 'Custom date format removed.');

    // Make sure the date does not exist in config.
    $date_format = entity_load('date_format', $date_format_id);
    $this->assertFalse($date_format);
  }

  /**
   * Test if the date formats are stored properly.
   */
  function testDateFormatStorage() {
    $date_format = entity_create('date_format', array(
      'id' => 'test_short',
      'label' => 'testDateFormatStorage Short Format',
      'pattern' => array('php' => 'dmYHis'),
    ));
    $date_format->save();

    $format = $date_format->getPattern();
    $this->assertEqual('dmYHis', $format, 'Unlocalized date format resides in general config.');

    $date_format->addLocale('en')->save();
    $format = $date_format->getPattern();
    $this->assertEqual('dmYHis', $format, 'Localized date format resides in general config too.');

    $format = \Drupal::config('locale.config.en.system.date_format.test_short')->get('pattern.php');
    $this->assertEqual('dmYHis', $format, 'Localized date format resides in localized config.');
  }

  /**
   * Test that date formats are sanitized.
   */
  function testDateFormatXSS() {
    $date_format = entity_create('date_format', array(
      'id' => 'xss_short',
      'label' => 'XSS format',
      'pattern' => array('php' => '\<\s\c\r\i\p\t\>\a\l\e\r\t\(\'\X\S\S\'\)\;\<\/\s\c\r\i\p\t\>'),
    ));
    $date_format->save();

    $this->drupalGet('admin/config/regional/date-time');
    $this->assertNoRaw("<script>alert('XSS');</script>", 'The date format was properly sanitized');
  }
}
