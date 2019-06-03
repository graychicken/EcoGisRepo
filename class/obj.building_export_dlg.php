<?php

use EcogisBundle\Repository\BuildingStatisticRepository;
use EcogisBundle\Repository\UtilitySupplierRepository;
use EcogisBundle\Repository\HeatingDegreeDaysRepository;

class eco_building_export_dlg extends R3AppBaseObject
{

    public function __construct(array $request = array(), array $opt = array())
    {
        parent::__construct($request, $opt);
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

    public function getData($id = null)
    {

    }

    public function getPageVars()
    {

    }

    public function getJSFiles()
    {

    }

    public function getTemplateName()
    {
        return 'building_export_dlg.tpl';
    }

    public function checkPerm()
    {
        $act = 'EXPORT';
        $name = 'BUILDING';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }
}