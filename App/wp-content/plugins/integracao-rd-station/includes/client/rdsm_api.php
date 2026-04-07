<?php

require_once(RDSM_SRC_DIR . '/helpers/rdsm_log_file_helper.php');

class RDSMAPI {
  private $api_url;
  private $user_credentials;

  function __construct($server_url, $user_credentials) {
    $this->api_url = $server_url;
    $this->user_credentials = $user_credentials;
  }

  public function get($resource, $args = array()) {
    if ($this->user_credentials->access_token()) {
      $args['headers'] = $this->authorization_header($args);
    }

    $response = wp_remote_get(sprintf("%s%s", $this->api_url, $resource), $args);   
    
    if (is_wp_error($response)) {
      $this->log_response('error', 'GET', $resource, $response, $args);
      return $response;
    }

    if ($this->handle_expired_token($response)) {
      return $this->get($resource, $args);
    }
    
    return $response;
  }

  public function post($resource, $args = array()) {
    if ($this->user_credentials->access_token()) {
      $args['headers'] = $this->authorization_header($args);
    }

    $response = wp_remote_post(sprintf("%s%s", $this->api_url, $resource), $args);

    if (is_wp_error($response)) {
      $this->log_response('error', 'POST', $resource, $response, $args);
      return $response;
    } 

    if ($this->handle_expired_token($response)) {
      return $this->post($resource, $args);
    }

    $type = $this->is_response_error($response) ? 'error' : 'success';
    $this->log_response($type, 'POST', $resource, $response, $args);

    return $response;
  }

  private function authorization_header($args) {
    $authorization_header = array('Authorization' => 'Bearer ' . $this->user_credentials->access_token());

    if (is_array($args) && $args['headers']) {
      return array_merge($args['headers'], $authorization_header);
    }

    return $authorization_header;
  }

  private function refresh_token() {
    $refresh_token = $this->user_credentials->refresh_token();

    if (empty($refresh_token)) {
      return false;
    }

    $response = wp_remote_get(sprintf("%s/%s%s", RDSM_REFRESH_TOKEN_URL, "?refresh_token=", $refresh_token));
   
    if (is_wp_error($response)) {
      $this->log_response('error', 'REFRESH', $url, $response);
      return false;
    }

    if (wp_remote_retrieve_response_code($response) == 200) {
      $parsed_credentials = json_decode(wp_remote_retrieve_body($response));
      $this->update_user_credentials($parsed_credentials);

      return true;
    }

    $this->log_response('success', 'REFRESH', $url, $response);
    return false;
  }

  private function handle_expired_token($response) {
    if (wp_remote_retrieve_response_code($response) != 401) {
      return false;
    }

    return $this->refresh_token();
  }

  private function update_user_credentials($credentials) {
    $this->user_credentials->save_access_token($credentials->access_token);
    $this->user_credentials->save_refresh_token($credentials->refresh_token);
  }

  private function is_response_error($response) {
    if (is_wp_error($response)) {
      return true;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    return $code >= 400 || strpos($body, 'errors') !== false;
  }

  private function log_response($type, $method, $resource, $response, $args = []) {
    $log_data = array(
      'method'    => $method,
      'endpoint'  => $this->api_url . $resource,
      'payload'   => isset($args['body']) ? json_decode($args['body'], true) : null,
    );

    if (is_wp_error($response)) {
      $log_data['error_type'] = 'WP_Error';
      $log_data['error_message'] = $response->get_error_message();
    } else {
      $log_data['status_code'] = wp_remote_retrieve_response_code($response);

      $body = wp_remote_retrieve_body($response);
      $decoded_body = json_decode($body, true);
      $log_data['body'] = $decoded_body !== null ? $decoded_body : $body;
    }

    $prefix = strtoupper($type); // "SUCCESS" ou "ERROR"
    $log_string = "[$prefix] " . json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    RDSMLogFileHelper::write_to_log_file($log_string);
  }

}
