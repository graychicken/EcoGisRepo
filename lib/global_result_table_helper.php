<?php

require_once R3_LIB_DIR.'obj.base_locale.php';
require_once R3_LIB_DIR.'eco_utils.php';

class R3EcoGisGlobalTableHelper {

    // Restituisce TRUE se la fojnte è energia elettrica in base a ges_id
    static private function getEnergyTypeBySourceId($ges_id) {
        static $cache = null;

        if (!isset($cache[$ges_id])) {
            $db = ezcDbInstance::get();
            $sql = "SELECT ges_id, get_code 
                FROM ecogis.global_energy_source ges
                INNER JOIN ecogis.global_energy_type get ON ges.get_id=get.get_id";
            foreach ($db->query($sql) as $row) {
                $cache[$row['ges_id']] = $row['get_code'];
            }
        }
        return $cache[$ges_id];
    }

    /**
     * Return the parameter list of a table
     *
     * return array                     the domain list
     */
    static public function getParameterList($kind, array $opt = array()) {
        $lang = \R3Locale::getLanguageID();
        $opt = array_merge(array('show_udm' => false), $opt);

        $sql = "SELECT gest_id, get.get_id, get_name_{$lang} AS get_name, ges.ges_id, ges_name_{$lang} AS ges_name,
                       CASE get_show_label WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS get_show_label
               FROM ecogis.global_type gt
               INNER JOIN ecogis.global_energy_source_type gest ON gt.gt_id=gest.gt_id
               INNER JOIN ecogis.global_energy_source ges ON gest.ges_id=ges.ges_id
               INNER JOIN ecogis.global_energy_type get ON get.get_id=ges.get_id
               WHERE gt_code='$kind'
               ORDER BY gest_order, get_order, ges_order";
        $db = ezcDbInstance::get();
        $header = array();
        $i1 = 0;
        $tot = 0;
        foreach ($db->query($sql) as $row) {
            if ($row['get_show_label'] == 'T') {
                if ($i1 > 0 && $row['get_id'] == $header['line1'][$i1 - 1]['id']) {
                    $header['line1'][$i1 - 1]['colspan'] ++;
                } else {
                    $header['line1'][$i1] = array('id' => $row['get_id'], 'label' => $row['get_name'], 'options' => array('xls_style' => 'table-header'), 'rowspan' => 1, 'colspan' => 1, 'width' => 500);
                    $i1++;
                }
                $header['line2'][] = array('id' => $row['ges_id'], 'label' => $row['ges_name'], 'options' => array('xls_style' => 'table-header', 'zzzxls_height' => 60));  // excel style + height
            } else {
                $header['line1'][$i1] = array('id' => $row['get_id'], 'label' => $row['ges_name'], 'options' => array('xls_style' => 'table-header'), 'rowspan' => 2, 'colspan' => 1);
                $i1++;
            }
            $tot++;
        }
        if (in_array($kind, array('CONSUMPTION', 'EMISSION'))) {
            $header['line1'][$i1] = array('label' => _('Totale'), 'is_total' => 'T', 'rowspan' => 2, 'colspan' => 1, 'options' => array('xls_style' => 'table-header'));
        }
        $header1stColumnName = '';
        $header['line0'][0] = array('label' => _('Categorie'), 'has_openclose' => 'T', 'rowspan' => 3, 'colspan' => 1, 'width' => 1500, 'options' => array('xls_style' => 'table-header'));
        switch ($kind) {
            case 'CONSUMPTION':
                $header['line0'][1] = array('label' => _('Consumo energetico finale') . ($opt['show_udm'] ? ' [MWh]' : ''), 'rowspan' => 1, 'colspan' => $tot + 1, 'options' => array('xls_style' => 'table-header'));
                break;
            case 'EMISSION':
                $header['line0'][1] = array('label' => _('Emissioni di CO2 o equivalenti di CO2') . ($opt['show_udm'] ? ' [t]' : ''), 'rowspan' => 1, 'colspan' => $tot + 1, 'options' => array('xls_style' => 'table-header'));
                break;
            case 'ENERGY_PRODUCTION':
                $header['line0'][1] = array('label' => _('Vettore energetico utilizzato'), 'rowspan' => 1, 'colspan' => $tot, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header['line0'][2] = array('label' => _('Emissioni di CO2 o equivalenti di CO2 [t]'), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header['line0'][3] = array('label' => _('Fattori di emissione di CO2 [t/MWh]'), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header1stColumnName['line0'] = array('label' => _('Elettricità prodotta localmente') . ($opt['show_udm'] ? ' [MWh]' : ''), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                break;
            case 'HEATH_PRODUCTION':
                $header['line0'][1] = array('label' => _('Vettore energetico utilizzato'), 'rowspan' => 1, 'colspan' => $tot, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header['line0'][2] = array('label' => _('Emissioni di CO2 o equivalenti di CO2 [t]'), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header['line0'][3] = array('label' => _('Fattori di emissione di CO2 [t/MWh]'), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                $header1stColumnName['line0'] = array('label' => _('Calore/freddo prodotti localmente') . ($opt['show_udm'] ? ' [MWh]' : ''), 'rowspan' => 3, 'colspan' => 1, 'options' => array('xls_style' => 'table-header', 'xls_height' => 60));
                break;
        }
        return array('line0' => $header['line0'], 'line1' => $header['line1'], 'line2' => $header['line2'], 'first_fixed_column' => $header1stColumnName);
    }

    /**
     * Return the parameter list of a table
     *
     * return array                     the domain list
     */
    static public function getParameterCount($kind) {
        $sql = "SELECT COUNT(*)
                FROM ecogis.global_type gt
                INNER JOIN ecogis.global_energy_source_type gest ON gt.gt_id=gest.gt_id
                INNER JOIN ecogis.global_energy_source ges ON gest.ges_id=ges.ges_id
                WHERE gt_code='$kind'";
        $db = ezcDbInstance::get();
        return $db->query($sql)->fetchColumn();
    }

    /**
     * Return the data DIVEDED by the dividerFactor
     *
     */
    static private function applyDivider($data, $dividerFactor) {
        if ($data === '')
            return '';
        $prec = array('1' => 0, '1000' => 1, '1000000' => 2);
        return $data / $dividerFactor;
    }

    static public function getGlobalMethod($ge_id, $gc_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $sql = "SELECT DISTINCT gs.gm_id, gm_name_{$lang} AS gm_name, gm_order
                FROM ecogis.global_subcategory gs
                INNER JOIN ecogis.global_method gm ON gs.gm_id=gm.gm_id
                WHERE ge_id=? AND gc_id=?
                ORDER BY gm_order, gm_name_{$lang}, gm_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($ge_id, $gc_id));
        $result = array();
        $tot = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tot++;
            $result['data'][$row['gm_id']] = $row['gm_name'];
        }
        $result['title'] = $tot == 0 ? null : implode(', ', $result['data']);
        return $result;
    }

    static private function getCategoriesDataMunicipality($ge_id, $kind, $divider, $returnAsLocale, $gc_id) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $ge_id = (int) $ge_id;
        $decimals = $divider == 1 ? 0 : 1;

        $em_is_production = ($kind == 'ENERGY_PRODUCTION' || $kind == 'HEATH_PRODUCTION') ? 'T' : 'F';

        $sql = "SELECT mu_id, ge_year, ge_national_efe, ge_local_efe FROM ecogis.global_entry WHERE ge_id={$ge_id}";
        list($mu_id, $year, $nationalEFE, $localEFE) = $db->query($sql)->fetch(PDO::FETCH_NUM);
        $mu_id = (int) $mu_id;
        $year = (int) $year;
        // EFE migliore: Locale inventario, nazionale inventario, locale globale, nazionale
        if ($localEFE <> '') {
            $efe = $localEFE;
        } else if ($nationalEFE <> '') {
            $efe = $nationalEFE;
        } else {
            $efe = R3EcoGisHelper::getElectricityCO2Factor($_SESSION['do_id'], $mu_id);
        }

        $sql = "SELECT gest_id, get.get_id, get_name_{$lang} AS get_name, ges.ges_id, ges_name_{$lang} AS ges_name,
                       CASE get_show_label WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS get_show_label
                FROM ecogis.global_type gt
                INNER JOIN ecogis.global_energy_source_type gest ON gt.gt_id=gest.gt_id
                INNER JOIN ecogis.global_energy_source ges ON gest.ges_id=ges.ges_id
                INNER JOIN ecogis.global_energy_type get ON get.get_id=ges.get_id
                WHERE gt_code='{$kind}'
                ORDER BY gest_order, get_order, ges_order";
        $parameters = array();
        $globalSumSourceDefault = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $parameters[$row['ges_id']] = $row;
            $globalSumSourceDefault[$row['ges_id']] = null;
        }
        $sql = "SELECT gc1.gc_id AS main_id, gc1.gc_code AS main_code, gc1.gc_name_{$lang} AS main_name, gc1.gc_show_label AS main_show_label,
                       gc2.gc_id AS gc_id, gc2.gc_code AS gc_code, gc2.gc_name_{$lang} AS gc_name, gc2.gc_total_only
                FROM ecogis.global_category gc1
                INNER JOIN ecogis.global_category gc2 ON gc2.gc_parent_id=gc1.gc_id
                INNER JOIN ecogis.global_category_type gcat ON gc2.gc_id=gcat.gc_id
                INNER JOIN ecogis.global_type gt ON gt.gt_id=gcat.gt_id
                WHERE gt_code='{$kind}' ";
        if ($gc_id !== null) {
            $gc_id = (int) $gc_id;
            $sql .=" AND gc2.gc_id={$gc_id} ";
        }
        $sql .="ORDER BY gc1.gc_order, gc1.gc_name_{$lang}, gc1.gc_id, gcat_order, gc2.gc_order, gc2.gc_name_{$lang}, gc2.gc_id";
        $categories = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $categories[$row['gc_id']] = $row;
        }

        $fieldName = $kind == 'EMISSION' ? 'co_value_co2' : 'co_value_kwh';

        $data = array();
        $buildingProduction = array();

        //Ricavo dati inseriti da form (non edifici e non illuminazione) e i totali
        $sql = "SELECT 'GLOBAL' AS kind, ge_id, gs_id, gs_name_{$lang} AS gs_name, gc_id, ges_id, co_value_kwh, co_value_co2, NULL AS gs_tot_value, the_geom IS NOT NULL AS has_geometry
                FROM ecogis.consumption_year_global
                WHERE mu_id={$mu_id} AND ge_id={$ge_id} AND ge_year={$year}

                UNION

                SELECT 'GLOBAL' AS kind, ge_id, gs_id, gs_name_{$lang} AS gs_name, gc.gc_id, NULL AS ges_id, NULL AS co_value_kwh, NULL AS co_value_co2, gs_tot_value, the_geom IS NOT NULL AS has_geometry
                FROM ecogis.global_subcategory gs
                INNER JOIN ecogis.global_category gc ON gs.gc_id=gc.gc_id
                WHERE ge_id={$ge_id} 

                ORDER BY gs_name";

        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $gcIdList[$row['gc_id']] = $row['gc_id'];
            $data[$row['gc_id']][$row['gs_id']]['header'] = array('kind' => $row['kind'],
                'id' => $row['gs_id'],
                'name' => $row['gs_name'],
                'sum' => R3EcoGisGlobalTableHelper::applyDivider($row['gs_tot_value'], $divider),
                'co2_sum' => null,
                'has_geometry' => $row['has_geometry']);
            if ($row['ges_id'] != '') {
                if ($kind == 'EMISSION' && self::getEnergyTypeBySourceId($row['ges_id']) == 'ELECTRICITY') {
                    $data[$row['gc_id']][$row['gs_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($efe * $row['co_value_kwh'], $divider);
                } else {
                    $data[$row['gc_id']][$row['gs_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($row[$fieldName], $divider);
                }
                $data[$row['gc_id']][$row['gs_id']]['co2_value'][$row['ges_id']] = $row['co_value_co2'];
            }
        }

        // Ricavo dati edifici
        $sql = "SELECT 'BUILDING' AS kind, 10000000+bu_id as bu_id, bu_name_{$lang} AS bu_name, gc_id, ges_id, co_value_kwh, co_value_co2, the_geom IS NOT NULL AS has_geometry
                FROM ecogis.consumption_year_building
                WHERE mu_id={$mu_id} AND co_year={$year} AND ges_id IS NOT NULL AND em_is_production='{$em_is_production}'
                ORDER BY bu_name";

        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($em_is_production) {
                $buildingProduction[$row['gc_id']][$row['bu_id']] = $row['co_value_kwh'];
            }

            $data[$row['gc_id']][$row['bu_id']]['header'] = array('kind' => $row['kind'],
                'id' => $row['bu_id'],
                'name' => $row['bu_name'],
                'sum' => null,
                'co2_sum' => null,
                'has_geometry' => $row['has_geometry']);
            if ($kind == 'EMISSION' &&
                    $row['co_value_co2'] > 0 && // Se fattore conversione CO2 è 0 allora non applico efe!
                    self::getEnergyTypeBySourceId($row['ges_id']) == 'ELECTRICITY') {
                // Applico efe locale o nazionale se presenti nell'inventario
                $data[$row['gc_id']][$row['bu_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($efe * $row['co_value_kwh'], $divider);
            } else {
                $data[$row['gc_id']][$row['bu_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($row[$fieldName], $divider);
            }
        }
        //Ricavo dati illuminazione pubblica
        $sql = "SELECT 'STREET_LIGHTING' AS kind, 11000000+sl_id as sl_id, sl_full_name_{$lang} AS sl_name, gc_id, ges_id, co_value_kwh, co_value_co2, the_geom IS NOT NULL AS has_geometry
                FROM ecogis.consumption_year_street_lighting
                WHERE mu_id={$mu_id} AND co_year={$year} AND ges_id IS NOT NULL AND em_is_production='{$em_is_production}'
                ORDER BY sl_name";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $data[$row['gc_id']][$row['sl_id']]['header'] = array('kind' => $row['kind'],
                'id' => $row['sl_id'],
                'name' => $row['sl_name'],
                'sum' => null,
                'co2_sum' => null,
                'has_geometry' => $row['has_geometry']);
            if ($kind == 'EMISSION' && self::getEnergyTypeBySourceId($row['ges_id']) == 'ELECTRICITY') {
                $data[$row['gc_id']][$row['sl_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($efe * $row['co_value_kwh'], $divider);
            } else {
                $data[$row['gc_id']][$row['sl_id']]['data'][$row['ges_id']] = R3EcoGisGlobalTableHelper::applyDivider($row[$fieldName], $divider);
            }
        }

        // Ricavo i dati aggiuntivi per produzione elettricità e calore/freddo (Nel db i valori sono in kWH)
        $sql = "SELECT gs.gc_id, gs_id, gs_tot_production_value, gs_tot_emission_value, gs_tot_emission_factor
                FROM ecogis.global_subcategory gs
                INNER JOIN ecogis.global_category gc on gs.gc_id=gc.gc_id
                INNER JOIN ecogis.global_category_type gcat on gc.gc_id=gcat.gc_id
                INNER JOIN ecogis.global_type gt on gt.gt_id=gcat.gt_id
                WHERE gt_code='{$kind}' AND ge_id={$ge_id} AND gs_tot_production_value IS NOT NULL";
        $productionData = array();
        $productionSum = array();
        $productionEmissionSum = array();
        $productionEmissionSumFactor = array();
        // Imposto array (serve per export)
        foreach ($categories as $gc_id => $dummy) {
            $productionSum['category'][$gc_id] = null;
            $productionEmissionSum['category'][$gc_id] = null;
        }

        // Add building production data
        $productionTot = 0;
        $productionEmissionTot = 0;

        foreach ($buildingProduction as $gc_id => $buildingProductionData) {
            foreach ($buildingProductionData as $bu_id => $val) {
                $val = R3EcoGisGlobalTableHelper::applyDivider($val, $divider);
                if (isset($categories[$gc_id])) {
                    $productionData[$gc_id][$bu_id]['production'] = $returnAsLocale ? R3NumberFormat($val, $decimals, true) : $val;
                    if (!isset($productionSum['category'][$gc_id])) {
                        $productionSum['category'][$gc_id] = 0;
                        $productionEmissionSum['category'][$gc_id] = 0;
                    }
                    $productionSum['category'][$gc_id] += $val;
                    $productionTot += $val;
                }
            }
        }
        $canSumFactor = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $val = R3EcoGisGlobalTableHelper::applyDivider($row['gs_tot_production_value'], $divider);
            $emissionVal = R3EcoGisGlobalTableHelper::applyDivider($row['gs_tot_emission_value'], $divider);
            $emissionFactorVal = R3EcoGisGlobalTableHelper::applyDivider($row['gs_tot_emission_factor'], $divider);
            $productionData[$row['gc_id']][$row['gs_id']]['production'] = $returnAsLocale ? R3NumberFormat($val, $decimals, true) : $val;
            $productionData[$row['gc_id']][$row['gs_id']]['production_emission'] = $returnAsLocale ? R3NumberFormat($emissionVal, $decimals, true) : $emissionVal;
            $productionData[$row['gc_id']][$row['gs_id']]['production_emission_factor'] = $returnAsLocale ? R3NumberFormat($emissionFactorVal, $decimals, true) : $emissionFactorVal;
            if (!isset($productionSum['category'][$row['gc_id']])) {
                $productionSum['category'][$row['gc_id']] = 0;
                $productionEmissionSum['category'][$row['gc_id']] = 0;
            }
            $productionSum['category'][$row['gc_id']] += $val;
            $productionEmissionSum['category'][$row['gc_id']] += $emissionVal;
            // Solo se ho un singolo entry posso sommare i fattori di conversione
            if (!isset($canSumFactor[$row['gc_id']])) {
                $canSumFactor[$row['gc_id']] = $emissionVal;
                $productionEmissionSumFactor['category'][$row['gc_id']] = $emissionFactorVal;
            } else {
                $productionEmissionSumFactor['category'][$row['gc_id']] = 'N/A';
            }
            $productionTot += $val;
            $productionEmissionTot += $emissionVal;
        }
        $productionSum['tot'] = $returnAsLocale ? R3NumberFormat($productionTot, $decimals, true) : $productionTot;
        $productionEmissionSum['tot'] = $returnAsLocale ? R3NumberFormat($productionEmissionTot, $decimals, true) : $productionEmissionTot;
        if (isset($productionSum['category']) && $returnAsLocale) {
            foreach ($productionSum['category'] as $key => $val) {
                $productionSum['category'][$key] = R3NumberFormat($val, $decimals, true);
            }
            foreach ($productionEmissionSum['category'] as $key => $val) {
                $productionEmissionSum['category'][$key] = R3NumberFormat($val, $decimals, true);
            }
        }

        // Generazione tabella
        $result = array();
        foreach ($categories as $gc_id => $cat) {
            $result[$cat['main_id']]['code'] = $cat['main_code'];
            $result[$cat['main_id']]['name'] = $cat['main_name'];
            $result[$cat['main_id']]['sub_total'] = 'T';
            $result[$cat['main_id']]['sub_total_label'] = _('Totale parziale') . ' ' . mb_strtolower($cat['main_name'], 'UTF-8');
            $result[$cat['main_id']]['show_label'] = $cat['main_show_label'] ? 'T' : 'F';
            $result[$cat['main_id']]['options']['xls_style'] = 'category-header';
            $result[$cat['main_id']]['options']['xls_style_sub_total_header'] = 'subtotal-header';
            $result[$cat['main_id']]['options']['xls_style_sub_total_data'] = 'subtotal-data';
            $result[$cat['main_id']]['options']['xls_style_sub_total_data_sum'] = 'subtotal-data-sum';
            $result[$cat['main_id']]['options']['xls_style_category'] = 'category';
            $result[$cat['main_id']]['options']['xls_style_category_data'] = 'category-data';
            $result[$cat['main_id']]['options']['xls_style_category_sum'] = 'category-sum';

            $result[$cat['main_id']]['sum'] = array();
            $result[$cat['main_id']]['categories'][$cat['gc_id']]['header'] = array('id' => $cat['gc_id'],
                'code' => $cat['gc_code'],
                'name' => $cat['gc_name'],
                'total_only' => $cat['gc_total_only'] ? 'T' : 'F',
                'sum' => '',
                'method' => self::getGlobalMethod($ge_id, $cat['gc_id']));
            if (isset($data[$gc_id])) {
                $row = array();
                $sum = array();
                foreach ($parameters as $ges_id => $dummy) {
                    $sum[$ges_id] = '';
                }
                foreach ($data[$gc_id] as $id => $data2) {
                    $row[$id]['header'] = $data2['header'];
                    $row[$id]['header']['sum'] = '';
                    foreach ($parameters as $ges_id => $param) {
                        if (isset($data2['data'][$ges_id])) {
                            $row[$id]['data'][$ges_id] = $data2['data'][$ges_id];
                            if (isset($data2['co2_value'][$ges_id])) {
                                $row[$id]['co2_value'][$ges_id] = $data2['co2_value'][$ges_id];
                            } else {
                                $row[$id]['co2_value'][$ges_id] = 0;
                            }
                            $row[$id]['header']['sum'] += $data2['data'][$ges_id];
                            $sum[$ges_id] += $data2['data'][$ges_id];
                            $result[$cat['main_id']]['categories'][$cat['gc_id']]['header']['sum'] += $data2['data'][$ges_id];
                        } else {
                            $row[$id]['data'][$ges_id] = '';
                        }
                    }
                    if ($data2['header']['sum'] != '') {
                        $row[$id]['header']['sum'] = $data2['header']['sum'] == '' ? '' : $data2['header']['sum'];
                        $result[$cat['main_id']]['categories'][$cat['gc_id']]['header']['sum'] += $data2['header']['sum'];
                    }
                }
                // Check sum
                $result[$cat['main_id']]['categories'][$cat['gc_id']]['sum'] = $sum;
                $result[$cat['main_id']]['categories'][$cat['gc_id']]['sub_categories'] = $row;
            } else {
                $sum = array();
                foreach ($parameters as $ges_id => $dummy) {
                    $sum[$ges_id] = '';
                }
                $result[$cat['main_id']]['categories'][$cat['gc_id']]['sum'] = $sum;
            }
        }
        $tableSum = self::getTableSum($result);

        // Formatto numeri
        $mainCategorySum = array();
        $globalSum = array('label' => _('Totale'), 'total' => null, 'source' => $globalSumSourceDefault);
        foreach ($result as $key1 => $val1) {
            $mainCategorySum[$key1]['total'] = null;
            foreach ($val1['categories'] as $key2 => $val2) {
                // Totale di categoria
                $mainCategorySum[$key1]['total'] += $result[$key1]['categories'][$key2]['header']['sum']; // totale
                $globalSum['total'] += $result[$key1]['categories'][$key2]['header']['sum']; // totale

                $result[$key1]['categories'][$key2]['header']['sum'] = $returnAsLocale ? R3NumberFormat($result[$key1]['categories'][$key2]['header']['sum'], $decimals, true) : $result[$key1]['categories'][$key2]['header']['sum'];
                foreach ($val2['sum'] as $key3 => $val3) {
                    // Totale parziale categoria
                    $result[$key1]['categories'][$key2]['sum'][$key3] = $returnAsLocale ? R3NumberFormat($result[$key1]['categories'][$key2]['sum'][$key3], $decimals, true) : $result[$key1]['categories'][$key2]['sum'][$key3];
                }
                if (isset($val2['sub_categories'])) {
                    foreach ($val2['sub_categories'] as $key3 => $val3) {
                        // Totale sottocategoria
                        $result[$key1]['categories'][$key2]['sub_categories'][$key3]['header']['sum'] = $returnAsLocale ? R3NumberFormat($result[$key1]['categories'][$key2]['sub_categories'][$key3]['header']['sum'], $decimals, true) : $result[$key1]['categories'][$key2]['sub_categories'][$key3]['header']['sum'];
                        foreach ($val3['data'] as $key4 => $val4) {
                            if (!isset($mainCategorySum[$key1]['source'][$key4])) {
                                $mainCategorySum[$key1]['source'][$key4] = null;
                            }
                            if (!isset($globalSum['source'][$key4])) {
                                $globalSum['source'][$key4] = null;
                            }
                            if ($result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4] <> null) {
                                $mainCategorySum[$key1]['source'][$key4] += $result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4];
                                $globalSum['source'][$key4] += $result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4];
                            }
                            // Dato
                            $result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4] = $returnAsLocale ? R3NumberFormat($result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4], $decimals, true) : $result[$key1]['categories'][$key2]['sub_categories'][$key3]['data'][$key4];
                        }
                    }
                }
            }
            $result[$key1]['sum'] = $mainCategorySum[$key1];
        }

        if ($returnAsLocale) {
            // Conversione in locale
            foreach ($result as $key => $val) {
                $result[$key]['sum']['total'] = R3NumberFormat($result[$key]['sum']['total'], $decimals, true);
                if (isset($val['sum']['source'])) {
                    foreach ($val['sum']['source'] as $key2 => $val2) {
                        $result[$key]['sum']['source'][$key2] = R3NumberFormat($val2, $decimals, true);
                    }
                }
            }
            $globalSum['total'] = R3NumberFormat($globalSum['total'], $decimals, true);
            if (isset($globalSum['source'])) {
                foreach ($globalSum['source'] as $key => $val) {
                    $globalSum['source'][$key] = R3NumberFormat($val, $decimals, true);
                }
            }
        }

        return array('data' => $result,
            'table_sum' => $tableSum,
            'sum' => $globalSum,
            'production_data' => $productionData,
            'production_sum' => $productionSum,
            'production_emission_sum' => $productionEmissionSum,
            'production_emission_sum_factor' => $productionEmissionSumFactor);
    }

    static public function getCategoriesData($ge_id, $kind, $divider = 1, $returnAsLocale = false, $gc_id = null, $mergeMunicipalityCollection = true) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $sql = "SELECT mu_id, mu_type, ge_year FROM ecogis.global_entry_data WHERE ge_id=" . (int) $ge_id;
        $muData = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($muData === false) {
            throw new Exception("Global entry #{$ge_id} not found");
        }
        if ($muData['mu_type'] == 'C') {
            // Municipality collection
            // Get collection data and other municipality data

            $result = self::getCategoriesDataMunicipality($ge_id, $kind, $divider, false, $gc_id);
            if ($mergeMunicipalityCollection) {
                // Get all the other municipality inventory. Multiple inventory/year can be present. 
                $sql = "SELECT ge_id, mu_name_{$lang} as mu_name
                    FROM ecogis.municipality mu
                    INNER JOIN ecogis.global_entry ge ON mu.mu_id=ge.mu_id AND ge_year={$muData['ge_year']} AND ge_exclude_from_collection IS FALSE
                    WHERE mu_parent_id={$muData['mu_id']}    
                    ORDER BY mu_name_{$lang}";
                foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                    $data = self::getCategoriesDataMunicipality($row['ge_id'], $kind, $divider, false, $gc_id);
                    self::meregeCategoriesData($result, $data, $row['mu_name']);
                }
            }
            if ($returnAsLocale) {
                self::convertToLocale($result, $divider == 1 ? 0 : 1);
            }
            return $result;
        } else {
            // Single municipality
            return self::getCategoriesDataMunicipality($ge_id, $kind, $divider, $returnAsLocale, $gc_id);
        }
    }

