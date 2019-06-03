<?php

/**
 * Storage driver for use against PEAR PDO
 *
 * @author Daniel Degasperi <daniel.degasperi@r3-gis.com>
 */

/**
 * Include Auth_Container base class
 */
require_once 'Auth/Container.php';

/**
 * Storage driver for fetching login data from a database
 *
 * This storage driver can use all databases which are supported
 * by the PDO abstraction layer to fetch login data.
 *
 * @author Daniel Degasperi <daniel.degasperi@r3-gis.com>
 */
class Auth_Container_PDO extends \Auth_Container
{
    
    /**
     * Database instance
     * 
     * @var \PDO
     */
    private $db;

    /**
     * Additional options for the storage container
     * @var array
     */
    private $options = array();

    /**
     * User that is currently selected from the DB.
     * @var string
     */
    public $activeUser = '';

    /**
     * Constructor of the container class
     * 
     * @param \PDO
     */
    public function __construct(\PDO $db, array $customOptions = array())
    {
        $this->db = $db;

        $defaultOptions = array(
            $this->options['cryptType']   = 'md5',
            $this->options['db_where']    = '',
        );

        $this->options = array_merge($defaultOptions, $customOptions);
    }

    /**
     * Prepare query to the database
     *
     * This function checks if we have already opened a connection to
     * the database. If that's not the case, a new connection is opened.
     * After that the query is passed to the database.
     *
     * @access public
     * @param  string Query string
     * @return mixed  a MDB_result object or MDB_OK on success, a MDB
     *                or PEAR error on failure
     */
    public function query($query)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        return $this->db->exec($query);
    }

    /**
     * Get user information from database
     *
     * This function uses the given username to fetch
     * the corresponding login data from the database
     * table. If an account that matches the passed username
     * and password is found, the function returns true.
     * Otherwise it returns false.
     *
     * @param   string Username
     * @param   string Password
     * @param   boolean If true password is secured using a md5 hash
     *                  the frontend and auth are responsible for making sure the container supports
     *                  challenge response password authentication
     * @return  mixed  Error object or boolean
     */
    public function fetchData($username, $password, $isChallengeResponse=false)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);
        
        $query = sprintf(
            "SELECT us_login, us_password FROM auth.users WHERE us_login = %s",
            $this->db->quote($username)
        );

        // check if there is an optional parameter db_where
        if ($this->options['db_where'] != '') {
            // there is one, so add it to the query
            $query .= " AND ".$this->options['db_where'];
        }

        $this->log('Running SQL against PDO: '.$query, AUTH_LOG_DEBUG);

        $res = $this->db->query($query)->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($res)) {
            $this->activeUser = '';
            return false;
        }

        // Perform trimming here before the hashing
        $password = trim($password, "\r\n");
        $res['us_password'] = trim($res['us_password'], "\r\n");
        // If using Challenge Response md5 the pass with the secret
        if ($isChallengeResponse) {
            $res['us_password'] =
                md5($res['us_password'].$this->_auth_obj->session['loginchallenege']);
            // UGLY cannot avoid without modifying verifyPassword
            if ($this->options['cryptType'] == 'md5') {
                $res['us_password'] = md5($res['us_password']);
            }
        }
        if ($this->verifyPassword($password, $res['us_password'], $this->options['cryptType'])) {
            return true;
        }

        $this->activeUser = $res['us_login'];
        return false;
    }

    /**
     * Returns a list of users from the container
     *
     * @return mixed array|PEAR_Error
     * @access public
     */
    public function listUsers()
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);

        $retVal = array();

        $query = 'SELECT us_login, us_password FROM auth.users';

        // check if there is an optional parameter db_where
        if ($this->options['db_where'] != '') {
            // there is one, so add it to the query
            $query .= " WHERE ".$this->options['db_where'];
        }

        $this->log('Running SQL against PDO: '.$query, AUTH_LOG_DEBUG);

        $r = $this->db->query($query);
        while($user = $r->fetch(\PDO::FETCH_ASSOC)) {
            $user['username'] = $user['us_login'];
            $retVal[] = $user;
        }
        $this->log('Found '.count($retVal).' users.', AUTH_LOG_DEBUG);
        return $retVal;
    }

    /**
     * Add user to the storage container
     *
     * @access public
     * @param  string Username
     * @param  string Password
     * @param  mixed  Additional information that are stored in the DB
     *
     * @return mixed True on success, otherwise error object
     */
    public function addUser($username, $password, $additional = "")
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);

        if (isset($this->options['cryptType']) && $this->options['cryptType'] == 'none') {
            $cryptFunction = 'strval';
        } elseif (isset($this->options['cryptType']) && function_exists($this->options['cryptType'])) {
            $cryptFunction = $this->options['cryptType'];
        } else {
            $cryptFunction = 'md5';
        }

        $password = $cryptFunction($password);

        $additional_key   = '';
        $additional_value = '';

        if (is_array($additional)) {
            foreach ($additional as $key => $value) {
                $additional_key   .= ', ' . $key;
                $additional_value .= ', ' . $this->db->quote($value);
            }
        }

        $query = sprintf(
            "INSERT INTO auth.users (us_login, us_password%s) VALUES (%s, %s%s)",
            $additional_key,
            $this->db->quote($username),
            $this->db->quote($password),
            $additional_value
        );

        $this->log('Running SQL against PDO: '.$query, AUTH_LOG_DEBUG);

        $res = $this->query($query);
        
        return true;
    }

    /**
     * Remove user from the storage container
     *
     * @access public
     * @param  string Username
     *
     * @return mixed True on success, otherwise error object
     */
    public function removeUser($username)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);

        $query = sprintf(
            "DELETE FROM auth.users WHERE us_login = %s",
            $this->db->quote($username)
        );

        // check if there is an optional parameter db_where
        if ($this->options['db_where'] != '') {
            // there is one, so add it to the query
            $query .= " AND ".$this->options['db_where'];
        }

        $this->log('Running SQL against PDO: '.$query, AUTH_LOG_DEBUG);

        $res = $this->query($query);
        
        return true;
    }

    /**
     * Change password for user in the storage container
     *
     * @param string Username
     * @param string The new password (plain text)
     */
    public function changePassword($username, $password)
    {
        $this->log(__METHOD__ . ' called.', AUTH_LOG_DEBUG);

        if (isset($this->options['cryptType']) && $this->options['cryptType'] == 'none') {
            $cryptFunction = 'strval';
        } elseif (isset($this->options['cryptType']) && function_exists($this->options['cryptType'])) {
            $cryptFunction = $this->options['cryptType'];
        } else {
            $cryptFunction = 'md5';
        }

        $password = $cryptFunction($password);

        $query = sprintf(
            "UPDATE auth.users SET us_password = %s WHERE us_login = %s",
            $this->db->quote($password),
            $this->db->quote($username)
        );

        // check if there is an optional parameter db_where
        if ($this->options['db_where'] != '') {
            // there is one, so add it to the query
            $query .= " AND ".$this->options['db_where'];
        }

        $this->log('Running SQL against PDO: '.$query, AUTH_LOG_DEBUG);

        $res = $this->query($query);
        
        return true;
    }

    /**
     * Determine if this container supports
     * password authentication with challenge response
     *
     * @return bool
     * @access public
     */
    public function supportsChallengeResponse()
    {
        return in_array($this->options['cryptType'], array('md5', 'none', ''));
    }

    /**
     * Returns the selected crypt type for this container
     *
     * @return string Function used to crypt the password
     */
    public function getCryptType()
    {
        return $this->options['cryptType'];
    }

    public function __sleep()
    {
        return array('options', 'activeUser');
    }
}
