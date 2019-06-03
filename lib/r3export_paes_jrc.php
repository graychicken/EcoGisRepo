<?php

class R3ExportPAESDriver_jrc extends R3ExportPAESDriver {

    protected $xls;
    protected $config;
    protected $objWorksheetStyle;  // style sheet
    protected $logger;

    /**
     * Return the template
     * @param string $templateFileName
     * @return PHPExcel 
     */
    protected function getTemplate($templateFileName) {
        $startTime = microtime(true);

        //require_once __DIR__ . '/PHPExcel/PHPExcel.php';
        //require_once __DIR__ . '/PHPExcel/PHPExcel/Cell/AdvancedValueBinder.php';
        PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_AdvancedValueBinder());

        $this->logger->log(LOG_INFO, 'getTemplate()');
        $this->logger->step(R3_PAES_READ_TEMPLATE, null, null);

        $this->xls = PHPExcel_IOFactory::load($templateFileName);
        $this->logger->log(LOG_INFO, sprintf('getTemplate() - Done [%.2fsec]', microtime(true) - $startTime));
    }

    public function extractTemplateConfig() {
        $startTime = microtime(true);

        $this->config = array();

        $this->logger->log(LOG_INFO, 'extractTemplateConfig()', 0);
        $this->logger->step(R3_PAES_READ_CONFIG, null, null);

        // Read settings for fixed cell
        $this->xls->setActiveSheetIndexByName('SETTINGS');
        $objWorksheet = $this->xls->getActiveSheet();
        $highestRow = $objWorksheet->getHighestRow();
        for ($row = 1; $row <= $highestRow; $row++) {
            $val = array();
            foreach (array('A', 'B', 'C') as $key => $col) {
                $val[$key] = $objWorksheet->getCell("{$col}{$row}")->getValue();
            }
            if ($val[0] <> '' && $val[1] <> '' && $val[2] <> '') {
                $this->config['settings'][$val[0]][$val[1]] = $val[2];
            }
        }

        // Read styles
        $this->xls->setActiveSheetIndexByName('STYLES');
        $this->objWorksheetStyle = $this->xls->getActiveSheet();
        $highestRow = $this->objWorksheetStyle->getHighestRow();
        $col = 'A';
        for ($row = 1; $row <= $highestRow; $row++) {
            $name = strtoupper($this->objWorksheetStyle->getCell("{$col}{$row}")->getValue());
            if ($name <> '') {
                $this->config['styles'][$name] = "{$col}{$row}";
            }
        }
        $this->logger->log(LOG_INFO, sprintf('extractTemplateConfig() - Done [%.2fsec]', microtime(true) - $startTime));
    }

    /**
     * Apply the style with the given name to a single cell or a cell range
     * @param string $styleName         the style name
     * @param string|array $cells       the cell or the cell interval
     */
    protected function applyStyle($objWorksheet, $styleName, $cells) {
        $startTime = microtime(true);

        $applyStyle = true;  // Development only (speedup xls creation)
        if (!$applyStyle) {
            return;
        }
        if (is_array($cells)) {
            if (!isset($cells['col_from'])) {
                $cells['col_from'] = $cells['col'];
                $cells['col_to'] = $cells['col'];
            }
            if (!isset($cells['row_from'])) {
                $cells['row_from'] = $cells['row'];
                $cells['row_to'] = $cells['row'];
            }
            $from = PHPExcel_Cell::stringFromColumnIndex($cells['col_from']) . $cells['row_from'];
            $to = PHPExcel_Cell::stringFromColumnIndex($cells['col_to']) . $cells['row_to'];
            $strCell = ($from == $to) ? $from : "{$from}:{$to}";
        } else {
            
        }

        $styleName = strtoupper($styleName);
        if (!isset($this->config['styles'][$styleName])) {
            throw new exception("Style \"{$styleName}\" not found");
        }
        $objWorksheet->duplicateStyle($this->objWorksheetStyle->getStyle($this->config['styles'][$styleName]), $strCell);
    }

    private function findCellForTag($searchTag) {
        $objWorksheet = $this->xls->getActiveSheet();
        $highestRow = $objWorksheet->getHighestRow();
        $highestCol = PHPExcel_Cell::columnIndexFromString($objWorksheet->getHighestColumn());
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 0; $col <= $highestCol; $col++) {
                if ($objWorksheet->getCellByColumnAndRow($col, $row)->getValue() === $searchTag) {
                    return PHPExcel_Cell::stringFromColumnIndex($col) . $row;
                }
            }
        }
        throw new exception("Tag \"{$searchTag}\" not found in sheet " . $objWorksheet->getTitle());
    }

    /**
     * Sum a value to the row part of a cell
     * @param string $cell            eg: B5
     * @param integer $increment      3
     * @return string                 B8
     */
    protected function incCellRow($cell, $increment) {
        list($col, $row) = PHPExcel_Cell::coordinateFromString($cell);
        $row += $increment;
        return "{$col}{$row}";
    }

    private function applyReplacement($data) {
        $startTime = microtime(true);
        $this->logger->log(LOG_INFO, 'applyReplacement()');
        $this->logger->step(R3_PAES_REPLACE, null, null);

        foreach (array('GENERAL', 'EMISSION_INVENTORY_1', 'EMISSION_INVENTORY_2', 'ACTION_PLAN') as $sheet) {
            if (!isset($this->config['settings'][$sheet])) {
                continue;
            }
            $this->xls->setActiveSheetIndexByName($sheet);
            $objWorksheet = $this->xls->getActiveSheet();
            foreach ($this->config['settings'][$sheet] as $field => $cell) {
                // searching field to replace into given data
                if (isset($data[$sheet]) && is_array($data[$sheet]['general'])) {
                    if (!array_key_exists($field, $data[$sheet]['general'])) {
                        throw new Exception("Missing column \"{$field}\" in data-sheet \"{$sheet}\"");
                        //continue;
                    }
                    $value = $data[$sheet]['general'][$field];
                    $objWorksheet->getCell($cell)->setValue($value);
                }
            }
        }
        $this->logger->log(LOG_INFO, sprintf('Reading configuration - Done [%.2fsec]', microtime(true) - $startTime));
    }

    public function removeUnusedTags() {
        $tags = array('TABLE_CONSUMPTION', 'TABLE_EMISSION', 'TABLE_ENERGY_PRODUCTION', 'TABLE_HEATH_PRODUCTION', 'TABLE_ACTION_PLAN');

        foreach ($this->xls->getSheetNames() as $idx => $sheetName) {
            $this->xls->setActiveSheetIndex($idx);
            foreach ($tags as $tag) {
                try {
                    $cell = $this->findCellForTag("<{$tag}>");
                    $objWorksheet = $this->xls->getActiveSheet();
                    $objWorksheet->getCell($cell)->setValue('');
                } catch (\Exception $e) {
                    
                }
            }
        }
    }

    protected function generateEmissionFactorRows($startCell, $data) {

        $objWorksheet = $this->xls->getActiveSheet();
        list($startCol, $row) = PHPExcel_Cell::coordinateFromString($startCell);
        $col = PHPExcel_Cell::columnIndexFromString($startCol);
        if (isset($data['CONSUMPTION']['rows']['sum']['source'])) {
            foreach ($data['CONSUMPTION']['rows']['sum']['source'] as $id => $consumption) {
                if (isset($data['EMISSION']['rows']['sum']['source'][$id])) {
                    $emission = $data['EMISSION']['rows']['sum']['source'][$id];
                    $factor = round($emission / $consumption, 3);
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $factor);
                }
                $col++;
            }
        }
    }

    protected function generateEmissionTableRows($startCell, $data, $opt = array()) {
        $startTime = microtime(true);
        $opt = array_merge(array('style' => false,
            'min_width' => 0,
            'insert_row' => true,
            'change_style' => true,
            'write_main_category' => true,
            'write_category' => true,
            'write_global_method' => false), $opt);
        $this->logger->log(LOG_INFO, "generateEmissionTableRows({$startCell})");

        list($startCol, $startRow) = PHPExcel_Cell::coordinateFromString($startCell);
        $startCol = PHPExcel_Cell::columnIndexFromString($startCol);

        // Calculate table height (returned by function)
        $height = 1;
        foreach ($data['data'] as $mainCategory) {
            if ($mainCategory['show_label'] == 'T') {
                $height += 2; // Category header + subtotal
            }
            $height += count($mainCategory['categories']);
        }
        $firstFixedColumnWidth = $opt['is_production'] ? 1 : 0;
        $row = $startRow;
        $col = $startCol - 1;
        $objWorksheet = $this->xls->getActiveSheet();
        if ($opt['insert_row']) {
            $objWorksheet->setCellValueByColumnAndRow($col, $row, '');      // Remove tag
            $objWorksheet->insertNewRowBefore($startRow + 1, $height + 1);  // Insert rows (Leave 1 row empty to prevent style apply)
        }
        // Calculating width
        $a = current($data['data']);
        $a = current($a['categories']);
        $width = count($a['sum']) + 2; // Description + total
        foreach ($data['data'] as $mainCategory) {
            // Header
            $col = $startCol - 1;
            $categoryStartRow = $row;
            if ($mainCategory['show_label'] == 'T') {
                if ($opt['write_main_category']) {
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $mainCategory['name']);
                    $objWorksheet->mergeCellsByColumnAndRow($col + 1, $row, $col + $width - 1, $row);
                    if (isset($mainCategory['options']['xls_style'])) {
                        $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style'], array('col_from' => $col, 'row_from' => $row, 'col_to' => $col + $width - 1, 'row_to' => $row));
                    }
                }
                $row++;
            }

            $productionWidth = $opt['is_production'] ? 2 : 0;

            if ($opt['change_style']) {
                // Apply default style to table header column
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_category'], array('col_from' => $startCol - 1, 'row_from' => $row, 'col_to' => $startCol - 1, 'row_to' => $row + count($mainCategory['categories']) - 1));
                // Apply default style to table data columns
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_category_data'], array('col_from' => $startCol, 'row_from' => $row, 'col_to' => $col + $width + $productionWidth - 2, 'row_to' => $row + count($mainCategory['categories']) - 1));
                // Apply default style to table sum columns
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_category_sum'], array('col_from' => $col + $width - 1, 'row_from' => $row, 'col_to' => $col + $width + $productionWidth - 1, 'row_to' => $row + count($mainCategory['categories']) - 1));
            }
            foreach ($mainCategory['categories'] as $catId => $category) {
                $col = $startCol - 1;
                if ($opt['write_category']) {
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $category['header']['name']);
                }
                $col = $col + 1 + $firstFixedColumnWidth;
                // echo "[{$category['header']['total_only']}]";
                if ($category['header']['total_only'] == 'T' && $opt['change_style']) {
                    $this->applyStyle($objWorksheet, 'DISABLED', array('col_from' => $startCol + $firstFixedColumnWidth, 'row_from' => $row, 'col_to' => $col + $width - 3, 'row_to' => $row));
                } else {
                    foreach ($category['sum'] as $value) {
                        if ($value <> '') {
                            // Tutte le tabelle: Valori songoli delle fonti
                            $objWorksheet->setCellValueByColumnAndRow($col, $row, R3LOCALE::convert2PHP($value));
                        }
                        $col++;
                    }
                }

                if ($opt['kind'] == 'CONSUMPTION' && $opt['write_global_method']) {
                    $objWorksheet->setCellValueByColumnAndRow($col + 1, $row, $category['header']['method']['title']);
                }

                if ($category['header']['total_only'] == 'T') {
                    $col = $col + $width - 2;
                }

                if (!$opt['is_production']) {
                    // Tabelle consumi/emissioni: Totale delle singole categorie (Colona R)
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $category['header']['sum']);
                } else {
                    if (isset($data['production_emission_sum']['category'][$catId])) {
                        $objWorksheet->setCellValueByColumnAndRow($col, $row, R3LOCALE::convert2PHP($data['production_emission_sum']['category'][$catId]));
                    }
                    if (isset($data['production_emission_sum_factor']['category'][$catId])) {
                        $objWorksheet->setCellValueByColumnAndRow($col + 1, $row, R3LOCALE::convert2PHP($data['production_emission_sum_factor']['category'][$catId]));
                    }
                }
                $row++;
            }

            // Sub total
            $col = $startCol - 1;
            if ($mainCategory['show_label'] == 'T') {
                $objWorksheet->setCellValueByColumnAndRow($col, $row, $mainCategory['sub_total_label']);
                // Style
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_sub_total_header'], array('col' => $col, 'row' => $row));
                $col = $col + 1 - $firstFixedColumnWidth;
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_sub_total_data'], array('col_from' => $col, 'col_to' => $col + $width - 3, 'row' => $row));
                $this->applyStyle($objWorksheet, $mainCategory['options']['xls_style_sub_total_data_sum'], array('col' => $col + $width - 2, 'row' => $row));

                if (isset($mainCategory['sum']['source'])) {
                    foreach ($mainCategory['sum']['source'] as $value) {
                        if ($value <> '') {
                            // Tabelle consumi/co2: totale parziale di categoria (Colonne C..Q)
                            $objWorksheet->setCellValueByColumnAndRow($col, $row, R3LOCALE::convert2PHP($value));
                        }
                        $col++;
                    }
                } else {
                    $col += $width - 2;
                }
                if (isset($mainCategory['sum']['total'])) {
                    // Tabelle consumi/co2: totale di categoria (Colonna R)
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, R3LOCALE::convert2PHP($mainCategory['sum']['total']));
                }

                // Sum style
                $col++;
                $row++;
            }

            // Apply category border
            $styleArray = array(
                'borders' => array(
                    'outline' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THICK,
                        'color' => array('rgb' => '000000'),
                    ),
                ),
            );
            $strColFrom = PHPExcel_Cell::stringFromColumnIndex($startCol - 1);
            $strColTo = PHPExcel_Cell::stringFromColumnIndex($col - 1);
            $borderRow = $row - 1;
            $objWorksheet->getStyle("{$strColFrom}{$categoryStartRow}:{$strColTo}{$borderRow}")->applyFromArray($styleArray);
        }

        if ($firstFixedColumnWidth > 0) {
            $row = $startRow;
            foreach ($data['production_sum'] as $mainCategory) {
                // Header
                $col = $startCol;
                if (is_array($mainCategory)) {
                    foreach ($mainCategory as $value) {
                        // Tabelle produzioni enegia prodotta localmente (Colonna C)
                        $objWorksheet->setCellValueByColumnAndRow($col, $row, $value);
                        $row++;
                    }
                    if (isset($data['production_sum']['tot'])) {
                        // Tabelle produzione: Totale colonna fissa
                        $objWorksheet->setCellValueByColumnAndRow($col, $row, $data['production_sum']['tot']);
                    }
                    $this->applyStyle($objWorksheet, 'TOTAL-DATA', array('col' => $col, 'row' => $row));
                    // Apply category border
                    $styleArray = array(
                        'borders' => array(
                            'outline' => array(
                                'style' => PHPExcel_Style_Border::BORDER_THICK,
                                'color' => array('rgb' => '000000'),
                            ),
                        ),
                    );
                    $strColFrom = PHPExcel_Cell::stringFromColumnIndex($col);
                    $strColTo = PHPExcel_Cell::stringFromColumnIndex($col);
                    $borderRow = $row;
                    $objWorksheet->getStyle("{$strColFrom}{$categoryStartRow}:{$strColTo}{$borderRow}")->applyFromArray($styleArray);
                }
            }
        }

        // Table total
        if (isset($data['sum'])) {
            $col = $startCol - 1;
            $objWorksheet->setCellValueByColumnAndRow($col, $row, $data['sum']['label']);
            $this->applyStyle($objWorksheet, 'TOTAL-HEADER',
                    array('col' => $col, 'row' => $row));
            $col = $col + 1 + $firstFixedColumnWidth;

            $this->applyStyle($objWorksheet, 'TOTAL-DATA', array('col_from' => $col, 'col_to' => $col + $width - 3, 'row' => $row));
            $this->applyStyle($objWorksheet, 'TOTAL-DATA-SUM', array('col' => $col + $width - 2, 'row' => $row));


            $styleName = 'TOTAL-DATA';
            if (isset($data['sum']['source'])) {
                foreach ($data['sum']['source'] as $value) {
                    if ($value <> '') {
                        // Tutte le tabelle: Totale di tabella per fonte
                        $objWorksheet->setCellValueByColumnAndRow($col, $row, $value);
                    }
                    $col++;
                }
            }
            // Tutte le tabelle: Totalone (Colonna R)
            $objWorksheet->setCellValueByColumnAndRow($col, $row, $data['sum']['total']);
            $row++;
        }

        // Totale CO2(Colonna O tabella C e D)
        if ($opt['is_production']) {
            $objWorksheet->setCellValueByColumnAndRow($col, $row - 1, R3LOCALE::convert2PHP($data['production_emission_sum']['tot']));
        }

        // Apply border to 1st column
        $styleArray = array(
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THICK,
                    'color' => array('rgb' => '000000'),
                ),
            ),
        );
        $strColTo = PHPExcel_Cell::stringFromColumnIndex($startCol - 1);
        $borderRow = $row - 1;
        $objWorksheet->getStyle("{$startCell}:{$strColTo}{$borderRow}")->applyFromArray($styleArray);
        $strColTo = PHPExcel_Cell::stringFromColumnIndex(max($col - 1, $opt['min_width'] + $startCol - 2));
        $objWorksheet->getStyle("{$startCell}:{$strColTo}{$borderRow}")->applyFromArray($styleArray);

        // Apply border to the table
        if ($opt['insert_row']) {
            $objWorksheet->removeRow($row);
        }

        $this->logger->log(LOG_INFO, sprintf("generateEmissionTableRows({$startCell}) - Done [%.2fsec]", microtime(true) - $startTime));
        return array('width' => $width, 'height' => $height);
    }

    public function generateEmissionTableHeader($startCell, $data, $opt = array()) {
        $startTime = microtime(true);

        $opt = array_merge(array('style' => false), $opt);
        $this->logger->log(LOG_INFO, "generateEmissionTableHeader($startCell)", 0);

        list($startCol, $startRow) = PHPExcel_Cell::coordinateFromString($startCell);
        $startCol = PHPExcel_Cell::columnIndexFromString($startCol);

        // Calculate table height (returned by function)
        $height = 0;
        foreach ($data as $key => $headerLine) {
            if (substr($key, 0, 4) == 'line') {
                $height++;
            }
        }
        $firstFixedColumnWidth = isset($data['first_fixed_column']) && is_array($data['first_fixed_column']) ? 1 : 0;
        $row = $startRow;
        $col = $startCol - 1;
        $objWorksheet = $this->xls->getActiveSheet();
        $objWorksheet->setCellValueByColumnAndRow($col, $row, '');      // Remove tag
        $objWorksheet->insertNewRowBefore($startRow - 1, $height - 1);  // Insert row
        $width = 0;                                                     // Table width
        $drawnCells = 0;
        foreach ($data as $key => $headerLine) {
            if ($key == 'first_fixed_column') {
                $row = $startRow;
            }
            if (is_array($headerLine)) {
                if ($drawnCells <> 0 && $key <> 'first_fixed_column') {
                    // Se ho disegnato categorie e ho prima fissa
                    $col += $firstFixedColumnWidth;
                }
                foreach ($headerLine as $headerData) {
                    while (isset($rowSpan[$col][$row])) {
                        $col++;
                    }
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $headerData['label']);

                    // 1. apply span
                    if ((isset($headerData['rowspan']) && $headerData['rowspan'] > 1) ||
                            (isset($headerData['colspan']) && $headerData['colspan'] > 1)) {
                        $colFrom = $col;
                        $rowFrom = $row;
                        $colTo = $col + $headerData['colspan'] - 1;
                        $rowTo = $row + $headerData['rowspan'] - 1;
                        $objWorksheet->mergeCellsByColumnAndRow($col, $row, $colTo, $rowTo);
                        if ($headerData['rowspan'] > 1) {
                            for ($i = 0; $i < $headerData['rowspan']; $i++) {
                                $rowSpan[$col][$row + $i] = true;
                            }
                        }
                        if ($headerData['colspan'] > 1) {
                            $col += $headerData['colspan'] - 1;
                        }
                    } else {
                        $colFrom = $col;
                        $rowFrom = $row;
                        $colTo = $col;
                        $rowTo = $row;
                    }

                    // 2. apply height
                    if (isset($headerData['options']['xls_height'])) {
                        $objWorksheet->getRowDimension($row)->setRowHeight($headerData['options']['xls_height']);
                    }

                    if ($drawnCells == 0) {
                        // Se ho disegnato categorie e ho prima fissa
                        $col += $firstFixedColumnWidth;
                    }
                    if ($col - $startCol > $width) {
                        $width = $col - $startCol;
                    }
                    $col++;
                    $drawnCells++;
                }
                $row++;
                $col = $startCol + $firstFixedColumnWidth - 1;
            }
        }
        // Apply style to all the table header
        $this->applyStyle($objWorksheet, $headerData['options']['xls_style'], array('col_from' => $startCol - 1, 'row_from' => $startRow, 'col_to' => $startCol + $width, 'row_to' => $startRow + $height - 1));

        // Header border
        $endCol = PHPExcel_Cell::stringFromColumnIndex($startCol + $width);
        $endRow = $startRow + $height - 1;
        $styleArray = array(
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THICK,
                    'color' => array('rgb' => '000000'),
                ),
            ),
        );
        $objWorksheet->getStyle("{$startCell}:{$endCol}{$endRow}")->applyFromArray($styleArray);
        $endRow = $startRow + $height - 1;
        $strColTo = PHPExcel_Cell::stringFromColumnIndex($startCol - 1);
        $objWorksheet->getStyle("{$startCell}:{$strColTo}{$endRow}")->applyFromArray($styleArray);
        if ($firstFixedColumnWidth > 0) {
            $strColTo = PHPExcel_Cell::stringFromColumnIndex($startCol);
            $objWorksheet->getStyle("{$startCell}:{$strColTo}{$endRow}")->applyFromArray($styleArray);
        }
        $this->logger->log(LOG_INFO, sprintf("generateEmissionTableHeader({$startCell}) - Done [%.2fsec]", microtime(true) - $startTime));
        return array('width' => $width + 2, 'height' => $height);
    }

    public function generateEmissionTable($emissionNo, $emissionData) {
        $this->xls->setActiveSheetIndexByName("EMISSION_INVENTORY_{$emissionNo}");
        $objWorksheet = $this->xls->getActiveSheet();
        $inventoryTableKinds = array('CONSUMPTION', 'EMISSION', 'ENERGY_PRODUCTION', 'HEATH_PRODUCTION');

        // Presi da tag su excel
        // Get tag before modifing table (?)
        $startCell = array();
        foreach ($inventoryTableKinds as $kind) {
            // Header
            $startCell[$kind] = $this->findCellForTag("<TABLE_{$kind}>");
        }

        $tablesHeight = 0;  // Somma altezza tabelle
        foreach ($inventoryTableKinds as $kind) {
            $isProduction = in_array($kind, array('ENERGY_PRODUCTION', 'HEATH_PRODUCTION'));
            $this->logger->step(R3_PAES_EMISSION_TABLE, $kind, $emissionNo);
            // Header
            $startCell[$kind] = $this->incCellRow($startCell[$kind], $tablesHeight);

            $headerResult = $this->generateEmissionTableHeader($startCell[$kind], $emissionData[$kind]['header'], array('style' => true));
            $startCell[$kind] = $this->incCellRow($startCell[$kind], $headerResult['height']);
            $tablesHeight += $headerResult['height'];
            $headerWidth = $headerResult['width'];

            $headerResult = $this->generateEmissionTableRows($startCell[$kind], $emissionData[$kind]['rows'], array('kind' => $kind, 'style' => false, 'min_width' => $headerWidth, 'is_production' => $isProduction));
            $tablesHeight += $headerResult['height'] - 1;
            if ($kind == 'EMISSION') {
                // Fattori di conversione
                $emissionFactorStartCell = $this->incCellRow($startCell[$kind], $headerResult['height'] + 1);
                $headerResult = $this->generateEmissionFactorRows($emissionFactorStartCell, $emissionData);
            }
        }
    }

    protected function generateActionPlanTableRows($startCell, $globalPlanData, $opt = array()) {
        $startTime = microtime(true);
        $this->logger->log(LOG_INFO, "generateActionPlanTableRows($startCell)", 0);
        $this->logger->step(R3_PAES_ACTION_PLAN_TABLE, null, null);

        $opt = array_merge(array('style' => false, 'insert_row' => true), $opt);
        $mainCategoryColSpan = 13;
        $styleArray = array(
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THICK,
                    'color' => array('rgb' => '000000'),
                ),
            ),
        );

        $dataFields = array('name' => array('type' => 'text', 'col_span' => 4),
            'responsible_department' => array('type' => 'text', 'col_span' => 2),
            'start_date' => array('type' => 'date'),
            'end_date' => array('type' => 'date'),
            'estimated_cost' => array('type' => 'currency', 'col_span' => 2),
            'expected_energy_saving' => array('type' => 'float'),
            'expected_renewable_energy_production' => array('type' => 'float'),
            'expected_co2_reduction' => array('type' => 'float'),
        );

        list($startCol, $startRow) = PHPExcel_Cell::coordinateFromString($startCell);
        $startCol = PHPExcel_Cell::columnIndexFromString($startCol);

        // Calculate table height (returned by function)
        $height = 1; // 1->Total; Header in template
        foreach ($globalPlanData['data'] as $mainCategory) {
            $height++;
            foreach ($mainCategory['categories'] as $category) {
                foreach ($category['data'] as $dummy) {
                    $height++;
                }
            }
        }

        //echo "[height={$height}]";
        $row = $startRow;
        $col = $startCol - 1;
        $objWorksheet = $this->xls->getActiveSheet();
        if ($opt['insert_row']) {
            $objWorksheet->setCellValueByColumnAndRow($col, $row, '');      // Remove tag
            $objWorksheet->insertNewRowBefore($startRow + 1, $height);  // Insert row (next to header to prevent header style to be copied)
        }
        // Apply default style to table header column
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-HEADER', array('col_from' => $startCol - 1, 'row_from' => $row, 'col_to' => $startCol - 1, 'row_to' => $row + $height - 2));
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-TEXT', array('col_from' => $startCol, 'row_from' => $row, 'col_to' => $startCol + 5, 'row_to' => $row + $height - 2));
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-DATE', array('col_from' => $startCol + 6, 'row_from' => $row, 'col_to' => $startCol + 7, 'row_to' => $row + $height - 2));
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-CURRENCY', array('col_from' => $startCol + 8, 'row_from' => $row, 'col_to' => $startCol + 9, 'row_to' => $row + $height - 2));
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-NUMBER', array('col_from' => $startCol + 10, 'row_from' => $row, 'col_to' => $startCol + 14, 'row_to' => $row + $height - 2));
        $this->applyStyle($objWorksheet, 'ACTION-CATEGORY-SUM', array('col_from' => $startCol + 15, 'row_from' => $row, 'col_to' => $startCol + 15, 'row_to' => $row + $height - 2));

        foreach ($globalPlanData['data'] as $mainCategory) {
            $this->logger->step(R3_PAES_ACTION_PLAN_TABLE, null, null);

            $mainCategoryStartRow = $row;
            $objWorksheet->setCellValueByColumnAndRow($col, $row, $mainCategory['name']);
            // Copy style
            $this->applyStyle($objWorksheet, 'ACTION-MAINCATEGORY-HEADER', array('col_from' => $col, 'row_from' => $row, 'col_to' => $col + $mainCategoryColSpan, 'row_to' => $row));
            $col += $mainCategoryColSpan;

            $objWorksheet->setCellValueByColumnAndRow($col + 1, $row, $mainCategory['sum']['expected_energy_saving']);
            $objWorksheet->setCellValueByColumnAndRow($col + 2, $row, $mainCategory['sum']['expected_renewable_energy_production']);
            $objWorksheet->setCellValueByColumnAndRow($col + 3, $row, $mainCategory['sum']['expected_co2_reduction']);
            $row++;
            $col = $startCol - 1;

            $startRow = $row;
            foreach ($mainCategory['categories'] as $category) {
                if ($category['name'] <> '') {
                    $objWorksheet->setCellValueByColumnAndRow($col, $row, $category['name']);
                }
                $rowSpan = count($category['data']);
                $objWorksheet->mergeCellsByColumnAndRow($col, $row, $col, $row + $rowSpan - 1);
                foreach ($category['data'] as $data) {
                    $col = $startCol;
                    foreach ($dataFields as $field => $typeDef) {
                        $type = $typeDef['type'];
                        $span = isset($typeDef['col_span']) ? $typeDef['col_span'] : 1;
                        if ($type == 'date') {
                            if ($data[$field] <> '') {
                                $a = explode('-', $data[$field]);
                                $time = gmmktime(0, 0, 0, $a['1'], $a['2'], $a['0']);
                                $objWorksheet->setCellValueByColumnAndRow($col, $row, PHPExcel_Shared_Date::PHPToExcel($time));
                            }
                        } else {
                            if ($data[$field] <> '') {
                                $objWorksheet->setCellValueByColumnAndRow($col, $row, $data[$field]);
                            }
                        }
                        if ($span > 1) {
                            $objWorksheet->mergeCellsByColumnAndRow($col, $row, $col + $span - 1, $row);
                            $col += $span;
                        } else {
                            $col++;
                        }
                    }
                    $row++;
                }
                $col = $startCol - 1;
            }

            $this->applyStyle($objWorksheet, 'DISABLED', array('col_from' => $startCol + 13, 'row_from' => $startRow, 'col_to' => $startCol + 15, 'row_to' => $row - 1));
            // Category border
            $strColFrom = PHPExcel_Cell::stringFromColumnIndex($startCol - 1);
            $strColTo = PHPExcel_Cell::stringFromColumnIndex($startCol + 15);
            $rowTo = $row - 1;
            $objWorksheet->getStyle("{$strColFrom}{$mainCategoryStartRow}:{$strColFrom}{$rowTo}")->applyFromArray($styleArray);  // Category border
            $objWorksheet->getStyle("{$strColFrom}{$mainCategoryStartRow}:{$strColTo}{$rowTo}")->applyFromArray($styleArray);    // Full border
            $strColFrom = PHPExcel_Cell::stringFromColumnIndex($startCol + 13);
            $objWorksheet->getStyle("{$strColFrom}{$mainCategoryStartRow}:{$strColTo}{$rowTo}")->applyFromArray($styleArray);    // Last border
        }

        $col = $startCol + 10;
        $objWorksheet->setCellValueByColumnAndRow($col, $row, _('Totale'));
        $objWorksheet->mergeCellsByColumnAndRow($col, $row, $col + 2, $row);
        $this->applyStyle($objWorksheet, 'ACTION-TOTAL-TEXT', array('col_from' => $col, 'row_from' => $row, 'col_to' => $col + 2, 'row_to' => $row));
        // Border (Totale)
        $strColFrom = PHPExcel_Cell::stringFromColumnIndex($col);
        $strColTo = PHPExcel_Cell::stringFromColumnIndex($col + 2);
        $objWorksheet->getStyle("{$strColFrom}{$row}:{$strColTo}{$row}")->applyFromArray($styleArray);

        $col += 3;
        $objWorksheet->setCellValueByColumnAndRow($col + 0, $row, $globalPlanData['sum']['expected_energy_saving']);
        $objWorksheet->setCellValueByColumnAndRow($col + 1, $row, $globalPlanData['sum']['expected_renewable_energy_production']);
        $this->applyStyle($objWorksheet, 'ACTION-TOTAL-NUMBER', array('col_from' => $col, 'row_from' => $row, 'col_to' => $col + 1, 'row_to' => $row));
        $objWorksheet->setCellValueByColumnAndRow($col + 2, $row, $globalPlanData['sum']['expected_co2_reduction']);
        $this->applyStyle($objWorksheet, 'ACTION-TOTAL-SUM', array('col_from' => $col + 2, 'row_from' => $row, 'col_to' => $col + 2, 'row_to' => $row));

        // Border (data)
        $strColFrom = PHPExcel_Cell::stringFromColumnIndex($col);
        $strColTo = PHPExcel_Cell::stringFromColumnIndex($col + 2);
        $objWorksheet->getStyle("{$strColFrom}{$row}:{$strColTo}{$row}")->applyFromArray($styleArray);
        $objWorksheet->getStyle("{$strColTo}{$row}:{$strColTo}{$row}")->applyFromArray($styleArray);

        $this->logger->log(LOG_INFO, sprintf("generateActionPlanTableRows({$startCell}) - Done [%.2fsec]", microtime(true) - $startTime));
    }

    public function generateGlobalPlanTable($globalPlanData) {
        $this->xls->setActiveSheetIndexByName("ACTION_PLAN");
        $objWorksheet = $this->xls->getActiveSheet();

        $startCell = $this->findCellForTag("<TABLE_ACTION_PLAN>");

        $this->generateActionPlanTableRows($startCell, $globalPlanData);
    }

    protected function setMetadata($data) {
        if (isset($data['cretor'])) {
            $this->xls->getProperties()->setCreator($data['cretor']);
        }
        if (isset($data['title'])) {
            $this->xls->getProperties()->setCreator($data['title']);
        }
    }

    protected function setSheetName($data) {
        foreach ($this->xls->getSheetNames() as $idx => $sheetName) {
            $sheets[$sheetName] = $idx;
        }
        foreach ($data as $oldName => $newName) {
            if (isset($sheets[$oldName])) {
                $this->xls->setActiveSheetIndexByName($oldName);
                $objWorksheet = $this->xls->getActiveSheet();
                $objWorksheet->setTitle($newName);
            }
        }
    }

    protected function removeUnusedSheet($opt) {
        $sheets = array();
        foreach ($this->xls->getSheetNames() as $idx => $sheetName) {
            $sheets[$sheetName] = $idx;
        }
        // Reverse order delete!
        $this->xls->removeSheetByIndex($sheets['SETTINGS']);
        $this->xls->removeSheetByIndex($sheets['STYLES']);
        if (!isset($opt["GLOBAL_PLAN"]) && isset($sheets["ACTION_PLAN"])) {
            $this->xls->removeSheetByIndex($sheets["ACTION_PLAN"]);
        }
        for ($emissionNo = 2; $emissionNo >= 1; $emissionNo--) {
            if (!isset($opt["EMISSION_INVENTORY_{$emissionNo}"])) {
                $this->xls->removeSheetByIndex($sheets["EMISSION_INVENTORY_{$emissionNo}"]);
            }
        }
    }

    protected function save($outputFileName, array $opt) {
        $startTime = microtime(true);
        $this->logger->log(LOG_INFO, "save({$outputFileName})");
        $this->logger->step(R3_PAES_SAVE, null, null);
        $ext = strtolower(strrchr($outputFileName, '.'));
        switch ($ext) {
            case '.xls':
                $xlsOut = new PHPExcel_Writer_Excel5($this->xls);
                break;
            case '.xlsx':
                $xlsOut = new PHPExcel_Writer_Excel2007($this->xls);
                break;
            default:
                throw new Exception("Unsupported output file format \"{$ext}\"");
        }
        $xlsOut->save($outputFileName);
        $this->logger->log(LOG_INFO, sprintf("save({$outputFileName}) - Done [%.2fsec]", microtime(true) - $startTime));
    }

    public function export($outputFileName, $templateFileName, array $opt) {

        $totSteps = 5; // Base steps
        for ($emissionNo = 1; $emissionNo <= 2; $emissionNo++) {
            if (isset($opt["EMISSION_INVENTORY_{$emissionNo}"])) {
                $totSteps += 4;  // 1 step per table
            }
        }
        if (isset($opt["GLOBAL_PLAN"])) {
            $totSteps += 1 + count($opt["GLOBAL_PLAN"]['data']);
        }
        $this->logger = $opt['logger'];
        $this->logger->log(LOG_INFO, 'Exporting');
        $this->logger->initProgress($totSteps);

        $this->getTemplate($templateFileName);

        $this->extractTemplateConfig();

        $this->applyReplacement($opt);
        for ($emissionNo = 1; $emissionNo <= 2; $emissionNo++) {
            if (isset($opt["EMISSION_INVENTORY_{$emissionNo}"])) {
                $this->generateEmissionTable($emissionNo, $opt["EMISSION_INVENTORY_{$emissionNo}"]);
            }
        }
        if (isset($opt["GLOBAL_PLAN"])) {
            $this->generateGlobalPlanTable($opt["GLOBAL_PLAN"]);
        }

        $this->logger->step(R3_PAES_FINALYZE, null, null);

        // Remove ununsed sheet
        $this->removeUnusedSheet($opt);

        // Apply metadata
        $this->setMetadata($opt["METADATA"]);

        // Elimina eventuali tag non sostituiti
        $this->removeUnusedTags();

        // Rename the sheets
        $this->setSheetName($opt["SHEET-NAME"]);

        // Imposta il cursore sul primo foglio, primo campo
        $this->xls->setActiveSheetIndex(0);
        $objWorksheet = $this->xls->getActiveSheet();
        $objWorksheet->getCell('B2')->setValue('.');
        $objWorksheet->getCell('B2')->setValue('');

        $this->save($outputFileName, $opt);
        $this->logger->step(R3_PAES_DONE, null, null);
    }

}
