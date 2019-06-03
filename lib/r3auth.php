<?php

/**
 * The main include file for R3Auth package
 *
 * PHP versions 5
 *
 * LICENSE: Commercial
 *
 * @category   Authentication
 * @package    R3Auth
 * @author     Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright  R3 GIS s.r.l.
 * @version    1.2
 * @link       http://www.r3-gis.com
 */
if (defined("__R3_AUTH__"))
    return;
define("__R3_AUTH__", 1);

/**
 * Library version
 */
define('R3AUTH_VERSION', '1.3');

/**
 * Returned if session exceeds idle time
 */
//define('AUTH_IDLED',                    -1);

/**
 * Returned if authentication is OK
 */
define('AUTH_OK', 0);

/**
 * Returned if session has expired
 */
//define('AUTH_EXPIRED',                  -2);
/**
 * Returned if container is unable to authenticate user/password pair
 */
//define('AUTH_WRONG_LOGIN',              -3);
/**
 * Returned if a container method is not supported.
 */
//define('AUTH_METHOD_NOT_SUPPORTED',     -4);
/**
 * Returned if new Advanced security system detects a breach
 */
//define('AUTH_SECURITY_BREACH',          -5);
/**
 * Returned if checkAuthCallback says session should not continue.
 */
//define('AUTH_CALLBACK_ABORT',           -6);


/**
 * Returned if the user is not logged id. Can be returned on cookie or session problems
 */
define('AUTH_NOT_LOGGED_IN', -9);

/**
 * Returned if the account is disabled
 */
define('AUTH_ACCOUNT_DISABLED', -11);

/**
 * Returned if the account start date is in the future
 */
define('AUTH_ACCOUNT_NOT_STARTED', -12);

/**
 * Returned if the account is expired
 */
define('AUTH_ACCOUNT_EXPIRED', -13);

/**
 * Returned if the user IP is allow to connect
 */
define('AUTH_INVALID_IP', -14);

/**
 * Returned if password is expired. Password update is needed.
 * <b>NOTE:</b> performLogin return true to permit the chenge of the password
 */
define('AUTH_PASSWORD_EXPIRED', -15);

/**
 * Returned if the user was disconnected by the administraror
 */
define('AUTH_USER_DISCONNECTED', -16);

/**
 * Returned if the password will expire in few days. Constant has a positiva value => authentication OK
 */
define('AUTH_PASSWORD_IN_EXPIRATION', 11);

/**
 * Returned if the password is to change at first login. Constant has a positiva value => authentication OK
 */
define('AUTH_PASSWORD_REPLACE', 12);

/**
 * Returned if the authentication data is not valid
 */
define('AUTH_INVALID_AUTH_DATA', 13);  // positivo (?)


/**
 * Super user UID
 */
define('SUPERUSER_UID', 0);


class R3AuthInstance {

    static protected $instance;

    static function set(IR3Auth $auth) {
        return self::$instance = $auth;
    }

    /**
     * Return auth object
     * 
     * @return \IR3Auth
     */
    static function get() {
        return self::$instance;
    }

}

/**
 * The R3Auth class provides methods for creating an
 * authentication system using PHP.
 *
 * @category   Authentication
 * @package    R3Auth
 * @author     Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright  R3 GIS s.r.l.
 * @link       http://www.r3-gis.com
 */
require_once 'Auth.php';
require_once 'Log.php';
require_once 'Log/observer.php';
require_once __DIR__ . '/pear/Auth/Container/Auth_Container_PDO.php';

// Definizione eccezioni
class EPermissionDenied extends Exception {
    
}

class EDatabaseError extends Exception {

    private $params;

    public function __construct($message, $code = 0, array $params = array()) {

        parent::__construct($message, $code);
        $this->params = $params;
    }

    final function getParams() {   // Field of the exception
        return $this->params;
    }

}

interface IR3Auth {

    function getUID();

    function isAuth();

    function getDomainID();
}

// Estendo classe di partenza
class R3Auth extends Auth implements IR3Auth
{
    /**
     * All the user permisisons (User + groups) cached
     *
     * @var  array
     * @access private
     */
    public $cachePerm = null; // permessi attivi dell'utente (somma gruppo, utente)

    /**
     * Additional options for the storage container
     *
     * @var  array
     * @access protected
     */
    protected $options = array();

    /**
     * Current domain ID
     *
     * @var  integer
     * @access protected
     */
    protected $domainID = null;

    /**
     * Current application ID
     *
     * @var  integer
     * @access protected
     */
    protected $applicationID = null;

    /**
     * Current application code
     *
     * @var  string
     * @access private
     */
    protected $applicationCode = null;

    /**
     * Current user ID
     *
     * @var  integer
     * @access protected
     */
    protected $UID = null;

    /**
     * Database instance
     *
     * @var \PDO
     */
    protected $db = null;

    /**
     * Don't update the user status
     *
     * @var  boolean
     * @access protected
     * @see updateStatus
     */
    protected $skipUpdateStatus = false;

    /**
     * R3DBIni object
     *
     * @var  object
     * @access protected
     */
    protected $dbini = null;

    /**
     * User information (database record)
     *
     * @var  array
     * @access protected
     */
    private $userInfo = array();

    /**
     * If true the class is destroing itself. This variabled is used to raise an exception (not destroing) or
     * triggen an error (destroing). In PHP it's not possible to raise an exception on destroy.
     *
     * @var  boolean
     * @access protected
     */
    private $isDestroying = false;

    /**
     * If true the isAuth function will ignore expired password
     *
     * @var  boolean
     * @access public
     */
    public $ignoreExpiredPassword = false;

    public $passwordStatus = null;

    protected $domain = null;

    protected $login = null;
    
    protected $lastAction = null;

    /**
     * @var boolean
     * If true the user is logged in as an
     */
    protected $isTrustedAuthentication = true;

    /**
     * Location where to store session parameters for user
     *
     * @var  array
     * @access protected
     */
    protected $sessionParameters = array();

