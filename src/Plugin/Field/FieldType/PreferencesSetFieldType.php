<?php

namespace Drupal\emailservice\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the 'preferences_set_field_type' field type.
 *
 * @FieldType(
 *   id = "preferences_set_field_type",
 *   label = @Translation("Preferences Set"),
 *   description = @Translation("Preferences Set field"),
 *   default_widget = "preferences_set_widget",
 *   default_formatter = "preferences_set_formatter"
 * )
 */
class PreferencesSetFieldType extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Preference label'))
      ->setRequired(TRUE);

    $properties['machine_name'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Preference machine name'))
      ->setRequired(TRUE);

    $properties['cql_query'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Preference CQL Query'))
      ->setRequired(TRUE);

    $properties['status'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Preference status'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'label' => [
          'type' => 'varchar',
          'description' => '',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'machine_name' => [
          'type' => 'varchar',
          'description' => '',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'cql_query' => [
          'type' => 'varchar',
          'description' => '',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'status' => [
          'type' => 'int',
          'description' => '',
          'not null' => TRUE,
          'default' => 1,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $fields = [
      'label',
      'machine_name',
      'cql_query',
      'status',
    ];

    foreach ($fields as $field) {
      $value = $this->get($field)->getValue();
      return $value === NULL || $value === '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $user = $this->getEntity()->get('uid')->target_id;
    $loaded_user = User::load($user);

    $prefix = $loaded_user->get('field_alias')->value;
    $machine_name = preg_replace('@[^a-z0-9-]+@', '-', strtolower($this->getValue()['label']));
    $this->set('machine_name', $prefix . '_' . $machine_name);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // Fetch entity.
    $entity = $this->getEntity();

    // Get current entity_id.
    $entity_id = $entity->get('nid')->getValue();

    // Get preferences.
    $preference = $this->getValue();

    // Extend preferences array.
    $db_pref = [
      'entity_id' => $entity_id[0]['value'],
      'preference_type' => $this->getFieldDefinition()->getName(),
    ] + $preference;

    $connection = \Drupal::database();
    $connection->merge('emailservice_preferences_mapping')
      ->key('machine_name', $db_pref['machine_name'])
      ->fields($db_pref)
      ->execute();
  }

}
