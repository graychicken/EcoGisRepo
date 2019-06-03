<?php
$isUserManager = true;

require_once dirname(__FILE__) . '/ajax_assign.php';
  
function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') { 
    global $lbl, $txt;

    $auth = R3AuthInstance::get();
    
    $fieldDescr = array('do_names'=>array(MISSING_FIELD=>"Il campo 'nome dominio' e' obbligatorio",
                                          INVALID_FIELD=>"Il campo 'nome dominio' contiene caratteri non validi. Solo lettere e numeri sono accettati",
                                          PK_ERROR=>"Il campo 'nome dominio' immesso esiste gia'"),
                        'do_auth_type'=>array(MISSING_FIELD=>"Il campo 'tipo autenticazione' e' obbligatorio"),
                                              INVALID_FIELD=>"Il campo 'tipo autenticazione' non è valido",);

    $elems = AjaxSplitArray($elems);
    $objResponse = new xajaxResponse();
    
    $error = array();
    
    try {
    
        if (isset($elems['do_name'])) {
            $do_names = array(strtoupper($elems['do_name']));
        }   
        if (isset($elems['do_alias'])) {
            foreach (explode("\n", $elems['do_alias']) as $value) {
                $s = trim(strtoupper(str_replace("\r", '', $value)));
                if ($s != '') {
                    $do_names[] = $s;
                }
            }
        }
         
        if (isset($elems['selectedApplications'])) {         
            foreach (explode(",", $elems['selectedApplications']) as $value)
                $applications[] = strtoupper($value);
        }
        
        if ($elems['act'] == 'add') {
            /** add a new somain */
            $auth->addDomain($do_names, $elems['do_auth_type'], $elems['do_auth_data'], $applications, array('description'=>$dn_descriptions));
        } else if ($elems['act'] == 'mod') {
            /** modify a domain */
            $auth->modDomain($elems['old_do_name'], $do_names, $elems['do_auth_type'], $elems['do_auth_data'], $applications, array('description'=>$dn_descriptions));
        } else if ($elems['act'] == 'del') {
            /** delete an application */
            $auth->delDomain($elems['name']);    
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
