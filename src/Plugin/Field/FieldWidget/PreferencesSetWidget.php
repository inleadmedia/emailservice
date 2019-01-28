<?php

namespace Drupal\emailservice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'preferences_set_widget' widget.
 *
 * @FieldWidget(
 *   id = "preferences_set_widget",
 *   label = @Translation("Preferences set widget"),
 *   field_types = {
 *     "preferences_set_field_type"
 *   }
 * )
 */
class PreferencesSetWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $item =& $items[$delta];

    $element = [
      '#type' => 'fieldset',
      '#title' => t('Preferences Set'),
      '#open' => TRUE,
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#default_value' => isset($item->label) ? $item->label : NULL,
    ];

    $element['machine_name'] = [
      '#type' => 'hidden',
      '#value' => isset($item->machine_name) ? $item->machine_name : 'stub',
    ];

    $element['cql_query'] = [
      '#type' => 'textfield',
      '#title' => t('CQL Query'),
      '#default_value' => isset($item->cql_query) ? $item->cql_query : NULL,
    ];

    $element['status'] = [
      '#type' => 'hidden',
      '#default_value' => isset($item->status) ? $item->status : 1,
    ];

    $options = [];
    $vid = 'types_materials';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $element['material_tid'] = [
      '#type' => 'select',
      '#title' => t('Related material type'),
      '#empty_option' => t('Choose type'),
      '#options' => $options,
      '#default_value' => isset($item->material_tid) ? $item->material_tid : '',
    ];

    return $element;
  }

}
