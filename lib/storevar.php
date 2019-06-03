<?php

/**
 * Get and set variable for a specified page
 *
 * @access public
 * @param string    variable name
 * @param mixed     default value if the variable doesn't exist. Default null
 * @param boolean   if true the default value is returned even if the variable exists. Default false
 * @param boolean   if true the session variable are ignored. 
 *                    If the POST/GET variable doesn't exist the function return the default value instead of the session var.
 * @param string    the session variable prefix. If null the prefix is the page name (without path and .php)
 * @return mixed    the variable value or the the default value
 *
 * @see             see the "variables_order" parameter in the php.ini to get/set the variables catch order
 */
if (defined('R3_LIB_DIR'))
    require_once(R3_LIB_DIR . 'charset.php');
else
    require_once(dirname(__FILE__) . '/charset.php');

/**
 * check php configuration for magic quotes and adjust mixed data by current setting
 *
 * @param mixed $mixed
 * @return mixed with adjusted data
 */
function doStripMagicQuotes($mixed) {

    if (!get_magic_quotes_gpc())
        return $mixed;

    if (is_array($mixed)) {
        foreach ($mixed as $key => $val)
            $mixed[$key] = doStripMagicQuotes($val);
    } else {
        $mixed = stripslashes($mixed);
    }
    return $mixed;
}

/**
 * @param $name
 * @param $default
 * @param $force_default
 * @param $ignore_session_var
 * @param $session_prefix
 * @param $saveValue
 */
function pageVar($name, $default = null, $force_default = false, $ignore_session_var = false, $session_prefix = null, $saveValue = true) {

    if ($session_prefix === null) {
        $session_prefix = basename($_SERVER['PHP_SELF']);
        $session_prefix = substr($session_prefix, 0, -strlen(strstr($session_prefix, '.')));
    }

    $variables_order = ini_get('variables_order');
    $len = strlen($variables_order);
    if ($force_default) {
        $mixed = $default;
    } else {
        for ($cont = 0; $cont < $len; $cont++) {
            switch ($variables_order[$cont]) {
                case 'E':
                    $e = getenv($name);
                    if ($e !== false) {
                        $mixed = $e;
                    }
                    break;
                case 'G':
                    if (isset($_GET[$name]))
                        $mixed = @doStripMagicQuotes($_GET[$name]);
                    break;
                case 'P':
                    if (isset($_POST[$name]))
                        $mixed = @doStripMagicQuotes($_POST[$name]);
                    break;
                case 'C':
                    if (isset($_COOKIE[$name]))
                        $mixed = @doStripMagicQuotes($_COOKIE[$name]);
                    break;
                case 'S':
                    if (!$ignore_session_var) {
                        if (isset($_SESSION[$session_prefix . '_' . $name]))
                            $mixed = @$_SESSION[$session_prefix . '_' . $name];
                    }
                    break;
            }
            if (isset($mixed)) {
                if ($saveValue) {
                    $_SESSION[$session_prefix . '_' . $name] = $mixed;
                }
                return $mixed;
            }
        }
        $mixed = $default;
    }
    if ($saveValue) {
        $_SESSION[$session_prefix . '_' . $name] = $mixed;
    }
    return $mixed;
}

/**
 * Set a page variable
 * @param $name
 * @param $value
 * @param $session_prefix
 */
function setPageVar($name, $value = null, $session_prefix = null) {

    if ($session_prefix === null) {
        $session_prefix = basename($_SERVER['PHP_SELF']);
        $session_prefix = substr($session_prefix, 0, -strlen(strstr($session_prefix, '.')));
    }
    $_SESSION[$session_prefix . '_' . $name] = $value;
    return $value;
}

/**
 * Get the value of a variable if exists, otherwise a default value
 *
 * @access public
 * @param string    variable name
 * @param mixed     default value if the variable doesn't exist. Default null
 * @param array     nullValue list of values where default is forced
 * @return array    if the variable name equal a variable of this array return the default
 *
 * @see             see the "variables_order" parameter in the php.ini to get/set the variables catch order
 */
function initVar($name, $default = null, array $nullValue = array()) {

    if (isset($_REQUEST[$name]) && !in_array($_REQUEST[$name], $nullValue)) {
        return doStripMagicQuotes($_REQUEST[$name]);
    }
    return $default;
}

/**
 * Get the value of a variable if exists, otherwise a default value
 *
 * @access public
 * @param array     search_array
 * @param string    index name
 * @param mixed     default value if the variable doesn't exist. Default null
 * @return mixed    the variable value or the the default value
 *
 * @see             see the "variables_order" parameter in the php.ini to get/set the variables catch order
 */
function getArrayValue($array, $name, $default = null) {

    if (isset($array[$name])) {
        return $array[$name];
    }
    return $default;
}

