<?php
/**
 * Created by PhpStorm.
 * User: ben.jeavons
 * Date: 2/11/14
 * Time: 7:17 PM
 */

namespace Drupal\acquia_connector;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

class Client {

  /**
   * @todo create specific exceptions?
   *
   */

  /**
   * @var \Guzzle\Http\ClientInterface
   */
  protected $client;

  /**
   * @var array
   */
  protected $headers;

  /**
   * @var string
   */
  protected $server;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  public function __construct(ClientInterface $client, ConfigFactoryInterface $config) {
    $this->client = $client;
    $this->headers = array(
      'Content-Type' => 'application/json',
      'Accept' => 'application/json'
    );
    $this->config = $config->get('acquia_connector.settings');
    $this->server = $this->config->get('network_address');
    $this->client->setDefaultOption('verify', $this->config->get('ssl_verify'));
  }

  /**
   * Get account settings to use for creating request authorizations.
   *
   * @param string $email Acquia Network account email
   * @param string $password
   *   Plain-text password for Acquia Network account. Will be hashed for
   *   communication.
   */
  public function getSubscriptionCredentials($email, $password) {
    $body = array('email' => $email); //@todo
    $authenticator = $this->buildAuthenticator($email, array('rpc_version' => '2.1'));
    $data = array(
      'body' => $body,
      'authenticator' => $authenticator,
    );

    $communication_setting = $this->request('POST', '/agent-api/subscription/communication', $data);

    if($communication_setting) {
      $crypt_pass = new CryptConnector($communication_setting['algorithm'], $password, $communication_setting['hash_setting'], $communication_setting['extra_md5']);
      $pass = $crypt_pass->cryptPass();

      $body = array('email' => $email, 'pass' => $pass, 'rpc_version' => '2.1'); //@todo
      $authenticator = $this->buildAuthenticator($pass, array('rpc_version' => '2.1'));
      $data = array(
        'body' => $body,
        'authenticator' => $authenticator,
      );

      $response = $this->request('POST', '/agent-api/subscription/credentials', $data);
      if($response['body']){
        return $response['body'];
      }
    }
  }