    /**
     * Constructor
     *
     * Set up the storage driver.
     *
     * @param \PDO
     * @return void
     */
    public function __construct(\PDO $db, $options = array(), $application = null, $logger = null)
    {
        $defOpt = array(
           /*// TO DELETE ONCE EVERYTHING IS REPLACED WITH STATIC TEXT
            'settings_table' => 'auth.settings',
            'auth_settings_table' => 'auth.auth_settings',
            'applications_table' => 'auth.applications',
            'users_groups_table' => 'auth.users_groups',
            'users_table' => 'auth.users',
            'domains_table' => 'auth.domains',
            'groups_table' => 'auth.groups',
            'groups_acl_table' => 'auth.groups_acl',
            'users_acl_table' => 'auth.users_acl',
            'users_ip_table' => 'auth.users_ip',
            'domains_applications_table' => 'auth.domains_applications',
            'log_table' => 'auth.logs',
            'domains_name_table' => 'auth.domains_name',
            'acnames_table' => 'auth.acnames',*/
            'table' => 'auth.users',
            'usernamecol' => 'us_login',
            'passwordcol' => 'us_password',
            'cryptType' => 'md5',
            'auto_quote' => false,
            'db_where' => '1=1',
            'enable_logging' => true,
            'log_path' => null,
        );

        $this->db = $db;

        // DEBUG for old table-options... in case there are some left...
        /*if(count($options) > 0 ) {
            foreach($options as $k=>$opt) {
                if(strpos($k, '_table') > 0) {
                    $debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT , 10);
                    foreach($debug as $d) {
                        echo $d['file'] . ":" . $d['function'] . "(...):" . $d['line'] . "\n";
                    }
                    echo "\n\n\n";
                    break;
                }
            }
        }*/

        $options = array_merge($defOpt, $options);
        parent::__construct('', $options, null, false);

        $this->options['options'] = $options;
        $this->application = $application;

        $this->options['options']['expirationTime'] = 0;
        $this->options['options']['idleTime'] = 0;

        //@TODO: tirare fuori info utente
        if ($logger !== null) {
            $this->attachLogObserver($logger);
        }

        $this->post['authsecret'] = false;
        $this->isLoggedIn = false;
        $this->skipUpdateStatus = false;
        $this->allowMultipleApplications = false;

        // if true all the hasPerm will return true
        $this->userIsSuperuser = false;
        $this->isTrustedAuthentication = false;
    }

    function __destruct() {

        $this->log(__METHOD__ . "[".__LINE__."]:----", AUTH_LOG_DEBUG);
    }

    public function getUID() {

        return $this->UID;
    }

    /**
     * Return the groups of the current user
     */
    public function getGroupNames() {
        static $groups = null;/** cache the statement */
        if ($groups === null) {
            $sql = "SELECT gr.gr_id, gr.gr_name 
                    FROM auth.groups gr
                    INNER JOIN auth.users_groups ug ON gr.gr_id=ug.gr_id 
                    WHERE us_id= {$this->UID}";

            $res = $this->db->query($sql);
            $groups = array();
            while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                $groups[$row['gr_id']] = $row['gr_name'];
            }
        }
        return $groups;
    }

    public function getLogin() {

        return $this->login;
    }

    public function getDomainName() {

        return $this->domain;
    }

    public function getApplicationCode() {

        return $this->applicationCode;
    }

    /**
     * Returns the Domain ID
     * @since 0.2b
     *
     * @return integer
     */
    public function getDomainID() {

        return $this->domainID;
    }

    /**
     * Returns the IP Address
     * @since 1.2
     *
     * @return string
     */
    private function getIPAddress() {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            //gethostname() not works in command line!
            return gethostbyname(php_uname('n'));
        }
    }

    /**
     * Set the UID (user ID). Should not be used
     *
     * @param integer         new UID
     * @access public
     */
//    public function setUID($UID) {
//        $this->UID = $UID;
//    }

    public function allowMultipleApplications($allowMultipleApplications) {

        $this->allowMultipleApplications = $allowMultipleApplications;
    }

// Converte una stringa con i parametri in un array
    private function stringToOptions($text) {

        $result = array();
        $a = explode("\n", $text);
        foreach ($a as $value) {
            if ($value == '' || $value[0] == ';' || $value[0] == '#') {
                continue;
            }
            if (($p = strpos($value, '=')) === null) {
                $result[trim($value)] = null;
            } else {
                $val = trim(substr($value, $p + 1));
                if (strtoupper($val) == 'FALSE') {
                    $val = false;
                } else if (strtoupper($val) == 'TRUE') {
                    $val = true;
                }
                $result[trim(substr($value, 0, $p))] = $val;
            }
        }
        return $result;
    }

    /**
     * Assign data from login form to internal values
     *
     * This function takes the values for username and password
     * from $HTTP_POST_VARS/$_POST and assigns them to internal variables.
     * If you wish to use another source apart from $HTTP_POST_VARS/$_POST,
     * you have to derive this function.
     *
     * @global $HTTP_POST_VARS, $_POST
     * @see    Auth
     * @return void
     * @access private
     */
    function assignData() {
        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        $this->post[$this->_postUsername] = $this->username;
        $this->post[$this->_postPassword] = $this->password;
    }

    /**
     * Return true if the given IP address is included in the given IP/MASK
     *
     * @param string    IP address to check
     * @param string    IP or IP/MASK (default mask 255.255.255.255)
     * @return boolean  Return true if the IP address is valid
     * @access private
     */
    private function isValidIP($ip, $validIP, $validMask) {

        if ($ValidMask == '') {
            $ValidMask = '255.255.255.255';
        }
        return (ip2long($ip) & ip2long($validMask)) == (ip2long($validIP) & ip2long($validMask));
    }

    /**
     * Perform a trust login as the given user without password
     *
     * On success the user session data is stored to mantain the authentication valid
     *
     * @param string    user login (mandatory)
     * @param string    domain (optional)
     * @return boolean  Return true if successfully logged in
     * @access public
     */
    public function performTrustLoginAsUser($login, $domain = null) {
        //$this->session['trust_user'] = true;
        $this->isTrustedAuthentication = true;
        $this->log(__METHOD__ . "[".__LINE__."]: logging in as user {$login} in trusted mode.", AUTH_LOG_INFO);
        return $this->performLogin($login, null, $domain);
    }

    /**
     * try to login an user
     *
     * On success the user session data is stored to mantain the authentication valid
     *
     * @param string    user login (mandatory)
     * @param string    user password
     * @param string    domain (optional)
     * @return boolean  Return true if successfully logged in
     * @access public
     */
    public function performLogin($login, $password, $domain = null) {
        if (isset($this->session['login']) && $this->session['login'] !== trim($login)) {
            $this->log(__METHOD__ . "[".__LINE__."]: isAuth TRUE but ({$this->session['login']} is different from {$login}).", AUTH_LOG_DEBUG);
            $this->status = AUTH_WRONG_LOGIN;
            return false;
        }
        $this->log(__METHOD__ . "[".__LINE__."]:({$login}, ***, {$domain}) called.", AUTH_LOG_DEBUG);

        if (!$this->skipUpdateStatus) {
            if (isset($this->options['options']['enable_logging']) && $this->options['options']['enable_logging'] == true) {
                $this->log(__METHOD__ . "[".__LINE__."]: Clearing old logs entry", AUTH_LOG_DEBUG);
                if (isset($this->options['options']['access_log_lifetime']) &&
                        $this->options['options']['access_log_lifetime'] > 0) {
                    $sql = "DELETE FROM auth.logs WHERE log_auth_type='N' AND log_time < ? ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(array(date('Y-m-d H:i:s', time() - $this->options['options']['access_log_lifetime'])));
                }
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] > 0) {
                    $sql = "DELETE FROM auth.logs WHERE log_auth_type IN ('I', 'O', 'X') AND log_auth_type<>'U' AND log_time < ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(array(date('Y-m-d H:i:s', time() - $this->options['options']['login_log_lifetime'])));
                }
            }
        }