    static private function convertToLocale(&$data, $decimals) {

        // SOMME TABELLA (TOTALONE)
        $data['table_sum'] = R3NumberFormat($data['table_sum'], $decimals, true);  // Somma tabella
        $data['sum']['total'] = R3NumberFormat($data['sum']['total'], $decimals, true);  // Somma tabella
        // SOMME TABELLA (PER FONTE)
        if (!empty($data['sum']['source'])) {
            foreach ($data['sum']['source'] as $key => $dummy) {
                $data['sum']['source'][$key] = R3NumberFormat($data['sum']['source'][$key], $decimals, true);
            }
        }

        // Consumo + emissioni
        foreach ($data['data'] as $key1 => $dummy1) {
            // SOMME PARZIALI MACROCATEGORIA
            $data['data'][$key1]['sum']['total'] = R3NumberFormat($data['data'][$key1]['sum']['total'], $decimals, true);  // Totale macrocategoria (Edifici, attrezzature/impianti e industrie)
            if (!empty($data['data'][$key1]['sum']['source'])) {
                foreach ($data['data'][$key1]['sum']['source'] as $key2 => $dummy2) {
                    $data['data'][$key1]['sum']['source'][$key2] = R3NumberFormat($data['data'][$key1]['sum']['source'][$key2], $decimals, true);
                }
            }
            foreach ($data['data'][$key1]['categories'] as $catId => $dummy2) {
                // TOTALE RIGA PRINCIPALE (Edifici, attrezzature/impianti comunali)
                $data['data'][$key1]['categories'][$catId]['header']['sum'] = R3NumberFormat($data['data'][$key1]['categories'][$catId]['header']['sum'], $decimals, true);
                // TOTALE CELLE PRINCIPALI
                foreach ($data['data'][$key1]['categories'][$catId]['sum'] as $sourceId => $dummy3) {
                    $data['data'][$key1]['categories'][$catId]['sum'][$sourceId] = R3NumberFormat($data['data'][$key1]['categories'][$catId]['sum'][$sourceId], $decimals, true);
                }
                // VALORI SOTTO-CATEGORIE (quelle inserite dagli utenti)
                if (!empty($data['data'][$key1]['categories'][$catId]['sub_categories'])) {
                    foreach ($data['data'][$key1]['categories'][$catId]['sub_categories'] as $id => $dummy3) {
                        $data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['sum'] = R3NumberFormat($data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['sum'], $decimals, true);
                        $data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['co2_sum'] = R3NumberFormat($data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['co2_sum'], $decimals, true);
                        foreach ($data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['data'] as $key => $dummy4) {
                            $data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['data'][$key] = R3NumberFormat($data['data'][$key1]['categories'][$catId]['sub_categories'][$id]['data'][$key], $decimals, true);
                        }
                    }
                }
            }
        }

        // Produzione
        foreach ($data['production_data'] as $key1 => $dummy1) {
            foreach ($data['production_data'][$key1] as $id => $dummy2) {
                $data['production_data'][$key1][$id]['production'] = R3NumberFormat($data['production_data'][$key1][$id]['production'], $decimals, true);
                if (!empty($data['production_data'][$key1][$id]['production_emission'])) {
                    $data['production_data'][$key1][$id]['production_emission'] = R3NumberFormat($data['production_data'][$key1][$id]['production_emission'], $decimals, true);
                }
                if (!empty($data['production_data'][$key1][$id]['production_emission_factor'])) {
                    $data['production_data'][$key1][$id]['production_emission_factor'] = R3NumberFormat($data['production_data'][$key1][$id]['production_emission_factor'], $decimals, true);
                }
            }
        }
        // Somme produzione
        foreach (array('production_sum', 'production_emission_sum', 'production_emission_sum_factor') as $key0) {
            if (!empty($data[$key0]['tot'])) {
                $data[$key0]['tot'] = R3NumberFormat($data[$key0]['tot'], $decimals, true);
            }

            if (!empty($data[$key0]['category'])) {
                foreach ($data[$key0]['category'] as $key1 => $dummy2) {
                    $data[$key0]['category'][$key1] = R3NumberFormat($data[$key0]['category'][$key1], $decimals, true);
                }
            }
        }
    }

