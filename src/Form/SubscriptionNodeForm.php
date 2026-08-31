<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\emailservice\Helpers\PreferencesSetHelper;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\NodeForm;

/**
 * Class SubscriptionNodeForm.
 *
 * @package Drupal\emailservice\Form
 */
class SubscriptionNodeForm extends NodeForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $node = $this->entity;

    if ($node->getType() == 'subscription') {
      $fields = [
        'new_arrivals_categories' => 'field_types_categories',
      ];

      $connection = \Drupal::database();
      $subscriber_fields = [];
      $has_changes = FALSE;

      foreach ($fields as $alias => $field) {
        $field_values = $form_state->getValue($field);

        foreach ($field_values as $key => $field_value) {

          if ($field_value['machine_name'] == 'stub' && !empty($field_value['label'])) {
            $machine_name = PreferencesSetHelper::generateMachineName($node, $field_value['label'], $field_value['material_tid']);
            $field_value['machine_name'] = $machine_name;
            $has_changes = TRUE;
          }

          if (empty($field_value['label']) && !empty($field_value['machine_name'])) {
            $connection->update('emailservice_preferences_mapping')
              ->fields(['status' => 0])
              ->condition('machine_name', $field_value['machine_name'])
              ->execute();
          }

          $fields_to_check = ['label', 'cql_query', 'material_tid'];

          foreach ($fields_to_check as $item) {
            // Update changed preferences.
            $element_value = $form[$field]['widget'][$key][$item]['#value'];
            $element_default_value = $form[$field]['widget'][$key][$item]['#default_value'];

            if ($element_value != $element_default_value) {
              $has_changes = TRUE;

              // Find "id" of generic item and change status to "0".
              $original_preference_id = $connection->select('emailservice_preferences_mapping', 'epm')
                ->fields('epm', ['id'])
                ->condition($item, $element_default_value)
                ->condition('status', 1)
                ->condition('entity_id', $node->id())
                ->execute()
                ->fetchAll();

              if (!empty($original_preference_id)) {
                $original_preference_id = end($original_preference_id);

                $connection->update('emailservice_preferences_mapping')
                  ->fields(['status' => 0])
                  ->condition('id', $original_preference_id->id)
                  ->execute();
              }
            }
          }

          if (!empty($field_value['label'])) {
            $subscriber_fields[$alias]['selection_list'][] = [
              'key' => $field_value['machine_name'],
              'value' => $field_value['label'],
            ];
          }
        }
      }

      // Only call Peytzmail API if there were actual changes (not just deletions).
      if (!empty($subscriber_fields) && $has_changes) {
        $connect = new PeytzmailConnect();
        $connect->setSubscriberFieldsData($subscriber_fields);
      }
    }
  }

}
