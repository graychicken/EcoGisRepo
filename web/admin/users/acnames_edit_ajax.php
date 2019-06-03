<?php
$isUserManager = true;
  
require_once dirname(__FILE__) . '/ajax_assign.php';
  
function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') { 
    global $lbl, $txt;

    $auth = R3AuthInstance::get();
    $fieldDescr = array('app_code'=>array(MISSING_FIELD=>(!isset($txt['missing_fld_app']) ? _("Il campo 'applicazione' e' obbligatorio") : $txt['missing_fld_app']),
                                          INVALID_FIELD=>"Il campo 'codice' contiene caratteri non validi. Solo lettere e numeri sono accettati",
                                          PK_ERROR=>"Il campo 'codice' immesso esiste gia'"),
                        'app_name'=>array(MISSING_FIELD=>"Il campo 'nome' e' obbligatorio"));
    
    // print_r($elems);
    $elems = AjaxSplitArray($elems);
        //print_r($elems);
    $objResponse = new xajaxResponse();

    $error = array();
    try{
        if ($elems['act'] == 'add') {
            /** add a new acname */
            foreach(explode(',', str_replace(';', ',', $elems['ac_verb'])) as $verb) {
                $auth->addACName($elems['app_code'], 
                                 strtoupper(trim($verb)), 
                                 strtoupper(trim($elems['ac_name'])), 
                                 trim($elems['ac_descr']), 
                                 trim($elems['ac_order']), 
                                 strtoupper($elems['ac_active']) == 'T',
                                 $elems['ac_type']);
            }
        } else if ($elems['act'] == 'mod') {
            /** modify an acname */
            $auth->modACName($elems['old_app_code'], 
                             $elems['old_ac_verb'], 
                             $elems['old_ac_name'], 
                             $elems['app_code'], 
                             strtoupper(trim($elems['ac_verb'])), 
                             strtoupper(trim($elems['ac_name'])), 
                             trim($elems['ac_descr']), 
                             trim($elems['ac_order']), 
                             strtoupper($elems['ac_active']) == 'T',
                             $elems['ac_type']);
        } else if ($elems['act'] == 'del') {
            /** delete an acname */
            $auth->delACName($elems['app_code'], 
                             $elems['ac_verb'], 
                             $elems['ac_name']);
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
	  $errText = (!isset($txt['err_store_failed']) ? _("Salvataggio fallito").":" : $txt['err_store_failed']) . "\n - " . implode("\n - ", $error['message']); 
      $objResponse->addScriptCall($errFunc, $errText, $error['element'][0]);
    } else {
      $objResponse->addScriptCall($doneFunc);
	}
	
    return $objResponse->getXML();
}
  