// doppia assegnazione serve per le sostituzioni
        $this->username = $this->login = trim($login);
        $this->password = trim($password);
        $this->domain = trim($domain);
        //echo "[$this->domain]";
        //$this->loginDomain = trim($domain);
        $this->lastAction = null;  //Needed for disconnect

        $sql = "
SELECT us.*, d.do_auth_type, d.do_auth_data, dn.dn_type, app.app_id, app.app_code, app.app_name
FROM auth.users us
LEFT JOIN auth.domains d ON us.do_id=d.do_id
LEFT JOIN auth.domains_name dn ON d.do_id=dn.do_id
LEFT JOIN auth.domains_applications da ON d.do_id=da.do_id
LEFT JOIN auth.applications app ON da.app_id=app.app_id
WHERE UPPER(us.us_login)=UPPER(" . $this->db->quote($this->username) . ") AND dn.dn_name=" . $this->db->quote($this->domain) . " AND us_status<>'X' ";
        if (!$this->allowMultipleApplications) {
            $sql .= "  AND app_code=" . $this->db->quote($this->application) . " \n ";
        }
        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
        $res = $this->db->query($sql);

        $this->log(__METHOD__ . "[".__LINE__."]: executing: {$sql}", AUTH_LOG_DEBUG);

        $i = 0;
        $userInfo = array();
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $userRow = $row;

            // Replace login insert by user with database login (needed by pear)
            $this->username = $this->login = trim($row['us_login']);

            $this->userIsSuperuser = isset($userRow['us_is_superuser']) && strtoupper($userRow['us_is_superuser']) == 'T';
            $i++;
            foreach ($row as $key => $value) {
                if ($key == 'us_id') {
                    $UID = $value;
                } else if ($key == 'us_login') {
                    $Login = $value;
                } else if ($key == 'do_auth_type') {
                    $do_auth_type = $value;
                } else if ($key == 'do_auth_data') {
                    $do_auth_data = $value;
                }
                $userInfo[$key] = $value;
            }
            if ($row['dn_type'] != 'N') {
                /** get the real domain name */
                $sql2 = "SELECT dn_name FROM auth.domains_name WHERE do_id={$row['do_id']} AND dn_type='N'";
                $res2 = $this->db->query($sql2);
                $row2 = $res2->fetch(\PDO::FETCH_ASSOC);
                $this->domain = $row2['dn_name'];
            }

            $this->UID = $row['us_id'];
            $this->applicationID = $row['app_id'];
            $this->applicationCode = $row['app_code'];

            $this->domainID = $row['do_id'];
            $this->auth_type = $do_auth_type;
            $this->auth_data = $do_auth_data;
            $this->lastAction = $row['us_last_action'];
        }
        
        if ($i == 0) {
            $this->log(__METHOD__ . "[".__LINE__."]: No user found.", AUTH_LOG_INFO);
            /* SS: Ricavo domain id e application per log */
            $sql = "SELECT d.do_id 
                    FROM auth.domains d
                    INNER JOIN auth.domains_name dn ON d.do_id=dn.do_id
                    WHERE dn_name=" . $this->db->quote($domain);
            $domainId = $this->db->query($sql)->fetchColumn(0);
            if ($domainId !== false) {
                $this->domainID = $domainId;
            }

            $sql = "SELECT app_id 
                    FROM auth.applications
                    WHERE app_code=" . $this->db->quote($this->application);
            $this->applicationID = $this->db->query($sql)->fetchColumn(0);

            if (isset($this->options['options']['login_log_lifetime']) &&
                    $this->options['options']['login_log_lifetime'] <> 0) {
                $this->internalDBLog(LOG_ERR, 'I', 'User "' . $this->username . '" not found');
            }

            $this->status = AUTH_WRONG_LOGIN;
            $this->doLogout();  //SS: Serve perche' questa funzione e' chiamata da isAuth!
            return false;
        }

        if (!$this->allowMultipleApplications && $i > 1) {
            // too much user. Shound be never here!
            $this->log(__METHOD__ . "[".__LINE__."]: Too much users.", AUTH_LOG_ERR);
            if (isset($this->options['options']['login_log_lifetime']) &&
                    $this->options['options']['login_log_lifetime'] <> 0) {
                $this->internalDBLog(LOG_ERR, 'I', 'Too much users');
            }
            throw new Exception('Too much users');
        }

        if (!in_array($do_auth_type, array('DB', 'POP3', 'IMAP', 'LDAP'))) {
            throw new Exception("Invalid auth_type ({$do_auth_type})");
        }
        $this->userInfo = $userInfo;
		
        // LDAP authentication
        $sql = "SELECT as_type, as_data, as_change_password
                FROM auth.users us
                INNER JOIN auth.domains_name dn USING (do_id)
                INNER JOIN auth.auth_settings USING (as_id)
                WHERE UPPER(us_login)=UPPER(" . $this->db->quote($this->login) . ") AND dn_name=" . $this->db->quote($this->domain);
        $this->log(__METHOD__ . "[".__LINE__."]: executing: {$sql}", AUTH_LOG_DEBUG);
        $res = $this->db->query($sql);
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $do_auth_type = $row['as_type'];
            $do_auth_data = $row['as_data'];
            $this->ignoreExpiredPassword = true; // $row['as_change_password'];
        }

        $this->log(__METHOD__ . "[".__LINE__."]: User $this->username found.", AUTH_LOG_INFO);
        $options = $this->stringToOptions($do_auth_data);

        if (!$this->isTrustedAuthentication) {
            $this->log(__METHOD__ . "[".__LINE__."]: Authentication method: $do_auth_type.", AUTH_LOG_DEBUG);
            // check for authentication type and choose the right storage driver
            if ($do_auth_type == 'DB') {
                if (isset($this->options['options']['cryptType'])) {
                    $options['cryptType'] = $this->options['options']['cryptType'];
                } else {
                    $options['cryptType'] = 'md5';
                }
                $options['db_where'] = "do_id={$userInfo['do_id']} AND us_status<>'X'";
                $do_auth_type = new Auth_Container_PDO($this->db, $options);
            } else if ($do_auth_type == 'POP3' || $do_auth_type == 'IMAP') {
                if ($options['username'] != '') {
                    $this->username = str_replace('<username>', $this->username, $options['username']);
                }
                if ($options['password'] != '') {
                    $this->password = str_replace('<password>', $this->password, $options['password']);
                }
            }
        }

        //Salvo parametri sessione
        $this->session['_storage_driver'] = $do_auth_type;
        $this->session['_storage_options'] = $options;

        if ($this->options['options']['idleTime'] > 0) {
            $this->setIdle($this->options['options']['idleTime']);
        }
        if ($this->_isAuth()) {
            $this->setAllowLogin(false);

            if ($this->options['options']['expirationTime'] == '')
                $this->options['options']['expirationTime'] == 0;  // ricarica ogni volta. Se < 0 non ricarica mai

            if ($this->options['options']['expirationTime'] >= 0) {
//SS: su sessioni a tempo limitato devo salvare anche nome utente e dominio per poter rieffettuare il login. la login � gi� salvata
                $this->session['login'] = $this->login;
                $this->session['password'] = $this->password;
                $this->session['domain'] = $this->domain;

                // SS: Needed for expired password
                $this->session['last_UID'] = $this->UID;
                $this->session['last_applicationID'] = $this->applicationID;
                $this->session['last_applicationCode'] = $this->applicationCode;
            }

            $this->status = AUTH_OK;
            $ipAddr = array();
            if ($this->isSuperuser()) {
                $userRow['us_status'] = 'E';
                $userRow['us_start_date'] = '';
                $userRow['us_expire_date'] = '';
                $userRow['us_pw_expire'] = '180';
                $userRow['us_pw_expire_alert'] = '60';
            } else {
                /** Get the valid IP addresses */
                $sql = "SELECT ip_addr, ip_mask, ip_kind 
                        FROM auth.users_ip
                        WHERE (app_id IS NULL OR app_id={$this->applicationID}) AND 
                              (us_id IS NULL OR us_id={$this->UID})
                        ORDER BY ip_order";
// echo nl2br(str_replace(' ', '&nbsp;', $sql));
                $res = $this->db->query($sql);
// $this->log("R3Auth::performLogin() executing: $sql", AUTH_LOG_DEBUG);
                while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                    $ipAddr[] = $row;
                }
            }

            /** Check for account status */
            if ($userRow['us_status'] != 'E') {
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_ERR, 'I', 'Account disabled');
                }
                $this->status = AUTH_ACCOUNT_DISABLED;
                $this->log(__METHOD__ . "[".__LINE__."]: User {$this->username} disabled.", AUTH_LOG_INFO);
                $this->doLogout();
                return false;
            }

            /** Check for account start date */
            if ($userRow['us_start_date'] != '' && $userRow['us_start_date'] > date('Y-m-d')) {
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_ERR, 'I', 'Account not started');
                }
                $this->status = AUTH_ACCOUNT_NOT_STARTED;
                $this->log(__METHOD__ . "[".__LINE__."]: The account of the user {$this->username} is not started.", AUTH_LOG_INFO);
                $this->doLogout();
                return false;
            }

            /** Check for account expiration date */
            if ($userRow['us_expire_date'] != '' && $userRow['us_expire_date'] < date('Y-m-d')) {
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_ERR, 'I', 'Account expired');
                }
                $this->status = AUTH_ACCOUNT_EXPIRED;
                $this->log(__METHOD__ . "[".__LINE__."]: The account of the user {$this->username} is expired.", AUTH_LOG_INFO);
                $this->doLogout();
                return false;
            }

            /** Check for valid IP address */
            if (count($ipAddr) > 0) {
                $allow = false;
                foreach ($ipAddr as $ip_mask) {
                    if ($this->IsValidIP($this->getIPAddress(), $ip_mask['ip_addr'], $ip_mask['ip_mask'])) {
                        $allow = ($ip_mask['ip_kind'] == 'A');
                    }
                }
                if (!$allow) {
                    if (isset($this->options['options']['login_log_lifetime']) &&
                            $this->options['options']['login_log_lifetime'] <> 0) {
                        $this->internalDBLog(LOG_ERR, 'I', 'Unauthorized IP address'); /* The IP is in the log data */
                    }
                    $this->status = AUTH_INVALID_IP;
                    $this->log(__METHOD__ . "[".__LINE__."]: Invalid IP address [" . $this->getIPAddress() . "] for user $this->username.", AUTH_LOG_INFO);
                    $this->doLogout();
                    return false;
                }
            }

            /** Check for password expiration date */
            $this->passwordStatus = 0;
            if ($userRow['us_pw_last_change'] != '' && $userRow['us_pw_expire'] != '') {
                $last_pw_change = mktime(0, 0, 0, substr($userRow['us_pw_last_change'], 5, 2), substr($userRow['us_pw_last_change'], 8, 2), substr($userRow['us_pw_last_change'], 0, 4), -1);
                $last_pw_change_days = ceil((time() - $last_pw_change) / (24 * 60 * 60)) - 1;
                $dd = $userRow['us_pw_expire'] - $last_pw_change_days;
                $this->log(__METHOD__ . "[".__LINE__."]: DD-Value = {$dd}", AUTH_LOG_INFO);
                if ($dd < 0) {
                    /** Password already expired. Return the expiration time in days */
                    if (isset($this->options['options']['login_log_lifetime']) &&
                            $this->options['options']['login_log_lifetime'] <> 0) {
                        $this->internalDBLog(LOG_NOTICE, 'I', 'Password expired');
                    }
                    $this->passwordStatus = $dd;
                    $this->status = AUTH_PASSWORD_EXPIRED;
                    $this->log(__METHOD__ . "[".__LINE__."]: Password for user {$this->username} expired.", AUTH_LOG_INFO);
                } else if ($dd < $userRow['us_pw_expire_alert']) {
                    /** Password is expiring. Return the left days to the expiration date */
                    if (isset($this->options['options']['login_log_lifetime']) &&
                            $this->options['options']['login_log_lifetime'] <> 0) {
                        $this->internalDBLog(LOG_INFO, 'I', 'Password in expiration');
                    }
                    $this->passwordStatus = $dd + 1;
                    $this->status = AUTH_PASSWORD_IN_EXPIRATION;
                    $this->log(__METHOD__ . "[".__LINE__."]: Password for user {$this->username} is expiring.", AUTH_LOG_INFO);
                } else {
                    $this->passwordStatus = $dd + 1;
                }
            } else {
                $dd = 0;
            }

            if (!$this->skipUpdateStatus) {
                /* log only if login. This function is also called internally */
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_INFO, 'I', 'User "' . $this->username . '" logged in');
                }
            }

            /** Password change forced (first login) */
            if (!$this->ignoreExpiredPassword && $userRow['us_pw_last_change'] == '') {
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_NOTICE, 'I', 'Password change forced');
                }
                $this->status = AUTH_PASSWORD_REPLACE;
                $this->passwordStatus = -1;
                $this->log(__METHOD__ . "[".__LINE__."]: Password for user {$this->username} must be changed at first login.", AUTH_LOG_INFO);
            }

            /** Store the password status */
            $this->session['passwordStatus'] = $this->passwordStatus;

            $this->isLoggedIn = true;
            if (!$this->skipUpdateStatus) {
                $this->updateStatus(true);
            }
            return true;
        }
        if (isset($this->options['options']['login_log_lifetime']) &&
                $this->options['options']['login_log_lifetime'] <> 0) {
            $this->internalDBLog(LOG_ERR, 'I', 'Invalid password for user "' . $this->username . '"');
        }
        
        /** Check for start / expiration data */
