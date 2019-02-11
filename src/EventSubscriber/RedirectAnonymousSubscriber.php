<?php

namespace Drupal\emailservice\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class RedirectAnonymousSubscriber.
 */
class RedirectAnonymousSubscriber implements EventSubscriberInterface {

  private $account;

  /**
   * Constructs a new RedirectAnonymousSubscriber object.
   */
  public function __construct() {
    $this->account = \Drupal::currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkAuthStatus'];
    return $events;
  }

  /**
   * Check if user is authenticated.
   */
  public function checkAuthStatus(GetResponseEvent $event) {
    if ($this->account->isAnonymous() && \Drupal::routeMatch()->getRouteName() != 'user.login') {
      // Add logic to check other routes you want available to anonymous users,
      // otherwise, redirect to login page.
      $route_name = \Drupal::routeMatch()->getRouteName();
      if ($route_name == 'emailservice.subscription_manager' || $route_name == 'emailservice.check_subscriber') {
        return;
      }

      $response = new RedirectResponse('/user/login', 301);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

}
