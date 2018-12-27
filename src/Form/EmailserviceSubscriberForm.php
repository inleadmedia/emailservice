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
    $buttons_color = $node->get('field_buttons_color')->color;

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
      '#prefix' => '<div class="card">',
      '#suffix' => '</div>',
      '#type' => 'container',
    ];

    $form['preferences_wrapper']['title'] = [
      '#type' => 'container',
      '#markup' => '<div class="card-header">' . $this->t('Preferences') . '</div>',
    ];

    if (!empty($node)) {
      $types_vocabulary = 'types_materials';
      $taxonomy_types = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($types_vocabulary);

      $types_data = $this->prepareOptionsList($taxonomy_types);

      $form['preferences_wrapper']['types'] = [
        '#prefix' => '<div class="card-body"><div class="row"><div class="col-6">',
        '#suffix' => '</div>',
        '#type' => 'checkboxes',
        '#id' => 'preference_types',
        '#title' => $this->t('Types of materials'),
        '#description' => $this->t('Choose the material types you are interested in:'),
        '#description_display' => 'before',
        '#options' => $types_data,
        '#default_value' => !empty($subscriber_info['types']) ? $subscriber_info['types'] : [],
      ];

      $node_field_categories = $node->get('field_types_categories')->getValue();
      $category_options = [];

      foreach ($node_field_categories as $node_field_category) {
        $category_options[$node_field_category['machine_name']] = $node_field_category['label'];
      }

      $form['preferences_wrapper']['categories'] = [
        '#prefix' => '<div class="col-6">',
        '#suffix' => '</div></div>',
        '#type' => 'checkboxes',
        '#id' => 'preference_categories',
        '#title' => $this->t('Genre/Categories'),
        '#description' => $this->t('Choose the categories you are interested in:'),
        '#description_display' => 'before',
        '#options' => $category_options,
        '#default_value' => !empty($subscriber_info['categories']) ? $subscriber_info['categories'] : [],
      ];
    }
    $form['preferences_wrapper']['actions'] = [
      '#type' => 'actions',
      '#prefix' => '<div class="row mt-3"><div class="col-12">',
      '#suffix' => '</div></div></div>',
    ];

    $form['preferences_wrapper']['actions']['subscribe'] = [
      '#type' => 'submit',
      '#name' => 'subscribe',
      '#value' => $this->t('Subscribe'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
        'style' => ["background-color: $buttons_color; border-color: $buttons_color"],
      ],
    ];

    if (!empty($subscriber_info['email'])) {
      $form['preferences_wrapper']['actions']['subscribe']['#value'] = $this->t('Update my preferences');
      $form['preferences_wrapper']['actions']['subscribe']['#name'] = 'update';

      $form['preferences_wrapper']['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Unsubscribe all/Delete my profile'),
        '#name' => 'unsubscribe',
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
    $data = [];
    $message = '';

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

    if (empty($data['subscriber']['new_arrivals_categories'])) {
      $data['subscriber']['new_arrivals_categories'] = [''];
    }

    foreach ($raw_types as $key => $raw_type) {
      if (!empty($raw_type)) {
        $data['subscriber']['new_arrivals_types'][] = $raw_type;
      }
    }

    if (empty($data['subscriber']['new_arrivals_types'])) {
      $data['subscriber']['new_arrivals_types'] = [''];
    }

    $messenger = \Drupal::messenger();
    $type = $messenger::TYPE_STATUS;

    $subscriber_data['subscriber'] = $data;

    if ($op['#name'] == 'subscribe') {
      $subscribe = [
        'mailinglist_ids' => [$form_data['mailinglist_id']],
        'subscriber' => [
          'email' => $form_data['email_address'],
        ] + $data['subscriber'],
      ];

      $connect->signupMailinglist($subscribe);
      $message = $this->t('You were successfully subscribed to @mailinglist list!', ['@mailinglist' => $form_data['mailinglist_id']]);
    }
    elseif ($op['#name'] == 'update') {
      $result = $connect->updateSubscriber($subscriber_data);
      if (!empty($result['exception_code'])) {
        $message = $this->t("Something went wrong. Your subscription wasn't updated.");
        $type = $messenger::TYPE_WARNING;
      }
      else {
        $message = $this->t('Your subscription was successfully updated.');
      }
    }
    elseif ($op['#name'] == 'unsubscribe') {
      $result = $connect->unsubscribe($form_data['mailinglist_id'], $subscriber_data['id']);
      if ($result['result'] == 'ok') {
        $message = $this->t('You were successfully unsubscribed.');
      }
    }

    $messenger->addMessage($message, $type);
  }

  /**
   * Generate options list.
   *
   * @param array $terms
   *   Array of terms.
   *
   * @return array
   *   Options list.
   */
  public function prepareOptionsList(array $terms) {
    $result = [];
    foreach ($terms as $term) {
      $term_name = preg_replace('@[^a-z0-9-]+@', '-', strtolower($term->name));
      $result[$term_name] = $term->name;
    }
    return $result;
  }

}
