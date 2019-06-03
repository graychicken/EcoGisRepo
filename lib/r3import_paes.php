<?php

//require_once __DIR__ . '/PHPExcel/PHPExcel.php';
//require_once __DIR__ . '/PHPExcel/PHPExcel/IOFactory.php';
//require_once __DIR__ . '/PHPExcel/PHPExcel/Writer/Excel5.php';

require_once R3_LIB_DIR . 'logger.php';

class R3ImportSEAPLogger implements LoggerInterface {

    private $opt;
    private $logs = array();

    public function emergency($message, array $context = array()) {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = array()) {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = array()) {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = array()) {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = array()) {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = array()) {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = array()) {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = array()) {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = array()) {
        if ($this->opt['output']) {
            echo "$level {$message}\n";
        }
        $this->logs[] = array('level' => $level, 'message' => $message);
    }

    public function getLogs() {
        return $this->logs;
    }

    public function clearLogs() {
        $this->logs = array();
    }

    public function __construct(array $opt = array()) {
        $defOpt = array('output' => false);
        $this->opt = array_merge($defOpt, $opt);
    }

}

class R3ImportSEAP implements LoggerAwareInterface {

    private $logger;
    private $db;
    private $lang = 1;
    private $istat;
    private $mu_id;
    private $do_id;
    private $mu_name;
    private $xls;
    private $data;
    private $currentSheet;
    private $currentInventory;
    private $efe;
    private $seapVersion;
    private $gst_id;
    private $shetIndex = array('general' => null, 'inventory' => array(1 => null, 2 => null), 'seap' => null);
    private $consumptionSources = array(
        'C' => '80ca53bf-10de-48dc-b5e9-e20dfdaca697', // Electricity
        'D' => '8972ff33-204f-492a-8428-f5a4512b4d01', // Riscaldamento/raffrescamento
        'E' => '8c62803e-df3a-4972-bbd7-8e81f7532a9c', // Gas naturale
        'F' => '883987e4-2c56-4cff-ad32-2cc6a2ed2d01', // GPL
        'G' => '8a8f83e1-4d71-4c41-b7f1-09d59f1daa8e', // Olio combustibile
        'H' => '83f137f7-f0f7-4965-9979-63b81912bd79', // Gasolio
        'I' => '88d6270c-f212-4279-b787-ab9a32fca4e9', // Benzina
        'J' => '8755b537-1263-4d00-8aec-29b3d03b1b11', // Lignite
        'K' => '8115e1ea-a1d9-49b4-8ae6-3053620bfb94', // Carbone
        'L' => '8ba42b1a-70e9-4cb4-aa0d-a97bb3204efe', // Altri combustibili fossili
        'M' => '8ec46068-c2ec-47cb-bc6b-3d110cf8f8cb', // Olio vegetale
        'N' => '8fbbf117-9b34-4f8c-a051-ad585c6caa5e', // Bio carburanti
        'O' => '8deb9302-52a1-4614-88f6-a63db231544a', // Altre biomasse
        'P' => '8176150f-f27e-48d6-bc07-889778303f7d', // Energia solare termica
        'Q' => '824f6af0-5007-49fb-970b-96a1c458dc52' // Energia geotermica
    );
    private $electricityProductionSources = array(
        'D' => '8c62803e-df3a-4972-bbd7-8e81f7532a9c', // Gas naturale
        'E' => '883987e4-2c56-4cff-ad32-2cc6a2ed2d01', // GPL
        'F' => '8a8f83e1-4d71-4c41-b7f1-09d59f1daa8e', // Olio combustibile
        'G' => '8755b537-1263-4d00-8aec-29b3d03b1b11', // Lignite
        'H' => '8115e1ea-a1d9-49b4-8ae6-3053620bfb94', // Carbone
        'I' => '8adb68dc-97fb-4eb6-8f85-24b2b176dcc8', // Vapore
        'J' => '8ba87cc6-2ded-4020-9620-ed2bfeea514d', // Rifiuti
        'K' => '82f1aa34-0021-4498-8a6b-c45c640625dc', // Olio vegetale
        'L' => '8ac7b805-a077-46a1-8430-5646b6cd75af', // Altre biomasse
        'M' => '8dd1bdb9-32e5-47db-9499-2921aef4606c', // Altre fonti rinnovabili
        'N' => '8dcee564-054b-4863-9614-10bd13c58e8d'  // Altro
    );
    private $heatingProductionSources = array(
        'D' => '8c62803e-df3a-4972-bbd7-8e81f7532a9c', // Gas naturale
        'E' => '883987e4-2c56-4cff-ad32-2cc6a2ed2d01', // GPL
        'F' => '8a8f83e1-4d71-4c41-b7f1-09d59f1daa8e', // Olio combustibile
        'G' => '8755b537-1263-4d00-8aec-29b3d03b1b11', // Lignite
        'H' => '8115e1ea-a1d9-49b4-8ae6-3053620bfb94', // Carbone
        'I' => '8ba87cc6-2ded-4020-9620-ed2bfeea514d', // Rifiuti
        'J' => '82f1aa34-0021-4498-8a6b-c45c640625dc', // Olio vegetale
        'K' => '8ac7b805-a077-46a1-8430-5646b6cd75af', // Altre biomasse
        'L' => '8dd1bdb9-32e5-47db-9499-2921aef4606c', // Altre fonti rinnovabili
        'M' => '8dcee564-054b-4863-9614-10bd13c58e8d'  // Altro
    );
    private $consumptionCategories = array(
        'M11' => '8185020c-452a-43a4-b96a-8dd89b035fbf', // Edifici, attrezzature/impianti comunali
        'M12' => '828db6af-d252-4044-ad7b-dac20a7781c2', // Edifici, attrezzature/impianti del terziario (non comunali)
        'M13' => '80639261-981a-489a-8516-93cb065c0bac', // Edifici residenziali
        'M14' => '80cd98b4-b3d7-47f3-b757-709defc244a1', // Illuminazione pubblica comunale
        'M15' => '86ae0351-5bef-4440-8198-ee280878d946', // Industrie (esclusi i soggetti contemplati nel Sistema europeo di scambio delle quote di emissione-ETS) 
        'M31' => '8292e63e-65f6-4504-986f-ca8b649c7d8f', // Parco veicoli comunale
        'M32' => '8928e743-1da2-44a0-ba5f-79df2ed86fe1', // Trasporti pubblici
        'M33' => '84dac5bd-ed61-414f-baa1-fe00651464a6', // Trasporti privati e commerciali
    );
    private $emissionExtraCategories = array(
        'M91' => '875eae5f-79a0-457b-81a6-6b377a142cfa', // Smaltimento dei rifiuti
        'M92' => '822bd921-bd05-45cb-9c09-1f1535009568', // Gestione delle acque reflue
        'M93' => '861eaf25-9f7d-427d-bdb8-00bad1d4fc3a'  // Altro
    );
    private $electricityProductionCategories = array(
        'M111' => '85a3088b-85d8-4ee0-ac35-28d849f5536b', // Energia eolica
        'M112' => '8bb788a4-1076-465c-ac51-e88212e559a9', // Energia idroelettrica
        'M113' => '8e8f54fc-e723-4b18-ac35-5b6d72fdb29b', // Fotovoltaico
        'M114' => '859a41c0-1e20-49c2-b661-d15e19875404', // Cogenerazione di energia elettrica e termica
        'M115' => '88ca6129-b8a3-4b19-99b1-c90af1ffbf6b'  // Altro
    );
    private $heatingProductionCategories = array(
        'M131' => '8ffcfadd-6a65-4b6e-8219-e3f36fed6ed8', // Cogenerazione di energia elettrica e termica
        'M132' => '8225cc3b-7f74-44e4-96fe-ebc0e40b92cc', // Impianto(i) di teleriscaldamento/teleraffrescamento
        'M135' => '81d047cb-ca81-432e-9b6d-5c9730b97337'  // Altro
    );