//SS: TODO:
// verificare indirizzi IP validi
// verificare password scaduta
// verificare alert password scaduta
// verificare forzatura cambio password
        return false;
    }

// reimposto i parametri di connessione
    public function start() {

        if($this->isTrustedAuthentication) {
            $this->log(__METHOD__ . "[".__LINE__."]: called for trust authentication.", AUTH_LOG_INFO, true);
            return true;
        }
        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG, true);

        if (!is_object($this->session['_storage_driver']) && $this->session['_storage_driver'] == '') {
            $this->log(__METHOD__ . "[".__LINE__."]: faild: No storage defined.", AUTH_LOG_DEBUG);
            return false;  // SS: evita che parta l'autenticazione senza sapere il modo di autneticazione (che � nel db e viene caricato dopo il primo login)
        }
        if (is_object($this->session['_storage_driver'])) {
            $storeDriverClassName = get_class($this->session['_storage_driver']);
        } else {
            $storeDriverClassName = $this->session['_storage_driver'];
        }
        $this->log(__METHOD__ . "[".__LINE__."]: Storage driver: {$storeDriverClassName}", AUTH_LOG_DEBUG);

        if ($this->options['options']['idleTime'] > 0) {
            $this->setIdle($this->options['options']['idleTime']);
        }

        if (is_object($this->session['_storage_driver'])) {
            $this->storage = $this->session['_storage_driver'];
        } else {
            $this->storage_driver = $this->session['_storage_driver'];
            $this->storage_options = & $this->session['_storage_options'];
        }
        parent::start();
        return true;
    }

    /**
     * logout the user
     *
     * @return boolean   Return true on success. False is returned if the user was not logged in
     * @access public
     */
    public function dologout() {

        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        $this->updateStatus(false, true);

        $this->domainID = null;
        $this->applicationID = null;
        $this->applicationCode = null;
        $this->UID = null;

        $this->isLoggedIn = false;

        return parent::logout();
    }

    /**
     * logout the user
     *
     * @return boolean   Return true on success. False is returned if the user was not logged in
     * @access public
     */
    public function logout() {

        if (isset($this->options['options']['login_log_lifetime']) &&
                $this->options['options']['login_log_lifetime'] <> 0) {
            $this->internalDBLog(LOG_INFO, 'O', 'Logged out');
        }
        $result = $this->dologout();
        session_unset();
        session_destroy();
        return $result;
    }

    /**
     * Check if the user is authenticated
     *
     * @return boolean   Return true if the user is authenticated and the authentication state is valid
     *                   Return FALSE if the password is expired!
     * @access public
     */
    private function _isAuth() {

        if($this->isTrustedAuthentication) {
            $this->log(__METHOD__ . "[".__LINE__."]: called for trust authentication.", AUTH_LOG_INFO, true);
            return true;
        }
        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG, true);
        if (!$this->start()) {
            return false;
        }
        $checkAuthResult = $this->checkAuth();
        $allowLoginResult = $this->allowLogin;
        if (!$checkAuthResult) {
            $this->log(__METHOD__ . "[".__LINE__."]: parent::checkAuth() return FALSE", AUTH_LOG_DEBUG);
        }
        if (!$this->allowLogin) {
            $this->log(__METHOD__ . "[".__LINE__."]: allowLogin IS FALSE", AUTH_LOG_DEBUG);
        }
        return ($checkAuthResult && $allowLoginResult);
    }

    //SS: OVERRIDE THE ORIGINAL FUNCTION TO FIX THE session_regenerate_id PROBLEM
    function setAuth($username) {
        // $oldregenerateSessionId = $this->regenerateSessionId;
        $this->regenerateSessionId = true;
        parent::setAuth($username);
        $this->regenerateSessionId = false; //$oldregenerateSessionId;
    }

    public function isAuth() {

        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        if (!session_id()) {
            session_start();
        }
        if ($this->isLoggedIn) {
            $this->log(__METHOD__ . "[".__LINE__."]: Preview call was OK, return OK again", AUTH_LOG_DEBUG, true);
            return true;
        }
        if (!isset($this->session['login'])) {
            /* SS: Ricavo domain id e application per log */
            $sql = "SELECT d.do_id FROM auth.domains d
                    INNER JOIN auth.domains_name dn ON d.do_id=dn.do_id
                    WHERE dn_name=" . $this->db->quote($this->domainID);  // SS: BUG!!!
            //echo $sql;
            //die();
            $domainId = $this->db->query($sql)->fetchColumn(0);
            if ($domainId !== false) {
                $this->domainID = $domainId;
            }

            $sql = "SELECT app_id 
                    FROM auth.applications
                    WHERE app_code=" . $this->db->quote($this->application);
            $this->applicationID = $this->db->query($sql)->fetchColumn(0);
            if (isset($this->options['options']['login_log_lifetime']) &&
                    $this->options['options']['login_log_lifetime'] <> 0) {
                $this->internalDBLog(LOG_ERR, 'X', 'User not logged in');
            }
            $this->log(__METHOD__ . "[".__LINE__."]: Not logged in or invalid session data", AUTH_LOG_DEBUG);
            $this->status = AUTH_NOT_LOGGED_IN;
            $this->doLogout();
            return false;
        }

        $this->passwordStatus = $this->session['passwordStatus'];
        if (!$this->ignoreExpiredPassword && $this->passwordStatus < 0) {
            if (isset($this->options['options']['login_log_lifetime']) &&
                    $this->options['options']['login_log_lifetime'] <> 0) {
                $this->internalDBLog(LOG_ERR, 'X', 'Password expired');
            }
            $this->log(__METHOD__ . "[".__LINE__."]: Password expired", AUTH_LOG_DEBUG);
            $this->status = AUTH_PASSWORD_EXPIRED;

            // SS: Added for multi domain: keep login data on password expiring  aaaaa
            $this->UID = $this->session['last_UID'];
            $this->applicationID = $this->session['last_applicationID'];
            $this->applicationCode = $this->session['last_applicationCode'];
            $this->login = $this->session['login'];
            $this->username = $this->login;
            $this->password = $this->session['password'];
            $this->domain = $this->session['domain'];
            $sql = "SELECT d.do_id FROM auth.domains d
                    INNER JOIN auth.domains_name dn ON d.do_id=dn.do_id 
                    WHERE dn_name=" . $this->db->quote($this->domain);  // SS: BUG!!!
            $this->domainID = $this->db->query($sql)->fetchColumn(0);
            return false;
        }

        if (($this->options['options']['expirationTime'] == 0) ||
                ($this->options['options']['expirationTime'] > 0
                && isset($this->session['timestamp'])
                && ($this->session['timestamp'] + $this->options['options']['expirationTime']) < time())) {
            $this->log(__METHOD__ . "[".__LINE__."]: Session time end. Login required", AUTH_LOG_DEBUG);
            $this->skipUpdateStatus = true;

            $result = $this->performLogin($this->session['login'], $this->session['password'], $this->session['domain']);
            $this->skipUpdateStatus = false;

            /** Check for disconnection (us_last_action is null) */
            if ($this->lastAction == '') {
                if (isset($this->options['options']['login_log_lifetime']) &&
                        $this->options['options']['login_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_ERR, 'X', 'User disconnected by administrator');
                }
                $this->log(__METHOD__ . "[".__LINE__."]: User disconnected", AUTH_LOG_DEBUG);
                $this->status = AUTH_USER_DISCONNECTED;
                $this->doLogout();
                return false;
            }

            if ($result) {
                //$this->regenerateSessionId = true;
                $this->setAuth($this->session['login']);
                //$this->regenerateSessionId = false;
            } else {
                $this->doLogout();
            }
            $this->isLoggedIn = $result;
            if ($result) {
                /** restore the old password status */
                $this->passwordStatus = $this->session['passwordStatus'];
            }

            if ($result) {
                if (isset($this->options['options']['access_log_lifetime']) &&
                        $this->options['options']['access_log_lifetime'] <> 0) {
                    $this->internalDBLog(LOG_INFO, 'N', null);
                }
                $this->updateStatus();
            }
            return $result;
        }

//		$auth_options['expirationTime']*/
        $result = $this->_isAuth();

        $this->isLoggedIn = $result;
        if ($result) {
            if (isset($this->options['options']['access_log_lifetime']) &&
                    $this->options['options']['access_log_lifetime'] <> 0) {
                $this->internalDBLog(LOG_INFO, 'N', null);
            }
            $this->updateStatus();
        }
        return $result;
    }

    private function updateStatus($isLogin = false, $isLogout = false) {
        if ($this->UID === null || $this->isTrustedAuthentication) {
            return;
        }

        // update IP-Address
        $sql = "UPDATE auth.users SET
                us_last_ip = " . $this->db->quote($this->getIPAddress());


        $more_where = array();
        if ($isLogout) {
            $sql .= ",\n  us_last_action = NULL ";
            $more_where[] = "1=1";
        } else {
            $sql .= ",\n  us_last_action = CURRENT_TIMESTAMP ";
            if ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) != 'oci' && isset($this->options['options']['update_status_skip_time'])) {
                $more_where[] = "AGE(CURRENT_TIMESTAMP, us_last_action) > '" . $this->options['options']['update_status_skip_time'] . " seconds'";
            } else {
                $more_where[] = "1=1";
            }
        }
        if ($isLogin) {
            $sql .= ",\n  us_last_login = CURRENT_TIMESTAMP ";
            $more_where[] = "1=1";
        }
        $sql .= "WHERE \n" .
                "  us_id=" . $this->UID . " AND ";
        $sql .= "  (us_last_ip <> " . $this->db->quote($this->getIPAddress()) . " OR ";
        $sql .= "  " . implode(' OR ', $more_where) . ")";

        $start = microtime(true);
