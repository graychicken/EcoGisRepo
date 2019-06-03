<?php


if (defined("__R3_AUTH_MANAGER__")) return;
define("__R3_AUTH_MANAGER__", 1);

//TODO: Aggiungere tabella utente / applicazione per velocizzare la gestione utenti e non far vedere utenti di applicativi diversi
define('R3AUTHMANAGER_VERSION',                       '0.5b');

define('MISSING_FIELD',                       1);
define('INVALID_FIELD',                       2);
define('PK_ERROR',                            3);
define('PK_NOT_FOUND',                        4);
define('LOOKUP_ERROR',                        5);  // Errore di lookup (ho dati collegati)
define('IN_USE',                              6);  // Oggetto in uso (es: voglio cancellare me stesso)

class EInputError extends Exception
{
    private $field = null;
    public function __construct($message, $field = null, $code = 0) {
        
        parent::__construct($message, $code);
        $this->field = $field;
    }

    final function getField() {   // Field of the exception
                        
        return $this->field;
    }
}

class EDeleteError extends EInputError { }



//SS: Permissions
//  SHOW ALL_DOMAINS: Mostra tutti i domini
//  SHOW DOMAIN: Mostra tutti i domini (come sopra)
//  SHOW ALL_APPLICATIONS: Mostra tutte le applicazioni
//  SHOW APPLICATION: Mostra tutte le applicazioni abilitate per l'utente

//  SHOW ALL_USERS: Mostra tutti gli utenti
//  SHOW USER: Mostra tutti gli utenti appartenenti al dominio dell'utente
//  SHOW LOCAL_USER: Mostra tutti gli utenti appartenenti al dominio dell'utente e solo per l'applicativo in uso


/**
 * extends R3Auth. Serve per sapere chi fa le modifiche!!!
 *
 * @author Sergio Segala <sergio.segala@r3-gis.com>
 */
class R3AuthManager extends \R3Auth
{
    /**
     * Create an associative array from the input numeric array for the given object.
     * Usefull for smarty and combo box
     * Eg: ([0] => Array ( [app_code] => MANAGER [app_name] => User Manager )
     *      [MANAGER] => User Manager)
     *
     * SS: invertire application e domain
     *
     * @param array     numeric array
     * @param string    object name. Valid names are 'APPLICATION', 'DOMAIN', 'GROUP', 'ACOBJECT', 'USER'
     * @return array    associative array
     * @access public
     */
    public function mkAssociativeArray($a, $obj, $fullName=true, $userFlag='NL')
    {
        $result = array();
        if (!is_array($a)) {
            return $result;
        }
        if ($obj == 'APPLICATION') {
            foreach ($a as $value) {
                $result[$value['app_code']] = $value['app_name'];
            }
        } else if ($obj == 'DOMAIN') {
            foreach ($a as $value) {
                $result[$value['dn_name']] = $value['dn_name'];
            }
        } else if ($obj == 'ACNAME') {
            // Application will be lost!
            foreach ($a as $value) {
                $result[$value['ac_verb'] . '|' . $value['ac_name']] = $value['ac_verb'] . ' ' . $value['ac_name'];
            }
        } else if ($obj == 'GROUP') {
            // Application will be lost!
            foreach ($a as $value) {
                if (!$fullName) {
                    $result[$value['app_code'] . '|' . $value['gr_name']] = $value['app_code'] . ' ' . $value['gr_name'];
                } else {
                    $result[$value['app_code'] . '|' . $value['gr_name']] = $value['gr_name'];
                }
            }
        } else if ($obj == 'USER') {
            // Application will be lost!
            foreach ($a as $value) {
                if (!$fullName) {
                    $s = $value['dn_name'] . ' ';
                } else {
                    $s = ''; 
                }
                if ($userFlag == 'NL') {
                    $s .= $value['us_name'] . ' (' . $value['us_login'] . ')';
                } else if ($userFlag == 'LN') {
                    if ($value['us_name'] == '') {
                        $s .= $value['us_login'];
                    } else {
                        $s .= $value['us_name'] . ' (' . $value['us_login'] . ')';                    
                    }
                } else if ($userFlag == 'L') {
                    $s .= $value['us_login'];
                } else if ($userFlag == 'N') {
                    if ($value['us_name'] == '') {
                        $s .= $value['us_login'];
                    } else {
                        $s .= $value['us_name'];                    
                    }
                }
                $result[$value['dn_name'] . '|' . $value['us_login']] = $s;
            }    
        } else {
            throw new Exception('mkAssociativeArray: invalid object', 1);	
        }
        return $result;
    }

    /**
     * Get the module version
     *
     * @param array           name ot the class to get the version
     * @return string|null    return the version text or null if faild
     * @access public
     */
    public function getVersionString($className = null)
    {
        if ($className == '' || $className == 'R3AuthManager') {
            return R3AUTHMANAGER_VERSION;
        } 
        return parent::getVersionString($className);
    }

