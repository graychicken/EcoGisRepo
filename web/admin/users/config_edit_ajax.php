<?php
$isUserManager = true;
  
require_once dirname(__FILE__) . '/ajax_assign.php';
  
if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg() {
        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON. Check that key and text values are enclosed in double quotes',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
    }
}

function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') { 
    global $lbl, $txt, $dbini;

    $auth = R3AuthInstance::get();
    if (!$auth->hasPerm('EDIT', 'CONFIG') && !$auth->hasPerm('MOD', 'CONFIG')) {
        die("PERMISSION DENIED [EDIT|MOD/CONFIG]\n");
    }
    
    $elems = AjaxSplitArray($elems);
    if (!isset($elems['old_us_login'])) {
        $elems['old_us_login'] = '';
    }
    $objResponse = new xajaxResponse();
    
    $error = array();
    
    try {
        if (($p = strpos($elems['us_login'], '|')) !== false) {
            $elems['us_login']= substr($elems['us_login'], $p + 1);
        }
        if (($p = strpos($elems['old_us_login'], '|')) !== false) {
            $elems['old_us_login']= substr($elems['old_us_login'], $p + 1);
        }        
        
        if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $elems['dn_name'] = $auth->domain;
        }
        if (!$auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
            $elems['app_code'] = $auth->application;
        }
        if (!$auth->hasPerm('SHOW', 'ALL_USERS')) {
            $elems['us_login'] = $auth->login;
        }

        if ($elems['act'] == 'del') {
            $dbini->removeAttribute($elems['dn_name'], $elems['app_code'], $elems['us_login'], 
                                    $elems['se_section'], $elems['se_param']);
        } else {
            $se_section = strtoupper(trim($elems['se_section']));
            $se_param = strtoupper(trim($elems['se_param']));
            if ($elems['se_type'] == 'STRING') {
                $se_type_ext = $elems['se_type_ext_STRING'];
            } else if ($elems['se_type'] == 'ENUM') {
                $se_type_ext = $elems['se_type_ext_ENUM'];
            } else {
                $se_type_ext = '';
            }
            if ($elems['se_type'] == 'ARRAY') {
                $se_value = trim($elems['se_value_TEXT']);
                @eval('$my_array = array(' . $se_value . ');');
                if (!isset($my_array)) {
                   throw new Exception('Invalid value');
                }
                $se_value = serialize($my_array);
                // echo $se_value;
            } else if ($elems['se_type'] == 'JSON') {
                if (trim($elems['se_value_TEXT']) == '') {
                    $se_value = null;
                } else {
                    $se_value = $elems['se_value_TEXT'];
                    $jsonData = @json_decode($se_value, true);
                    if ($jsonData === null) {
                        throw new Exception('JSon error: ' . json_last_error_msg());
                    }
                }    
            } else if ($elems['se_type'] == 'TEXT') {
                $se_value = $elems['se_value_TEXT'];
            } else {
                $se_value = $elems['se_value_normal'];
            }
            
            $dbini->replaceAttribute($elems['old_dn_name'], $elems['old_app_code'], $elems['old_us_login'], 
                                     $elems['old_se_section'], $elems['old_se_param'],
                                     $elems['dn_name'], $elems['app_code'], $elems['us_login'], 
                                     $se_section, $se_param, $se_value,
                                     $elems['se_type'], $se_type_ext,
                                     $elems['se_private'], $elems['se_order'], $elems['se_descr']);
        }
    } catch (EPermissionDenied $e) {
        $error['element'][] = '';
        $error['message'][] = $e->getMessage(); 
    } catch (EDatabaseError $e) {
        $error['element'][] = '';
        $error['message'][] = "Database error: " . $e->getMessage(); 
    } catch (Exception $e) {
        $error['element'][] = '';
        $error['message'][] = 'Generic error: ' . $e->getMessage(); 
    }
    
	// Action
    if (count($error) > 0) {
	  $errText = $txt['err_store_failed'] . "\n - " . implode("\n - ", $error['message']); 
      $objResponse->addScriptCall($errFunc, $errText, $error['element'][0]);
    } else {
      $objResponse->addScriptCall($doneFunc);
	}
	
    return $objResponse->getXML();
}
