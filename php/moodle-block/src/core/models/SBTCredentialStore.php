<?php
/*
 * © Copyright IBM Corp. 2013
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at:
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
* implied. See the License for the specific language governing
* permissions and limitations under the License.
*/
if (!isset($CFG) || !isset($CFG->wwwroot)) {
	$path = str_replace("blocks".DIRECTORY_SEPARATOR."ibmsbt".DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."models", '', __DIR__);
	include_once $path . "".DIRECTORY_SEPARATOR."config.php";
}

if (!defined('SESSION_NAME')) {
	define('SESSION_NAME', 'ibm_sbt_session');
}

if (!defined('BASIC_AUTH_USERNAME')) {
	define('BASIC_AUTH_USERNAME', 'basicauthusername');
}

if (!defined('BASIC_AUTH_PASSWORD')) {
	define('BASIC_AUTH_PASSWORD', 'basicauthpassword');
}

if (!defined('TOKEN')) {
	define('TOKEN', 'token');
}

if (!defined('REQUEST_TOKEN')) {
	define('REQUEST_TOKEN', 'requesttoken');
}

if (!defined('TOKEN_TYPE')) {
	define('TOKEN_TYPE', 'tokentype');
}

if (!defined('OAUTH_TOKEN')) {
	define('OAUTH_TOKEN', 'oauthtoken');
}

if (!defined('OAUTH_TOKEN_SECRET')) {
	define('OAUTH_TOKEN_SECRET', 'oauthtokensecret');
}

if (!defined('OAUTH_VERIFIER_TOKEN')) {
	define('OAUTH_VERIFIER_TOKEN', 'oauthverifiertoken');
}

if (!defined('OAUTH_REQUEST_TOKEN')) {
	define('OAUTH_REQUEST_TOKEN', 'oauthrequesttoken');
}

if (!defined('OAUTH_REQUEST_TOKEN_SECRET')) {
	define('OAUTH_REQUEST_TOKEN_SECRET', 'oauthrequesttokensecret');
}

if (!defined('IBM_SBT_CRYPTO_ENABLED')) {
	global $CFG;
	require_once $CFG->dirroot . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'ibmsbt' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'security.php';
}

/**
 * Credential Store for authorization tokens.
 *
 * @author Benjamin Jakobus
 */
class SBTCredentialStore {
	
	// Session name
	private $sessionName;
	
	// Encryption key
	private $key;
	
	// Initialization vector
	private $iv;
	
	private static $uid;
	
	private static $instance = null;
	
