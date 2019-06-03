<?php
$isUserManager = true;

require_once dirname(__FILE__) . '/ajax_assign.php';

function submitForm($elems, $doneFunc='AjaxFormObj.checkDone', $errFunc='AjaxFormObj.checkError') {
    global $lbl, $txt, $users_extra_fields;
    $db = ezcDbInstance::get();
    $auth = R3AuthInstance::get();

    $fieldDescr = array(
        'app_code' => array(
            MISSING_FIELD => _("Il campo 'applicazione' e' obbligatorio"),
            INVALID_FIELD => _("Il campo 'applicazione' contiene caratteri non validi. Solo lettere e numeri sono accettati"),
            PK_ERROR => _("Il campo 'codice' immesso esiste gia'")
        ),
        'app_name' => array(
            MISSING_FIELD => _("Il campo 'nome' e' obbligatorio")
        )
    );

    // print_r($elems);
    $elems = AjaxSplitArray($elems);
    // print_r($elems);
    $objResponse = new xajaxResponse();

    /** User extra field for the common section */
    $extra_fields = $auth->getConfigValue('USER_MANAGER', 'EXTRA_FIELDS', array());
    if (isset($users_extra_fields)) {
        $extra_fields = array_merge($extra_fields, $users_extra_fields);
    }

    $error = array();



    try {
        $errors = checkReq($extra_fields, $elems);
        if (!empty($errors)) {
            $errorMsg = (implode('\n', $errors));
            throw new Exception($errorMsg);
        }

        if ($auth->passwordStatus < 0 && $elems['us_password'] == '') {
            throw new Exception('Password must be set');
        }
        /** Extra fields in user table */
        $extras = array();
        foreach ($extra_fields as $key => $val) {
            if (!isset($val['inistorage']) && !isset($val['kind'])) {
                if (isset($elems[$key])) {
                    $extras[$key] = $elems[$key];
                }
            }
        }

        /** password check */
        if ($elems['us_password'] != '' && $elems['us_password'] != $elems['us_password2']) {
            throw new Exception('Invalid password');
        }
        if ($elems['us_password'] != '') {
            $auth->setParam('us_password', $elems['us_password'], true);
        }

        foreach ($extras as $key => $val) {
            $auth->setParam($key, $val, true);
        }

        /** Extra fields in user table */
        foreach ($extra_fields as $key => $val) {
            if (isset($val['inistorage']) && !isset($val['kind'])) {
                if (isset($elems[$key])) {
                    $auth->setConfigValue($val['inistorage'][0], $val['inistorage'][1], $elems[$key]);
                }
            }
        }
    } catch (EPermissionDenied $e) {
        $error['element'][] = '';
        $error['message'][] = $e->getMessage();
    } catch (EDatabaseError $e) {
        $error['element'][] = '';
        $error['message'][] = "Database error: " . $e->getMessage();
    } catch (EInputError $e) {
        $error['element'][] = $e->getField();
        if (isset($fieldDescr[$e->getField()][$e->getCode()])) {
            $error['message'][] = $fieldDescr[$e->getField()][$e->getCode()];
        } else {
            $error['message'][] = $e->getMessage();
        }
    } catch (Exception $e) {
        $error['element'][] = '';
        //$error['message'][] = 'Generic error: ' . $e->getMessage();
        $error['message'][] = $e->getMessage();
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

function checkReq($extra_fields, $elems) {
    /** checks * */
    $db = ezcDbInstance::get();
    $parseError = null;
    $errors = array();
    foreach ($extra_fields as $key => $val) {
        $label = tagReplace($val['label']);
        
        /** check required * */
        if (isset($val['required']) && $val['required'] === true) {
            if ($elems[$key] == '' && !in_array($label, $errors)) {
                $msg = sprintf(_("\"%s\" Ë obbligatorio"), $label);
                $errors[$label] = $msg;
                break;
            }
        }

        /** check maxlength * */
        if (isset($val['type']) && $val['type'] == 'text' || $val['type'] == 'string') {
            // check string length
            if (isset($val['maxlength']) && (mb_strlen($elems[$key]) > $val['maxlength'])) {
                $sanValue = mb_substr($rv, 0, $val['maxlength']);
            } else {
                $sanValue = $elems[$key];
            }
        }

        /** check type integer * */
        if (isset($val['type']) && $val['type'] == 'integer') {
            if (trim($elems[$key]) === '') {
                $sanValue = null;
            } else if (is_numeric($elems[$key])) {
                if ((int) $elems[$key] == $elems[$key]) {
                    $sanValue = (int) $elems[$key];
                } else {
                    $parseError = true;
                }
            } else if (is_string($elems[$key])) {
                if (preg_match('/^[+-]?[0-9]+$/', trim($elems[$key]))) {
                    $sanValue = (int) $elems[$key];
                } else {
                    $parseError = true;
                }
            } else {
                $parseError = true;
            }

            if ($parseError) {
                $msg = sprintf(_("La stringa '%s' non puÚ essere interpretata come intero"), $elems[$key]);
                $errors[$label] = $msg;
                break;
            }
        }

        /** check type float * */
        if (isset($val['type']) && $val['type'] == 'float') {
            if (trim($elems[$key]) === '') {
                $sanValue = null;
            } else if (is_numeric($elems[$key])) {
                $sanValue = $elems[$key];
            } else if (is_string($elems[$key])) {
                $sign = +1;
                // is integer?
                if (preg_match('/^\s*([+-]?)([0-9]+)\s*$/', $elems[$key], $parts)) {
                    if ($parts[1] == '-')
                        $sign = -1;
                    $sanValue = $sign * (float) $parts[2];
                    // or float?
                } else if (preg_match('/^([+-]?)([0-9]*)([\.,]?)([0-9]*)$/', $elems[$key], $parts)) {
                    if ($parts[1] == '-')
                        $sign = -1;
                    $sanValue = $sign * (float) ($parts[2] . '.' . $parts[4]);
                } else {
                    $parseError = true;
                }
            }
            if ($parseError) {
                $msg = sprintf(_("La stringa '%s' non puÚ essere interpretata come float"), $elems[$key]);
                $errors[$label] = $msg;
                break;
            }
        }

        /** check uniqes * */
        if (isset($val['unique']) && is_array($val['unique'])) {
            $sql = "SELECT COUNT(*) FROM {$val['unique']['table']} WHERE {$val['unique']['key']} = " . $db->quote($elems[$key]);
            if (!empty($elems['us_login'])) {
                $sql .= " AND us_login <> " . $db->quote($elems['us_login']);
            }
            if ($db->query($sql)->fetchColumn() > 0) {
                $msg = sprintf(_("$label \"%s\" esiste gi√†"), $elems[$key]);
                $errors[$label] = $msg;
                break;
            }
            continue;
        }
    }
    return $errors;
}
