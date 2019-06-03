<?php

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.r3datepicker.php
 * Type:     function
 * Name:     r3datepicker
 * Purpose:  adds jquery r3datepicker
 * -------------------------------------------------------------
 */

function smarty_function_r3grid($params, &$smarty) {

    // - check name parameter
    if (empty($params['url'])) {
        $smarty->trigger_error("r3grid: missing 'url' parameter");
        return;
    } else {
        $url = $params['url'];
    }

    // - check value parameter
    if (empty($params['definition'])) {
        $smarty->trigger_error("r3grid: missing 'definition' parameter");
        return;
    } else if (!is_array($params['definition'])) {
        $smarty->trigger_error("r3grid: 'definition' parameter is not an array");
        return;
    } else {
        $def = $params['definition'];
    }

    // - check format parameter
    if (isset($params['options']) && !is_array($params['options'])) {
        $smarty->trigger_error("r3grid: 'definition' parameter is not an array");
        return;
    } else {
        $options = array(
            'datatype' => 'json',
            'table' => 'r3grid',
            'pager' => 'r3pager',
            'rowNum' => 10,
            'height' => '420',
            'theme' => 'steel',
            'loadonce' => false
        );
        if (isset($params['options']))
            $options = array_merge($options, $params['options']);
    }

    $html = "";

    $events = "";
    if (is_array($def['events']) && count($def['events']) > 0) {
        foreach ($def['events'] as $eventName => $eventAction)
            $events .= ",$eventName: $eventAction\n";
    }

    $colNames = implode("','", $def['colNames']);
    $colModel = implode(",\n", array_map("json_encode", $def['colModel']));

    $loadOnce = '';
    if ($options['loadonce'])
        $loadOnce = ", loadonce: true";
        
    if ($options['search']) {
        $withSearch = <<<JS
        jQuery("#{$options['table']}").jqGrid('navGrid','#{$options['pager']}',{del:false,add:false,edit:false,search:true}, {}, {}, {}, {multipleSearch:true});
JS;
    } else {
        $withSearch = <<<JS
        jQuery("#{$options['table']}").jqGrid('navGrid','#{$options['pager']}',{del:false,add:false,edit:false,search:false}, {}, {}, {}, {multipleSearch:false});
JS;
    }

    // - javascript
    $html .= <<<JS
        <script type="text/javascript" >
            jQuery(document).ready(function() {
                jQuery("#{$options['table']}").delegate("button[name=grid_action]", 'hover', function() {
                    $(this).toggleClass('ui-state-hover');
                }).delegate("button[name=grid_action]", 'click', function() {
                     var r3data = eval(jQuery(this).attr('r3data'));
                     var r3click = eval(jQuery(this).attr('r3click'));
                     r3click({data: {args: r3data }});
                });
                jQuery("#{$options['table']}").jqGrid({
                    url:'{$url}',
                    postData:{
                        init: '{$options['init']}'
                    },
                    loadComplete: function() {
                        jQuery("#{$options['table']}").jqGrid('setGridParam',{postData:{init:'T'}});
                    },
                    datatype: '{$options['datatype']}',
//                    mtype: 'GET',
                    colNames: ['{$colNames}'],
                    colModel: [
                        {$colModel}
                    ],
//                    autowidth: true, // TODO: !!!
                    rownumbers: true,
                    pager: '#{$options['pager']}',
                    rowNum: {$options['rowNum']},
//                    imgpath: '../javascript/jquery/plugins/jqGrid/themes/{$options['theme']}/images',
                    height: '{$options['height']}',
                    viewrecords: true
                    {$loadOnce}
                    {$events}
                }); //.filterToolbar();
                {$withSearch}
            });
        </script>
JS;




    // - input field
    $html .= "<input type=\"hidden\" id=\"{$options['table']}_url\" value=\"" . str_replace("&", "&amp;", $url) . "\" />\n" .
            "<table id=\"{$options['table']}\" class=\"scroll\"></table>\n" .
            // TODO: Dummy row has problems on column resize!!
            // "  <!-- Dummy row for doctype XHTML 1.0 -->\n" .
            // "  <tr style=\"display:none;\">".implode('', array_fill( 0 , count($def['colModel']), "<td />" ))."</tr>\n" .
            "<div id=\"{$options['pager']}\" class=\"scroll\" style=\"text-align:center;width:500px\"></div>\n";
    // - action template
    if (count($def['colActions']) > 0) {
        $html .= "<div id=\"{$options['table']}_actions\" style=\"display:none;\">\n" . implode("\n", $def['colActions']) . "</div>\n";
    }

    return $html;
}

?> 