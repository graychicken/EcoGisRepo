<?php

/**
 * R3Error constant
 *
 * R3_ERROR_SCREEN:            if not defined or true echo the error to video
 * R3_ERROR_SCREEN_BACKTRACE:  if true additional backtrace info are provided on video
 * R3_ERROR_ERRLOG:            if not defined or true write the error to PHP's error log (default apache error_log)
 * R3_ERROR_SYSLOG:            if true write the error to the system log error (messages)
 * R3_ERROR_MAIL:              if true send an email with the error. R3_ERROR_MAIL_ADDR must contain a valid email address
 * R3_ERROR_MAIL_ADDR:         the address wich will receive the error mail
 * R3_ERROR_MAX_EMAIL:         The maximum email to send
 */
/*
  Specify the html output type. Possible values are html or text
 */
$R3ErrorFormat = 'html';

/**
 * Return the filename without the DOCUMENT_ROOT part
 *
 * @param string         file name with path
 * @return string        the new file name with path
 */
if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);
}

function __ERR_getRelativeFileName($fileName) {

    $fileName = str_replace('//', '/', $fileName);                              /* Remove double slashes */
    if ($fileName == '' || !isset($_SERVER['DOCUMENT_ROOT'])) {
        return $fileName;  /* Empty name or DOCUMENT_ROOT doesn't exist */
    }

    if (isset($_SERVER['DOCUMENT_ROOT']) && // Needed for command line script
            strlen($_SERVER['DOCUMENT_ROOT']) > 0 &&
            strpos($fileName, $_SERVER['DOCUMENT_ROOT']) === false) {

        $fileNameArray = explode('/', $fileName);
        $docRootArray = explode('/', $_SERVER['DOCUMENT_ROOT']);
        if ($docRootArray[count($docRootArray) - 1] == '') {
            unset($docRootArray[count($docRootArray) - 1]);
        }
        if (count($fileNameArray) < count($docRootArray)) {
            return $fileName;
        }
        $same = 0;
        while ($same < count($docRootArray)) {
            if ($docRootArray[$same] != $fileNameArray[$same]) {
                break;
            }
            $same++;
        }
        if (count($docRootArray) - $same > 2) {
            /* Too mutch ../ */
            return $fileName;
        }
        $res = '';
        for ($i = 0; $i < count($docRootArray) - $same; $i++) {
            $res .= '../';
        }
        $res = substr($res, 0, -1);
        for ($i = $same; $i < count($fileNameArray); $i++) {
            $res .= '/' . $fileNameArray[$i];
        }
        return $res;  /* File is outside the document root: Full path shown */
    }
    $fileName = substr($fileName, strlen($_SERVER['DOCUMENT_ROOT']));
    if ($fileName == '') {
        /* FileName is the document root. Shound be never here! */
        return $fileName;
    }
    if ($fileName[0] == '/') {
        return '.' . $fileName;
    }
    return './' . $fileName;
}

/**
 * Return the formatted string of the error
 *
 * @param  integer       php error number
 * @param  string        php error string
 * @param string         php file
 * @param integer        php error line
 * @param boolean        if true return the text in an html format
 * @return string        The formatted string
 */
function __ERR_mkErrorString($errno, $errstr, $errfile, $errline, $html = true) {

    switch ($errno) {
        case E_ERROR:
        case E_USER_ERROR:
            $kind = 'Error';
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $kind = 'Warning';
            /* Trap the php html for the function name */
            $p1 = strpos($errstr, '<a ');
            $p2 = strpos($errstr, '</a>', $p1);
            if ($p1 > 0 && $p2 > 0) {
                $errstr = substr($errstr, 0, $p1 - 2) . substr($errstr, $p2 + 5);
            }
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $kind = 'Notice';
            break;
        case E_STRICT:
            $kind = 'Deprecated';
            break;
        case E_DEPRECATED:
            $kind = 'Deprecated';
            break;
        default:
            $kind = 'Unhandled error';
            break;
    }
    if ($html) {
        $res = "<b>$kind</b>: $errstr in <b>$errfile</b> on line <b>$errline</b>";
    } else {
        $errstr = str_replace('&quot;', "'", $errstr);
        $res = "$kind: $errstr in $errfile on line $errline";
    }
    return $res;
}

/**
 * Return the kind of the error (Error, Warning, Notice)
 *
 * @param  integer       php error number
 * @return string        The text
 */
