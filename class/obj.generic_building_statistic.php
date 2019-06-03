<?php

use EcogisBundle\Repository\BuildingStatisticRepository;
use EcogisBundle\Repository\UtilitySupplierRepository;
use EcogisBundle\Repository\HeatingDegreeDaysRepository;

class eco_generic_building_statistic extends R3AppBaseObject
{

    public function __construct(array $request = array(), array $opt = array())
    {
        parent::__construct($request, $opt);

        $this->act = initVar('act', 'show');
        $this->tab_mode = initVar('tab_mode');
        $this->stat_type = initVar('stat_type', 'compact');
        $this->udm_divider = initVar('udm_divider', 1000);
        $this->mu_id = initVar('mu_id');
        $this->bpu_id = initVar('bpu_id');
        $this->do_id = $_SESSION['do_id'];

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

    private function getMunicipalityId() {
        $auth = R3AuthInstance::get();
        if ($this->auth->getParam('mu_id') == '') {
            return $this->mu_id;
        } else {
            return $this->auth->getParam('mu_id');
        }
    }

    private function getCompactStatsData() {

    }

    private function getBuildingPurposeUseStatsData() {
        
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = NULL)
    {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $mu_id = $this->getMunicipalityId();

        $heatingDegreeDaysRepository = new HeatingDegreeDaysRepository();
        $utilitySupplierRepository = new UtilitySupplierRepository();
        $buildingStatisticRepository = new BuildingStatisticRepository();
        
        $heating2label = array();
        if (empty($mu_id)) {
            $hasHeatingDegreeDay = $heatingDegreeDaysRepository->hasHeatingDegreeDayForDomain($this->do_id);
            $heating2label = $utilitySupplierRepository->getHeatingConcatNameForDomain($this->do_id, $lang);
        } else {
            $hasHeatingDegreeDay = $heatingDegreeDaysRepository->hasHeatingDegreeDayForMunicipality($mu_id);
            $heating2label = $utilitySupplierRepository->getHeatingConcatNameForMunicipality($mu_id, $lang);
        }

        if ($this->stat_type == 'building_purpose_use') {
            $rows = $buildingStatisticRepository->getStatisticForMunicipalityAndBpu($mu_id, $lang, $this->bpu_id, $this->udm_divider);
        } else {
            $rows = $buildingStatisticRepository->getStatisticForMunicipality($mu_id, $this->udm_divider);
        }
        if ($this->udm_divider == 1000) {
            $consumptionUnit = _('MWh/anno');
            $emissionUnit = _('t CO<sub>2</sub>/anno');
        } else {
            $consumptionUnit = _('kWh/anno');
            $emissionUnit = _('kg CO<sub>2</sub>/anno');
        }

        $data = array('rows' => $rows);
        $data['has_heating_degree_day'] = $hasHeatingDegreeDay;
        $data['heating2_label'] = $heating2label;
        $vlu['mu_id'] = $this->mu_id;
        $vlu['stat_type'] = $this->stat_type;
        $vlu['bpu_id'] = $this->bpu_id;
        $vlu['udm_divider'] = $this->udm_divider;

        $vlu['consumption_unit'] = $consumptionUnit;
        $vlu['emission_unit'] = $emissionUnit;
        $vlu['data'] = $data;

        return $vlu;
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
    public function getLookupData($id = null) {
        $lkp = array();

        if ($this->auth->getParam('mu_id') == '') {
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $lkp['stat_types'] = array(
            "compact"=>_("Compatta"),
            "building_purpose_use"=>_("Espansa per destinazione d'uso"));
        $lkp['udm_dividers'] = array(
            1=>_("kWh/anno"),
            1000=>_("MWh/anno"));
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