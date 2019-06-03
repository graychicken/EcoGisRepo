<?php

if (defined("__R3_LOCALE__"))
    return;
define("__R3_LOCALE__", 1);

/**
 * The conversion table from lang to locale
 *
 * @var  array
 * @access private
 */
static $langLocaleConversionTable = array('' => 'C',
    'it' => 'it_IT.utf8',
    'de' => 'de_DE.UTF8',
    'fr' => 'fr_FR.utf8',
    'es' => 'es_ES.utf8',
    'en' => 'en_US.utf8',
    'cs' => 'cs_CZ.utf8',
    'pl' => 'pl_PL.utf8',
);


/**
 * Locale information table
 *
 * @var  array
 * @access private
 */
static $localeInfoTable = array();

/**
 * return the locale/language conversion table
 *
 * @return array   The current table
 */
function getLangLocaleTable() {
    global $langLocaleConversionTable;

    return $langLocaleConversionTable;
}

/**
 * return the locale/language conversion table
 *
 * @return array   The current table
 */
function getLangLocaleByCode($locale) {
    global $langLocaleConversionTable;

    if (isset($langLocaleConversionTable[$locale])) {
        return$langLocaleConversionTable[$locale];
    }
    return null;
}

/**
 * set the locale/language conversion table
 *
 * @param array    The new table
 */
function setLangLocaleTable($value) {
    global $langLocaleConversionTable;

    $langLocaleConversionTable = $value;
}

/**
 * return the current locale
 *
 * @param mixed     database dsn
 * @return string   The locale string (eg: en_EN.utf8)
 */
function getLocale($kind = LC_MESSAGES) {
    return setLocale($kind, '0');
}

/**
 * return the lang code by locale code
 *
 * @param mixed     locale code (eg: en_EN.utf8) or null. If null the current locale is used
 * @return string   The locale string (eg: en) or null on error
 */
function getLangByLocale($locale = null) {
    global $langLocaleConversionTable;

    if ($locale === null) {
        $locale = getLocale();
    }

    $key = array_search($locale, $langLocaleConversionTable);
    if ($key === false) {
        return null;
    }
    return $key;
}

/**
 * return the locale code by lang code
 *
 * @param mixed     lang code (eg: en) or null. If null the current locale is used
 * @return string   The locale string (en_EN.utf8) or null on error
 */
function getLocaleByLang($lang = null) {
    global $langLocaleConversionTable;

    if ($lang === null) {
        return getLocale();
    }
    if (isset($langLocaleConversionTable[$lang])) {
        return $langLocaleConversionTable[$lang];
    }
    return null;
}

/**
 * return the locale information array by locale code
 *
 * @param mixed     locale code (eg: en_EN.utf8) or null. If null the current locale is used
 * @return mixed    an array with all the locale settings is returned (see localeconv)
 * @see localeconv
 */
function getLocaleInfo($locale = null) {
    global $localeInfoTable;

    if ($locale === null) {
        $locale = getLocale();
    }
    if (!isset($localeInfoTable[$locale])) {
        $oldlocale = getLocale();
        if (!setLocale(LC_ALL, $locale)) {
            return null;
        }
        $localeInfoTable[$locale] = localeconv();
        setLocale(LC_ALL, $oldlocale);
    }
    return $localeInfoTable[$locale];
}

/**
 * set the locale informations. The changed information are affected only by the library functions
 *
 * @param array     the value to change
 * @param mixed     locale code (eg: en_EN.utf8) or null. If null the current locale is used
 * @return boolean  return true on success
 * @see localeconv
 */
function setLocaleInfo($data, $locale = null) {
    global $localeInfoTable;

    if ($locale === null) {
        $locale = getLocale();
    }
    if (!isset($localeInfoTable[$locale])) {
        /* Load the locale data */
        if (!getLocaleInfo($locale)) {
            return false;  /* function faild: Locale not found */
        }
    }
    foreach ($data as $key => $val) {
        $localeInfoTable[$locale][$key] = $val;
    }
    return true;
}

/**
 * return the locale information array by lang code
 *
 * @param mixed     language code (eg: en) or null. If null the current locale is used
 * @return mixed    an array with all the locale settings is returned (see localeconv)
 * @see localeconv
 */
function getLangInfo($lang = null) {

    if ($lang === null) {
        return getLocaleInfo();
    }
    if (($locale = getLocaleByLang($lang)) === null) {
        return null;
    }
    return getLocaleInfo($locale);
}

/**
 * set the locale informations by lang code. The changed information are affected only by the library functions
 *
 * @param array     the value to change
 * @param mixed     locale code (eg: en) or null. If null the current locale is used
 * @return boolean  return true on success
 * @see localeconv
 */
function setLangInfo($data, $lang = null) {

    if ($lang === null) {
        return setLocaleInfo($data);
    }
    return setLocaleInfo($data, getLocaleByLang($lang));
}

/**
 * set the locale from the lang code
 *
 * @param string    the lang code
 * @param           category (@see setlocale). Default LC_ALL 
 * @return boolean  return true on success
 * @see localeconv
 */
function setLang($lang, $category = LC_ALL) {
    if (($locale = getLocaleByLang($lang)) === null) {
        return false;
    }
    return setLocale($category, $locale);
}

/**
 * Format a number with grouped thousands
 *
 * @param float     the number
 * @param mixed     the number of decimal points or null 
 * @param mixed     The locale code or null. If null the current locale is used
 * @return boolean  return true on success
 * @see localeconv
 */
function locale_number_format($number, $decimals = null, $locale = null) {

    $info = getLocaleInfo($locale);
    if ($info === null) {
        return number_format($number, $decimals);
    }
    return number_format($number, $decimals, $info['decimal_point'], $info['thousands_sep']);
}

/**
 * Format a number with grouped thousands
 *
 * @param float     the number
 * @param mixed     the number of decimal points or null 
 * @param mixed     The lang code or null. If null the current loclae is used
 * @return boolean  return true on success
 * @see localeconv
 */
function lang_number_format($number, $decimals = null, $lang = null) {

    if (($locale = getLocaleByLang($lang)) === null) {
        return null;
    }
    return locale_number_format($number, $decimals, $locale);
}
