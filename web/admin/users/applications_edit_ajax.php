<?php
$isUserManager = true;
  
require_once dirname(__FILE__) . '/ajax_assign.php';
  
function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') { 
    global $lbl, $txt;

    $auth = R3AuthInstance::get();
    
    $fieldDescr = array('app_code'=>array(MISSING_FIELD=>"Il campo 'codice' e' obbligatorio",
                                          INVALID_FIELD=>"Il campo 'codice' contiene caratteri non validi. Solo lettere e numeri sono accettati",
                                          PK_ERROR=>"Il campo 'codice' immesso esiste gia'"),
                        'app_name'=>array(MISSING_FIELD=>"Il campo 'nome' e' obbligatorio"));
    
    $elems = AjaxSplitArray($elems);
    $objResponse = new xajaxResponse();
    
    $error = array();
    try{
    
        if ($elems['act'] == 'add') {
            /** add a new application */
            $auth->addApplication(strtoupper(trim($elems['app_code'])), trim($elems['app_name']));
        } else if ($elems['act'] == 'mod') {
            /** modify an application */
            $auth->modApplication($elems['old_app_code'], strtoupper(trim($elems['app_code'])), trim($elems['app_name']));
        } else if ($elems['act'] == 'del') {
            /** delete an application */
            $auth->delApplication($elems['app_code']);    
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