    static private function meregeCategoriesData(&$result, &$data, $text) {
        // SOMME TABELLA (TOTALONE)
        $result['table_sum'] += $data['table_sum'];  // Somma tabella
        $result['sum']['total'] += $data['sum']['total'];  // Somma tabella
        // SOMME TABELLA (PER FONTE)
        if (!empty($data['sum']['source'])) {
            foreach ($data['sum']['source'] as $key => $dummy) {
                if (!empty($data['sum']['source'][$key])) {
                    if (empty($result['sum']['source'][$key])) {
                        $result['sum']['source'][$key] = $data['sum']['source'][$key];
                    } else {
                        $result['sum']['source'][$key] += $data['sum']['source'][$key];
                    }
                }
            }
        }
        // Consumo + emissioni
        foreach ($data['data'] as $key1 => $dummy1) {
            // SOMME PARZIALI MACROCATEGORIA
            $result['data'][$key1]['sum']['total'] += $data['data'][$key1]['sum']['total'];  // Totale macrocategoria (Edifici, attrezzature/impianti e industrie)
            if (!empty($data['data'][$key1]['sum']['source'])) {
                foreach ($data['data'][$key1]['sum']['source'] as $key2 => $dummy2) {
                    if (empty($result['data'][$key1]['sum']['source'][$key2])) {
                        $result['data'][$key1]['sum']['source'][$key2] = $data['data'][$key1]['sum']['source'][$key2];
                    } else {
                        $result['data'][$key1]['sum']['source'][$key2] += $data['data'][$key1]['sum']['source'][$key2];
                    }
                }
            }
            foreach ($data['data'][$key1]['categories'] as $catId => $dummy2) {
                // TOTALE RIGA PRINCIPALE (Edifici, attrezzature/impianti comunali)
                $result['data'][$key1]['categories'][$catId]['header']['sum'] += $data['data'][$key1]['categories'][$catId]['header']['sum'];
                // TOTALE CELLE PRINCIPALI
                foreach ($data['data'][$key1]['categories'][$catId]['sum'] as $sourceId => $dummy3) {
                    if (!empty($data['data'][$key1]['categories'][$catId]['sum'][$sourceId])) {
                        $result['data'][$key1]['categories'][$catId]['sum'][$sourceId] += $data['data'][$key1]['categories'][$catId]['sum'][$sourceId];
                    }
                }
                // VALORI SOTTO-CATEGORIE (quelle inserite dagli utenti)
                if (!empty($data['data'][$key1]['categories'][$catId]['sub_categories'])) {
                    foreach ($data['data'][$key1]['categories'][$catId]['sub_categories'] as $id => $dummy3) {
                        $result['data'][$key1]['categories'][$catId]['sub_categories'][$id] = $data['data'][$key1]['categories'][$catId]['sub_categories'][$id];
                        // Aggiungo nome comune
                        $result['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['name'] = self::concatNames($text, $result['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['name']);
                        // Evito modifica e cancellazione
                        $result['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['can_mod'] = false;
                        $result['data'][$key1]['categories'][$catId]['sub_categories'][$id]['header']['can_del'] = false;
                    }
                }
            }
        }

        // Produzione
        foreach ($data['production_data'] as $key1 => $dummy1) {
            foreach ($data['production_data'][$key1] as $id => $dummy2) {
                $result['production_data'][$key1][$id] = $data['production_data'][$key1][$id];
            }
        }
        // Somme produzione
        foreach (array('production_sum', 'production_emission_sum', 'production_emission_sum_factor') as $key0) {
            if (!empty($data[$key0]['tot'])) {
                if (empty($result[$key0]['tot'])) {
                    $result[$key0]['tot'] = $data[$key0]['tot'];
                } else {
                    $result[$key0]['tot'] += $data[$key0]['tot'];
                }
            }
            if (!empty($data[$key0]['category'])) {
                foreach ($data[$key0]['category'] as $key1 => $dummy2) {
                    if (empty($result[$key0]['category'][$key1])) {
                        $result[$key0]['category'][$key1] = $data[$key0]['category'][$key1];
                    } else {
                        $result[$key0]['category'][$key1] += $data[$key0]['category'][$key1];
                    }
                }
            }
        }
    }

    static private function concatNames($name1, $name2) {
        if ($name1 == $name2) {
            return $name1;
        }
        if ($name1 == '') {
            return $name2;
        }
        if (in_array($name2, array('', '-'))) {
            return $name1;
        }
        return "{$name1} - {$name2}";
    }

    static private function getTableSum($data) {
        $tot = 0;
        foreach ($data as $mainKey => $mainVal) {
            foreach ($mainVal['categories'] as $categoryVal) {
                if (isset($categoryVal['header']['total_only']) && $categoryVal['header']['total_only'] == 'T' && isset($categoryVal['header']['sum'])) {
                    // Immissioni CO2 inserite manualmente (es rifiuti della tabella B)
                    $tot += $categoryVal['header']['sum'];
                } else {
                    foreach ($categoryVal['sum'] as $val) {
                        $tot += $val;
                    }
                }
            }
        }
        return $tot;
    }

}
