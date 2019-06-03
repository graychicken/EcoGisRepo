<?php

/** Include if exists the xajax extension for file upload */
if (defined("__XAJAX_PHP__")) return;
define("__XAJAX_PHP__", 1);

require_once(dirname(__FILE__) . '/xajax/xajax.inc.php');
if (file_exists(dirname(__FILE__) . '/xajax/xajaxExtend.php')) {
    require_once(dirname(__FILE__) . '/xajax/xajaxExtend.php');
}

/* Extend the xajaxResponse to add the removeAllOptions and addCreateOptions feature */

class R3XajaxResponse extends xajaxResponse {  
    
    /** Remove all the option for a select */
    /* sSelectId the string with the select id */
    function RemoveAllOptions($sSelectId)  {
        $this->addScript("var xajax_select = document.getElementById('$sSelectId');");
        $this->addScript("while (xajax_select.length > 0) { xajax_select.options[0] = null }");
    }
    
    /* Add options from an associative array */
    /* sSelectId the string with the select id */
    /* aValues the associative array */
    /* selectedIndex the selected value */
    function addCreateOptions($sSelectId, $aValues, $selectedIndex=null)  {
        $attribs = array();
        $selectedIndexVal = -1;
        
        $i = 0;
        $this->addScript("var xajax_select = document.getElementById('$sSelectId');");
        foreach($aValues as $key => $val) {
            if (is_array($val)) {
                foreach($val as $k => $v) {
                    // echo "$k";
                    if ($k == 'label') {
                        $this->addScript("xajax_select.options.add(new Option('$v', '$key'));");
                        if ($selectedIndex==$key) {
                            $selectedIndexVal = $i;
                        }
                        //echo "xajax_select.options.add(new Option('$v', '$key'));";
                    } else {
                        $attribs[$k] = $i;
                    }
                }
                foreach($attribs as $k => $v) {
                    $this->addScript("xajax_select.options[xajax_select.options.length - 1].setAttribute('$k', '$v')");
                    if ($selectedIndex==$v) {
                        $selectedIndexVal = $i;
                    }
                    //echo "xajax_select.options[xajax_select.options.length - 1].setAttribute('$k', '$v');";
                }
            } else {    
                $val = str_replace("'", "\\'", $val);
                $this->addScript("xajax_select.options.add(new Option('$val', '$key'));");
                if ($selectedIndex==$key) {
                    $selectedIndexVal = $i;
                }
            }
            $i++;
        }
        if ($selectedIndexVal >= 0) {
            $this->addScript("xajax_select.selectedIndex=" . $selectedIndexVal);  
        }
    }
}

/**
 * Return the URI for the XAJAX request
 *
 * string $kind    URI kind: 
 *                 - When null or "" return ""
 *                 - When RELATIVE return the relative path and file
 *                 - When SELF return only the file
*/
function getXajaxRequestURI($kind=null) {

    $uri = '';
    switch (strToUpper($kind)) {
        case 'RELATIVE':
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = str_replace(array('"',"'",'<','>'), array('%22','%27','%3C','%3E'), $_SERVER['REQUEST_URI']);
            }
        break;
        case 'SELF':
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = basename(str_replace(array('"',"'",'<','>'), array('%22','%27','%3C','%3E'), $_SERVER['REQUEST_URI']));
            }
        break;
    } 
    return $uri;
}

?>