  /**
   * Validate Network ID/Key pair to Acquia Network.
   *
   * @param string $id Network ID
   * @param string $key Network Key
   * @return bool
   */
  public function validateCredentials($id, $key) {
    try {
      $this->getSubscription($id, $key);
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get Acquia subscription from Acquia Network.
   *
   * @param string $id Network ID
   * @param string $key Network Key
   * @param array $body
   *   (optional)
   *
   * @return array|false
   * D7: acquia_agent_get_subscription
   */
  public function getSubscription($id, $key, array $body = array()) {
    $body += array('identifier' => $id, 'rpc_version' => '2.1'); //@todo
    $authenticator =  $this->buildAuthenticator($key, $body);
    $data = array(
      'body' => $body,
      'authenticator' => $authenticator,
    );

    // There is an identifier and key, so attempt communication.
    $subscription = array();
    $subscription['timestamp'] = REQUEST_TIME;

    // Include version number information.
    acquia_connector_load_versions();
    if (IS_ACQUIA_DRUPAL) {
      $params['version']  = ACQUIA_DRUPAL_VERSION;
      $params['series']   = ACQUIA_DRUPAL_SERIES;
      $params['branch']   = ACQUIA_DRUPAL_BRANCH;
      $params['revision'] = ACQUIA_DRUPAL_REVISION;
    }
    // @todo
    // Include Acquia Search module version number.
    if (\Drupal::moduleHandler()->moduleExists('acquia_search')) {
//      foreach (array('acquia_search', 'apachesolr') as $name) {
//        $info = system_get_info('module', $name);
//        // Send the version, or at least the core compatibility as a fallback.
//        $params['search_version'][$name] = isset($info['version']) ? (string)$info['version'] : (string)$info['core'];
//      }
    }
    // @todo
    // Include Acquia Search for Search API module version number.
    if (\Drupal::moduleHandler()->moduleExists('search_api_acquia')) {
//      foreach (array('search_api_acquia', 'search_api', 'search_api_solr') as $name) {
//        $info = system_get_info('module', $name);
//        // Send the version, or at least the core compatibility as a fallback.
//        $params['search_version'][$name] = isset($info['version']) ? (string)$info['version'] : (string)$info['core'];
//      }
    }

    try{
      $response = $this->request('POST', '/agent-api/subscription/' . $id, $data);
      if ($this->validateResponse($key, $response, $authenticator)) {
        return $subscription + $response['body'];
      }
    }
    catch (\Exception $e){}
    return FALSE;
  }

  /**
   * Get Acquia subscription from Acquia Network.
   *
   * @param string $id Network ID
   * @param string $key Network Key
   * @param array $body
   *   (optional)
   *
   * @return array|false
   */
  public function sendNspi($id, $key, array $body = array()) {
    $body['identifier'] = $id;
    $authenticator =  $this->buildAuthenticator($key, $body);
    dpm($authenticator);
    $ip = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : '';
    $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : '';
    $ssl = isset($_SERVER["HTTPS"]) ? TRUE : FALSE;
    $data = array(
      'body' => $body,
      'authenticator' => $authenticator,
      'ip' => $ip,
      'host' => $host,
      'ssl' => $ssl,
    );
    dpm($data);

    try{
      $response = $this->request('POST', '/spi-api/site', $data);
//      if ($this->validateResponse($key, $response, $authenticator)) {
        return $response;
//      }
    }
    catch (\Exception $e){}
    return FALSE;
  }

  public function getDefinition($apiEndpoint) {
    try{
      $response = $this->request('GET', $apiEndpoint, array());
      return $response;
    }
    catch (\Exception $e){
    }
    return FALSE;
  }

  /**
   * Validate the response authenticator.
   *
   * @param string $key
   * @param array $response
   * @param array $requestAuthenticator
   * @return bool
   */
  protected function validateResponse($key, array $response, array $requestAuthenticator) {
    $responseAuthenticator = $response['authenticator'];
    if (!($requestAuthenticator['nonce'] === $responseAuthenticator['nonce'] && $requestAuthenticator['time'] < $responseAuthenticator['time'])) {
      return FALSE;
    }
    $hash = $this->hash($key, $responseAuthenticator['time'], $responseAuthenticator['nonce'], $response['body']);
    return ($hash === $responseAuthenticator['hash']);
  }

  /**
   * Create and send a request.
   *
   * @param string $method
   * @param string $path
   * @param array $data
   * @return array|false
   * @throws \Exception
   */
  protected function request($method, $path, $data) {
    $uri = $this->server . $path;
    switch ($method) {
      case 'GET':
        try {
          $options = array(
            'headers' => $this->headers,
            'json' => json_encode($data),
          );

          $response = $this->client->get($uri, $options);
        }
        catch (ClientException $e) {
          drupal_set_message($e->getMessage(), 'error');
        }
        break;
      case 'POST':
        try {
          $options = array(
            'headers' => $this->headers,
            'json' => json_encode($data),
          );

          $response = $this->client->post($uri, $options);

        }
        catch (ClientException $e) {
          drupal_set_message($e->getMessage(), 'error');
        }
        break;
    }
    // @todo support response code
    if (!empty($response)) {
      $body = $response->json();
      if (!empty($body['error'])) {
        drupal_set_message($body['code'] . ' : ' .$body['message'], 'error');
      }
      return $body;
    }
    return FALSE;
  }

  /*
   * Build authenticator to sign requests to the Acquia Network
   *
   * @params string $key Secret key to use for signing the request.
   * @params array $params Optional parameters to include.
   *   'identifier' - Network Identifier
   *
   * @return string
   */
  protected function buildAuthenticator($key, $params = array()) {
    $authenticator = array();
    if (isset($params['identifier'])) {
      // Put Network ID in authenticator but do not use in hash.
      $authenticator['identifier'] = $params['identifier'];
      unset($params['identifier']);
    }
    $nonce = $this->getNonce();
    $authenticator['time'] = REQUEST_TIME;
    $authenticator['hash'] = $this->hash($key, REQUEST_TIME, $nonce, $params);
    $authenticator['nonce'] = $nonce;

    return $authenticator;
  }

  /**
   * Calculates a HMAC-SHA1 according to RFC2104 (http://www.ietf.org/rfc/rfc2104.txt).
   *
   * @param string $key
   * @param int $time
   * @param string $nonce
   * @param array $params
   * @return string
   * D7: _acquia_agent_hmac
   */
  protected function hash($key, $time, $nonce, $params = array()) {
    if (empty($params['rpc_version']) || $params['rpc_version'] < 2) {
      dpm('Methos 1');
      $string = $time . ':' . $nonce . ':' . $key . ':' . serialize($params);

      return base64_encode(
        pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
        pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) .
        $string)))));
    }
    elseif ($params['rpc_version'] == 2) {
      dpm('Methos 2');
      $string = $time . ':' . $nonce . ':' . json_encode($params);
      return sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) . pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $string)));
    }
    else {
      $string = $time . ':' . $nonce;
      return sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) . pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $string)));
    }
  }

  /**
   * Get a random base 64 encoded string.
   *
   * @return string
   */
  protected function getNonce() {
    return Crypt::hashBase64(uniqid(mt_rand(), TRUE) . Crypt::randomBytes(55));
  }
}
