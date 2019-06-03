<?php

if (defined("__DATEUTILS_PHP__"))
    return;
define("__DATEUTILS_PHP__", 1);

/**
 * Convert an SQL-ISO date and/or time into a timestamp
 *
 * string value           SQL-ISO date and or time (YY[YY]-M[M]-D[D], H[H]:M[M][:S[S]] or YY[YY]-M[M]-D[D] H[H]:M[M][:S[S]])
 * return integer|null    Return the timestamp or null on error
 */
function SQLDateToTimeStamp($value) {
    $a = explode(' ', $value);
    $dst = 0;

    if (count($a) == 1) {
        // Date OR time
        $d = explode('-', $value);
        $t = explode(':', $value);
        if (count($d) == 3) {
            // Date
            $time = mktime(12, 0, 0, $d[1], $d[2], $d[0]);
        } else if (count($t) == 2) {
            // Time without seconds
            $time = mktime($t[0], $t[1], 0, 1, 1, 1970);
        } else if (count($t) == 3) {
            // Time with seconds
            $time = mktime($t[0], $t[1], $t[2], 1, 1, 1970);
        } else {
            // Invalid date/time
            return null;
        }
    } else if (count($a) == 2) {
        // Date AND time
        $d = explode('-', $a[0]);
        $t = explode(':', $a[1]);
        if (count($d) == 3 && count($t) == 2) {
            // Date and time without seconds
            $time = mktime($t[0], $t[1], 0, $d[1], $d[2], $d[0]);
        } else if (count($d) == 3 && count($t) == 3) {
            // Date and time with seconds
            $time = mktime($t[0], $t[1], $t[2], $d[1], $d[2], $d[0]);
        } else {
            // Invalid date/time
            return null;
        }
    } else {
        // Invalid date and/or time
        return null;
    }

    if ($time == -1 || $time === null) {
        // Invalid date and/or time
        return null;
    }

    return $time;
}

/**
 * Convert a Timestamp into a SQL-ISO datetime, date or time
 * string value           Timestamp
 * string kind            output format. Valid values are: 'datetime', 'date', 'time'
 * return string|null     The SQL-ISO date or null on error
 */
function TimeStampToSQLDate($value, $kind = 'datetime') {

    if ($value == '') {
        return null;
    }
    switch ($kind) {
        case 'date': return date('Y-m-d', $value);
        case 'time': return date('H:i:s', $value);
        case 'datetime': return date('Y-m-d H:i:s', $value);
    }
    return null;
}

// Converte una data dal formato italiano (DD/MM/YY hh:mm:ss o DD-MM-YY hh:mm:ss) a timestamp
function StrToTimeStamp($value) {

    $dst = 0;
    $a = explode(' ', $value);
    if (count($a) == 1) {
        $value = str_replace('.', '-', $value);
        $value = str_replace('/', '-', $value);
        $d = explode('-', $value);
        $t = explode(':', $value);
        if (count($d) == 3) {
            // Date
            $time = mktime(0, 0, 0, $d[1], $d[0], $d[2]);
        } else if (count($t) == 2) {
            // Time without seconds
            $time = mktime($t[0], $t[1], 0, 1, 1, 1970);
        } else if (count($t) == 3) {
            // Time with seconds
            $time = mktime($t[0], $t[1], $t[2], 1, 1, 1970);
        } else {
            // Invalid date or time
            return null;
        }
    } else if (count($a) == 2) {
        $value = str_replace('.', ':', $value);
        $d = explode('-', $a[0]);
        $t = explode(':', $a[1]);
        if (count($d) == 3 && count($t) == 2) {
            // Date and time without seconds
            $time = mktime($t[0], $t[1], 0, $d[1], $d[0], $d[2]);
        } else if (count($d) == 3 && count($t) == 3) {
            // Date and time with seconds
            $time = mktime($t[0], $t[1], $t[2], $d[1], $d[0], $d[2]);
        } else {
            // Invalid datetime
            return null;
        }
    } else {
        // Invalid date and/or time
        return null;
    }

    if ($time == -1 || $time === null) {
        // Invalid date and/or time
        return null;
    }

    // Daylight check
    $isDst = date('I', $time);
    $time -= $isDst * 3600;
    return $time;
}

function TimeStampToStr($value, $format = 'd/m/Y H:i:s') {

    return date($format, $value);
}

function SQLDateToStr($value, $format = 'd/m/Y H:i:s') {
    if ($value == '' || $value == '0000-00-00')
        return '';
    if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
        $date = new DateTime($value);
        return $date->format($format);
    } else {
        return date($format, SQLDateToTimeStamp($value));
    }
}

function StrToSQLDate($value) {
    return TimeStampTOSQLDate(StrToTimeStamp($value));
}

/**
 * get single day name (eventually with short name)
 *
 * @param integer $d index of month [0 = actual day; 1 = Monday; ... ] (first day of 1990 is a Monday)
 * @param boolean $shortName define output format of day
 *
 * @return string with day name
 */
function getday($d = 0, $shortName = false) {
    if ($shortName !== false)
        return (($d == 0 ) ? strftime("%a") : strftime("%a", mktime(0, 0, 0, 1, $d, 1990, 0)));
    else
        return (($d == 0 ) ? strftime("%A") : strftime("%A", mktime(0, 0, 0, 1, $d, 1990, 0)));
}

/**
 * get all day names (eventually with short name)
 *
 * @param boolean $shortName define output format of day
 *
 * @return array with all day names
 */
function getalldays($shortName = false) {
    $ret = array();
    for ($i = 1; $i < 8; $i++)
        $ret[] = getday($i, $shortName);
    return $ret;
}

/**
 * get single month name (eventually with short name)
 *
 * @param integer $m index of month [0 = actual month; 1 = January; ... ]
 * @param boolean $shortName define output format of month
 *
 * @return string with month name
 */
function getmonth($m = 0, $shortName = false) {
    if ($shortName !== false)
        return (($m == 0 ) ? strftime("%b") : strftime("%b", mktime(0, 0, 0, $m)));
    else
        return (($m == 0 ) ? strftime("%B") : strftime("%B", mktime(0, 0, 0, $m)));
}

/**
 * get all month names (eventually with short name)
 *
 * @param boolean $shortName define output format of month
 *
 * @return array with all month names
 */
function getallmonths($shortName = false) {
    $ret = array();
    for ($i = 1; $i < 13; $i++)
        $ret[$i] = getmonth($i, $shortName);
    return $ret;
}
