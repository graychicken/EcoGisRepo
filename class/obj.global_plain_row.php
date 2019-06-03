<?php

class R3EcoGisGlobalPlainHelper {

    /**
     * Restituisce l'elenco delle categorie PAES principali
     */
    public function getCategoriesListByParentId($do_id, $gc_parent_id = null, array $opt = array()) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $defaultOptions = array('allow_empty' => false,
            'empty_text' => _('-- Selezionare --'));
        $opt = array_merge($defaultOptions, $opt);
        $gc_parent_id = (int) $gc_parent_id;
        $moreWhere = $gc_parent_id == '' ? 'gc_parent_id IS NULL' : 'gc_parent_id=' . (int) $gc_parent_id;

        $sql = "SELECT gc_id, gc_name_$lang AS gc_name, CASE gc_has_extradata WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS gc_has_extradata
                FROM global_category
                WHERE (do_id IS NULL OR do_id={$do_id}) AND gc_visible IS TRUE AND gc_global_plain IS TRUE AND {$moreWhere}
                ORDER BY gc_order, gc_name";
        $result = array();
        if ($opt['allow_empty']) {
            $result[''] = array('name' => $opt['empty_text'], 'has_extradata' => 'F');
        }
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['gc_id']] = array('name' => $row['gc_name'], 'has_extradata' => $row['gc_has_extradata']);
        }
        return $result;
    }

    /**
     * Restituisce l'elenco delle fonti per l'editing
     */
    public function getCategoriesList($do_id, $gc_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $gc_id = (int) $gc_id;
        $sql = "SELECT gc_id, gc_name_$lang AS gc_name, CASE gc_has_extradata WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS gc_has_extradata
                FROM global_category 
                WHERE (do_id IS NULL OR do_id={$do_id}) AND gc_parent_id=(SELECT gc_parent_id FROM global_category WHERE gc_id={$gc_id})
                ORDER BY gc_order, gc_name";
        $result = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['gc_id']] = array('gc_name' => $row['gc_name'], 'gc_has_extradata' => $row['gc_has_extradata']);
        }
        return $result;
    }

    /**
     * Restituisce l'elenco delle azioni possibili per una determinata categoria
     */
    public function getPlainActionList($do_id, $gc_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $opt = array_merge(array('allow_empty' => false,
            'empty_text' => _('-- Selezionare --')), $opt);

        $gc_id = (int) $gc_id;
        $sql = "SELECT gpa.gpa_id,
                CASE WHEN gpa_extradata_1 IS NULL THEN gpa_name_{$lang}
                     ELSE gpa_name_{$lang} || COALESCE(' - ' || gpa_extradata_1, '') END AS gpa_name,
                CASE gpa_has_extradata WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS gpa_has_extradata
                FROM global_plain_action gpa
                INNER JOIN global_plain_action_category gpac on gpa.gpa_id=gpac.gpa_id
                WHERE (do_id IS NULL OR do_id={$do_id}) AND gc_id={$gc_id}
                ORDER BY gpa_order, gpa_name";
        $result = array();
        if ($opt['allow_empty']) {
            $result[''] = array('name' => $opt['empty_text'], 'has_extradata' => 'F');
        }
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['gpa_id']] = array('name' => $row['gpa_name'], 'has_extradata' => $row['gpa_has_extradata']);
        }
        return $result;
    }

    public static function getGaugeData($gpr_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $gpr_id = (int) $gpr_id;

        $sql = "SELECT gpr_gauge_type FROM ecogis.global_plain_row WHERE gpr_id={$gpr_id}";
        $gaugeType = $db->query($sql)->FetchColumn();
        switch ($gaugeType) {
            case 'P': // Percentuale
                $sql = "SELECT gpm_id, gpm_date, gpm_value_1, gpm_value_2
                        FROM ecogis.global_plain_monitor gpm
                        WHERE gpr_id={$gpr_id}
                        ORDER BY gpm_date";
                $result = array();
                $result['P']['header'] = array('title' => _('% Completamento azione'), 'unit' => '%', 'unit_value' => 1);
                foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                    $result['P']['data'][$row['gpm_id']] = array(
                        'gpg_id' => null,
                        'date' => $row['gpm_date'],
                        'date_fmt' => SQLDateToStr($row['gpm_date'], 'd/m/Y'),
                        'value_1' => $row['gpm_value_1'],
                        'value_1_fmt' => R3NumberFormat($row['gpm_value_1'], 2),
                        'value_2' => $row['gpm_value_2'],
                        'value_2_fmt' => R3NumberFormat($row['gpm_value_2'], 2),
                        'tot_value' => $row['gpm_value_1'] * 1,
                        'tot_value_fmt' => R3NumberFormat($row['gpm_value_1'] * 1, 2));
                }

                break;
            case 'G': // Indicatore
                $sql = "SELECT gpg.gpg_id, gpg_name_{$lang} AS gpg_name, gpgu_1.gpgu_name_{$lang} AS gpgu_name_1, 
                               gpgu_2.gpgu_name_{$lang} AS gpgu_name_2, gpg_value_1, gpg_value_2, gpg_value_3, gpm_id, gpm_date, 
                               gpm_value_1, gpm_value_2
                        FROM ecogis.global_plain_gauge gpg
                        LEFT JOIN ecogis.global_plain_gauge_udm gpgu_1 ON gpg.gpgu_id_1=gpgu_1.gpgu_id
                        LEFT JOIN ecogis.global_plain_gauge_udm gpgu_2 ON gpg.gpgu_id_2=gpgu_2.gpgu_id
                        LEFT JOIN ecogis.global_plain_monitor gpm ON gpg.gpg_id=gpm.gpg_id
                        WHERE gpg.gpr_id={$gpr_id}
                        ORDER BY gpg_name, gpm_date";
                $sql = "SELECT gpg_id, gpg_name_{$lang} AS gpg_name, gpgu_name_1_{$lang} AS gpgu_name_1, 
                               gpgu_name_2_{$lang} AS gpgu_name_2, gpg_value_1, gpg_value_2, gpg_value_3, gpm_id, gpm_date, 
                               gpm_value_1, gpm_value_2
                        FROM ecogis.global_plain_gauge_full_data
                        WHERE gpr_id={$gpr_id}
                        ORDER BY gpg_name, gpm_date";
                $result = array();
                foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                    $result[$row['gpg_id']]['header'] = array(
                        'title' => $row['gpg_name'],
                        'unit_1' => $row['gpgu_name_1'],
                        'unit_2' => $row['gpgu_name_2'],
                        'value_1' => $row['gpg_value_1'],
                        'value_2' => $row['gpg_value_2'],
                        'value_3' => $row['gpg_value_3']
                    );
                    $energyVariation = self::calculateEnergyVariation($row['gpg_value_1'], $row['gpg_value_2'], $row['gpm_value_2'], $row['gpm_value_1']);
                    $emissionVariation = self::calculateEmissionVariation($energyVariation, $row['gpg_value_3']);
                    $result[$row['gpg_id']]['data'][$row['gpm_id']] = array('gpg_id' => $row['gpg_id'],
                        'date' => $row['gpm_date'],
                        'date_fmt' => SQLDateToStr($row['gpm_date'], 'd/m/Y'),
                        'value_1' => $row['gpm_value_1'],
                        'value_1_fmt' => R3NumberFormat($row['gpm_value_1'], 2),
                        'value_2' => $row['gpm_value_2'],
                        'value_2_fmt' => R3NumberFormat($row['gpm_value_2'], 2),
                        'energy_variation' => $energyVariation,
                        'energy_variation_fmt' => R3NumberFormat($energyVariation, 2),
                        'emission_variation' => $emissionVariation,
                        'emission_variation_fmt' => R3NumberFormat($emissionVariation, 2),
                    );
                }

                break;
            default:
                // No action
                return null;
        }
        return $result;
    }

    static public function getActionStatus($gpr_id) {
        $result = array(
            'energy_tot' => null, // Non distingue tra produzione e riduzione
            'energy_reduction_tot' => null,
            'energy_production_tot' => null,
            'emission_tot' => null,
            'energy_perc' => null, // Non distingue tra produzione e riduzione
            'energy_reduction_perc' => null,
            'energy_production_perc' => null,
            'emission_perc' => null,
        );

        $db = ezcDbInstance::get();
        $gpr_id = (int) $gpr_id;

        $sql = "SELECT gpr_expected_energy_saving, gpr_expected_renewable_energy_production, gpr_expected_co2_reduction, gpr_gauge_type
                FROM ecogis.global_plain_row WHERE gpr_id={$gpr_id}";
        $rowActionData = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        switch ($rowActionData['gpr_gauge_type']) {
            case 'P': // Percentuale
                $sql = "SELECT MAX(gpm_value_1) AS value_1
                        FROM ecogis.global_plain_monitor gpm
                        WHERE gpr_id={$gpr_id} AND gpm_date=(SELECT MAX(gpm_date) FROM ecogis.global_plain_monitor WHERE gpr_id={$gpr_id})";
                $perc = $db->query($sql)->fetchColumn();
                $result['energy_tot'] = ($rowActionData['gpr_expected_energy_saving'] + $rowActionData['gpr_expected_renewable_energy_production']) / 100 * $perc;
                $result['energy_reduction_tot'] = $rowActionData['gpr_expected_energy_saving'] === null ? null : $rowActionData['gpr_expected_energy_saving'] / 100 * $perc;
                $result['energy_production_tot'] = $rowActionData['gpr_expected_renewable_energy_production'] === null ? null : $rowActionData['gpr_expected_renewable_energy_production'] / 100 * $perc;
                $result['emission_tot'] = $rowActionData['gpr_expected_co2_reduction'] === null ? null : $rowActionData['gpr_expected_co2_reduction'] / 100 * $perc;
                $result['energy_perc'] = $result['energy_reduction_perc'] = $result['energy_production_perc'] = $result['emission_perc'] = $perc;
                break;
            case 'G': // Indicatore
                $sql = "SELECT gpg_id, gpg_value_1, gpg_is_production
                        FROM ecogis.global_plain_gauge gpg
                        WHERE gpr_id={$gpr_id}";
                $hasReduction = false;
                $hasProduction = false;
                $totEnergy = null;
                foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                    $sql = "SELECT *
                            FROM ecogis.global_plain_gauge_full_data gpm
                            WHERE gpr_id={$gpr_id} AND gpg_id={$row['gpg_id']}
                            ORDER BY gpm_id";
                    foreach ($db->query($sql, PDO::FETCH_ASSOC) as $energyRow) {
                        if ($row['gpg_is_production']) {
                            $hasProduction = true;
                            $result['energy_production_tot'] += $energyRow['gpg_energy_variation'];
                        } else {
                            $hasReduction = true;
                            $result['energy_reduction_tot'] += $energyRow['gpg_energy_variation'];
                        }
                        $totEnergy += $energyRow['gpg_energy_variation'];
                        $result['emission_tot'] += $energyRow['gpg_emission_variation'];
                    }
                }

                $result['energy_reduction_perc'] = ($totEnergy === null || $rowActionData['gpr_expected_energy_saving'] == 0) ? null : $totEnergy / $rowActionData['gpr_expected_energy_saving'] * 100;
                $result['energy_production_perc'] = ($totEnergy === null || $rowActionData['gpr_expected_renewable_energy_production'] == 0) ? null : $totEnergy / $rowActionData['gpr_expected_renewable_energy_production'] * 100;
                $result['emission_perc'] = ($result['emission_tot'] === null || $rowActionData['gpr_expected_co2_reduction'] == 0) ? null : $result['emission_tot'] / $rowActionData['gpr_expected_co2_reduction'] * 100;
                if ($hasReduction && $hasProduction) {
                    $result['energy_tot'] = ($result['energy_production_tot'] + $result['energy_reduction_tot']) / 2;
                    $result['energy_perc'] = ($result['energy_reduction_perc'] + $result['energy_production_perc']) / 2;
                } else if ($hasReduction) {
                    $result['energy_tot'] = $result['energy_reduction_tot'];
                    $result['energy_perc'] = $result['energy_reduction_perc'];
                } else if ($hasProduction) {
                    $result['energy_tot'] = $result['energy_production_tot'];
                    $result['energy_perc'] = $result['energy_production_perc'];
                }
                break;
            default:
                // No action
                return $result;
        }
        return $result;
    }

    /**
     * Calcola la variazione energetica [MWh/anno]
     */
    static public function calculateEnergyVariation($gpg_value_1, $gpg_value_2, $efficiency, $quantity) {
        return $gpg_value_1 * ($gpg_value_2 - $efficiency) * $quantity;
    }

    /**
     * Restituisce la variazione delle emissioni [tCO2/anno]
     */
    static public function calculateEmissionVariation($energyVariation, $gpg_value_3) {
        return $energyVariation * $gpg_value_3;
    }

}

