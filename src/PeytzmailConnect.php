<?php

namespace Drupal\emailservice;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Class PeytzmailConnect.
 */
class PeytzmailConnect {

  const PEYTZMAIL_NOT_FOUND = 'not_found';
  public $request;
  public $config;

  /**
   * Constructs a new PeytzmailConnect object.
   */
  public function __construct() {
    $config = \Drupal::config('emailservice.config');
    $this->config = $config;
    $api_url = $config->get('peytzmail_api_url');
    $this->request = new Client(['base_uri' => $api_url]);
  }

  /**
   * Find subscriber.
   *
   * @param string $email
   *   Email of searched user.
   *
   * @return object|array
   *   Response object from service or array in case when subscriber is missing.
   */
  public function findSubscriber($email) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
    ];

    $uri = '/api/v1/subscribers/search.json?criteria[email]=' . $email;
    $result = '';

    try {
      $response = $this->request->get($uri, $options);
      $result = JSON::decode($response->getBody()->getContents());
    }
    catch (ClientException $e) {
      $response_body = JSON::decode($e->getResponse()->getBody()->getContents());
      if ($e->getCode() == '404' && $response_body->error == self::PEYTZMAIL_NOT_FOUND) {
        $result = ['No such user'];
      }
    }

    return $result;
  }

  /**
   * Signup to mailinglist.
   *
   * @param array $data
   *   Subscriber data.
   *
   * @return array
   *   Response from service.
   */
  public function signupMailinglist(array $data) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
      'body' => json_encode(['subscribe' => $data]),
    ];

    $uri = '/api/v1/mailinglists/subscribe.json';

    try {
      $response = $this->request->post($uri, $options);
      $result = JSON::decode($response->getBody()->getContents());
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }

    $return_data = [
      'code' => $response->getStatusCode(),
      'result' => $result,
    ];

    return $return_data;
  }

  /**
   * Update subscription.
   *
   * @param array $subscriber_data
   *   Data to be updated.
   *
   * @return array
   *   Response from service.
   */
  public function updateSubscriber(array $subscriber_data) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
      'body' => json_encode($subscriber_data['subscriber']),
    ];

    $uri = '/api/v1/subscribers/' . $subscriber_data['id'];

    try {
      $response = $this->request->put($uri, $options);
      $result = JSON::decode($response->getBody()->getContents());
    }
    catch (ClientException $exception) {
      $result['exception_code'] = $exception->getCode();
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }

    return $result;
  }

  /**
   * Setting the subscriber field data and push to Peytzmail.
   *
   * @param array $data
   *   Array containing field values.
   */
  public function setSubscriberFieldsData(array $data) {
    $api_token = $this->config->get('peytzmail_api_token');

    foreach ($data as $field => $field_data) {
      $field_request_data = $this->getSubscriberFieldsData($field);
      $original_set = $field_request_data->subscriber_field->selection_list;
      $new_set = $field_data['selection_list'];

      $updated_set = array_merge($original_set, $new_set);
      $field_request_data->subscriber_field->selection_list = $updated_set;

      $set_for_send = json_encode($field_request_data);

      $options = [
        'auth' => [$api_token, NULL],
        'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
        'body' => $set_for_send,
      ];

      $uri = '/api/v1/subscriber_fields/' . $field;

      try {
        $this->request->put($uri, $options);
      }
      catch (ClientException $exception) {
        \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
      }
    }
  }

  /**
   * Get Subscriber Fields data.
   *
   * @param string $field
   *   Subscriber field.
   *
   * @return mixed
   *   Requested subscriber field data.
   */
  public function getSubscriberFieldsData($field) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
    ];

    $uri = '/api/v1/subscriber_fields/' . $field;

    try {
      $request = $this->request->get($uri, $options);
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }

    $result = JSON::decode($request->getBody()->getContents());
    return $result;
  }

  /**
   * Create newsletter and initialize send-out.
   *
   * @param string $mailinglist
   *   Mailing List param.
   * @param object $feed
   *   Feed to be sent.
   *
   * @return mixed
   *   Response from service.
   */
  public function createAndSend(string $mailinglist, $feed) {
    $api_token = $this->config->get('peytzmail_api_token');
    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
      'body' => json_encode($feed),
    ];

    $uri = '/api/v1/mailinglists/' . $mailinglist . '/newsletters/create_and_send.json';

    try {
      $response = $this->request->post($uri, $options);
      return $response->getBody()->getContents();
    }
    catch (ClientException $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }
  }

  /**
   * Unsubscribe subscriber from mailinglist.
   *
   * @param string $mailinglist_id
   *   Mailing list ID.
   * @param string $subscriber_id
   *   Subscriber ID.
   *
   * @return array
   *   Result form service.
   */
  public function unsubscribe($mailinglist_id, $subscriber_id) {
    $api_token = $this->config->get('peytzmail_api_token');
    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
    ];

    $uri = '/api/v1/mailinglists/' . $mailinglist_id . '/subscribers/' . $subscriber_id;

    try {
      $response = $this->request->delete($uri, $options);
      return JSON::decode($response->getBody()->getContents());
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }
  }

}
