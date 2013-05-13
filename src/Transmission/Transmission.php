<?php namespace Transmission;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;

class Transmission {

  protected $host        = 'http://127.0.0.1';
  protected $port        = 9091;
  protected $endpoint    = '/transmission/rpc';
  protected $debug       = false;
  protected $sessionId   = '';
  protected $username    = '';
  protected $password    = '';

  public static $csrfKey = 'X-Transmission-Session-Id';

  public static $fields  = [
    'activityDate', 'addedDate', 'bandwidthPriority', 'comment', 'corruptEver',
    'creator', 'dateCreated', 'desiredAvailable', 'doneDate', 'downloadDir',
    'downloadedEver', 'downloadLimit', 'downloadLimited', 'error',
    'errorString', 'eta', 'files', 'fileStats', 'hashString', 'haveUnchecked',
    'haveValid', 'honorsSessionLimits', 'id', 'isFinished', 'isPrivate',
    'leftUntilDone', 'magnetLink', 'manualAnnounceTime', 'maxConnectedPeers',
    'metadataPercentComplete', 'name', 'peer-limit', 'peers', 'peersConnected',
    'peersFrom', 'peersGettingFromUs', 'peersKnown', 'peersSendingToUs',
    'percentDone', 'pieces', 'pieceCount', 'pieceSize', 'priorities',
    'rateDownload', 'rateUpload', 'recheckProgress', 'seedIdleLimit',
    'seedIdleMode', 'seedRatioLimit', 'seedRatioMode', 'sizeWhenDone',
    'startDate', 'status', 'trackers', 'trackerStats ', 'totalSize',
    'torrentFile', 'uploadedEver', 'uploadLimit', 'uploadLimited',
    'uploadRatio', 'wanted', 'webseeds', 'webseedsSendingToUs'
  ];

  /**
   * @var Guzzle\Http\Client
   */
  protected $client;

  /**
   * @var Guzzle\Http\Message\Response
   */
  protected $response;

  public function __construct(array $config) {

    if (isset($config['host'])) {
      $this->host = strpos($config['host'], 'http://') === 0 ? $config['host'] : 'http://' . $config['host'];
    }
    if (isset($config['port'])) $this->port = $config['port'];
    if (isset($config['debug'])) $this->debug = $config['debug'];
    if (isset($config['endpoint'])) $this->endpoint = $config['endpoint'];
    if (isset($config['fields'])) static::$fields = $config['fields'];

    $this->client = new Client($this->host . ':' . $this->port,
      array('curl.options' => array(
        'debug' => $this->debug
      ))
    );

    if (isset($config['username']) && isset($config['password'])) {
      list($this->username, $this->password) = array($config['username'], $config['password']);
    }
  }

  /**
   * Add a torrent
   * @param string $url     Torrent / Magnet URL
   * @param array  $options additional options
   */
  public function add($url, array $options) {
    $response = $this->callServer('torrent-add', array('filename' => $url) + $options);

    return $response->{'torrent-added'};
  }

  /**
   * Get torrent info
   * @param  int|array $id Torrent id(s)
   * @return array
   */
  public function get($ids, $fields = array()) {
    $ids = (array) $ids;
    $fields = empty($fields) ? static::$fields : $fields;

    $response = $this->callServer('torrent-get', array(
      'ids'    => $ids,
      'fields' => $fields
    ));

    return $response->torrents;
  }

  /**
   * Get torrent info for a single torrent
   * @param  int    $id     Torrent id
   * @param  array  $fields
   * @return obj|bool       returns false if torrent ID is inexistent
   */
  public function only($id, $fields = array()) {
    $get = $this->get($id, $fields);

    return reset($get);
  }

  /**
   * Remove torrent and optionally it's data
   * @param  int|array  $ids     The torrent id(s)
   * @param  boolean $deleteData Delete the data as well
   * @return boolean
   */
  public function delete($ids, $deleteData = false) {
    $ids = (array) $ids;

    $this->callServer('torrent-remove', array(
      'ids'               => $ids,
      'delete-local-data' => $deleteData
    ));

    return true;
  }

  /**
   * Get session variables
   * @param  array|string  $keys
   * @return array|string
   */
  public function getSession($keys = array()) {
    $response = $this->callServer('session-get');

    if (is_array($keys)) {
      if (empty($keys)) return $response;

      $vars = new \stdClass;
      foreach ($keys as $key) {
        $vars->{$key} = isset($response->{$key}) ? $response->{$key} : null;
      }

      return $vars;
    }

    return (isset($response->{$keys}) ? $response->{$keys} : null);
  }

  protected function getSessionId() {
    $request = $this->client->get($this->endpoint);

    if ($this->username && $this->password) {
      $request->setAuth($this->username, $this->password);
    }

    try {
      $this->response = $request->send();
    } catch (BadResponseException $e) {
      $this->response = $e->getResponse();

      if ($this->response->getStatusCode() == 409) {
        $this->sessionId = (string) $this->response->getHeader(static::$csrfKey);
        if ( ! $this->sessionId) {
          throw new TransmissionException('Unable to get ' . static::$csrfKey . '.');
        }

        return $this->sessionId;
      }

      if ($this->response->getStatusCode() == 401) {
        throw new TransmissionException('Invalid username/password.');
      }

      // unrecognized status code
      throw $e;
    }

    return $this->sessionId;
  }

  protected function callServer($command, $options = array()) {

    if ( ! $this->sessionId) {
      $this->getSessionId();
    }

    $data = array(
      'method'    => $command,
      'arguments' => $options
    );

    $request = $this->client->post(
      $this->endpoint, array(
        static::$csrfKey => $this->sessionId
      ), json_encode($data));

    if ($this->username && $this->password) {
      $request->setAuth($this->username, $this->password);
    }

    $jsonResponse = $request->send();
    $response = json_decode($jsonResponse->getBody(true));

    if ($response->result == 'duplicate torrent') {
      throw new DuplicateTorrentException;
    }

    // assume $response->result == 'success'

    return $response->arguments;
  }

}

class TransmissionException extends \Exception {}
class DuplicateTorrentException extends \Exception {}
