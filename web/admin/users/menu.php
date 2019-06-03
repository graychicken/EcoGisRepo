<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")) {
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_APP_ROOT . 'lang/lang.php';

/** Authentication and permission check */
$db = ezcDbInstance::get();
$auth = new R3Auth($db, $auth_options, APPLICATION_CODE);
$auth->ignoreExpiredPassword = true;  /** ignore expired password to allow user to change it */
if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}
if (is_null(R3AuthInstance::get())) {
    R3AuthInstance::set($auth);
}

?>
<html>
    <head>
        <title></title>
        <?php
        if (file_exists(R3_APP_ROOT . 'lib/custom.um.php')) {
            require_once(R3_APP_ROOT . 'lib/custom.um.php');
            $umDependenciesObj = getUmDependenciesObject();
            $um = $umDependenciesObj->get();
            foreach($um['css'] as $src) {
                echo "<link href=\"{$src}\"  rel=\"stylesheet\" type=\"text/css\" />\n";
            }
        } else {
            if (defined('R3_CSS_URL')) {
                echo "<link href=\"".R3_CSS_URL."default.css\"  rel=\"stylesheet\" type=\"text/css\" />\n";
                echo "<link href=\"".R3_CSS_URL."menu.css\"     rel=\"stylesheet\" type=\"text/css\" />\n";
            } else {
                echo "<link href=\"../../style/default.css\"  rel=\"stylesheet\" type=\"text/css\" />\n";
                echo "<link href=\"../../style/menu.css\"     rel=\"stylesheet\" type=\"text/css\" />\n";
            }
        }    
        ?>
        <script type="text/javascript" language="JavaScript">
            function changePage(url) {
                parent.document.getElementById('framework').src = 'users/' + url;
            }
        </script>
    </head>
    <body class="body_border" bgcolor="#e5e5e5">
        <table class="menu_table" style="position:absolute; top: 2px; left:10px">
            <tr>
                <?php
                if (($auth->hasPerm('SHOW', 'DOMAIN') || $auth->hasPerm('SHOW', 'ALL_DOMAINS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Domains list']) ? _('Domains list') : $txt['Domains list']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('domains_list.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'APPLICATION') || $auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_APPLICATION_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Applications list']) ? _('Applications list') : $txt['Applications list']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('applications_list.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'ACNAME') || $auth->hasPerm('SHOW', 'ALL_ACNAMES')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_ACNAME_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Access control list']) ? _('Access control list') : $txt['Access control list']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('acnames_list.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'GROUP') || $auth->hasPerm('SHOW', 'ALL_GROUPS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_GROUP_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Groups list']) ? _('Groups list') : $txt['Groups list']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('groups_list.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'USER') || $auth->hasPerm('SHOW', 'ALL_USERS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_USER_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Users list']) ? _('Users list') : $txt['Users list']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('users_list.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'USER') || $auth->hasPerm('SHOW', 'ALL_USERS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_USER_SETTINGS_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Personal settings']) ? _('Personal settings') : $txt['Personal settings']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('personal_settings.php')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if (($auth->hasPerm('SHOW', 'CONFIG')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_CONFIG_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Configuration']) ? _('Configuration') : $txt['Configuration']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('config_list.php')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if ($auth->hasPerm('IMPORT', 'CONFIG') ||
                        $auth->hasPerm('IMPORT', 'ACNAME') &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_IMPORT_MENU') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Import']) ? _('Import') : $txt['Import']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('import.php')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }

                if ($auth->hasPerm('SHOW', 'CONNECTED_USER') &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_CONNECTED_USER') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Connected users']) ? _('Connected users') : $txt['Connected users']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('connected_users.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";
                }
                if ($auth->hasPerm('SHOW', 'LOG') &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_LOG') != 'F') {
                    echo "<td class=\"menu_td_off\"><label>|</label></td>\n";

                    $label = str_replace(' ', '&nbsp;', (!isset($txt['Logs']) ? _('Logs') : $txt['Logs']));
                    echo "<td class=\"menu_td_off\" onclick=\"javascript:changePage('logs.php?pg=1')\" onmouseover=\"this.className='menu_td_hover'\" onmouseout=\"this.className='menu_td_off'\">$label</td>\n";

                }
                echo "<td class=\"menu_td_off\"><label>|</label></td>\n";
                ?>
            </tr>
        </table>
    </body>
</html>