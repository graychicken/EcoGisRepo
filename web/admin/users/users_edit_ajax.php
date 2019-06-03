<?php

/* UTF-8 FILE: òàèü */
$isUserManager = true;

require_once dirname(__FILE__) . '/ajax_assign.php';

class EConstraintError extends Exception {
    
}

function dateToISO($date) {

    $date = trim($date);
    if ($date == '') {
        return null;
    }
    $date = str_replace('.', '-', $date);
    $date = str_replace('/', '-', $date);
    $resArr = explode('-', $date);
    if (count($resArr) == 3) {
        return $resArr[2] . '-' . $resArr[1] . '-' . $resArr[0];
    }
    return null;
}

function submitForm($elems, $doneFunc = 'AjaxFormObj.checkDone', $errFunc = 'AjaxFormObj.checkError') {
    global $lbl, $txt, $users_extra_fields, $mdb2;

    $auth = R3AuthInstance::get();

    $fieldDescr = array(
        'dn_name' => array(
            MISSING_FIELD => _("Il campo 'Dominio' è obbligatorio."),
            INVALID_FIELD => _("Il campo 'Dominio' non è valido.")
        ),
        'app_code' => array(
            MISSING_FIELD => _("Il campo 'Applicazione' è obbligatorio."),
            INVALID_FIELD => _("Il campo 'Applicazione' contiene caratteri non validi. Prego inserire solo lettere e numeri."),
            PK_ERROR => _("Il campo 'Applicazione' immesso è già presente in banca dati.")
        ),
        'us_login' => array(
            IN_USE => _("Impossibile cancellare questo utente perchè in uso."),
            MISSING_FIELD => _("Il campo 'Login' è obbligatorio."),
            INVALID_FIELD => _("Il campo 'Login' non è valido.")
        ),
        'us_name' => array(
            MISSING_FIELD => _("Il campo 'Nome' è obbligatorio.")
        ),
    );
    // print_r($elems);
    $elems = AjaxSplitArray($elems);
    if (!isset($elems['us_ip'])) {
        $elems['us_ip'] = null;
    }
    if (!isset($elems['us_start_date'])) {
        $elems['us_start_date'] = null;
    }
    if (!isset($elems['us_expire_date'])) {
        $elems['us_expire_date'] = null;
    }
    // print_r($elems);
    $objResponse = new xajaxResponse();

    /** User extra field for the common section */
    $extra_fields = $auth->getConfigValue('USER_MANAGER', 'EXTRA_FIELDS', array());
    if (isset($users_extra_fields)) {
        $extra_fields = array_merge($extra_fields, $users_extra_fields);
    }

    $error = array();

    try {
        if ($elems['act'] != 'del') {
            $errors = checkReq($extra_fields, $elems);
            if (!empty($errors)) {
                $errorMsg = (implode('\n', $errors));
                throw new Exception($errorMsg);
            }
        }

        /** extract the selected groups and permissions */
        $dn_name = strtoupper(trim($elems['dn_name']));
        $a = $auth->getDomainData($dn_name, true);
        $appList = $a['applications'];
        $groups = array();
        $perms = array();
        //$perms_n = array();
        if (is_array($appList)) {
            foreach ($appList as $appKey => $appVal) {
                if (isset($elems['selectedGroups_' . $appKey])) {
                    $elemValues = $elems['selectedGroups_' . $appKey];
                    foreach (explode(",", $elemValues) as $value) {
                        if ($value != '') {
                            $groups[] = array('app_code' => $appKey, 'gr_name' => $value);
                        }
                    }
                }

                if (isset($elems['selectedPerms_' . $appKey])) {
                    $elemValues = $elems['selectedPerms_' . $appKey];
                    foreach (explode(",", $elemValues) as $value) {
                        if ($value <> '') {
                            $a = explode('|', $value);
                            $perms[] = array('app_code' => $appKey, 'ac_verb' => $a[0], 'ac_name' => $a[1], 'ua_kind' => 'ON');
                        }
                    }
                }

                if (isset($elems['selectedPerms_n_' . $appKey])) {
                    $elemValues = $elems['selectedPerms_n_' . $appKey];
                    foreach (explode(",", $elemValues) as $value) {
                        if ($value <> '') {
                            $a = explode('|', $value);
                            $perms[] = array('app_code' => $appKey, 'ac_verb' => $a[0], 'ac_name' => $a[1], 'ua_kind' => 'OFF');
                        }
                    }
                }
            }
        }

        /** Extra fields in user table */
        $extras = array();
        foreach ($extra_fields as $key => $val) {
            if (!isset($val['inistorage']) && isset($elems[$key])) {
                if (isset($val['storagetable'])) {
                    $extras[$key] = array(
                        'table' => $val['storagetable'],
                        'data' => $elems[$key]
                    );
                } else {
                    $extras[$key] = $elems[$key];
                }
            }
        }

        global $dbini;
        if ($elems['act'] == 'add') {
            /** add a new application */
            if ($elems['us_password'] != $elems['us_password2']) {
                throw new Exception('Invalid password');
            }
            $data = array('us_name' => $elems['us_name'],
                'us_password' => $elems['us_password'],
                'us_status' => $elems['us_status'],
                'groups' => $groups,
                'perms' => $perms,
                'ip' => $auth->strToIPArray($elems['us_ip']),
                'us_start_date' => dateToISO($elems['us_start_date']),
                'us_expire_date' => dateToISO($elems['us_expire_date']),
                'us_pw_expire' => $elems['us_pw_expire'],
                'us_pw_expire_alert' => $elems['us_pw_expire_alert'],
				'as_code' => isset($elems['as_code']) ? $elems['as_code'] : null,
                'forceChangePassword' => (isset($elems['us_force_password_change']) && $elems['us_force_password_change'] == 'T'));
            $auth->addUserFromArray($dn_name, trim($elems['us_login']), $data, $extras, true);
        } else if ($elems['act'] == 'mod') {
            /** modify an application */
            if ($elems['us_password'] != '' && $elems['us_password'] != $elems['us_password2']) {
                throw new Exception('Invalid password');
            }
            $data = array(
                'us_name' => $elems['us_name'],
                'us_password' => $elems['us_password'],
                'us_status' => $elems['us_status'],
                'groups' => $groups,
                'perms' => $perms,
                'ip' => $auth->strToIPArray($elems['us_ip']),
                'us_start_date' => dateToISO($elems['us_start_date']),
                'us_expire_date' => dateToISO($elems['us_expire_date']),
                'us_pw_expire' => $elems['us_pw_expire'],
                'us_pw_expire_alert' => $elems['us_pw_expire_alert'],
				'as_code' => isset($elems['as_code']) ? $elems['as_code'] : null,
                'forceChangePassword' => (isset($elems['us_force_password_change']) && $elems['us_force_password_change'] == 'T')
            );
            $auth->modUserFromArray($elems['old_dn_name'], $elems['old_us_login'], $dn_name, trim($elems['us_login']), $data, $extras, true);
        } else if ($elems['act'] == 'del') {
            /** delete an application */
            // Check constraint
            $a = $auth->getConfigValue('USER_MANAGER', 'USER_CONSTRAINTS', array());
            if (is_array($a)) {
                $userData = $auth->getUserData($elems['dn_name'], null, $elems['us_login']);
                if ($userData !== null) {
                    foreach ($a as $val) {
                        if (isset($val['sql'])) {
                            $sql = $val['sql'];
                            $sql = str_replace('<UID>', $userData['us_id'], $sql);
                            $res = & $mdb2->query($sql);
                            if (PEAR::isError($res)) {
                                throw new EDatabaseError($res->getMessage() . $sql);
                            }
                            if ($row = $res->fetchRow()) {
                                if ($row[0] > 0) {
                                    if (isset($val['error_message'])) {
                                        $s = $val['error_message'];
                                    } else {
                                        $s = $val['Constraint error'];
                                    }
                                    if (isset($txt[$s])) {
                                        $s = $txt[$s];
                                    }
                                    throw new EConstraintError($s);
                                }
                            }
                        }
                    }
                }
            }
            $auth->delUser($elems['dn_name'], $elems['us_login'], false, true);
        } else {
            throw new Exception('Invalid action');
        }

        /** Extra fields in user table */
        foreach ($extra_fields as $key => $val) {
            if (isset($val['inistorage'])) {
                if (isset($elems[$key])) {
                    // Creo il parametro per ogni applicazione
                    //SS: TODO: Salvare solo un valore nella banca dati
                    $domainData = $auth->getDomainData($dn_name, true);
                    foreach ($domainData['applications'] as $appKey => $appVal) {
                        $auth->setConfigValueFor($dn_name, $appKey, trim($elems['us_login']), $val['inistorage'][0], $val['inistorage'][1], $elems[$key]);
                    }
                }
            }
        }
    } catch (EPermissionDenied $e) {
        $error['element'][] = '';
        $error['message'][] = _('Permesso negato'); //$e->getMessage(); 
    } catch (EDatabaseError $e) {
        //SS: E' sempre la login?
        if (strpos($e->getMessage(), 'constraint violation') !== false) {
            $error['element'][] = 'us_login';
			if ($elems['act'] == 'del') {
				$error['message'][] = _("Impossibile cancellare l'utente perchè vi sono dei dati ad esso legati");
			} else {
				$error['message'][] = "Database error: " . $e->getMessage();
			}	
        } else {
            $error['element'][] = '';
            $error['message'][] = "Database error: " . $e->getMessage();
        }
    } catch (EConstraintError $e) {
        $error['element'][] = '';
        $error['message'][] = $e->getMessage();
    } catch (EInputError $e) {
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
        $errText = _('Attenzione!') . "\n - " . implode("\n - ", $error['message']);
        $objResponse->addScriptCall($errFunc, $errText, $error['element'][0]);
    } else {
        $objResponse->addScriptCall($doneFunc);
    }

    return $objResponse->getXML();
}

