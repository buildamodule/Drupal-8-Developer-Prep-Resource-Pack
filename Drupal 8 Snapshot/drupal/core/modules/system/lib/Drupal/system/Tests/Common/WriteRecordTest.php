<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Common\WriteRecordTest.
 */

namespace Drupal\system\Tests\Common;

use Drupal\simpletest\WebTestBase;
use stdClass;

/**
 * Tests writing of data records with drupal_write_record().
 */
class WriteRecordTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('database_test');

  public static function getInfo() {
    return array(
      'name' => 'Data record write functionality',
      'description' => 'Tests writing of data records with drupal_write_record().',
      'group' => 'Common',
    );
  }

  /**
   * Tests the drupal_write_record() API function.
   */
  function testDrupalWriteRecord() {
    // Insert a record with no columns populated.
    $record = array();
    $insert_result = drupal_write_record('test', $record);
    $this->assertTrue($insert_result == SAVED_NEW, 'Correct value returned when an empty record is inserted with drupal_write_record().');

    // Insert a record - no columns allow NULL values.
    $person = new stdClass();
    $person->name = 'John';
    $person->unknown_column = 123;
    $insert_result = drupal_write_record('test', $person);
    $this->assertTrue($insert_result == SAVED_NEW, 'Correct value returned when a record is inserted with drupal_write_record() for a table with a single-field primary key.');
    $this->assertTrue(isset($person->id), 'Primary key is set on record created with drupal_write_record().');
    $this->assertIdentical($person->age, 0, 'Age field set to default value.');
    $this->assertIdentical($person->job, 'Undefined', 'Job field set to default value.');

    // Verify that the record was inserted.
    $result = db_query("SELECT * FROM {test} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'John', 'Name field set.');
    $this->assertIdentical($result->age, '0', 'Age field set to default value.');
    $this->assertIdentical($result->job, 'Undefined', 'Job field set to default value.');
    $this->assertFalse(isset($result->unknown_column), 'Unknown column was ignored.');

    // Update the newly created record.
    $person->name = 'Peter';
    $person->age = 27;
    $person->job = NULL;
    $update_result = drupal_write_record('test', $person, array('id'));
    $this->assertTrue($update_result == SAVED_UPDATED, 'Correct value returned when a record updated with drupal_write_record() for table with single-field primary key.');

    // Verify that the record was updated.
    $result = db_query("SELECT * FROM {test} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Peter', 'Name field set.');
    $this->assertIdentical($result->age, '27', 'Age field set.');
    $this->assertIdentical($result->job, '', 'Job field set and cast to string.');

    // Try to insert NULL in columns that does not allow this.
    $person = new stdClass();
    $person->name = 'Ringo';
    $person->age = NULL;
    $person->job = NULL;
    $insert_result = drupal_write_record('test', $person);
    $this->assertTrue(isset($person->id), 'Primary key is set on record created with drupal_write_record().');
    $result = db_query("SELECT * FROM {test} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Ringo', 'Name field set.');
    $this->assertIdentical($result->age, '0', 'Age field set.');
    $this->assertIdentical($result->job, '', 'Job field set.');

    // Insert a record - the "age" column allows NULL.
    $person = new stdClass();
    $person->name = 'Paul';
    $person->age = NULL;
    $insert_result = drupal_write_record('test_null', $person);
    $this->assertTrue(isset($person->id), 'Primary key is set on record created with drupal_write_record().');
    $result = db_query("SELECT * FROM {test_null} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Paul', 'Name field set.');
    $this->assertIdentical($result->age, NULL, 'Age field set.');

    // Insert a record - do not specify the value of a column that allows NULL.
    $person = new stdClass();
    $person->name = 'Meredith';
    $insert_result = drupal_write_record('test_null', $person);
    $this->assertTrue(isset($person->id), 'Primary key is set on record created with drupal_write_record().');
    $this->assertIdentical($person->age, 0, 'Age field set to default value.');
    $result = db_query("SELECT * FROM {test_null} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Meredith', 'Name field set.');
    $this->assertIdentical($result->age, '0', 'Age field set to default value.');

    // Update the newly created record.
    $person->name = 'Mary';
    $person->age = NULL;
    $update_result = drupal_write_record('test_null', $person, array('id'));
    $result = db_query("SELECT * FROM {test_null} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Mary', 'Name field set.');
    $this->assertIdentical($result->age, NULL, 'Age field set.');

    // Insert a record - the "data" column should be serialized.
    $person = new stdClass();
    $person->name = 'Dave';
    $update_result = drupal_write_record('test_serialized', $person);
    $result = db_query("SELECT * FROM {test_serialized} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical($result->name, 'Dave', 'Name field set.');
    $this->assertIdentical($result->info, NULL, 'Info field set.');

    $person->info = array();
    $update_result = drupal_write_record('test_serialized', $person, array('id'));
    $result = db_query("SELECT * FROM {test_serialized} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical(unserialize($result->info), array(), 'Info field updated.');

    // Update the serialized record.
    $data = array('foo' => 'bar', 1 => 2, 'empty' => '', 'null' => NULL);
    $person->info = $data;
    $update_result = drupal_write_record('test_serialized', $person, array('id'));
    $result = db_query("SELECT * FROM {test_serialized} WHERE id = :id", array(':id' => $person->id))->fetchObject();
    $this->assertIdentical(unserialize($result->info), $data, 'Info field updated.');

    // Run an update query where no field values are changed. The database
    // layer should return zero for number of affected rows, but
    // db_write_record() should still return SAVED_UPDATED.
    $update_result = drupal_write_record('test_null', $person, array('id'));
    $this->assertTrue($update_result == SAVED_UPDATED, 'Correct value returned when a valid update is run without changing any values.');

    // Insert an object record for a table with a multi-field primary key.
    $node_access = new stdClass();
    $node_access->nid = mt_rand();
    $node_access->gid = mt_rand();
    $node_access->realm = $this->randomName();
    $insert_result = drupal_write_record('node_access', $node_access);
    $this->assertTrue($insert_result == SAVED_NEW, 'Correct value returned when a record is inserted with drupal_write_record() for a table with a multi-field primary key.');

    // Update the record.
    $update_result = drupal_write_record('node_access', $node_access, array('nid', 'gid', 'realm'));
    $this->assertTrue($update_result == SAVED_UPDATED, 'Correct value returned when a record is updated with drupal_write_record() for a table with a multi-field primary key.');
  }

}
