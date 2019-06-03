<?php

// Valid charset and its alias
$charsetlist = array(
    'ISO-8859-1', array('latin1', 'ISO_8859-1', 'ISO-8859', '8859-1', '8859'),
    'UTF-8', array('UTF8')
);

function GetCharsetName($charset) {
    global $charsetlist;

    $charset = strtoupper($charset);
    $tot = count($charsetlist);
    for ($cont = 0; $cont < $tot; $cont+=2) {
        if ($charsetlist[$cont] == $charset || in_array($charset, $charsetlist[$cont + 1]))
            return $charsetlist[$cont];
    }
    trigger_error("Invalid charset: $charset", E_USER_WARNING);
    return null;
}

function CharsetHeader($charset = null) {

    if ($charset == '')
        $charset = ini_get('default_charset');
    else
        $charset = GetCharsetName($charset);

    $HasHeader = false;
    foreach (headers_list() as $hdr) {
        if (strtolower(substr($hdr, 0, 13)) == 'content-type:') {
            $HasHeader = true;
            if (($p = strpos($hdr, ';')) !== null)
                $hdr = substr($hdr, 0, $p);
            break;
        }
    }

    if (!$HasHeader)
        $hdr = 'Content-Type: ' . ini_get('default_mimetype');
    if ($charset != '')
        $hdr .= ';charset=' . $charset;
    // echo $hdr;
    header($hdr);
}

function CharsetMeta() {

    $content = '';
    $charset = '';
    foreach (headers_list() as $hdr) {
        if (strtolower(substr($hdr, 0, 13)) == 'content-type:') {
            $hdr = trim(substr($hdr, 13));
            if (($p = strpos($hdr, ';')) === null)
                $content = $hdr;
            else {
                $content = trim(substr($hdr, 0, $p));
                $s = trim(substr($hdr, $p + 1));
                $charset = trim(substr($s, 8));
            }
            break;
        }
    }
    if ($content != '' && $charset != '')
        return "<meta http-equiv=\"Content-Type\" content=\"$content; charset=$charset\">";
    else if ($content != '')
        return "<meta http-equiv=\"Content-Type\" content=\"$content\">";
    else
        return null;
}

/**
 * check php configuration for magic quotes and adjust mixed data by current setting
 *
 * @param mixed $mixed
 * @return mixed with adjusted data
 */
function stripMagicQuotes($mixed) {

    if (is_array($mixed)) {
        foreach ($mixed as $key => $val)
            $mixed[$key] = stripMagicQuotes($val);
    } else {
        if (get_magic_quotes_gpc()) {
            $mixed = stripslashes($mixed);
        }
    }
    return $mixed;
}

/**
 * convert mixed data with special to html entities
 *
 * @param mixed $mixed
 * @param string $charset (default: ISO-8859-1)
 * @return mixed with converted data
 */
function HTMLCharset($mixed, $charset = 'ISO-8859-1') {

    if (is_array($mixed)) {
        foreach ($mixed as $key => $val)
            $mixed[$key] = HTMLCharset($val, $charset);
    } else {
        $mixed = htmlentities($mixed, ENT_QUOTES, $charset);
        $mixed = str_replace('�', '&euro;', $mixed);
    }
    return $mixed;
}

function ConvertCharset($mixed, $sourceCharset = 'ISO-8859-1', $destCharset = 'UTF-8') {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            if (is_array($value)) {
                $mixed[$key] = ConvertCharset($value, $sourceCharset, $destCharset);
            } else if (is_string($value))
                $mixed[$key] = ConvertCharset($value, $sourceCharset, $destCharset);
        }
    } else {
        $mixed = html_entity_decode(htmlentities($mixed, ENT_NOQUOTES, $sourceCharset), ENT_NOQUOTES, $destCharset);
        if ($destCharset == 'ISO-8859-1')
            $mixed = str_replace('&euro;', '�', $mixed);
        if ($destCharset == 'UTF-8')
            $mixed = str_replace('&euro;', chr(226) . chr(130) . chr(172), $mixed);
    }
    return $mixed;
}

function DeHTMLCharset($mixed, $charset = 'ISO-8859-1') {

    if (is_array($mixed)) {
        foreach ($mixed as $key => $val)
            $mixed[$key] = DeHTMLCharset($val, $charset);
    } else {
        $mixed = html_entity_decode($mixed, ENT_QUOTES, $charset);
    }
    return $mixed;
}
