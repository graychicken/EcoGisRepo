<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     modifier.r3htmlentities.php
 * Type:     modifier
 * Name:     r3htmlentities
 * Purpose:  htmlentities using charset $smarty_modifier_r3htmlentities_sbr_charset
 * -------------------------------------------------------------
 */
 
  function smarty_modifier_r3htmlentities_sbr($string) {
      global $smarty_modifier_r3htmlentities_sbr_charset;
      
      $charset = $smarty_modifier_r3htmlentities_sbr_charset;
      return htmlentities($string, ENT_QUOTES, $charset);
  }
?>