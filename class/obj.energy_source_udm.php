<?php

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisESUHelper {

    /**
     * Update the energy source for all the consumption of a municipality
     *
     * @param integer $do_id            domain id
     * @param integer $es_id            energy source id
     * @param integer $udm_id           udm_id
     * @param integer $mu_id            municipality id
     * @param integer $restore          if true reset the energy source to the default value (when delete the custom udm)
     * @return array                    true if change made, else false
     */
    static public function updateEnergySourceUDM($do_id, $es_id, $udm_id, $mu_id, $restore = false) {
        $db = ezcDbInstance::get();
        $do_id = (int) $do_id;
        $mu_id = (int) $mu_id;
        $es_id = (int) $es_id;
        $udm_id = (int) $udm_id;
        $sqlConsumption = "SELECT em_id FROM
                             (SELECT CASE WHEN emo.emo_code = 'BUILDING'::text THEN bu.mu_id
                                          WHEN emo.emo_code::text = 'STREET_LIGHTING'::text THEN st.mu_id
                                          WHEN emo.emo_code::text = 'GLOBAL_ENERGY'::text THEN ge.mu_id
                                     END AS mu_id, es_id, udm_id, em_id
                              FROM energy_meter em
                              INNER JOIN energy_meter_object emo ON em.emo_id=emo.emo_id
                              INNER JOIN energy_source_udm esu ON em.esu_id=esu.esu_id AND esu_is_private IS FALSE
                              LEFT JOIN building bu ON em.em_object_id=bu.bu_id AND emo_code='BUILDING'
                              LEFT JOIN street_lighting sl ON em.em_object_id=sl.sl_id AND emo_code='STREET_LIGHTING'
                              LEFT JOIN common.street st ON sl.st_id=st.st_id
                              LEFT JOIN global_data gd ON em.em_object_id=gd.gd_id AND emo_code='GLOBAL_ENERGY'
                              LEFT JOIN ecogis.global_subcategory gs ON gd.gs_id = gs.gs_id
                              LEFT JOIN ecogis.global_entry ge ON ge.ge_id = gs.ge_id
                           ) AS foo
                           WHERE mu_id={$mu_id} and es_id={$es_id} and udm_id={$udm_id}
                           GROUP BY em_id";
        $sql = "SELECT esu_id FROM energy_source_udm WHERE es_id={$es_id} AND udm_id={$udm_id} AND do_id={$do_id} AND mu_id IS NULL AND esu_is_private IS FALSE";
        $esu_id_base = $db->query($sql)->fetchColumn();

        $sql = "SELECT esu_id FROM energy_source_udm WHERE es_id={$es_id} AND udm_id={$udm_id} AND do_id={$do_id} AND mu_id={$mu_id} AND esu_is_private IS FALSE";
        $esu_id_municipality = $db->query($sql)->fetchColumn();
        if ($restore) {
            if ($esu_id_municipality > '') {
                // Update energy meter
                $sql = "UPDATE energy_meter SET esu_id=$esu_id_base WHERE em_id IN ($sqlConsumption)";
                $db->query($sql);
                // Update work
                $sql = "UPDATE work SET esu_id_primary={$esu_id_base} WHERE esu_id_primary={$esu_id_municipality}";
                $db->query($sql);
                $sql = "UPDATE work SET esu_id_electricity={$esu_id_base} WHERE esu_id_electricity={$esu_id_municipality}";
                $db->query($sql);
                // Update energy action_catalog
                $sql = "UPDATE action_catalog_energy SET esu_id={$esu_id_base} WHERE esu_id={$esu_id_municipality}";
                $db->query($sql);
                $sql = "UPDATE action_catalog SET esu_id_production={$esu_id_base} WHERE esu_id_production={$esu_id_municipality}";
                $db->query($sql);
                return true;
            }
        } else {
            if ($esu_id_municipality > '') {
                // Update energy meter
                $sql = "UPDATE energy_meter SET esu_id=$esu_id_municipality WHERE em_id IN ($sqlConsumption)";
                $db->query($sql);
                // Update work
                $sql = "UPDATE work SET esu_id_primary={$esu_id_municipality} WHERE esu_id_primary={$esu_id_base}";
                $db->query($sql);
                $sql = "UPDATE work SET esu_id_electricity={$esu_id_municipality} WHERE esu_id_electricity={$esu_id_base}";
                $db->query($sql);
                // Update energy action_catalog
                $sql = "UPDATE action_catalog_energy SET esu_id={$esu_id_municipality} WHERE esu_id={$esu_id_base}";
                $db->query($sql);
                $sql = "UPDATE action_catalog SET esu_id_production={$esu_id_municipality} WHERE esu_id_production={$esu_id_base}";
                $db->query($sql);
                return true;
            }
        }
        return false;
    }

    /**
     * Return the global energy Source associated with the base source
     *
     * @param integer $do_id            domain id
     * @param integer $es_id            energy source id
     * @param integer $udm_id           udm_id
     * @return intger                   ges_id
     */
    static public function getGlobalEnergySource($do_id, $es_id, $udm_id) {
        $db = ezcDbInstance::get();
        $do_id = (int) $do_id;
        $es_id = (int) $es_id;
        $udm_id = (int) $udm_id;

        $sql = "SELECT ges_id FROM energy_source_udm WHERE es_id={$es_id} AND udm_id={$udm_id} AND do_id={$do_id} AND mu_id IS NULL";
        return $db->query($sql)->fetchColumn();
    }

}

