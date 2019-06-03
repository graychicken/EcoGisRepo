<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.r3dialog_iframe.php
 * Type:     function
 * Name:     r3dialog_iframe
 * Purpose:  adds jquery r3dialog_iframe
 * -------------------------------------------------------------
*/

function smarty_function_r3dialog_iframe($params, &$smarty) {

    // - check id parameter
    if (empty($params['id'])) {
        $smarty->trigger_error("r3dialog_iframe: missing 'id' parameter");
        return;
    } else {
        $id = $params['id'];
    }

    // - check url parameter
    if (empty($params['url'])) {
        // $smarty->trigger_error("r3dialog_iframe: missing 'url' parameter");
        // return;
        //$url = 'about:blank';
        $url = "";
    } else {
        $url = $params['url'];
    }

    // - check title parameter
    if (empty($params['class'])) {
        $className = '';
    } else {
        $className = $params['class'];
    }

    // - check title parameter
    if (empty($params['title'])) {
        $title = '';
    } else {
        $title = $params['title'];
    }

    // - dialog options
    $options = array();
    // Reload the startup location
    $options[] = "close: function() {    $('#$id').attr('src', \"$url\");     }";
//	$options[] = "autoResize: false"; // BUG FIX: IE6 + Themeroller
    if (!empty($params['autoOpen'])) {
        $options[] = "autoOpen: {$params['autoOpen']}";
    } else {
        $options[] = "autoOpen: false";
    }
    if (!empty($params['width'])) {
        $options[] = "width: {$params['width']}";
    }
    if (!empty($params['height'])) {
        $options[] = "height: {$params['height']}";
    }
    if (empty($params['modal']) || strtoupper($params['modal'][0]) == 'T') {
        $options[] = "modal: true";
        $overlayOpt = array();
        $overlayOpt[] = 'opacity: ' . (array_key_exists('overlayOpacity', $params) ? $params['overlayOpacity'] : '0.5');
        $overlayOpt[] = 'background: ' . (array_key_exists('overlayBackground', $params) ? '"' . $params['overlayBackground'] . '"' : '"black"');
        $options[] = "overlay: {" . implode(', ', $overlayOpt) . "}";
    }
    if (isset($params['draggable']) && (strtoupper($params['draggable'][0]) == 'F' || $params['draggable'] === false)) {
        $options[] = "draggable: false";
    }
    if (isset($params['resizable']) && (strtoupper($params['resizable'][0]) == 'F' || $params['resizable'] === false)) {
        $options[] = "resizable: false";
    }
    $html = "";
    // TODO: static var for output once (ATTENTION: width & height can be different for every dialog)
    // - javascript
    $html .= "<script type=\"text/javascript\">\n" .
            "  $(document).ready(function() {\n " .
            "                      $('#{$id}')".($className != '' ? ".addClass('$className')" : "").".dialog({ \n" .
            "                        ".implode(", \n", $options). " \n" .
            "                      }); \n" .
            "                      $('#$id').css('display', '');\n" .
            "  }); \n" .
            "</script>\n";
    // - input field
    $html .= "<iframe id=\"{$id}\" name=\"{$id}\" src=\"{$url}\" frameBorder=\"0\" style=\"display: none;width:785px;\" title=\"{$title}\" class=\"r3dialog_iframe\"></iframe>\n";
    return $html;
}
?>