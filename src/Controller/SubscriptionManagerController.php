<?php

namespace Drupal\emailservice\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\emailservice\PeytzmailConnect;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\taxonomy\Entity\Term;

/**
 * Class SubscriptionManagerController.
 */
class SubscriptionManagerController extends ControllerBase {

  private $newsletter;

  /**
   * Form and send request to LMS.
   *
   * @param string $nid
   *   Node id of subscriber node.
   * @param string $alias
   *   Library user alias.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function lmsRequest(string $nid, string $alias) {
    $url = \Drupal::config('lms.config')->get('lms_api_url');

    $registered_materials = \Drupal::database()->select('emailservice_preferences_mapping', 'epm')
      ->fields('epm', ['material_tid'])
      ->condition('status', 1)
      ->execute()
      ->fetchAll();

    $materials = [];
    foreach ($registered_materials as $registered_material) {
      $materials[$registered_material->material_tid] = $registered_material->material_tid;
    }

    $categories = \Drupal::database()->select('emailservice_preferences_mapping', 'epm')
      ->fields('epm', ['cql_query', 'label', 'machine_name'])
      ->condition('epm.entity_id', $nid)
      ->condition('epm.preference_type', 'field_types_categories')
      ->condition('epm.status', 1)
      ->execute()
      ->fetchAll();

    $node = Node::load($nid);
    $item_url = $node->get('field_url_for_item_page')->value;

    $results = [];
    foreach ($materials as $material) {
      foreach ($categories as $category) {
        $term = Term::load($material);
        $type = $term->get('field_types_cql_query')->value;
        $query = "/search?query=(($type) AND ($category->cql_query)) AND term.acSource=\"bibliotekskatalog\" AND holdingsitem.accessionDate>=\"NOW-7DAYS\"&step=200";
        $uri = $url . $alias . $query;

        try {
          $content = \Drupal::service('emailservice.opensearch')->request($uri)->get('content');
        }
        catch (\Exception $e) {
          \Drupal::messenger()->addError($this->t("@message", ["@message" => $e->getMessage()]));
          \Drupal::logger('emailservice')->error($this->t("@message", ["@message" => $e->getMessage()]));
          $content = '';
        }

        $content = Json::decode($content);

        $content = array_map(function ($object) use ($alias, $category, $item_url) {
          $result_item = new \stdClass();

          $result_item->identifier = $object['id'];

          $result_item->title = $object['title'];
          $result_item->type = $object['type'];
          $result_item->url = $item_url . $object['id'];
          $result_item->subject = $category->label;

          if (!empty($object['description'])) {
            $result_item->description = $object['description'];
          }

          if (!empty($object['author'])) {
            $result_item->creator = $object['author'];
          }

          $result_item->date = $object['year'];

          if (!empty($object['cover'])) {
            $result_item->image = 'https://v2.cover.lms.inlead.ws/' . $alias . $object['cover'];
          }

          $result_item->type_key = $this->filterPreference($object['type']);
          $result_item->subject_key = $category->machine_name;

          return $result_item;
        }, $content['objects']);

        $results = array_merge($results, $content);
      }
    }

    $this->newsletter = $results;
  }

  /**
   * Prepare newsletter to be sent.
   */
  private function prepareNewsletter() {
    $this->newsletter = $this->removeDuplicates($this->newsletter);

    $week = new DrupalDateTime();
    $title = $this->t('New arrivals - Week @weekCount', ['@weekCount' => $week->format('W')]);

    $this->newsletter = $this->prepareFeed($title, $this->newsletter);

  }

  /**
   * Prepare preference param.
   *
   * @param string $preference
   *   Preference option.
   *
   * @return string
   *   Lowercase preference.
   */
  private function filterPreference($preference) {
    return strtolower($preference);
  }

  /**
   * Cleanup results.
   *
   * @param array $results
   *   Array of results.
   *
   * @return array
   *   Array without duplicated items.
   */
  private function removeDuplicates(array $results = NULL) {
    $results = array_map("unserialize", array_unique(array_map("serialize", $results)));

    return $results;
  }

  /**
   * Prepare feed.
   *
   * @param string $title
   *   Newsletter title.
   * @param array $data
   *   Newsletter data.
   *
   * @return \stdClass
   *   Feed object.
   */
  private function prepareFeed(string $title, array $data = NULL) {
    $object = new \stdClass();
    $object->name = 'pushed_arrivals';
    $object->data = $data;
    $feeds[0] = $object;

    $feed = new \stdClass();
    $feed->newsletter = new \stdClass();

    $feed->newsletter->title = $title;
    $feed->newsletter->feeds = $feeds;

    return $feed;
  }

