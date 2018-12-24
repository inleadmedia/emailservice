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
    // This block to be removed after the submit is done.
/*    $this->config('lms.config')
      ->set('lms_api_url', 'https://v2.lms.inlead.ws/')
      ->set('lms_api_hash', 'bronbib')
      ->save();*/

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
    $form['lms']['lms_api_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agency hash'),
      '#default_value' => $config->get('lms_api_hash'),
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
      ->set('lms_api_hash', $form_state->getValue('lms_api_hash'))
      ->save();
  }

}
