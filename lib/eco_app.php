<?php

// ezComponents autoload
//set_include_path(get_include_path() . PATH_SEPARATOR . R3_EZC_DIR . PATH_SEPARATOR . R3_PHPEXCEL_DIR);

//require_once "Base/base.php";

require_once R3_LIB_DIR . '/../vendor/autoload.php';

require_once R3_LIB_DIR . 'obj.base_locale.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_LIB_DIR . 'r3auth.php';
require_once R3_LIB_DIR . 'r3auth_manager.php';
require_once R3_LIB_DIR . 'r3auth_impexp.php';
require_once R3_LIB_DIR . 'r3auth_text.php';
require_once R3_LIB_DIR . 'r3dbini.php';


function __autoload($className) {
    // EZ Component
    ezcBase::autoload($className);
}

function R3AppInitDB() {
    //global $mdb2;
    global $dsn;

    //require_once 'MDB2.php';
    if (!isset($dsn) || $dsn == '') {
        throw new Exception('Missing $dsn');
    }

    $txtDsn = $dsn['dbtype'] . '://' . $dsn['dbuser'] . ':' . $dsn['dbpass'] . '@' . $dsn['dbhost'] . '/' . $dsn['dbname'];
    try {
        $db = ezcDbFactory::create($txtDsn);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION);
        //$mdb2 = MDB2::singleton($txtDsn);
        ezcDbInstance::set($db);
    } catch (\PDOException $e) {
        $msg = "Error connecting to database {$dsn['dbname']} on  {$dsn['dbhost']} as  {$dsn['dbuser']}: {$e->getMessage()}";
        echo $msg;
        error_log( $msg );
        die();
    }

    if (isset($dsn['charset'])) {
        $db->exec("SET client_encoding TO '{$dsn['charset']}'");
        //$mdb2->exec("SET client_encoding TO '{$dsn['charset']}'");
    }
    if (isset($dsn['search_path'])) {
        $db->exec("SET search_path TO {$dsn['search_path']}, public");
        //$mdb2->exec("SET search_path TO {$dsn['search_path']}, public");
    }
    $db->exec("SET datestyle TO ISO");
    //$mdb2->exec("SET datestyle TO ISO");
}

/**
 * Handle all the active stuff that usually happens in config.php.
 * This are things as opening db connections, start sessions etc.
 * 
 * Session is started
 * The folloy object are created:
 *  - $smarty
 *  - $auth
 */
