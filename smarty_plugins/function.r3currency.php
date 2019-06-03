<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.r3currency.php
 * Type:     function
 * Name:     r3currency
 * Purpose:  adds current currency
 * -------------------------------------------------------------
 */

use R3Gis\ApplicationBundle\Utils\LanguageHelper;
 
function smarty_function_r3currency($params, &$smarty) {
    return LanguageHelper::getCurrencySymbol();
}
