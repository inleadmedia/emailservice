<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MaterialTypesMappingForm.
 */
class MaterialTypesMappingForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'emailservice.materialtypesmapping',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'material_types_mapping_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('emailservice.materialtypesmapping');
    $labels = $config->get('labels');

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('types_materials');

    $form['custom_labels'] = [
      '#type' => 'table',
      '#title' => t('Custom labels'),
      '#header' => ['Original', 'Custom'],
    ];

    foreach ($terms as $term) {
      $form['custom_labels'][$term->tid]['original'] = [
        '#type' => 'textfield',
        '#title' => t('Original label'),
        '#disabled' => TRUE,
        '#default_value' => $term->name
      ];

      $form['custom_labels'][$term->tid]['custom'] = [
        '#type' => 'textfield',
        '#title' => t('Custom label'),
        '#default_value' => $labels[$term->tid]['custom'] ?? $term->name,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    $this->config('emailservice.materialtypesmapping')
      ->set('labels', $values['custom_labels'])
      ->save();
  }

}