// echo nl2br($sql);

        $this->log(__METHOD__ . "[".__LINE__."]: $sql", AUTH_LOG_DEBUG);
        $this->db->exec($sql);
// echo "<br />\nTIME=" . sprintf("%.2f", (microtime(true) - $start));
    }

    private function internalDBLog($log_type, $log_auth_type, $log_text) {

        if (isset($this->options['options']['enable_logging']) && $this->options['options']['enable_logging'] == true) {
            if (is_string($log_type)) {
                $log_type = strToupper(substr($log_type, 0, 1));
            }
            switch ($log_type) {
                case LOG_CRIT:
                case 'C':
                    $log_type = 'C';
                    break;
                case LOG_WARNING:
                case 'W':
                    $log_type = 'W';
                    break;
                case LOG_NOTICE:
                case 'N':
                    $log_type = 'N';
                    break;
                case LOG_INFO:
                case 'I':
                    $log_type = 'I';
                    break;
                case LOG_DEBUG:
                case 'D':
                    $log_type = 'D';
                    break;
                default:
                    $log_type = 'E';
            }

            $log_page = $_SERVER['SCRIPT_FILENAME'];
            $root = $_SERVER['DOCUMENT_ROOT'];
            if ($root <> '' && $root[strlen($root) - 1] <> '/') {
                $root = $root . '/';
            }

            if (!empty($root) && strpos($log_page, $root) == 0) {
                $log_page = './' . substr($log_page, strlen($root));
            }
            if (strlen($log_page) > 80) {
                $log_page = '...' . substr($log_page, -77);
            }

            // TODO: prepare statement
            $sql = 'INSERT INTO auth.logs (do_id, app_id, us_id, log_type,' .
                    ' log_auth_type, log_time, log_ip, log_page, log_text) ' .
                    ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                $this->domainID,
                $this->applicationID,
                $this->UID,
                $log_type,
                $log_auth_type,
                date('Y-m-d H:i:s'),
                $this->getIPAddress(),
                $log_page,
                $log_text,
            ));

            return true;
        }
        return false; /* No log table defined */
    }

    /**
     * Text-Log to write into database. Requires "log_table" as authentication option.
     *
     * @param log_type ['CRITICAL' or LOG_CRIT, 'ERROR' or LOG_ERR, 'WARNING' or LOG_WARNING, 'NOTICE' or LOG_NOTICE, 'INFO' or LOG_INFO, 'DEBUG' or LOG_DEBUG]
     * @param log_text
     * @return boolean if log-entry was successfully (true) or not (false)
     */
    public function dblog($log_type, $log_text) {
        return $this->internalDBLog($log_type, null, $log_text);
    }

