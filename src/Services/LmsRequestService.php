<?php

namespace Drupal\emailservice\Services;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emailservice\EmailserviceLogger;
use Drupal\emailservice\Models\Item;
use GuzzleHttp\Client;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class LmsRequestService {

  use StringTranslationTrait;


  /**
   * Default material count.
   */
  const MATERIAL_COUNT_DEFAULT = 9;

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

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructor for LmsRequestService.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Config factory service.
   * @param \Drupal\emailservice\EmailserviceLogger $emailserviceLogger
   *   Emailservice logger service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection service.
   * @param \GuzzleHttp\Client $client
   *   HTTP client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(ConfigFactory $config, EmailserviceLogger $emailserviceLogger, Connection $connection, Client $client, EntityTypeManagerInterface $entityTypeManager) {
    $this->config = $config;
    $this->emailserviceLogger = $emailserviceLogger;
    $this->connection = $connection;
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;

    $lmsConfig = $this->config->get('lms.config');
    $this->lmsServiceURL = $lmsConfig->get('lms_api_url');
    $this->coversServiceURL = $lmsConfig->get('lms_covers_api_url');
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('emailservice.logger'),
      $container->get('database'),
      $container->get('http_client'),
      $container->get('entity_type.manager')
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
   * @param int $limit
   *   Limit of materials to return.
   *
   * @return array
   *   Array of results.
   */
  public function lmsRequest(string $nid, string $alias, $item_url, $limit = self::MATERIAL_COUNT_DEFAULT) {
    $pattern = "/{$alias}\B/";
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

    // @todo Find out why query selecting by current entity_id includes results with machine_names of other clients.
    // For example: Expecting only - bornbib-ung_XYZ, having: bornbib_XYZ also,
    // result which relates other entity, but in db it's assigned to current node.
    // Filter unrelated results from categories array.
    $filteredCategories = [];
    foreach ($categories as $categoryData) {
      if (preg_match($pattern, $categoryData->machine_name) === 1) {
        $filteredCategories[] = $categoryData;
      }
    }

    $results = [];
    foreach ($filteredCategories as $category) {
      $query = "/search?query=(($category->field_types_cql_query_value) AND ($category->cql_query)) AND term.acSource=\"bibliotekskatalog\" AND holdingsitem.accessionDate>=\"NOW-7DAYS\"&step=" . $limit . "&_source=emailservice";
      $uri = $this->lmsServiceURL . $alias . $query;

      try {
        $request = $this->client->get($uri);
        $content = $request->getBody()->getContents();
      }
      catch (\Exception $e) {
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
      elseif (isset($object['faustNumber'])) {
        // Try to construct cover URL from faustNumber when cover field is missing.
        $potentialCoverUrl = rtrim($this->coversServiceURL, '/') . '/' . $alias . '/covers/' . $object['faustNumber'] . '?size=210&crop=210x315';

        // Check if cover exists with HEAD request.
        try {
          $headRequest = $this->client->head($potentialCoverUrl, ['timeout' => 5]);

          if ($headRequest->getStatusCode() === 200) {
            $result_item->setCover($potentialCoverUrl);
          }
        }
        catch (\Exception $e) {
          // Cover doesn't exist or request failed, continue without cover.
          $this->emailserviceLogger->log(LogLevel::DEBUG, $this->t("Cover not found for faustNumber @faust: @message", [
            "@faust" => $object['faustNumber'],
            "@message" => $e->getMessage(),
          ]));
        }
      }
      $result_item->setTypeKey($object['type']);
      $result_item->setSubjectKey($object['category']->machine_name);

      $result_items[] = $result_item;
    }

    return $result_items;
  }

}
