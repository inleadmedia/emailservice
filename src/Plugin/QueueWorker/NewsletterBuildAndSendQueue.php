<?php

namespace Drupal\emailservice\Plugin\QueueWorker;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\emailservice\Controller\SubscriptionManagerController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the newsletter_build_and_send queue worker.
 *
 * @QueueWorker(
 *   id = "newsletter_build_and_send",
 *   title = @Translation("Request LMS and send data to Peytzmail to initiate sendout."),
 *   cron = {"time" = 60}
 * )
 */
class NewsletterBuildAndSendQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param $plugin_id
   *   The plugin ID for the plugin instance.
   * @param $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Logger factory instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactory $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
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
    Timer::start('emailservice_' . $data->nid);
    $manager = new SubscriptionManagerController(\Drupal::service('emailservice.lms'));
    $manager->sendNewsletter($data->nid);
    Timer::stop('emailservice_' . $data->nid);
    $this->logger->get('emailservice.queue')
      ->notice('Processed nid: @nid in @time.', [
        '@nid' => $data->nid,
        '@time' => Timer::read('emailservice_' . $data->nid) . 'ms'
      ]);
  }

}
