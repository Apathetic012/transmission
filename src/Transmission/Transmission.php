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
   * @param string $url Torrent / Magnet URL
   */
  public function add($url) {
    return $this->callServer('torrent-add', array('filename' => $url));
  }

  /**
   * Get torrent info
   * @param  string|array $id Torrent id(s)
   * @return array
   */
  public function get($ids, $fields = array()) {
    $ids = (array) $ids;
    $fields = empty($fields) ? static::$fields : $fields;

    return $this->callServer('torrent-get', array('ids' => $ids, 'fields' => $fields));
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

      // unrecognized status code
      throw $e;
    }

    return $this->sessionId;
  }

  protected function callServer($command, $options) {

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

    // won't catch the exceptions
    $response = $request->send();

    $jsonResponse = json_decode($response->getBody(true));

    switch ($command) {

      case 'torrent-add': return $jsonResponse->arguments->{'torrent-added'};
      case 'torrent-get': return $jsonResponse->arguments->torrents;
    }
  }

}

class TransmissionException extends \Exception {}
