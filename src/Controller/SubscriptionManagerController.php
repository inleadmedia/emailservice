<?php

namespace Drupal\emailservice\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\NodeInterface;
use \Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class SubscriptionManagerController.
 */
class SubscriptionManagerController extends ControllerBase {

  private $newsletter;

  private function lmsRequest(string $nid, string $alias) {
    $url = \Drupal::config('lms.config')->get('lms_api_url');
    $hash = \Drupal::config('lms.config')->get('lms_api_hash');

    $materials = \Drupal::database()->select('emailservice_preferences_mapping', 'epm')
      ->fields('epm', ['cql_query', 'label'])
      ->condition('epm.entity_id', $nid)
      ->condition('epm.preference_type', 'field_types_materials')
      ->condition('epm.status', 1)
      ->execute()
      ->fetchAll();

    $categories = \Drupal::database()->select('emailservice_preferences_mapping', 'epm')
      ->fields('epm', ['cql_query', 'label'])
      ->condition('epm.entity_id', $nid)
      ->condition('epm.preference_type', 'field_types_categories')
      ->condition('epm.status', 1)
      ->execute()
      ->fetchAll();

    $results = [];
    foreach ($materials as $material) {
      foreach ($categories as $category) {
        $query = "/search?query=(($material->cql_query) AND ($category->cql_query)) AND term.acSource=\"bibliotekskatalog\" AND holdingsitem.accessionDate>=\"NOW-7DAYS\"&step=200";
        $uri = $url . $alias . $query;
        $content = \Drupal::service('emailservice.opensearch')->request($uri)->get('content');

        $content = json_decode($content);

        $content = array_map(function ($object) use ($alias, $material) {
          $object->identifier = $object->id;
          unset($object->id);

          $object->creator = $object->author;
          unset($object->author);

          $object->date = $object->year;
          unset($object->year);

          unset($object->faustNumber);
          unset($object->description);

          $object->image = 'https://v2.cover.lms.inlead.ws/' . $alias . $object->cover;
          unset($object->cover);

          $object->type_key = $this->filterPreference($object->type);
          $object->subject_key = $alias . '_'. $this->filterPreference($material->label);

          return $object;
        }, $content->objects);

        $results = array_merge($results, $content);
      }
    }

    $this->newsletter = $results;
  }

  private function prepareNewsletter() {
    $this->newsletter = $this->removeDuplicates($this->newsletter);

    $week = new DrupalDateTime();
    $title = $this->t('New arriwals - Week @weekCount', ['@weekCount' => $week->format('W')]);

    $this->newsletter = $this->prepareFeed($title, $this->newsletter);

  }

  private function filterPreference($preference) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', strtolower($preference));
  }

  private function removeDuplicates($results) {
    return $results;
  }

  private function prepareFeed($title, $data) {
    $feeds = [
      'name' => 'new_arrivals',
      'data' => $data,
    ];
    $feed = new \stdClass();
    $feed->newsletter->title = $title;
    $feed->newsletter->feeds = $feeds;

    return $feed;
  }

  public function sendNewsletter($nid) {
    if (empty($nid)) {
      return FALSE;
    }

    $node =Node::load($nid);
    
    $owner = $node->getOwner();
    $alias = $owner->get('field_alias')->getString();

    $this->lmsRequest($nid, $alias);

    $this->prepareNewsletter();

    $connect = new PeytzmailConnect();
    $return = $connect->createAndSend($this->newsletter);
    echo json_encode($this->newsletter);
    $rendered = \Drupal::service('renderer')->render($return);
    return Response::create($rendered);
  }

  /**
   * Compare municipality param against user's alias field.
   */
  private function checkMunicipalityParam($param) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'field_alias' => $param,
      ]);

    return $users ? reset($users) : FALSE;
  }

  /**
   * Content.
   */
  public function content() {

    $nids = NULL;
    $node = NULL;
    $valid_user = FALSE;

    $params = \Drupal::request()->query->all();

    $connect = new PeytzmailConnect();

    $subscriber_data = [];
    if (!empty($params['email'])) {
      $subscriber_data = $connect->findSubscriber($params['email']);
    }

    $return = [
      '#theme' => 'subscription_manager',
    ];

    if (!empty($params['municipality'])) {
      $valid_user = $this->checkMunicipalityParam($params['municipality']);
    }
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

}
