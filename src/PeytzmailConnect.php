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

    $uri = '/api/v1/subscribers/search.json?criteria[email]=' . urlencode($email);
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
   * Get concrete subscriber info.
   *
   * @param string $subscriber_id
   *   Subscriber id.
   *
   * @return array
   *   Array with subscriber info.
   */
  public function getSubscriber($subscriber_id) {
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
    ];

    $uri = '/api/v1/subscribers/' . $subscriber_id;

    try {
      $request = $this->request->get($uri, $options);
      return JSON::decode($request->getBody()->getContents());
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
    }
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
    // Check if we should log emails to watchdog instead of sending them.
    if ($this->config->get('log_emails_to_watchdog')) {
      return $this->logSignupToWatchdog($data);
    }

    $return_data = [];
    $api_token = $this->config->get('peytzmail_api_token');

    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => [
        'content-type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'body' => json_encode(['subscribe' => $data]),
    ];

    $uri = '/api/v1/mailinglists/subscribe.json';

    try {
      $response = $this->request->post($uri, $options);
      $result = JSON::decode($response->getBody()->getContents());
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')->error($exception->getMessage() . ': ' . $exception->getCode());
      $exception_message = JSON::decode($exception->getResponse()->getBody()->getContents());
      $reason = explode(':', $exception_message['message']);

      $return_data = [
        'exception_message' => trim($reason[1]),
      ];
    }

    if (!empty($result)) {
      $return_data = [
        'code' => $response->getStatusCode(),
        'result' => $result,
      ];
    }

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
      $original_set = $field_request_data['subscriber_field']['selection_list'];
      $new_set = $field_data['selection_list'];

      $updated_set = array_merge($original_set, $new_set);
      $field_request_data['subscriber_field']['selection_list'] = $updated_set;

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
    // Check if we should log emails to watchdog instead of sending them.
    if ($this->config->get('log_emails_to_watchdog')) {
      return $this->logEmailToWatchdog($mailinglist, $feed);
    }

    $api_token = $this->config->get('peytzmail_api_token');
    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => [
        'content-type' => 'application/json',
        'Accept' => 'application/json',
      ],
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
   * @param string $alias
   *   Subscriber node alias.
   *
   * @return array
   *   Result form service.
   */
  public function unsubscribe($mailinglist_id, $subscriber_id, $alias) {
    // Check if we should log emails to watchdog instead of sending them.
    if ($this->config->get('log_emails_to_watchdog')) {
      return $this->logUnsubscribeToWatchdog($mailinglist_id, $subscriber_id, $alias);
    }

    $api_token = $this->config->get('peytzmail_api_token');
    $options = [
      'auth' => [$api_token, NULL],
      'headers'  => [
        'content-type' => 'application/json',
        'Accept' => 'application/json',
      ],
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

  /**
   * Request subscribers for a mailinglist.
   *
   * @param string $mailinglist
   *   Mailinglist ID.
   *
   * @return array
   *   List of subscribers.
   */
  public function getMailinglistSubscribers(string $mailinglist) {
    $api_token = $this->config->get('peytzmail_api_token');
    $options = [
      'auth' => [$api_token, NULL],
      'headers' => [
        'content-type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ];

    $uri = '/api/v1/mailinglists/' . $mailinglist . '/subscribers.json';

    try {
      $response = $this->request->get($uri, $options);
      $result = JSON::decode($response->getBody()->getContents());
      return $result['subscribers'];
    }
    catch (ClientException $exception) {
      \Drupal::logger('emailservice')
        ->error($exception->getMessage() . ': ' . $exception->getCode());
    }
  }

  /**
   * Log signup to watchdog instead of performing it.
   *
   * @param array $data
   *   Subscriber data that would have been sent.
   *
   * @return array
   *   Mock response indicating the signup was logged.
   */
  private function logSignupToWatchdog(array $data) {
    \Drupal::logger('emailservice')->info('Signup logged instead of performed - Email: @email, Data: @data', [
      '@email' => $data['email'] ?? 'No email provided',
      '@data' => json_encode($data),
    ]);

    // Show a message to the user that the signup was logged instead of performed.
    \Drupal::messenger()->addStatus('Signup action was logged to watchdog instead of being performed (development mode).');

    // Return a mock response similar to what the API would return.
    return [
      'code' => 200,
      'result' => [
        'status' => 'logged',
        'message' => 'Signup logged to watchdog instead of performing',
        'data' => $data,
      ],
    ];
  }

  /**
   * Log email to watchdog instead of sending it.
   *
   * @param string $mailinglist
   *   Mailing list parameter.
   * @param object $feed
   *   Feed that would have been sent.
   *
   * @return string
   *   Mock response indicating the email was logged.
   */
  private function logEmailToWatchdog($mailinglist, $feed) {
    $email_data = [
      'mailinglist' => $mailinglist,
      'feed' => $feed,
      'timestamp' => date('Y-m-d H:i:s'),
    ];

    \Drupal::logger('emailservice')->info('Email logged instead of sent - Mailinglist: @mailinglist, Subject: @subject, Content:<pre>@content</pre>', [
      '@mailinglist' => $mailinglist,
      '@subject' => $feed->subject ?? 'No subject',
      '@content' => json_encode($feed, JSON_PRETTY_PRINT),
    ]);

    // Show a message to the user that the email was logged instead of sent.
    \Drupal::messenger()->addStatus('Email was logged to watchdog instead of being sent (development mode).');

    // Return a mock response similar to what the API would return.
    return json_encode([
      'status' => 'logged',
      'message' => 'Email logged to watchdog instead of sending',
      'data' => $email_data,
    ]);
  }

  /**
   * Log unsubscribe action to watchdog instead of performing it.
   *
   * @param string $mailinglist_id
   *   Mailing list ID.
   * @param string $subscriber_id
   *   Subscriber ID.
   * @param string $alias
   *   Subscriber node alias.
   *
   * @return array
   *   Mock response indicating the unsubscribe was logged.
   */
  private function logUnsubscribeToWatchdog($mailinglist_id, $subscriber_id, $alias) {
    \Drupal::logger('emailservice')->info('Unsubscribe logged instead of performed - Mailinglist: @mailinglist_id, Subscriber: @subscriber_id, Alias: @alias', [
      '@mailinglist_id' => $mailinglist_id,
      '@subscriber_id' => $subscriber_id,
      '@alias' => $alias,
    ]);

    // Show a message to the user that the unsubscribe was logged instead of performed.
    \Drupal::messenger()->addStatus('Unsubscribe action was logged to watchdog instead of being performed (development mode).');

    // Return a mock response similar to what the API would return.
    return [
      'status' => 'logged',
      'message' => 'Unsubscribe logged to watchdog instead of performing',
      'mailinglist_id' => $mailinglist_id,
      'subscriber_id' => $subscriber_id,
      'alias' => $alias,
    ];
  }

}