function R3AppInit($type = null, array $opt = array()) {
    global $smarty, $auth, $pdo, /*$mdb2, */$dbini;            // output object
    global /*$mdb2_dsn, */$lang;                               // output var
    global $dsn, $sessionOpt/*, $mdb2_options*/;               // input vars
    global $auth_options;                                  // input vars
    global $scriptStartTime;

    /* Default application type */
    if ($type === null) {
        $type = 'admin';
    }
    $opt = array_merge(array(
        'session_start' => true, // If false don't start the session
        'auth' => true, // If true create an R3Auth (or R3AuthManager) object
        'auth_manager' => false, // if true create an R3AuthManager object instead of R3Auth
        'dbini' => false), // If true create an R3DBINI object
            $opt);
    /* Session */
    foreach ($sessionOpt as $key => $val) {
        switch ($key) {
            case 'name':
                session_name($val);
                break;
            case 'cache_limiter':
                session_cache_limiter($val);
                break;
            case 'save_path':
                session_save_path($val);
                break;
            case 'timeout':
                session_cache_expire(ceil($val / 60));
                break;
            case 'warning_timeout':
                // do nothing;
                break;

            default:
                ini_set("session." . $key, $val);
        }
    }
    if ($opt['session_start'] === true && session_id() == '') {
        session_start();
    }

    /* Smarty */
    //require_once R3_SMARTY_ROOT_DIR . 'Smarty.class.php';
    $smarty = new Smarty();
    $smarty->config_dir = R3_SMARTY_ROOT_DIR . 'configs/';
    $smarty->cache_dir = R3_SMARTY_ROOT_DIR . 'cache/';
    if (defined('R3_SMARTY_PLUGIN_DIR')) {
        $smarty->plugins_dir[] = R3_SMARTY_PLUGIN_DIR;
    }
    if ($type == 'admin') {
        $smarty->template_dir = R3_SMARTY_TEMPLATE_DIR_ADMIN;
        $smarty->compile_dir = R3_SMARTY_TEMPLATE_C_DIR_ADMIN;
    } else if ($type == 'public') {  // for public sites
        $smarty->template_dir = R3_SMARTY_TEMPLATE_DIR_PUBLIC;
        $smarty->compile_dir = R3_SMARTY_TEMPLATE_C_DIR_PUBLIC;
    } else if ($type == 'map') {
        $smarty->template_dir = R3_SMARTY_TEMPLATE_DIR_MAP;
        $smarty->compile_dir = R3_SMARTY_TEMPLATE_C_DIR_MAP;
    } else {
        throw new Exception("Unknown smarty specialization");
    }
    $smarty->load_filter('pre', 'r3quotevalue');

    R3AppInitDB();
    $db = ezcDbInstance::get();

    /* Authentication */
    if ($opt['auth'] === true && !isset($auth_options)) {
        throw new Exception('Missing $auth_options');
    }

    if ($opt['auth'] === true) {
        if ($opt['auth_manager'] === true) {
            require_once R3_LIB_DIR . 'r3auth_manager.php';
            $auth = new R3AuthManager($db, $auth_options, APPLICATION_CODE);
        } else {
            require_once R3_LIB_DIR . 'r3auth.php';
            $auth = new R3Auth($db, $auth_options, APPLICATION_CODE);
        }
        R3AuthInstance::set($auth);
    }
    /* DBIni */
    if ($opt['dbini'] === true) {
        require_once R3_LIB_DIR . 'r3dbini.php';
        $domainName = R3_IS_MULTIDOMAIN ? 'SYSTEM' : DOMAIN_NAME;
        $dbini = new R3DBIni($db, $auth_options, $domainName, APPLICATION_CODE);
    }
}

function shutdown() {
    global $scriptStartTime;

    // Write a message to the log.
    $params = '';
    $params .= isset($_REQUEST['on']) ? '?on=' . $_REQUEST['on'] : '';
    $params .= isset($_REQUEST['act']) ? '&act=' . $_REQUEST['act'] : '';
    ezcLog::getInstance()->log(sprintf("Script [%s{$params}] execution time: %.2fsec", $_SERVER["SCRIPT_NAME"], microtime(true) - $scriptStartTime), ezcLog::DEBUG);
}

function initLog() {
    // Get the one and only instance of the ezcLog.
    $log = ezcLog::getInstance();
    // Get an instance to the default log mapper.
    $mapper = $log->getMapper();
    // Create a new Unix file writer, that writes to the file: "default.log".
    $writer = new ezcLogUnixFileWriter(R3_LOG_DIR, strtolower(APPLICATION_CODE) . ".log");
    // Create a filter that accepts every message (default behavior).
    $filter = new ezcLogFilter;
    // Combine the filter with the writer in a filter rule.
    $rule = new ezcLogFilterRule($filter, $writer, true);
    // And finally assign the rule to the mapper.
    $mapper->appendRule($rule);
}

/**
 * Handle all the active stuff that usually happens in config.php.
 * This are things as opening db connections, start sessions etc.
 * 
 * Session is started
 * The folloy object are created:
 *  - $smarty
 *  - $auth
 */