//SS: temporaneo
    public function log($message, $level = AUTH_LOG_DEBUG, $debug_backtrace = false) {

        if (isset($this->options['options']['log_path']) && $this->options['options']['log_path'] <> '') {
            $filename = $this->options['options']['log_path'] . '/';
            if (defined('DOMAIN_NAME')) {
                $filename .= strToLower(DOMAIN_NAME) . '_';
            }
            if (defined('APPLICATION_CODE')) {
                $filename .= strToLower(APPLICATION_CODE) . '_';
            }
            $filename .= 'auth.log';
            if ($message == '----') {
                file_put_contents($filename, "---\n", FILE_APPEND);
            } else {
                file_put_contents($filename, basename($_SERVER['PHP_SELF']) . "[$level]: " . $message . "\n", FILE_APPEND);
            }
            
            if ($debug_backtrace) {
                $trace = debug_backtrace();
                $caller = array_shift($trace); 
                $function_name = $caller['function']; 
                file_put_contents($filename, sprintf('%s: Called from %s:%s', $function_name, $caller['file'], $caller['line'])."\n", FILE_APPEND);
                foreach ($trace as $entry_id => $entry) { 
                    $entry['file'] = $entry['file'] ? : '-'; 
                    $entry['line'] = $entry['line'] ? : '-'; 
                    if (empty($entry['class'])) { 
                        file_put_contents($filename, sprintf('%s %3s. %s() %s:%s', $function_name, $entry_id + 1, $entry['function'], $entry['file'], $entry['line'])."\n", FILE_APPEND);
                    } else { 
                        file_put_contents($filename, sprintf('%s %3s. %s->%s() %s:%s', $function_name, $entry_id + 1, $entry['class'], $entry['function'], $entry['file'], $entry['line'])."\n", FILE_APPEND);
                    } 
                }
            }
        }

        parent::log($message, $level);
    }

    /**
     * Get the last user status (eg: 0 ok, -1 password expired...)
     *
     * @return integer   Return the last status
     * @access public
     */
    public function getStatus() {

        $res = parent::getStatus();
        if ($res == AUTH_OK) {
            $res = $this->status;
        }
        return $res;
    }

    /**
     * Get the last user status as a stirng (eg: AUTH00000)
     *
     * @return string   Return the last status
     * @access public
     */
    public function getStatusText() {

        return sprintf('AUTH%05d', $this->getStatus());
    }

    /**
     * Get the user status as a text loaded from external file r3auth_text.php
     *
     * @return string   Return the status message
     * @access public
     */
    public function getStatusMessage($statusCode) {
        static $statusText = null;

        if ($statusText === null) {
            $statusText = array();
            $fileName = dirname(__FILE__) . '/r3auth_text.php';
            if (file_exists($fileName)) {
                include $fileName;
            }
        }
        if (isset($statusText[$statusCode])) {
            return $statusText[$statusCode];
        }
        return sprintf('Error #%d', $statusCode);
    }

    function setIdleTime($time) {
        $this->options['options']['idleTime'] = $time;
    }
    
    /**
     * Carica dal db tutte le permission
     * se setID è impostato restituisce l'ID della banca dati al posto di true
     *
     * @param type $app_id
     * @param type $UID
     * @param type $setID
     * @return boolean
     */
    protected function doLoadPermission($app_id, $UID, $setID = false) {
        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        
        $sql = "SELECT DISTINCT 
                auth.groups_acl.ac_id as id, 
                ac_verb as verb, ac_name as name, ga_kind as kind, ac_order as ordr
                FROM  auth.groups_acl
                INNER JOIN auth.users_groups ON auth.groups_acl.gr_id = auth.users_groups.gr_id
                INNER JOIN auth.acnames ON auth.groups_acl.ac_id = auth.acnames.ac_id
                WHERE us_id = ? AND ac_active = 'T' AND app_id = ?
                
                UNION
                
                SELECT DISTINCT
                auth.users_acl.ac_id as id, 
                ac_verb as verb, ac_name as name, ua_kind as kind, ac_order as ordr
                FROM auth.users_acl
                INNER JOIN auth.acnames ON auth.users_acl.ac_id = auth.acnames.ac_id
                WHERE us_id = ? AND ac_active = 'T' AND app_id = ? 
                
                ORDER BY ordr, verb, name";
//echo nl2br(str_replace(' ', '&nbsp;', $sql));
//$res = $this->db->query($sql);

        $sth = $this->db->prepare($sql);
        $this->log(__METHOD__ . "[".__LINE__."]: executing: $sql", AUTH_LOG_DEBUG);
        
// echo "[$UID, $app_id, $UID, $app_id]";
        // die();
        $sth->execute(array($UID, $app_id, $UID, $app_id));

        $result = array();
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['kind'] == 'A') {  //SS: rimuovere kind='T'
                if ($setID) {
                    $result[$row['verb']][$row['name']] = $row['id'];
                } else {
                    $result[$row['verb']][$row['name']] = true;
                }
            } else {
                if (isset($result[$row['verb']][$row['name']])) {
                    unset($result[$row['verb']][$row['name']]);
                }
            }
        }
        
        return $result;
    }

    private function loadPermission($forceReload = false) {

        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        //SS: Forse salvare nella sessione le info
        if ($this->cachePerm === null) {
            $this->cachePerm = $this->doLoadPermission($this->applicationID, $this->UID);
        }
    }

