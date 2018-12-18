<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class EmailserviceConfigForm.
 */
class EmailserviceConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'emailservice.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'emailservice_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('emailservice.config');
    $form['peytzmail_integration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('PeytzMail Integration'),
    ];

    $form['peytzmail_integration']['peytzmail_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service URL'),
      '#default_value' => $config->get('peytzmail_api_url'),
      '#required' => TRUE,
    ];

    $form['peytzmail_integration']['peytzmail_api_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service API user'),
      '#default_value' => $config->get('peytzmail_api_user'),
      '#required' => TRUE,
    ];

    $form['peytzmail_integration']['peytzmail_api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service API auth token'),
      '#default_value' => $config->get('peytzmail_api_token'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('emailservice.config')
      ->set('peytzmail_api_url', $form_state->getValue('peytzmail_api_url'))
      ->set('peytzmail_api_user', $form_state->getValue('peytzmail_api_user'))
      ->set('peytzmail_api_token', $form_state->getValue('peytzmail_api_token'))
      ->save();
  }

}
