<?php
require_once('xmlrpc-3.0.0.beta/lib/xmlrpc.inc');

/**
 * 
 * @author arnaud
 * @version 1.0.0
 * 
 * A class providing a connection with the Drupal xmlrpc server
 * of the services module.
 * This class uses the library XML-RPC for PHP version 3.0.0.beta.
 * <a href="http://phpxmlrpc.sourceforge.net" title="http://phpxmlrpc.sourceforge.net" rel="nofollow">http://phpxmlrpc.sourceforge.net</a>
 * Example: Login, create a new user, update its name, delete the user, logout.
 * 
 * require_once('xmlrpc_connector.inc');
 * 
 * $connector = new XmlRpcConnector('services/xmlrpc', 'my.drupal.website.com', 80, 'my_api_key', 'app_domain.com');
 * $connector->login('user', 'password');
 * $user_id = $connector->user_create('username', 'userpassword', 'email@email.com'); // this method returns the user id of the created user.
 * $connector->user_update($user_id, 'newname', 'newpass', 'newemail');
 * $connector->user_delete($user_id);
 * $connector->logout();
 */
class XmlRpcConnector {
  private $_server;
  private $_domain;
  private $_app_domain;
  private $_port;
  private $_api_key;
  private $_timestamp;
  private $_nonce;
  private $_hash;
  private $_sessionId;
  private $_userId;
  private $_xmlrpc_client;
  
  /**
   * Instantiates an xmlrpcConnector
   * 
   * @param string $server Path to the xmlrpc server (usually: services/xmlrpc).
   * @param string $domain The domain of the Drupal website (mydomain.com).
   * @param int $port The port on which the server listens for conections (defaults to 80).
   * @param string $api_key The api key if necessary (not required for anonymous methods).
   * @param string $app_domain The authorized domain linked with the api key.
   * @return void
   * @throws Exception If invalid argument specified.
   */
  public function __construct($server = null, $domain = null, $port = 80, $api_key = null, $app_domain = null) {
    if ($server == null || $domain == null) {
      throw new Exception('Invalid Constructor Arguments');
    }
    
    $this->_server      = $server;
    $this->_domain      = $domain;
    $this->_port        = $port;
    $this->_api_key     = $api_key;
    $this->_app_domain  = $app_domain;
    
    $this->_connect();
  }

  /**
   * Login with a user account. Requires an api key and an athorized domain.
   * 
   * @param string $userName The user name.
   * @param string $password The password.
   * @return void
   * @throws Exception If invalid argument specified or if an error occurs during login.
   */
  public function login($userName = null, $password = null) {
    if ($userName == null || $password == null) {
      throw new Exception('Illegal Arguments');
    } else if ($this->_api_key == null) {
      throw new Exception('Api key required to login');
    } else if ($this->_app_domain == null) {
      throw new Exception(' Application domain missing');
    }
    
    $this->_setUpHash('user.login');
    
    $m = $this->_getXmlRpcMsg('user.login', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId,
      $userName,
      $password
    ));

    $r = $this->_xmlrpc_client->send($m);

