<?php

namespace Drupal\emailservice\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\emailservice\Controller\SubscriptionManagerController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the newsletter_build_and_send queueworker.
 *
 * @QueueWorker (
 *   id = "newsletter_build_and_send",
 *   title = @Translation("Request LMS and send data to Peytzmail to initiate sendout."),
 *   cron = {"time" = 360}
 * )
 */
class NewsletterBuildAndSendQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected $logger;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactory $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $s = microtime(TRUE);
    $manager = new SubscriptionManagerController(\Drupal::service('emailservice.lms'));
    $manager->sendNewsletter($data->nid);
    $e = microtime(TRUE);
    $this->logger->get('emailservice.queue')
      ->warning('Processed nid: @nid in @microtime', ['@nid' => $data->nid, '@microtime' => $e-$s]);
  }

}