// Restituisce un array con tutte le permission dell'utente autenticato
    public function getAllPerms() {

        $this->loadPermission();
        return $this->cachePerm;
    }

// Restituisce un array con tutte le permission dell'utente autenticato
    function getAllPermsAsString($prefix = 'USER_CAN_', $separator = '_') {

        $this->loadPermission();
        $result = array();

        foreach ($this->cachePerm as $key1 => $value1) {
            foreach ($value1 as $key2 => $value2) {
                $result[] = $prefix . $key1 . $separator . $key2;
            }
        }

        return $result;
    }

// Restituisce un array con tutte le permission dell'utente autenticato
    function hasPerm($verb, $name) {

        if ($this->isSuperuser()) {
            /** Superuser has all permission */
            return true;
        }

        if ($this->passwordStatus < 0) {
            /** Privileges to set if the password expire */
            $this->cachePerm['USE']['APPLICATION'] = true;
        } else {
            $this->loadPermission();
        }


        if (isset($this->options['options']['acnames_upper']) && $this->options['options']['acnames_upper'] == true) {
            $verb = strToUpper($verb);
            $name = strToUpper($name);
        }
        return isset($this->cachePerm[$verb][$name]);
    }
    
    /**
     * Restituisce true se l'utente è superuser (UID=0)
     *
     * @return boolean
     */
    public function isSuperuser() {
        if ($this->userIsSuperuser) {
            return true;
        }
        return ($this->UID != '' && $this->UID == SUPERUSER_UID);
    }

    private function loadConfig() {

        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        if ($this->dbini === null) {
            if (!isset($this->applicationCode)) {
                $this->applicationCode = 'MANAGER';
            }
            $this->dbini = new R3DBIni($this->db, $this->options['options'], $this->domain, $this->applicationCode, $this->login);
        }
    }

    public function reloadConfig() {

        $this->log(__METHOD__ . "[".__LINE__."]: called.", AUTH_LOG_DEBUG);
        $this->loadconfig();
    }