  /**
   * Send generated newsletter.
   *
   * @param int $nid
   *   Identifier of sent node.
   *
   * @return array
   *   Renderable array.
   */
  public function sendNewsletter($nid) {
    $node = Node::load($nid);

    try {
      $owner = $node->getOwner();
      $alias = $owner->get('field_alias')->getString();

      $this->lmsRequest($nid, $alias);

      $this->prepareNewsletter();

      $mailinglist = $node->get('field_mailing_list_id')->getString();

      $connect = new PeytzmailConnect();
      $return = $connect->createAndSend($mailinglist, $this->newsletter);

      $content = Json::encode($return);
    }
    catch (\Exception $exception) {
      \Drupal::logger('emailservice')
        ->error($exception->getMessage());
      \Drupal::messenger()->addError($this->t('@exception_message', ['@exception_message' => $exception->getMessage()]));

      $content = [];
    }

    $renderer = [
      '#markup' => $content,
    ];

    return $renderer;
  }

  /**
   * Compare municipality param against user's alias field.
   *
   * @param string $param
   *   Check if such alias exists in database.
   *
   * @return object|bool
   *   Returns user object or false.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * Subscription page content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Renderable page.
   *
   * @throws \Exception
   */
  public function content() {
    $nids = NULL;
    $node = NULL;
    $valid_user = FALSE;

    $municipality = \Drupal::request()->get('municipality');
    $params = \Drupal::request()->query->all();
    $params['municipality'] = $municipality;
    $email_parameter = $params['email'];
    $cs_parameter = $params['cs'];

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

      $params['is_allowed'] = FALSE;

      if (isset($email_parameter) && isset($cs_parameter)) {
        $shared_secret_key = $node->get('field_shared_secret_key')->value;

        $prepare_string = $email_parameter . $shared_secret_key;

        $control_cs = hash('sha256', $prepare_string);

        if ($cs_parameter == $control_cs) {
          $params['is_allowed'] = TRUE;
        }
      }
      if (!empty($node)) {
        $mailing_list = $node->get('field_mailing_list_id')->value;
        $return['#subscriber_info'] = [
          'mailinglist_id' => $mailing_list,
        ];

        if (!empty($subscriber_data['total_records'])) {
          foreach ($subscriber_data['subscribers'] as $subscriber) {
            if (in_array($mailing_list, $subscriber['mailinglist_ids'])) {
              $return['#subscriber_info'] += [
                'id' => $subscriber['id'],
                'email' => $subscriber['email'],
                'first_name' => $subscriber['first_name'],
                'last_name' => $subscriber['last_name'],
                'types' => $subscriber['extra_fields']['new_arrivals_types'],
                'categories' => $subscriber['extra_fields']['new_arrivals_categories'],
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
   * Helper for generating machine name.
   *
   * @param object $node
   *   Node on which we are acting.
   * @param string $label
   *   The label of preference which have to be added.
   * @param int $tid
   *   Term id.
   *
   * @return string
   *   Generated machine name for category key.
   */
  public function generateMachineName($node, $label, $tid) {
    $user = $node->get('uid')->target_id;
    $loaded_user = User::load($user);

    $prefix = $loaded_user->get('field_alias')->value;

    $term = Term::load($tid);
    $material_type = self::lowerDanishTerms($term->getName());

    $machine_name = self::lowerDanishTerms($label);

    return $prefix . '_' . $material_type . '-' . $machine_name;
  }

  /**
   * Lowerize words.
   *
   * @param string $term
   *   String to be lowerized.
   *
   * @return string|string[]|null
   *   Lowerized string.
   */
  public static function lowerDanishTerms(string $term) {
    $term_name = mb_strtolower($term, 'UTF-8');
    return preg_replace('@[^a-zæøå0-9-]+@', '-', strtolower($term_name));
  }

  /**
   * Generate salt.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Return ajax response with replacement value for form field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generateSalt() {
    $response = new AjaxResponse();

    $values = array('type' => 'subscription');

    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->create($values);

    $form = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($node);
    $form = \Drupal::formBuilder()->getForm($form);

    $value = bin2hex(random_bytes(24));
    $element = $form["field_shared_secret_key"];
    $element['widget'][0]["value"]["#value"] = $value;

    $response->addCommand(new ReplaceCommand('#emailservice_salt_field', $element['widget'][0]['value']));

    return $response;
  }

}
