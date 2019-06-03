<?php

/**
 * Callback Function, which replace matches with gettext
 *
 * @param array $text it's alway $text[0]
 */
function _cbReplaceText($text) {
    $text = preg_match('/<lang\s*text=\"([^\"]*)\"\s*>/', $text[0], $matches);
    return _($matches[1]);
}

/**
 * Replace defined tags from given text
 * Tag-Replacement:
 *  - <lang> => $lang
 *  - <lang text="custom"> => gettext("custom)
 *
 * @param string $text
 * @return string with replaced tags
 */
function tagReplace($text) {
    global $lang;

    // - replace <lang>
    $text = str_replace('<lang>', $lang, $text);

    // - replace <lang text="custom">
    // DEBUG
    // $text = "add <lang text=\"-- selezionare --\"> dffsdf <lang text=\"-- selezionare --\">";
    $tot = preg_match_all('/<lang\s*text\s*=\s*\"(.*)\"\s*>/', $text, $matches);
    if ($tot > 0) {
        $text = preg_replace_callback('/<lang\s*text=\"[^\"]*\"\s*>/', '_cbReplaceText', $text);
    }

    return $text;
}

/**
 * Read field-configuration as array and interpret array-keys
 * this function calls tagReplace
 *
 * @param db      PDO database object
 * @param R3Auth  $auth authentication object
 * @param array   $fields field-configuration
 * @param array   $data
 */
function readFieldArray(\PDO $db, $auth, &$fields, &$data, $opts = array()) {
    $opts = array_merge(array('ignoreReadOnly' => false, 'ignoreHidden' => false), $opts);
    $isAuthManager = get_class($auth) == 'R3AuthManager' || is_subclass_of($auth, 'R3AuthManager');
    if ($isAuthManager) {
        if (isset($data['do_name'])) {
            $dn_name = $data['dn_name'];
        } else if (isset($data['do_id'])) {
            $domainData = $auth->getDomainDataFromID($data['do_id']);
            $dn_name = $domainData['dn_name'];
        } else {
            $dn_name = $auth->getDomainName();
        }
        if (isset($data['app_code'])) {
            $app_code = $data['app_code'];
        } else {
            $app_code = $auth->getApplicationCode();
        }
        if (isset($data['us_login'])) {
            $us_login = $data['us_login'];
        } else {
            $us_login = $auth->getLogin();
        }
        // Se la chiamata fallisce l'utente non viene trovato. Considero un nuovo utente
        $isAdd = !$auth->loadConfigFor($dn_name, $app_code, $us_login);
    } else {
        $isAdd = false;
    }

    //$fixedValues = array();
    foreach ($fields as $fieldname => $settings) {
        if (isset($settings['inivalue']) && !isset($settings['value'])) {
            $fields[$fieldname]['value'] = $auth->getConfigValue($settings['inivalue'][0], $settings['inivalue'][1], $settings['inivalue'][2]);
        }
        if (isset($settings['label']) && isset($lbl[$settings['label']])) {
            $fields[$fieldname]['label'] = tagReplace($lbl[$settings['label']]);
        } else if (isset($settings['label'])) {
            $fields[$fieldname]['label'] = _(tagReplace($settings['label']));
        }
        if ($opts['ignoreReadOnly'] == true) {
            $ReadOnly = false;
        } else {
            $ReadOnly = (isset($settings['kind']) && strToUpper(substr($settings['kind'], 0, 1)) == 'R');
        }
        if (isset($settings['type']) && in_array($settings['type'], array('select', 'select-multiple'))) {
            $a = array();
            if (isset($settings['sql']) && $settings['sql'] != '') {
                $sql = tagReplace($settings['sql']);
                $res = & $mdb2->query($sql);
                if (PEAR::isError($res)) {
                    throw new Exception($res->getMessage());
                }
                while ($row = $res->fetchRow(MDB2_FETCHMODE_ORDERED)) {
                    if (count($row) > 2) {
                        if ($ReadOnly || $row[0] == '' && $row[1] == '') {
                            $a[$row[0]] = $row[2];
                        } else {
                            $a[$row[1]][$row[0]] = $row[2];/** men� a tendina con option */
                        }
                    } else {
                        $a[$row[0]] = $row[1];
                    }
                }

                $fields[$fieldname]['values'] = $a;
                if ($ReadOnly) {
                    /** Menu a tendina Read Only: Correggo valore (test_cbReplaceTexto e non value) */
                    if (isset($data[$fieldname]) && isset($a[$data[$fieldname]])) {
                        $data[$fieldname] = $a[$data[$fieldname]];
                    }
                }
            } else {
                if ($ReadOnly) {
                    if (!isset($settings['inistorage'])) {

                        $data[$fieldname] = @$fields[$fieldname]['values'][$auth->getParam($fieldname)];
                    }
                } else {
                    foreach ($fields[$fieldname]['values'] as $key => $val) {
                        $fields[$fieldname]['val'][$key] = _(tagReplace($val));
                    }
                    $fields[$fieldname]['values'] = $fields[$fieldname]['val'];
                }
            }
        }
    }
    // Assign the user values
    if (!$isAdd) {
        if (isset($fields) && is_array($fields)) {
            foreach ($fields as $fieldname => $settings) {
                if (isset($settings['inistorage'])) {
                    $default = @$auth->getConfigValue($settings['inivalue'][0], $settings['inivalue'][1], $settings['inivalue'][2]);
                    $fields[$fieldname]['value'] = $auth->getConfigValue($settings['inistorage'][0], $settings['inistorage'][1], $default);
                } else if (isset($settings['storagetable'])) {
                    if (isset($data['us_id'])) {
                        $sql = "SELECT {$fieldname} FROM {$settings['storagetable']} WHERE us_id={$data['us_id']} ";
                        $res = & $mdb2->query($sql);
                        if (PEAR::isError($res)) {
                            throw new Exception($res->getMessage());
                        }
                        $fields[$fieldname]['value'] = array();
                        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                            $fields[$fieldname]['value'][] = $row[$fieldname];
                        }
                    }
                } else if (isset($data[$fieldname])) {
                    $fields[$fieldname]['value'] = $data[$fieldname];
                }
            }
        }
    }
}
