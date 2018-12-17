<?php

namespace Drupal\emailservice;

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
   * Get info about subscriber.
   *
   * @param string $email
   *   Email of searched user.
   *
   * @return object|array
   */
  public function findSubscriber($email) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, null],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json']
    ];

    $uri = '/api/v1/subscribers/search.json?criteria[email]=' . $email;
    $result = '';

    try {
      $response = $this->request->get($uri, $options);
      $result = json_decode($response->getBody()->getContents());
    }
    catch (ClientException $e) {
      $response_body = json_decode($e->getResponse()->getBody()->getContents());
      if ($e->getCode() == '404' && $response_body->error == self::PEYTZMAIL_NOT_FOUND) {
        $result = ['No such user'];
      }
    }

    return $result;
  }

  /**
   * @param $data
   *
   * @return array
   */
  public function signupMailinlist($data) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, null],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
      'body' => json_encode(['subscribe' => $data]),
    ];

    $uri = '/api/v1/mailinglists/subscribe.json';

    $response = $this->request->post($uri, $options);
    $result = json_decode($response->getBody()->getContents());

    $return_data = [
      'code' => $response->getStatusCode(),
      'result' => $result,
    ];

    $a = 1;
    return $return_data;
  }


  /**
   * @param $subscriber_data
   *
   * @return array
   */
  public function updateSubscriber($subscriber_data) {

    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, null],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
      'body' => json_encode($subscriber_data['subscriber'])
    ];

    $uri = '/api/v1/subscribers/' . $subscriber_data['id'];

    $response = $this->request->put($uri, $options);
    $result = json_decode($response->getBody()->getContents());

    $return_data = [
      'code' => $response->getStatusCode(),
      'result' => $result,
    ];

    $a = 1;
    return $return_data;
  }

  /**
   * @param $data
   */
  public function setSubscriberFieldsData($data) {
    $api_token = $this->config->get('peytzmail_api_token');

    foreach ($data as $field => $field_data) {

      $field_request_data = $this->getSubscriberFieldsData($field);
      $original_set = $field_request_data->subscriber_field->selection_list;
      $new_set = $field_data['selection_list'];

      $updated_set = array_merge($original_set, $new_set);
      $field_request_data->subscriber_field->selection_list = $updated_set;

      $set_for_send = json_encode($field_request_data);

      $options = [
        'auth' => [$api_token, null],
        'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
        'body' => $set_for_send,
      ];

      $uri = '/api/v1/subscriber_fields/' . $field;

      $request  = $this->request->put($uri, $options);

      return $request;
    }
  }

  /**
   * @param $field
   *
   * @return mixed
   */
  public function getSubscriberFieldsData($field) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, null],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
    ];

    $uri = '/api/v1/subscriber_fields/' . $field;

    $request = $this->request->get($uri, $options);
    $result = json_decode($request->getBody()->getContents());
    return $result;

  }
}
