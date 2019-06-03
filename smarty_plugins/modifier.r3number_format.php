<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty r3number_format modifier plugin
 *
 * Type:     modifier<br>
 * Name:     r3number_format<br>
 * Purpose:  format numbers via number_format
 * @link http://smarty.php.net/manual/en/language.modifier.number.format.php
 *          r3number_format (Smarty online manual)
 * @author   Monte Ohrt <monte at ohrt dot com>
 * @param string
 * @param string
 * @return string
 */
function smarty_modifier_r3number_format($number, $decimals=null, $dec_point=null, $thousands_sep=null)
{static $localeInfoTable = array();

    if ((string)$number == '') {
        return '';  // SS: modifica del 21/08/2010
    }
    if ($decimals == '' || $dec_point == null || $thousands_sep == null) {
        //Get locale infos
        $locale = setLocale(LC_ALL, '0');  
        if (!isset($localeInfoTable[$locale])) {
            $localeInfoTable[$locale] = localeconv();
        }
        if ($decimals == '') {
            // Set the decimal points
            $dec = strstr($number, '.');
            $decimals = ($dec === false) ? 0 : strlen($dec) - 1;
        }
        if ($dec_point == '') {
            $dec_point = $localeInfoTable[$locale]['decimal_point'];            
        }
        if ($thousands_sep == '') {
            $thousands_sep = $localeInfoTable[$locale]['thousands_sep'];
            if ($locale <> 'C' && $thousands_sep == '') {
                $thousands_sep = '.';
            }
        }
    }

    return number_format($number, $decimals, $dec_point, $thousands_sep);
}

/* vim: set expandtab: */

?>
