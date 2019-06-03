<?php

function NullIfEmpty($s) {
    if (trim($s) == '')
        return null;
    else
        return $s;
}

function addSchemaName($schema, $dbObj, $alias = null) {
    $ret = $dbObj;
    if (strlen($schema) > 0)
        $ret = $schema . "." . $dbObj;
    if (strlen($alias) > 0)
        $ret = $ret . " " . $alias;
    return $ret;
}

function addObjAlias($alias, $mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $val)
            $mixed[$key] = implode('.', array($alias, $val));
    } else {
        $mixed = implode('.', array($alias, $mixed));
    }
    return $mixed;
}

// Multibyte fix: If the constant R3_APP_CHARSET_DB exists the the function will use the iconv function
function adjDBValue($value, $len = -1) {
    if ($value === null) {
        return null;
    }
    if (is_array($value)) {
        foreach ($value as $key => $val)
            $value[$key] = adjDBValue($val, $len);
    } else {
        $value = trim($value);      // NON-Multibyte here!
        if (strlen($value) == 0) {  // NON-Multibyte here!
            return null;
        }
        if ($len >= 0) {
            // Multibyte here!
            if (defined('R3_APP_CHARSET_DB')) {
                // PHP-BUG (iconv_substr($value, 0, 1, "UFT-8"); faild!)
                if (iconv_strlen($value, R3_APP_CHARSET_DB) > 1) {
                    $value = iconv_substr($value, 0, $len, R3_APP_CHARSET_DB);
                }
            } else {
                $value = substr($value, 0, $len);
            }
        }
    }
    return $value;
}

function dateToDB($date) {
    $res = null;

    $res = adjDBValue($date);
    if ($res !== null) {
        $res = substr($res, 0, 10);
        $res = str_replace('.', '-', $res);
        $res = str_replace('/', '-', $res);
        if (preg_match("/\d{2}-\d{2}-\d{4}/", $res)) {
            $resArr = explode('-', $res);
            if (count($resArr) > 0)
                $res = $resArr[2] . '-' . $resArr[1] . '-' . $resArr[0];
        } else {
            $res = null;
        }
    }
    return $res;
}

/**
 * Force the given value to Integer. If is not an integer value, it will be return default. Float values will be rounded if round is set to true.
 *
 * @param string $s
 * @param string $default
 * @param boolean $round
 * @return integer with corrected value or default
 */
function forceInteger($s, $default = null, $round = true, $thousandSeparator = null) {
    if (is_int($s)) {
        return $s;
    }
    $s = trim($s);
    if ($thousandSeparator !== null) {
        $s = str_replace($thousandSeparator, '', $s);
    }
    if ($round !== false) {
        $s = str_replace(',', '.', $s);
        if (is_numeric($s))
            $s = round($s);
    }
    if (preg_match('/^[+-]?[0-9]+$/', $s))
        return (int) $s;
    else
        return $default;
}

/**
 * Si assicura che il parametro passato sia un float. In caso negativo restituisce il valore di default.
 * Se $thousandSeparator � impostato, rimuove questo carattere
 *
 * @param type $s
 * @param type $default
 * @param type $thousandSeparator
 * @return type 
 */
function forceFloat($s, $default = null, $thousandSeparator = null) {
    if (is_float($s) || is_int($s)) {
        return $s;
    }
    $s = trim($s);
    if ($thousandSeparator !== null) {
        $s = str_replace($thousandSeparator, '', $s);
    }
    $s = str_replace(',', '.', $s);
    if (is_numeric($s))
        return (float) $s;
    else
        return $default;
}

// Si assicura che il parametro passato sia booleano. In caso negativo restituisce il valore di default.
//  Valori considerati come true sono: true, 1, iniziali per 'T' e 'Y'
function forceBool($val, $default = null) {

    $val = trim($val);
    if ($val === null)
        return $default;
    $val = strtoupper($val);
    if ($val === true || $val[0] == 'T' || $val[0] == 'Y' || $val == '1')
        return 'T';
    else
        return 'F';
}

// Si assicura che il parametro passato sia una data in formato ISO (YYYY-MM-DD), EUrope (DD/MM/YYYY), USa  (MM/DD/YYYY) in  valida. 
// In caso negativo restituisce il valore di default.
function forceISODate($date, $default = null, $inputFormat = 'EU') {
    $format = array('ISO' => array(0, 1, 2), //Posizione di anno, mese giorno nei vari formati
        'EU' => array(2, 1, 0),
        'USA' => array(1, 0, 2));

    $res = adjDBValue($date);
    if ($res == '') {
        return $default;
    }
    $res = trim(substr($res, 0, 10));
    $res = str_replace('.', '-', $res);
    $res = str_replace('/', '-', $res);
    $resArr = explode('-', $res);

    if (count($resArr) != 3) {
        return $default;
    }


    if ($inputFormat[0] == 'I') {
        $resArr = array($resArr[$format['ISO'][0]], $resArr[$format['ISO'][1]], $resArr[$format['ISO'][2]]);
    } else if ($inputFormat[0] == 'E') {
        $resArr = array($resArr[$format['EU'][0]], $resArr[$format['EU'][1]], $resArr[$format['EU'][2]]);
    } else if ($inputFormat[0] == 'U') {
        $resArr = array($resArr[$format['USA'][0]], $resArr[$format['USA'][1]], $resArr[$format['USA'][2]]);
    } else {
        return $default;
    }
    if (!@checkdate($resArr[1], $resArr[2], $resArr[0])) {
        return $default;
    }
    if ($resArr[0] < 100) {
        $resArr[0] = $resArr[0] < 50 ? $resArr[0] + 2000 : $resArr[0] + 1900;
    }
    return sprintf('%02d-%02d-%02d', $resArr[0], $resArr[1], $resArr[2]);
}