class eco_energy_source_udm extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'energy_source_udm';

    /**
     * building fields definition
     */

    /**
     * ecogis.energy_source_udm fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'esu_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'do_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'auth.domains')),
            array('name' => 'mu_id', 'type' => 'lookup', 'lookup' => array('table' => 'municipality'), 'label' => _('Comune')),
            array('name' => 'es_id', 'type' => 'lookup', 'required' => true, 'label' => _('Tipo alimentazione'), 'lookup' => array('table' => 'energy_source')),
            array('name' => 'udm_id', 'type' => 'lookup', 'required' => true, 'label' => _('Unità di misura'), 'lookup' => array('table' => 'udm')),
            array('name' => 'esu_tep_factor', 'type' => 'float', 'label' => _('Fattore conversione TEP')),
            array('name' => 'esu_kwh_factor', 'type' => 'float', 'required' => true, 'label' => _('Fattore conversione kWh')),
            array('name' => 'esu_co2_factor', 'type' => 'float', 'required' => true, 'label' => _('Fattore conversione CO2 [kg]')),
            array('name' => 'esu_energy_price', 'type' => 'float', 'label' => _('Prezzo energia')),
            array('name' => 'esu_energy_min_price', 'type' => 'float'),
            array('name' => 'esu_energy_max_price', 'type' => 'float'),
            array('name' => 'esu_is_private', 'type' => 'boolean', 'label' => _('Uso interno'), 'default' => 'false'),
            array('name' => 'esu_is_consumption', 'type' => 'boolean', 'label' => _('Fonte a consumo'), 'default' => 'false'),
            array('name' => 'esu_is_production', 'type' => 'boolean', 'label' => _('Fonte a produzione'), 'default' => 'false'),
            array('name' => 'ges_id', 'type' => 'lookup', 'label' => _('Alimentazione inventario'), 'lookup' => array('table' => 'global_energy_source')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];  // PageVar('do_id',          $_SESSION['do_id'],    $init | $reset, false, $this->baseName);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName);
        $this->es_id = PageVar('es_id', null, $init | $reset, false, $this->baseName);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('fetchUDM');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('askDelESU');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo fattore di conversione');
            case 'mod': return _('Modifica fattore di conversione');
            case 'show': return _('Visualizza fattore di conversione');
            case 'list': return _('Elenco fattori di conversione');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        if (count($filters['mu_values']) == 1) {
            $mu_id = key($filters['mu_values']);
        } else {
            $mu_id = null;
        }
        $filters['es_values'] = R3Opt::getOptList('energy_source_udm_data', 'es_id', 'es_name_' . R3Locale::getLanguageID(), array('constraints' => "do_id={$this->do_id}")); // AND esu_is_private IS FALSE"));
        $filters['do_id'] = $this->do_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['es_id'] = $this->es_id;
        return $filters;
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery();
        $where = array();
        $where[] = $q->expr->eq('do_id', $this->do_id);
        $where[] = 'esu_is_private IS FALSE';

        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS') && $this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->auth->getParam('mu_id')));
        }
        if ($this->mu_id <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->mu_id));
        }
        if ($this->mu_name <> '') {
            $where[] = "mu_name_{$lang} ILIKE " . $db->quote("%{$this->mu_name}%");
        }
        if ($this->es_id <> '') {
            $where[] = $q->expr->eq('es_id', $db->quote((int) $this->es_id));
        }
        $q->select("esu_id, do_id, et_code, et_name_{$lang} AS et_name, mu_id, mu_name_{$lang} AS mu_name, es_id, es_name_{$lang} AS es_name, udm_id, udm_name_{$lang} AS udm_name, " .
                        "esu_tep_factor, esu_co2_factor, esu_energy_price, esu_kwh_factor, mu_id, " .
                        "esu_is_private, CASE WHEN esu_is_consumption IS TRUE THEN 'X' ELSE '' END AS esu_is_consumption, " .
                        "CASE WHEN esu_is_production IS TRUE THEN 'X' ELSE '' END AS esu_is_production, ges_id, ges_full_name_$lang AS ges_full_name")
                ->from('energy_source_udm_data');
        if (count($where) > 0) {
            $q->where($where);
        }
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        if ($this->auth->getParam('mu_id') == '' && count(R3EcoGisHelper::getMunicipalityList($this->do_id)) > 1) {
            if ($this->mu_id == '') {
                $this->simpleTable->addSimpleField(_('Comune'), 'mu_name', 'text', null, array('sortable' => true, 'order_fields' => 'mu_name, es_name, udm_name'));
            }
        }
        $this->simpleTable->addSimpleField(_('Tipo alimentazione'), 'es_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Unità di misura'), 'udm_name', 'text', 60, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Fattore conversione TEP'), 'esu_tep_factor', 'float', 120, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Fattore conversione CO2 [kg]'), 'esu_co2_factor', 'float', 120, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Fattore conversione kWh'), 'esu_kwh_factor', 'float', 120, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Fonte a consumo'), 'esu_is_consumption', 'text', 70, array('sortable' => true, 'align' => 'center'));
        $this->simpleTable->addSimpleField(_('Fonte a produzione'), 'esu_is_production', 'text', 70, array('sortable' => true, 'align' => 'center'));
        $this->simpleTable->addSimpleField(_('Alimentazione inventario'), 'ges_full_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'energy_source_list_table');
        //if (get_class($this->simpleTable) == 'simpleGrid')
        //    $this->simpleTable->setOptions(array('height'=>-5));
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['esu_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        if ($row['mu_id'] == '') {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelESU('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        } else {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelMunicipalityESU('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($this->act <> 'add') {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('energy_source_udm_data')
                    ->where("esu_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['ges_full_name'] = $vlu["ges_full_name_{$lang}"];
            $vlu['gc_full_name'] = $vlu["gc_full_name_{$lang}"];
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('energy_source_udm', $vlu['esu_id']));
        } else {
            $vlu = array(); 
            $vlu['es_id'] = null;
        }
        $this->data = $vlu;
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $lkp = array();
        if ($this->auth->getParam('mu_id') == '') {
            $lkp['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id);
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod') {
            $mu_id = $this->data['mu_id'];
        }
        $lkp['es_values'] = R3Opt::getOptList('energy_source_udm_data', 'es_id', 'es_name_' . R3Locale::getLanguageID(), array('constraints' => "do_id={$this->do_id} AND mu_id IS NULL")); // AND esu_is_private IS FALSE"));
        if ($this->data['es_id'] != '') {
            $sqlUDM = "SELECT udm_id, udm_name_{$lang} AS udm_name
                       FROM energy_source_udm_data
                       WHERE do_id IS NULL AND es_id={$this->data['es_id']}
                       GROUP BY udm_id, udm_name_{$lang}
                       ORDER BY udm_name, udm_id";
            $lkp['udm_values'] = $db->query($sqlUDM)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
        }

        return $lkp;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtChangeMade' => _('Salvataggio avvenuto con successo.\nI dati di consumo precedentemente inseriti sono stati convertiti con i nuovi fattori di conversione'),
        );
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();

        $request['esu_id'] = (int) $request['id'];
        $request['do_id'] = $this->do_id;

        $this->setMunicipalityForUser($request);

        if ($this->act == 'add' && $request['mu_id'] == '') {
            $errors['mu_id'] = array('CUSTOM_ERROR' => _("Indicare il comune su cui attivare questo fattore di conversione"));
        }
        if ($this->act <> 'del') {
            $request['es_id'] = (int) $request['es_id'];
            $request['udm_id'] = (int) $request['udm_id'];
            $request['mu_id'] = (int) $request['mu_id'];
            $errors = $this->checkFormData($request, $errors);
            $sql = "SELECT COUNT(*) FROM energy_source_udm WHERE es_id={$request['es_id']} AND udm_id={$request['udm_id']} AND do_id={$request['do_id']} AND mu_id={$request['mu_id']}";
            if ($this->act == 'mod') {
                $sql .= " AND esu_id<>" . $request['esu_id'];
            }
            if ($db->query($sql)->fetchColumn() > 0) {
                $errors['es_id'] = array('CUSTOM_ERROR' => _("L'alimentazione e l'unità di misura immesse esistono già"));
            }
            $request['mu_id'] = $mu_id = (int) $request['mu_id'] > 0 ? (int) $request['mu_id'] : null;
            if ($mu_id == 0) {
                $request['mu_id'] = $mu_id = null;
            }
        } else {
            // Ricavo valori per spostamento fattori di conversione
            $data = $db->query("SELECT es_id, udm_id, mu_id FROM energy_source_udm WHERE esu_id={$request['esu_id']}")->fetch(PDO::FETCH_ASSOC);
            $request['es_id'] = $data['es_id'];
            $request['udm_id'] = $data['udm_id'];
            $mu_id = $data['mu_id'];
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db->beginTransaction();

            $request['ges_id'] = R3EcoGisESUHelper::getGlobalEnergySource($this->do_id, $request['es_id'], $request['udm_id']);

            $changed = 0;
            if ($mu_id > 0 && $this->act == 'del') {
                // Converte la fonte del comune in fonte generica (del dominio)
                $changed = R3EcoGisESUHelper::updateEnergySourceUDM($this->do_id, $request['es_id'], $request['udm_id'], $mu_id, true);
            }

            $id = $this->applyData($request);
            if ($mu_id > 0 && $this->act == 'add') {
                // Converte la vecchia fonte con la nuova per tutto il comune
                $changed = R3EcoGisESUHelper::updateEnergySourceUDM($this->do_id, $request['es_id'], $request['udm_id'], $mu_id);
            }
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataEnergySourceUDMDone($id, " . ($changed ? 1 : 0) . ")");
        }
    }

    /**
     * Return the help data (ajax)
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function getHelp($request) {
        require_once R3_LIB_DIR . 'eco_help.php';
        $body = R3Help::getHelpPartFromSection($request['section'], $request['id'], R3Locale::getLanguageCode());
        return array('data' => $body !== null ? $body : '');
    }

    /**
     * Ritorna l'elenco delle unità di misura (prese dal template energy_dource_udm con do_id=null), fattore conversione elettrico e fonte PAES
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetchUDM($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $es_id = (int) $request['es_id'];
        $row = $db->query("SELECT COALESCE(esu_kwh_factor::TEXT, '') AS esu_kwh_factor, COALESCE(esu_co2_factor::TEXT, '') AS esu_co2_factor,
                           COALESCE(esu_tep_factor::TEXT, '') AS esu_tep_factor, esu_is_consumption, esu_is_production, 
                           COALESCE(ges_name_{$lang}, '') AS ges_name 
						   FROM energy_source_udm_data
                           WHERE do_id={$this->do_id} AND 
						         es_id={$es_id} AND 
								 mu_id IS NULL AND 
								 esu_is_private IS FALSE
						   ORDER BY udm_order
						   LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $data = array();
        $sqlUDM = "SELECT udm_id, udm_name_{$lang} AS udm_name
                   FROM energy_source_udm_data
                   WHERE do_id IS NULL AND es_id={$es_id}
                   GROUP BY udm_id, udm_name_{$lang}
                   ORDER BY udm_name, udm_id";
        $data['udm_id'] = array('options' => $db->query($sqlUDM)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE));
        $data['udm_id_selected'] = key($data['udm_id']['options']);
        $data['esu_kwh_factor'] = $row['esu_kwh_factor'];
        $data['esu_co2_factor'] = $row['esu_co2_factor'];
        $data['esu_tep_factor'] = $row['esu_tep_factor'];
        $data['esu_is_consumption'] = $row['esu_is_consumption'];
        $data['esu_is_production'] = $row['esu_is_production'];
        $data['ges_name'] = $row['ges_name'];

        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $data);
    }

    public function askDelESU($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $data = $db->query("SELECT es_name_$lang AS es_name, udm_name_$lang AS udm_name, mu_id FROM energy_source_udm_data WHERE esu_id={$id}")->fetch(PDO::FETCH_ASSOC);
        if ($request['type'] == 'MUNICIPALITY') {
            $mu_name = R3EcoGisHelper::getMunicipalityName($data['mu_id']);
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare il fattore di conversione \"%s (%s)\" e ripristinare il valore standard per i consumi già inseriti nel comune di \"%s\"?"), $data['es_name'], $data['udm_name'], $mu_name));
        } else {
            if ($this->tryDeleteData($id)) {
                return array('status' => R3_AJAX_NO_ERROR,
                    'confirm' => sprintf(_("Sei sicuro di voler cancellare il fattore di conversione \"%s (%s)\"?"), $data['es_name'], $data['udm_name']));
            } else {
                return array('status' => R3_AJAX_NO_ERROR,
                    'alert' => _('Impossibile cancellare questo fattore di conversione poichè vi sono dei dati ad esso legati'));
            }
        }
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
