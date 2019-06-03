<?php
$isUserManager = true;
  
require_once dirname(__FILE__) . '/ajax_assign.php';
  
function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') { 
    global $lbl, $txt;

    $auth = R3AuthInstance::get();
    
    $fieldDescr = array('app_code'=>array(MISSING_FIELD=>"Il campo 'applicazione' e' obbligatorio",
                                          INVALID_FIELD=>"Il campo 'applicazione' contiene caratteri non validi. Solo lettere e numeri sono accettati",
                                          PK_ERROR=>"Il campo 'codice' immesso esiste gia'"),
                        'app_name'=>array(MISSING_FIELD=>"Il campo 'nome' e' obbligatorio"));
    
    $elems = AjaxSplitArray($elems);
    $objResponse = new xajaxResponse();
    
    $error = array();
    try {
        $privileges = array();
        if (isset($elems['selectedPrivileges'])) {
            foreach (explode(",", $elems['selectedPrivileges']) as $value) {
                $a = explode("|", $value);
                if (count($a) == 2) {
                    $privileges[] = array('ac_verb'=>$a[0], 'ac_name'=>$a[1], 'ga_kind'=>'ALLOW');
                }
            }    
        }
        if ($elems['act'] == 'add') {
            /** add a new application */
            $auth->addGroup(strtoupper(trim($elems['app_code'])), 
                            strtoupper(trim($elems['gr_name'])),
                            strtoupper(trim($elems['dn_name'])), 
                            $elems['gr_descr'],
                            $privileges);
        } else if ($elems['act'] == 'mod') {
            /** modify an application */
            $auth->modGroup(strtoupper(trim($elems['old_app_code'])), 
                            strtoupper(trim($elems['old_gr_name'])),                            
                            strtoupper(trim($elems['app_code'])), 
                            strtoupper(trim($elems['gr_name'])),
                            strtoupper(trim($elems['dn_name'])), 
                            $elems['gr_descr'],
                            $privileges);
        } else if ($elems['act'] == 'del') {
            /** delete an application */
            $auth->delGroup($elems['app_code'], 
                            $elems['gr_name']);
        } else {
            throw new Exception('Invalid action');
        }
    } catch (EPermissionDenied $e) {
        $error['element'][] = '';
        $error['message'][] = $e->getMessage(); 
    } catch (EDatabaseError $e) {
        $error['element'][] = '';
        $error['message'][] = "Database error: " . $e->getMessage(); 
    } catch (EInputError  $e) {
        $error['element'][] = $e->getField();
        if (isset($fieldDescr[$e->getField()][$e->getCode()])) {
            $error['message'][] = $fieldDescr[$e->getField()][$e->getCode()];
        } else {
            $error['message'][] = $e->getMessage(); 
        }
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
  
function load_select($type, $parentID=null, $selID=null, $extraParam=null) {
    global $auth, $authText;
    
	// Output
    $db = ezcDbInstance::get();
    $objResponse = new xajaxResponse();
	
    if ($type == 'privileges') {
        $selObj = "ajax_select_privileges";
        $acNameList = array();
        foreach($auth->getACNamesTypeList() as $key => $val) {
            $val = $authText["user_manager_acname_type_$key"];
            if ($parentID != '') {
                try {
                    $acNameList[$val] = $auth->mkAssociativeArray($auth->getACNamesList($parentID, array('where'=>'ac_type=' . $db->quote($key))), 'ACNAME');
                } catch (EPermissionDenied $e) {
                }
            }
        }
      // $acNameList = $auth->mkAssociativeArray($auth->getACNamesList($parentID), 'ACNAME');
    } else if ($type == 'groups') {
	  $selObj = "ajax_select_groups";
      $acNameList = $auth->mkAssociativeArray($auth->getGroupsList($parentID), 'GROUP');
      $objResponse->addScript("$selObj.addOption('', '".(!isset($txt['dd_select']) ? _('-- selezionare --') : $txt['dd_select'])."');");
    }
    
    
	// Stop Loading
    $objResponse->addScript("$selObj.stopLoading();");
	
	// Assign new element values
    if ($type == 'privileges') {
        // print_r($acNameList);
        foreach($acNameList as $group => $acNames) {
            foreach($acNames as $value => $text) {
                $objResponse->addScript("$selObj.addOption('$value', '$text', null, null, '$group', '$group');");
            }
    	}
    } else if ($type == 'groups') {
    	foreach($acNameList as $value => $text) {
    	  $objResponse->addScript("$selObj.addOption('$value', '$text');");
    	}
    }
    
    // - After loading
    if ($type == 'groups') {
      $objResponse->addScriptCall("doneLoadingGroups", count($acNameList) + 1);
    }
	return $objResponse->getXML();
}
  
function copy_group($app1, $grp1, $grp2) {
    global $auth;
    
    $objResponse = new xajaxResponse();
    if (strpos($grp2, '|') !== false)
      list($app2, $grp2) = explode('|', $grp2);
    
    $objResponse->addScriptCall("clearPrivileges");
    
    $permInfo = $auth->compareGroups($app1, $grp1 , $app2, $grp2);
    
    foreach($permInfo['missing1'] as $permission) {
      $objResponse->addScriptCall("addPrivileges", $permission['ac_verb'], $permission['ac_name']);
    }
    return $objResponse->getXML();
}
  
function append_group($app1, $grp1, $grp2) {
    global $auth;
    
    $objResponse = new xajaxResponse();
    if (strpos($grp2, '|') !== false)
      list($app2, $grp2) = explode('|', $grp2);
    
    $permInfo = $auth->compareGroups($app1, $grp1 , $app2, $grp2);
    
    foreach($permInfo['missing1'] as $permission) {
      $objResponse->addScriptCall("addPrivileges", $permission['ac_verb'], $permission['ac_name']);
    }
    
    return $objResponse->getXML();
}
