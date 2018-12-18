<?php

namespace Drupal\emailservice\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SubscriptionManagerController.
 */
class SubscriptionManagerController extends ControllerBase {

  /**
   * Content.
   */
  public function content() {

    $nids = NULL;
    $node = NULL;

    $params = \Drupal::request()->query->all();

    $connect = new PeytzmailConnect();

    $subscriber_data = [];
    if (!empty($params['email'])) {
      $subscriber_data = $connect->findSubscriber($params['email']);
    }

    $return = [
      '#theme' => 'subscription_manager',
    ];

    $valid_user = $this->checkMunicipalityParam($params['municipality']);

    if (!empty($valid_user)) {
      $nids = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('uid', $valid_user->id())
        ->execute();

      $node = Node::load(reset($nids));
      if (!empty($node)) {
        $mailing_list = $node->get('field_mailing_list_id')->value;
        $return['#subscriber_info'] = [
          'mailinglist_id' => $mailing_list,
        ];

        if (!empty($subscriber_data->total_records)) {
          foreach ($subscriber_data->subscribers as $subscriber) {
            if (in_array($mailing_list, $subscriber->mailinglist_ids)) {
              $return['#subscriber_info'] += [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'types' => $subscriber->extra_fields->new_arrivals_types,
                'categories' => $subscriber->extra_fields->new_arrivals_categories,
              ];
            }
          }
        }

        $form = \Drupal::formBuilder()
          ->getForm('\Drupal\emailservice\Form\EmailserviceSubscriberForm', $return['#subscriber_info'], $node);
        $form = \Drupal::service('renderer')->renderRoot($form);
        $return['#form'] = $form;

        $return += [
          '#node' => $node,
          '#params' => $params,
        ];
      }
    }
    $rendered = \Drupal::service('renderer')->render($return);
    return Response::create($rendered);
  }

  /**
   * Compare municipality param against user's alias field.
   */
  public function checkMunicipalityParam($param) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'field_alias' => $param,
      ]);

    return $users ? reset($users) : FALSE;
  }

}