function __ERR_mkErrorStringKind($errno) {

    switch ($errno) {
        case E_ERROR:
        case E_USER_ERROR:
            return 'Error';
        case E_WARNING:
        case E_USER_WARNING:
            return 'Warning';
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_STRICT:
            return 'Notice';
    }
    return '';
}

/**
 * Return the formatted string of the error
 *
 * @param  class         php exception class
 * @param boolean        if true return the text in an html format
 * @return string        The formatted string
 */
function mkExceptionString($exception, $html = true) {

    $class = get_class($exception);
    $message = $exception->getMessage();
    $code = $exception->getCode();
    $file = $exception->getFile();
    $line = $exception->getLine();

    $file = __ERR_getRelativeFileName($file);
    $text = 'Unhandled exception' .
            ($class == 'Exception' ? '' : ' "' . $class . '"') .
            ($code == '0' ? '' : ' #' . $code) .
            ($message == '' ? '' : ': ' . $message);

    return __ERR_mkErrorString(E_ERROR, $text, $file, $line, $html);
}

/**
 * Return the formatted string of the error
 *
 * @param   array   the arguments 
 * @return  string  the list of arguments
 */
function mkErrorFuncParam($array) {

    $res = '';
    foreach ($array as $key => $val) {
        if (is_object($val)) {
            $res .= 'CLASS::' . get_class($val) . ', ';
        } else if (is_array($val)) {
            $res .= 'ARRAY::[' . print_r($val, true) . '], ';
        } else if (is_string($val)) {
            $res .= '"' . $val . '", ';
        } else {
            $res .= $val . ', ';
        }
    }
    return substr($res, 0, -2);
}

/**
 * Sending the log message to screen , video, logs
 *
 * @param   array   the arguments 
 */
function errorOutput($errno, $text, $htmlText, $lines = null, $htmlLines = null) {
    static $emailSent = 0;
    global $R3ErrorFormat;

    /** Screen message */
    if (!defined('R3_ERROR_SCREEN') || R3_ERROR_SCREEN) {
        echo "\n" . ($R3ErrorFormat == 'html' ? "<br />$htmlText<br />" : "$htmlText") . "\n";
        if (defined('R3_ERROR_SCREEN_BACKTRACE') && R3_ERROR_SCREEN_BACKTRACE) {
            if (is_array($htmlLines)) {
                for ($i = 0; $i < count($htmlLines) - 0; $i++) {
                    echo $htmlLines[$i] . ($R3ErrorFormat == 'html' ? '<br />' : '') . "\n";
                }
            }
        }
        echo ($R3ErrorFormat == 'html' ? '<br />' : '') . "\n";
    }

    /** PHP error log message */
    if (!defined('R3_ERROR_ERRLOG') || R3_ERROR_ERRLOG) {
        error_log($text);
    }

    /** Syslog message */
    if (defined('R3_ERROR_SYSLOG') && R3_ERROR_SYSLOG) {
        if (defined('APPLICATION_CODE') && defined('DOMAIN_NAME')) {
            $ident = APPLICATION_CODE . '_' . DOMAIN_NAME;
        } else {
            $ident = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $ident = __FILE__;
        }
        openlog($ident, LOG_CONS | LOG_PERROR, LOG_LOCAL0);
        syslog(LOG_WARNING, $text);
        closelog();
    }

    /** Mail message */
    if (defined('R3_ERROR_MAIL') && defined('R3_ERROR_MAIL_ADDR') && R3_ERROR_MAIL) {
        if (!defined('R3_ERROR_MAX_EMAIL') || $emailSent < R3_ERROR_MAX_EMAIL) {
            if (defined('APPLICATION_CODE') && defined('DOMAIN_NAME')) {
                $subject = APPLICATION_CODE . ' ' . DOMAIN_NAME;
            } else {
                $subject = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $ident = __FILE__;
            }
            $subject .= ' ' . strtoupper(__ERR_mkErrorStringKind($errno));
            if (isset($_SERVER['HTTP_HOST']))
                $subject .= ' [' . $_SERVER['HTTP_HOST'] . ']';
            $body = array();
            $body[] = $text;
            if (is_array($lines)) {
                for ($i = 0; $i < count($lines) - 0; $i++) {
                    $body[] = $lines[$i];
                }
            }

            // Add Server
            if (count($_SERVER) > 0) {
                $body[] = "SERVER:";
                foreach ($_SERVER as $key => $val) {
                    $body[] = "{$key}=" . var_export($val, true);
                }
            }

            // Add Get
            if (count($_GET) > 0) {
                $body[] = "GET:";
                foreach ($_GET as $key => $val) {
                    $body[] = "{$key}=" . var_export($val, true);
                }
            }

            // Add Post
            if (count($_POST) > 0) {
                $body[] = "POST:";
                foreach ($_POST as $key => $val) {
                    if (in_array($key, array('password')))
                        continue;
                    $body[] = "{$key}=" . var_export($val, true);
                }
            }

            mail(R3_ERROR_MAIL_ADDR, $subject, implode(PHP_EOL, $body));
            $emailSent++;
        }
    }
}

