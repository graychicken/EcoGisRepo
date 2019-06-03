<?php

use EcogisBundle\Repository\BuildingStatisticRepository;
use EcogisBundle\Repository\UtilitySupplierRepository;
use EcogisBundle\Repository\HeatingDegreeDaysRepository;

class eco_building_statistic extends R3AppBaseObject
{

    public function __construct(array $request = array(), array $opt = array())
    {
        parent::__construct($request, $opt);

        $this->act = initVar('act', 'show');
        $this->tab_mode = initVar('tab_mode');
        $this->bu_id = PageVar('bu_id');
        $this->parent_act = PageVar('parent_act');
        $this->kind = strtoupper(PageVar('kind'));
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
    }

    public function getPageTitle()
    {
        
    }

    public function getListSQL()
    {
        
    }

    public function createListTableHeader(&$order)
    {
        
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null)
    {
        $id = (int) $this->bu_id;
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $heatingDegreeDaysRepository = new HeatingDegreeDaysRepository();
        $utilitySupplierRepository = new UtilitySupplierRepository();
        $buildingStatisticRepository = new BuildingStatisticRepository();

        // Close session (to allow other tab to be loaded)
        session_write_close();

        // Has heating degree days
        $buildingData = R3EcoGisHelper::getBuildingData($id);
        $muId = $buildingData['mu_id'];

        $hasHeatingDegreeDay = $heatingDegreeDaysRepository->hasHeatingDegreeDayForMunicipality($muId);
        $heating2label = $utilitySupplierRepository->getHeatingConcatNameForMunicipality($muId, $lang);

        $rows = $buildingStatisticRepository->getStatisticForBuilding($id);
        $data = array('rows' => $rows);
        $data['has_heating_degree_day'] = $hasHeatingDegreeDay;
        $data['heating2_label'] = $heating2label;
        return array('data' => $data);
    }

    public function getPageVars()
    {
        return array(
            'tab_mode' => $this->tab_mode,
            'bu_id' => $this->bu_id,
            'parent_act' => $this->parent_act,
            'kind' => $this->kind,
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
        return 'building_statistic.tpl';
    }

    public function checkPerm()
    {
        $act = 'SHOW';
        $name = 'STATISTIC';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        R3Security::checkBuilding($this->bu_id);
    }
}