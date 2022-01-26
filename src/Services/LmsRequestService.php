<?php

namespace Drupal\emailservice\Services;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emailservice\EmailserviceLogger;
use Drupal\emailservice\Models\Item;
use GuzzleHttp\Client;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LmsRequestService {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $config;

  /**
   * @var \Drupal\emailservice\EmailserviceLogger
   */
  private $emailserviceLogger;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  /**
   * @var string
   */
  private $lmsServiceURL;

  /**
   * @var string
   */
  private $coversServiceURL;

  public function __construct(ConfigFactory $config, EmailserviceLogger $emailserviceLogger, Connection $connection, Client $client) {
    $this->config = $config;
    $this->emailserviceLogger = $emailserviceLogger;
    $this->connection = $connection;
    $this->client = $client;

    $lmsConfig = $this->config->get('lms.config');
    $this->lmsServiceURL = $lmsConfig->get('lms_api_url');
    $this->coversServiceURL = $lmsConfig->get('lms_covers_api_url');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('emailservice.logger'),
      $container->get('database'),
      $container->get('http_client')
    );
  }

  /**
   * Form and send request to LMS.
   *
   * @param string $nid
   *   Node id of subscriber node.
   * @param string $alias
   *   Library user alias.
   * @param string $item_url
   *   Library material item URL.
   *
   * @return array
   */
  public function lmsRequest(string $nid, string $alias, $item_url) {
    $categories = $this->connection->select('emailservice_preferences_mapping', 'epm');
    $categories->join('taxonomy_term__field_types_cql_query', 'q', 'epm.material_tid=q.entity_id');
    $categories->fields('epm', [
        'cql_query',
        'label',
        'machine_name',
      ])
      ->fields('q', ['field_types_cql_query_value'])
      ->condition('epm.entity_id', $nid)
      ->condition('epm.preference_type', 'field_types_categories')
      ->condition('epm.status', 1)
      ->condition('epm.material_tid', 0, '!=')
      ->orderBy('epm.material_tid');

    $categories = $categories->execute()->fetchAll();

    $results = [];
    foreach ($categories as $category) {
      $query = "/search?query=(($category->field_types_cql_query_value) AND ($category->cql_query)) AND term.acSource=\"bibliotekskatalog\" AND holdingsitem.accessionDate>=\"NOW-7DAYS\"&step=200&_source=emailservice";
      $uri = $this->lmsServiceURL . $alias . $query;

      try {
        $request = $this->client->get($uri);
        $content = $request->getBody()->getContents();
      } catch (\Exception $e) {
        $this->emailserviceLogger->log(LogLevel::ERROR, $this->t("@message", ["@message" => $e->getMessage()]));
        $content = '';
      }

      $content = Json::decode($content);
      if (!empty($content['hitCount'])) {
        foreach ($content['objects'] as $k => $object) {
          $content['objects'][$k]['category'] = $category;
        }
        $results = array_merge($results, $content['objects']);
      }
    }

    return $this->buildItems($results, $item_url, $alias);
  }

  /**
   * @param array $content
   * @param $item_url
   * @param $alias
   *
   * @return array
   */
  private function buildItems(array $content, $item_url, $alias): array {
    $result_items = [];
    foreach ($content as $object) {
      $result_item = new Item();
      $result_item->setIdentifier($object['id']);
      $result_item->setTitle($object['title']);
      $result_item->setType($object['type']);
      $result_item->setUrl($item_url . $object['id']);
      $result_item->setSubject($object['category']->label);
      if (isset($object['author'])) {
        $result_item->setAuthor($object['author']);
      }
      if (isset($object['year'])) {
        $result_item->setDate($object['year']);
      }
      if (isset($object['cover'])) {
        $result_item->setCover($this->coversServiceURL . $alias . $object['cover'] . '?size=210&crop=210x315');
      }
      $result_item->setTypeKey($object['type']);
      $result_item->setSubjectKey($object['category']->machine_name);

      $result_items[] = $result_item;
    }

    return $result_items;
  }

}