class eco_global_plain_row extends \R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_plain_row';

    /**
     * ecogis.global_plain_row fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gpr_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'gp_id', 'type' => 'lookup', 'required' => true, 'label' => _('Piano d\'azione'), 'lookup' => array('table' => 'global_plain')),
            array('name' => 'gc_id', 'type' => 'lookup', 'required' => true, 'label' => _('Settore'), 'lookup' => array('table' => 'global_category')),
            array('name' => 'gc_extradata_1', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gc_extradata_2', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gpa_id', 'type' => 'lookup', 'required' => true, 'label' => _('Azione/misura principale'), 'lookup' => array('table' => 'global_plain_action')),
            array('name' => 'gpa_extradata_1', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gpa_extradata_2', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gpr_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'gpr_descr_2', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'gpr_responsible_department_1', 'type' => 'text', 'label' => _('Servizio, persona o società responsabile (in caso di coinvolgimento di terzi)')),
            array('name' => 'gpr_responsible_department_2', 'type' => 'text', 'label' => _('Servizio, persona o società responsabile (in caso di coinvolgimento di terzi)')),
            array('name' => 'gpr_start_date', 'type' => 'date', 'label' => _('Attuazione [data di inizio e fine]')),
            array('name' => 'gpr_end_date', 'type' => 'date', 'label' => _('Attuazione [data di inizio e fine]')),
            array('name' => 'gpr_estimated_cost', 'type' => 'float', 'label' => _('Costi stimati per azione/misura')),
            array('name' => 'gpr_expected_energy_saving', 'type' => 'float', 'label' => _('Risparmio energetico previsto per misura [MWh/a]')),
            array('name' => 'gpr_expected_renewable_energy_production', 'type' => 'float', 'label' => _('Produzione di energia rinnovabile prevista per misu [MWh/a]')),
            array('name' => 'gpr_expected_co2_reduction', 'type' => 'float', 'label' => _('Riduzione di CO<sub>2</sub> prevista per misura [t/a]')),
            array('name' => 'gpr_gauge_type', 'type' => 'text', 'label' => _('Nome')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list';  // if true store the filter variables

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->parent_act = initVar('parent_act');
        $this->tab_mode = initVar('tab_mode');

        $this->kind = initVar('kind');
        $this->gp_id = (int) initVar('gp_id');
        $this->gc_id = (int) initVar('gc_id');
        $this->gpr_id = (int) initVar('gpr_id');
        $this->is_paes_action = initVar('is_paes_action') == 'T';

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);
        $this->limit = 0;                                    // No limit to the document list
        $this->do_id = $_SESSION['do_id'];
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);

        $this->fields = $this->defFields();


        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
        $this->registerAjaxFunction('getGlobalAction');
        $this->registerAjaxFunction('askDelGlobalPlainRow');
        $this->registerAjaxFunction('submitFormData');
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
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        // Ricavo nome macro categoria
        $categoryName = '';

        if ($this->gc_id <> '') {
            $sql = "SELECT gc_parent.gc_name_{$lang} AS gc_name
                    FROM global_category gc
                    INNER JOIN global_category gc_parent ON gc.gc_parent_id=gc_parent.gc_id
                    WHERE gc.gc_id={$this->gc_id}";
            $categoryName = $db->query($sql)->fetchColumn();
        }
        if ($this->act == 'add') {
            $vlu['gc_id'] = $this->gc_id;
            $vlu['gp_id'] = $this->gp_id;
        } else {
            $sql = "SELECT grp.*, mu_id
                    FROM global_plain_row_data grp
                    INNER JOIN global_plain_data gp ON grp.gp_id=gp.gp_id
                    WHERE gpr_id=" . (int) $this->gpr_id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $this->gc_id = $vlu['gc_id'];
            $vlu['data'] = R3EcoGisGlobalPlainHelper::getGaugeData($this->gpr_id);
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_plain_row', $vlu['gpr_id']));
        }
        $vlu['gc_name'] = $categoryName;
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        $lkp['gc_parent_values'] = R3EcoGisHelper::getGlobalCategoryMainList($this->do_id);
        $lkp['gc_parent_values'] = R3EcoGisHelper::getGlobalCategoryMainList($this->do_id);
        $lkp['gc_values'] = R3EcoGisGlobalPlainHelper::getCategoriesList($_SESSION['do_id'], $this->gc_id);
        $lkp['gpa_values'] = R3EcoGisGlobalPlainHelper::getPlainActionList($_SESSION['do_id'], $this->data['gc_id']);
        return $lkp;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function isImportedRow($gpr_id) {
        $db = ezcDbInstance::get();
        if ((int) $gpr_id <= 0) {
            throw new Exception("Invalid gpr_id");
        }
        $sql = "SELECT gpr_imported_row FROM ecogis.global_plain_row WHERE gpr_id=" . (int) $gpr_id;
        return $db->query($sql)->fetchColumn();
    }

    public function submitFormDataDetail($request) {
        $db = ezcDbInstance::get();
        if (isset($request['gpm_date']) && isset($request['gpm_value_1'])) {
            // Insert details
            $sql = "DELETE FROM ecogis.global_plain_monitor WHERE gpr_id={$request['gpr_id']}";
            $db->exec($sql);
            $sql = "INSERT INTO ecogis.global_plain_monitor (gpr_id, gpg_id, gpm_date, gpm_value_1, gpm_value_2) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            for ($i = 0; $i < count($request['gpg_id']); $i++) {
                $gpg_id = empty($request['gpg_id'][$i]) ? null : $request['gpg_id'][$i];
                $gpm_date = forceISODate($request['gpm_date'][$i]);
                $gpm_value_1 = forceFloat($request['gpm_value_1'][$i], null, '.');
                $gpm_value_2 = forceFloat($request['gpm_value_2'][$i], null, '.');
                if (!empty($gpm_date) && !empty($gpm_value_1)) {
                    $stmt->execute(array($request['gpr_id'], $gpg_id, $gpm_date, $gpm_value_1, $gpm_value_2));
                }
            }
        }
    }

    public function checkFormDataDetail(array $request, array $errors = array()) {
        if (isset($request['gpm_date']) && isset($request['gpm_value_1'])) {
            for ($i = 0; $i < count($request['gpg_id']); $i++) {
                if ($request['gpm_date'][$i] == '' xor $request['gpm_value_1'][$i] == '') {
                    $errors['gpm_date'] = array('CUSTOM_ERROR' => _("Data audit e quantità sono obbligatori"));
                }
            }
        }
        return $errors;
    }

    public function hasGauge($gpr_id) {
        $db = ezcDbInstance::get();
        $gpr_id = (int) $gpr_id;
        $sql = "SELECT COUNT(*) FROM ecogis.global_plain_gauge WHERE gpr_id={$gpr_id}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    public function updateGaugeType($gpr_id, $gpr_gauge_type) {
        $db = ezcDbInstance::get();

        // Verifica l'assenza di indicatori
        $gpr_id = (int) $gpr_id;
        // Se ho inserimenti NON permeto il cambio
        $sql = "SELECT COUNT(*) FROM ecogis.global_plain_monitor WHERE gpr_id={$gpr_id}";
        if ($db->query($sql)->fetchColumn() > 0) {
            return;
        }
        // Se ho indicatori, forzo a indicatore (quando modifico la select, aggiungo indicatori, e non ho salvato. In inserimneto indicatore, forzo cambio indicatore)
        $sql = "SELECT COUNT(*) FROM ecogis.global_plain_gauge WHERE gpr_id={$gpr_id}";
        if ($db->query($sql)->fetchColumn() > 0) {
            $gpr_gauge_type = 'G';
        }
        $sql = "UPDATE ecogis.global_plain_row SET gpr_gauge_type=:gpr_gauge_type WHERE gpr_id=:gpr_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('gpr_gauge_type' => $gpr_gauge_type, 'gpr_id' => $gpr_id));
    }

    public function updateDescription($gpr_id, $data) {
        $db = ezcDbInstance::get();

        $data['gpr_descr_2'] = isset($data['gpr_descr_2']) ? $data['gpr_descr_2'] : null;
        $sql = "UPDATE ecogis.global_plain_row SET gpr_descr_1=:gpr_descr_1, gpr_descr_2=:gpr_descr_2 WHERE gpr_id=:gpr_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('gpr_descr_1' => $data['gpr_descr_1'], 'gpr_descr_2' => $data['gpr_descr_2'], 'gpr_id' => $gpr_id));
    }

    public function submitFormData($request) {

        $errors = array();
        $db = ezcDbInstance::get();
        $request['gpr_id'] = (int) $request['id'];

        //R3Security::checkGlobalPlainRow(@$request['gc_id'], @$request['gp_id']);
        if ($this->act <> 'add') {
            $isImportedRow = $this->isImportedRow($request['gpr_id']);
        } else {
            $isImportedRow = false;
        }
        if ($this->is_paes_action && empty($request['gpr_id'])) {
            // Ricava il paes dal comune
            if (empty($request['mu_id'])) {
                $errors['mu_id'] = array('CUSTOM_ERROR' => _("Il campo \"Comune\" è obbligatorio. Se il comune non è presente, verificare un PAES sia associato al comune"));
            }
            $sql = "SELECT gp_id FROM ecogis.global_strategy WHERE gp_id IS NOT NULL AND mu_id=" . (int) $request['mu_id'];
            $gp_id = $db->query($sql)->fetchColumn();
            if (empty($gp_id)) {
                $errors['mu_id'] = array('CUSTOM_ERROR' => _("Per poter inserire un azione assicurarsi di aver associato il PAES al comune nei parametri principali"));
            }
            $request['gp_id'] = $gp_id;
        }
        if ($this->act <> 'del') {
            if (!$isImportedRow) {
                $errors = array_merge($errors, $this->checkFormData($request));
            }
            $errors = array_merge($errors, $this->checkFormDataDetail($request, $errors));
        }

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        }

        $db->beginTransaction();
        if ($this->hasGauge($request['gpr_id'])) {
            $request['gpr_gauge_type'] = 'G';
        }
        if ($isImportedRow) {
            // Only update and delete
            if ($this->act == 'del') {
                $errors['gpr_imported_row'] = array('CUSTOM_ERROR' => _("Impossibile cancellare i dati importati"));
                return $this->getAjaxErrorResult($errors);
            }
            $id = $request['gpr_id'];
            $this->updateGaugeType($id, $request['gpr_gauge_type']);

            if (isset($request['gpr_descr_1'])) {
                $this->updateDescription($id, $request);
            }
            $this->submitFormDataDetail($request);
        } else {
            // Standard
            $id = $this->applyData($request);
            $this->submitFormDataDetail($request);
        }
        $db->Commit();
        R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneGlobalPlainRow($id)");
    }

    public function askDelGlobalPlainRow($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler eliminare l'azione selezioanta?")));
    }

    public function getGlobalAction($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    R3EcoGisGlobalPlainHelper::getPlainActionList($_SESSION['do_id'], $request['gc_id'], array('allow_empty' => true)))
        );
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = 'GLOBAL_PLAIN_TABLE';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if ($this->act == 'list') {
            return true;
        }
        if ($this->act == 'add') {
            //R3Security::checkGlobalPlain($this->gp_id);
        } else {
            // Can edit/delete the given id
            if (!in_array($this->method, array())) {
                if ($this->gpr_id == null) {
                    $this->gpr_id = $this->id;
                }
                R3Security::checkGlobalPlainRow($this->gpr_id);
            }
        }
    }

}
