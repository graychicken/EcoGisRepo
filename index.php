<?php
/* * *************************************************************************** */
/*                                                                            */
/* Entry point for the application                                            */
/*  On success the file main.php is called                                    */
/*  On error the file error.php is called                                     */
/*                                                                            */
/* * *************************************************************************** */

// Done, warning and error URL
$doneURL = 'admin/login.php';
$warningURL = 'admin/login.php?warning=%s';
$errorURL = 'error.php?kind=%s';

header("Cache-Control: no-cache, no-store, max-age=0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 24 * 60 * 60) . ' GMT');
header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
header('etag: "' . md5(time()) . '"');

/* Ajax check */
if (isset($_REQUEST['check']) && $_REQUEST['check'] == 'ajax') {
    echo "OK " . date('H:i:s');
    die();
}
?>
<html>
    <head>
        <title>R3 GIS</title>
        <meta http-equiv="refresh" content="9;url=<?php printf($errorURL, 'JS'); ?>">
        <style type="text/css">
            body { cursor: wait }
            .text { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 14px; line-height: 16px; }
        </style>
    </head>
    <body onLoad="checkAll()">
        <table width="100%" height="100%" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" class="text">Loading. Please wait...</td>
            </tr>
        </table>
    </body>
    <script language="JavaScript" type="text/javascript">
<?php
echo "var serverTime = '" . mktime() . "';  // Remote server time\n";
?>
        var maxDiffTime = 30 * 60;      // Maximum time difference detween cliend and server
        var xmlhttp = null;             // ajax check

        /*
         * Set a cookie value
         *  name string    the cookie name
         *  value string   the cookie value
         *  return the cookie value
         */
        function setCookieValue(name, value) {

            document.cookie = name + "=" + value;
            return value;
        }

        /*
         * Get a cookie value
         *  name string        the cookie name
         *  reutn string|null  return the cookie value or null if the cookie is not set
         */
        function getCookieValue(name) {

            var allCookies = document.cookie || false;
            name = name + '=';
            if (allCookies) {
                var start = document.cookie.indexOf(name);
                if (start >= 0) {
                    start += name.length;
                    var end = allCookies.indexOf(';', start);
                    if (end == -1) {
                        end = allCookies.length;
                    }
                    return allCookies.substring(start, end);
                }
            }
            return null;
        }

        /*
         * Return if cookies are enabled
         * Return true if cookies are enabled
         */
        function getCookieEnabled() {

            var cookieName = 'cookieStatus';
            setCookieValue(cookieName, 'ON');
            return getCookieValue(cookieName) == 'ON';
        }

        /*
         * Return if ajax is enabled/supported
         * Return true if ajax is enabled/supported
         */
        function getAjaxEnabled() {

            try {
                xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
                xmlhttp.onreadystatechange = ajaxEnabledCallback;
                xmlhttp.open("GET", "<?php basename($_SERVER['PHP_SELF']); ?>?check=ajax");
                xmlhttp.send(null);
                return true;
            } catch (e) {
            }
            return false;
        }

        /*
         * Ajax callback function
         */
        function ajaxEnabledCallback() {

            if ((xmlhttp.readyState == 4) && (xmlhttp.status == 200)) {
                location.href = "<?php echo $doneURL; ?>";
            }
        }

        /*
         * Check for:
         *  - JavaScript enabled (implicit if the function was called)
         *  - cookies enabled 
         *  - screen resolution (min 1024x768)
         *  - screen colors (min 32K)
         *  - browser (IE >6, firefox)
         *  - valid local date
         *  - ajax enabled 
         * Return true if all test are ok, else false and redirect to the error page
         */
        function checkAll() {

            // Check cookies
            if (!getCookieEnabled()) {
                location.href = "<?php printf($errorURL, 'COOKIE'); ?>";  // cookies NOT enabled
                return false;
            }

            // Check screen resolution
            if ((screen.width > 0 && screen.width < 1024) || (screen.height > 0 && screen.height < 768)) {
                location.href = "<?php printf($warningURL, 'SCREEN-RESOLUTION'); ?>";  // small screen resolution
                return false;
            }

            // Check screen colors
            if (screen.pixelDepth != null)
                var c = screen.pixelDepth;
            else if (screen.colorDepth != null)
                var c = screen.colorDepth
            else
                var c = 0;
            if (c > 0 && c < 15) {
                location.href = "<?php printf($warningURL, 'SCREEN-DEPTH'); ?>";  // not enougth colors
                return false;
            }

            // Browser check
            var userAgent = navigator.userAgent.toLowerCase();
            var userAgentVersion = (userAgent.match(/.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/) || [])[1];
            var isIE = /msie/.test(userAgent) && !/opera/.test(userAgent);
            var isSafari = /webkit/.test(userAgent);
            var isMozzilla = /mozilla/.test(userAgent) && !/(compatible|webkit)/.test(userAgent);
            if (!(isIE && userAgentVersion >= 6) && !isSafari && !isMozzilla) {
                location.href = "<?php printf($warningURL, 'BROWSER'); ?>";  // not enougth colors
                return false;
            }

            // Date check
            var curDate = new Date()
            var curTime = Math.round(curDate.getTime() / 1000);
            var diffTime = Math.abs(curTime - serverTime);
            if (diffTime > maxDiffTime) {
                location.href = "<?php printf($warningURL, 'DATE'); ?>";  // date error
                return false;
            }

            // Ajax check
            if (!getAjaxEnabled()) {
                location.href = "<?php printf($errorURL, 'AJAX'); ?>";  // ajax error
                return false;
            }
        }
    </script>
</html>