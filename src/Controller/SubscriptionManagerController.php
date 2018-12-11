<?php

namespace Drupal\emailservice\Controller;

use Drupal\Core\Controller\ControllerBase;
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
    $node = '';
    if (!isset($_GET['test'])) {
      $node = Node::load('2');
    }

    $ret = [
      '#theme' => 'subscription_manager',
      '#node' => $node,
      '#variables' => [
        'node' => $node
      ]
    ];

    $rendered = \Drupal::service('renderer')->render($ret);
    return Response::create($rendered);
  }

}
