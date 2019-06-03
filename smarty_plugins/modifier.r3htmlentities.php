<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     modifier.r3htmlentities.php
 * Type:     modifier
 * Name:     r3htmlentities
 * Purpose:  htmlentities using charset CONSTAT R3_APP_CHARSET
 * -------------------------------------------------------------
 */
 
  function smarty_modifier_r3htmlentities($string) {

      if (!is_string($string))  //SS: Convert string only
          return $string;
      $charset = defined('R3_APP_CHARSET') ? R3_APP_CHARSET : null;
      return htmlentities($string, ENT_QUOTES, $charset);
  }
?>