    private function nextID($seqName)
    {
        if ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            //SS: 2011-11-21 postgres 9 fix
            $sql = "SELECT nextval('{$seqName}'::regclass)";
            return $this->db->query($sql)->fetchColumn(0);
        } else {
            // mysql, oracle (SS: CODE NOT TESTED)
            return $this->db->lastInsertId();
        }
    }
    
    /**
     * execute the given statement and return its resultset.
     *
     * @param string    the sql statement to execute
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return array    a numeric array with all the applications
     * @access public
     */
    private function executeStatement($sql, $data=array(), &$tot=0)
    {
        $this->log("R3AuthManager::executeStatement(): $sql", AUTH_LOG_DEBUG);
        //$sth = $this->db->prepare($sql);
        //$res = $sth->execute();
                    
        $res = $this->db->prepare($sql);
        $res->execute();
        
        $tot = $res->rowCount();
        
        $result = array();
        if (isset($data['limit']) && isset($data['offset'])) {
            // limited resultset
            if ($data['offset'] < $tot) {
                $i = 0;
                while($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                    if ($i >= $data['offset'] && $i < $data['offset'] + $data['limit']) {
                        $result[] = $row;
                    }
                    $i++;
                }
            }
        } else {
            // unlimited result
            while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }
        }
        
        return $result;
    }

    /**
     * Start a transaction if not already started
     */
    private function beginTransaction()
    {
        // do not restart transaction if already started
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }
    
    /**
     * Rollback a transaction
     */
    private function rollbackTransaction()
    {
        if ($this->db->inTransaction()) {
            $this->db->rollback();
        }
    }

    /**
     * Commit a transaction
     */
    private function commitTransaction()
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }
    
    /**
     * return true if all chars in the given string are valid
     *
     * @param string   text to validate
     * @param string   valid charecters. 
     * @return bool    true if all charecters are valid
     * @access public
     */
    public function validChars($s, $validChars='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890._@%$&=#')
    {
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            if (strpos($validChars, $s[$i]) === false) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * return true if the regular expression in $pattern is valid
     *
     * @param string   text to validate
     * @param string   regular expression pattern
     * @return bool    true if done
     * @access public
     */
    public function validCharsRegEx($s, $pattern='/^[A-Za-z0-9._@%$&=#\-\s]*$/')
    {
        return preg_match($pattern, $s) > 0;
    }

    /**
     * Return the valid and implemented authentication methods
     *
     * @param array     all the method I want to have
     * @param array     all the method I don't want to have
     * @return array    return all the valid authentication methods supported by the server
     * @access public
     */
    public function getAuthenticationMethods($includeList=null, $excludeList=null)
    {
        $result = array();
        if (($includeList === null || in_array('DB', $includeList)) &&
            ($excludeList === null || !in_array('DB', $excludeList))) {
            $result['DB'] = 'Database';
        }    
        if (($includeList === null || in_array('POP3', $includeList)) &&
            ($excludeList === null || !in_array('POP3', $excludeList))) {
            $result['POP3'] = 'POP3';
        }
        if (extension_loaded('imap') && 
            ($includeList === null || in_array('IMAP', $includeList)) &&
            ($excludeList === null || !in_array('IMAP', $excludeList))) {
            $result['IMAP'] = 'IMAP';
        }
        if (($includeList === null || in_array('FILE', $includeList)) &&
            ($excludeList === null || !in_array('FILE', $excludeList))) {
            $result['FILE'] = 'File';
        }
        if (extension_loaded('kadm5') && 
            ($includeList === null || in_array('KADM5', $includeList)) &&
            ($excludeList === null || !in_array('KADM5', $excludeList))) {
            $result['KADM5'] = 'Kerberos 5';
        }
        if (extension_loaded('ldap') && 
            ($includeList === null || in_array('LDAP', $includeList)) &&
            ($excludeList === null || !in_array('LDAP', $excludeList))) {
            $result['LDAP'] = 'LDAP';
        }
        if (($includeList === null || in_array('RADIUS', $includeList)) &&
            ($excludeList === null || !in_array('RADIUS', $excludeList))) {
            $result['RADIUS'] = 'Radius';
        }
        if (($includeList === null || in_array('SAP', $includeList)) &&
            ($excludeList === null || !in_array('SAP', $excludeList))) {
            $result['SAP'] = 'SAP';
        }
        if (($includeList === null || in_array('SMBPasswd', $includeList)) &&
            ($excludeList === null || !in_array('SMBPasswd', $excludeList))) {
            $result['SMBPasswd'] = 'SAMBA smbpasswd file';
        }
        return $result;
    }  
    
    /**
     * Return true if the system permit the passwords'change
     *
     * @return boolean   return true if the change is allowed
     * @access public
     */
    public function canChangePassword($method = null, $auth_data = null)
    {
        if ($method == null) {
            $method = $this->auth_type;
        }
        if ($auth_data == null) {
            $auth_data = $this->auth_data;
        }
        return ($method == 'DB' && $auth_data == '');
    }

    /**
     * Get all the available domains of the authenticated user.
     * if the permission SHOW ALL_DOMAINS is set all the appliocations are returned
     *
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of domains
     * @param boolean   if true the function return a record for each alias, instead of a single name
     * @param boolean   if true force the reload of the list
     * @return array    a numeric array with all the domains
     * @access public
     */
    public function getDomainsList($data = array(), &$tot = 0, $multiple = false, $forceRealod=false)
    {
        static $lastSQL = '';
        static $lastResult = array();

        $this->log('R3AuthManager::getDomainsList() called.', AUTH_LOG_DEBUG);
		// Check permission
        if ($this->hasPerm('SHOW', 'DOMAIN') || 
            $this->hasPerm('SHOW', 'ALL_DOMAINS')) {
            // Show all domains
            $this->log('R3AuthManager::getDomainsList(): All domains are shown', AUTH_LOG_INFO);
            $domain_where = '1 = 1';       // All applications should be shown
        } else { 
            // Show only my domain
            $this->log('R3AuthManager::getDomainsList(): Only the follow domain is shown: ' . $this->domain . '.', AUTH_LOG_DEBUG);
            $domain_where = 'auth.domains.do_id=' . $this->domainID;
        }
        
        /* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'dn_name';
		}
        
        /* SQL where */
		if (isset($data['where']) && $data['where'] != '') {
			$more_where = $data['where'];
		} else {
			$more_where = '1 = 1';
		}
        
        // Prevent multiple record to be returned
        if ($multiple) {
            $multiple_where = '1 = 1';
        } else {
            $multiple_where = "dn_type='N'";
        }
        
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'dn_name';
		}
        
        // Execute the query
        $sql = "SELECT {$data['fields']}
               FROM auth.domains 
               INNER JOIN auth.domains_name ON 
               auth.domains.do_id=auth.domains_name.do_id 
               WHERE 
               ($multiple_where) AND
               ($domain_where) AND 
               ($more_where)
               ORDER BY {$order}";
        
        if ($forceRealod || $sql != $lastSQL) {
           // echo nl2br(str_replace(' ', '&nbsp;', $sql));
            $lastSQL = $sql;
            $lastResult = $this->executeStatement($sql, $data, $tot);
        }
        return $lastResult;    
    }

    /**
     * get a single domain data
     * The permission SHOW DOMAIN must be set
     * if the permission SHOW ALL_DOMAINS is set all the domains are returned
     *
     * @param string    The domain name or alias to get the data. 
     * @param boolean   If true return also the lockup data (domains name and alias, applications)
     * @return mixed    return an array with all the fields, or null if the application is not found
     * @access public
     */
    public function getDomainData($dn_name, $getLockupData=false, $forceRealod=false)
    {
        static $cacheDomainData = array();
        static $cacheDomainDataAlias = array();
        static $cacheDomainDataAliasDescr = array();
        static $cacheDomainDataApplication = array();
        static $cacheDomainDataSettings = array();
        
        $this->log('R3AuthManager::getDomainData() called.', AUTH_LOG_DEBUG);
        
        if (!$forceRealod && isset($cacheDomainData[$dn_name])) {
            // Data cached
            $this->log('R3AuthManager::getDomainData() cached data returned.', AUTH_LOG_DEBUG);    
            $data = $cacheDomainData[$dn_name];
        } else {
            // Load data from database
            $data = $this->getDomainsList(array('fields'=>'*', 'where'=>'dn_name=' . $this->db->quote($dn_name)), $tot, true, $forceRealod);
            $cacheDomainData[$dn_name] = $data;
        }
        
        if (count($data) == 1) {
            $data = $data[0];
            // A single domain where found. Security is OK.
            if ($getLockupData === true || is_array($getLockupData)) {
                if ($getLockupData === true || in_array('ALIAS', $getLockupData)) {
                    if (!$forceRealod && isset($cacheDomainDataAlias[$dn_name]))  {
                        // Data cached
                        $this->log('R3AuthManager::getDomainData() lockup cached data returned for ALIAS.', AUTH_LOG_DEBUG);    
                        $data['names'] = $cacheDomainDataAlias[$dn_name];
                        $data['descriptions'] = $cacheDomainDataAliasDescr[$dn_name];
                    } else {
                        // Get the alias list
                        $sql = "SELECT dn_name, dn_description
                                FROM auth.domains_name
                                WHERE
                                do_id= {$data['do_id']}
                                ORDER BY dn_type desc, dn_name";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $data['names'] = array();
                        $data['descriptions'] = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $data['names'][] = $value['dn_name'];
                            $data['descriptions'][] = $value['dn_description'];
                        }
                        $cacheDomainDataAlias[$dn_name] = $data['names'];
                        $cacheDomainDataAliasDescr[$dn_name] = $data['descriptions'];
                    }
                }
                
                if ($getLockupData === true || in_array('APPLICATION', $getLockupData)) {
                    if (!$forceRealod && isset($cacheDomainDataApplication[$dn_name]))  {
                        // Data cached
                        $this->log('R3AuthManager::getDomainData() lockup cached data returned for APPLICATION.', AUTH_LOG_DEBUG);    
                        $data['applications'] = $cacheDomainDataApplication[$dn_name];
                    } else {
                        // Get the applications list
                        $sql = "SELECT app_code, app_name 
                                FROM auth.applications
                                INNER JOIN auth.domains_applications ON 
                                auth.applications.app_id=auth.domains_applications.app_id
                                WHERE
                                do_id= {$data['do_id']}
                                ORDER BY
                                app_name ";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $data['applications'] = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $data['applications'][$value['app_code']] = $value['app_name'];
                        }
                        $cacheDomainDataApplication[$dn_name] = $data['applications'];
                    }
                }
                if ($getLockupData === true || in_array('AUTH_SETTINGS', $getLockupData)) {
                    if (!$forceRealod && isset($cacheDomainDataSettings[$dn_name]))  {
                        // Data cached
                        $this->log('R3AuthManager::getDomainData() lockup cached data returned for AUTH_SETTINGS.', AUTH_LOG_DEBUG);    
                        $data['auth_settings'] = $cacheDomainDataSettings[$dn_name];
                    } else {
                        // Get the applications list
                        $sql = "SELECT as_code, as_type, as_name, as_change_password, as_data
                                FROM auth.auth_settings a
                                WHERE do_id={$data['do_id']}
                                ORDER BY as_code, as_id";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $data['auth_settings'] = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $data['auth_settings'][$value['as_code']] = $value;
                        }
                        $cacheDomainDataSettings[$dn_name] = $data['auth_settings'];
                    }
                } 
            }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * get a single domain data from ID instead of name
     * The permission SHOW DOMAIN must be set
     * if the permission SHOW ALL_DOMAINS is set all the domains are returned
     *
     * @param string    The domain name or alias to get the data. 
     * @param boolean   If true return also the lockup data (domains name and alias, applications)
     * @return mixed    return an array with all the fields, or null if the application is not found
     * @access public
     */
    public function getDomainDataFromID($ID, $getLockupData=false)
    {
        $this->log('R3AuthManager::getDomainDataFromID() called.', AUTH_LOG_DEBUG);
        $data = $this->getDomainsList(array('fields'=>'dn_name', 
                                            'where'=>'auth.domains.do_id=' . $this->db->quote($ID)));
        if (count($data) == 1) {
            $data = $this->getDomainData($data[0]['dn_name'], $getLockupData);
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Return the domain code from domain id
     */
    public function getDomainCodeFromID($id)
    {
        $this->log('R3AuthManager::getDomainCodeFromID() called.', AUTH_LOG_DEBUG);
        $data = $this->getDomainDataFromID($id);
        if ($data === null)
            return null;
        return $data['dn_name'];
    }

    /**
     * Execute the DML to insert/update/delete of a domain. Permission and integrity are checked here
     *   SS: DOMAINS o ALL_DOMAINS fanno la stessa cosa
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function execDomainDML($dmlKind, $old_dn_name, $dn_names, $do_auth_type, $do_auth_data, $applications, $extra_data)
    {
        // Check permission
        if (!$this->hasPerm($dmlKind, 'DOMAIN') && !$this->hasPerm($dmlKind, 'ALL_DOMAINS')) {
            $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }
        
        if (is_string($dn_names)) {
            $dn_names = array($dn_names);
        }
                
        // Check inputs
        if ($dmlKind != 'DEL') {
            // Check for valid name
            if (!is_array($dn_names) || $dn_names[0] == '') {
                throw new EInputError(__METHOD__ . ': Missing domain name', 'dn_name', MISSING_FIELD);
            }
            if (!$this->validCharsRegEx($dn_names[0])) {
                throw new EInputError(__METHOD__ . ': Invalid domain name', 'dn_name', INVALID_FIELD);
            }
            for ($i = 1; $i < count($dn_names); $i++) {
                if (!$this->validCharsRegEx($dn_names[$i])) {
                    throw new EInputError(__METHOD__ . ': Invalid domain alias', 'do_alias', INVALID_FIELD);
                }
            }
            if ($do_auth_type == '') {
                throw new EInputError(__METHOD__ . ': Missing authentication type', 'do_auth_type', MISSING_FIELD);
            }
            if (!array_key_exists($do_auth_type, $this->getAuthenticationMethods())) {
                throw new EInputError(__METHOD__ . ': Invalid authentication type', 'do_auth_type', INVALID_FIELD);
            }
            
            if (!is_array($applications) || $applications[0] == '') {
                throw new EInputError(__METHOD__ . ': Missing applications', 'applications', MISSING_FIELD);
            }
        }
        
        // Check if domain already exists or does not exists
        $data = $this->getDomainData($dn_names[0], true);
        // print_r($data);
        if ($dmlKind == 'ADD' && $data !== null) {
            // add a new application
            throw new EInputError(__METHOD__ . ': Domain already exists', 'dn_name', PK_ERROR);
        } else if ($dmlKind == 'MOD') {
            // modify an old application
            $old_data = $this->getDomainData($old_dn_name);
            if ($old_data === null) {
                throw new EInputError(__METHOD__ . ': Domain name does not exists', 'dn_name', PK_NOT_FOUND);
            } else if ($old_dn_name != $dn_names[0] && $data !== null) {
                throw new EInputError(__METHOD__ . ': Domain already exists', 'dn_name', PK_ERROR);
            }
        } else if ($dmlKind == 'DEL') {
            // delete an application
            if ($data === null) { 
                throw new EInputError(__METHOD__ . ': Domain name does not exists', 'dn_name', PK_NOT_FOUND);
            }
        }	    
        
        // Prepare the DML statement
        $this->beginTransaction();

        switch($dmlKind) {
            case 'ADD':
                $domainId = $this->nextID("auth.domains_do_id_seq");
                $stmtData = array(
                    substr($do_auth_type, 0, 20),
                    $do_auth_data,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $domainId,
                );

                $stmtSql = 'INSERT INTO auth.domains (do_auth_type, do_auth_data, ' .
                            ' do_mod_user, do_mod_date, do_id) ' .
                            ' VALUES (?, ?, ?, ?, ?) ';
                break;
            case 'MOD':
                $domainId = $old_data['do_id'];
                $stmtData = array(
                    substr($do_auth_type, 0, 20),
                    $do_auth_data,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $domainId,
                );
                $stmtSql = 'UPDATE auth.domains SET ' .
                            ' do_auth_type = ?, do_auth_data = ?, ' .
                            ' do_mod_user = ?, do_mod_date = ? ' .
                            ' WHERE do_id = ? ';
                break;
            case 'DEL':
                $domainId = $data['do_id'];
                $stmtData = array($domainId);
                $stmtSql = 'DELETE FROM auth.domains WHERE do_id = ? ';
                break;
        }

        try {
            // domain table
            $stmt = $this->db->prepare($stmtSql);
            $stmt->execute($stmtData);
            $affectedRows = $stmt->rowCount();
            if ($affectedRows != 1) {
                throw new Exception(__METHOD__ . ": affected rows are $affectedRows instead of 1");
            }
            
            // handle domains_name and domains_applications table
            if ($dmlKind == 'MOD') {
                // domains_name table
                $stmt2 = $this->db->prepare('DELETE FROM auth.domains_name WHERE do_id = ?');
                $stmt2->execute(array($domainId));

                // domains_applications table
                $stmt3 = $this->db->prepare('DELETE FROM auth.domains_applications WHERE do_id = ?');
                $stmt3->execute(array($domainId));
            }
            if ($dmlKind != 'DEL') {
                // domains_name table
                $stmt4 = $this->db->prepare('INSERT INTO auth.domains_name ' .
                    ' (do_id, dn_name, dn_description, dn_type) VALUES (?, ?, ?, ?) ');
                for ($i = 0; $i < count($dn_names); $i++) {
                    if ($i == 0) {
                        $dn_type = 'N';
                    } else {
                        $dn_type = 'A';
                    }
                    $description = empty($extra_data['description'][$i]) ? null : $extra_data['description'][$i];

                    $stmt4->execute(array(
                        $domainId,
                        $dn_names[$i],
                        $description,
                        $dn_type
                    ));
                }
                
                // domains_applications table
                $stmt5 = $this->db->prepare('INSERT INTO auth.domains_applications ' .
                    ' (do_id, app_id) VALUES (?, ?) ');
                foreach($applications as $value) {
                    $appData = $this->getApplicationData($value);
                    $stmt5->execute(array(
                        $domainId,
                        $appData['app_id']
                    ));
                }
            }
            
            $this->commitTransaction();
        } catch (Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        }	
        
        return true;            
    }
    
    /**
     * Add a new domain
     * The permission ADD ALL_DOMAIN must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function addDomain($dn_names, $do_auth_type, $do_auth_data, $applications, $extra_data = array())
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execDomainDML('ADD', null, $dn_names, $do_auth_type, $do_auth_data, $applications, $extra_data);
    }

    /**
     * Modify an existing domain
     * The permission MOD ALL_DOMAINS must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function modDomain($old_dn_name, $dn_names, $do_auth_type, $do_auth_data, $applications, $extra_data = array())
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execDomainDML('MOD', $old_dn_name, $dn_names, $do_auth_type, $do_auth_data, $applications, $extra_data);
    }

    /**
     * Delete an existing domain
     * The permission DEL ALL_DOMAINS must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function delDomain($dn_name)
    {
        $this->log('R3AuthManager::delDomain() called.', AUTH_LOG_DEBUG);
        return $this->execDomainDML('DEL', null, $dn_name, null, null, null, null);
    }
    
    /**
     * Get all the available applications of the authenticated user.
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the appliocations are returned
     *  SS: TODO: Permettere tutte le chiamate degli elenchi anche nel caso in cui non si abbiano permission. In questo caso vedo solo il mio utente, il mio dominio, il mio applicativo
     *
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return array    a numeric array with all the applications
     * @access public
     */
    public function getApplicationsList($data=array(), &$tot=0)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        
        // Check permission
        if ($this->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
            // Show all applications
            $this->log('R3AuthManager::getApplicationsList(): All applications are shown', AUTH_LOG_INFO);
            $app_where = '1 = 1';
        } else if ($this->hasPerm('SHOW', 'APPLICATION')) {
            // Show only the application of my domain
            $app_codes = array();
            $domainData = $this->getDomainData($this->domain, true);
            foreach($domainData['applications'] as $key=>$value) {
                $app_codes[] = $key;
            }
            $this->log('R3AuthManager::getApplicationsList(): The follows applications are shown: ' . implode(', ', $app_codes) . '.', AUTH_LOG_DEBUG);
            $app_where = "app_code in ('" . implode("', '", $app_codes) . "')";  
        } else {
            // Show all applications
            $this->log('R3AuthManager::getApplicationsList(): The follows applications are shown: ' . $this->applicationCode . '.', AUTH_LOG_DEBUG);
            $app_where = "app_code = '" . $this->applicationCode . "'";
        }
            
		/* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'app_code, app_name';
		}
        
        /* SQL where */
		if (isset($data['where']) && $data['where'] != '') {
			$more_where = $data['where'];
		} else {
			$more_where = '1 = 1';
		}
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'app_name';
		}
        
        // Execute the query
        $sql = "SELECT \n" . 
               "  " . $data['fields'] . "\n" . 
               "FROM \n" . 
               "  auth.applications \n" .
               "WHERE \n" .
               "  ($app_where) AND \n" .
               "  ($more_where) \n" .
               "ORDER BY \n" .
               "  " . $order;
        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
        
        return $this->executeStatement($sql, $data, $tot);
    }

    /**
     * get a single application data
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the appliocations are returned
     *
     * @param string    The application code to get the data
     * @return mixed    return an array with all the fields, or null if the application is not found
     * @access public
     */
    public function getApplicationData($app_code, $getLockupData=false, $forceRealod=false)
    {
        static $cacheApplicationData = array();
        static $cacheApplicationDataIP = array();
        
        $this->log('R3AuthManager::getApplicationData() called.', AUTH_LOG_DEBUG);
        if (!$forceRealod && isset($cacheApplicationData[$app_code])) {
            // Data cached
            $this->log('R3AuthManager::getApplicationData() cached data returned.', AUTH_LOG_DEBUG);    
            $data = $cacheApplicationData[$app_code];
        } else {
            // Load data from database
            $data = $this->getApplicationsList(array('fields'=>'*', 'where'=>'app_code=' . $this->db->quote($app_code)), $tot);
            $cacheApplicationData[$app_code] = $data;
        }
        if (count($data) == 1) {
            $data = $data[0];
            // A single domain where found. Security is OK.
            if ($getLockupData === true) {
                if (!$forceRealod && isset($cacheApplicationDataIP[$app_code])) {
                    // Data cached
                    $this->log('R3AuthManager::getApplicationData() lockup cached data returned.', AUTH_LOG_DEBUG);    
                    $data['ip'] = $cacheApplicationDataIP[$app_code];
                } else {
                    // Get the IP list
                    $sql = "SELECT app_code, ip_descr, ip_addr, ip_mask, ip_kind 
                           FROM auth.users_ip 
                           INNER JOIN auth.applications ON
                           auth.users_ip.app_id = auth.applications.app_id 
                           WHERE us_id IS NULL AND app_code=" . $this->db->quote($data['app_code']) . "
                           ORDER BY ip_order";
                    // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                
                    $ip = array();
                    foreach($this->executeStatement($sql) as $value) {
                        $a = array();
                        $a['app_code'] = $value['app_code'];
                        $a['ip_descr'] = $value['ip_descr'];
                        $a['ip_addr'] = $value['ip_addr'];
                        $a['ip_mask'] = $value['ip_mask'];
                        if (strtoupper($value['ip_kind']) == 'A') {
                            $a['ip_kind'] = 'ALLOW';
                        } else {
                            $a['ip_kind'] = 'DENY';
                        }
                        $ip[] = $a;
                    }

                    $cacheApplicationDataIP[$app_code] = $ip;
                    $data['ip'] = $ip;
                }
            }    
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Execute the DML to insert/update/delete of an application. Permission and integrity are checked here
     *  the permission APPLICATION and ALL_APPLICATIONS are allow
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function execApplicationDML($dmlKind, $old_app_code, $app_code, $app_name)
    {
        // Check permission
        if (!$this->hasPerm($dmlKind, 'APPLICATION') && !$this->hasPerm($dmlKind, 'ALL_APPLICATIONS')) {
            $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }
        
        // Check inputs
        if ($dmlKind != 'DEL') {
            if ($app_code == '') {
                throw new EInputError(__METHOD__ . ': Missing application code', 'app_code', MISSING_FIELD);
            }
            if (!$this->validCharsRegEx($app_code)) {
                throw new EInputError(__METHOD__ . ': Invalid application code', 'app_code', INVALID_FIELD);
            }
            if ($app_name == '') {
                throw new EInputError(__METHOD__ . ': Missing application name', 'app_name', MISSING_FIELD);
            }
        }
        
        // Check if application already exists or does not exists
        $data = $this->getApplicationData($app_code);
        if ($dmlKind == 'ADD' && $data !== null) {
            // add a new application
            throw new EInputError(__METHOD__ . ': Application code already exists', 'app_code', PK_ERROR);
        } else if ($dmlKind == 'MOD') {
            // modify an old application
            $old_data = $this->getApplicationData($old_app_code);

            if ($old_data === null) {
                throw new EInputError(__METHOD__ . ': Application code does not exists', 'app_code', PK_NOT_FOUND);
            } else if ($old_app_code != $app_code && $data !== null) {
                throw new EInputError(__METHOD__ . ': Application code already exists', 'app_code', PK_ERROR);
            }

        } else if ($dmlKind == 'DEL') {
            // delete an application
            if ($data === null) { 
                throw new EInputError(__METHOD__ . ': Application code does not exists', 'app_code', PK_NOT_FOUND);
            }
        }

        // Prepare the sql statement
        switch($dmlKind) {
            case 'ADD':
                $stmtData = array(
                    $this->nextID("auth.applications_app_id_seq"),
                    substr($app_code, 0, 20),
                    substr($app_name, 0, 200),
                    $this->UID,
                    date('Y-m-d H:i:s'),
                );
                $stmtSql = 'INSERT INTO auth.applications (app_id, app_code, ' .
                            ' app_name, app_mod_user, app_mod_date) ' .
                            ' VALUES (?, ?, ?, ?, ?) ';
                break;
            case 'MOD':
                $stmtData = array(
                    substr($app_code, 0, 20),
                    substr($app_name, 0, 200),
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $old_app_code,
                );
                $stmtSql = 'UPDATE auth.applications SET app_code=?, ' .
                            ' app_name=?, app_mod_user=?, app_mod_date=? ' .
                            ' WHERE app_code = ? ';
                break;
            case 'DEL':
                $stmtData = array($app_code);
                $stmtSql = 'DELETE FROM  auth.applications WHERE app_code = ? ';
                break;
        }

        $stmt = $this->db->prepare($stmtSql);
        $stmt->execute($stmtData);
        
        return ($stmt->rowCount() == 1);
    }
    
    /**
     * Add a new application
     * The permission ADD ALL_APPLICATION must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function addApplication($app_code, $app_name)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execApplicationDML('ADD', null, $app_code, $app_name);
    }
        
    /**
     * Modify an application
     * The permission MOD ALL_APPLICATION must be set
     *
     * @param string    old-application code. 
     * @param string    new application code. 
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function modApplication($old_app_code, $app_code, $app_name)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execApplicationDML('MOD', $old_app_code, $app_code, $app_name);
    }
    
    /**
     * Delete an application
     * The permission DEL ALL_APPLICATION must be set
     *
     * @param string    application code to delete 
     * @return array    a numeric array with all the applications
     * @access public
     */
    public function delApplication($app_code)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execApplicationDML('DEL', null, $app_code, null);
    }
    
    /**
     * Get all the permission types available for the system
     * To add new permission type modify this function and the check on database
     *
     * @param string key of permission type
     * @return mixed array of all the permission types or string with permission type text or null if given type not exists
     */
    public function getACNamesTypeList($type = null)
    {
        $types = array('C'=>'CUSTOM', 'S'=>'SYSTEM');
        if ($type === null) {
            return $types;
        } else if (isset($types[$type])) {
            return $types[$type];
        }
        return null;
    }

    /**
     * Get all the permission available on the system
     * The permission SHOW ACNAME must be set
     * if the permission SHOW ALL_ACNAMES is set all the names of all applications are returned
     *
     * @param string    application code to get the list
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of domains
     * @return array    a numeric array with all the domains
     * @access public
     */
    public function getACNamesList($app_code = null, $data = array(), &$tot = 0)
    {
        $this->log('R3AuthManager::getACNamesList() called.', AUTH_LOG_DEBUG);
		
        // Check permission
        if ($this->hasPerm('SHOW', 'ALL_ACNAMES')) {
            // Show all permissions
            $this->log('R3AuthManager::getACNamesList(): All permissions are shown', AUTH_LOG_INFO);
            $app_where = '1 = 1';
            $strict_where = '1 = 1';
        } else if ($this->hasPerm('SHOW', 'ACNAME')) {
            // Show only the permission for all my applications
            if ($this->hasPerm('SHOW', 'ALL_APPLICATIONS') || $this->hasPerm('SHOW', 'APPLICATION')) {
                // Vedo anche gli acl degli altri applicativi
                $app_codes = array();
                $domainData = $this->getDomainData($this->domain, true);
                foreach($domainData['applications'] as $key=>$value) {
                    $app_codes[] = $key;
                }
                $this->log('R3AuthManager::getACNamesList(): The permission of the follow applications are shown: ' . implode(', ', $app_codes) . '.', AUTH_LOG_DEBUG);
                $app_where = "app_code in ('" . implode("', '", $app_codes) . "')";  
            } else {
                // Vedo gli acl solo dell'applicativo corrente
                $app_where = "app_code = '" . $this->applicationCode . "'";  
            }
            $strict_where = '1 = 1';
        } else {
            // Show only my permission
            $app_where = '1 = 1';
            $this->log('R3AuthManager::getACNamesList(): Only my permission are shown.', AUTH_LOG_DEBUG);
            //SS: cache!!!
            $perms = $this->doLoadPermission($this->applicationID, $this->UID, true);
            $a = array();
            foreach($perms as $key1 => $value1) {
                foreach($value1 as $key2 => $value2) {
                    $a[] = $value2;
                }
            }
            if (count($a) > 0) {
                $strict_where = "ac_id in (" . implode(', ', $a) . ")";
            } else {
                $strict_where = 'false';
            }
        }
        
        //$userData = $this->getUserData($this->domain, $this->applicationCode, $this->login, true);
        // print_r($userData);
        
        /* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'app_code, ac_verb, ac_name, ac_active';
		}
        
        /* SQL filter where */
		if ($app_code != '') {
			$filter_where = 'app_code = ' . $this->db->quote($app_code);
		} else {
			$filter_where = '1 = 1';
		}
        
        /* SQL where */
		if (isset($data['where']) && $data['where'] != '') {
			$more_where = $data['where'];
		} else {
			$more_where = '1 = 1';
		}
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'ac_order, app_code, ac_verb, ac_name, ac_active';
		}
        
        // Execute the query
        $sql = "SELECT {$data['fields']}
               FROM auth.acnames
               INNER JOIN auth.applications ON 
               auth.acnames.app_id=auth.applications.app_id 
               WHERE ($app_where) 
               AND ($strict_where) 
               AND ($filter_where) 
               AND ($more_where)
               ORDER BY $order";
        
        if (isset($data['sql'])) {
            // apply the other sql statement
            $sql = str_replace('<SQL>', "(" . $sql . ") user_manager\n", $data['sql']);
        }        
        // echo "<br>\n" . nl2br(str_replace(' ', '&nbsp;', $sql)) . "<br>\n";
        return $this->executeStatement($sql, $data, $tot);
    }

    /**
     * Return all the distinct verbs and/or name in the system
     * The permission SHOW ACNAME must be set
     * if the permission SHOW ALL_ACNAMES is set all the names of all applications are returned
     *
     * @param string    application code to get the list
     * @param string    which field should be returned
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of domains
     * @return array    a numeric array with all the domains
     * @access public
     */
    public function getDistinctACNamesList($app_code = null, $what = 'ac_verb, ac_name', $data = array(), &$tot = 0)
    {
        $this->log('R3AuthManager::getDistinctACVerbsList() called.', AUTH_LOG_DEBUG);
        $data['fields'] = 'DISTINCT ' . $what;
        $data['order'] = $what;
        
        return $this->getACNamesList($app_code, $data, $tot);
    }

    /**
     * get a single application data
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the appliocations are returned
     *
     * @param string    The application code to get the data
     * @return mixed    return an array with all the fields, or null if the application is not found
     * @access public
     */
    public function getACNameData($app_code, $ac_verb, $ac_name)
    {
        $this->log('R3AuthManager::getApplicationData() called.', AUTH_LOG_DEBUG);
        $result = $this->getACNamesList($app_code, array('fields'=>'*', 
                                                         'where'=>'app_code=' . $this->db->quote($app_code) . ' AND ' .
                                                                  'ac_verb=' . $this->db->quote($ac_verb) . ' AND ' .
                                                                  'ac_name=' . $this->db->quote($ac_name)), $tot);
                                                            
        if (count($result) == 1) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Execute the DML to insert/update/delete of an application. Permission and integrity are checked here
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function execACNameDML($dmlKind, 
                                   $old_app_code, $old_ac_verb, $old_ac_name, 
                                   $app_code, $ac_verb, $ac_name, $ac_descr, $ac_order, $ac_active, $ac_type)
    {
        // Check permission
        if (!$this->hasPerm($dmlKind, 'ALL_ACNAMES') && !$this->hasPerm($dmlKind, 'ACNAME')) {
            $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }
        
        if ($ac_order == '') {
            $ac_order = 0;
        }
        
        if ($ac_active == true) {
            $ac_active = 'T';
        } else {
            $ac_active = 'F';
        }

        // Check inputs
        if ($dmlKind != 'DEL') {
            if ($app_code == '') {
                throw new EInputError(__METHOD__ . ': Missing application code', 'app_code', MISSING_FIELD);
            }
            if ($ac_verb == '') {
                throw new EInputError(__METHOD__ . ': Missing verb', 'ac_verb', MISSING_FIELD);
            }
            if ($ac_name == '') {
                throw new EInputError(__METHOD__ . ': Missing name', 'ac_name', MISSING_FIELD);
            }
            $data = $this->getApplicationData($app_code); 
                
            if ($data === null) {
                throw new EInputError(__METHOD__ . ': Invalid application code', 'app_code', INVALID_FIELD);
            }
            $app_id = $data['app_id'];
            if (!$this->validCharsRegEx($app_code)) {
                throw new EInputError(__METHOD__ . ': Invalid application code', 'app_code', INVALID_FIELD);
            }
            if (!$this->validCharsRegEx($ac_verb)) {
                throw new EInputError(__METHOD__ . ': Invalid verb', 'ac_verb', INVALID_FIELD);
            }
            if (!$this->validCharsRegEx($ac_name)) {
                throw new EInputError(__METHOD__ . ': Invalid name', 'ac_name', INVALID_FIELD);
            }
            if (!is_numeric($ac_order)) {
                throw new EInputError(__METHOD__ . ': Invalid order', 'ac_order', INVALID_FIELD);
            }
        } else {
            $app_id = null;
        }
        
        // Check if acl already exists or does not exists
        $data = $this->getACNameData($app_code, $ac_verb, $ac_name);
        if ($dmlKind == 'ADD' && $data !== null) {
            // add a new acl
            throw new EInputError(__METHOD__ . ': Access control verb and name already exists', 'app_code, ac_verb, ac_name', PK_ERROR);
        } else if ($dmlKind == 'MOD') {
            // modify an old acl
            $old_data = $this->getACNameData($old_app_code, $old_ac_verb, $old_ac_name);

            if ($old_data === null) {
                throw new EInputError(__METHOD__ . ': Access control verb and name does not exists', 'app_code, ac_verb, ac_name', PK_NOT_FOUND);
            } else if ($old_app_code != $app_code && 
                       $old_ac_verb != $ac_verb && 
                       $old_ac_name != $ac_name && $data !== null) {
                throw new EInputError(__METHOD__ . ': Access control verb and name already exists', 'app_code, ac_verb, ac_name', PK_ERROR);
            }
        } else if ($dmlKind == 'DEL') {
            // delete an acl
            if ($data === null) { 
                throw new EInputError(__METHOD__ . ': Access control verb and name does not exists', 'app_code, ac_verb, ac_name', PK_NOT_FOUND);
            }
        }

        // Prepare the sql statement
        switch($dmlKind) {
            case 'ADD':
                $aclId = $this->nextID("auth.acnames_ac_id_seq");
                $stmtData = array(
                    $aclId,
                    $app_id,
                    substr($ac_verb, 0, 64),
                    substr($ac_name, 0, 64),
                    $ac_descr,
                    $ac_order,
                    $ac_active,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $ac_type,
                );

                $stmtSql = 'INSERT INTO auth.acnames (ac_id, app_id, ac_verb, ' .
                            ' ac_name, ac_descr, ac_order, ac_active, ac_mod_user, ' .
                            ' ac_mod_date, ac_type)  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ';
                break;
            case 'MOD':
                $aclId = $old_data['ac_id'];
                $stmtData = array(
                    $app_id,
                    substr($ac_verb, 0, 64),
                    substr($ac_name, 0, 64),
                    $ac_descr,
                    $ac_order,
                    $ac_active,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $ac_type,
                    $aclId,
                );

                $stmtSql = 'UPDATE auth.acnames SET app_id = ?, ac_verb = ?, ' .
                            ' ac_name = ?, ac_descr = ?, ac_order = ?, ac_active = ?, ' .
                            ' ac_mod_user = ?, ac_mod_date = ?, ac_type = ? WHERE ac_id = ? ';
                break;
            case 'DEL':
                $aclId = $data['ac_id'];
                $stmtData = array($aclId);
                $stmtSql = 'DELETE FROM  auth.acnames WHERE ac_id = ? ';
                break;
        }

        $stmt = $this->db->prepare($stmtSql);
        $stmt->execute($stmtData);
        $affectedRows = $stmt->rowCount();
        
        return ($affectedRows == 1);            
    }
    
    /**
     * Add a new AC name
     * The permission ADD ALL_ACNAMES must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function addACName($app_code, $ac_verb, $ac_name, $ac_descr = null, $ac_order = 0, $ac_active = true, $ac_type)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execACNameDML('ADD',
                            null, null, null, 
                            $app_code, $ac_verb, $ac_name, $ac_descr, $ac_order, $ac_active, $ac_type);
    }
    
    /**
     * Modify an existing AC name
     * The permission ADD ALL_ACNAMES must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function modACName($old_app_code, $old_ac_verb, $old_ac_name, $app_code, $ac_verb, $ac_name, $ac_descr = null, $ac_order = 0, $ac_active = true, $ac_type)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execACNameDML('MOD',
                            $old_app_code, $old_ac_verb, $old_ac_name, 
                            $app_code, $ac_verb, $ac_name, $ac_descr, $ac_order, $ac_active, $ac_type);
    }

    /**
     * Delete an existing AC name
     * The permission ADD ALL_ACNAMES must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function delACName($app_code, $ac_verb, $ac_name)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execACNameDML('DEL',
                            null, null, null, 
                            $app_code, $ac_verb, $ac_name, null, null, null, null);
    }

    /**
     * Get all the available groups of the authenticated user.
     * The permission SHOW GROUP must be set
     * if the permission SHOW ALL_GROUPS is set all the appliocations are returned
     *
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of domains
     * @param boolean   if true the function return a record for each alias, instead of a single name
     * @return array    a numeric array with all the domains
     * @access public
     */
    public function getGroupsList($app_code = null, $data = array(), &$tot = 0)
    {
        $this->log('R3AuthManager::getGroupsList() called.', AUTH_LOG_DEBUG);
        // Check permission
        if ($this->hasPerm('SHOW', 'ALL_GROUPS')) {
            // Show all permissions
            $this->log('R3AuthManager::getGroupsList(): All groups are shown', AUTH_LOG_INFO);
            $app_where = '1 = 1';
            $strict_where = '1 = 1';
        } else if ($this->hasPerm('SHOW', 'GROUP')) {
            // Show only the permission for all my applications
            $this->log('R3AuthManager::getGroupsList(): Only the group of the follow application are shown: ' . $app_code . '.', AUTH_LOG_DEBUG);
            $app_codes = array();
            $domainData = $this->getDomainData($this->domain, true);
            foreach($domainData['applications'] as $key=>$value) {
                $app_codes[] = $key;
            }
            $app_where = "app_code IN ('" . implode("', '", $app_codes) . "')";  
            $strict_where = '1 = 1';
        } else {
            // Show only the groups wich the user has all the permission
            /* $app_where = '1 = 1';
            $this->log('R3AuthManager::getGroupsList(): Restricted groups are shown.', AUTH_LOG_DEBUG);
            $perms = $this->doLoadPermission($this->applicationID, $this->UID, true);
            $a = array();
            foreach($perms as $key1 => $value1) {
                foreach($value1 as $key2 => $value2) {
                    $a[] = $value2;
                }
            }
            if (count($a) > 0) {
                $strict_where = "gr_id in (SELECT DISTINCT gr_id FROM \n" . 
                                "          auth.groups_acl \n" .
                                "          WHERE ac_id in (" . implode(', ', $a) . "))";
            } else {
                $strict_where = 'false';
            }
            */
            
            $this->log('R3AuthManager::getGroupsList(): Only the group of the current application (' . $this->application . ') are shown.', AUTH_LOG_DEBUG);
            $app_where = 'app_code=' . $this->db->quote($this->application);
            $strict_where = '1 = 1';
            
        }
        
        /* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'app_code, gr_name, gr_descr';
		}
        
        /* SQL where */
		if (isset($data['where']) && $data['where'] != '') {
			$more_where = $data['where'];
		} else {
			$more_where = '1 = 1';
		}
        
        /* SQL filter where */
		if ($app_code !== null) {
			$filter_where = 'app_code = ' . $this->db->quote($app_code);
		} else {
			$filter_where = '1 = 1';
		}
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'app_code, gr_name';
		}
        
        /* Execute the query */
        $sql = "SELECT {$data['fields']}
               FROM auth.groups
               INNER JOIN auth.applications ON 
               auth.groups.app_id=auth.applications.app_id 
               LEFT JOIN auth.domains_name ON 
               auth.groups.do_id=auth.domains_name.do_id 
               WHERE 
               (dn_type IS NULL OR dn_type = 'N') AND
               ($filter_where) AND 
               ($app_where) AND 
               ($strict_where) AND 
               ($more_where)
               ORDER BY $order";
               
        if (isset($data['sql'])) {
            // apply the other sql statement
            $sql = str_replace('<SQL>', "(" . $sql . ") user_manager\n", $data['sql']);
        }
        // echo nl2br(str_replace(' ', '&nbsp;', $sql));

        return $this->executeStatement($sql, $data, $tot);
    }
    
    /**
     * compare groups about their permission and returns the verview of common and missing permissions
     * @since 0.5b
     *
     * @param string The application 1 code to get the data
     * @param string The group 1 code to get the data
     * @param string The application 2 code to get the data
     * @param string The group 2 code to get the data
     * @return array with an overview of common and missing permissions
     * @access public
     */
    public function compareGroups($app_code1, $gr_name1, $app_code2, $gr_name2)
    {
        $this->log("R3AuthManager::compareGroups($app_code1, $gr_name1, $app_code2, $gr_name2) called.", AUTH_LOG_DEBUG);
        
        // - get Information
        $data1 = $this->getGroupData($app_code1, $gr_name1, true);
        $data2 = $this->getGroupData($app_code2, $gr_name2, true);
        
        // - fill return array
        $ret = array();
        $ret['common'] = array();
        $ret['missing1'] = array();
        $ret['missing2'] = array();
        
        // - check data1
        if ($data1 !== null && isset($data1['perm'])) {
          foreach($data1['perm'] as $permission) {
            if (in_array($permission, $data2['perm']))
              $ret['common'][] = $permission;
            else
              $ret['missing2'][] = $permission;
          }
        }
        
        // - check data2
        if ($data2 !== null && isset($data2['perm'])) {
          foreach($data2['perm'] as $permission) {
            if ($data1 === null || !in_array($permission, $data1['perm']))
              $ret['missing1'][] = $permission;
          }
        }
        
        return $ret;
    }

    /**
     * get a single user data
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the appliocations are returned
     *
     * @param string    The application code to get the data
     * @return mixed    return an array with all the fields, or null if the application is not found
     * @access public
     */
    public function getGroupData($app_code, $gr_name, $getLockupData=false)
    {
        $this->log('R3AuthManager::getGroupData() called.', AUTH_LOG_DEBUG);
        $data = $this->getGroupsList($app_code, array('fields'=>'*', 'where'=>'gr_name=' . $this->db->quote($gr_name)), $tot);

        if (count($data) == 1) {
            $data = $data[0];
            /* A single group where found. Security is OK. */
            if ($getLockupData === true) {
                /* Get the ac name/verb list */
                $sql = "SELECT ac_verb, ac_name, ga_kind 
                       FROM auth.groups_acl
                       INNER JOIN auth.acnames on 
                       auth.groups_acl.ac_id = auth.acnames.ac_id 
                       WHERE ac_active = 'T' AND 
                       gr_id = {$data['gr_id']}
                       ORDER BY ga_kind DESC, ac_verb, ac_name";

                // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                $perm = array();
                foreach($this->executeStatement($sql) as $value) {
                    $a = array();
                    $a['ac_verb'] = $value['ac_verb'];
                    $a['ac_name'] = $value['ac_name'];
                    if (strtoupper($value['ga_kind']) == 'A') {
                        $a['kind'] = 'ALLOW';
                    } else {
                        $a['kind'] = 'DENY';
                    }
                    $perm[] = $a;
                }
                $data['perm'] = $perm;
             }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Execute the DML to insert/update/delete of a group. Permission and integrity are checked here
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function execGroupDML($dmlKind, 
                                   $old_app_code, $old_gr_name,
                                   $app_code, $gr_name, $dn_name, $gr_descr=null, $perms=null)
    {
        // check permission (step 1/2)
        if (!$this->hasPerm($dmlKind, 'ALL_GROUPS') && !$this->hasPerm($dmlKind, 'GROUP') && !$this->hasPerm($dmlKind, 'LOCAL_GROUPS')) {
            $this->log(__METHOD__ . ": Permission denied for [$dmlKind/ALL_GROUPS|GROUP|LOCAL_GROUPS].", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }

        // Check inputs
        $do_id = null;
        if ($dmlKind != 'DEL') {
            if ($app_code == '') {
                throw new EInputError(__METHOD__ . ': Missing application code', 'app_code', MISSING_FIELD);
            }
            if ($gr_name == '') {
                throw new EInputError(__METHOD__ . ': Missing group name', 'gr_name', MISSING_FIELD);
            }
            
            if (!$this->validCharsRegEx($app_code)) {
                throw new EInputError(__METHOD__ . ': Invalid application code', 'app_code', INVALID_FIELD);
            }
            if (!$this->validCharsRegEx($gr_name)) {
                throw new EInputError(__METHOD__ . ': Invalid group name', 'gr_name', INVALID_FIELD);
            }
            if ($dn_name != '' && !$this->validCharsRegEx($dn_name)) {
                throw new EInputError(__METHOD__ . ': Invalid name', 'dn_name', INVALID_FIELD);
            }
            
            if ($dn_name != '') {
                $data = $this->getDomainData($dn_name);
                if ($data == null) {
                    throw new EInputError(__METHOD__ . ': Domain does not exists', 'do_id', PK_NOT_FOUND);
                }
                $do_id = $data['do_id'];
            } 
        } 
        
        // Check permission (step 2/2)
        $applicationData = $this->getApplicationData($app_code);
        if ($applicationData === null) {
            throw new EInputError(__METHOD__ . ': Invalid application code', 'app_code', INVALID_FIELD);
        }
        $app_id = $applicationData['app_id'];
        if (!$this->hasPerm($dmlKind, 'ALL_GROUPS')) {
            // Posso cambiare applicazione e impostarne una tra quelle del dominio dell'utente, solo se ho la permission GROUP
            $data = $this->getDomainDataFromID($this->domainID, true);
            // Verifica application
            if (!$this->hasPerm($dmlKind, 'GROUP') &&
                $app_code != $this->applicationCode) {
                // Non posso cambiare applicazione! Solo quella da cui mi sono autenticato
                throw new EPermissionDenied(__METHOD__ . ': permission denied', 1);
            }
            if (!array_key_exists($app_code, $data['applications'])) {
                throw new EPermissionDenied(__METHOD__ . ': permission denied', 1);
            }
                
            // Check domain
            if ($dmlKind != 'DEL' && $dn_name != $data['names'][0]) {
                // Non posso cambiare dominio! Solo quello da cui mi sono autenticato
                throw new EPermissionDenied(__METHOD__ . ': permission denied', 1);
            }
        }
        
        // Check if group already exists or does not exists
        $data = $this->getGroupData($app_code, $gr_name);
        if ($dmlKind == 'ADD' && $data !== null) {
            /* add a new group */
            throw new EInputError(__METHOD__ . ': Group name already exists', 'app_code, gr_name', PK_ERROR);
        } else if ($dmlKind == 'MOD') {
            /* modify an old application */
            $old_data = $this->getGroupData($old_app_code, $old_gr_name);
            if ($old_data === null) {
                throw new EInputError(__METHOD__ . ': Group name does not exists', 'app_code, gr_name', PK_NOT_FOUND);
            } else if ($old_app_code != $app_code && 
                       $old_gr_name != $gr_name && $data !== null) {
                throw new EInputError(__METHOD__ . ': Access Group name already exists', 'app_code, gr_name', PK_ERROR);
            }
        } else if ($dmlKind == 'DEL') {
            /* delete a group */
            if ($data === null) { 
                throw new EInputError(__METHOD__ . ': Group name does not exists', 'app_code, gr_name', PK_NOT_FOUND);
            }
        }

        // Prevent to set false to an empty string. See substr documentation
        // TODO: check if this is neeeded!
        if (($gr_descr = substr($gr_descr, 0, 200)) === false) {
            $gr_descr = '';
        }

        $this->beginTransaction();

        // Prepare the sql statement
        switch($dmlKind) {
            case 'ADD':
                $groupId = $this->nextID("auth.groups_gr_id_seq");
                $stmtData = array(
                    $groupId,
                    $gr_name,
                    $gr_descr,
                    $app_id,
                    $do_id,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                );

                $stmtSql = 'INSERT INTO auth.groups (gr_id, gr_name, gr_descr, ' .
                            ' app_id, do_id, gr_mod_user, gr_mod_date) ' .
                            ' VALUES (?, ?, ?, ?, ?, ?, ?) ';
                break;
            case 'MOD':
                $groupId = $old_data['gr_id'];
                $stmtData = array(
                    $gr_name,
                    $gr_descr,
                    $app_id,
                    $do_id,
                    $this->UID,
                    date('Y-m-d H:i:s'),
                    $groupId,
                );

                $stmtSql = 'UPDATE auth.groups SET gr_name = ?, gr_descr = ?, ' .
                            ' app_id = ?, do_id = ?, gr_mod_user = ?, gr_mod_date = ? ' .
                            ' WHERE gr_id = ? ';
                break;
            case 'DEL':
                $groupId = $data['gr_id'];
                $stmtData = array($groupId);

                $stmtSql = 'DELETE FROM auth.groups WHERE gr_id = ? ';
                break;
        }
        
        try { 
            // groups table
            $stmt = $this->db->prepare($stmtSql);
            $stmt->execute($stmtData);
            $affectedRows = $stmt->rowCount();
            if ($affectedRows != 1) {
                throw new Exception(__METHOD__ . ": affected rows are {$affectedRows} instead of 1");
            }
            
            // groups_acl table
            if ($dmlKind == 'MOD') {
                $stmt2 = $this->db->prepare('DELETE FROM auth.groups_acl WHERE gr_id = ?');
                $stmt2->execute(array($groupId));
            }
            
            if ($dmlKind != 'DEL') {
                $stmt3 = $this->db->prepare('INSERT INTO auth.groups_acl (gr_id, ac_id, ga_kind) ' .
                                            ' VALUES (?, ?, ?) ');

                $sql2 = "SELECT ac_id FROM auth.acnames WHERE app_id = ? AND ac_verb = ? AND ac_name = ?";
                $stmt4 = $this->db->prepare($sql2);
                // print_r($perms);
                foreach ($perms as $perm) {
                    $stmt4->execute(array($app_id, $perm['ac_verb'], $perm['ac_name']));
                    if ($row = $stmt4->fetch(\PDO::FETCH_ASSOC)) {
                        $ac_id = $row['ac_id'];
                    } else {
                        throw new Exception('Permission ' . $perm['ac_verb'] . ' ' . $perm['ac_name'] . ' not found!');
                    }
                    if (isset($perm['ga_kind']) && $perm['ga_kind'] == 'ALLOW') {
                        $ga_kind = 'A';
                    } else {
                        $ga_kind = 'D';
                    }
                    $stmt3->execute(array($groupId, $ac_id, $ga_kind));
                }
            }
            
            $this->commitTransaction();
        } catch (Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        }	
        
        return true;           
    }
    
    /**
     * Add a new group
     * The permission ADD GROUP must be set //SS: gruppo per solo 1 applicativo
     * The permission ADD LOCAL_GROUP must be set //SS: gruppo per solo 1 applicativo e solo 1 dominio
     * If the permission ADD ALL_GROUPS is set the user can add groups for all applications //SS: no limit
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function addGroup($app_code, $gr_name, $dn_name, $gr_descr=null, $perms=null)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execGroupDML('ADD', null, null, $app_code, $gr_name, $dn_name, $gr_descr, $perms);
    }
    
    /**
     * Modify a group
     * The permission MOD GROUP must be set //SS: gruppo per solo 1 applicativo
     * The permission MOD LOCAL_GROUP must be set //SS: gruppo per solo 1 applicativo e solo 1 dominio
     * If the permission NOD ALL_GROUPS is set the user can add groups for all applications //SS: no limit
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function modGroup($old_app_code, $old_gr_name, $app_code, $gr_name, $dn_name, $gr_descr=null, $perms=null)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execGroupDML('MOD', $old_app_code, $old_gr_name, $app_code, $gr_name, $dn_name, $gr_descr, $perms);
    }

    /**
     * Delete a group
     * The permission MOD GROUP must be set //SS: gruppo per solo 1 applicativo
     * The permission MOD LOCAL_GROUP must be set //SS: gruppo per solo 1 applicativo e solo 1 dominio
     * If the permission MOD ALL_GROUPS is set the user can add groups for all applications //SS: no limit
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function delGroup($app_code, $gr_name)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execGroupDML('DEL', null, null, $app_code, $gr_name, null, null, null, null);
    }

    /**
     * Get all the user  that the authenticated user can see
     * The permission SHOW USER must be set -> solo gli utenti dell'applicativo in cui l'utente è autenticato
     * The permission SHOW LOCAL_USER must be set -> tutti gli utenti degli applicativi che l'utente autenticato ha accesso
     * if the permission SHOW ALL_USERS is set all the users are returned -> tutti gli utenti di sistema
     *
     * SS: Verificare che l'utente abbia accesso all'applicativo, altrimenti se sono in modalità strict NON dovrei vederlo
     *
     * @param string    domain name
     * @param string    application code
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return bool     skip permissions
     * 
     * @access public
     */
    public function getUsersList($dn_name=null, $app_code=null, $data=array(), &$tot=0, $skipUseApplicationPerm=false)
    {
        $this->log('R3AuthManager::getUsersList() called.', AUTH_LOG_DEBUG);
        
        /* Check permission  */
        if ($this->hasPerm('SHOW', 'ALL_USERS')) {
            /* Show all users */
            $this->log('R3AuthManager::getUsersList(): All users are shown', AUTH_LOG_INFO);
            $user_where = '1 = 1';
            $domain_where = '1 = 1';
        } else if ($this->hasPerm('SHOW', 'USER')) {
            /* Show only the user of my domain */
            $this->log('R3AuthManager::getUsersList(): The users of my domain are shown.', AUTH_LOG_DEBUG);
            $user_where = '1 = 1';
            $domain_where = 'auth.domains_name.do_id=' . $this->domainID;
        } else if ($this->hasPerm('SHOW', 'LOCAL_USER')) {
            /* Show only the user of my domain */
            $this->log('R3AuthManager::getUsersList(): The users of my application are shown.', AUTH_LOG_DEBUG);
            $user_where = "us_id in (SELECT DISTINCT auth.users_acl.us_id FROM auth.users_acl
                          INNER JOIN auth.acnames ON
                          auth.users_acl.ac_id=auth.acnames.ac_id 
                          WHERE ua_kind = 'A' AND app_id = {$this->applicationID}
                          UNION
                          SELECT DISTINCT auth.users_groups.us_id FROM auth.users_groups
                          INNER JOIN auth.groups ON
                          auth.users_groups.gr_id=auth.groups.gr_id
                          WHERE app_id = {$this->applicationID})";
            $domain_where = 'auth.domains_name.do_id=' . $this->domainID;
        } else {
            /* Show all applications */
            $this->log('R3AuthManager::getUsersList(): The follows applications are shown: ' . $this->applicationCode . '.', AUTH_LOG_DEBUG);
            $user_where = 'us_id = ' . $this->UID;
            $domain_where = '1 = 1';
        }
       
		/* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'us_id, us_login, us_name, us_status, dn_name';
		}
        
        /* SQL where */
		$more_where = "us_status IN ('E', 'D')";
		if (isset($data['where']) && $data['where'] != '') {
			$more_where .= " AND {$data['where']}";
		}
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'us_login';
		}
        
        /* Create the query */
        $join_where = '1 = 1';
        $sql = "SELECT {$data['fields']}
               FROM auth.users
               INNER JOIN auth.domains_name ON
               auth.users.do_id=auth.domains_name.do_id ";
        
        if ($app_code != '') {
            /* application filter */
            $sql .= "INNER JOIN auth.domains_applications ON 
                    auth.users.do_id=auth.domains_applications.do_id
                    INNER JOIN auth.applications ON 
                    auth.domains_applications.app_id=auth.applications.app_id ";
            $join_where .= " AND app_code=" . $this->db->quote($app_code) . " ";
            
            if ($skipUseApplicationPerm) {
                $app_sql = ' 1=1 ';
            } else {
                $app_sql = "us_id in (SELECT DISTINCT auth.users_groups.us_id FROM auth.groups_acl
                           INNER JOIN auth.users_groups ON
                           auth.groups_acl.gr_id = auth.users_groups.gr_id
                           INNER JOIN auth.acnames ON
                           auth.groups_acl.ac_id = auth.acnames.ac_id
                           INNER JOIN auth.applications ON
                           auth.acnames.app_id=auth.applications.app_id
                           WHERE ac_verb='USE' AND ac_name='APPLICATION' AND ga_kind = 'A' AND ac_active = 'T' AND app_code = {$this->db->quote($app_code)}
                           
                           UNION
                           
                           SELECT DISTINCT auth.users_acl.us_id FROM auth.users_acl
                           INNER JOIN auth.acnames ON 
                           auth.users_acl.ac_id = auth.acnames.ac_id
                           INNER JOIN auth.applications ON
                           auth.acnames.app_id=auth.applications.app_id
                           WHERE ac_verb='USE' AND ac_name='APPLICATION' AND ua_kind = 'A' AND ac_active = 'T' AND app_code = " . $this->db->quote($app_code) . ")";
            }
            $join_where .= " AND \n  (us_id = " . SUPERUSER_UID . " OR $app_sql) ";  
        } 
        
        if ($dn_name != '') {
            $join_where .= " AND \n  dn_name=" . $this->db->quote($dn_name);
        }
        
        $sql .= " WHERE \n" . 
                "  dn_type = 'N' AND \n " . // SS: da verificare se � sempre ok
                "  ($join_where) AND \n" .
                "  ($domain_where) AND \n" .
                "  ($user_where) AND \n" .
                "  ($more_where) \n" .
                "ORDER BY \n" .
                "  " . $order;
         
        if (isset($data['sql'])) {
            // apply the other sql statement
            $sql = str_replace('<SQL>', "(" . $sql . ") user_manager\n", $data['sql']);
        }
        // echo $sql;
        $res = $this->executeStatement($sql, $data, $tot);
        $this->log('R3AuthManager::getUsersList() call end.', AUTH_LOG_DEBUG);
        return $res;
        //return $this->executeStatement($sql, $data, $tot);
    }

    /**
     * get a single user data
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the applications are returned
     *
     * @param string    The application code to get the data
     * @return mixed    return an array with all the fields, or null if the application is not found
     * SS: se getLockupData é false restituisce gruppi, permission e IP, altrimenti può essere un array con qesti elementi GROUPS, PERM, IP a seconda di cosa deve essere restituito
     * @access public
     */
    public function getUserData($dn_name, $app_code, $us_login, $getLockupData=false, $forceRealod=false, $skipUseApplicationPerm=false)
    {
        static $cacheUserData = array();
        static $cacheUserDataGroup = array();
        static $cacheUserDataPerm = array();
        static $cacheUserDataIP = array();
        
        $this->log('R3AuthManager::getUserData() called.', AUTH_LOG_DEBUG);
        if (!$forceRealod && isset($cacheUserData[$dn_name][$app_code][$us_login])) {
            /* Data cached */
            $this->log('R3AuthManager::getUserData() cached data returned.', AUTH_LOG_DEBUG);    
            $data = $cacheUserData[$dn_name][$app_code][$us_login];
        } else {
            /* Load data from database */
            $data = $this->getUsersList($dn_name, $app_code, array('fields'=>'*', 'where'=>'us_login=' . $this->db->quote($us_login)), $tot, $skipUseApplicationPerm);
            if (count($data) == 1 && $data[0]['as_id'] > 0) {
                $sql = "SELECT as_type, as_code, as_change_password FROM auth.auth_settings WHERE as_id={$data[0]['as_id']}";
				$authSettingsData = $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC);
                $data[0]['as_type'] = $authSettingsData[0]; //['as_type'];
				$data[0]['as_code'] = $authSettingsData[1]; //['as_code'];
				$data[0]['as_change_password'] = strtolower($authSettingsData[2]) == 't';  // ['as_change_password']
            }   
            $cacheUserData[$dn_name][$app_code][$us_login] = $data;
        }
     
        if (count($data) == 1) {
            if ($app_code == '') {
                $app_where = '1 = 1';
            } else {
                $app_where = 'app_code = ' . $this->db->quote($app_code);
            }
            $data = $data[0];

            /* A single group where found. Security is OK. */
            if ($getLockupData === true || is_array($getLockupData)) {
                if ($getLockupData === true || in_array('GROUPS', $getLockupData)) {
                    if (!$forceRealod && isset($cacheUserDataGroup[$dn_name][$app_code][$us_login]))  {
                        /* Data cached */
                        $this->log('R3AuthManager::getUserData() lockup cached data returned for GROUPS.', AUTH_LOG_DEBUG);    
                        $data['groups'] = $cacheUserDataGroup[$dn_name][$app_code][$us_login];
                    } else {
                        /* Get the groups list */
                        $sql = "SELECT app_code, app_name, gr_name
                               FROM auth.users_groups
                               INNER JOIN auth.groups ON
                               auth.users_groups.gr_id = auth.groups.gr_id
                               INNER JOIN auth.applications ON
                               auth.groups.app_id = auth.applications.app_id
                               WHERE us_id = {$data['us_id']} AND
                               {$app_where}
                               ORDER BY app_code, gr_name";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $data['groups'] = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $data['groups'][] = $value;
                        }
                        $cacheUserDataGroup[$dn_name][$app_code][$us_login] = $data['groups'];
                    }
                }
                
                if ($getLockupData === true || in_array('PERM', $getLockupData)) {
                    if (!$forceRealod && isset($cacheUserDataPerm[$dn_name][$app_code][$us_login]))  {
                        /* Data cached */
                        $this->log('R3AuthManager::getUserData() lockup cached data returned for PERM.', AUTH_LOG_DEBUG);    
                        $data['perm'] = $cacheUserDataPerm[$dn_name][$app_code][$us_login];
                    } else {
                        /* Get privileges list */
                        $sql = "SELECT app_code, ac_verb, ac_name, ua_kind
                               FROM auth.users_acl
                               INNER JOIN auth.acnames ON 
                               auth.users_acl.ac_id = auth.acnames.ac_id
                               INNER JOIN auth.applications ON
                               auth.acnames.app_id = auth.applications.app_id 
                               WHERE us_id = {$data['us_id']} AND
                               {$app_where}
                               ORDER BY app_code, ac_verb, ac_name";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $perm = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $a = array();
                            $a['app_code'] = $value['app_code'];
                            $a['ac_verb'] = $value['ac_verb'];
                            $a['ac_name'] = $value['ac_name'];
                            if (strtoupper($value['ua_kind']) == 'A') {
                                $a['kind'] = 'ALLOW';
                            } else {
                                $a['kind'] = 'DENY';
                            }
                            $perm[] = $a;
                        }
                        $data['perm'] = $perm;
                        $cacheUserDataPerm[$dn_name][$app_code][$us_login] = $perm;
                    }
                }
                
                if ($getLockupData === true || in_array('IP', $getLockupData)) {
                    if (!$forceRealod && isset($cacheUserDataIP[$dn_name][$app_code][$us_login])) {
                        /* Data cached */
                        $this->log('R3AuthManager::getUserData() lockup cached data returned for IP.', AUTH_LOG_DEBUG);    
                        $data['ip'] = $cacheUserDataIP[$dn_name][$app_code][$us_login];
                    } else {
                        /* Get IP list */
                        $sql = "SELECT app_code, ip_descr, ip_addr, ip_mask, ip_kind 
                               FROM auth.users_ip
                               LEFT JOIN auth.applications ON
                               auth.users_ip.app_id = auth.applications.app_id
                               WHERE us_id = {$data['us_id']} AND
                               {$app_where}
                               ORDER BY ip_order";
                        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
                        $ip = array();
                        foreach($this->executeStatement($sql) as $value) {
                            $a = array();
                            $a['app_code'] = $value['app_code'];
                            $a['ip_descr'] = $value['ip_descr'];
                            $a['ip_addr'] = $value['ip_addr'];
                            $a['ip_mask'] = $value['ip_mask'];
                            if ($value['ip_kind'] == 'A') {
                                $a['ip_kind'] = 'ALLOW';
                            } else {
                                $a['ip_kind'] = 'DENY';
                            }
                            $ip[] = $a;
                        }
                        $data['ip'] = $ip;
                        $cacheUserDataIP[$dn_name][$app_code][$us_login] = $ip;
                    }
                }
             }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Set the user groups for a single user
     *
     * @param integer   User ID
     * @param string    groups list
     * @access public
     */    
    private function setUserGroups($us_id, $groups, $oldUserData=null)
    {
        $stmt1 = $this->db->prepare('DELETE FROM auth.users_groups WHERE us_id = ?');
        $stmt1->execute(array($us_id));

        $stmt2 = $this->db->prepare('DELETE FROM auth.users_groups WHERE us_id = ? AND gr_id = ?');
        $stmt3 = $this->db->prepare('INSERT INTO auth.users_groups (us_id, gr_id) VALUES (?, ?) ');
        
        // Prevent user to change th own permission
        /*
        print_r($oldUserData);
        $perms = $this->doLoadPermission($appId, $this->UID);
        foreach($groupData['perm'] as $perm) {
            if ($perm['kind'] == 'ALLOW') {
                if (!$this->hasPerm($perm['ac_verb'], $perm['ac_name'])) {
                    $this->log(__METHOD__ . ': Missing privilege: ' . $perm['ac_verb'] . ' ' . $perm['ac_name'], AUTH_LOG_DEBUG);
                    $this->log(__METHOD__ . ": Permission denied.", AUTH_LOG_INFO);
                    throw new EPermissionDenied('Permission denied', 1);
                }
            }
        }
        */
        
        foreach($groups as $group) {
            $groupData = $this->getGroupData($group['app_code'], $group['gr_name'], true);
            if ($groupData === null) { 
                throw new Exception(__METHOD__ . ': group ' . $group['gr_name'] . ' not found.');
            }
            $appData = $this->getApplicationData($group['app_code']);
            if ($appData === null) { 
                throw new Exception(__METHOD__ . ': application ' . $group['app_code'] . ' not found.');
            }
            $myPerms = $this->doLoadPermission($appData['app_id'], $this->UID);
            
            if (!$this->isSuperuser()) {
                foreach($groupData['perm'] as $perm) {
                    if ($perm['kind'] == 'ALLOW') {
                        if (!isset($myPerms[$perm['ac_verb']][$perm['ac_name']])) {
                            $this->log(__METHOD__ . ': Missing privilege: ' . $perm['ac_verb'] . ' ' . $perm['ac_name'], AUTH_LOG_DEBUG);
                            $this->log(__METHOD__ . ': Permission denied.', AUTH_LOG_INFO);
                            throw new EPermissionDenied('Permission denied', 1);  
                        }
                    }
                }
            }
                    
            $gr_id = $groupData['gr_id'];
            $stmt2->execute(array($us_id, $gr_id));
            $stmt3->execute(array($us_id, $gr_id));
        }
    }
    
    /**
     * set all the user privileges
     *  SS: evitare che posso assegnare privilegi che l'utente corrente non abbia!!!
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function setUserPrivileges($us_id, $privileges, $oldUserData=null)
    {
        $stmt1 = $this->db->prepare('DELETE FROM auth.users_acl WHERE us_id = ? ');
        $stmt1->execute(array($us_id));

        $stmt2 = $this->db->prepare('DELETE FROM auth.users_acl WHERE us_id = ? AND ac_id = ?');
        $stmt3 = $this->db->prepare('INSERT INTO auth.users_acl (us_id, ac_id, ua_kind) VALUES (?, ?, ?) ');
                           
        foreach($privileges as $privilege) {
            $privilegeData = $this->getACNameData($privilege['app_code'], $privilege['ac_verb'], $privilege['ac_name']);
            if ($privilegeData === null) { 
                throw new Exception(__METHOD__ . ': ACName ' . $privilege['ac_verb'] . ' ' . $privilege['ac_name'] . ' not found.');
            }
            $appData = $this->getApplicationData($privilege['app_code']);
            if ($appData === null) { 
                throw new Exception(__METHOD__ . ': application ' . $group['app_code'] . ' not found.');
            }
            $myPerms = $this->doLoadPermission($appData['app_id'], $this->UID);
            if (!$this->isSuperuser() && $privilege['ua_kind'] == 'ON') {
                if (!isset($myPerms[$privilege['ac_verb']][$privilege['ac_name']])) {
                    $this->log(__METHOD__ . ': Missing privilege: ' . $privilege['ac_verb'] . ' ' . $privilege['ac_name'], AUTH_LOG_DEBUG);
                    $this->log(__METHOD__ . ': Permission denied.', AUTH_LOG_INFO);
                    throw new EPermissionDenied('Permission denied', 1);  
                }
            }
            
            $ac_id = $privilegeData['ac_id'];
            $ua_kind = $privilege['ua_kind'] == 'ON' ? 'A' : 'D';

            $stmt2->execute(array($us_id, $ac_id));
            $stmt3->execute(array($us_id, $ac_id, $ua_kind));
        }
    }

    /**
     * set teh user's IP
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    private function setUserIP($us_id, $ips, $oldUserData=null)
    {
        $stmt1 = $this->db->prepare('DELETE FROM auth.users_ip WHERE us_id = ?');
        $stmt1->execute(array($us_id));
        
        $stmt2 = $this->db->prepare('DELETE FROM auth.users_ip WHERE us_id = ?  ' .
                                    ' AND app_id = ? AND ip_addr = ? AND ip_mask = ?');
        $stmt3 = $this->db->prepare('INSERT INTO auth.users_ip (us_id, app_id, ' .
                                    ' ip_addr, ip_mask, ip_kind, ip_descr, ip_order) ' .
                                    ' VALUES (?, ?, ?, ?, ?, ?, ?) ');
        
        foreach($ips as $ip) {
            if (isset($ip['app_code'])) {
                $applicationData = $this->getApplicationData($ip['app_code']);
                if ($applicationData === null) { 
                    throw new Exception(__METHOD__ . ': Application ' . $ip['app_code'] . ' not found.');
                }
                $app_id = $applicationData['app_id'];
            } else {
                $app_id = null;
            }

            $stmt2->execute(array($us_id, $app_id, $ip['ip_addr'], $ip['ip_mask']));
            $stmt3->execute(array(
                $us_id,
                $app_id,
                substr($ip['ip_addr'], 0, 15),
                substr($ip['ip_mask'], 0, 15),
                substr($ip['ip_kind'], 0, 1),
                substr($ip['ip_descr'], 0, 20),
                $ip['ip_order'],
            ));
        }        
    }

    /**
     * Execute the DML to insert/update/delete of a user
     *  on success returns the user id.
     *  Permission and integrity are checked here.
     *
     * @param string    DML type 'ADD', 'MOD', 'DEL'
     * @param string    old-application code. User only for update
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return integer  true on success
     */
    private function execUserDML($dmlKind, $old_dn_name, $old_us_login, $dn_name, $us_login, $data, $extra_data)
    {
        //print_r($extra_data);
        //print_r($data);

        // check permissions
        if (!$this->hasPerm($dmlKind, 'ALL_USERS') &&
            !$this->hasPerm($dmlKind, 'USER') &&
            !$this->hasPerm($dmlKind, 'LOCAL_USER')) {
            $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }
        if (!$this->hasPerm($dmlKind, 'ALL_USERS') && $dn_name != $this->domain &&
            ($old_dn_name !== null || $old_dn_name !== $this->domain)) {
            $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }
        if (!$this->isSuperUser() && $dmlKind != 'ADD') {
            if ($this->compareUserPerm($dmlKind == 'MOD' ? $old_dn_name : $dn_name, $this->hasPerm('SHOW', 'APPLICATION') ? null : $this->applicationCode, $dmlKind == 'MOD' ? $old_us_login : $us_login) < 0) {
                $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
                throw new EPermissionDenied('Permission denied', 1);
            }
        }

        // Check inputs
        if ($dmlKind != 'DEL') {
            if ($dn_name == '') {
                throw new EInputError(__METHOD__ . ': Missing domain name', 'dn_name', MISSING_FIELD);
            }
            if ($us_login == '') {
                throw new EInputError(__METHOD__ . ': Missing login', 'us_login', MISSING_FIELD);
            }
            if (!isset($data['us_name']) || $data['us_name'] == '') {
                throw new EInputError(__METHOD__ . ': Missing name', 'us_name', MISSING_FIELD);
            }

            if (!$this->validCharsRegEx($dn_name)) {
                throw new EInputError(__METHOD__ . ': Invalid domain name', 'dn_name ', INVALID_FIELD);
            }
            if (!$this->validCharsRegEx($us_login)) {
                throw new EInputError(__METHOD__ . ': Invalid login', 'us_login', INVALID_FIELD);
            }
        }

        $domainData = $this->getDomainData($dn_name);

        if ($domainData === null) {
            throw new EInputError(__METHOD__ . ': Invalid domain name', 'dn_name', INVALID_FIELD);
        }
        $do_id = $domainData['do_id'];

        // Check if users already exists or does not exists
        $userData = $this->getUserData($dn_name, null, $us_login);
        if ($dmlKind == 'ADD' && $userData !== null) {
            throw new EInputError(__METHOD__ . ': User already exists', 'dn_name, us_login', PK_ERROR);
        } else if ($dmlKind == 'MOD') {
            $oldUserData = $this->getUserData($old_dn_name, null, $old_us_login);
            if ($oldUserData === null) {
                throw new EInputError(__METHOD__ . ': User does not exists (to edit)', 'dn_name, us_login', PK_NOT_FOUND);
            } else if ($old_dn_name != $dn_name &&
                    $us_login != $us_login && $userData !== null) {
                throw new EInputError(__METHOD__ . ': User already exists', 'dn_name, us_login', PK_ERROR);
            }
        } else if ($dmlKind == 'DEL') {
            if ($userData === null) {
                throw new EInputError(__METHOD__ . ': User does not exists (to delete)', 'dn_name, us_login', PK_NOT_FOUND);
            }
            if (!$this->isSuperuser()) {
                // Verifico per tutte le applicazioni che l'utente che cancella abbia le almeno le stesse permission dell'utente che vuole cancellare
                $domainData = $this->getDomainData($dn_name, true);
                foreach ($domainData['applications'] as $appKey => $appVal) {
                    $sql = "SELECT app_id FROM auth.applications WHERE app_code='$appKey'";
                    $a = $this->executeStatement($sql, array());
                    $appId = $a[0]['app_id'];
                    $perms = $this->doLoadPermission($appId, $this->UID);
                    $permsToDel = $this->doLoadPermission($appId, $userData['us_id']);
                    foreach ($permsToDel as $key1 => $val1) {
                        foreach ($val1 as $key2 => $val2) {
                            $found = false;
                            foreach ($perms as $ukey1 => $uval1) {
                                if ($found) {
                                    break;
                                }
                                foreach ($uval1 as $ukey2 => $uval2) {
                                    if ($key1 == $ukey1 && $key2 == $ukey2) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found) {
                                $this->log(__METHOD__ . ": Permission denied for $dmlKind.", AUTH_LOG_INFO);
                                throw new EPermissionDenied('Permission denied', 1);
                            }
                        }
                    }
                }
            }
        }

        // Prepare input data
        if ($dmlKind != 'DEL') {
            //SS: Se la password è remota potrei NON volerla salvare in locale!
            $pass = substr(trim($data['us_password']), 0, 32);

            if ($pass != '') {
                $pass = md5($pass);
            } else {
                $pass = null;
            }

            $data = array_merge(array('forceChangePassword' => false), $data);

            $needPassword = true;
            $dbFields = array(
                'us_login' => $us_login,
                'us_status' => strtoupper(substr($data['us_status'], 0, 1)),
                'do_id' => $do_id,
                'us_name' => $data['us_name'],
                'us_pw_expire' => $data['us_pw_expire'] == '' ? null : (int) $data['us_pw_expire'],
                'us_pw_expire_alert' => $data['us_pw_expire_alert'] == '' ? null : (int) $data['us_pw_expire_alert'],
                'us_pw_last_change' => ($data['forceChangePassword'] ? null : date('Y-m-d H:i:s')),
                'us_start_date' => $data['us_start_date'],
                'us_expire_date' => $data['us_expire_date'],
                'us_mod_user' => $this->UID,
                'us_mod_date' => date('Y-m-d H:i:s'));

            if ($data['as_code'] == '') {
                $as_id = null;
            } else {
                $sql = "SELECT as_id FROM auth.auth_settings WHERE as_code=" . $this->db->quote($data['as_code']);
                $as_id = $this->db->query($sql)->fetchColumn(0);
                $needPassword = false;  // Extranal password
            }
            $dbFields['as_id'] = $as_id;

            if ($pass != null) {
                $dbFields['us_password'] = $pass;
            }
            // Check if user need password
            if ($pass == '' && $needPassword) {
                if ($dmlKind == 'MOD') {
                    $sql = "SELECT LENGTH(COALESCE(us_password, '')) FROM auth.users WHERE us_id={$oldUserData['us_id']}";
                    $needPassword = $this->db->query($sql)->fetchColumn(0) == 0;
                }
                if ($needPassword) {
                    // Password needed
                    throw new Exception('Missing password');
                }
            }

            // merge extra data with database fields
            if (is_array($extra_data)) {
                $dbFields = array_merge($extra_data, $dbFields);
            }
        }

        // Prepare the sql statement
        switch($dmlKind) {
            case 'ADD':
                $userId = $this->nextID("auth.users_us_id_seq");
                $stmtData = array_values($dbFields);
                $stmtData[] = $userId;
                $stmtFields = array_keys($dbFields);
                $stmtFields[] = 'us_id';

                $stmtSql = 'INSERT INTO auth.users ('.implode(', ', $stmtFields). ') ' .
                            ' VALUES (' . implode(', ', array_fill(0, count($stmtFields), "?")) . ') ';
                break;
            case 'MOD':
                $userId = $oldUserData['us_id'];
                $stmtData = array_values($dbFields);
                $stmtData[] = $userId;
                $stmtFields = array_keys($dbFields);

                $stmtSql = 'UPDATE auth.users SET '.implode(' = ?, ', $stmtFields). ' = ? ' .
                            ' WHERE us_id = ? ';
                break;
            case 'DEL':
                $userId = $userData['us_id'];
                $stmtData = array($userId);
                $stmtSql = 'DELETE FROM auth.users WHERE us_id = ? ';
                break;
        }
        
        $this->beginTransaction();
        try {
            // users table
            $stmt = $this->db->prepare($stmtSql);
            $stmt->execute($stmtData);
            $affectedRows = $stmt->rowCount();
            if ($affectedRows != 1) {
                throw new Exception(__METHOD__ . ": affected rows are {$affectedRows} instead of 1");
            }

            if ($dmlKind != 'DEL' && $userId != $this->UID) {
                if (isset($data['groups'])) {
                    $this->setUserGroups($userId, $data['groups'], $userData);
                }

                if (isset($data['perms'])) {
                    $this->setUserPrivileges($userId, $data['perms'], $userData);
                }

                if (isset($data['ip'])) {
                    $this->setUserIP($userId, $data['ip'], $userData);
                }
            }
            
            $this->commitTransaction();
        } catch (Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        }

        return $userId;
    }

    /**
     * Add a new user using an array for the all the data (exept domain name and login)
     *  on success returns the users id.
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return integer
     */
    public function addUserFromArray($dn_name, $us_login, $data=array(), $extra=array())
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execUserDML('ADD', null, null, $dn_name, $us_login, $data, $extra);
    }
    
    /**
     * Modify an existing user using an array for the all the data (exept domain name and login)
     *  on success returns the users id.
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return integer
     */
    public function modUserFromArray($old_dn_name, $old_us_login, $dn_name, $us_login, $data=array(), $extra=array())
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->execUserDML('MOD', $old_dn_name, $old_us_login, $dn_name, $us_login, $data, $extra);
    }
    
    /**
     * Remove an existing user using an array for the all the data (exept domain name and login)
     *  on success returns the users id.
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return integer
     */
    public function delUser($dn_name, $us_login)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        
        if ($dn_name == $this->domain && $us_login == $this->login) {
            throw new EDeleteError(__METHOD__ . ': can\'t detele yourself.', 'us_login', IN_USE);
        }

        try {
            // try to delete user, could fail if a foreign key is violated
            return $this->execUserDML('DEL', null, null, $dn_name, $us_login, null, null);
        } catch (Exception $e) {
            $this->log(__METHOD__ . ': ' . $e->getMessage() . '. Change status to "X"');
            $data = $this->getUserData($dn_name, null, $us_login);
            $data['us_status'] = 'X';
            return $this->modUserFromArray($dn_name, $us_login, $dn_name, $us_login, $data, array());
        }
    }
  
    /**
     * Restituisce l'elenco di utenti attualmente connessi
     */
    public function getConnectedUsersList($dn_name=null, $app_code=null, $data=array(), &$tot=0, $timeout=null)
    {
        if (!isset($data['fields'])) {
            $data['fields'] = 'us_id, us_login, us_name, us_status, dn_name, us_last_ip, us_last_login, us_last_action';
        }
        if (!isset($data['where'])) {
            if ($timeout === null) {    
                $timeout = ini_get("session.gc_maxlifetime") * 60;
            }
            $maxtime = date('Y-m-d H:i:s', time() - $timeout);
            $data['where'] = "us_last_action>'$maxtime'";
        }
        return $this->getUsersList($dn_name, $app_code, $data, $tot);
    }
    
    /**
     * Return the log data / BETA FUNCTION. PERMISSION NOT CHECKED!!!!
     * if the permission SHOW LOG is set all the appliocations are returned
     *
     * @param mixed     one or more application codes
     * @param mixed     one or more domain names
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of entries
     * @return array    a numeric array with all the domains
     * @access public
     */
    public function getLogList($dn_name=null, $app_code=null, $data = array(), &$tot = 0, $multiple = false)
    {
        // static $lastSQL = '';
        // static $lastResult = array();
        
        $this->log('R3AuthManager::getLogList() called.', AUTH_LOG_DEBUG);
        
        
        if ($this->hasPerm('SHOW', 'DOMAIN') || 
            $this->hasPerm('SHOW', 'ALL_DOMAINS')) {
            /* Show all domains */
            $this->log('R3AuthManager::getLogList(): All domains are shown', AUTH_LOG_INFO);
            $domain_where = '1 = 1';       /* All domain should be shown */
        } else { 
            /* Show only my domain */
            $this->log('R3AuthManager::getLogList(): Only the follow domain is shown: ' . $this->domain . '.', AUTH_LOG_DEBUG);
            $domain_where = "auth.domains.do_id= {$this->domainID}";
        }
        
        if ($this->hasPerm('SHOW', 'APPLICATION') || 
            $this->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
            /* Show all domains */
            $this->log('R3AuthManager::getLogList(): All applications are shown', AUTH_LOG_INFO);
            $app_where = '1 = 1';       /* All domain should be shown */
        } else { 
            /* Show only my domain */
            $this->log('R3AuthManager::getLogList(): Only the follow application is shown: ' . $this->domain . '.', AUTH_LOG_DEBUG);
            $app_where = "auth.application.app_id= {$this->applicationID}";
        }
        
        /* SQL filter where - domain */
		if ($dn_name !== null) {
			$domain_filter_where = 'dn_name = ' . $this->db->quote($dn_name);
		} else {
			$domain_filter_where = '1 = 1';
		}
        /* SQL filter where */
		if ($app_code !== null) {
			$app_filter_where = 'app_code = ' . $this->db->quote($app_code);
		} else {
			$app_filter_where = '1 = 1';
		}
        
        
        /* SQL fields */
		if (!isset($data['fields']) || $data['fields'] == '') {
			$data['fields'] = 'log_id, dn_name, app_code, app_name, log.us_id, us_login, us_name, log_type, log_auth_type, log_time, log_ip, log_page, log_text';
		}
        
        /* SQL where */
		if (isset($data['where']) && $data['where'] != '') {
			$more_where = $data['where'];
		} else {
			$more_where = '1 = 1';
		}
        
        /* SQL order */
		if (isset($data['order']) && $data['order'] != '') {
			$order = $data['order'];
		} else {
			$order = 'log_id DESC';
		}
        
        /* Execute the query */
        $sql = "SELECT {$data['fields']}
               FROM auth.logs log
               LEFT JOIN auth.domains_name dm ON log.do_id=dm.do_id
               LEFT JOIN auth.applications app ON log.app_id=app.app_id
               LEFT JOIN auth.users us ON log.us_id=us.us_id 
               WHERE 
                    ($domain_where) AND
                    ($app_where) AND
                    ($domain_filter_where) AND
                    ($app_filter_where) AND
                    ($more_where)
               ORDER BY $order";
        // echo nl2br(str_replace(' ', '&nbsp;', $sql));
        return $this->executeStatement($sql, $data, $tot);
    }
    
    /**
     * SS: transazione
     */
    public function modUser()
    {
        die('not implemented');
    }
    
    /**
     * return true if the user is member of the specified group
     * The permission ???? must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function isMemberOfGroup($dn_name, $us_login, $app_code, $gr_name)
    {
        $userData = $this->getUserData($dn_name, $app_code, $us_login, true, false, true);
        if (is_array($userData['groups'])) {
            foreach($userData['groups'] as $grp) {
                if ($grp['app_code'] == $app_code &&
                    $grp['gr_name'] == $gr_name) {
                    return true;    
                }
            }
        }
        return false;
    }           
    
    /**
     * return true if the current user has all the permission of a specified group
     * The permission ???? must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function hasAllPermsOfGroup($app_code, $gr_name)
    {
        if ($this->isSuperuser()) {
            return true;
        }
        
        // Carica tutte le permission del gruppo
        $groupData = $this->getGroupData($app_code, $gr_name, true);
        
        // Carica tutte le permisison dell'utente corrente per l'applicativo indicato
        $app = $this->getApplicationsList(array('fields'=>'app_id', 'where'=>'app_code=' . $this->db->quote($app_code)), $tot);
        if ($tot <> 1) {
            throw new EInputError('hasAllPermsOfGroup: Invalid application code', 'app_code', INVALID_FIELD);  
        }
        $app_id = $app[0]['app_id'];
               
        $perms = $this->doLoadPermission($app_id, $this->UID);
        
        foreach ($groupData['perm'] as $perm) {
            if ($perm['kind'] == 'ALLOW') {
                
                if (!(isset($perms[$perm['ac_verb']][$perm['ac_name']]) && 
                      $perms[$perm['ac_verb']][$perm['ac_name']] == true)) {
                         $this->log(sprintf('R3AuthManager::hasAllPermsOfGroup(): Missing permisison for application %s, group %s: %s %s', $app_code, $gr_name, $perm['ac_verb'], $perm['ac_name']), AUTH_LOG_DEBUG);
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * return true if the user is member of the specified group
     * The permission ???? must be set
     *
     * @param string    application code. Only chars, number and some symbols are accepted (the space is invalid)
     * @param array     application name
     * @return boolean  true on success
     * @access public
     */
    public function hasPermForApplication($dn_name, $us_login, $app_code, $ac_verb, $ac_name, $kind = 'ALLOW', $checkGroup=true, $checkUser=true)
    {
        $userData = $this->getUserData($dn_name, $app_code, $us_login, true);
        
        if ($userData === null) {
            return false;
        }
        if ($checkGroup) {
            throw new Exception ('hasPermForApplication: not implemented!');
        }
        if ($checkUser) {
            foreach($userData['perm'] as $perm) {
                if ($perm['app_code'] == $app_code &&
                    $perm['ac_verb'] == $ac_verb &&
                    $perm['ac_name'] == $ac_name &&
                    $perm['kind'] == $kind) {
                     
                    return true;
                }
            }
        }
        return false;
    } 
    
    /**
     * Restituisce l'elenco delle applicazioni a cui l'utente ha accesso.
     * Se strict è true verifica che sia presente la permission USE APPLICATION
     */
    public function availableApplication($strict=false)
    {
        if (!$this->isSuperuser()) {
            $sql1 = "SELECT DISTINCT app_id 
                    FROM auth.acnames 
                    INNER JOIN auth.users_acl ON
                    auth.acnames.ac_id = auth.users_acl.ac_id";
            if ($strict) {
                $sql1 .= "WHERE ac_verb='USE' and ac_name='APPLICATION'\n";
            }
            $sql2 = "SELECT DISTINCT app_id 
                    FROM auth.acnames
                    INNER JOIN auth.groups_acl ON
                    auth.acnames.ac_id = auth.groups_acl.ac_id";
            if ($strict) {
                $sql2 .= "WHERE ac_verb='USE' and ac_name='APPLICATION'";
            }
        
            $sql = "SELECT * FROM auth.applications
                   WHERE app_id IN ($sql1 UNION $sql2)";
        } else {
            $sql = "SELECT * FROM auth.applications";
        }
        
        // echo $sql;
        return $this->executeStatement($sql, $data, $tot);
    }

    /**
     * Come loadConfig, ma per un qualunque dominio, applicazione, utente
     *  SS: TODO: Mettere permission per caricare/salvare configurazioni di altri utenti
     */
    public function loadConfigFor($dn_name=null, $app_code=null, $us_login=null)
    {
        static $last_dn_name = null;
        static $last_app_code = null;
        static $last_us_login = null;
        
        if ($dn_name != $last_dn_name || $app_code != $last_app_code || $us_login != $last_us_login) {
            
            if ($dn_name === null && $app_code === null && $us_login === null) {
                // Force reload
                $this->dbini = null;
            } else {
                // SS: Removed at2010-07-13. A che cosa serviva???
                //$data = $this->getUserData($dn_name, $app_code, $us_login);
                //if ($data === null) {
                //    return false;
                //}
                $this->dbini = new R3DBIni($this->db, $this->options['options'], $dn_name, $app_code, $us_login);
            }
        }
        $last_dn_name = $dn_name;
        $last_app_code = $app_code;
        $last_us_login = $us_login;
        return true;            
    }

    /**
     * Restituisce un singolo parametro di configurazione
     */
    public function getConfigValueFor($dn_name=null, $app_code=null, $us_login=null, $se_section=null, $se_param=null, $default=null)
    {
        $dbini = new R3DBIni($this->db, $this->options['options'], $dn_name, $app_code, $us_login);
        return $dbini->getValue($se_section, $se_param, $default);
    }

    /**
     * Salvo un singolo valore di configurazione per un utente diverso dal mio
     */
    public function setConfigValueFor($dn_name, $app_code, $us_login, $se_section, $se_param, $value)
    {
        $dbini = new R3DBIni($this->db, $this->options['options'], $dn_name, $app_code, $us_login);
        $dbini->setValue($se_section, $se_param, $value);
    }

    /**
     * Restituisce 0 se NON ha il permesso, +1 se il permesso è OK, -1 se è negato. Come sopra, ma con l'applicazione
     *
     * @todo seems unused
     * @deprecated
     */
    public static function hasPermApplication($permList, $application, $verb, $name)
    {
        // print_r($permList);
        //echo "$application, $verb, $name";
        foreach($permList as $perm) {
            if ($perm['app_code'] == $application &&
                $perm['ac_verb'] == $verb &&
                $perm['ac_name'] == $name) {
                if ($perm['kind'] == 'ON') {
                    return 1;
                }
                return -1;
            }       

        }
        return 0;
    }
                
    /**
     * Restituisce true le l'applicazione code è in $applications
     *
     * @todo seems unused
     * @deprecated
     */
    public static function hasApplication($applicationList, $code)
    {
        foreach($applicationList as $application) {
            if ($application['app_code'] == $code) {
                return true;
            }
        }
        return false;         
    }

    /**
     * Restituisce true se membro di un gruppo. Mettere in auth!
     *
     * @todo seems unused
     * @deprecated
     */
    public static function isMemberOfDomain($groupList, $domain)
    {
        foreach($groupList as $value) {
            if ($value['dn_name'] == $domain) {
                return true;
            }
        }
        return false;
    }

    /**
     * Restituisce un array con gli indirizzi IP presenti in una stringa
     * Ogni riga è un ip
     * opzionale: descrizione seguito da :
     *  ip
     *  opzionale / mask
     * opzionale allow o deny
     * usare una regex
     * rivedere questa funzione!
     */
    public static function strToIPArray($text)
    {
        $retVal = array();
        $text = trim($text);
        if ($text == '') {
            return $retVal;
        }

        $lines = explode("\n", $text);
        $lineCount = 0;
        $tot = 0;
        foreach($lines as $line) {
            $line = trim($line);
            $lineCount++;
            $retVal[$tot]['ip_descr'] = '';
            if ($line != '') {
                $s = explode(':', $line);
                if (count($s) == 2) {
                    $text = trim($s[0]);
                    $line = trim($s[1]);
                } else {
                    $text = '';
                }
                $s = explode('/', $line);
                if (count($s) == 1) {
                    $s[1] = '255.255.255.255';
                }
                if (count($s) == 2) {
                    $retVal[$tot]['ip_descr'] = $text;
                    $retVal[$tot]['ip_addr'] = $s[0];
                    if (($p = strpos($s[1], ' ')) === false) {
                        $retVal[$tot]['ip_mask'] = $s[1];
                        $retVal[$tot]['ip_kind'] = 'ALLOW';
                    } else {
                        $retVal[$tot]['ip_mask'] = substr($s[1], 0, $p);
                        $s = strtoupper(trim(substr($s[1], $p + 1)));
                        $retVal[$tot]['ip_kind'] = ($s == '' || $s == 'ALLOW' ? 'ALLOW' : 'DENY');
                    }
                    $retVal[$tot]['ip_order'] = $lineCount;
          //          if (isValidIP($s[0]) && isValidIP($s[1]))
                      //$retVal[$tot]['status'] = 'OK';
                    //else
          //            $retVal[$tot]['status'] = 'ERROR';
                    $tot++;
                }
            }
        }
        return $retVal;
    }
  
    /**
     * contrario di strToIPArray
     */
    public static function arrayIPToString($array)
    {
        $a = array();
        if (is_array($array)) {
            foreach($array as $value) {
                if ($value['ip_descr'] == '') {
                    $s = $value['ip_addr'];
                } else {
                    $s = $value['ip_descr'] . ': ' . $value['ip_addr'];
                }
                if ($value['ip_mask'] <> '' && $value['ip_mask'] <> '255.255.255.255') {
                    $s .= '/' . $value['ip_mask'];
                }
                if ($value['ip_kind'] <> 'ALLOW') {
                    $s .= ' DENY';
                }
                $a[] = $s;
            }
        }
        return implode("\n", $a);
    }

    /**
     * Return TRUE if a user has the specified persission
     *
     * @param mixed     one or more application codes
     * @param mixed     one or more domain names
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return array    a numeric array with all the applications
     * @access public
     */
    public function userHasPerm($dn_name, $app_code, $us_login, $verb, $name, $forceReload = false) //, $includeSuperuser=true)
    {
        static $userPermCache = array();
        
        // echo "[$dn_name, $app_code, $us_login, $verb, $name]<br />\n";
        
        /* Doamin permission check */
        if (!$this->hasPerm('SHOW', 'LOCAL_DOMAIN') && 
            !$this->hasPerm('SHOW', 'DOMAIN') && 
            !$this->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
		} else if (!$this->hasPerm('SHOW', 'ALL_DOMAINS') && $dn_name != $this->domain) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
		}
        
        if (!$this->hasPerm('SHOW', 'DOMAIN') && 
            !$this->hasPerm('SHOW', 'ALL_DOMAINS') && 
            $dn_name <> $this->domain) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
        }    
        
        /* Application permission check */
        if (!$this->hasPerm('SHOW', 'LOCAL_APPLICATION') && 
            !$this->hasPerm('SHOW', 'APPLICATION') && 
            !$this->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
		} else if (!$this->hasPerm('SHOW', 'ALL_APPLICATIONS') && $app_code != $this->application) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied: ALL_APPLICATIONS/SHOW', 1);
		}
        
        if (!$this->hasPerm('SHOW', 'DOMAIN') && 
            !$this->hasPerm('SHOW', 'ALL_DOMAINS') && 
            $app_code <> $this->applicationCode) {
            $this->log("R3AuthManager::userHasPerm(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
        } 
        
        if (!$forceReload && isset($userPermCache[$dn_name][$app_code][$us_login])) {
            return (isset($userPermCache[$dn_name][$app_code][$us_login][$verb][$name]));
        }
        
        /* get the app_id */
        $userData = $this->getUserData($dn_name, $app_code, $us_login, false, $forceReload);
        if ($userData === null) {
            /* User not found for the specified application */
            return false;
        }
        //if ($includeSuperuser && $userData['us_id'] == SUPERUSER_UID) {
        //    return true;
        //}
        
        //echo "[$dn_name, $app_code, $us_login, $verb, $name, $includeSuperuser]<br />\n";
        $perms = $this->doLoadPermission($userData['app_id'], $userData['us_id'], true);
        //print_r($perms);
        
        $userPermCache[$dn_name][$app_code][$us_login] = $perms;
        
        return (isset($userPermCache[$dn_name][$app_code][$us_login][$verb][$name]));
    }

    /**
     * Compare my permisson with the permission of another user. 
     *
     * @param mixed     one or more application codes
     * @param mixed     one or more domain names
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return integer  Returns < 0 if I have less permission than the other user, 
     *                          = 0 if the permisson are at least the same, 
     * @access public
     */
    public function compareUserPerm($dn_name, $app_code, $us_login)
    {
        //static $userPermCache = array();
        
        // SS: Security check!
        $app_codes = array();
        if ($app_code === null) {
            $data = $this->getDomainData($dn_name, true);
            $domainData = $this->getDomainData($this->domain, true);
            foreach($domainData['applications'] as $key=>$value) {
                $app_codes[] = $key;
            }        
        } else {
            $app_codes[] = $app_code;
        }
        
        foreach($app_codes as $app_code) {
            $appData = $this->getApplicationData($app_code);
            if ($appData === null) {
                /* Cerco di avere informazioni su un applicativo senza permission SHOW APPLICATION */
                $this->log("R3AuthManager::compareUserPerm(): Permission denied.", AUTH_LOG_INFO);
                throw new EPermissionDenied('Permission denied', 1);
            }
            $userData = $this->getUserData($dn_name, $app_code, $us_login);
            $myPerms = $this->doLoadPermission($appData['app_id'], $this->UID);
            $userPerms = $this->doLoadPermission($appData['app_id'], $userData['us_id']);
            foreach($userPerms as $key1 => $value1) {
                foreach($value1 as $key2 => $value2) {
                    if (!isset($myPerms[$key1][$key2])) {
                        return -1;
                    }
                }
            }
        }
        return 0;
    }

    public function disconnectUser($dn_name, $us_login) {
        if (!$this->hasPerm('DISCONNECT', 'USER')) {
            $this->log("R3AuthManager::disconnectUser(): Permission denied.", AUTH_LOG_INFO);
		    throw new EPermissionDenied('Permission denied', 1);
		}
        $userData = $this->getUserData($dn_name, null, $us_login);
        if ($userData === null) {
            throw new Exception("disconnectUser: user $dn_name $us_login not found.");
        }
        $sql = "UPDATE auth.users SET 
               us_last_action = null
               WHERE us_id=" . $userData['us_id'];
        $this->db->exec($sql);
        $this->log("R3Auth::disconnectUser(): User $dn_name $us_login disconnected", AUTH_LOG_DEBUG);
        return true;
    }
}       
