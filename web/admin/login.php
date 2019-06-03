<?php

$scriptStartTime = microtime(true);
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LANG_DIR . 'lang.php';
initLog();  // Initialize ezLog
register_shutdown_function('shutdown');


// 
define('CURRENT_DOMAIN_NAME', R3EcoGisHelper::getSubDomainName($_SERVER['HTTP_HOST'], true));

/* ------------------------------ Settings ------------------------------ */

$act = initVar('act');
$status = initVar('status');
$warning = initVar('warning');

/* ------------------------------ Functions ------------------------------ */

function redirect($url) {
    header("Location: $url");
    die();
}

/* ------------------------------ Authentication ------------------------------ */

if ($auth->isAuth()) {
    if ($auth->getConfigValue('APPLICATION', 'LOGON_AUTH_USER', false)) {
        // Allow to enter user already authenticate
        redirect($auth->getConfigValue('APPLICATION', 'LOGON_ACCESS_POINT', 'main.php'));
    } else {
        // force logoff
        $auth->logout();
    }
}


if (isset($_REQUEST['login']) && $_REQUEST['login'] != '' &&
        isset($_REQUEST['password']) && $_REQUEST['password'] != '') {

    if (($p = strpos($_REQUEST['login'], '@')) === false) {
        $login = trim($_REQUEST['login']);
        $password = trim($_REQUEST['password']);
        $domain = defined('DEFAULT_DOMAIN') ? DEFAULT_DOMAIN : strtoupper(CURRENT_DOMAIN_NAME);
    } else {
        $login = trim(substr($_REQUEST['login'], 0, $p));
        $password = trim($_REQUEST['password']);
        $domain = strtoupper(trim(substr($_REQUEST['login'], $p + 1)));
    }
    // Login required
    ezcLog::getInstance()->log(sprintf("Perform login for user [%s@%s]", $login, $domain, $password), ezcLog::NOTICE);

    if ($auth->performLogin($login, $password, $domain)) {
        ezcLog::getInstance()->log(sprintf("User [%s@%s] logged in", $login, $domain, $password), ezcLog::WARNING);
        if (!$auth->hasPerm('USE', 'APPLICATION')) {
            die("PERMISSION DENIED [USE/APPLICATION]\n");
        }
        cleanTmporaryDirs(R3_TEMPORARY_FILE_TTL, array('ext' => array('.log', '.xml', '.pdf', '.txt')));
        if ($auth->getStatus() == AUTH_PASSWORD_REPLACE || $auth->getStatus() == AUTH_PASSWORD_EXPIRED) {
            redirect('app_manager.php?url=' . urlencode('users/index.php?menu_item=personal_settings&st=0&pg=1&status=' . $auth->getStatus()));
        } else {
            redirect($auth->getConfigValue('APPLICATION', 'LOGON_ACCESS_POINT', $dbini->getValue('APPLICATION', 'LOGON_ACCESS_POINT', 'app_manager.php?on=building&init'))); //'main.php')));
        }
    } else {
        ezcLog::getInstance()->log(sprintf("Login faild login for user [%s@%s]", $login, $domain, $password), ezcLog::WARNING);
        redirect('login.php?status=' . $auth->getStatus()); // Invalid login / password
    }
}


/* ------------------------------ Init Settings ------------------------------ */

require_once R3_WEB_ADMIN_DIR . 'smarty_assign.php';


/* ------------------------------ Smarty assign ----------------------------- */
if ($status <> '') {
    $smarty->assign('errmsg', $auth->getStatusMessage($status));
}
if ($warning <> '') {
    switch ($warning) {
        case 'SCREEN-RESOLUTION':  // Cookie not enabled
            $smarty->assign('warnmsg', _('Your screen resolution is too small. You need al least 1024x768 pixel.'));
            break;
        case 'SCREEN-DEPTH':       // Screen depth
            $smarty->assign('warnmsg', _('Your screen resolution has not enougth colors. You need al least 32K colors.'));
            break;
        case 'BROWSER':            // Unsupported browser
            $smarty->assign('warnmsg', _('Your browser is not compatible with this software. Correct operation is guaranteed with Internet Explorer 6+ and FireFox 3+.'));
            break;
        case 'DATE':               // Invalid client date (possible session problems?)
            $smarty->assign('warnmsg', _('Your system date is invalid. Please check it before continue.'));
            break;
        default:
            $smarty->assign('warnmsg', _('Unknown error #' . $warning));
    }
}


/* ------------------------------ Output ------------------------------------ */

if (file_exists(R3_WEB_DIR . 'version.txt')) {
    $smarty->assign('APPLICATION_VERSION', file_get_contents(R3_WEB_DIR . 'version.txt'));
}
$smarty->display('login.tpl');
