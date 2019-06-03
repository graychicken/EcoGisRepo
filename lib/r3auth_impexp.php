<?php


if (defined("__R3_AUTH_MANAGER_IMPEXP__")) return;
define("__R3_AUTH_MANAGER_IMPEXP__", 1);

define('R3AUTHMANAGER_IMPORT_EXPORT_VERSION', '0.4a');

require_once __DIR__ . '/r3xml_utils.php';

class R3AuthManagerImpExp extends R3AuthManager
{
    protected $dbini = null;

    /**
     * Get the module version
     *
     * @param array           name ot the class to get the version
     * @return string|null    return the version text or null if faild
     * @access public
     */

    public function getVersionString($className = null) {

        if ($className == '' || $className == 'R3AuthManagerImpExp') {
            return R3AUTHMANAGER_IMPORT_EXPORT_VERSION;
        }
        return parent::getVersionString($className);
    }


    /**
     * if $val is an array return a valid string to inject into an xml node
     *
     * @param mixed     the param to adjust
     * @return string   a valid string
     * @access private
     */

    private function adjXMLValue($val) {

        if (is_array($val)) {
            return substr(var_export($val, true), 7, -3);
        }
        return $val;

    }


    /**
     * Get all the available applications of the authenticated user.
     * The permission SHOW APPLICATION must be set
     * if the permission SHOW ALL_APPLICATIONS is set all the appliocations are returned
     *
     * @param array     'field', 'where', 'order', 'offset', 'limit' array
     * @param integer   return the number of applications
     * @return array    a numeric array with all the applications
     * @access public
     */

