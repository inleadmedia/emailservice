<?php

namespace Drupal\emailservice;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\user\Entity\User;

/**
 * Class EmailserviceLogger
 *
 * @package Drupal\emailservice
 */
class EmailserviceLogger {
  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   */
  public function __construct(LoggerChannelFactory $loggerFactory) {
    $this->loggerFactory = $loggerFactory->get('emailservice');
  }

  /**
   * Extended log function.
   *
   * @param string $level
   * @param string $message
   * @param array $context
   */
  public function log($level, $message, array $context = []) {
    if ($context['uid']) {
      $this->loggerFactory->setCurrentUser(User::load($context['uid']));
    }
    $this->loggerFactory->log($level, $message, $context);
  }

}
