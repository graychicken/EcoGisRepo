<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.r3colorpicker.php
 * Type:     function
 * Name:     r3colorpicker
 * Purpose:  adds jquery r3colorpicker
 * -------------------------------------------------------------
 */
function smarty_function_r3colorpicker($params, &$smarty) {
    
    // - check name parameter
    if (empty($params['name'])) {
        $smarty->trigger_error("r3colorpicker: missing 'name' parameter");
        return;
    } else {
        $name = $params['name'];
    }
    
    // - check color parameter
    if (empty($params['color'])) {
        $color = '';
    } else {
        $color = $params['color'];
    }
    
    if (empty($params['colors'])) {
      $colors = "['#000000', '#666666', '#999999', '#CCCCCC', '#CC9966', ".
			    " '#009900', '#006633', '#660000', '#990000', '#996633', ".
			    " '#00FF00', '#99FF99', '#00FFFF', '#3399FF', '#0000FF', ".
			    " '#FF9999', '#FF00FF', '#9900FF', '#660099', '#000099', ".
			    " '#FF0000', '#FF9900', '#FFCC00', '#FFFF00', '#FFFFFF']";
    			
    } else if (is_array($params['colors']) && count($params['colors']) > 0) {
      $colors = "['".implode("', '", $params['colors'])."']";
    } else if (is_array($params['colors']) && count($params['colors']) == 0) {
      $smarty->trigger_error("r3colorpicker: missing elements for 'colors' parameter");
      return;
    } else {
      $smarty->trigger_error("r3colorpicker: wrong type for 'colors' parameter. should be an array");
      return;
    }
    
    // - check color parameter
    if (!empty($params['maxlength'])) {
        $params['maxlength'] = (int)$params['maxlength'];
        if (!empty($params['maxlength'])) {
            $maxlength = "maxlength=\"{$params['maxlength']}\"";
        }
    }
    
    $html = "";
    $html .= "<script type=\"text/javascript\" >\n" .
             "  $(document).ready(function() {\n " .
             "    $('#{$name}_color_selector').addColorPicker({clickCallback: function(c) { \n " .
             "                                                                  $('#pw_{$name}').css('background-color', c); \n " .
             "                                                                  if (c.substr(0, 3) == 'rgb') { \n " .
             "                                                                    c = c.replace('rgb(', ''); c = c.replace(')', ''); var tmp = c.split(','); \n " .
             "                                                                    var red = parseInt(tmp[0]); \n ".
             "                                                                    var green = parseInt(tmp[1]); \n ".
             "                                                                    var blue = parseInt(tmp[2]); \n ".
             "                                                                    var hex = new Array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f'); \n " .
             "                                                                    var rgb = new Array(); \n " .
             "                                                                    var k = 0; \n " .
             "                                                                    for (var i = 0; i < 16; i++) for (var j = 0; j < 16; j++) { rgb[k] = hex[i] + hex[j]; k++; } \n " .
             "                                                                    c = '#' + rgb[red] + rgb[green] + rgb[blue]; \n " .
             "                                                                  } \n " .
             "                                                                  $('#{$name}').attr('value', c); \n " .
             "                                                                }," .
             "                                                 blotchElemType: 'div', " .
             "                                                 colors: {$colors} " .
             "                                                }); \n " .
             "  }); \n" .
             "</script>\n";
    $html .= "<img id=\"pw_{$name}\" class=\"color_preview\" style=\"background-color:{$color}\" src=\"../images/spacer.gif\">\n" .
             "<input type=\"text\" class=\"color_input\" id=\"{$name}\" name=\"{$name}\" value=\"{$color}\" {$maxlength}>\n" .
             "<img src=\"../images/ico_color.gif\" class=\"color_icon\" onClick=\"$('#{$name}_color_selector').css('display') == 'none' ? $('#{$name}_color_selector').css('display', '') : $('#{$name}_color_selector').css('display', 'none')\" />\n" . 
             "<div id=\"{$name}_color_selector\" class=\"color_selector\" style=\"display:none;\" onClick=\"$(this).css('display') == 'none' ? $(this).css('display', '') : $(this).css('display', 'none')\"></div>\n";
    return $html;
}
?> 