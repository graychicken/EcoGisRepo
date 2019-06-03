<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     prefilter.r3quotevalue.php
 * Type:     prefilter
 * Name:     r3quotevalue
 * Purpose:  Add custom function htmlentities_smarty to smarty-variables used in input-tags (value)
 * Requires: modifier.r3htmlentities.php
 * -------------------------------------------------------------
 */
 
  function smarty_prefilter_r3quotevalue($source, &$smarty) {
 // print_r($source);
 // die();
// <input name="pippo" value="{$vlu.localita_imposto}" >
//<input value='aaa {$vlu.localita_imposto}' >
//<INPUT value='{$vlu.localita_imposto}' >
//      echo $source;
      return preg_replace('/value=\"\{(.*)\}\"/', 'value="{$1|r3htmlentities}"', $source);
//      die();
//      return $source;
 }
?>