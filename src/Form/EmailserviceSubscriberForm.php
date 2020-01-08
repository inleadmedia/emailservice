<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

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

    $form['#theme'] = 'emailservice_subscription_form';

    $form['mailinglist_id'] = [
      '#type' => 'hidden',
      '#value' => $subscriber_info['mailinglist_id'],
    ];

    $form['email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => !empty($subscriber_info['email']) ? $subscriber_info['email'] : '',
      '#required' => TRUE,
      '#maxlength' => 254,
      '#attributes' => [
        'placeholder' => $this->t('Type in the email address you wish to use.'),
        'class' => ['form-control'],
        'autocomplete' => "off",
      ],
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => !empty($subscriber_info['first_name']) ? $subscriber_info['first_name'] : '',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Type in the first name.'),
        'class' => ['form-control'],
        'autocomplete' => "off",
      ],
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname'),
      '#default_value' => !empty($subscriber_info['last_name']) ? $subscriber_info['last_name'] : '',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Type in the last name.'),
        'class' => ['form-control'],
        'autocomplete' => "off",
      ],
    ];
    $form['preferences_wrapper'] = [
      '#type' => 'container',
    ];

    if (!empty($node)) {
      $node_field_categories = $node->get('field_types_categories')->getValue();

      // Extract material types groups.
      $grouped_categories = $this->groupCategories($node_field_categories);

      foreach ($grouped_categories as $tid => $grouped_category) {
        $term = Term::load($tid);
        if (empty($term)) {
          break;
        }
        $type_name = $term->getName();

        $category_options = [];
        foreach ($grouped_category as $item) {
          $category_options[$item['machine_name']] = $item['label'];
        }

        $form['preferences_wrapper']['categories']['category_' . $tid] = [
          '#type' => 'checkboxes',
          '#id' => 'preference_categories',
          '#title' => $type_name,
          '#options' => $category_options,
          '#default_value' => !empty($subscriber_info['categories']) ? $subscriber_info['categories'] : [],
        ];
      }
    }

    $form['preferences_wrapper']['actions'] = [
      '#type' => 'actions',
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
    $form_state->set('alias', $node->getOwner()->get('field_alias')->value);

    return $form;
  }

  /**
   * Form validator.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Drupal validation of email address.
    if (!$form_state->getValue('email_address') || !filter_var($form_state->getValue('email_address'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email_address', $this->t('Invalid email address.'));
    }

    // Validate preferences.
    $values = $form_state->getValues();
    $categories = [];
    foreach ($values as $key => $value) {
      if (strpos($key, 'category') !== FALSE) {
        foreach ($value as $cat => $item) {
          $categories[$cat] = $item;
        }
      }
    }
    $check_empty = array_filter($categories);

    if (empty($check_empty)) {
      $form_state->setError($form['preferences_wrapper'], $this->t('You have to pick at least one interest in order to subscribe.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $op = $form_state->getTriggeringElement();
    $data = [];
    $message = '';
    $raw_types = [];
    $raw_categories = [];

    $connect = new PeytzmailConnect();
    $subscriber_data = $form_state->get('subscriber_info');
    $alias = $form_state->get('alias');

    $form_data = $form_state->getValues();

    foreach ($form_data as $key => $form_datum) {
      if (strpos($key, 'category') !== FALSE) {
        foreach ($form_datum as $i => $item) {
          $raw_categories[$i] = $item;

          if (!empty($item)) {
            $first_level = explode('_', $item);
            $second_level = explode('-', $first_level[1]);

            $raw_types[$second_level[0]] = $second_level[0];
          }
        }
      }
    }

    $subs_categories = [];
    if (isset($subscriber_data['categories'])) {
      $subs_categories = $subscriber_data['categories'];
    }
    else {
      // If is not set categories array, make request to fetch from service.
      $subscriber_data_remote = $connect->findSubscriber($form_data['email_address']);
      foreach ($subscriber_data_remote['subscribers'] as $subscriber) {
        if (!empty($subscriber['extra_fields'])) {
          $subs_categories = $subscriber["extra_fields"]["new_arrivals_categories"] ?? [];
        }
      }
    }

    $data['subscriber']['new_arrivals_categories'] = $this->prepareCategories($alias, $raw_categories, $subs_categories);

    if (isset($subscriber_data['types'])) {
      foreach ($subscriber_data['types'] as $existing_type) {
        $raw_types[$existing_type] = $existing_type;
      }
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
          'first_name' => $form_data['first_name'],
          'last_name' => $form_data['last_name'],
        ] + $data['subscriber'],
      ];

      $signup = $connect->signupMailinglist($subscribe);

      if (!empty($signup['exception_message'])) {
        $message = $this->t("Your subscription wasn't accepted. Reason: @subscribe_exception_message", ['@subscribe_exception_message' => $signup['exception_message']]);
        $type = $messenger::TYPE_ERROR;
      }
      else {
        $message = $this->t('You were successfully subscribed to @mailinglist list!', ['@mailinglist' => $form_data['mailinglist_id']]);
      }
    }
    elseif ($op['#name'] == 'update') {
      $subscriber_data['subscriber']['subscriber']['email'] = $form_data['email_address'];
      $subscriber_data['subscriber']['subscriber']['first_name'] = $form_data['first_name'];
      $subscriber_data['subscriber']['subscriber']['last_name'] = $form_data['last_name'];
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
      $result = $connect->unsubscribe($form_data['mailinglist_id'], $subscriber_data['id'], $alias);
      if ($result['result'] == 'ok') {

        // Delete preference categories on unsubscribe.
        $new_subscriber_data = $connect->getSubscriber($subscriber_data['id']);
        $categories = $new_subscriber_data['subscriber']['extra_fields']['new_arrivals_categories'];
        foreach ($categories as $key => $category) {
          if (strpos($category, $alias) !== FALSE) {
            unset($categories[$key]);
          }
        }

        if (empty($categories)) {
          $categories = [''];
        }

        $new_subscriber_data['subscriber']['extra_fields']['new_arrivals_categories'] = $categories;
        $subscriber_data_to_send['subscriber']['subscriber'] = $new_subscriber_data['subscriber']['extra_fields'];
        $subscriber_data_to_send['id'] = $subscriber_data['id'];
        $connect->updateSubscriber($subscriber_data_to_send);

        $message = $this->t('You were successfully unsubscribed.');
      }
    }

    $messenger->addMessage($message, $type);
  }

  /**
   * Prepare categories to be pushed.
   *
   * @param string $alias
   *   Current node owner's alias.
   * @param array $raw_categories
   *   Current form categories.
   * @param array $remote_categories
   *   Categories already present in subscriber's profile.
   *
   * @return array
   *   List of categories prepared to be sent.
   */
  public function prepareCategories($alias, array $raw_categories, array $remote_categories = []) {
    $selected_categories = [];
    $other_nodes_categories = [];

    if (!empty($remote_categories)) {
      // Get current subscription categories from user data.
      foreach ($remote_categories as $remote_category) {
        if (strpos($remote_category, $alias) !== FALSE) {
          $current_node_categories[] = $remote_category;
        }
        else {
          $other_nodes_categories[] = $remote_category;
        }
      }
    }

    // Filter checked categories.
    foreach ($raw_categories as $key => $raw_category) {
      if (!empty($raw_category)) {
        $selected_categories[] = $raw_category;
      }
    }

    $categories = array_merge_recursive($other_nodes_categories, $selected_categories);

    return $categories;
  }

  /**
   * Group categories by type.
   *
   * @param array $items
   *   Array of categories.
   *
   * @return array
   *   Grouped array.
   */
  public function groupCategories(array $items) {
    $templevel = 0;
    $newkey = 0;
    $grouparr = [];

    foreach ($items as $key => $val) {
      if ($templevel == $val['material_tid']) {
        $grouparr[$templevel][$newkey] = $val;
      }
      else {
        $grouparr[$val['material_tid']][$newkey] = $val;
      }
      $newkey++;
    }
    return $grouparr;
  }

}