function exceptionHandler($exception) {
    global $R3ErrorFormat;

    $stack = array_reverse($exception->getTrace());
    $toCrt = mkExceptionString($exception, $R3ErrorFormat == 'html');
    $toLog = mkExceptionString($exception, false);

    $tabCrt = '';
    $tabLog = '';
    $c = '&lfloor;';
    $resCrt = array();
    $resLog = array();
    foreach ($stack as $value) {
        $line = isset($value['line']) ? $value['line'] : '';
        $file = isset($value['file']) ? $value['file'] : '';
        $func = isset($value['function']) ? $value['function'] : '';
        $args = isset($value['args']) ? mkErrorFuncParam($value['args']) : '';

        $file = __ERR_getRelativeFileName($file);
        if ($R3ErrorFormat == 'html') {
            $resCrt[] = $tabCrt . $c . " file <b>$file</b> at line: <b>$line</b> function: <b>$func($args)</b>";
            $tabCrt .= "&nbsp;&nbsp;";
        } else {
            $resCrt[] = $tabCrt . "\_" . " file $file at line: $line function: $func($args)";
            $tabCrt .= "  ";
        }
        $resLog[] = $tabLog . "\_" . " file $file at line: $line function: $func($args)";
        $tabLog .= "  ";
    }
    errorOutput(E_ERROR, $toLog, $toCrt, $resLog, $resCrt);

    /* Don't execute PHP internal error handler */
    return true;
}

/**
 * Return the formatted string of the error
 *
 * @param  integer       php error number
 * @param  string        php error string
 * @param string         php file
 * @param integer        php error line
 * @return string        The formatted string
 */
function errorHandler($errno, $errstr, $errfile, $errline) {
    global $R3ErrorFormat;

    /** The @ is prepend to the function */
    if (ini_get('error_reporting') == 0) {
        return false;
    }

    /** Trap only the error trapper in the php.ini */
    if (!(ini_get('error_reporting') & $errno)) {
        return false;
    }

    $errfile = __ERR_getRelativeFileName($errfile);
    $stack = array_reverse(debug_backtrace());

    $toCrt = __ERR_mkErrorString($errno, $errstr, $errfile, $errline, $R3ErrorFormat == 'html');
    $toLog = __ERR_mkErrorString($errno, $errstr, $errfile, $errline, false);

    $tabCrt = '';
    $tabLog = '';
    $c = '&lfloor;';
    $resCrt = array();
    $resLog = array();
    foreach ($stack as $value) {
        $line = isset($value['line']) ? $value['line'] : '';
        $file = isset($value['file']) ? $value['file'] : '';
        $func = isset($value['function']) ? $value['function'] : '';
        $args = isset($value['args']) ? mkErrorFuncParam($value['args']) : '';

        if (strpos($func, 'errorHandler') !== 0) {
            $file = __ERR_getRelativeFileName($file);
            if ($R3ErrorFormat == 'html') {
                $resCrt[] = $tabCrt . $c . " file <b>$file</b> at line: <b>$line</b> function: <b>$func($args)</b>";
                $tabCrt .= "&nbsp;&nbsp;";
            } else {
                $resCrt[] = $tabCrt . "\_" . " file $file at line: $line function: $func($args)";
                $tabCrt .= "  ";
            }
            $resLog[] = $tabLog . "\_" . " file $file at line: $line function: $func($args)";
            $tabLog .= "  ";
        }
    }
    errorOutput($errno, $toLog, $toCrt, $resLog, $resCrt);

    /* Don't execute PHP internal error handler */
    return true;
}

set_exception_handler('exceptionHandler');
set_error_handler("errorHandler");

