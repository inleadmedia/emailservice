<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\emailservice\Controller\SubscriptionManagerController;
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
      foreach ($fields as $alias => $field) {
        $field_values = $form_state->getValue($field);

        foreach ($field_values as $field_value) {

          if ($field_value['machine_name'] == 'stub' && !empty($field_value['label'])) {
            $machine_name = new SubscriptionManagerController();
            $machine_name = $machine_name->generateMachineName($node, $field_value['label'], $field_value['material_tid']);
            $field_value['machine_name'] = $machine_name;
          }

          if (empty($field_value['label']) && !empty($field_value['machine_name'])) {
            $connection->update('emailservice_preferences_mapping')
              ->fields(['status' => 0])
              ->condition('machine_name', $field_value['machine_name'])
              ->execute();
          }

          if (!empty($field_value['label'])) {
            $subscriber_fields[$alias]['selection_list'][] = [
              'key' => $field_value['machine_name'],
              'value' => $field_value['label'],
            ];
          }
        }
      }

      if (!empty($subscriber_fields)) {
        $connect = new PeytzmailConnect();
        $connect->setSubscriberFieldsData($subscriber_fields);
      }
    }
  }

}
