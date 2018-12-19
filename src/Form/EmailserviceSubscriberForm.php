<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\Entity\Node;

/**
 * Class EmailserviceSubscriberForm.
 */
class EmailserviceSubscriberForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'emailservice_subscriber_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $subscriber_info = NULL, Node $node = NULL) {
    $form['mailinglist_id'] = [
      '#type' => 'hidden',
      '#value' => $subscriber_info['mailinglist_id'],
    ];

    $form['email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => !empty($subscriber_info['email']) ? $subscriber_info['email'] : '',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Type in the email address you wish to use.'),
        'class' => ['form-control'],
        'autocomplete' => "off",
      ],
    ];

    $form['preferences_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preferences'),
      '#attributes' => [
        'class' => ['mt-3'],
      ],
    ];

    if (!empty($node)) {
      $node_field_types = $node->get('field_types_materials')->getValue();
      $type_options = [];
      foreach ($node_field_types as $node_field_type) {
        $type_options[$node_field_type['machine_name']] = $node_field_type['label'];
      }

      $form['preferences_wrapper']['types'] = [
        '#prefix' => '<div class="row mt-3 mb-3"><div class="col">',
        '#suffix' => '</div>',
        '#type' => 'checkboxes',
        '#id' => 'preference_types',
        '#title' => $this->t('Types of materials'),
        '#description' => $this->t('Choose the material types you are interested in:'),
        '#description_display' => 'before',
        '#options' => $type_options,
        '#default_value' => !empty($subscriber_info['types']) ? $subscriber_info['types'] : [],
      ];

      $node_field_categories = $node->get('field_types_categories')->getValue();
      $category_options = [];

      foreach ($node_field_categories as $node_field_category) {
        $category_options[$node_field_category['machine_name']] = $node_field_category['label'];
      }

      $form['preferences_wrapper']['categories'] = [
        '#prefix' => '<div class="col">',
        '#suffix' => '</div></div',
        '#type' => 'checkboxes',
        '#id' => 'preference_categories',
        '#title' => $this->t('Genre/Categories'),
        '#description' => $this->t('Choose the categories you are interested in:'),
        '#description_display' => 'before',
        '#options' => $category_options,
        '#default_value' => !empty($subscriber_info['categories']) ? $subscriber_info['categories'] : [],
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['subscribe'] = [
      '#type' => 'submit',
      '#name' => 'subscribe',
      '#value' => $this->t('Subscribe'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
    ];

    if (!empty($subscriber_info['email'])) {
      $form['actions']['subscribe']['#value'] = $this->t('Update my preferences');
      $form['actions']['subscribe']['#name'] = 'update';

      $form['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Unsubscribe all/Delete my profile'),
        '#attributes' => [
          'class' => ['btn', 'btn-danger'],
        ],
      ];
    }

    $form['#form'] = $form;

    $form_state->set('subscriber_info', $subscriber_info);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $op = $form_state->getTriggeringElement();

    $connect = new PeytzmailConnect();
    $subscriber_data = $form_state->get('subscriber_info');

    $form_data = $form_state->getValues();
    $raw_categories = $form_data['categories'];
    $raw_types = $form_data['types'];

    foreach ($raw_categories as $key => $raw_category) {
      if (!empty($raw_category)) {
        $data['subscriber']['new_arrivals_categories'][] = $raw_category;
      }
    }

    foreach ($raw_types as $key => $raw_type) {
      if (!empty($raw_type)) {
        $data['subscriber']['new_arrivals_types'][] = $raw_type;
      }
    }

    $subscriber_data['subscriber'] = $data;

    if ($op['#name'] == 'subscribe') {
      $subscribe = [
        'mailinglist_ids' => [$form_data['mailinglist_id']],
        'subscriber' => [
          'email' => $form_data['email_address'],
        ] + $data['subscriber'],
      ];

      $connect->signupMailinlist($subscribe);
      $message = $this->t('You were successfuly subscribed to @mailinglist list!', ['@mailinglist' => $form_data['mailinglist_id']]);
    }
    elseif ($op['#name'] == 'update') {
      $connect->updateSubscriber($subscriber_data);
      $message = $this->t('Your subscription was successfully updated.');
    }

    $messenger = \Drupal::messenger();
    $messenger->addStatus($message);
  }

}
