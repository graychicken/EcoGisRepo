<?php

class eco_consumption_tree extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->act = initVar('act', 'show');
        $this->tab_mode = initVar('tab_mode');
        $this->meter_last_id = initVar('meter_last_id');
        $this->device_last_id = initVar('device_last_id');
        $this->consumption_last_id = initVar('consumption_last_id');
        $this->bu_id = PageVar('bu_id');
        $this->parent_act = PageVar('parent_act');
        $this->kind = strtoupper(PageVar('kind'));
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
    }

    public function getPageTitle() {
        
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        $vlu = array();
        $lang = R3Locale::getLanguageID();
        $hasNonStandardFactor = false;

        $conversionFactors = array();
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select("em_id, em_name_{$lang} AS em_name, es_name_{$lang} AS es_name, udm_name_{$lang} AS udm_name,
                    us_name_{$lang} AS us_name, up_name_{$lang} AS up_name, mu_name_{$lang} AS mu_name, em_is_production, is_producer, im_id")
                ->from('energy_meter_data em')
                ->innerJoin('energy_source_udm esu', 'esu.esu_id=em.esu_id')
                ->leftJoin('municipality mu', 'esu.mu_id=mu.mu_id')
                ->where("emo_code='BUILDING' AND et_code=" . $db->quote($this->kind) . " AND em_object_id=" . (int) $this->bu_id)
                ->orderBy('em_serial, em_id');

        // Device prepared statement
        $qDevice = $db->createSelectQuery();
        $qDevice->select("dev_id, dev_serial, dt_name_$lang AS dt_name, dev_install_date, dev_end_date, dev_power, dt_extradata_$lang AS dt_extradata, im_id")
                ->from('device_data')
                ->where('em_id=?')
                ->orderBy('dev_serial, dev_id');
        $stmtDevice = $qDevice->prepare();

        // Consumption prepared statement
        $qConsumption = $db->createSelectQuery();
        $qConsumption->select("esu_id, es_name_$lang AS es_name, udm_name_$lang AS udm_name, esu_kwh_factor, esu_tep_factor, esu_co2_factor,
                               co_id, co_start_date, co_end_date, co_value, co_value_tep, co_value_kwh, co_value_co2, co_bill, co_bill_specific, standard_factor, im_id")
                ->from('consumption_data')
                ->where('em_id=?')
                ->orderBy('co_start_date DESC, co_end_date DESC, co_id DESC');
        //echo $qConsumption;
        $stmtConsumption = $qConsumption->prepare();

        foreach ($db->query($q, PDO::FETCH_ASSOC) as $row) {
            $devices = array();
            $stmtDevice->execute(array($row['em_id']));
            while ($rowDevice = $stmtDevice->fetch(PDO::FETCH_ASSOC)) {
                $rowDevice['dev_power'] = R3NumberFormat($rowDevice['dev_power'], null, true);
                $rowDevice['dev_install_date'] = SQLDateToStr($rowDevice['dev_install_date'], R3Locale::getPhpDateFormat());
                $rowDevice['dev_end_date'] = SQLDateToStr($rowDevice['dev_end_date'], R3Locale::getPhpDateFormat());
                $devices[] = $rowDevice;
            }
            $consumptions = array();
            $stmtConsumption->execute(array($row['em_id']));
            while ($rowConsumption = $stmtConsumption->fetch(PDO::FETCH_ASSOC)) {
                $rowConsumption['co_start_date'] = SQLDateToStr($rowConsumption['co_start_date'], R3Locale::getPhpDateFormat());
                $rowConsumption['co_end_date'] = SQLDateToStr($rowConsumption['co_end_date'], R3Locale::getPhpDateFormat());
                $rowConsumption['co_value'] = R3NumberFormat($rowConsumption['co_value'], null, true);
                $rowConsumption['co_value_tep'] = R3NumberFormat($rowConsumption['co_value_tep'], 2, true);
                $rowConsumption['co_value_kwh'] = R3NumberFormat($rowConsumption['co_value_kwh'], 0, true);
                $rowConsumption['co_value_co2'] = R3NumberFormat($rowConsumption['co_value_co2'], 0, true);
                $rowConsumption['co_bill_specific'] = R3NumberFormat($rowConsumption['co_bill_specific'], 3, true);
                $rowConsumption['co_bill'] = R3NumberFormat($rowConsumption['co_bill'], 2, true);
                $consumptions[] = $rowConsumption;

                $conversionFactors[$rowConsumption['esu_id']] = array('descr' => "{$rowConsumption['es_name']} " . ($row['mu_name'] <> '' ? " - <i>{$row['mu_name']}</i>" : '') . " [{$rowConsumption['udm_name']}]",
                    'kwh' => R3NumberFormat($rowConsumption['esu_kwh_factor'], null, true),
                    'tep' => R3NumberFormat($rowConsumption['esu_tep_factor'], null, true),
                    'co2' => R3NumberFormat($rowConsumption['esu_co2_factor'], null, true),
                    'standard_factor' => $rowConsumption['standard_factor']);
                if (!$rowConsumption['standard_factor']) {
                    $hasNonStandardFactor = true;
                }
            }
            $vlu[] = array('meter' => $row,
                'devices' => $devices,
                'consumptions' => $consumptions);
        }
        return array('data' => $vlu, 'conversion_factor' => $conversionFactors, 'has_non_standard_factor' => $hasNonStandardFactor);
    }

    public function getPageVars() {
        return array('tab_mode' => $this->tab_mode,
            'bu_id' => $this->bu_id,
            'meter_last_id' => $this->meter_last_id,
            'device_last_id' => $this->device_last_id,
            'consumption_last_id' => $this->consumption_last_id,
            'parent_act' => $this->parent_act,
            'kind' => $this->kind,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS($this->baseName . '.js', false); // inline js
    }

    public function getKindText($kind) {
        switch ($kind) {
            case 'HEATING': return _('riscaldamento');
                break;
            case 'ELECTRICITY': return _('elettrico');
                break;
            case 'WATER': return _('acqua');
                break;
        }
        return $kind;
    }

    public function getJSVars() {
        return array('txtShowMeter' => sprintf(_('Visualizza contatore %s'), $this->getKindText($this->kind)),
            'txtAddMeter' => sprintf(_('Aggiungi contatore %s'), $this->getKindText($this->kind)),
            'txtModMeter' => sprintf(_('Modifica contatore %s'), $this->getKindText($this->kind)),
            'txtCantDeleteMeter' => _('Impossibile cancellare il contatore poichè vi sono degli impianti e/o dei consumi adesso legati'),
            'txtShowDevice' => sprintf(_('Visualizza impianto %s'), $this->getKindText($this->kind)),
            'txtAddDevice' => sprintf(_('Aggiungi impianto %s'), $this->getKindText($this->kind)),
            'txtModDevice' => sprintf(_('Modifica impianto %s'), $this->getKindText($this->kind)),
            'txtShowConsumption' => sprintf(_('Visualizza consumo %s'), $this->getKindText($this->kind)),
            'txtAddConsumption' => sprintf(_('Aggiungi consumo %s'), $this->getKindText($this->kind)),
            'txtModConsumption' => sprintf(_('Modifica consumo %s'), $this->getKindText($this->kind)));
    }

    public function getTemplateName() {
        return 'consumption_tree.tpl';
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = 'METER';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        R3Security::checkBuilding($this->bu_id);
    }

}