function R3AppStart($type = null, array $opt = array()) {
    global $smarty, $auth, $languages, $jQueryDateFormat, $phpDateFormat, $phpDateTimeFormat;
    global $lang;  // output var

    global $scriptStartTime;

    initLog();  // Initialize ezLog
    $text = "{$_SERVER['REMOTE_ADDR']}: {$_SERVER['SCRIPT_FILENAME']}?{$_SERVER['QUERY_STRING']} started ({$_SERVER['REQUEST_METHOD']})";
    ezcLog::getInstance()->log($text, ezcLog::DEBUG);

    require_once R3_LIB_DIR . 'eco_utils.php';

    $isAuth = $auth->isAuth();
    if (!$isAuth && isset($opt['allow_change_password']) && $opt['allow_change_password'] === true &&
            ($auth->getStatus() == AUTH_PASSWORD_REPLACE || $auth->getStatus() == AUTH_PASSWORD_EXPIRED)) {
        $isAuth = true;
        $auth->getAllPermsAsString();
    }

    if (!$isAuth) {
        ezcLog::getInstance()->log(sprintf("Non authenticated request: Logged out [%s]", $auth->getStatusText()), ezcLog::NOTICE);
        Header("location: logout.php?status=" . $auth->getStatusText());
        die();
    }

    if (1 == 1) {
        $sql = "SELECT set_session_var('R3UID', '{$auth->getUID()}')";
        $db = ezcDbInstance::get();
        $db->exec($sql);
        //$mdb2->exec($sql);
    }

    $_SESSION['lang'] = $auth->getParam('us_lang', 1);
    $lang = $_SESSION['lang'];
    $smarty->assign('lang', $lang);
    \R3Locale::setLanguageID($lang);
    \R3Locale::setLanguages($languages);
    \R3Locale::setJqueryDateFormat($jQueryDateFormat);
    \R3Locale::setPhpDateFormat($phpDateFormat);
    \R3Locale::setPhpDateTimeFormat($phpDateTimeFormat);

    /** Apply locale */
    setLang($languages[$_SESSION['lang']], LC_MESSAGES);
    bindtextdomain('messages', R3_LANG_DIR);
    textdomain('messages');
    bind_textdomain_codeset('messages', R3_APP_CHARSET);

    // Force domain
    if ($auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
        $domainList = R3EcoGisHelper::getDomainList();
        $smarty->assign('domains', $domainList);
        if (!isset($_SESSION['do_id'])) {
            if (R3_IS_MULTIDOMAIN) {
                $_SESSION['do_id'] = key($domainList);  // 1st domain
            } else {
                $_SESSION['do_id'] = $auth->getDomainID();  // Default domain
            }
        }
    } else {
        $_SESSION['do_id'] = $auth->getDomainID();      // Default domain
    }
    $smarty->assign('do_id', $_SESSION['do_id']);

    if (!isset($_REQUEST['method'])) {  // Don't rebuild on ajax request
        // Rebuild css
        R3BuildCSS();
        // Rebuild js
        R3BuildJS();
    }

    if (defined('GZIP_PHP_PAGE') && GZIP_PHP_PAGE == true) {
        // Compress php page
        ob_start("ob_gzhandler");
    }
}

function checkPath($path, $writeable, $dieOnError) {
    if (!file_exists($path)) {
        die("Path \"{$path}\" not found.\n");
    }
    if ($writeable) {
        if (!is_writable($path)) {
            die("Path \"{$path}\" is not writable.\n");
        }
    }
    return true;
}

/**
 * Build the css if necessary
 *
 */
function R3BuildCSS($force = false) {
    global $auth, $xcssConfig;

    $thema = 'orange';

    $appCode = strtolower(APPLICATION_CODE);
    $path = R3_UPLOAD_DATA_DIR . strtolower(R3EcoGisHelper::getCurrentDomainName()) . "/style/";
    $xcssConfig['master_filename'] = "{$path}{$appCode}_{$thema}.css";
    $xcssConfig['xCSS_files']["{$thema}.xcss"] = "{$thema}.xcss";
    if (file_exists("{$path}custom.css")) {
        $xcssConfig['xCSS_files']["{$path}custom.css"] = "custom.css";
    }
    if (!$force) {
        $rebuild = !file_exists($xcssConfig['master_filename']);
        if (!$rebuild) {
            $compiledAge = filemtime($xcssConfig['master_filename']);
            $files = $xcssConfig['xCSS_files'];
            if (isset($xcssConfig['prepend'])) {
                foreach ($xcssConfig['prepend'] as $f) {
                    $files[$f] = basename($f);
                }
            }
            foreach ($files as $srcFile => $dummy) {
                if (filemtime(($srcFile[0] == '/' ? $srcFile : $xcssConfig['path_to_css_dir'] . $srcFile)) > $compiledAge) {
                    // Complie needed
                    $rebuild = true;
                    break;
                }
            }
        }
    }
    if ($force || $rebuild) {
        ezcLog::getInstance()->log("CSS rebuild for \"{$xcssConfig['master_filename']}\"", ezcLog::DEBUG);
        define('XCSSCLASS', 'xcss-class.php');
        require_once R3_LIB_DIR . 'xcss-class.php';
        checkPath(dirname($xcssConfig['master_filename']), true, true);
        $xCSS = new xCSS($xcssConfig);
        $xCSS->compile();
    }
}

