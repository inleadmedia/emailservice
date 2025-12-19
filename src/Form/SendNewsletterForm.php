<?php

namespace Drupal\emailservice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\emailservice\Controller\SubscriptionManagerController;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for manually sending newsletters.
 */
class SendNewsletterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'emailservice_send_newsletter_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node || $node->bundle() !== 'subscription') {
      $this->messenger()->addError($this->t('Invalid subscription node.'));
      return $form;
    }

    $form_state->set('node', $node);

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning">' .
      $this->t('This will send a newsletter for: <strong>@title</strong>', [
        '@title' => $node->getTitle(),
      ]) .
      '</div>',
    ];

    $materials_count = $node->get('field_materials_count')->value;
    if (empty($materials_count)) {
      $materials_count = SubscriptionManagerController::MATERIAL_COUNT_DEFAULT;
    }

    $form['items'] = [
      '#title' => $this->t('Subscription Details'),
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Node ID: @nid', ['@nid' => $node->id()]),
        $this->t('Owner: @owner', ['@owner' => $node->getOwner()->getDisplayName()]),
        $this->t('Mailing List ID: @list', ['@list' => $node->get('field_mailing_list_id')->getString()]),
        $this->t('Materials Count: @count', ['@count' => $node->get('field_materials_count')->value]),
      ],
    ];

    // Check if development mode is enabled.
    $dev_mode = $this->config('emailservice.config')->get('log_emails_to_watchdog');

    if ($dev_mode) {
      $form['dev_notice'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--status">' .
        $this->t('Development mode is enabled. Newsletter will be logged instead of sent.') .
        '</div>',
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $dev_mode ? $this->t('Test Newsletter (Log Only)') : $this->t('Send Newsletter'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('node');

    if (!$node) {
      $this->messenger()->addError($this->t('Unable to send newsletter. Node not found.'));
      return;
    }

    $nid = $node->id();

    // Redirect to the newsletter send route.
    $url = Url::fromRoute('emailservice.newsletter', ['nid' => $nid]);
    $form_state->setRedirectUrl($url);

    $this->messenger()->addStatus($this->t('Newsletter sending initiated for @title.', [
      '@title' => $node->getTitle(),
    ]));
  }

}