	public static function getInstance($uid = null) {
		self::$uid = $uid;
		if (self::$instance == null) {
			self::$instance = new SBTCredentialStore();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->_initProfileSession();
	}

	/**
	 * Generate keys and IV.
	 */
	private function _initProfileSession() {
		$this->key = $this->gen_string(32);
		
		global $DB;
		global $USER;
		$dbman = $DB->get_manager();
		
		$table = new xmldb_table(SESSION_NAME);
		
		if (!$dbman->table_exists($table)) {
			$this->_createTable($table, $dbman);
		} else {
			$uid = null;
			if (isset($USER->id) && $USER->id != 0) {
				$uid = $USER->id;
			} else if (isset(self::$uid)) {
				$uid = self::$uid;
			} else if (isset($_COOKIE['ibm-sbt-uid'])) {
				$uid = intval($_COOKIE['ibm-sbt-uid']);
			}  else {
				$uid = $_GET['uid'];
			} 
			$records = $DB->get_records(SESSION_NAME, array('user_id' => $uid));	

			if (empty($records) || $records == null) {
				$record = new stdClass();
				
				if (isset($USER->id) && $USER->id != 0) {
					$record->user_id = intval($USER->id);
				} else if (self::$uid != null) {
					$record->user_id = intval(self::$uid);
				} else if (isset($_COOKIE['ibm-sbt-uid'])) {
					$record->user_id = intval($_COOKIE['ibm-sbt-uid']);
				} 
				
				// Populate database with default values
				$record->basicauthusername = json_encode(array('connections' => null));
				$record->basicauthpassword = json_encode(array('connections' => null));
				$record->token = json_encode(array('connections' => null));
				$record->requesttoken = json_encode(array('connections' => null));
				$record->tokentype = json_encode(array('connections' => null));
				$record->oauthtoken = json_encode(array('connections' => null));
				$record->oauthtokensecret = json_encode(array('connections' => null));
				$record->oauthverifiertoken = json_encode(array('connections' => null));
				$record->oauthrequesttoken = json_encode(array('connections' => null));
				$record->oauthrequesttokensecret = json_encode(array('connections' => null));
					
				if (defined('IBM_SBT_CRYPTO_ENABLED') && IBM_SBT_CRYPTO_ENABLED) {
					$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
					$this->iv = base64_encode(mcrypt_create_iv($iv_size, MCRYPT_RAND));
					
					$record->iv = $this->iv;
				} else {
					$record->iv = '';
				}
				$ret = $DB->insert_record(SESSION_NAME, $record);
			} else {
				foreach ($records as $record) {
					$this->iv = $record->iv;
				}
			}
		}
	}
	
	private function _createTable($table, $dbman) {
		
		global $DB;
		global $USER;
		
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
		$table->add_field('iv', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(BASIC_AUTH_USERNAME, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(BASIC_AUTH_PASSWORD, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(TOKEN, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(REQUEST_TOKEN, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(TOKEN_TYPE, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(OAUTH_TOKEN, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(OAUTH_TOKEN_SECRET, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(OAUTH_VERIFIER_TOKEN, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(OAUTH_REQUEST_TOKEN, XMLDB_TYPE_TEXT, 'big', null, null, null, null);
		$table->add_field(OAUTH_REQUEST_TOKEN_SECRET, XMLDB_TYPE_TEXT, 'big', null, null, null, null);

		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		
		$dbman->create_table($table);
	
		$record = new stdClass();
		
		if (isset($USER->id) && $USER->id != 0) {
			$record->user_id = intval($USER->id);
		} else if (self::$uid != null) {
			$record->user_id = intval(self::$uid);
		} else if (isset($_COOKIE['ibm-sbt-uid'])) {
			$record->user_id = intval($_COOKIE['ibm-sbt-uid']);
		} 

		// Populate database with default values
		$record->basicauthusername = json_encode(array('connections' => null));
		$record->basicauthpassword = json_encode(array('connections' => null));
		$record->token = json_encode(array('connections' => null));
		$record->requesttoken = json_encode(array('connections' => null));
		$record->tokentype = json_encode(array('connections' => null));
		$record->oauthtoken = json_encode(array('connections' => null));
		$record->oauthtokensecret = json_encode(array('connections' => null));
		$record->oauthverifiertoken = json_encode(array('connections' => null));
		$record->oauthrequesttoken = json_encode(array('connections' => null));
		$record->oauthrequesttokensecret = json_encode(array('connections' => null));
			
		if (defined('IBM_SBT_CRYPTO_ENABLED') && IBM_SBT_CRYPTO_ENABLED) {
			$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
			$this->iv = base64_encode(mcrypt_create_iv($iv_size, MCRYPT_RAND));
	
			$record->iv = $this->iv;
		} else {
			$record->iv = '';
		}
		$ret = $DB->insert_record(SESSION_NAME, $record);
	}
	
	/**
	 * Stores a value in the credential store.
	 * 
	 * @param string $skey
	 * @param string $value
	 */
	private function _store($skey, $endpoint, $value) {
		try {
			global $DB;
			global $USER;
			
			$uid = null;
			if (isset($USER->id) && $USER->id != 0) {
				$uid = $USER->id;
			} else if (isset($_GET['uid'])) {
				$uid = $_GET['uid'];
			} else if (isset($_COOKIE['ibm-sbt-uid']) && $_COOKIE['ibm-sbt-uid'] != null) {
				$uid = $_COOKIE['ibm-sbt-uid'];
			} else if (self::$uid != null) {
				$uid = self::$uid;
			} else {
				return;
			}

			$record = $DB->get_record(SESSION_NAME, array('user_id' => intval($uid)));
			if ($record == null) {
				return;
			}
			
			if (!isset($record->$skey)) {
				$this->_initProfileSession();
			}
			
			$endpointMappings = (array) json_decode($record->$skey);
			$value = ibm_sbt_encrypt($this->key, $value, base64_decode($this->iv));
			
			$endpointMappings[$endpoint] = "$value";
			$record->$skey = json_encode($endpointMappings);
			
			$DB->update_record(SESSION_NAME, $record);
		} catch(Exception $e) {
			syslog(LOG_INFO, $e);
		}
	}
	
	/**
	 * Returns a value from the credentials store
	 * 
	 * @param string $skey
	 * @param string $endpoint
	 * 
	 * @return 
	 */
	private function _get($skey, $endpoint) {
		global $DB;
		global $USER;
		
		$uid = null;
		if (isset($USER->id) && $USER->id != 0) {
			$uid = $USER->id;
		} else if (isset($_GET['uid'])) {
			$uid = $_GET['uid'];
		} else if (isset($_COOKIE['ibm-sbt-uid']) && $_COOKIE['ibm-sbt-uid'] != null) {
			$uid = $_COOKIE['ibm-sbt-uid'];
		} else if (self::$uid != null) {
			$uid = self::$uid;
		} else {
			return null;
		}
	
		$record = $DB->get_record(SESSION_NAME, array('user_id' => intval($uid)));

		if ($record == null || empty($record)) {
			return null;
		}
		
		if (!isset($record->$skey)) {
			$this->_initProfileSession();
		}

		$endpointMappings = (array) json_decode($record->$skey);

		if ($endpointMappings == null) {
			return null;
		}
		
		// Get value, decrypt and return
		if (!isset($endpointMappings[$endpoint])) {
			return null;
		}
		$value = $endpointMappings[$endpoint];
	
		if ($value == "" || $value == null) {
			return null;
		}

		$value = ibm_sbt_decrypt($this->key, $value, base64_decode($this->iv));

		return $value;
	}
	/**
	 * Deletes an entry from the credentials store.
	 *
	 * @param string $key
	 */
	private function _delete($skey, $endpoint) {
		global $DB;
		global $USER;
		
		$uid = null;
		if (isset($USER->id) && $USER->id != 0) {
			$uid = $USER->id;
		} else if (isset($_GET['uid'])) {
			$uid = $_GET['uid'];
		} else if (isset($_COOKIE['ibm-sbt-uid']) && $_COOKIE['ibm-sbt-uid'] != null) {
			$uid = $_COOKIE['ibm-sbt-uid'];
		} else if (self::$uid != null) {
			$uid = self::$uid;
		} else {
			return false;
		}
		
		$record = $DB->get_record(SESSION_NAME, array('user_id' => intval($uid)));
		
		// Make sure that item exists. If not, create session
		if (!isset($record->$skey)) {
			$this->_initProfileSession();
		}
		
		if (!isset($record->$skey)) {
			return;
		}
		
		$endpointMappings = (array) json_decode($record->$skey);
		
		if ($endpointMappings == null) {			
			return false;
		}
		// Delete entry and update
		unset($endpointMappings[$endpoint]);
		
		$record->$skey = json_encode($endpointMappings);
		$DB->update_record(SESSION_NAME, $record);
		return true;
	}
	
	/**
	 * Destroys the credential store (i.e. removes the session from the database).
	 *
	 * @param string $key
	 */
	public function destroyStore($endpoint = "connections") {
		$this->deleteTokens($endpoint);
		$this->deleteBasicAuthCredentials($endpoint);
		$this->deleteOAuthCredentials($endpoint);
	}
	
	public function storeToken($token, $endpoint = "connections") {
		$this->_store(TOKEN, $endpoint, $token);
	}
	
	/**
	 * Stores the OAuth request token in the database.
	 *
	 * @param string $token			The OAuth request token.
	 */
	public function storeRequestToken($token, $endpoint = "connections") {
		$this->_store(REQUEST_TOKEN, $endpoint, $token);
	}
	
	/**
	 * Returns a request token.
	 *
	 * @return string 		A request token.
	 */
	public function getRequestToken($endpoint = "connections") {
		$token = $this->_get(REQUEST_TOKEN, $endpoint);
		return $token;
	}

	/**
	 * Returns a token.
	 *
	 * @param string $endpoint The endpoint associated with this token.
	 * @return string 		A token.
	 */
	public function getToken($endpoint = "connections") {
		return $this->_get(TOKEN, $endpoint);
	}
	
	/**
	 * Deletes the OAuth tokens.
	 * 
	 * @param string $endpoint The endpoint associated with the tokens.
	 */
	public function deleteTokens($endpoint = "connections") {
		$ret1 = $this->_delete(TOKEN, $endpoint);
		$ret2 = $this->_delete(TOKEN_TYPE, $endpoint);
		$ret3 = $this->_delete(OAUTH_TOKEN, $endpoint);
		$ret4 = $this->_delete(OAUTH_TOKEN_SECRET, $endpoint);
		$ret5 = $this->_delete(REQUEST_TOKEN, $endpoint);
		$ret6 = $this->_delete(OAUTH_VERIFIER_TOKEN, $endpoint);
		$ret7 = $this->_delete(OAUTH_REQUEST_TOKEN_SECRET, $endpoint);
		return ($ret1 && $ret2 && $ret3 && $ret4 && $ret5 && $ret6 && $ret7);
	}
	
	/**
	 * Stores the OAuth access token in the database.
	 * 
	 * @param string $token			The OAuth access token.
	 * @param string $endpoint The endpoint associated with this token.
	 */
	public function storeOAuthAccessToken($token, $endpoint = "connections") {
		$this->_store(OAUTH_TOKEN, $endpoint, $token);
	}
	
	
	public function storeTokenSecret($token, $endpoint = "connections") {
		$this->_store(OAUTH_TOKEN_SECRET, $endpoint, $token);
	}
	
	public function storeVerifierToken($token, $endpoint = "connections") {
		$this->_store(OAUTH_VERIFIER_TOKEN, $endpoint, $token);
	}
	
	public function getTokenSecret($endpoint = "connections") {
		return $this->_get(OAUTH_TOKEN_SECRET, $endpoint);
	}
	
	/**
	 * Returns OAuth access token.
	 *
	 * @param string $endpoint The endpoint associated with this token.
	 * @return string 		OAuth access token.
	 */
	public function getOAuthAccessToken($endpoint = "connections") {
		return $this->_get(OAUTH_TOKEN, $endpoint);
	}
	
	/**
	 * Returns the verifier token.
	 *
	 * @return string 		OAuth verifier token.
	 * @param string $endpoint The endpoint associated with this token.
	 */
	public function getVerifierToken($endpoint = "connections") {
		return $this->_get(OAUTH_VERIFIER_TOKEN, $endpoint);
	}
	
	/**
	 * Stores the basic authentication username in the database.
	 *
	 * @param string $username		The username used to perform basic authentication.
	 * @param string $endpoint 
	 */
	public function storeBasicAuthUsername($username, $endpoint = "connections") {
		$this->_store(BASIC_AUTH_USERNAME, $endpoint, $username);
	}
	
	/**
	 * Returns basic auth username.
	 * 
	 * @param string $endpoint 
	 * @return string 		Basic auth username.
	 */
	public function getBasicAuthUsername($endpoint = "connections") {
		return $this->_get(BASIC_AUTH_USERNAME, $endpoint);
	}
	
	/**
	 * Stores the OAuth 1.0 request token secret
	 *
	 * @param string $token		The OAuth 1.0 request token secret
	 */
	public function storeRequestTokenSecret($token, $endpoint = "connections") {
		$this->_store(OAUTH_REQUEST_TOKEN_SECRET, $endpoint, $token);
	}
	
	/**
	 * Returns basic auth username.
	 * 
	 * @param string $endpoint The endpoint associated with this token.
	 * @return string 		
	 */
	public function getRequestTokenSecret($endpoint = "connections") {
		return $this->_get(OAUTH_REQUEST_TOKEN_SECRET, $endpoint);
	}
	
	/**
	 * Stores the basic authentication password in the database.
	 * 
	 * @param string $password		The password used to perform basic authentication.
	 * @param string $endpoint 
	 */
	public function storeBasicAuthPassword($password, $endpoint = "connections") {
		$this->_store(BASIC_AUTH_PASSWORD, $endpoint, $password);
	}
	
	/**
	 * Returns basic auth password.
	 *
	 * @param string $endpoint The endpoint associated with this token.
	 * @return string 		Basic auth password.
	 */
	public function getBasicAuthPassword($endpoint = "connections") {
		return $this->_get(BASIC_AUTH_PASSWORD, $endpoint);
	}
	
	/**
	 * Deletes stored basic authentication credentials.
	 * 
	 * @param string $endpoint The endpoint associated with the credentials to delete.
	 */
	public function deleteBasicAuthCredentials($endpoint = "connections") {
		$ret1 = $this->_delete(BASIC_AUTH_PASSWORD, $endpoint);
		$ret2 = $this->_delete(BASIC_AUTH_USERNAME, $endpoint);
		return ($ret1 && $ret2);
	}
	
	/**
	 * Deletes stored OAuth credentials.
	 * @param string $endpoint The endpoint associated with the credentials to delete.
	 */
	public function deleteOAuthCredentials($endpoint = "connections") {
		$ret1 = $this->_delete(TOKEN_TYPE, $endpoint);
		$ret2 = $this->_delete(OAUTH_TOKEN, $endpoint);
		$ret3 = $this->_delete(OAUTH_TOKEN_SECRET, $endpoint);
		$ret4 = $this->_delete(OAUTH_REQUEST_TOKEN, $endpoint);
		$ret5 = $this->_delete(TOKEN, $endpoint);
		$ret6 = $this->_delete(OAUTH_VERIFIER_TOKEN, $endpoint);
		$ret7 = $this->_delete(OAUTH_REQUEST_TOKEN_SECRET, $endpoint);
		$ret8 = $this->_delete(REQUEST_TOKEN, $endpoint);
		return ($ret1 && $ret2 && $ret3 && $ret4 && $ret5 && $ret6 && $ret7 && $ret8);
	}
	
	public function storeTokenType($tokenType, $endpoint = "connections") {
		$this->_store(TOKEN_TYPE, $endpoint, $tokenType);
	}
	
	public function getTokenType($endpoint = "connections") {
		return $this->_get(TOKEN_TYPE, $endpoint);
	}
	
	
	/**
	 * Generates a random string of given length.
	 *
	 * @param int $length		Desired length of the string.
	 *
	 * @return string			Random string.
	 */
	private function gen_string($length) {
		global $USER;
		if (isset($USER->id) && $USER->id != 0) {
			$str = sha1($USER->id);
		} else if (isset(self::$uid) && self::$uid != null) {
			$str = sha1(self::$uid);
		} else if (isset($_COOKIE['ibm-sbt-uid'])) {
			$str = sha1($_COOKIE['ibm-sbt-uid']);
		} else {
			$str = sha1($_GET['uid']);
		}
		
		return substr($str, 0, $length);
	}
}