    if (!$r->faultCode()) {
      $v = $r->value();

      //We only take the user id but there is more stuff
      $this->_userId = $v['user']['uid'];

      // The session id changed during the login call
      $this->_sessionId = $v['sessid'];
    } else {
      throw new Exception(" An error occurred on user.login: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Logs out the user currently logged in.
   *
   * @return void
   * @throws Exception if an error occurs during logout.
   */
  public function logout() {
    $this->_setUpHash('user.logout');
    
    $m = $this->_getXmlRpcMsg('user.logout', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId
    ));
    
    $r = $this->_xmlrpc_client->send($m);
    
    if ($r->faultCode()) {
      throw new Exception("An error occurred on user.logout: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Deletes a user from the website. Requires to be logged in.
   * 
   * @param mixed $user_id The user id of the user to delete.
   * @return void
   * @throws Exception if an error occurs during user deletion.
   */
  public function user_delete($user_id = null) {
    $this->_setUpHash('user.delete');
    
    $m = $this->_getXmlRpcMsg('user.delete', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId,
      $user_id
    ));
    
    $r = $this->_xmlrpc_client->send($m);
    
    if ($r->faultCode()) {
      throw new Exception(" An error occurred on user.logout: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Creates a new user. Requires to be logged in.
   * 
   * @param string $name The name.
   * @param string $pass The password.
   * @param string $mail The e-mail address.
   * @return string The user id of the created user.
   * @throws Exception If an error occurs during the save operation.
   */
  public function user_create($name, $pass, $mail) {
    $this->_setUpHash('user.save');
    
    $m = $this->_getXmlRpcMsg('user.save', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId,
      array('name' => $name, 'pass' => $pass, 'mail' => $mail)
    ));
    
    $r = $this->_xmlrpc_client->send($m);
    
    if (!$r->faultCode()) {
      return $r->value();
    } else {
      throw new Exception(" An error occurred on user.save: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Updates the account of a user.
   * 
   * @param mixed $user_id The user id used to identifie which user to modify.
   * @param string $name New name.
   * @param string $pass New password.
   * @param string $mail New e-mail.
   * @return void
   * @throws Exception If an error occurs during the user update.
   */
  public function user_update($user_id, $name, $pass, $mail) {
    $this->_setUpHash('user.save');
    
    $m = $this->_getXmlRpcMsg('user.save', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId,
      array('uid' => $user_id, 'name' => $name, 'pass' => $pass, 'mail' => $mail)
    ));
    
    $r = $this->_xmlrpc_client->send($m);
    
    if ($r->faultCode()) {
      throw new Exception(" An error occurred on user.save: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Get a user from the website.
   * 
   * @param mixed $user_id The user id.
   * @return An array representing the Drupal user object.
   * @throws Exception If an error occurs during the user retrieval.
   */
  public function user_get($user_id) {
    $this->_setUpHash('user.get');
    
    $m = $this->_getXmlRpcMsg('user.get', array(
      $this->_hash,
      $this->_app_domain,
      $this->_timestamp,
      $this->_nonce,
      $this->_sessionId,
      $user_id
    ));
    
    $r = $this->_xmlrpc_client->send($m);
    
    if (!$r->faultCode()) {
      return $r->value();
    } else {
      throw new Exception(" An error occurred on user.get: " . $r->faultString() . "\n");
    }
  }

  /**
   * Establishes the system connection to the website.
   *
   * @return void
   * @throws Exception If an error occurs during connection.
   */
  private function _connect() {
    $m = $this->_getXmlRpcMsg('system.connect');
    
    $this->_xmlrpc_client = new xmlrpc_client(
      $this->_server,
      $this->_domain,
      $this->_port
    );

    $this->_xmlrpc_client->return_type = 'phpvals';
    $r = $this->_xmlrpc_client->send($m);
    
    if (!$r->faultCode()) {
      $v = $r->value();
      $this->_sessionId = $v['sessid'];
    } else {
      throw new Exception(" An error occurred on system.connect: " . $r->faultString() . "\n");
    }
  }
  
  /**
   * Transforms a method call with its argument into an xmlrpc message.
   * 
   * @param string $method The method to call.
   * @param mixed $args The method arguments.
   * @return xmlrpcmsg The new XML-RPC message.
   */
  private function _getXmlRpcMsg($method, $args = array()) {
    foreach ($args as &$argument) {
      if (is_array($argument)) {
        foreach ($argument as &$a) {
          $a = new xmlrpcval($a);
        }
        $argument = new xmlrpcval($argument, 'struct');
      } else {
        $argument = new xmlrpcval($argument);
      }
    }
    
    return new xmlrpcmsg($method, $args);
  }
  
  /**
   * Sets the required hash code for a method.
   * 
   * @param string $method The method for which the hash code is generated.
   * @return void
   */
  private function _setUpHash($method) {
    $this->_timestamp = (string) time();
    $this->_nonce     = uniqid('nonce_', true);
    $hash_parameters  = array(
      $this->_timestamp,
      $this->_app_domain,
      $this->_nonce,
      $method
    );
                             
    $this->_hash = hash_hmac(
      "sha256",
      implode(';', $hash_parameters),
      $this->_api_key
    );
  }
}
?>
