<?php

namespace EcogisBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ezcDbInstance;

require_once R3_LIB_DIR.'r3impexp.php';
require_once R3_LIB_DIR.'r3import_utils.php';

class ExportBuildingsCommand extends EcoGenericCommand
{

    protected function configure()
    {
        $this
            ->setName('ecogis:export-buildings')
            ->setDescription('Export buildings data')
            ->setHelp("Export buildings data")
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language code')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Export format ("shp"|"xls"|"xlsx")')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'The full output file name')
            ->addOption('zip', null, InputOption::VALUE_NONE, 'If set, zip the content')
            ->addOption('zip-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix of names inside the zip file')
            ->addOption('json-filter', null, InputOption::VALUE_REQUIRED, 'Filter to apply')
        ;
    }

    /**
     * Return the where statement with the filter
     * @param (json)string $jsonText
     * @return array
     */
    protected function filter2where($jsonText)
    {
        $filterDefs = array(
            'do_id' => 'integer',
            'mu_id' => 'integer',
            'mu_name' => 'string',
            'fr_id' => 'integer',
            'fr_name' => 'string',
            'st_id' => 'integer',
            'st_name' => 'string',
            'bu_civic' => 'string',
            'bu_code' => 'string',
            'bu_name' => 'string',
            'bpu_id' => 'integer',
            'bt_id' => 'integer',
            'bby_id' => 'integer',
            'bry_id' => 'integer',
            'bu_to_check' => 'boolean'
        );

        $db = ezcDbInstance::get();
        $filters = json_decode($jsonText, true);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            throw new \Exception("Error #{$jsonError} decoding json filter data");
        }
        $where = array();
        foreach ($filters as $key => $val) {
            if (!array_key_exists($key, $filterDefs)) {
                throw new \Exception("Unknown filter key: {$key}");
            }
            if ($key == 'bu_civic') {
                if (strpos($val, '/') > 0) {
                    $where[] = "COALESCE(bu_nr_civic::text || COALESCE('/' || bu_nr_civic_crossed, '')) ILIKE ".$db->quote("%{$val}%");
                } else {
                    $where[] = "bu_nr_civic=".(int) $val;
                }
            } else {
                switch ($filterDefs[$key]) {
                    case 'integer':
                        $where[] = "{$key}=".(int) $val;
                        break;
                    case 'string':
                        $where[] = "{$key} ILIKE ".$db->quote("%{$val}%");
                        break;
                    case 'boolean':
                        if ($val === true || substr(strtoupper($val), 0, 1) == 'T') {
                            $boolString = "TRUE";
                        } else {
                            $boolString = "FALSE";
                        }
                        $where[] = "{$key} IS {$boolString}";
                        break;


                    default:
                        throw new \Exception("Unknown filter type: {$filterDefs[$key]}");
                }
            }
        }
        return $where;
    }

    private function getTranslations($shortFormat = false)
    {
        $translations = array(
            'bu_code' => _("Codice edificio"),
            'bu_name' => _("Nome edificio"),
            'fr_name' => _("Frazione"),
            'st_name' => _("Strada"),
            'bu_nr_civic' => _("Nr.Civico"),
            'cm_name' => _("Comune catastale"),
            'cm_number' => _("Particella"),
            'bu_survey_date' => _("Data audit"),
            'bt_name' => _("Tipologia costruttiva"),
            'bpu_name' => _("Destinazione d'uso"),
            'gc_name' => _("Categoria inventario"),
            'bby_name' => _("Anno di costruzione"),
            'bry_name' => _("Anno di ristrutturazione"),
            'bu_restructure_descr' => _("Descrizione ristrutturazione"),
            'ez_code' => _("Zona climatica"),
            'ec_code' => _("Classe energetica"),
            'bu_area_heating' => _("Sup.utile riscaldata [m2]"),
            'bu_area' => _("Vol. lordo riscaldato [m3]"),
            'bu_glass_area' => _("Superficie vetrata [m2]"),
            'bu_sv_factor' => _("Fattore forma S/V"),
            'bu_usage' => _("Uso giorn. edificio"),
            'bu_usage_days' => _("Uso sett. edificio [giorni/settimana]"),
            'bu_usage_weeks' => _("Uso annuale edificio [settimane/anno]"),
            'bu_persons' => _("Occupanti edificio [N°pers./gg]"),
            'bu_descr' => _("Note"),
            'bu_to_check' => _("Da controllare"),
            'has_geometry' => _("Geometria presente")
        );
        if ($shortFormat) {
            return array_combine(array_keys($translations), array_keys($translations));
        } else {
            return $translations;
        }
    }

    /**
     * Fix the shape text (remove special chars)
     * @param type $text
     */
    private function fixShapeLabel($text)
    {
        $from = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'à', 'á', 'â', 'ã', 'ä', 'å', 'È', 'É', 'Ê', 'Ë', 'è', 'é', 'ê', 'ë',
            'Ì', 'Í', 'Î', 'Ï', '', 'ì', 'í', 'î', 'ï', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'Ù',
            'Ú', 'Û', 'Ü', 'ù', 'ú', 'û', 'ü', 'ß');
        $to =   array('A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
            'I', 'I', 'I', 'I', 'I', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'U',
            'U', 'U', 'U', 'U', 'U', 'U', 'U', 'SS');
        $text = str_replace($from, $to, $text);
        $text = preg_replace("/[^A-Z0-9]/i", '_', $text);
        $text = str_replace($from, $to, $text);
        $text = preg_replace("/_{2,}/", "_", $text);
        $text = strtolower($text);
        return $text;
    }

    /**
     * Fix the shape text (remove special chars)
     * @param type $text
     */
    private function zipFiles($destFile, array $files, $prefix=null)
    {
        $zip = new \ZipArchive();

        if ($zip->open($destFile, \ZipArchive::CREATE) !== true) {
            throw new \Exception("Can't create \"{$destFile}\"");
        }
        foreach ($files as $file) {
            if ($prefix == null) {
                $zip->addFile($file, basename($file));
            } else {
                $parts = explode('.', basename($file), 2);
                $nameInZip = $prefix . '.' . $parts[count($parts) - 1];
                $zip->addFile($file, $nameInZip);
            }
        }
        if ($zip->close() !== true) {
            throw new \Exception("Can't create \"{$destFile}\"");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driversMap = array('shp' => 'OgrShp', 'xls' => 'PhpexcelXlsAutoload', 'xlsx' => 'PhpexcelXlsxAutoload');

        error_reporting(E_ALL);
        $this->dbConnect();
        $this->setLangOptions();
        $db = ezcDbInstance::get();
        $lang = $input->getOption('lang');
        \R3Locale::setLanguageIDFromCode($lang);
        $lang = \R3Locale::getLanguageID();

        // Apply language
        setLang(\R3Locale::getLanguageCode(), LC_MESSAGES);
        bindtextdomain('messages', R3_LANG_DIR);
        textdomain('messages');
        bind_textdomain_codeset('messages', R3_APP_CHARSET);

        $domainName = $input->getOption('domain');
        $sql = "SELECT do_id FROM auth.domains_name WHERE dn_name=".$db->quote($domainName);
        $doId = $db->query($sql)->fetchColumn();
        if (empty($doId)) {
            throw new \Exception("Domain \"{$domainName}\" not found");
        }
        $srid = $sql = "SELECT cus_srid FROM ecogis.customer WHERE do_id={$doId}";
        $srid = $db->query($sql)->fetchColumn();

        $fileName = $input->getOption('output');
        if (empty($fileName)) {
            throw new \Exception("Missing destination file");
        }

        $format = $input->getOption('format');
        if (empty($format) || !in_array($format, array('shp', 'xls', 'xlsx'))) {
            throw new \Exception("Missing or invalid format (\"{$format}\"). Valid format are shp, xls, xlsx");
        }

        $jsonFilter = $input->getOption('json-filter');
        $where = array('true');
        if (!empty($jsonFilter)) {
            $where = $this->filter2where($jsonFilter);
        }
        $whereText = implode(' AND ', $where);
        // die;
        $lockFileName = "{$fileName}.lock";
        if (!($fp = fopen($lockFileName, "w"))) {
            throw new \Exception("Can't acquire lock \"{$lockFileName}\"");
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            throw new \Exception("Can't acquire lock \"{$lockFileName}\"");
        }

        $logger = new \R3ExportLogger("{$fileName}.sts");
        $logger->log(LOG_INFO, 'Preparing');

        // Materialyze consumption data
        $db->beginTransaction();

        $logger->log(LOG_INFO, "Materialyze consumption data");
        $sql = "CREATE TEMPORARY TABLE consumption_year_mat_temp AS
                SELECT * FROM ecogis.consumption_year";
        $db->exec($sql);

        // Extract years
        $logger->log(LOG_INFO, "Extracting years");
        $sql = "SELECT DISTINCT co_year
                FROM consumption_year_mat_temp
                ORDER BY co_year";
        $years = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_COLUMN);

        // Extract sources
        $logger->log(LOG_INFO, "Extracting sources");
        $sql = "SELECT DISTINCT COALESCE(es1.es_id, es2.es_id) AS es_id,
                       COALESCE(es1.es_name_{$lang}, us_name_{$lang}) AS es_name,
                       COALESCE(et1.et_code, et2.et_code) AS et_code,
                       emo_code,
                       CASE COALESCE(et1.et_code, et2.et_code)
                           WHEN 'ELECTRICITY' THEN 20
                           WHEN 'HEATING' THEN 10
                       ELSE 30 END ord
                FROM consumption_year_mat_temp
                INNER JOIN ecogis.energy_meter em using(em_id)
                INNER JOIN ecogis.energy_meter_object emo ON em.emo_id=emo.emo_id AND emo_code='BUILDING'
                LEFT JOIN (ecogis.energy_source_udm esu1
                    INNER JOIN ecogis.energy_source es1 on esu1.es_id=es1.es_id
                    INNER JOIN ecogis.energy_type et1 on es1.et_id=et1.et_id )ON em.esu_id = esu1.esu_id
                LEFT JOIN (ecogis.utility_product up
                    INNER JOIN ecogis.energy_source_udm esu2 ON up.esu_id = esu2.esu_id
                    INNER JOIN ecogis.energy_source es2 on esu2.es_id=es2.es_id
                    INNER JOIN ecogis.utility_supplier us2 on up.us_id=us2.us_id
                    INNER JOIN ecogis.energy_type et2 on es2.et_id=et2.et_id ) ON em.up_id = up.up_id
                ORDER BY ord, es_name";
        $sources = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $totSource = array('HEATING' => 0, 'ELECTRICITY' => 0, 'WATER' => 0);
        foreach ($sources as $source) {
            $totSource[$source['et_code']] ++;
        }

        $geomFieldText = '';
        if ($format == 'shp') {
            $lbl = $this->getTranslations(true);
            $geomFieldText = ', the_geom';
            if (strrchr($fileName, '.') == '.shp') {
                $fileName = substr($fileName, 0, -4);
            }
        } else {
            $lbl = $this->getTranslations(false);
        }
        // print_r($lbl); die;
        //Build query
        $parts = array();
        foreach ($years as $year) {
            foreach (array('HEATING' => 'riscaldamento', 'ELECTRICITY' => 'elettrico') as $et_code => $et_name) {
                foreach ($sources as $source) {
                    if ($source['et_code'] == $et_code) {
                        $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_{$source['es_name']}") : "{$year} - {$source['es_name']} [kWh/a]";
                        $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} AND es_id={$source['es_id']} THEN co_value*esu_kwh_factor END)::NUMERIC, 0) AS \"{$lblVariable}\"";
                    }
                }
                if ($totSource[$et_code] > 1) {
                    $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TA_{$et_name}") : "{$year} - Totale {$et_name} [kWh/a]";
                    $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} AND et_code='{$et_code}' THEN co_value*esu_kwh_factor END)::NUMERIC, 0) AS \"{$lblVariable}\"";
                }
                $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TR_{$et_name}") : "{$year} - Totale {$et_name} [kWh/m2/a]";
                $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} AND et_code='{$et_code}' THEN co_value*esu_kwh_factor END / NULLIF(bu_area_heating, 0))::NUMERIC, 1)::NUMERIC(18,1) AS \"{$lblVariable}\"";

                $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TE_{$et_name}") : "{$year} - Totale emissioni {$et_name} [Kg CO2/a]";
                $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} AND et_code='{$et_code}' THEN co_value*esu_co2_factor END)::NUMERIC, 0) AS \"{$lblVariable}\"";

                $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TC_{$et_name}") : "{$year} - Totale contatori {$et_name}";
                $parts[] = "       NULLIF(COUNT(CASE WHEN co_year={$year} AND et_code='{$et_code}' THEN 1 END)::NUMERIC, 0) AS \"{$lblVariable}\"";
            }
            // Building totals
            $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TOT_CONSUMPTION") : "{$year} - Totale consumi edificio [kWh/a]";
            $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} THEN co_value*esu_kwh_factor END)::NUMERIC, 0) AS \"{$lblVariable}\"";

            $lblVariable = $format == 'shp' ? $this->fixShapeLabel("{$year}_TOT_EMISSION") : "{$year} - Totale emissioni edificio [Kg CO2/a]";
            $parts[] = "       ROUND(SUM(CASE WHEN co_year={$year} THEN co_value*esu_co2_factor END)::NUMERIC, 0) AS \"{$lblVariable}\"";
        }

        $textParts = implode(", \n", $parts);
        $sql = "WITH co_year_data AS (
                    SELECT mu.do_id, bu.mu_id, mu_name_{$lang} AS mu_name,
                           bu_id, bu_code, LPAD(COALESCE(bu.bu_code, ''), 10, '0') AS bu_code_pad,
                           COALESCE(bu_name_{$lang}, bu_name_1, bu_name_2) AS bu_name,
                           bu.fr_id, COALESCE(fr_name_{$lang}, fr_name_1, fr_name_2)::TEXT AS fr_name,
                           bu.st_id, COALESCE(st_name_{$lang}, st_name_1, st_name_2)::TEXT AS st_name,
                           bu_nr_civic, bu_nr_civic_crossed,
                           bu.bt_id, NULLIF(CONCAT_WS(' ' , COALESCE(bt_name_{$lang}, bt_name_1, bt_name_2), '(' || COALESCE(bt_extradata_{$lang}, bt_extradata_1, bt_extradata_2) || ')'), '') AS bt_name,
                           COALESCE(cm_name_{$lang}, cm_name_1, cm_name_2) AS cm_name, cm_number, bu_survey_date,
                           bu.bpu_id, NULLIF(CONCAT_WS(' ' , COALESCE(bpu_name_{$lang}, bpu_name_1, bpu_name_2), '(' || COALESCE(bpu_extradata_{$lang}, bpu_extradata_1, bpu_extradata_2) || ')'), '') AS bpu_name,
                           COALESCE(gc_name_{$lang}, gc_name_1, gc_name_2) AS gc_name,
                           bu.bby_id, COALESCE(bu_build_year::TEXT, bby_name_{$lang}) AS bby_name,
                           bu.bry_id, COALESCE(bu_restructure_year::TEXT, COALESCE(bry_name_{$lang}, bry_name_1, bry_name_2)) AS bry_name,
                           COALESCE(bu_restructure_descr_{$lang}, bu_restructure_descr_1, bu_restructure_descr_2) AS bu_restructure_descr,
                           bu_area, bu_area_heating, bu_glass_area, bu_sv_factor,
                           bu_usage_h_from, bu_usage_h_to, bu_usage_days, bu_usage_weeks, bu_persons,
                           NULLIF(CONCAT_WS(' ' , COALESCE(bu_descr_{$lang}, bu_descr_1, bu_descr_2), '(' || COALESCE(bu_extra_descr_{$lang}, bu_extra_descr_1, bu_extra_descr_2) || ')'), '') AS bu_descr,
                           bu_to_check, CASE WHEN bu_to_check THEN 'X' END AS bu_to_check_cross,
                           CASE WHEN bu.the_geom IS NOT NULL THEN 'X' END AS has_geometry_cross,
                           ez_code, ec.ec_code AS ec_code,
                           co_year, co_value,
                           COALESCE(esu1.esu_kwh_factor, esu2.esu_kwh_factor) AS esu_kwh_factor,
                           COALESCE(esu1.esu_co2_factor, esu2.esu_co2_factor) AS esu_co2_factor,
                           COALESCE(esu1.es_id, esu2.es_id) as es_id,
                           COALESCE(et1.et_code, et2.et_code) as et_code,
                           ST_SetSRID(bu.the_geom, {$srid}) AS the_geom
                    FROM ecogis.building bu
                    INNER JOIN ecogis.municipality mu USING(mu_id)
                    LEFT JOIN (ecogis.energy_meter em
                        INNER JOIN ecogis.energy_meter_object emo ON em.emo_id = emo.emo_id AND emo.emo_code::text = 'BUILDING'::text) ON bu.bu_id = em.em_object_id
                    LEFT JOIN consumption_year_mat_temp cy ON em.em_id = cy.em_id
                    LEFT JOIN (ecogis.energy_source_udm esu1
                        INNER JOIN ecogis.energy_source es1 on esu1.es_id=es1.es_id
                        INNER JOIN ecogis.energy_type et1 on es1.et_id=et1.et_id )ON em.esu_id = esu1.esu_id
                    LEFT JOIN (ecogis.utility_product up
                        INNER JOIN ecogis.energy_source_udm esu2 ON up.esu_id = esu2.esu_id
                        INNER JOIN ecogis.energy_source es2 on esu2.es_id=es2.es_id
                        INNER JOIN ecogis.energy_type et2 on es2.et_id=et2.et_id ) ON em.up_id = up.up_id
                    LEFT JOIN ecogis.building_type bt ON bu.bt_id=bt.bt_id
                    LEFT JOIN ecogis.building_purpose_use bpu ON bu.bpu_id=bpu.bpu_id
                    LEFT JOIN ecogis.global_category gc ON bpu.gc_id=gc.gc_id
                    LEFT JOIN ecogis.cat_munic cm ON bu.cm_id=cm.cm_id
                    LEFT JOIN common.fraction fr ON bu.fr_id=fr.fr_id
                    LEFT JOIN common.street st ON bu.st_id=st.st_id
                    LEFT JOIN ecogis.building_build_year bby ON bu.bby_id = bby.bby_id
                    LEFT JOIN ecogis.building_restructure_year bry ON bu.bry_id = bry.bry_id
                    LEFT JOIN ecogis.energy_zone ez ON bu.ez_id = ez.ez_id
                    LEFT JOIN ecogis.energy_class ec ON bu.ec_id = ec.ec_id
                )
                SELECT bu_code AS \"{$lbl['bu_code']}\", bu_name AS \"{$lbl['bu_name']}\",
                       fr_name AS \"{$lbl['fr_name']}\", st_name AS \"{$lbl['st_name']}\", CONCAT_WS('/', bu_nr_civic, UPPER(TRIM(bu_nr_civic_crossed))) AS \"{$lbl['bu_nr_civic']}\",
                       cm_name AS \"{$lbl['cm_name']}\", cm_number AS \"{$lbl['cm_number']}\", bu_survey_date AS \"{$lbl['bu_survey_date']}\", bt_name AS \"{$lbl['bt_name']}\",
                       bpu_name AS \"{$lbl['bpu_name']}\", gc_name AS \"{$lbl['gc_name']}\",
                       bby_name AS \"{$lbl['bby_name']}\", bry_name AS \"{$lbl['bry_name']}\", bu_restructure_descr AS \"{$lbl['bu_restructure_descr']}\",
                       ez_code AS \"{$lbl['ez_code']}\", ec_code AS \"{$lbl['ec_code']}\",
                       bu_area_heating AS \"{$lbl['bu_area_heating']}\", bu_area AS \"{$lbl['bu_area']}\",
                       bu_glass_area AS \"{$lbl['bu_glass_area']}\", bu_sv_factor::NUMERIC(18,2) AS \"{$lbl['bu_sv_factor']}\",
                       NULLIF(CONCAT_WS(' - ', SUBSTR(bu_usage_h_from::TEXT, 1, 5), SUBSTR(bu_usage_h_to::TEXT, 1, 5)), '') AS \"{$lbl['bu_usage']}\",
                       bu_usage_days AS \"{$lbl['bu_usage_days']}\", bu_usage_weeks AS \"{$lbl['bu_usage_weeks']}\",
                       bu_persons AS \"{$lbl['bu_persons']}\",
                       bu_descr AS \"{$lbl['bu_descr']}\",
                       {$textParts},
                       bu_to_check_cross AS \"{$lbl['bu_to_check']}\", has_geometry_cross AS \"{$lbl['has_geometry']}\"
                       {$geomFieldText}
                FROM co_year_data
                WHERE {$whereText}
                GROUP BY bu_id, bu_code, bu_code_pad, bu_name, bt_name, fr_name, st_name, cm_name, cm_number,
                         bu_nr_civic, bu_nr_civic_crossed, bu_survey_date, bpu_name, gc_name, bby_name, bry_name, bu_restructure_descr,
                         bu_area_heating, bu_area, bu_glass_area, bu_sv_factor, ec_code, bu_usage_h_from, bu_usage_h_to, bu_usage_days, bu_usage_weeks, bu_persons,
                         bu_descr, bu_to_check, bu_to_check_cross, has_geometry_cross, ez_code {$geomFieldText}
                ORDER BY bu_code_pad";

        // echo "$sql\n";
        $utils = new \R3ImportUtils($db, array('schema' => R3_EXPORT_SCHEMA));
        $utils->createImportSystemTable();
        $tmpTableName = $utils->getSchema().'.'.$utils->createTemporaryTableEntry('export_building', null, md5(rand()));

        $logger->log(LOG_INFO, "Materialyze final data into {$tmpTableName}");
        $db->exec("CREATE TABLE {$tmpTableName} AS {$sql}");
        $db->commit();

        $logger->log(LOG_INFO, "Exporting {$format}");
        $export = \R3Export::factory($driversMap[$format]);
        $opt = array(
            'destination_encoding' => 'UTF-8',
            'case_sensitive' => true);
        if ($format == 'shp') {
            $dsn = \ConfigClass::getDsn();
            $dsn2 = array(
                'phptype' => $dsn['dbtype'],
                'hostspec' => $dsn['dbhost'],
                'port' => empty($dsn['port']) ? 5432 : $dsn['port'],
                'username' => $dsn['dbuser'],
                'password' => $dsn['dbpass'],
                'database' => $dsn['dbname']);
            $export->setDsn($dsn2);
            $export->export($tmpTableName, $fileName, $db, $opt);
        } else {
            $export->createDatabase($fileName, $opt);
            $export->export($tmpTableName, 'Buildings', $db, $opt);
        }

        $export->closeDatabase();

        fclose($fp);
        if (!unlink($lockFileName)) {
            throw new \Exception("Error releasing lock \"{$lockFileName}\"");
        }

        if ($input->getOption('zip')) {
            $logger->log(LOG_INFO, "Zipping data");
            if ($format == 'shp') {
                $files = array();
                foreach (array('.shp', '.shx', '.dbf', '.prj', '.cpg') as $ext) {
                    $fileToZip = "{$fileName}{$ext}";
                    $files[] = $fileToZip;
                }
            } else {
                $files[] = $fileName;
            }
            $this->zipFiles("{$fileName}.zip", $files, $input->getOption('zip-prefix'));
            foreach ($files as $file) {
                if (!unlink($file)) {
                    throw new \Exceptino("Error removing \"{$file}\"");
                }
            }
        }

        $logger->log(LOG_INFO, "Removing old data");
        $utils->dropTemporaryTable($tmpTableName);
        $utils->cleanTemporaryTables();
    }
}