function checkReq($extra_fields, $elems) {
    /** checks * */
    $parseError = null;
    $errors = array();
    foreach ($extra_fields as $key => $val) {
        $label = tagReplace($val['label']);

        /** check required * */
        if (isset($val['required']) && $val['required'] === true) {
            if ($elems[$key] == '' && !in_array($label, $errors)) {
                $msg = sprintf(_("\"%s\" è obbligatorio"), $label);
                $errors[$label] = $msg;
                break;
            }
        }

        /** check maxlength * */
        if (isset($val['type']) && $val['type'] == 'text' || $val['type'] == 'string') {
            // check string length
            if (isset($val['maxlength']) && (mb_strlen($elems[$key]) > $val['maxlength'])) {
                $sanValue = mb_substr($elems[$key], 0, $val['maxlength']);
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
                $msg = sprintf(_("La stringa '%s' non può essere interpretata come intero"), $elems[$key]);
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
                $msg = sprintf(_("La stringa '%s' non può essere interpretata come float"), $elems[$key]);
                $errors[$label] = $msg;
                break;
            }
        }

        /** check uniqes * */
        if (isset($val['unique']) && is_array($val['unique'])) {
            global $mdb2;
            $sql = "SELECT count(*) FROM {$val['unique']['table']} WHERE {$val['unique']['key']} = " . $mdb2->quote($elems[$key]);
            if (!empty($elems['old_us_login']))
                $sql .= " AND us_login <> " . $mdb2->quote($elems['old_us_login']);

            $result = & $mdb2->query($sql);
            $vlu = $result->fetchRow(0);
            if ($vlu[0] > 0) {
                $msg = sprintf(_("$label \"%s\" esiste già"), $elems[$key]);
                $errors[$label] = $msg;
                break;
            }
            continue;
        }
    }
    return $errors;
}

?>