// Configurazione
    function getConfigValue($section, $param, $default = null) {

        $this->loadConfig();
        return $this->dbini->getValue($section, $param, $default);
    }

    function setConfigValue($section, $param, $value, array $opt = array()) {
        $defOpt = array('persistent' => false, 'type' => 'STRING', 'type_ext' => null, 'private' => 'T', 'order' => '0', 'description' => null);
        $opt = array_merge($defOpt, $opt);
        $this->loadConfig();
        if ($opt['persistent']) {
            $result = $this->dbini->setAttribute($this->domain, $this->applicationCode, $this->login, $section, $param, $value, strtoupper($opt['type']), $opt['type_ext'], $opt['private'], $opt['order'], $opt['description']);
        } else {
            // Not persistent
            $result = $this->dbini->setValue($section, $param, $value);
        }

        //
        //$this->dbini->flushWriteCache();

        return $result;
    }

// Configurazione
    function getAllConfigValues($section = null) {

        $this->loadConfig();
        return $this->dbini->getAllValues($section);
    }

    function getAllConfigValuesAsString($section = null, $prefix = 'USER_CONFIG_', $separator = '_') {

        $this->loadConfig();
        $result = array();
        foreach ($this->dbini->getAllValues($section) as $key1 => $value1) {
            foreach ($value1 as $key2 => $value2) {
                $result[$prefix . $key1 . $separator . $key2] = $value2;
            }
        }
        return $result;
    }

//SS: to cache!!!
    function getParam($name, $default = null) {

        if (isset($this->userInfo[$name])) {
            return $this->userInfo[$name];
        }
        return $default;
    }

    function setParam($name, $value, $permanent = false) {

        $value = trim($value);
        if (in_array($name, array('us_id', 'us_status', 'us_start_date', 'us_expire_date', 'do_id',
                    'us_pw_expire', 'us_pw_last_change', 'us_last_ip', 'us_last_login', 'us_last_action',
                    'us_mod_user', 'us_mod_date'))) {
// Parametri che non posso mai modificare
            throw new Exception('Permission denied');
        }
        if (in_array($name, array('us_login'))) {
            throw new Exception('Permission denied');
        }

        if ($name == 'us_login') {
            $this->login = $value;
        }
        if ($name == 'us_password') {
            $value = md5($value);
//SS: Ricarico da db? $this->password = $value;
        }

        $this->userInfo[$name] = $value;
        if ($permanent) {
// Update only if data changed
            if ($name == 'us_password') {
                /** Password change */
                $this->passwordStatus = 1;  //SS: tra 1 gg la pw scade.
                $this->session['passwordStatus'] = $this->passwordStatus;
                $sql = "UPDATE auth.users SET 
                        us_password = " . $this->db->quote($value) . ",
                        us_pw_last_change=CURRENT_TIMESTAMP,
                        us_mod_date=CURRENT_TIMESTAMP, 
                        us_mod_user= {$this->UID}
                        WHERE us_id=" . $this->UID;
            } else {
                /** Field change */
                $sql = "UPDATE auth.users SET 
                        {$name}=" . $this->db->quote($value) . ",
                        us_mod_date=CURRENT_TIMESTAMP,
                        us_mod_user= {$this->UID}
                        WHERE us_id=" . $this->UID;
            }
            $this->log(__METHOD__ . "[".__LINE__."]: $sql", AUTH_LOG_DEBUG);
            $this->db->exec($sql);
        }
    }

    public function getOptions() {
        return $this->options;
    }

    /**
     * Return all session parameters
     * @return array
     */
    public function getAllSessionParameters() {
        return $this->sessionParameters;
    }

    /**
     * Return the session parameter value by name
     * @param mixed $name
     * @param mixed $default
     * @return mixed 
     */
    public function getSessionParam($name, $default = null) {

        if (isset($this->sessionParameters[$name])) {
            return $this->sessionParameters[$name];
        }
        return $default;
    }

    /**
     * Set a session parameter by name
     * @param mixed $name
     * @param mixed $value 
     */
    public function setSessionParam($name, $value) {
        $this->sessionParameters[$name] = $value;
    }

}


