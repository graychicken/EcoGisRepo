<?php

class eco_stat_type extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'stat_type';

    /**
     * building fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'st_id', 'type' => 'integer', 'is_primary_key' => true),
            //array('name' => 'do_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'auth.domains')),
            //array('name' => 'st_parent_id', 'type' => 'lookup', 'required' => true, 'label' => _('Categoria principale'), 'lookup' => array('table' => 'stat_type')),
            //array('name' => 'st_code', 'type' => 'text', 'label' => _('Codice')),
            array('name' => 'st_title_short_1', 'type' => 'text', 'label' => _('Titolo corto'), 'required' => true),
            array('name' => 'st_title_short_2', 'type' => 'text', 'label' => _('Titolo corto'), 'required' => false),
            array('name' => 'st_title_long_1', 'type' => 'text', 'label' => _('Titolo lungo')),
            array('name' => 'st_title_long_2', 'type' => 'text', 'label' => _('Titolo lungo')),
            array('name' => 'st_udm_1', 'type' => 'text', 'label' => _('Unità di misura assoluta')),
            array('name' => 'st_udm_2', 'type' => 'text', 'label' => _('Unità di misura assoluta')),
            array('name' => 'st_udm_relative_1', 'type' => 'text', 'label' => _('Unità di misura relativa')),
            array('name' => 'st_udm_relative_2', 'type' => 'text', 'label' => _('Unità di misura relativa')),
            array('name' => 'st_descr_1', 'type' => 'text', 'label' => _('Descrizione superiore')),
            array('name' => 'st_descr_2', 'type' => 'text', 'label' => _('Descrizione superiore')),
            array('name' => 'st_show_text_value', 'type' => 'boolean', 'label' => _('Attiva'), 'default' => false),
            array('name' => 'st_text_value_title_1', 'type' => 'text', 'label' => _('Titolo dato testuale')),
            array('name' => 'st_text_value_title_2', 'type' => 'text', 'label' => _('Titolo dato testuale')),
            array('name' => 'st_lower_descr_1', 'type' => 'text', 'label' => _('Descrizione inferiore')),
            array('name' => 'st_lower_descr_2', 'type' => 'text', 'label' => _('Descrizione inferiore')),
            array('name' => 'st_order', 'type' => 'integer', 'label' => _('Ordinamento'), 'required' => true, 'default' => '0'),
            array('name' => 'st_enable', 'type' => 'boolean', 'label' => _('Attiva'), 'default' => false),
            array('name' => 'st_visible', 'type' => 'boolean', 'label' => _('Visibile'), 'default' => false),
            array('name' => 'st_private', 'type' => 'boolean', 'label' => _('Richiede autenticazione'), 'default' => false),
            array('name' => 'st_render_map_as_grid', 'type' => 'boolean', 'label' => _('Rendering con griglia'), 'default' => false),
            array('name' => 'st_render_preview_as_grid', 'type' => 'boolean', 'label' => _('Rendering anteprima con griglia'), 'default' => false),
            array('name' => 'st_has_municipality_data', 'type' => 'boolean', 'label' => _('Statistica con ambito comunale'), 'default' => false),
            array('name' => 'st_has_municipality_community_data', 'type' => 'boolean', 'label' => _('Statistica con ambito aggregazione di comuni'), 'default' => false),
            array('name' => 'st_has_province_data', 'type' => 'boolean', 'label' => _('Statistica con ambito provinciale'), 'default' => false),
            array('name' => 'st_has_absolute_data', 'type' => 'boolean', 'label' => _('Dati assoluti'), 'default' => false),
            array('name' => 'st_has_relative_data', 'type' => 'boolean', 'label' => _('Dati relativi'), 'default' => false),
            array('name' => 'st_has_year', 'type' => 'boolean', 'label' => _('Statistica su base annua'), 'default' => false),
            array('name' => 'st_has_building_purpose_use', 'type' => 'boolean', 'label' => _("Filtrabile per destinazione d'uso edificio"), 'default' => false),
            array('name' => 'st_has_building_build_year', 'type' => 'boolean', 'label' => _('Filtrabile per anno di costruzione edificio'), 'default' => false),
            array('name' => 'st_has_category_data', 'type' => 'boolean', 'label' => _('Filtrabile per categorie inventario/PAES'), 'default' => false),
            array('name' => 'st_has_energy_source_data', 'type' => 'boolean', 'label' => _('Filtrabile per alimentazione inventario/PAES '), 'default' => false),
        );

        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $rows = array(//'st_code' => array('label' => _('Codice')),
            'st_title_short_full' => array('label' => _('Nome')),
            'st_order' => array('label' => _('Ordinamento'), 'width' => 70, 'visible' => true, 'options' => array('align' => 'center', 'order_fields' => 'st_order, st_title_short_full')),
            'st_enable' => array('label' => _('Attivo'), 'width' => 70, 'visible' => true, 'options' => array('align' => 'center', 'order_fields' => 'st_enable, st_title_short_full')),
            'st_visible' => array('label' => _('Visibile'), 'width' => 70, 'visible' => true, 'options' => array('align' => 'center', 'order_fields' => 'st_visible, st_title_short_full')),
        );
        return $rows;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);

        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list' || $reset || $init;  // if true store the filter variables
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->id = initVar('id');
        if (initVar('bu_id') !== null) {
            $this->id = initVar('bu_id');
        }
        $this->last_id = initVar('last_id');
        $this->parent_act = initVar('parent_act');
        $this->act = initVar('act', 'list');

        $this->do_id = $_SESSION['do_id'];
        $this->st_parent_id = PageVar('st_parent_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->st_name = PageVar('st_name', null, $init | $reset, false, $this->baseName, $storeVar);

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('generateStatisticClass');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'mod': return _('Modifica statistica');
            case 'list': return _('Elenco statistiche');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();

        $filters['st_parent_id_values'] = R3EcogisHelper::getStatisticMainList($this->do_id);
        $filters['st_parent_id'] = $this->st_parent_id;
        $filters['st_name'] = $this->st_name;
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

        if ($this->st_parent_id <> '') {
            $where[] = $q->expr->eq('st_parent_id', $db->quote((int) $this->st_parent_id));
        }
        if ($this->st_name <> '') {
            $where[] = "(st_title_short_1_full ILIKE " . $db->quote("%{$this->st_name}%") . " OR st_title_short_1_full ILIKE " . $db->quote("%{$this->st_name}%") . ")";
        }

        $q->select("st_id, st_code, st_title_short_{$lang}_full AS st_title_short_full, st_order, st_enable, st_visible")
                ->from('ecogis.stat_type_data');
        if (count($where) > 0) {
            $q->where($where);
        }
        // echo $q;
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($this->getTableColumnConfig(), $this->baseName);

        foreach ($tableColumns as $fieldName => $colDef) {
            if ($colDef['visible']) {
                $this->simpleTable->addSimpleField($colDef['label'], $fieldName, $colDef['type'], $colDef['width'], $colDef['options']);
            }
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'building_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $row['st_visible'] = $row['st_visible'] === true ? 'X' : '';
        $row['st_enable'] = $row['st_enable'] === true ? 'X' : '';

        $id = $row['st_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();

        foreach (array('mod') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelBuilding('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getStatisticCapabilitiesByCode($code) {
        // include statistics class
        $statFileName = R3_CLASS_DIR . 'stat/' . strtolower($code) . '.php';
        $className = 'eco_stat_' . strtolower($code);
        require_once R3_LIB_DIR . 'obj.base_stat.php';
        require_once $statFileName;
        return call_user_func("{$className}::getCapabilities", array());
    }

    public function generateClasses($code) {
        // include statistics class
        $statFileName = R3_CLASS_DIR . 'stat/' . strtolower($code) . '.php';
        $className = 'eco_stat_' . strtolower($code);
        require_once R3_LIB_DIR . 'obj.base_stat.php';
        require_once $statFileName;
        if (method_exists($className, 'generateClasses')) {
            return call_user_func("{$className}::generateClasses", ezcDbInstance::get(), $this->do_id);
        }
        return null;
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();

        //R3Security::checkBuilding($this->id);
        $q = $db->createSelectQuery();
        $q->select('*')
                ->from('stat_type_data')
                ->where("st_id={$this->id}");
        $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);

        $capabilities = $this->getStatisticCapabilitiesByCode($vlu['st_code']);
        $context = R3EcoGisStatHelper::getBetterContext($vlu['st_code']);
        $childContext = R3EcoGisStatHelper::getBetterChildContext($vlu['st_code']);
        $classes['absolute'] = R3EcoGisStatHelper::getStatsLegend($vlu['do_id'], $vlu['st_code'], $context, $childContext, true);
        if ($vlu['st_has_relative_data']) {
            $classes['relative'] = R3EcoGisStatHelper::getStatsLegend($vlu['do_id'], $vlu['st_code'], $context, $childContext, false);
        }
        $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('building', $vlu['st_id']), array('capabilities' => $capabilities, 'classes' => $classes));

        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null) {
        $lkp = array();

        return $lkp;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    private function saveClass($id, $type, $isValueStat, $request) {
        $db = ezcDbInstance::get();
        $statData = R3EcoGisStatHelper::getStatTypeData($this->do_id, R3EcoGisStatHelper::getStatTypeCodeById($id));
        // Salvo con contesto di partenza più piccolo (comune o provincia)
        $sc_id = R3EcoGisStatHelper::getContextIdByCode(R3EcoGisStatHelper::getBetterContext($statData['st_code']));
        // Salvo con contesto di arrivo più piccolo (edifici, comune o aggregazione)
        $sc_id_child = R3EcoGisStatHelper::getContextIdByCode(R3EcoGisStatHelper::getBetterChildContext($statData['st_code']));

        $sql = "DELETE FROM ecogis.stat_type_class WHERE st_id={$id} AND stc_automatic IS FALSE AND stc_type_absolute IS " . ($type == 'absolute' ? 'TRUE' : 'FALSE');  // Evita di non vedere le statistiche prima del ricalcolo
        $db->exec($sql);

        $sql = "INSERT INTO ecogis.stat_type_class (st_id, stc_order, stc_text_1, stc_text_2, stc_color, stc_outline_color, stc_expression, stc_type_absolute, stc_value, sc_id, sc_id_child)
                VALUES (:st_id, :stc_order, :stc_text_1, :stc_text_2, :stc_color, :stc_outline_color, :stc_expression, :stc_type_absolute, :stc_value, :sc_id, :sc_id_child)";
        $stmt = $db->prepare($sql);
        $idList = array();
        for ($ii = 0; $ii < count($request['stc_type']); $ii++) {
            if ((string) $request['stc_order'][$ii] != '' && $request['stc_type'][$ii] == $type) {
                $idList[] = $ii;
            }
        }
        $seq = 0;
        foreach ($idList as $key) {
            if ($request['stc_type'][$key] == 'absolute') {
                $field = 'sdt_absolute_value';
                $prec = 'st_value_1_prec';
            } else if ($request['stc_type'][$key] == 'relative') {
                $field = 'sdt_relative_value';
                $prec = 'st_value_2_prec';
            } else {
                throw new Exception("Invalid kind \"{$request['stc_type'][$key]}\"");
            }
            if ($isValueStat) {
                if ($seq == 0) {
                    $expr = "[{$field}]<{$request['stc_value'][$key]}";
                    $text1 = sprintf(R3_STAT_FROM_TEXT_1, R3NumberFormat($request['stc_value'][$key], $statData[$prec], true));
                    $text2 = sprintf(R3_STAT_FROM_TEXT_2, R3NumberFormat($request['stc_value'][$key], $statData[$prec], true));
                } else if ($seq == count($idList) - 1) {
                    $expr = "[{$field}]>={$request['stc_value'][$key]}";
                    $text1 = sprintf(R3_STAT_TO_TEXT_1, R3NumberFormat($oldValue, $statData[$prec], true));
                    $text2 = sprintf(R3_STAT_TO_TEXT_2, R3NumberFormat($oldValue, $statData[$prec], true));
                } else {
                    $expr = "[{$field}]>={$oldValue} AND [{$field}]<{$request['stc_value'][$key]}";
                    $text1 = sprintf(R3_STAT_BETWEEN_TEXT_1, R3NumberFormat($oldValue, $statData[$prec], true), R3NumberFormat($request['stc_value'][$key], $statData[$prec], true));
                    $text2 = sprintf(R3_STAT_BETWEEN_TEXT_2, R3NumberFormat($oldValue, $statData[$prec], true), R3NumberFormat($request['stc_value'][$key], $statData[$prec], true));
                }
                $oldValue = $request['stc_value'][$key];
                $data = array('st_id' => $id,
                    'stc_order' => $request['stc_order'][$key],
                    'stc_text_1' => $text1,
                    'stc_text_2' => $text2,
                    'stc_color' => $request['stc_color'][$key],
                    'stc_outline_color' => $request['stc_outline_color'][$key],
                    'stc_expression' => empty($request['stc_value'][$key]) ? null : $expr,
                    'stc_type_absolute' => $request['stc_type'][$key] == 'absolute' ? 't' : 'f',
                    'stc_value' => empty($request['stc_value'][$key]) ? null : $request['stc_value'][$key],
                    'sc_id' => $sc_id,
                    'sc_id_child' => $sc_id_child,
                );
            } else {
                $data = array('st_id' => $id,
                    'stc_order' => $request['stc_order'][$key],
                    'stc_text_1' => $request['stc_text_1'][$key],
                    'stc_text_2' => $request['stc_text_2'][$key],
                    'stc_color' => $request['stc_color'][$key],
                    'stc_outline_color' => $request['stc_outline_color'][$key],
                    'stc_expression' => empty($request['stc_expression'][$key]) ? null : $request['stc_expression'][$key],
                    'stc_type_absolute' => $request['stc_type'][$key] == 'absolute' ? 't' : 'f',
                    'stc_value' => empty($request['stc_value'][$key]) ? null : $request['stc_value'][$key],
                    'sc_id' => $sc_id,
                    'sc_id_child' => $sc_id_child,
                );
            }
            $stmt->execute($data);
            $seq++;
        }
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    // MODIFY ONLY
    public function submitFormData($request) {
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();

        $request['st_id'] = $request['id'];
        $errors = $this->checkFormData($request);
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        }
        $db->beginTransaction();

        $id = $this->applyData($request);

        // Salva classi 
        $capabilities = $this->getStatisticCapabilitiesByCode($request['st_code']);
        if (!empty($request['st_has_absolute_data']) || (empty($request['st_has_absolute_data']) && empty($request['st_has_relative_data']))) {
            // Solo se ho dati assoluti o non ho ne dati relativi ne assoluti
            $this->saveClass($id, 'absolute', $capabilities['is_value_stat'], $request);
        }
        if (!empty($request['st_has_relative_data'])) {
            // Solo dati relativi
            $this->saveClass($id, 'relative', $capabilities['is_value_stat'], $request);
        }
        $db->commit();

        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneStatType($id)");
    }

    public function decodeRGB($rgbText) {
        if ($rgbText == '') {
            return array(0, 0, 0);
        }
        if ($rgbText[0] == '#') {
            $rgbText = substr($rgbText, 1);
        }
        $r = hexdec(substr($rgbText, 0, 2));
        $g = hexdec(substr($rgbText, 2, 2));
        $b = hexdec(substr($rgbText, 4, 2));
        return array($r, $g, $b);
    }

    public function generateStatisticClass($request) {
        $id = (int) $request['id'];
        if ($request['kind'] == 'absolute') {
            $field = 'sdt_absolute_value';
            $prec = 'st_value_1_prec';
        } else if ($request['kind'] == 'relative') {
            $field = 'sdt_relative_value';
            $prec = 'st_value_2_prec';
        } else {
            throw new Exception("Invalid kind \"{$request['kind']}\"");
        }

        require_once R3_LIB_DIR . 'eco_stat_utils.php';
        require_once R3_LIB_DIR . 'stats_quantile_round.php';
        $statData = R3EcoGisStatHelper::getStatTypeData($this->do_id, R3EcoGisStatHelper::getStatTypeCodeById($id));

        $db = ezcDbInstance::get();
        $sql = "SELECT {$field} 
                FROM ecogis.stat_data_table sdt
                INNER JOIN ecogis.stat_data sd ON sd.sd_id=sdt.sd_id
                INNER JOIN ecogis.stat_type st ON sd.st_id=st.st_id
                INNER JOIN  ecogis.stat_context sc ON sdt.sc_id=sc.sc_id
                WHERE st.do_id={$this->do_id} AND sd.st_id={$id} AND sc_code='BUILDING' AND gc_id IS NULL AND ges_id IS NULL AND sbpu_id IS NULL AND bby_id IS NULL";
        $data = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $capabilities = $this->getStatisticCapabilitiesByCode($statData['st_code']);
        if ($capabilities['is_value_stat']) {
            $limits = $capabilities['default_class_no'];

            $limits = getQuantileRoundLimits($data, $limits, $statData[$prec]);
            $result = array();
            $i = 0;
            $oldValue = null;
            list($r, $g, $b) = $this->decodeRGB($capabilities['class_start_color']);
            list($rEnd, $gEnd, $bEnd) = $this->decodeRGB($capabilities['class_end_color']);
            $deltaR = ($rEnd - $r) / (count($limits) + 1);
            $deltaG = ($gEnd - $g) / (count($limits) + 1);
            $deltaB = ($bEnd - $b) / (count($limits) + 1);
            foreach ($limits as $limit) {
                $expr = $i == 0 ? "[{$field}]<{$limit}" : "[{$field}]>={$oldValue} AND [{$field}]<{$limit}";
                $text1 = $i == 0 ? sprintf('fino a %s', R3NumberFormat($limit, $statData[$prec], true)) : sprintf('tra %s e %s', R3NumberFormat($oldValue, $statData[$prec], true), R3NumberFormat($limit, $statData[$prec], true));
                $text2 = $i == 0 ? sprintf('bis %s', R3NumberFormat($limit, $statData[$prec], true)) : sprintf('von %s und %s', R3NumberFormat($oldValue, $statData[$prec], true), R3NumberFormat($limit, $statData[$prec], true));
                $result[] = array('stc_color' => '#' . sprintf('%02X%02X%02X', $r, $g, $b),
                    'stc_outline_color' => '#000000',
                    'stc_text_1' => $text1,
                    'stc_text_2' => $text2,
                    'stc_value' => $limit,
                    'stc_order' => 10 + $i * 10,
                    'stc_expression' => $expr,
                );
                $i++;
                $oldValue = $limit;
                $r+=$deltaR;
                $g+=$deltaG;
                $b+=$deltaB;
            }
            $expr = "[{$field}]>={$oldValue}";
            $result[] = array('stc_color' => '#' . sprintf('%02X%02X%02X', $r, $g, $b),
                'stc_outline_color' => '#000000',
                'stc_text_1' => sprintf('oltre %s', R3NumberFormat($oldValue, $statData[$prec], true)),
                'stc_text_2' => sprintf('mehr als %s', R3NumberFormat($oldValue, $statData[$prec], true)),
                'stc_value' => $oldValue,
                'stc_order' => 10 + $i * 10,
                'stc_expression' => $expr,
            );
        } else {
            $classData = $this->generateClasses($statData['st_code']);
            if ($classData == null) {
                throw new Exception("Impossibile calcolare automaticamente le classi");
            }
            $i = 0;
            foreach ($classData as $data) {
                $r = rand(0, 255);
                $g = rand(0, 255);
                $b = rand(0, 255);
                $expr = "[{$field}]={$data['id']}";
                $result[] = array('stc_color' => '#' . sprintf('%02X%02X%02X', $r, $g, $b),
                    'stc_outline_color' => '#000000',
                    'stc_text_1' => $data['text_1'],
                    'stc_text_2' => $data['text_1'],
                    'stc_value' => $data['id'],
                    'stc_order' => 10 + $i * 10,
                    'stc_expression' => $expr,
                );
                $i++;
            }
        }

        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $result);
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        //if (!in_array($this->act, array('list', 'add'))) {
        //    R3Security::checkBuilding($this->id);
        //}
    }

}