    public function exportConfiguration($dn_name, $app_code, $us_login, $section, $mode) {

        $this->log('R3AuthManagerImpExp::exportConfiguration() called.', AUTH_LOG_DEBUG);

        if (!$this->hasPerm('EXPORT', 'CONFIG')) {
            $this->log("R3AuthManagerImpExp::exportConfiguration(): Permission denied.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }

        if ($this->dbini === null) {
            $this->dbini = new R3DBIni($this->db, $this->options['options'], $dn_name, $app_code, $us_login);
            $this->dbini->setDomainName($dn_name, true);
            $this->dbini->setApplicationCode($app_code, true);
            $this->dbini->setUserLogin($app_code, $us_login, true);
            $this->dbini->setShowPrivate(true);
        }


        $data = $this->dbini->getAllAttributes($section);
        $result = array();


        $lastSection = '';
        $sectionData = array();
        foreach($data as $key1=>$value1) {
            foreach($value1 as $key2=>$value2) {
                if ($lastSection <> $value2['se_section']) {
                    $a = array();
                    if (count($sectionData) > 0) {
                        $a['mode'] = $mode;
                        if ($dn_name <> '') {
                            $a['domain'] = $dn_name;
                        }
                        if ($app_code <> '') {
                            $a['application'] = $app_code;
                        }
                        if ($us_login <> '') {
                            $a['user'] = $us_login;
                        }
                        $a['section'] = $lastSection;
                        $a['parameters'] = $sectionData;
                        $result['sections'][] = $a;
                    }
                    $lastSection = $value2['se_section'];
                    $sectionData = array();
                }
                $a = array();
                $a['parameter'] = $value2['se_param'];
                if ($value2['se_value'] <> '') {
                    $a['value'] = $this->adjXMLValue($value2['se_value']);
                }
                $a['type'] = $value2['se_type'];
                if ($value2['se_type_ext'] <> '') {
                    $a['type-extended'] = $this->adjXMLValue($value2['se_type_ext']);
                }
                if ($value2['se_descr'] <> '') {
                    $a['description'] = $this->adjXMLValue($value2['se_descr']);
                }
                if ($value2['se_order'] <> 0) {
                    $a['order'] = $this->adjXMLValue($value2['se_order']);
                }
                if ($value2['se_private'] <> 'F') {
                    $a['private'] = $this->adjXMLValue($value2['se_private']);
                }
                $sectionData[] = $a;
            }
        }


        if (count($sectionData) > 0) {
            if (count($sectionData) > 0) {
                $data = array('mode'=>$mode);
                if ($dn_name <> '') {
                    $data['domain'] = $dn_name;
                }
                if ($app_code <> '') {
                    $data['application'] = $app_code;
                }
                if ($us_login <> '') {
                    $data['user'] = $us_login;
                }
                $data['section'] = $value2['se_section'];
                $data['parameters'] = $sectionData;
                $result['sections'][] = $data;
            }
        }
        $dom = array2xml(array('configuration'=>$result), array('document_element' => 'auth'));
        // echo $dom->saveXML();
        return $dom->saveXML();
    }

    /**
     * Prepare the array of the ACName for the array2xml function
     *
     * @param array     acname data
     * @return array    array for array2xml
     * @access private
     */
    private function prepareACNameForXML($data) {

        $result = array();
        $acNameData = array();
        $lastApplication = '';
        foreach($data as $key=>$value) {
            $a = array();
            if ($lastApplication <> $value['app_code']) {
                if (count($acNameData) > 0) {
                    $result['applications'][] = array('code'=>$lastApplication, 'parameters'=>$acNameData);
                }
                $lastApplication = $value['app_code'];
                $acNameData = array();
            }
            $a['verb'] = $value['ac_verb'];
            $a['name'] = $value['ac_name'];

            if ($value['ac_type'] <> 'S') {
                $a['type'] = $value['ac_type'];
            }
            if ($value['ac_active'] <> 'T') {
                $a['active'] = $value['ac_active'];
            }
            if ($value['ac_order'] <> '0') {
                $a['order'] = $value['ac_order'];
            }
            if ($value['ac_descr'] <> '') {
                $a['description'] = $value['ac_descr'];
            }
            $acNameData[] = $a;
        }
        if (count($acNameData) > 0) {
            $result['applications'][] = array('code'=>$lastApplication, 'parameters'=>$acNameData);
        }
        return $result;
    }

    /**
     * Function which exports permissions visible on list
     *
     * @param string    application code
     * @param string    permission verb
     * @param string    permission name
     * @param string    permission type
     * @return string   the xml as string
     * @access public
     */

    public function exportACName($app_code, $ac_verb, $ac_name, $ac_type = null) {

        $this->log('R3AuthManagerImpExp::exportACName() called.', AUTH_LOG_DEBUG);

        if (!$this->hasPerm('EXPORT', 'ACNAME')) {
            $this->log("R3AuthManagerImpExp::exportACName(): Permission denied.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }

        /** filters */
        $filter_where = '1 = 1';
        if ($ac_verb != '') {
            $filter_where .= 'AND ac_verb=' . $this->quote($ac_verb);
        } 

        if ($ac_name != '') {
            $filter_where .= 'AND ac_name=' . $this->quote($ac_name);
        }

        if ($ac_type != '') {
            $filter_where .= 'AND ac_type=' . $this->quote($ac_type);
        }

        $data = $this->getACNamesList($app_code,
                array('fields'=>'app_code, ac_type, ac_verb, ac_name, ac_active, ac_order, ac_descr',
                'where'=>$filter_where,
                'order'=>'app_code, ac_order, ac_type, ac_verb, ac_name'));

        $data = $this->prepareACNameForXML($data);
        $dom = array2xml(array('acnames'=>$data), array('document_element' => 'auth'));
        // echo $dom->saveXML();
        return $dom->saveXML();
    }

    /**
     * Prepare the array of the group for the array2xml function
     *
     * @param array     group data
     * @return array    array for array2xml
     * @access private
     */
    private function prepareGroupForXML($data) {

        $result = array();
        $acNameData = array();
        $lastApplication = '';
        foreach($data as $key=>$value) {
            $a = array();
            if ($lastApplication <> $value['app_code']) {
                if (count($acNameData) > 0) {
                    $result['applications'][] = array('code'=>$lastApplication, 'parameters'=>$acNameData);
                }
                $lastApplication = $value['app_code'];
                $acNameData = array();
            }
            $a['name'] = $value['gr_name'];
            if ($value['gr_descr'] <> '') {
                $a['description'] = $value['gr_descr'];
            }
            // if ($value['dn_type'] <> '') {
            // $a['type'] = $value['dn_type'];
            // }
            if ($value['dn_name'] <> '') {
                $a['domain'] = $value['dn_name'];
            }
            if (isset($value['perm'])) {
                foreach($value['perm'] as $perm) {
                    $p = array();
                    $p['verb'] = $perm['ac_verb'];
                    $p['name'] = $perm['ac_name'];
                    if ($perm['kind'] <> 'ALLOW') {
                        $p['kind'] = $perm['kind'];
                    }
                    $a['permissions'][] = $p;
                }
            }
            $acNameData[] = $a;
        }
        if (count($acNameData) > 0) {
            $result['applications'][] = array('code'=>$lastApplication, 'parameters'=>$acNameData);
        }
        return $result;
    }

    /**
     * Function which exports groups and optionally the acl definition
     *
     * @param string application code
     * @param string permission verb
     * @param string permission name
     * @param string permission type
     */

    public function exportGroup($app_code, $gr_code, $includeACName=false) {

        $this->log('R3AuthManagerImpExp::exportGroup() called.', AUTH_LOG_DEBUG);

        if (!$this->hasPerm('EXPORT', 'GROUP')) {
            $this->log("R3AuthManagerImpExp::exportGroup(): Permission denied.", AUTH_LOG_INFO);
            throw new EPermissionDenied('Permission denied', 1);
        }

        // Get the group list to export
        if ($gr_code === null) {
            $groups = $this->getGroupsList($app_code, array('fields'=>'app_code, gr_name'));
        } else {
            $groups = $this->getGroupsList($app_code, array('fields'=>'app_code, gr_name', 'where'=>'gr_name=' . $this->db->quote($gr_code)));
        }

        // Ricavo definizione gruppi
        $groupData = array();
        $usedACNames = array();
        foreach($groups as $group) {
            $data = $this->getGroupData($group['app_code'], $group['gr_name'], true);
            foreach($data['perm'] as $perm) {
                $usedACNames[$perm['ac_verb'] . '|' . $perm['ac_name']] = array('ac_verb'=>$perm['ac_verb'], 'ac_name'=>$perm['ac_name']);
            }
            $groupData[] = $data;
        }
        $result = array();
        if ($includeACName) {
            // Ricavo informazione ACNames e le confronto con quelle in uso dai vari gruppi
            $data = array();
            foreach($this->getACNamesList($app_code, array('fields'=>'*')) as $acName) {
                if (array_key_exists($acName['ac_verb'] . '|' . $acName['ac_name'], $usedACNames)) {
                    $data[] = $acName;
                };
            }
            $result['acnames'] = $this->prepareACNameForXML($data);
        }
        $result['groups'] = $this->prepareGroupForXML($groupData);

        $dom = array2xml($result, array('document_element' => 'auth'));
        // echo $dom->saveXML();
        return $dom->saveXML();
    }


    private function parseForGroupNode($acNameNode, $forcedDomain=null, $forcedApplication=null) {

        $tot = 0;
        $skip = 0;
        $app_code = $forcedApplication;
        
        $existentGroupArr = $this->getGroupsList($app_code);
        foreach($acNameNode as $key1=>$val1) {

            if ($key1 == 'applications') {
                foreach($val1 as $key2=>$val2) {
                    foreach($val2 as $key3=>$val3) {
                        if ($key3 == 'code') {
                            if ($forcedApplication === null) {
                                foreach($val3 as $key4=>$val4) {
                                    $app_code = $val4;
                                }
                            }
                        } else if ($key3 == 'parameters') {
                            foreach($val3 as $key4=>$val4) {
                                $data = array('app_code'=>$app_code, 'dn_name'=>null, 'gr_name'=>null, 'gr_descr'=>null, 'perm'=>array());
                                foreach($val4 as $key5=>$val5) {
                                    if ($key5 == 'name') {
                                        $data['gr_name'] = $val5[0];
                                    } else if ($key5 == 'description') {
                                        $data['gr_descr'] = $val5[0];
                                    } else if ($key5 == 'domain') {
                                        if ($forcedDomain === null) {
                                            $data['dn_name'] = $val5[0];
                                        } else {
                                            $data['dn_name'] = $forcedDomain;
                                        }
                                    } else if ($key5 == 'permissions') {
                                        foreach($val5 as $key6=>$val6) {
                                            $perm = array('ac_verb'=>null, 'ac_name'=>null, 'ga_kind'=>'ALLOW');
                                            foreach($val6 as $key7=>$val7) {
                                                if ($key7 == 'verb') {
                                                    $perm['ac_verb'] = $val7[0];
                                                } else if ($key7 == 'name') {
                                                    $perm['ac_name'] = $val7[0];
                                                } else if ($key7 == 'kind') {
                                                    $perm['ga_kind'] = $val7[0];
                                                }
                                            }
                                            $data['perm'][] = $perm;
                                        }
                                    }
                                }
                                foreach ($existentGroupArr as $groups){
                                     $existentGroups[] = $groups['gr_name'];
                                }
                                if(!in_array($data['gr_name'], $existentGroups)){
                                    try {
                                        $this->addGroup($data['app_code'],
                                                $data['gr_name'],
                                                $data['dn_name'],
                                                $data['gr_descr'],
                                                $data['perm']);
                                        $tot++;
                                    } catch (EInputError $e) {
                                        // Ignore Key error
                                        $skip++;
                                        if ($e->getCode() != PK_ERROR)
                                            throw $e;
                                    }
                                } else {
                                    try {
                                        $this->modGroup($data['app_code'],
                                                $data['gr_name'],
                                                $data['app_code'],
                                                $data['gr_name'],
                                                $data['dn_name'],
                                                $data['gr_descr'],
                                                $data['perm']);
                                        $tot++;
                                    } catch (EInputError $e) {
                                        // Ignore Key error
                                        $skip++;
                                        if ($e->getCode() != PK_ERROR)
                                            throw $e;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return array('tot'=>$tot, 'skip'=>$skip);
    }

    private function parseForACNameNode($acNameNode, $forcedApplication=null) {

        $tot = 0;
        $skip = 0;
        $app_code = $forcedApplication;
        foreach($acNameNode as $key1=>$val1) {
            if ($key1 == 'applications') {
                foreach($val1 as $key2=>$val2) {
                    foreach($val2 as $key3=>$val3) {
                        if ($key3 == 'code') {
                            if ($forcedApplication === null) {
                                foreach($val3 as $key4=>$val4) {
                                    $app_code = $val4;
                                }
                            }
                        } else if ($key3 == 'parameters') {
                            foreach($val3 as $key4=>$val4) {
                                $data = array('app_code'=>$app_code, 'ac_verb'=>null, 'ac_name'=>null,
                                        'ac_descr'=>null, 'ac_order'=>0, 'ac_active'=>true, 'ac_type'=>'S');
                                foreach($val4 as $key5=>$val5) {
                                    if ($key5 == 'verb') {
                                        $data['ac_verb'] = $val5[0];
                                    } else if ($key5 == 'name') {
                                        $data['ac_name'] = $val5[0];
                                    } else if ($key5 == 'type') {
                                        $data['ac_type'] = $val5[0];
                                    } else if ($key5 == 'active') {
                                        $data['ac_active'] = $val5[0] == 'T';
                                    } else if ($key5 == 'order') {
                                        $data['ac_order'] = $val5[0];
                                    } else if ($key5 == 'description') {
                                        $data['ac_descr'] = $val5[0];
                                    }
                                }
                                try {
                                    $this->addACName($data['app_code'],
                                            $data['ac_verb'],
                                            $data['ac_name'],
                                            $data['ac_descr'],
                                            $data['ac_order'],
                                            $data['ac_active'],
                                            $data['ac_type']);
                                    $tot++;
                                } catch (EInputError $e) {
                                    // Ignore Key error
                                    $skip++;
                                    if ($e->getCode() != PK_ERROR)
                                        throw $e;
                                }
                            }
                        }
                    }
                }
            }
        }
        return array('tot'=>$tot, 'skip'=>$skip);
    }

    private function parseForConfigNode($acNameNode, $forcedDomain=null, $forcedApplication=null) {

        $tot = 0;
        $skip = 0;
        $defaults = array();

        foreach($acNameNode as $key1=>$val1) {
            if ($key1 == 'sections') {
                foreach($val1 as $key2=>$val2) {
                    $defaultMode = null;
                    $defaultDomain = null;
                    $defaultApplication = null;
                    $defaultUser = null;
                    $defaultSection = null;
                    foreach($val2 as $key3=>$val3) {
                        if ($key3 == 'mode') {
                            $defaultMode = $val3[0];
                        } else if ($key3 == 'domain') {
                            if ($val3[0] == '' || $forcedDomain === null) {
                                $defaultDomain = $val3[0];
                            } else {
                                $defaultDomain = $forcedDomain;
                            }
                        } else if ($key3 == 'application') {
                            if ($val3[0] == '' || $forcedApplication === null) {
                                $defaultApplication = $val3[0];
                            } else {
                                $defaultApplication = $forcedApplication;
                            }
                        } else if ($key3 == 'user') {
                            $defaultUser = $val3[0];
                        } else if ($key3 == 'section') {
                            $defaultSection = $val3[0];
                        } else if ($key3 == 'parameters') {
                            foreach($val3 as $key4=>$val4) {
                                $this->dbini = new R3DBIni($this->db, $this->options['options'], $defaultDomain, $defaultApplication, $defaultUser);
                                $this->dbini->setShowPrivate(true);
                                $org_data = $this->dbini->getAllAttributes($defaultSection);
                                $data = array('param'=>null, 'value'=>null, 'type'=>null, 'type-ext'=>null,
                                        'private'=>'F', 'order'=>0, 'descr'=>null);
                                foreach($val4 as $key5=>$val5) {
                                    if ($key5 == 'parameter') {
                                        $data['param'] = $val5[0];
                                    } else if ($key5 == 'value') {
                                        $data['value'] = $val5[0];
                                    } else if ($key5 == 'type') {
                                        $data['type'] = $val5[0];
                                    } else if ($key5 == 'type-extended') {
                                        $data['type-ext'] = $val5[0];
                                    } else if ($key5 == 'description') {
                                        $data['descr'] = $val5[0];
                                    } else if ($key5 == 'private') {
                                        $data['private'] = $val5[0];
                                    } else if ($key5 == 'order') {
                                        $data['order'] = $val5[0];
                                    }
                                }
                                if ($defaultMode == 'SET' || !isset($org_data[$defaultSection][$data['param']])) {
                                    if ($data['type'] == 'ARRAY') {
                                        $data['value'] = $data['value'];
                                        @eval('$my_array = array(' . $data['value'] . ');');
                                        if (!isset($my_array)) {
                                            throw new Exception('Invalid value');
                                        }
                                        $data['value'] = serialize($my_array);
                                    }
                                    $tot++;
                                    $this->dbini->setAttribute($defaultDomain, $defaultApplication, $defaultUser,
                                            $defaultSection, $data['param'], $data['value'],
                                            $data['type'], $data['type-ext'],
                                            $data['private'], $data['order'], $data['descr']);
                                } else {
                                    $skip++;
                                }
                            }
                        }
                    }
                }
            }
        }
        return array('tot'=>$tot, 'skip'=>$skip);
    }


    public function import($dn_name, $app_code, $data) {

        $totals = array('configs'=>array('tot'=>0, 'skip'=>0),
                'acnames'=>array('tot'=>0, 'skip'=>0),
                'groups'=>array('tot'=>0, 'skip'=>0));
        $data = xmltext2array($data, array());
        if (!array_key_exists('auth', $data)) {
            throw new Exception('Invalid xml: root node must be "auth"');
        }
        foreach($data['auth'] as $key=>$val) {
            if ($key == 'acnames') {
                if (!$this->hasPerm('IMPORT', 'ACNAME')) {
                    $this->log("R3AuthManagerImpExp::import(): Permission denied for ACName.", AUTH_LOG_INFO);
                    throw new EPermissionDenied('Permission denied', 1);
                }
                foreach($val as $acNameNode) {
                    $tot = $this->parseForACNameNode($acNameNode, $app_code);
                    $totals['acnames']['tot'] += $tot['tot'];
                    $totals['acnames']['skip'] += $tot['skip'];
                }
            } else if ($key == 'groups') {
                if (!$this->hasPerm('IMPORT', 'GROUP')) {
                    $this->log("R3AuthManagerImpExp::import(): Permission denied for group.", AUTH_LOG_INFO);
                    throw new EPermissionDenied('Permission denied', 1);
                }
                foreach($val as $acNameNode) {
                    $tot = $this->parseForGroupNode($acNameNode, $dn_name, $app_code);
                    $totals['groups']['tot'] += $tot['tot'];
                    $totals['groups']['skip'] += $tot['skip'];
                }
            } else if ($key == 'configuration') {
                if (!$this->hasPerm('IMPORT', 'CONFIG')) {
                    $this->log("R3AuthManagerImpExp::import(): Permission denied for configuration.", AUTH_LOG_INFO);
                    throw new EPermissionDenied('Permission denied', 1);
                }
                foreach($val as $acNameNode) {
                    $tot = $this->parseForConfigNode($acNameNode, $dn_name, $app_code);
                    $totals['configs']['tot'] += $tot['tot'];
                    $totals['configs']['skip'] += $tot['skip'];
                }
            }

        }
        return $totals;
    }

}
