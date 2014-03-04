<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Entity\EntityTranslationTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\Core\Language\Language;
use Drupal\Core\TypedData\TranslatableInterface;
use InvalidArgumentException;

/**
 * Tests entity translation.
 */
class EntityTranslationTest extends EntityUnitTestBase {

  protected $langcodes;

  public static $modules = array('language', 'entity_test');

  public static function getInfo() {
    return array(
      'name' => 'Entity Translation',
      'description' => 'Tests entity translation functionality.',
      'group' => 'Entity API',
    );
  }

  function setUp() {
    parent::setUp();

    $this->installSchema('system', 'variable');
    $this->installSchema('entity_test', array(
      'entity_test_mul',
      'entity_test_mul_property_data',
      'entity_test_rev',
      'entity_test_rev_revision',
      'entity_test_mulrev',
      'entity_test_mulrev_property_data',
      'entity_test_mulrev_property_revision',
    ));
    $this->installConfig(array('language'));

    // Create the test field.
    entity_test_install();

    // Enable translations for the test entity type.
    $this->state->set('entity_test.translation', TRUE);

    // Create a translatable test field.
    $this->field_name = drupal_strtolower($this->randomName() . '_field_name');
    entity_create('field_entity', array(
      'field_name' => $this->field_name,
      'type' => 'text',
      'cardinality' => 4,
      'translatable' => TRUE,
    ))->save();
    $this->field = field_read_field($this->field_name);

    // Create instance in all entity variations.
    foreach (entity_test_entity_types() as $entity_type) {
      entity_create('field_instance', array(
        'field_name' => $this->field_name,
        'entity_type' => $entity_type,
        'bundle' => $entity_type,
      ))->save();
      $this->instance[$entity_type] = field_read_instance($entity_type, $this->field_name, $entity_type);
    }

    // Create the default languages.
    $default_language = language_save(language_default());
    $languages = language_default_locked_languages($default_language->weight);
    foreach ($languages as $language) {
      language_save($language);
    }

    // Create test languages.
    $this->langcodes = array();
    for ($i = 0; $i < 3; ++$i) {
      $language = new Language(array(
        'id' => 'l' . $i,
        'name' => $this->randomString(),
      ));
      $this->langcodes[$i] = $language->id;
      language_save($language);
    }
  }

