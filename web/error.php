<?php
$errMessage = _('Unknown error');

if (isset($_REQUEST['kind'])) {
    switch ($_REQUEST['kind']) {
        case 'JS':                 // Javascript not enabled
            $errMessage = _('Javascript is not enabled on jour system. Please enable it befone continue.');
            break;
        case 'COOKIE':             // Cookie not enabled
            $errMessage = _('Cookies are not enabled on jour system. Please enable it befone continue.');
            break;
        case 'SCREEN-RESOLUTION':  // Screen resolution
            $errMessage = _('Your screen resolution is too small. You need al least 1024x768 ');
            break;
        case 'SCREEN-DEPTH':       // Screen depth
            $errMessage = _('Your screen resolution has not enougth colors. You need al least 32K colors');
            break;
        case 'BROWSER':            // Unsupported browser
            $errMessage = _('Your browser is not compatible with this software. Supported browser are Internet Explorer 6+, Safari, FireFox 2+');
            break;
        case 'DATE':               // invalid date
            $errMessage = _('Your system date is invalid. Please check it before confinue');
            break;
        case 'AJAX':               // Ajax not enabled
            $errMessage = _('Ajax is not enabled. Please enable it befone continue.');
            break;
        default:
            $errMessage = _('Unknown error #' . $_REQUEST['kind']);
    }
}
?>
<html>
    <head>
        <title>R3 GIS</title>
        <style type="text/css">
            <!--
            .error { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 14px; font-weight: bold; color:#990000; }
            .text  { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 14px; color:#000000;}
            .text2  { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; color:#000000;}
            .link  { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; color:#000000;}
            -->
        </style>
    </head>
    <body>
        <table width="100%" height="100%" border="0" cellpadding="0" cellspacing="0">
            <tr><td class="text" align="center">
                    <span class=error>WARNING!</span> <?php echo $errMessage; ?><br /><br />
                    <span class="text2">(<a href="main.php" class="link">PRESS HERE</a> to continue anyway, or <a href="." class="link">GO BACK</a> to check again)</span>
                </td></tr>
        </table>
    </body>
</html>