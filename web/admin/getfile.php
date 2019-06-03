<?php

$scriptStartTime = microtime(true);
define('R3_FAST_SESSION', true);
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'r3delivery.php';

function shutdown_getfile() {
    global $scriptStartTime;

    // Write a message to the log.
    $params = '';
    $params .= isset($_REQUEST['type']) ? '?type=' . $_REQUEST['type'] : '';
    $params .= isset($_REQUEST['domain']) ? '&domain=' . $_REQUEST['domain'] : '';
    $params .= isset($_REQUEST['file']) ? '&file=' . $_REQUEST['file'] : '';
    $params .= isset($_REQUEST['disposition']) ? '&disposition=' . $_REQUEST['disposition'] : '';
    if (defined('R3_FAST_SESSION') && R3_FAST_SESSION === true) {
        // Close immediatly the session to allow concurrency session
        session_write_close();
    }
    ezcLog::getInstance()->log(sprintf("Script [%s{$params}] execution time: %.2fsec", $_SERVER["SCRIPT_NAME"], microtime(true) - $scriptStartTime), ezcLog::DEBUG);
}

initLog();  // Initialize ezLog
register_shutdown_function('shutdown_getfile');

$type = isset($_GET['type']) ? $_GET['type'] : null;
$domain = isset($_GET['domain']) ? strtolower(basename($_GET['domain'])) . '/' : null;
$file = isset($_GET['file']) ? basename($_GET['file']) : null;
$name = isset($_GET['name']) ? basename($_GET['name']) : null;
$disposition = isset($_GET['disposition']) ? $_GET['disposition'] : 'inline';
switch ($type) {
    case 'style':
        $path = R3_WEB_CSS_DIR;
        $ttl = 7 * 24 * 60 * 60; 
        break;
    case 'custom-style':
        $ext = strrchr($file, '.');
        $ttl = 7 * 24 * 60 * 60;
        if (in_array($ext, array('.jpg', '.jpeg', '.png', '.gif'))) {
            $path = R3_WEB_DIR . 'images/';
        } else {
            $path = R3_UPLOAD_DATA_DIR . $domain . 'style/';
            if (!file_exists($path . $file)) {
               $ttl = 60;
               $file = 'ecogis2_orange.css';
            }
        }
        break;
    case 'custom-js':
        $path = R3_UPLOAD_DATA_DIR . $domain . 'js/';
        $ttl = 7 * 24 * 60 * 60;
        if (!file_exists($path . $file) && $file == 'jquery.all.i18n..js') {
            $file = 'jquery.all.i18n.it.js';
        }
        break;
    case 'logo':
        $path = R3_UPLOAD_DATA_DIR . $domain . 'logo/';
        $ttl = 7 * 24 * 60 * 60;
        break;
    case 'reference':
        $path = R3_CONFIG_DIR . $domain . 'map/';
        $ttl = 7 * 24 * 60 * 60;
        $file = 'reference.png';
        break;
    case 'download':
        $path = R3_WEB_DIR . 'download/';
        $ttl = 7 * 24 * 60 * 60;
        break;
    case 'tmp':
        $path = R3_TMP_DIR;
        $ttl = 24 * 60 * 60;
        break;
    default:
        throw new Exception("Invalid type \"$type\"");
}

// Search file in the appropriate path
$fileName = $path . $file;
if (!file_exists($fileName)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    header("Status: 404 Not Found");
    echo "<html><head>
          <title>404 Not Found</title>
          </head><body>
          <h1>Not Found</h1>
          <p>The requested URL {$_SERVER['REQUEST_URI']} was not found on this server.</p>
          <hr>
          <address>See http and application configuration</address>
          </body></html>";
    die();
}

$downloadName = $name == '' ? $file : $name;
deliverFile($fileName, array('name' => $downloadName,
    'disposition' => $disposition,
    'purge' => false, //$type == 'tmp',
    'cacheable' => $ttl > 0,
    'cache_ttl' => $ttl,
    'header' => array('etag' => null),
    'die' => true));
