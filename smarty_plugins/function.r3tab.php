<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.r3dialog_iframe3.php
 * Type:     function
 * Name:     r3dialog_iframe2
 * Purpose:  adds jquery r3dialog_iframe2 (for jQuery UI >= 1.7.0
 * -------------------------------------------------------------
 */
 
/**
 * Extract the keys values of the given array
 * param array $data     source array
 * parma array $keys     the keys to extract
 * return string         the extracted data
 */
function smarty_r3tab_explode($data, $keys) {
    $a = array();
    $charset = defined('R3_APP_CHARSET') ? R3_APP_CHARSET : null;
    foreach($keys as $key) {
        if (isset($data[$key]))
            $a[] = $key . '="' . htmlentities($data[$key], ENT_QUOTES, $charset) . '"';  // SS: check for special chars
    }
    return implode(' ', $a);
}

function smarty_function_r3tab($params, &$smarty) {
    
    $defaultOpt = array('id'=>null,          // id of the container
                        'class'=>'ui-tabs',  // default calss
                        'mode'=>'normal',    // tab mode: normal, iframe, ajax
                        'autoInit'=>true,    // if true call the $("#id").tabs();\n" .
                        'style'=>'',         // additional style of the container
                        'istyle'=>'',        // iframe style
                        // 'attrib'=>'',
                        'items'=>null);      // array item (valid parameters are: id:     tab id, 
                                             //                                   mode:   normal, iframe, ajax (Default container-mode)
                                             //                                   label:  tab label
                                             //                                   url:    iframe&ajax the url to call, normal mode: add an url tag to the div
                                             //                                   innerHTML: normal mode: the div html
    $params = array_merge($defaultOpt, $params);

    
    // - check id/class parameter
    if (empty($params['id'])) {
        $smarty->trigger_error("r3tab: missing 'id' parameter");
        return;
    }
    // - check items parameter
    if (!is_array($params['items'])) {
        $smarty->trigger_error("r3tab: missing or invalid 'items' parameter");
        return;
    }
    if (!empty($params['width'])) {
        $params['style'] .= ' width: ' . $params['width'];
    }
    if (!empty($params['height'])) {
        $params['style'] .= ' height: ' . $params['height'];
    }

    $html .= "<div " . smarty_r3tab_explode($params, array('id', 'class', 'style')) . " >\n";
    $htmlHeader = '';
    $htmlBody = '';
    $htmlJSLoad = array();
    foreach($params['items'] as $key=>$item) {
        if (!isset($item['id'])) {
            $smarty->trigger_error("r3tab: missing 'id' parameter for item #$key");
            continue;
        }
        $id = $item['id'];
        $mode = isset($item['mode']) ? $item['mode'] : $params['mode'];
        $label = isset($item['label']) ? $item['label'] : '';
        $url = isset($item['url']) ? $item['url'] : 'about:blank';
        
        $innerHTML = isset($item['innerHTML']) ? $item['innerHTML'] : '';
        switch($mode) {
            case 'iframe':
                $htmlHeader .= "<li><a href=\"#$id\"><span id=\"{$id}_label\">$label</span></a></li>\n";
                $htmlBody .= "  <div id=\"$id\" class=\"ui-tabs-hide\"><iframe id=\"{$id}_src\" src=\"about:blank\" frameborder=\"0\" style=\"{$params['istyle']}\"></iframe></div>\n";
                $htmlJSLoad[$id] = $url;
            break;
            case 'ajax':
                $htmlHeader .= "<li><a href=\"$url\" " . ($id <> '' ? "title=\"$id\"" : "") . ">$label</a></li>\n";
            break;
            default:
                $htmlHeader .= "<li><a href=\"#$id\"><span id=\"{$id}_label\">$label</span></a></li>\n";
                $htmlBody .= "  <div id=\"$id\" " . ($url <> '' ? 'url="' . $url . '" ' : '') . "class=\"ui-tabs-hide\">$innerHTML</div>\n";
        }
    }
    if (count($htmlJSLoad) > 0 || !empty($params['onLoad'])) {
        $html .= "<script type=\"text/javascript\">\n" .
                 "$(document).ready(function() {\n";
        foreach($htmlJSLoad as $key=>$val) {
            $html .= "  $(\"#{$key}_src\").attr(\"src\", \"{$val}\");\n";
        }
        if (!empty($params['onLoad'])) {
            $html .= "    {$params['onLoad']};\n";
        }
        $html .= "});\n" .
                 "</script>\n";
    }
    $html .= "<ul>\n";
    $html .= $htmlHeader;
    $html .= "</ul>\n";
    $html .= $htmlBody;
    $html .= "</div>\n";
    if ($params['autoInit'] === true) {
        $html .= "<script type=\"text/javascript\">\n" .
                 "$(document).ready(function() {\n " .
                 "    $(\"#{$params['id']}\").tabs();\n" .
                 "});\n" .
                 "</script>\n";
    }
    return $html;
    return nl2br(htmlspecialchars($html)) . $html;
    return nl2br(htmlspecialchars($html));
    


}
?>