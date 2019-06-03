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
 
function smarty_function_r3tab_iframe($params, &$smarty) {
    
    // - check id parameter
    if (empty($params['id'])) {
        $smarty->trigger_error("r3tab_iframe: missing 'id' parameter");
        return;
    } else {
        $id = $params['id'];
    }
    
    // - check items parameter
    if (empty($params['items'])) {
        $smarty->trigger_error("r3tab_iframe: missing 'items' parameter");
        return;
    } else if (!is_array($params['items'])) {
        $smarty->trigger_error("r3tab_iframe: invalid 'items' parameter type. Must ba an array");
        return;
    } else {
        $items = $params['items'];
    }
    $styleArray = array();
    if (!empty($params['width'])) {
        $styleArray[] = 'width: ' . $params['width'];
    }
    if (!empty($params['height'])) {
        $styleArray[] = 'height: ' . $params['height'];
    }
    if (count($styleArray) == 0) {
        $style = '';
    } else {
        $style = "style=\"" . implode('; ', $styleArray) . "\"";
    }
    $attributeArray = array();
    if (!empty($params['scroll'])) {
        $attributeArray[] = "scrollbar=\"{$params['scroll']}\"";
    }
    if (count($attributeArray) == 0) {
        $attributes = '';
    } else {
        $attributes = implode(' ', $attributeArray);
    }
    
    $htmlJS = "";
    $htmlBody = "";
    $htmlHeader = "";
    
    
    
    
    $htmlHeader .= "<div id=\"$id\">\n";
    $htmlHeader .= "<ul>\n";
    foreach($items as $key=>$item) {
        if (!isset($item['id'])) {
            $smarty->trigger_error("r3tab_iframe: missing 'id' parameter for item #$key");
            continue;
        }
        $itemId = $item['id'];
        $itemLabel = isset($item['label']) ? $item['label'] : '';
        $itemUrl = isset($item['url']) ? $item['url'] : 'about:blank';
        $htmlHeader .= "  <li><a href=\"#$itemId\"><span id=\"" . $itemId . "_label\">$itemLabel</span></a></li>\n";
        $htmlBody .= "  <div id=\"$itemId\"><iframe id=\"" . $itemId . "_src\" src=\"about:blank\" frameborder=\"0\" $attributes $style></iframe></div>\n";
        $htmlJS .= "    $(\"#" . $itemId . "_src\").attr('src', '$itemUrl');\n";
    }
    
    $htmlHeader .= "</ul>\n";
    $htmlHeader .= $htmlBody;
    $htmlHeader .= "</div>\n";
    
    
    
    
    $htmlHeader = "<script type=\"text/javascript\">\n" .
                   "  $(document).ready(function() {\n " .
                   "                      $(\"#tabs > ul\").tabs();\n" .
                   "                      $htmlJS ".
                   "  }); \n" .
                   "</script>\n" .
                   $htmlHeader;
    
    // - input field
    // $html .= "<iframe id=\"{$id}\" name=\"{$id}\" src=\"{$url}\" frameBorder=\"0\" style=\"display: none\" title=\"{$title}\"></iframe>\n";
    
    return $htmlHeader;
    
    
    
    
  
    // <li><a href="#fragment-1"><span>{t}Installazioni{/t}</span></a></li>
    // <li><a href="#fragment-2"><span>{t}Avvisi/Accertamenti{/t}</span></a></li>
    // <li><a href="#fragment-3"><span>{t}Pagamenti{/t}</span></a></li>
    // <li><a href="#fragment-4"><span>{t}Documenti{/t}</span></a></li>
  // </ul>
  
  // <div id="fragment-2"><iframe id="xfragment-2" src="about:blank" style="height: 500px; width: 100%"></iframe></div>
  // <div id="fragment-3"><iframe id="xfragment-3" src="about:blank" style="height: 500px; width: 100%"></iframe></div>
  // <div id="fragment-4"><iframe id="xfragment-4" src="about:blank" style="height: 500px; width: 100%"></iframe></div>
// </div>



}
?>