  /**
   * Tests language related methods of the Entity class.
   */
  public function testEntityLanguageMethods() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertEntityLanguageMethods($entity_type);
    }
  }

  /**
   * Executes the entity language method tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertEntityLanguageMethods($entity_type) {
    $entity = entity_create($entity_type, array(
      'name' => 'test',
      'user_id' => $GLOBALS['user']->id(),
    ));
    $this->assertEqual($entity->language()->id, Language::LANGCODE_NOT_SPECIFIED, format_string('%entity_type: Entity language not specified.', array('%entity_type' => $entity_type)));
    $this->assertFalse($entity->getTranslationLanguages(FALSE), format_string('%entity_type: No translations are available', array('%entity_type' => $entity_type)));

    // Set the value in default language.
    $entity->set($this->field_name, array(0 => array('value' => 'default value')));
    // Get the value.
    $this->assertEqual($entity->getTranslation(Language::LANGCODE_DEFAULT)->get($this->field_name)->value, 'default value', format_string('%entity_type: Untranslated value retrieved.', array('%entity_type' => $entity_type)));

    // Set the value in a certain language. As the entity is not
    // language-specific it should use the default language and so ignore the
    // specified language.
    $entity->getTranslation($this->langcodes[1])->set($this->field_name, array(0 => array('value' => 'default value2')));
    $this->assertEqual($entity->get($this->field_name)->value, 'default value2', format_string('%entity_type: Untranslated value updated.', array('%entity_type' => $entity_type)));
    $this->assertFalse($entity->getTranslationLanguages(FALSE), format_string('%entity_type: No translations are available', array('%entity_type' => $entity_type)));

    // Test getting a field value using a specific language for a not
    // language-specific entity.
    $this->assertEqual($entity->getTranslation($this->langcodes[1])->get($this->field_name)->value, 'default value2', format_string('%entity_type: Untranslated value retrieved.', array('%entity_type' => $entity_type)));

    // Now, make the entity language-specific by assigning a language and test
    // translating it.
    $entity->langcode->value = $this->langcodes[0];
    $entity->{$this->field_name} = array();
    $this->assertEqual($entity->language(), language_load($this->langcodes[0]), format_string('%entity_type: Entity language retrieved.', array('%entity_type' => $entity_type)));
    $this->assertFalse($entity->getTranslationLanguages(FALSE), format_string('%entity_type: No translations are available', array('%entity_type' => $entity_type)));

    // Set the value in default language.
    $entity->set($this->field_name, array(0 => array('value' => 'default value')));
    // Get the value.
    $this->assertEqual($entity->get($this->field_name)->value, 'default value', format_string('%entity_type: Untranslated value retrieved.', array('%entity_type' => $entity_type)));

    // Set a translation.
    $entity->getTranslation($this->langcodes[1])->set($this->field_name, array(0 => array('value' => 'translation 1')));
    $this->assertEqual($entity->getTranslation($this->langcodes[1])->{$this->field_name}->value, 'translation 1', format_string('%entity_type: Translated value set.', array('%entity_type' => $entity_type)));

    // Make sure the untranslated value stays.
    $this->assertEqual($entity->get($this->field_name)->value, 'default value', 'Untranslated value stays.');

    $translations[$this->langcodes[1]] = language_load($this->langcodes[1]);
    $this->assertEqual($entity->getTranslationLanguages(FALSE), $translations, 'Translations retrieved.');

    // Try to get a not available translation.
    $this->assertNull($entity->getTranslation($this->langcodes[2])->get($this->field_name)->value, format_string('%entity_type: A translation that is not available is NULL.', array('%entity_type' => $entity_type)));

    // Try to get a value using an invalid language code.
    try {
      $entity->getTranslation('invalid')->get($this->field_name)->value;
      $this->fail('Getting a translation for an invalid language is NULL.');
    }
    catch (InvalidArgumentException $e) {
      $this->pass('A translation for an invalid language is NULL.');
    }

    // Try to set a value using an invalid language code.
    try {
      $entity->getTranslation('invalid')->set($this->field_name, NULL);
      $this->fail(format_string('%entity_type: Setting a translation for an invalid language throws an exception.', array('%entity_type' => $entity_type)));
    }
    catch (InvalidArgumentException $e) {
      $this->pass(format_string('%entity_type: Setting a translation for an invalid language throws an exception.', array('%entity_type' => $entity_type)));
    }

    // Set the value in default language.
    $field_name = 'field_test_text';
    $entity->getTranslation($this->langcodes[1])->set($field_name, array(0 => array('value' => 'default value2')));
    // Get the value.
    $this->assertEqual($entity->get($field_name)->value, 'default value2', format_string('%entity_type: Untranslated value set into a translation in non-strict mode.', array('%entity_type' => $entity_type)));
  }

  /**
   * Tests multilingual properties.
   */
  public function testMultilingualProperties() {
    // Test all entity variations with data table support.
    foreach (entity_test_entity_types(ENTITY_TEST_TYPES_MULTILINGUAL) as $entity_type) {
      $this->assertMultilingualProperties($entity_type);
    }
  }

  /**
   * Executes the multilingual property tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertMultilingualProperties($entity_type) {
    $name = $this->randomName();
    $uid = mt_rand(0, 127);
    $langcode = $this->langcodes[0];

    // Create a language neutral entity and check that properties are stored
    // as language neutral.
    $entity = entity_create($entity_type, array('name' => $name, 'user_id' => $uid));
    $entity->save();
    $entity = entity_load($entity_type, $entity->id());
    $this->assertEqual($entity->language()->id, Language::LANGCODE_NOT_SPECIFIED, format_string('%entity_type: Entity created as language neutral.', array('%entity_type' => $entity_type)));
    $this->assertEqual($name, $entity->getTranslation(Language::LANGCODE_DEFAULT)->get('name')->value, format_string('%entity_type: The entity name has been correctly stored as language neutral.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->getTranslation(Language::LANGCODE_DEFAULT)->get('user_id')->target_id, format_string('%entity_type: The entity author has been correctly stored as language neutral.', array('%entity_type' => $entity_type)));
    // As fields, translatable properties should ignore the given langcode and
    // use neutral language if the entity is not translatable.
    $this->assertEqual($name, $entity->getTranslation($langcode)->get('name')->value, format_string('%entity_type: The entity name defaults to neutral language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->getTranslation($langcode)->get('user_id')->target_id, format_string('%entity_type: The entity author defaults to neutral language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($name, $entity->get('name')->value, format_string('%entity_type: The entity name can be retrieved without specifying a language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->get('user_id')->target_id, format_string('%entity_type: The entity author can be retrieved without specifying a language.', array('%entity_type' => $entity_type)));

    // Create a language-aware entity and check that properties are stored
    // as language-aware.
    $entity = entity_create($entity_type, array('name' => $name, 'user_id' => $uid, 'langcode' => $langcode));
    $entity->save();
    $entity = entity_load($entity_type, $entity->id());
    $this->assertEqual($entity->language()->id, $langcode, format_string('%entity_type: Entity created as language specific.', array('%entity_type' => $entity_type)));
    $this->assertEqual($name, $entity->getTranslation($langcode)->get('name')->value, format_string('%entity_type: The entity name has been correctly stored as a language-aware property.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->getTranslation($langcode)->get('user_id')->target_id, format_string('%entity_type: The entity author has been correctly stored as a language-aware property.', array('%entity_type' => $entity_type)));
    // Translatable properties on a translatable entity should use default
    // language if Language::LANGCODE_NOT_SPECIFIED is passed.
    $this->assertEqual($name, $entity->getTranslation(Language::LANGCODE_NOT_SPECIFIED)->get('name')->value, format_string('%entity_type: The entity name defaults to the default language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->getTranslation(Language::LANGCODE_NOT_SPECIFIED)->get('user_id')->target_id, format_string('%entity_type: The entity author defaults to the default language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($name, $entity->get('name')->value, format_string('%entity_type: The entity name can be retrieved without specifying a language.', array('%entity_type' => $entity_type)));
    $this->assertEqual($uid, $entity->get('user_id')->target_id, format_string('%entity_type: The entity author can be retrieved without specifying a language.', array('%entity_type' => $entity_type)));

    // Create property translations.
    $properties = array();
    $default_langcode = $langcode;
    foreach ($this->langcodes as $langcode) {
      if ($langcode != $default_langcode) {
        $properties[$langcode] = array(
          'name' => array(0 => $this->randomName()),
          'user_id' => array(0 => mt_rand(128, 256)),
        );
      }
      else {
        $properties[$langcode] = array(
          'name' => array(0 => $name),
          'user_id' => array(0 => $uid),
        );
      }
      $entity->getTranslation($langcode)->setPropertyValues($properties[$langcode]);
    }
    $entity->save();

    // Check that property translation were correctly stored.
    $entity = entity_load($entity_type, $entity->id());
    foreach ($this->langcodes as $langcode) {
      $args = array(
        '%entity_type' => $entity_type,
        '%langcode' => $langcode,
      );
      $this->assertEqual($properties[$langcode]['name'][0], $entity->getTranslation($langcode)->get('name')->value, format_string('%entity_type: The entity name has been correctly stored for language %langcode.', $args));
      $this->assertEqual($properties[$langcode]['user_id'][0], $entity->getTranslation($langcode)->get('user_id')->target_id, format_string('%entity_type: The entity author has been correctly stored for language %langcode.', $args));
    }

    // Test query conditions (cache is reset at each call).
    $translated_id = $entity->id();
    // Create an additional entity with only the uid set. The uid for the
    // original language is the same of one used for a translation.
    $langcode = $this->langcodes[1];
    entity_create($entity_type, array(
      'user_id' => $properties[$langcode]['user_id'],
      'name' => 'some name',
    ))->save();

    $entities = entity_load_multiple($entity_type);
    $this->assertEqual(count($entities), 3, format_string('%entity_type: Three entities were created.', array('%entity_type' => $entity_type)));
    $entities = entity_load_multiple($entity_type, array($translated_id));
    $this->assertEqual(count($entities), 1, format_string('%entity_type: One entity correctly loaded by id.', array('%entity_type' => $entity_type)));
    $entities = entity_load_multiple_by_properties($entity_type, array('name' => $name));
    $this->assertEqual(count($entities), 2, format_string('%entity_type: Two entities correctly loaded by name.', array('%entity_type' => $entity_type)));
    // @todo The default language condition should go away in favor of an
    // explicit parameter.
    $entities = entity_load_multiple_by_properties($entity_type, array('name' => $properties[$langcode]['name'][0], 'default_langcode' => 0));
    $this->assertEqual(count($entities), 1, format_string('%entity_type: One entity correctly loaded by name translation.', array('%entity_type' => $entity_type)));
    $entities = entity_load_multiple_by_properties($entity_type, array('langcode' => $default_langcode, 'name' => $name));
    $this->assertEqual(count($entities), 1, format_string('%entity_type: One entity correctly loaded by name and language.', array('%entity_type' => $entity_type)));

    $entities = entity_load_multiple_by_properties($entity_type, array('langcode' => $langcode, 'name' => $properties[$langcode]['name'][0]));
    $this->assertEqual(count($entities), 0, format_string('%entity_type: No entity loaded by name translation specifying the translation language.', array('%entity_type' => $entity_type)));
    $entities = entity_load_multiple_by_properties($entity_type, array('langcode' => $langcode, 'name' => $properties[$langcode]['name'][0], 'default_langcode' => 0));
    $this->assertEqual(count($entities), 1, format_string('%entity_type: One entity loaded by name translation and language specifying to look for translations.', array('%entity_type' => $entity_type)));
    $entities = entity_load_multiple_by_properties($entity_type, array('user_id' => $properties[$langcode]['user_id'][0], 'default_langcode' => NULL));
    $this->assertEqual(count($entities), 2, format_string('%entity_type: Two entities loaded by uid without caring about property translatability.', array('%entity_type' => $entity_type)));

    // Test property conditions and orders with multiple languages in the same
    // query.
    $query = \Drupal::entityQuery($entity_type);
    $group = $query->andConditionGroup()
      ->condition('user_id', $properties[$default_langcode]['user_id'], '=', $default_langcode)
      ->condition('name', $properties[$default_langcode]['name'], '=', $default_langcode);
    $result = $query
      ->condition($group)
      ->condition('name', $properties[$langcode]['name'], '=', $langcode)
      ->execute();
    $this->assertEqual(count($result), 1, format_string('%entity_type: One entity loaded by name and uid using different language meta conditions.', array('%entity_type' => $entity_type)));

    // Test mixed property and field conditions.
    $entity = entity_load($entity_type, reset($result), TRUE);
    $field_value = $this->randomString();
    $entity->getTranslation($langcode)->set($this->field_name, array(array('value' => $field_value)));
    $entity->save();
    $query = \Drupal::entityQuery($entity_type);
    $default_langcode_group = $query->andConditionGroup()
      ->condition('user_id', $properties[$default_langcode]['user_id'], '=', $default_langcode)
      ->condition('name', $properties[$default_langcode]['name'], '=', $default_langcode);
    $langcode_group = $query->andConditionGroup()
      ->condition('name', $properties[$langcode]['name'], '=', $langcode)
      ->condition("$this->field_name.value", $field_value, '=', $langcode);
    $result = $query
      ->condition('langcode', $default_langcode)
      ->condition($default_langcode_group)
      ->condition($langcode_group)
      ->execute();
    $this->assertEqual(count($result), 1, format_string('%entity_type: One entity loaded by name, uid and field value using different language meta conditions.', array('%entity_type' => $entity_type)));
  }

  /**
   * Tests the Entity Translation API behavior.
   */
  function testEntityTranslationAPI() {
    $default_langcode = $this->langcodes[0];
    $langcode = $this->langcodes[1];
    $entity = $this->entityManager
      ->getStorageController('entity_test_mul')
      ->create(array('name' => $this->randomName()));

    $entity->save();
    $hooks = $this->getHooksInfo();
    $this->assertFalse($hooks, 'No entity translation hooks are fired when creating an entity.');

    // Verify that we obtain the entity object itself when we attempt to
    // retrieve a translation referring to it.
    $translation = $entity->getTranslation($langcode);
    $this->assertEqual($entity, $translation, 'The translation object corresponding to a non-default language is the entity object itself when the entity is language-neutral.');
    $entity->langcode->value = $default_langcode;
    $translation = $entity->getTranslation($default_langcode);
    $this->assertEqual($entity, $translation, 'The translation object corresponding to the default language (explicit) is the entity object itself.');
    $translation = $entity->getTranslation(Language::LANGCODE_DEFAULT);
    $this->assertEqual($entity, $translation, 'The translation object corresponding to the default language (implicit) is the entity object itself.');

    // Create a translation and verify that the translation object and the
    // original object behave independently.
    $name = $default_langcode . '_' . $this->randomName();
    $entity->name->value = $name;
    $name_translated = $langcode . '_' . $this->randomName();
    $translation = $entity->addTranslation($langcode);
    $this->assertNotEqual($entity, $translation, 'The entity and the translation object differ from one another.');
    $this->assertTrue($entity->hasTranslation($langcode), 'The new translation exists.');
    $this->assertEqual($translation->language()->id, $langcode, 'The translation language matches the specified one.');
    $this->assertEqual($translation->getUntranslated()->language()->id, $default_langcode, 'The original language can still be retrieved.');
    $translation->name->value = $name_translated;
    $this->assertEqual($entity->name->value, $name, 'The original name is retained after setting a translated value.');
    $entity->name->value = $name;
    $this->assertEqual($translation->name->value, $name_translated, 'The translated name is retained after setting the original value.');

    // Save the translation and check that the expecte hooks are fired.
    $translation->save();
    $hooks = $this->getHooksInfo();
    $this->assertEqual($hooks['entity_translation_insert'], $langcode, 'The generic entity translation insertion hook has fired.');
    $this->assertEqual($hooks['entity_test_mul_translation_insert'], $langcode, 'The entity-type-specific entity translation insertion hook has fired.');

    // Check that after loading an entity the language is the default one.
    $entity = $this->reloadEntity($entity);
    $this->assertEqual($entity->language()->id, $default_langcode, 'The loaded entity is the original one.');

    // Add another translation and check that everything works as expected. A
    // new translation object can be obtained also by just specifying a valid
    // language.
    $langcode2 = $this->langcodes[2];
    $translation = $entity->getTranslation($langcode2);
    $value = $entity != $translation && $translation->language()->id == $langcode2 && $entity->hasTranslation($langcode2);
    $this->assertTrue($value, 'A new translation object can be obtained also by specifying a valid language.');
    $this->assertEqual($entity->language()->id, $default_langcode, 'The original language has been preserved.');
    $translation->save();
    $hooks = $this->getHooksInfo();
    $this->assertEqual($hooks['entity_translation_insert'], $langcode2, 'The generic entity translation insertion hook has fired.');
    $this->assertEqual($hooks['entity_test_mul_translation_insert'], $langcode2, 'The entity-type-specific entity translation insertion hook has fired.');

    // Verify that trying to manipulate a translation object referring to a
    // removed translation results in exceptions being thrown.
    $entity = $this->reloadEntity($entity);
    $translation = $entity->getTranslation($langcode2);
    $entity->removeTranslation($langcode2);
    foreach (array('get', 'set', '__get', '__set', 'createDuplicate') as $method) {
      $message = format_string('The @method method raises an exception when trying to manipulate a removed translation.', array('@method' => $method));
      try {
        $translation->{$method}('name', $this->randomName());
        $this->fail($message);
      }
      catch (\Exception $e) {
        $this->pass($message);
      }
    }

    // Verify that deletion hooks are fired when saving an entity with a removed
    // translation.
    $entity->save();
    $hooks = $this->getHooksInfo();
    $this->assertEqual($hooks['entity_translation_delete'], $langcode2, 'The generic entity translation deletion hook has fired.');
    $this->assertEqual($hooks['entity_test_mul_translation_delete'], $langcode2, 'The entity-type-specific entity translation deletion hook has fired.');
    $entity = $this->reloadEntity($entity);
    $this->assertFalse($entity->hasTranslation($langcode2), 'The translation does not appear among available translations after saving the entity.');

    // Check that removing an invalid translation causes an exception to be
    // thrown.
    foreach (array($default_langcode, Language::LANGCODE_DEFAULT, $this->randomName()) as $invalid_langcode) {
      $message = format_string('Removing an invalid translation (@langcode) causes an exception to be thrown.', array('@langcode' => $invalid_langcode));
      try {
        $entity->removeTranslation($invalid_langcode);
        $this->fail($message);
      }
      catch (\Exception $e) {
        $this->pass($message);
      }
    }

    // Check that hooks are fired only when actually storing data.
    $entity = $this->reloadEntity($entity);
    $entity->addTranslation($langcode2);
    $entity->removeTranslation($langcode2);
    $entity->save();
    $hooks = $this->getHooksInfo();
    $this->assertFalse($hooks, 'No hooks are run when adding and removing a translation without storing it.');

    // Verify that entity serialization does not cause stale references to be
    // left around.
    $entity = $this->reloadEntity($entity);
    $translation = $entity->getTranslation($langcode);
    $entity = unserialize(serialize($entity));
    $entity->name->value = $this->randomName();
    $name = $default_langcode . '_' . $this->randomName();
    $entity->getTranslation($default_langcode)->name->value = $name;
    $this->assertEqual($entity->name->value, $name, 'No stale reference for the translation object corresponding to the original language.');
    $translation2 = $entity->getTranslation($langcode);
    $translation2->name->value .= $this->randomName();
    $this->assertNotEqual($translation->name->value, $translation2->name->value, 'No stale reference for the actual translation object.');
    $this->assertEqual($entity, $translation2->getUntranslated(), 'No stale reference in the actual translation object.');

    // Verify that deep-cloning is still available when we are not instantiating
    // a translation object, which instead relies on shallow cloning.
    $entity = $this->reloadEntity($entity);
    $entity->getTranslation($langcode);
    $cloned = clone $entity;
    $translation = $cloned->getTranslation($langcode);
    $this->assertNotEqual($entity, $translation->getUntranslated(), 'A cloned entity object has no reference to the original one.');

    // Check that per-language defaults are properly populated.
    $entity = $this->reloadEntity($entity);
    $instance_id = implode('.', array($entity->entityType(), $entity->bundle(), $this->field_name));
    $instance = $this->entityManager->getStorageController('field_instance')->load($instance_id);
    $instance['default_value_function'] = 'entity_test_field_default_value';
    $instance->save();
    $translation = $entity->addTranslation($langcode2);
    $this->assertEqual($translation->get($this->field_name)->value, $this->field_name . '_' . $langcode2, 'Language-aware default values correctly populated.');
  }

}