/**
 * Build the js if necessary
 *
 */
function R3BuildJS($force = false) {
    global $auth, $jsPacker;

    $appCode = strtolower(APPLICATION_CODE);
    $jsDestPath = R3_UPLOAD_DATA_DIR . strtolower(R3EcoGisHelper::getCurrentDomainName()) . "/js/";
    $masterFileName = "{$jsDestPath}{$appCode}_all.js";
    if (!$force) {
        $rebuild = !file_exists($masterFileName);
        if (!$rebuild) {
            $compiledAge = filemtime($masterFileName);
            $files = $jsPacker['JS_files'];
            if (isset($jsPacker['files'])) {
                foreach ($jsPacker['files'] as $name => $fileGroup) {
                    $name = str_replace('<LANG>', R3Locale::getLanguageCode(), $name);
                    if (!file_exists("{$jsDestPath}{$name}")) {
                        $rebuild = true;
                        ezcLog::getInstance()->log("JavaScript file \"{$jsDestPath}{$name}\" not found. Rebuild necessary", ezcLog::DEBUG);
                        break;
                    }
                    foreach ($fileGroup as $file) {
                        $files[] = $file;
                    }
                }
            }
            foreach ($files as $file) {
                $file = str_replace('<LANG>', R3Locale::getLanguageCode(), $file);
                if (filemtime(R3_WEB_JS_DIR . $file) > $compiledAge) {
                    // Complie needed
                    $rebuild = true;
                    break;
                }
            }
        }
    }
    if ($force || $rebuild) {
        checkPath(dirname($masterFileName), true, true);
        // Non packed files
        if (isset($jsPacker['files'])) {
            foreach ($jsPacker['files'] as $name => $fileGroup) {
                $script = '';
                foreach ($fileGroup as $file) {
                    $file = str_replace('<LANG>', R3Locale::getLanguageCode(), $file);
                    $script .= "/*** " . basename($file) . " ***/\n\n\n" . file_get_contents(R3_WEB_JS_DIR . $file) . "\n\n\n";
                }
                $name = str_replace('<LANG>', R3Locale::getLanguageCode(), dirname($masterFileName) . '/' . $name);
                ezcLog::getInstance()->log("JavaScript rebuild for \"{$name}\"", ezcLog::DEBUG);
                file_put_contents($name, $script);
            }
        }

        require_once R3_LIB_DIR . 'class.JavaScriptPacker.php';
        $script = '';
        $funcList = array();
        foreach ($jsPacker['JS_files'] as $file) {
            $data = file_get_contents(R3_WEB_JS_DIR . $file);
            $script .= "{$data}\n\n\n";
            // Check function averride!
            foreach (explode("\n", $data) as $lineNo => $line) {
                if (substr($line, 0, 9) == 'function ' && ($p = strpos($line, '(')) !== false) {
                    $name = trim(substr($line, 9, $p - 9));
                    if (isset($funcList[$name])) {
                        echo "<b>Warning</b>: JavaScript function \"{$name}\" in file {$file}:{$lineNo} already declared in file {$funcList[$name]['file']}:{$funcList[$name]['line']}.<br />\n";
                    }
                    $funcList[$name] = array('file' => $file, 'line' => $lineNo);
                }
            }
        }
        if (!isset($jsPacker['minify_output'])) {
            ezcLog::getInstance()->log("JavaScript rebuild for \"{$masterFileName}\"", ezcLog::DEBUG);
            file_put_contents($masterFileName, $script);
        } else {
            $packer = new JavaScriptPacker($script, $jsPacker['minify_output'] ? 'Normal' : 'None', true, false);
            $packed = $packer->pack();
            ezcLog::getInstance()->log("JavaScript rebuild for \"{$masterFileName}\"", ezcLog::DEBUG);
            file_put_contents($masterFileName, $packed);
        }
    }
}
