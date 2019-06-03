<?php

use EcogisBundle\Repository\BuildingStatisticRepository;
use EcogisBundle\Repository\UtilitySupplierRepository;
use EcogisBundle\Repository\HeatingDegreeDaysRepository;

class ecoCustomPalette extends ezcGraphPalette
{
    protected $axisColor = '#000000';
    //protected $majorGridColor = '#000000BB';
    protected $dataSetColor = array();
    protected $dataSetSymbol = array(
        ezcGraph::SQUARE,
    );
    protected $fontName = 'sans-serif';
    protected $fontColor = '#555753';

    public function __construct(array $dataSetColor)
    {
        $this->dataSetColor = $dataSetColor;
    }
}

class eco_generic_building_statistic_graph extends R3AppBaseObject
{

    public function __construct(array $request = array(), array $opt = array())
    {
        parent::__construct($request, $opt);

        $this->act = initVar('act', 'show');
        $this->tab_mode = initVar('tab_mode');
        $this->stat_type = initVar('stat_type', 'compact');
        $this->udm_divider = initVar('udm_divider', 1000);
        $this->mu_id = (int) initVar('mu_id');
        $this->bpu_id = (int) initVar('bpu_id');
        $this->do_id = $_SESSION['do_id'];
        $this->width = min(max((int) initVar('width'), 250), 1280);
        $this->height = min(max((int) initVar('height'), 250), 1024);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
    }

    public function getPageTitle()
    {
        return _("Statistiche edifici");
    }

    public function getListSQL()
    {
        
    }

    public function createListTableHeader(&$order)
    {
        
    }

    private function getMunicipalityId()
    {
        $auth = R3AuthInstance::get();
        if ($this->auth->getParam('mu_id') == '') {
            return $this->mu_id;
        } else {
            return $this->auth->getParam('mu_id');
        }
    }

    private function getCompactStatsData()
    {

    }

    private function getBuildingPurposeUseStatsData()
    {
        
    }

