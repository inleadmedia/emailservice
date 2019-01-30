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
      '#title' => $this->t('First Name'),
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
      '#title' => $this->t('Last Name'),
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
      $types_vocabulary = 'types_materials';
      $taxonomy_types = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($types_vocabulary);

      $types_data = $this->prepareOptionsList($taxonomy_types);

      $form['preferences_wrapper']['types'] = [
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
    $email_address = $form_state->getValue('email_address');

    $connect = new PeytzmailConnect();

    $request = $connect->findSubscriber($email_address);

    if (empty($request['subscribers'])) {
      $message = $this->t('%email_address is an invalid email address.', ['%email_address' => $email_address]);
      $form_state->setErrorByName('email_address', $message);
    }
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
    $alias = $form_state->get('alias');

    $form_data = $form_state->getValues();
    $raw_categories = $form_data['categories'];
    $raw_types = $form_data['types'];

    $subs_categories = [];
    if (isset($subscriber_data['categories'])) {
      $subs_categories = $subscriber_data['categories'];
    }
    else {
      // If is not set categories array, make request to fetch from service.
      $subscriber_data_remote = $connect->findSubscriber($form_data['email_address']);
      foreach ($subscriber_data_remote['subscribers'] as $subscriber) {
        $subs_categories = $subscriber["extra_fields"]["new_arrivals_categories"];
      }
    }

    $data['subscriber']['new_arrivals_categories'] = $this->prepareCategories($alias, $raw_categories, $subs_categories);

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

      $connect->signupMailinglist($subscribe);
      $message = $this->t('You were successfully subscribed to @mailinglist list!', ['@mailinglist' => $form_data['mailinglist_id']]);
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
      $term_name = mb_strtolower($term->name, 'UTF-8');
      $term_name = preg_replace('@[^a-zæøå0-9-]+@', '-', strtolower($term_name));
      $result[$term_name] = $term->name;
    }
    return $result;
  }

}
