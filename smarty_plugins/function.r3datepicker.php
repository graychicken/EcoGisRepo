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
 
function smarty_function_r3datepicker($params, &$smarty) {
    static $jsOutput = false;
    // - check name parameter
    if (empty($params['name'])) {
        $smarty->trigger_error("r3datepicker: missing 'name' parameter");
        return;
    } else {
        $name = $params['name'];
    }
    
    // - check name parameter
    if (empty($params['id'])) {
        $id = $params['name'];
    } else {
        $id = $params['id'];
    }
    
    // - check value parameter
    if (empty($params['value'])) {
        $value = '';
    } else {
        $value = $params['value'];
    }
    
    // - check image parameter
    if (empty($params['image'])) {
        $image = R3_APP_URL . 'images/ico_cal.gif';
    } else {
        $image = $params['image'];
    }
    
    // - check format parameter
    if (empty($params['format'])) {
        $format = 'dd/mm/yy';
    } else {
        $format = $params['format'];
    }
    
    // - check format parameter
    if (!empty($params['yearRange'])) {
        $range = ",yearRange: '{$params['yearRange']}'";
    } else {
        $range = ",yearRange: '-20:+10'";
    }
    
    // - check format parameter
    if (empty($params['constrainInput'])) {
        $constrainInput = true;
    } else {
        $constrainInput = $params['constrainInput'];
    }
    
    $html = "";
    
    // - javascript
    // TODO: attention format can be different for every datepicker
    if (!$jsOutput) {
        $jsOutput = true;
        $html .= 
<<<JS
<script type="text/javascript" >
    $(document).ready(function() {
        $('.r3datepicker').datepicker({showAnim: "fadeIn",
                                       showOn: "button",
                                       buttonImage: "{$image}",
                                       buttonImageOnly: true,
                                       duration: 'fast',
                                       dateFormat: '{$format}',
                                       constrainInput: {$constrainInput}
                                       $range });
        $.each($('.r3datepicker'), function(i, el) {
            if ($(el).attr('disabled')) {
                $(el).attr('disabled', false);
                $(el).datepicker('disable');
            }
        });
    });
</script>
JS;
    }
    // - input field
    $html .= "<input type=\"text\" id=\"{$id}\" name=\"{$name}\" value=\"{$value}\" class=\"r3datepicker\" maxlength=\"10\" style=\"width: 80px;\">\n";
    
    return $html;
}
?>