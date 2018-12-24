<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class LMSClientConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lms_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'lms.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lms.config');
    $form['lms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('LMS Configurations'),
    ];
    $form['lms']['lms_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service URL'),
      '#default_value' => $config->get('lms_api_url'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('lms.config')
      ->set('lms_api_url', $form_state->getValue('lms_api_url'))
      ->save();
  }

}