    /**
     * Try to return the font file-name and extendsion from the font name
     * @param type $fontName
     * @return type
     */
    private function getFontPath($fontName)
    {
        $cmd = "fc-match --format=%{file} ".escapeshellarg($fontName);
        $name = exec($cmd, $output, $retVal);
        if ($retVal == 0) {
            if (file_exists($name) && is_readable($name)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = NULL)
    {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $mu_id = $this->getMunicipalityId();

        session_write_close();

        $heatingDegreeDaysRepository = new HeatingDegreeDaysRepository();
        $utilitySupplierRepository = new UtilitySupplierRepository();
        $buildingStatisticRepository = new BuildingStatisticRepository();

        try {
            if (empty($mu_id)) {
                $hasHeatingDegreeDay = $heatingDegreeDaysRepository->hasHeatingDegreeDayForDomain($this->do_id);
                $heating2label = $utilitySupplierRepository->getHeatingConcatNameForDomain($this->do_id, $lang);
            } else {
                $hasHeatingDegreeDay = $heatingDegreeDaysRepository->hasHeatingDegreeDayForMunicipality($mu_id);
                $heating2label = $utilitySupplierRepository->getHeatingConcatNameForMunicipality($mu_id, $lang);
            }

            if ($this->stat_type == 'building_purpose_use' && $this->bpu_id > 0) {
                $rows = $buildingStatisticRepository->getStatisticForMunicipalityAndBpu($mu_id, $lang, $this->bpu_id,
                    $this->udm_divider);
            } else {
                $rows = $buildingStatisticRepository->getStatisticForMunicipality($mu_id, $this->udm_divider);
            }
            if ($this->udm_divider == 1000) {
                $consumptionUnit = _('MWh/anno');
                $emissionUnit = _('t CO2/anno');
            } else {
                $consumptionUnit = _('kWh/anno');
                $emissionUnit = _('kg CO2/anno');
            }

            $fontFile = $this->getFontPath('verdana');

            $graphBarColors = array();

            $graphData = array();
            foreach ($rows as $row) {
                $year = $row['co_year'];
                if (!empty($row['heating_gg'])) {
                    $graphData['heating'][$row['co_year']] = $row['heating_gg'];
                }
                if (!empty($row['heating_utility_gg'])) {
                    $graphData['heating_utility'][$row['co_year']] = $row['heating_utility_gg'];
                }
                if (!empty($row['electricity'])) {
                    $graphData['electricity'][$row['co_year']] = $row['electricity'];
                }
                if (!empty($row['co2_gg'])) {
                    $graphData['co2'][$row['co_year']] = $row['co2_gg'];
                }
            }

            $colors = array();
            if (!empty($graphData['heating'])) {
                $colors[] = '#66cc99';
            }
            if (!empty($graphData['heating_utility'])) {
                $colors[] = '#333FFF';
            }
            if (!empty($graphData['electricity'])) {
                $colors[] = '#ff3333';
            }
            if (!empty($graphData['co2'])) {
                $colors[] = '#999999';
            }

            $graph = new ezcGraphBarChart();
            $graph->palette = new ecoCustomPalette($colors);
            if ($fontFile === null) {
                $graph->driver = new ezcGraphSvgDriver();
            } else if (extension_loaded('cairo_wrapper')) {
                $graph->driver = new ezcGraphCairoDriver();
                $graph->options->font = $fontFile;
            } else {
                $graph->driver = new ezcGraphGdDriver();
                $graph->options->font = $fontFile;
                $graph->driver->options->supersampling = 1;
                $graph->driver->options->jpegQuality = 100;
                $graph->driver->options->imageFormat = IMG_PNG;
            }
            $graph->options->font->minFontSize = 7;
            $graph->options->font->maxFontSize = 8;
            $graph->title->font->maxFontSize = 14;
            $graph->yAxis->label = " {$consumptionUnit}";

            if (!empty($graphData['heating'])) {
                $graph->data["Riscaldamento"] = new ezcGraphArrayDataSet($graphData['heating']);
            }
            if (!empty($graphData['heating_utility'])) {
                $graph->data["{$heating2label}"] = new ezcGraphArrayDataSet($graphData['heating_utility']);
            }
            if (!empty($graphData['electricity'])) {
                $graph->data["Energia elettrica"] = new ezcGraphArrayDataSet($graphData['electricity']);
            }
            if (!empty($graphData['co2'])) {
                $graph->data["CO2"] = new ezcGraphArrayDataSet($graphData['co2']);

                // Additional axis
                $co2Axis = new ezcGraphChartElementNumericAxis();
                $co2Axis->axisSpace = 0.12;
                $graph->additionalAxis['co2'] = $co2Axis;
                $co2Axis->label = " {$emissionUnit}";
                $co2Axis->position = ezcGraph::BOTTOM;
                $co2Axis->chartPosition = 1;
                $co2Axis->min = 0;
                $graph->data["CO2"]->yAxis = $co2Axis;
                $graph->data["CO2"]->symbol = ezcGraph::BULLET;
                Header("Content-Type: image/png");
            }
            $graph->renderer->options->barMargin = .15;
            $graph->renderToOutput($this->width, $this->height);

            die;  // Need to output data
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getPageVars()
    {
        return array(
            'tab_mode' => $this->tab_mode,
        );
    }

    public function getJSFiles()
    {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS($this->baseName.'.js', false); // inline js
    }

    public function getTemplateName()
    {
        return 'generic_building_statistic.tpl';
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null)
    {
        $lkp = array();

        if ($this->auth->getParam('mu_id') == '') {
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $lkp['stat_types'] = array(
            "compact" => _("Compatta"),
            "building_purpose_use" => _("Espansa per destinazione d'uso"));
        $lkp['udm_dividers'] = array(
            1 => _("kWh/anno"),
            1000 => _("MWh/anno"));
        $lkp['bpu_values'] = R3EcoGisHelper::getBuildingPurposeUseList($this->do_id);
        return $lkp;
    }

    public function checkPerm()
    {
        $act = 'SHOW';
        $name = 'STATISTIC';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }
}