    public function __construct(PDO $db, $istat) {
        $this->db = $db;
        $this->istat = $istat;
        $stmt = $this->db->prepare("SELECT mu_id, do_id, mu_name_{$this->lang} AS mu_name "
                . "FROM ecogis.municipality "
                . "WHERE mu_istat=?");
        $stmt->execute(array($istat));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data === false) {
            throw new exception("Municipality with istat \"{$istat}\" not found");
        }
        if ($data['do_id'] == null) {
            throw new exception("Municipality not active on this installation");
        }
        $this->mu_id = $data['mu_id'];
        $this->do_id = $data['do_id'];
        $this->mu_name = $data['mu_name'];
    }

    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    private function decodeSheetIndex() {
        $sheetCount = $this->xls->getSheetCount();
        for ($i = 0; $i < $sheetCount; $i++) {
            $this->xls->setActiveSheetIndex($i);
            $marker = $this->xls->getActiveSheet()->getCell('A1')->getCalculatedValue();
            switch ($marker) {
                case 'SG-10':
                    $this->shetIndex['general'] = $i;
                    break;
                case 'PB1-10':
                    $this->shetIndex['inventory'][1] = $i;
                    break;
                case 'PB2-10':
                    $this->shetIndex['inventory'][2] = $i;
                    break;
                case 'PS-10':
                    $this->seapVersion = 1.0;
                    $this->shetIndex['seap'] = $i;
                    break;
                case 'PS-11':
                    $this->seapVersion = 1.1;  // EcoGis
                    $this->shetIndex['seap'] = $i;
                    break;
            }
        }
    }

    private function getSheetIndex($name, $i = null) {
        if ($i === null) {
            return $this->shetIndex[$name];
        }
        return $this->shetIndex[$name][$i];
    }

    private function checkNumber($value, $defValue = null, $errorText = null) {
        $value = trim($value);
        if ($value == '') {
            return $defValue;
        }
        if (!is_numeric($value)) {
            if ($errorText <> '') {
                $this->logger->warning("{$this->currentSheet}: {$errorText} [$value]");
            }
            return $defValue;
        }
        return (float) $value;
    }

    private function getCellByMarker($marker) {
        static $data;

        if (empty($data[$this->currentSheet])) {
            $sheet = $this->xls->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            for ($i = 1; $i <= $highestRow; $i++) {
                $val = $sheet->getCellByColumnAndRow('A', $i)->getCalculatedValue();
                if (substr($val, 0, 1) == 'M') {
                    $data[$this->currentSheet][$val] = $i;
                }
            }
        }
        if (isset($data[$this->currentSheet][$marker])) {
            return $data[$this->currentSheet][$marker];
        }
        $this->logger->warning(_("Il marcatore \"{$marker}\" non è stato trovato"));
        return null;
    }

    private function getEnergySourceIdByUUID($uuid) {
        static $data;

        if (empty($data[$uuid])) {
            $data = $this->db->query("SELECT ges_uuid, ges_id FROM ecogis.global_energy_source")->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
        }
        if (isset($data[$uuid])) {
            return $data[$uuid];
        }
        throw new Exception("UUID {$uuid} not found in enegy source");
    }

    private function getGlobalCategoryIdByUUID($uuid) {
        static $data;

        if (empty($data[$uuid])) {
            $data = $this->db->query("SELECT gc_uuid, gc_id FROM ecogis.global_category WHERE gc_parent_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
        }
        if (isset($data[$uuid])) {
            return $data[$uuid];
        }
        throw new Exception("UUID {$uuid} not found in global category");
    }

    private function getGlobalCategories($type) {
        switch ($type) {
            case 'consumption':
                return $this->consumptionCategories;
            case 'emission':
                // Translate marker (add 40)
                $result = array();
                foreach ($this->consumptionCategories as $key => $val) {
                    $key = substr($key, 0, 1) . (substr($key, 1) + 40);
                    $result[$key] = $val;
                }
                return $result;
            case 'electricity_production':
                return $this->electricityProductionCategories;
            case 'heating_production':
                return $this->heatingProductionCategories;
            default:
                throw new Exception("Unknown type \"$type\"");
        }
    }

    private function getGlobalSources($type) {
        switch ($type) {
            case 'consumption':
            case 'emission':
                return $this->consumptionSources;
            case 'electricity_production':
                return $this->electricityProductionSources;
            case 'heating_production':
                return $this->heatingProductionSources;
            default:
                throw new Exception("Unknown type \"$type\"");
        }
        //
    }

    protected function importGlobalStrategy() {
        $sheetName = $this->currentSheet = 'general';
        $sheetIndex = $this->getSheetIndex($this->currentSheet);
        if ($sheetIndex === null) {
            $this->logger->warning("Missing global strategy sheet");
            return false;
        }
        $this->logger->debug("Importing global strategy sheet");
        $data = $this->data[$sheetName] = array();
        $this->xls->setActiveSheetIndex($sheetIndex);
        $sheet = $this->xls->getActiveSheet();
        $data['gst_reduction_target'] = $this->checkNumber($sheet->getCell('D6')->getCalculatedValue(), null, _('Target di riduzione deve essere numerico'));
        $data['gst_reduction_target_year'] = $this->checkNumber($sheet->getCell('F6')->getCalculatedValue(), 2020, _('Anno target di riduzione deve essere numerico'));
        $data['gst_reduction_target_absolute'] = stripos($sheet->getCell('D8')->getCalculatedValue(), 'solut') !== false; // [ASSOLUTO / ABSOLUT]
        $data["gst_target_descr_{$this->lang}"] = $sheet->getCell('B12')->getCalculatedValue();
        $data["gst_coordination_text_{$this->lang}"] = $sheet->getCell('D16')->getCalculatedValue();
        $value = trim($sheet->getCell('D17')->getCalculatedValue());
        if ($value <> '') {
            if (is_numeric($value)) {
                $data['gst_staff_nr'] = (float) $value;
            } else {
                $data["gst_staff_text_{$this->lang}"] = $value;
            }
        }

        $data["gst_citizen_text_{$this->lang}"] = $sheet->getCell('D18')->getCalculatedValue();
        $data['gst_budget'] = $this->checkNumber($sheet->getCell('D19')->getCalculatedValue(), null, _('Bilancio complessivo deve essere numerico'));
        $data["gst_financial_text_{$this->lang}"] = $sheet->getCell('D20')->getCalculatedValue();
        $data["gst_monitoring_text_{$this->lang}"] = $sheet->getCell('D21')->getCalculatedValue();

        $this->data[$sheetName] = $data;
        return true;
    }

    protected function importInventory($inventoryNr) {

        $sheetName = $this->currentSheet = 'inventory';
        $this->currentInventory = $inventoryNr;
        $sheetIndex = $this->getSheetIndex($this->currentSheet, $inventoryNr);
        if ($sheetIndex === null) {
            $this->logger->warning("Missing inventory #{$inventoryNr}");
            return false;
        }
        $this->logger->debug("Importing inventory #{$inventoryNr} sheet");
        $data = $this->data[$sheetName][$inventoryNr] = array();
        $this->xls->setActiveSheetIndex($sheetIndex);
        $sheet = $this->xls->getActiveSheet();
        if ($inventoryNr == 1) {
            // Valori per dati generali
            $this->data['general']['gst_emission_factor_type_ipcc'] = stripos($sheet->getCell('D8')->getCalculatedValue(), 'lca') === false;
            $this->data['general']['gst_emission_unit_co2'] = stripos($sheet->getCell('D14')->getCalculatedValue(), 'equivalent') === false;
        }
        $data['general']['ge_year'] = $this->checkNumber($sheet->getCell('D7')->getCalculatedValue(), null, _('Anno di riferimento deve essere numerico'));
        $data['general']['ge_citizen'] = $this->checkNumber($sheet->getCell('J8')->getCalculatedValue(), null, _('Abitanti anno di riferimento deve essere numerico'));
        $data['general']['ge_green_electricity_purchase'] = 1000 * $this->checkNumber($sheet->getCell('C' . $this->getCellByMarker('M510'))->getCalculatedValue(), null, _('Acquisti elettricità verda deve essere numerico'));
        $data['general']['ge_green_electricity_co2_factor'] = $this->checkNumber($sheet->getCell('C' . $this->getCellByMarker('M520'))->getCalculatedValue(), null, _('Fattore emissione elettricità verda deve essere numerico'));
        $data['general']['ge_non_produced_co2_factor'] = $this->checkNumber($sheet->getCell('C' . $this->getCellByMarker('M560'))->getCalculatedValue(), null, _('Fattore emissione elettricità non prodotta localmente deve essere numerico'));

        // Lettura tabelle
        foreach (array('consumption', 'emission', 'electricity_production', 'heating_production') as $type) {
            foreach ($this->getGlobalCategories($type) as $marker => $categoryUUID) {
                $rowNr = $this->getCellByMarker($marker);
                foreach ($this->getGlobalSources($type) as $column => $sourceUUID) {
                    $val = $sheet->getCell("{$column}{$rowNr}")->getCalculatedValue();
                    if (!empty($val)) {
                        $data[$type][$this->getGlobalCategoryIdByUUID($categoryUUID)]['data'][$this->getEnergySourceIdByUUID($sourceUUID)] = $val;
                    }
                }
                if (in_array($type, array('electricity_production', 'heating_production'))) {
                    $column = 'C';
                    $val = $sheet->getCell("{$column}{$rowNr}")->getCalculatedValue();
                    if (!empty($val)) {
                        $data[$type][$this->getGlobalCategoryIdByUUID($categoryUUID)]['production'] = 1000 * $val;
                    }

                    $column = $type == 'electricity_production' ? 'O' : 'N';
                    $val = $sheet->getCell("{$column}{$rowNr}")->getCalculatedValue();
                    if (!empty($val)) {
                        $data[$type][$this->getGlobalCategoryIdByUUID($categoryUUID)]['emission'] = 1000 * $val;
                    }

                    $column = $type == 'electricity_production' ? 'P' : 'O';
                    $val = $sheet->getCell("{$column}{$rowNr}")->getCalculatedValue();
                    if (!empty($val)) {
                        $data[$type][$this->getGlobalCategoryIdByUUID($categoryUUID)]['emission_factor'] = 1000 * $val;
                    }
                }
            }
        }

        // lettura totali emissioni (ALTRO)
        foreach ($this->emissionExtraCategories as $marker => $categoryUUID) {
            $rowNr = $this->getCellByMarker($marker);
            $val = $sheet->getCell("R{$rowNr}")->getCalculatedValue();
            if (!empty($val)) {
                $data['emission'][$this->getGlobalCategoryIdByUUID($categoryUUID)]['totals'] = 1000 * $val;
            }
        }

        // Lettura fattori di conversione
        $rowNr = $this->getCellByMarker('M550');
        foreach ($this->getGlobalSources('consumption') as $column => $sourceUUID) {
            $val = $sheet->getCell("{$column}{$rowNr}")->getCalculatedValue();
            if ((string) $val <> '') {
                $data['emission_factor'][$this->getEnergySourceIdByUUID($sourceUUID)] = $val;
            }
        }
        $this->data[$sheetName][$inventoryNr] = $data;
        return true;
    }

    protected function convertCellDate($cellValue) {
        if ($cellValue == '') {
            return null;
        }
        if (is_numeric($cellValue)) {
            $d = PHPExcel_Shared_Date::ExcelToPHPObject($cellValue);
        } else {
            $cellValue = str_replace(array('/', '.'), '-', $cellValue);
            $a = explode('-', $cellValue);
            if (count($a) == 3 && is_numeric($a[0]) && is_numeric($a[1]) && is_numeric($a[2])) {
                $d = DateTime::createFromFormat('Y-m-d', "{$a[2]}-{$a[1]}-{$a[0]}");
            } else {
                $this->logger->warning(_("Data non valida \"{$cellValue}\""));
                return null;
            }
        }
        return $d->format('Y-m-d');
    }

    private function getGlobalCategoryByMarker($marker) {
        static $data;
        $translateMarker = array('M133' => 'M135', 'M134' => 'M135');  // Solare termico e Pompa di calore geotermica vanno in altro
        if ($data === null) {
            $sql = "SELECT *
                    FROM ecogis.global_category
                    WHERE gp_paes_marker IS NOT NULL";
            $data = array();
            foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $data[$row['gp_paes_marker']] = $row;
            }
        }
        $baseMarker = $marker[0] . (int) substr($marker, 1);
        if (!empty($translateMarker[$baseMarker])) {
            // Translate marker
            $baseMarker = $translateMarker[$baseMarker];
        }
        if (isset($data[$baseMarker])) {
            return $data[$baseMarker];
        }
        return null;
    }

    private function checkSEAPMarkers() {
        $sheet = $this->xls->getActiveSheet();
        $usedMarker = array();
        for ($i = 16; $i < 204; $i++) {
            $marker = $sheet->getCell('A' . $i)->getCalculatedValue();
            if ($marker == '') {
                die("Marker at A{$i} is empty\n");
                continue;
            }
            if ($this->getGlobalCategoryByMarker($marker) === null) {
                $txt = $sheet->getCell('B' . $i)->getCalculatedValue();
                $this->logger->warning(_("Il marker \"{$marker}\" [{$txt}] non è stato trovato"));
            }
            if (empty($usedMarker[$marker])) {
                $usedMarker[$marker] = $marker;
            } else {
                $error = "Marker {$marker} already used";
                throw new exception($error);
            }
        }
    }

    protected function parseSEAPDateOrYear($value, $extra) {
        if ($value == '') {
            return null;
        }
        if (is_numeric($value) && $value >= 1990 && $value <= 2100) {
            // Year
            $d = DateTime::createFromFormat('Y-m-d', "{$value}-{$extra}");
        } else {
            $value = str_replace(array('/', '.'), '-', $value);
            $a = explode('-', $value);
            if (count($a) == 3 && is_numeric($a[0]) && is_numeric($a[1]) && is_numeric($a[2])) {
                $d = DateTime::createFromFormat('Y-m-d', "{$a[2]}-{$a[1]}-{$a[0]}");
            } else {
                $this->logger->warning(_("Data non valida \"{$value}\""));
                return null;
            }
        }
        return $d->format('Y-m-d');
    }

    protected function parseSEAPActuationDate(&$startDate, &$endDate) {
        if ($startDate <> '' && $endDate <> '') {
            $startDate = $this->parseSEAPDateOrYear($startDate, '01-01');
            $endDate = $this->parseSEAPDateOrYear($endDate, '12-31');
        } else {
            $s = $startDate;
            $s = trim(str_replace(array('dal', 'al', 'form', 'to', '-'), ' ', $s));
            $s = preg_replace('/\s+/', ' ', $s);  // Replace multiple space with a single space
            $parts = explode(' ', $s);
            if (count($parts) == 1) {
                $startDate = $this->parseSEAPDateOrYear($parts[0], '01-01');
                $endDate = $this->parseSEAPDateOrYear($parts[0], '12-31');
            } else {
                $startDate = $this->parseSEAPDateOrYear($parts[0], '01-01');
                $endDate = $this->parseSEAPDateOrYear($parts[1], '12-31');
            }
        }
    }

    protected function importSEAP() {
        $sheetName = $this->currentSheet = 'seap';
        $sheetIndex = $this->getSheetIndex($this->currentSheet);
        if ($sheetIndex === null) {
            $this->logger->warning(_("Foglio PAES mancante o incompleto"));
            return false;
        }
        $this->logger->debug("Importing seap sheet");
        $data = $this->data[$sheetName] = array();
        $this->xls->setActiveSheetIndex($sheetIndex);
        $sheet = $this->xls->getActiveSheet();


        $data['general']["gp_name_{$this->lang}"] = $sheet->getCell('B7')->getCalculatedValue();
        $data['general']["gp_approval_date"] = $this->convertCellDate($sheet->getCell('D9')->getCalculatedValue());
        $data['general']["gp_approving_authority_{$this->lang}"] = $sheet->getCell('G9')->getCalculatedValue();
        $data['general']["gp_url_{$this->lang}"] = $sheet->getCell('C' . $this->getCellByMarker('M600'))->getCalculatedValue();
        if ($data['general']["gp_url_{$this->lang}"] <> '' && strpos($data['general']["gp_url_{$this->lang}"], '://') === false) {
            $data['general']["gp_url_{$this->lang}"] = "http://{$data['general']["gp_url_{$this->lang}"]}";
        }

        $highestRow = $sheet->getHighestRow();
        $seq = 0;
        for ($i = $this->getCellByMarker('M10'); $i < $this->getCellByMarker('M580'); $i++) {
            $marker = $sheet->getCellByColumnAndRow('A', $i)->getCalculatedValue();
            if (substr($marker, 0, 1) <> 'M') {
                continue;
            }
            $globalCategoryData = $this->getGlobalCategoryByMarker($marker);
            $gc_id = $globalCategoryData['gc_id'];
            if ($globalCategoryData['gc_parent_id'] === null) {
                // Gloal category. Read totals
                if ($this->seapVersion == 1.0) {
                    $data['totals'][$gc_id]['gps_expected_energy_saving'] = $this->checkNumber($sheet->getCell('M' . $i)->getCalculatedValue(), null, _('Obiettivo riduzione consumi deve essere numerico'));
                    $data['totals'][$gc_id]['gps_expected_renewable_energy_production'] = $this->checkNumber($sheet->getCell('N' . $i)->getCalculatedValue(), null, _('Obiettivo produzioni  deve essere numerico'));
                    $data['totals'][$gc_id]['gps_expected_co2_reduction'] = $this->checkNumber($sheet->getCell('O' . $i)->getCalculatedValue(), null, _('Obiettivo riduzione emissioni deve essere numerico'));
                } else {
                    $data['totals'][$gc_id]['gps_expected_energy_saving'] = $this->checkNumber($sheet->getCell('P' . $i)->getCalculatedValue(), null, _('Obiettivo riduzione consumi deve essere numerico'));
                    $data['totals'][$gc_id]['gps_expected_renewable_energy_production'] = $this->checkNumber($sheet->getCell('Q' . $i)->getCalculatedValue(), null, _('Obiettivo produzioni  deve essere numerico'));
                    $data['totals'][$gc_id]['gps_expected_co2_reduction'] = $this->checkNumber($sheet->getCell('R' . $i)->getCalculatedValue(), null, _('Obiettivo riduzione emissioni deve essere numerico'));
                }
            } else {

                if ($this->seapVersion == 1.0) {
                    $actionName = $sheet->getCell('D' . $i)->getCalculatedValue();
                    $actionDescr = trim($sheet->getCell('E' . $i)->getCalculatedValue());
                    $responsibleDepartment = $sheet->getCell('F' . $i)->getCalculatedValue();
                    $startDate = $this->convertCellDate($sheet->getCell('G' . $i)->getCalculatedValue());
                    $endDate = $this->convertCellDate($sheet->getCell('H' . $i)->getCalculatedValue());
                    $estimatedCost = $this->checkNumber($sheet->getCell('I' . $i)->getCalculatedValue(), null, _('Costi stimati deve essere numerico'));
                    $expectedEnergySaving = $this->checkNumber($sheet->getCell('J' . $i)->getCalculatedValue(), null, _('Risparmio energetico deve essere numerico'));
                    $expectedRenewableEnergyProduction = $this->checkNumber($sheet->getCell('K' . $i)->getCalculatedValue(), null, _('Produzione deve essere numerico'));
                    $expectedCo2Reduction = $this->checkNumber($sheet->getCell('L' . $i)->getCalculatedValue(), null, _('Riduzione emissioni deve essere numerico'));
                    if ($actionName == $actionDescr) {
                        $actionDescr = null;
                    }
                } else {
                    $actionName = $sheet->getCell('C' . $i)->getCalculatedValue();
                    $actionDescr = null;
                    $responsibleDepartment = $sheet->getCell('G' . $i)->getCalculatedValue();
                    $startDate = $sheet->getCell('I' . $i)->getCalculatedValue();
                    $endDate = $sheet->getCell('J' . $i)->getCalculatedValue();
                    $this->parseSEAPActuationDate($startDate, $endDate);  // Split date
                    $estimatedCost = $this->checkNumber($sheet->getCell('K' . $i)->getCalculatedValue(), null, _('Costi stimati deve essere numerico'));
                    $expectedEnergySaving = $this->checkNumber($sheet->getCell('M' . $i)->getCalculatedValue(), null, _('Risparmio energetico deve essere numerico'));
                    $expectedRenewableEnergyProduction = $this->checkNumber($sheet->getCell('N' . $i)->getCalculatedValue(), null, _('Produzione deve essere numerico'));
                    $expectedCo2Reduction = $this->checkNumber($sheet->getCell('O' . $i)->getCalculatedValue(), null, _('Riduzione emissioni deve essere numerico'));
                }
                if ($actionName == '') {
                    continue;
                }

                $seq++;
                $data['data'][$gc_id][$seq]["gpa_name"] = trim($actionName);
                $data['data'][$gc_id][$seq]["gpa_extradata"] = trim($actionDescr);
                $data['data'][$gc_id][$seq]["gpr_responsible_department"] = trim($responsibleDepartment);
                $data['data'][$gc_id][$seq]["gpr_start_date"] = $startDate;
                $data['data'][$gc_id][$seq]["gpr_end_date"] = $endDate;
                $data['data'][$gc_id][$seq]['gpr_estimated_cost'] = $estimatedCost;
                $data['data'][$gc_id][$seq]['gpr_expected_energy_saving'] = $expectedEnergySaving;
                $data['data'][$gc_id][$seq]['gpr_expected_renewable_energy_production'] = $expectedRenewableEnergyProduction;
                $data['data'][$gc_id][$seq]['gpr_expected_co2_reduction'] = $expectedCo2Reduction;
                $data['data'][$gc_id][$seq]['gpr_order'] = -(1000 - $seq);
            }
        }
        $this->data[$sheetName] = $data;
        return true;
    }

    public function load($fileName) {
        if (!file_exists($fileName)) {
            throw new exception("File \"{$fileName}\" not found");
        }
        setLocale(LC_ALL, 'C');
        $this->logger->debug("Reading XLS file");
        $this->xls = PHPExcel_IOFactory::load($fileName);

        $this->decodeSheetIndex();

        $this->data = array();

        $this->importGlobalStrategy();
        for ($i = 1; $i <= 2; $i++) {
            $this->importInventory($i);
        }
        $this->importSEAP();
    }

    public function hasSheet($shetName) {
        if (array_key_exists($shetName, $this->shetIndex)) {
            return $this->shetIndex[$shetName] !== null;
        }
        return null;
    }

    // Elimina tutti i consumi dell'inventario dato
    public function deleteInventoryConsumption($ge_id) {
        $stmt1 = $this->db->prepare("SELECT gs_id FROM ecogis.global_subcategory WHERE ge_id=?");
        $stmt2 = $this->db->prepare("DELETE FROM ecogis.global_data WHERE gs_id=?");
        $em_list_stmt = $this->db->prepare("SELECT em_id
                                     FROM ecogis.energy_meter em
                                     INNER JOIN ecogis.global_data gd ON em.em_object_id=gd.gd_id
                                     INNER JOIN ecogis.energy_meter_object emo ON em.emo_id = emo.emo_id AND emo.emo_code::text = 'GLOBAL_ENERGY'::text
                                     WHERE gs_id=?");

        $stmt1->execute(array($ge_id));
        foreach ($stmt1->fetchAll(PDO::FETCH_ASSOC) as $row1) {
            $em_list_stmt->execute(array($row1['gs_id']));
            $energyMeterList = $em_list_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($energyMeterList) > 0) {
                $sql = "DELETE FROM ecogis.consumption WHERE em_id IN (" . implode(', ', $energyMeterList) . ")";  // Remove consumption
                $this->db->exec($sql);
                $sql = "DELETE FROM ecogis.energy_meter WHERE em_id IN (" . implode(', ', $energyMeterList) . ")";  // Remove energy_meter
                $this->db->exec($sql);
            }
            $stmt2->execute(array($row1['gs_id']));
        }
        $stmt = $this->db->prepare("DELETE FROM ecogis.global_subcategory WHERE ge_id=?");
        $stmt->execute(array($ge_id));
    }

    public function deleteSeapActions($gp_id) {
        $stmt = $this->db->prepare("DELETE FROM ecogis.global_plain_sum WHERE gp_id=:gp_id");
        $stmt->execute(array('gp_id' => $gp_id));

        $stmt = $this->db->prepare("DELETE FROM ecogis.global_plain_row WHERE gp_id=:gp_id");
        $stmt->execute(array('gp_id' => $gp_id));

        // Remove global action data if not used
        $sql = "SELECT gpa_id FROM ecogis.global_plain_action
                    WHERE do_id=:do_id AND gpa_id NOT IN (SELECT gpa_id FROM ecogis.global_plain_row WHERE gp_id=:gp_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array('gp_id' => $gp_id, 'do_id' => $this->do_id));
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($actions) > 0) {
            $sql = "DELETE FROM ecogis.global_plain_action_category WHERE gpa_id IN (" . implode(', ', $actions) . ')';
            $this->db->exec($sql);
            $sql = "DELETE FROM ecogis.global_plain_action WHERE gpa_id IN (" . implode(', ', $actions) . ')';
            $this->db->exec($sql);
        }
    }

    private function getEnergySourceUDM($ges_id) {
        static $data;

        if ($data === null) {
            $MWhUUID = '8fa59aa4-9eb6-422e-8b3c-7a2e84a2b627';

            $sql = "SELECT ges_id, esu_id 
                    FROM ecogis.energy_source_udm esu
                    INNER JOIN ecogis.udm ON esu.udm_id=udm.udm_id
                    WHERE esu.do_id={$this->do_id} AND udm_uuid='{$MWhUUID}'
                    ORDER BY esu.do_id NULLS FIRST, mu_id NULLS FIRST, es_id";
            $data = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
        }
        if (isset($data[$ges_id])) {
            return $data[$ges_id];
        }
    }

    private function getEnergySourceUDMData($ges_id) {
        static $data;

        if ($data === null || $ges_id == null) {
            $MWhUUID = '8fa59aa4-9eb6-422e-8b3c-7a2e84a2b627';           // UDM=MWh
            $electricityUUID = '8252d5db-6e85-45a2-9083-1ae30d5cc740';   // Source=Elettricità (non termica)

            $sql = "SELECT esu.*, CASE WHEN es_uuid='{$electricityUUID}' THEN true ELSE false END AS is_electricity
                    FROM ecogis.energy_source_udm esu
                    INNER JOIN ecogis.udm ON esu.udm_id=udm.udm_id
                    INNER JOIN ecogis.energy_source es ON esu.es_id=es.es_id
                    WHERE esu.do_id={$this->do_id} AND udm_uuid='{$MWhUUID}'
                    ORDER BY esu.do_id NULLS FIRST, mu_id NULLS FIRST, es_id";
            $data = array();
            foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
                // echo "[{$row['ges_id']}: {$row['esu_co2_factor']}]\n";
                $data[$row['ges_id']] = $row;
            }
        }
        if (isset($data[$ges_id])) {
            return $data[$ges_id];
        }
        return null;
    }

    private function applyEmissionFactor($esu_id, $factor) {
        static $stmtSelect1, $stmtSelect2, $stmtUpdate;

        if ($stmtSelect1 === null) {
            $stmtSelect1 = $this->db->prepare("SELECT * FROM  ecogis.energy_source_udm WHERE do_id={$this->do_id} AND mu_id=:mu_id AND esu_id=:esu_id");
            $stmtSelect2 = $this->db->prepare("SELECT * FROM  ecogis.energy_source_udm WHERE do_id={$this->do_id} AND mu_id IS NULL AND esu_id=:esu_id");
            $stmtInsert = $this->db->prepare("INSERT INTO ecogis.energy_source_udm WHERE do_id IS NOT NULL AND mu_id=:mu_id AND esu_id=:esu_id");
            $stmtUpdate = $this->db->prepare("UPDATE ecogis.energy_source_udm SET esu_co2_factor=:esu_co2_factor WHERE do_id IS NOT NULL AND mu_id=:mu_id AND esu_id=:esu_id");
        }
        // Verifica che il fattore di conversione esiste per il comune, altrimeni lo crea inserendo quello dell'installazione e poi modificandolo
        $stmtSelect1->execute(array('esu_id' => $esu_id, 'mu_id' => $this->mu_id));
        $data = $stmtSelect1->fetch(PDO::FETCH_ASSOC);
        if ($stmtSelect1->rowCount() > 1) {
            throw new exception("Select error in applyEmissionFactor");
        }
        if ($data === false) {
            // Copy row
            $stmtSelect2->execute(array('esu_id' => $esu_id));
            $data = $stmtSelect2->fetch(PDO::FETCH_ASSOC);
            unset($data['esu_id']);
            $data['mu_id'] = $this->mu_id;
            foreach ($data as $key => $val) {
                if ($val === true) {
                    $data[$key] = 't';
                } else if ($val === false) {
                    $data[$key] = 'f';
                }
            }
            $sql = "INSERT INTO ecogis.energy_source_udm (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
            $stmtInsert = $this->db->prepare($sql);
            $stmtInsert->execute($data);
            $esu_id = $this->db->lastInsertId('ecogis.energy_source_udm_esu_id_seq');
        }
        // Update factor
        $stmtUpdate->execute(array('esu_co2_factor' => $factor * 1000, 'mu_id' => $this->mu_id, 'esu_id' => $esu_id));
        if ($stmtUpdate->rowCount() <> 1) {
            throw new exception("Update error in applyEmissionFactor");
        }
    }

    private function updateConversionFactor($emissionFactor, $consumptionData) {
        static $changedFactor = array();

        $globalEnergySource = array();
        foreach ($consumptionData as $data) {
            foreach ($data['data'] as $key => $dummy) {
                $globalEnergySource[$key] = $key;
            }
        }
        $this->getEnergySourceUDMData(null);
        foreach ($globalEnergySource as $ges_id) {
            if (!isset($emissionFactor[$ges_id])) {
                $this->logger->warning(_("Fattore di conversione mancante per la fonte \"" . $this->getGlobalEnergySourceName($ges_id) . "\" nell'inventario {$this->currentInventory}"));
                continue;
            }
            // echo "\n[$ges_id]:\n";
            $esuData = $this->getEnergySourceUDMData($ges_id);
            if (empty($esuData)) {
                $this->logger->error(_("Impossibile reperire i dati per la fonte \"" . $this->getGlobalEnergySourceName($ges_id) . "\". Verificare la configurazione di R3-EcoGIS"));
                continue;
            }
            $esuData['esu_co2_factor'] = $esuData['esu_co2_factor'] / 1000;
            if ($esuData['is_electricity']) {
                if ($esuData['esu_co2_factor'] <> $emissionFactor[$ges_id]) {
                    // echo "EFE CHANGE from {$esuData['esu_co2_factor']} to {$emissionFactor[$ges_id]}\n";
                    $this->efe = $emissionFactor[$ges_id];
                }
            } else {
                if (empty($changedFactor[$ges_id])) {
                    // Update
                    if ($esuData['esu_co2_factor'] <> $emissionFactor[$ges_id]) {
                        // echo "{$ges_id} CHANGE from {$esuData['esu_co2_factor']} to {$emissionFactor[$ges_id]}\n";
                        $this->applyEmissionFactor($esuData['esu_id'], $emissionFactor[$ges_id]);
                    }
                    $changedFactor[$ges_id] = $emissionFactor[$ges_id];
                } else {
                    if ($esuData['esu_co2_factor'] <> $emissionFactor[$ges_id]) {
                        $this->logger->warning(_("Il fattore di conversione per la fonte \"" . $this->getGlobalEnergySourceName($ges_id) . "\" è già stato aggiornato. Nell'inventario {$this->currentInventory} verrà mantenuto il valore {$changedFactor[$ges_id]} al posto di {$emissionFactor[$ges_id]}"));
                    }
                }
            }
        }
    }

    private function insertInventoryConsumption($ge_id, $data, $inventoryNr) {
        $stmt1 = $this->db->prepare("INSERT INTO ecogis.global_subcategory 
                                     (ge_id, gc_id, gs_name_{$this->lang}, gs_tot_value, gs_tot_production_value, gs_tot_emission_value, gs_tot_emission_factor) VALUES
                                     (:ge_id, :gc_id, :gs_name, :gs_tot_value, :gs_tot_production_value, :gs_tot_emission_value, :gs_tot_emission_factor)");
        $stmtGlobalData = $this->db->prepare("INSERT INTO ecogis.global_data (ges_id, gs_id) VALUES (:ges_id, :gs_id)");
        $stmtEnergyMeter = $this->db->prepare("INSERT INTO ecogis.energy_meter (esu_id, em_object_id, emo_id, em_serial) VALUES
                                               (:esu_id, :em_object_id, (SELECT emo_id FROM ecogis.energy_meter_object WHERE emo_code='GLOBAL_ENERGY'), '')");
        $stmtConsumption = $this->db->prepare("INSERT INTO ecogis.consumption (co_start_date, co_end_date, co_value, em_id) VALUES
                                              (:co_start_date, :co_end_date, :co_value, :em_id)");
        if (empty($data['emission_factor'])) {
            $data['emission_factor'] = null;
        }
        $this->updateConversionFactor($data['emission_factor'], $data['consumption'], $inventoryNr == 1);
        foreach (array('consumption', 'emission', 'electricity_production', 'heating_production') as $type) {
            if (empty($data[$type])) {
                continue;
            }
            foreach ($data[$type] as $gc_id => $row) {
                $values = array('ge_id' => $ge_id,
                    'gc_id' => $gc_id,
                    'gs_name' => _('Import'),
                    'gs_tot_value' => null,
                    'gs_tot_production_value' => null,
                    'gs_tot_emission_value' => null,
                    'gs_tot_emission_factor' => null);
                if ($type == 'emission' && empty($data[$type][$gc_id]['totals'])) {
                    // Le emissioni sono da inserire solo come totali
                    continue;
                }
                if (!empty($data[$type][$gc_id]['totals'])) {
                    $values['gs_tot_value'] = $data[$type][$gc_id]['totals'];
                }
                if (!empty($data[$type][$gc_id]['production'])) {
                    $values['gs_tot_production_value'] = $data[$type][$gc_id]['production'];
                }
                if (!empty($data[$type][$gc_id]['emission'])) {
                    $values['gs_tot_emission_value'] = $data[$type][$gc_id]['emission'];
                }
                if (!empty($data[$type][$gc_id]['emission_factor'])) {
                    $values['gs_tot_emission_factor'] = $data[$type][$gc_id]['emission_factor'];
                }
                $stmt1->execute($values);
                $gs_id = $this->db->lastInsertId('ecogis.global_subcategory_gs_id_seq');
                if ($type == 'emission') {
                    // Le emissioni non sono da inserire
                    continue;
                }
                if (empty($row['data'])) {
                    continue;
                }
                foreach ($row['data'] as $ges_id => $value) {
                    $stmtGlobalData->execute(array('ges_id' => $ges_id, 'gs_id' => $gs_id));
                    $gd_id = $this->db->lastInsertId('ecogis.global_data_gd_id_seq');
                    $esu_id = $this->getEnergySourceUDM($ges_id);
                    if ($esu_id === null) {
                        throw new Exception("Energy source (MWh) not found for Global Energy Source #\"{$ges_id}\" (" . $this->getGlobalEnergySourceName($ges_id) . ")");
                    }

                    $stmtEnergyMeter->execute(array('esu_id' => $esu_id, 'em_object_id' => $gd_id));
                    $em_id = $this->db->lastInsertId('ecogis.energy_meter_em_id_seq');
                    $stmtConsumption->execute(array('co_start_date' => "{$data['general']['ge_year']}-01-01",
                        'co_end_date' => "{$data['general']['ge_year']}-12-31", 'co_value' => $value, 'em_id' => $em_id));
                }
            }
        }
    }

    private function getOrInsertGlobalActionByName($gc_id, $gpa_name) {
        static $stmtSearch, $stmtInsert1, $stmtInsert2;

        if ($stmtSearch === null) {
            $sql = "SELECT gpa.gpa_id 
                    FROM ecogis.global_plain_action gpa
                    INNER JOIN ecogis.global_plain_action_category gpac ON gpa.gpa_id=gpac.gpa_id
                    WHERE (do_id IS NULL OR do_id=:do_id) AND gc_id=:gc_id AND gpa_name_{$this->lang} ILIKE :gpa_name";
            $stmtSearch = $this->db->prepare($sql);
            $sql = "INSERT INTO ecogis.global_plain_action (gpa_name_{$this->lang}, do_id, gpa_has_extradata) VALUES
                    (:gpa_name, :do_id, true)";
            $stmtInsert1 = $this->db->prepare($sql);
            $sql = "INSERT INTO ecogis.global_plain_action_category (gc_id, gpa_id) VALUES
                    (:gc_id, :gpa_id)";
            $stmtInsert2 = $this->db->prepare($sql);
        }
        $stmtSearch->execute(array('do_id' => $this->do_id, 'gc_id' => $gc_id, 'gpa_name' => $gpa_name));
        $gpa_id = $stmtSearch->fetchColumn();
        if ($gpa_id === false) {
            $stmtInsert1->execute(array('do_id' => $this->do_id, 'gpa_name' => $gpa_name));
            $gpa_id = $this->db->lastInsertId('ecogis.global_plain_action_gpa_id_seq');
            $stmtInsert2->execute(array('gc_id' => $gc_id, 'gpa_id' => $gpa_id));
        }
        return $gpa_id;
    }

    public function getGlobalEnergySourceName($ges_id) {
        $stmt = $this->db->prepare("SELECT ges_name_{$this->lang} FROM ecogis.global_energy_source WHERE ges_id=:ges_id");
        $stmt->execute(array($ges_id));
        return $stmt->fetchColumn();
    }

    public function applyGlobalStrategy() {
        $this->currentSheet = 'general';
        $this->gst_id = null;
        if (empty($this->data['general'])) {
            return false;
        }
        $this->logger->debug("Applying global strategy data");
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ecogis.global_strategy WHERE mu_id=?");
        $stmt->execute(array($this->mu_id));
        $hasData = $stmt->fetchColumn();

        $data = $this->data['general'];
        $data['gst_reduction_target_absolute'] = $data['gst_reduction_target_absolute'] ? 't' : 'f';
        if (array_key_exists('gst_emission_factor_type_ipcc', $data)) {
            $data['gst_emission_factor_type_ipcc'] = $data['gst_emission_factor_type_ipcc'] ? 't' : 'f';
        }
        if (array_key_exists('gst_emission_unit_co2', $data)) {
            $data['gst_emission_unit_co2'] = $data['gst_emission_unit_co2'] ? 't' : 'f';
        }
        if ($hasData == 0) {
            $sql = "INSERT INTO ecogis.global_strategy (mu_id, gst_imported_row, gst_name_1, " . implode(', ', array_keys($data)) . ") VALUES (:mu_id, true, :gst_name_1, :" . implode(', :', array_keys($data)) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data, array('mu_id' => $this->mu_id, 'gst_name_1' => sprintf(_('Patto dei sindaci - Comune di %s'), $this->mu_name))));
        } else if ($hasData == 1) {
            $fileds = array();
            foreach (array_keys($data) as $name) {
                $fileds[] = "{$name}=:{$name}";
            }
            $sql = "UPDATE ecogis.global_strategy SET " . implode(', ', $fileds) . ", ge_id=NULL, ge_id_2=NULL, gp_id=NULL WHERE mu_id=:mu_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data, array('mu_id' => $this->mu_id)));
        } else {
            throw new exception("Can't import global strategy when more record found");
        }
        $stmt = $this->db->prepare("SELECT gst_id FROM ecogis.global_strategy WHERE mu_id=?");
        $stmt->execute(array($this->mu_id));
        $this->gst_id = $stmt->fetchColumn();
    }

    public function applyInventory($inventoryNr = null) {
        $this->currentSheet = 'inventory';
        if ($inventoryNr === null) {
            $this->applyInventory(1);
            $this->applyInventory(2);
        }

        if (empty($this->data['inventory'][$inventoryNr])) {
            return false;
        }

        $data = $this->data['inventory'][$inventoryNr];

        if ($data['general']['ge_year'] == '') {
            $this->logger->debug("Inventario #{$inventoryNr} mancante o incompleto");
            return false;
        }
        $this->logger->debug("Applying inventory #{$inventoryNr} data");
        $this->currentInventory = $inventoryNr;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ecogis.global_entry WHERE mu_id=? AND ge_year=?");
        $stmt->execute(array($this->mu_id, $this->data['inventory'][$inventoryNr]['general']['ge_year']));
        $hasData = $stmt->fetchColumn();
        $this->efe = null;


        if ($hasData == 0) {
            $sql = "INSERT INTO ecogis.global_entry (mu_id, ge_imported_row, ge_name_1, " . implode(', ', array_keys($data['general'])) . ") VALUES (:mu_id, true, :gst_name_1, :" . implode(', :', array_keys($data['general'])) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data['general'], array('mu_id' => $this->mu_id, 'gst_name_1' => sprintf(_('Inventario emissioni %d'), $data['general']['ge_year']))));
            $ge_id = $this->db->lastInsertId('ecogis.global_entry_ge_id_seq');
        } else if ($hasData == 1) {
            $fileds = array();
            foreach (array_keys($data['general']) as $name) {
                $fileds[] = "{$name}=:{$name}";
            }
            $sql = "UPDATE ecogis.global_entry SET " . implode(', ', $fileds) . " WHERE mu_id=:mu_id AND ge_year=:ge_year_where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data['general'], array('mu_id' => $this->mu_id, 'ge_year_where' => $data['general']['ge_year'])));
            if ($stmt->rowCount() > 1) {
                throw new exception("Update error in applyInventory");
            }
            // Get ge_id
            $stmt = $this->db->prepare("SELECT ge_id FROM ecogis.global_entry WHERE mu_id=? AND ge_year=?");
            $stmt->execute(array($this->mu_id, $this->data['inventory'][$inventoryNr]['general']['ge_year']));
            $ge_id = $stmt->fetchColumn();
        } else {
            throw new exception("Can't import global strategy when more record found");
        }

        $this->deleteInventoryConsumption($ge_id);
        $this->insertInventoryConsumption($ge_id, $data, $inventoryNr);

        if ($this->efe <> null) {
            $stmt = $this->db->prepare("UPDATE ecogis.global_entry SET ge_local_efe=:ge_local_efe WHERE ge_id=:ge_id");
            $stmt->execute(array('ge_local_efe' => $this->efe, 'ge_id' => $ge_id));
        }
        if ($this->gst_id !== null) {
            $sql = "UPDATE ecogis.global_strategy SET " . ($inventoryNr == 1 ? 'ge_id' : 'ge_id_2') . "={$ge_id} WHERE gst_id=:gst_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array('gst_id' => $this->gst_id));
        }
    }

    public function applySEAP() {
        $this->currentSheet = 'general';
        if (empty($this->data['seap'])) {
            return false;
        }
        $this->logger->debug("Applying seap data");
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ecogis.global_plain WHERE mu_id=? AND gp_imported_row IS TRUE");
        $stmt->execute(array($this->mu_id));
        $hasData = $stmt->fetchColumn();
        $data = $this->data['seap'];

        if ($hasData == 0) {
            $sql = "INSERT INTO ecogis.global_plain (mu_id, gp_imported_row, " . implode(', ', array_keys($data['general'])) . ") VALUES (:mu_id, true, :" . implode(', :', array_keys($data['general'])) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data['general'], array('mu_id' => $this->mu_id)));
            $gp_id = $this->db->lastInsertId('ecogis.global_plain_gp_id_seq');
        } else if ($hasData == 1) {
            $fileds = array();
            foreach (array_keys($data['general']) as $name) {
                $fileds[] = "{$name}=:{$name}";
            }
            $sql = "UPDATE ecogis.global_plain SET " . implode(', ', $fileds) . " WHERE mu_id=:mu_id AND gp_imported_row IS TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($data['general'], array('mu_id' => $this->mu_id)));
            if ($stmt->rowCount() > 1) {
                throw new exception("Update error in applySEAP");
            }
            // Get ge_id
            $stmt = $this->db->prepare("SELECT gp_id FROM ecogis.global_plain WHERE mu_id=? AND gp_imported_row IS TRUE");
            $stmt->execute(array($this->mu_id));
            $gp_id = $stmt->fetchColumn();

            // Delete old data
            $this->deleteSeapActions($gp_id);
        } else {
            throw new exception("Can't import seap when more imported record found");
        }

        // Insert totals
        $sql = "INSERT INTO ecogis.global_plain_sum (gp_id, gc_id, gps_expected_energy_saving, gps_expected_renewable_energy_production, gps_expected_co2_reduction) VALUES
                (:gp_id, :gc_id, :gps_expected_energy_saving, :gps_expected_renewable_energy_production, :gps_expected_co2_reduction)";
        $stmt = $this->db->prepare($sql);
        foreach ($data['totals'] as $gc_id => $row) {
            $stmt->execute(array(
                'gp_id' => $gp_id,
                'gc_id' => $gc_id,
                'gps_expected_energy_saving' => $row['gps_expected_energy_saving'],
                'gps_expected_renewable_energy_production' => $row['gps_expected_renewable_energy_production'],
                'gps_expected_co2_reduction' => $row['gps_expected_co2_reduction']));
        }

        // Insert rows
        $sql = "INSERT INTO ecogis.global_plain_row (gp_id, gc_id, gpa_id, gpa_extradata_{$this->lang}, gpr_responsible_department_{$this->lang},
                gpr_start_date, gpr_end_date, gpr_estimated_cost, gpr_expected_energy_saving, gpr_expected_renewable_energy_production, gpr_expected_co2_reduction, gpr_order, gpr_imported_row) VALUES
                (:gp_id, :gc_id, :gpa_id, :gpa_extradata, :gpr_responsible_department, :gpr_start_date, :gpr_end_date, :gpr_estimated_cost, :gpr_expected_energy_saving, 
                 :gpr_expected_renewable_energy_production, :gpr_expected_co2_reduction, :gpr_order, true)";
        $stmt = $this->db->prepare($sql);

        if (empty($data['data'])) {
            $this->logger->error(_("Imporribile leggere il foglio con il PAES"));
            return false;
        }
        foreach ($data['data'] as $gc_id => $rows) {
            foreach ($rows as $row) {
                $gpa_id = $this->getOrInsertGlobalActionByName($gc_id, $row['gpa_name']);
                $stmt->execute(array(
                    'gp_id' => $gp_id,
                    'gc_id' => $gc_id,
                    'gpa_id' => $gpa_id,
                    'gpa_extradata' => $row['gpa_extradata'],
                    'gpr_responsible_department' => $row['gpr_responsible_department'],
                    'gpr_start_date' => $row['gpr_start_date'],
                    'gpr_end_date' => $row['gpr_end_date'],
                    'gpr_estimated_cost' => $row['gpr_estimated_cost'],
                    'gpr_expected_energy_saving' => $row['gpr_expected_energy_saving'],
                    'gpr_expected_renewable_energy_production' => $row['gpr_expected_renewable_energy_production'],
                    'gpr_expected_co2_reduction' => $row['gpr_expected_co2_reduction'],
                    'gpr_order' => $row['gpr_order']
                ));
            }
        }

        if ($this->gst_id !== null) {
            $sql = "UPDATE ecogis.global_strategy SET gp_id={$gp_id} WHERE gst_id=:gst_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array('gst_id' => $this->gst_id));
        }
    }

    public function apply() {
        $this->applyGlobalStrategy();
        $this->applyInventory();
        $this->applySEAP();
    }

}
