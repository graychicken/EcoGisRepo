<?php

class eco_cache extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->act = initVar('act', 'set');
        $this->do_id = $_SESSION['do_id'];

        $this->registerAjaxFunction('delCache');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        return _('Gestione cache');
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getData($id = null) {
        
    }

    public function delCache($request) {
        if (isset($request['del_preview_map']) && $request['del_preview_map'] == 'T') {
            R3EcoGisCacheHelper::resetMapPreviewCache(null);
        }
        if (isset($request['del_preview_photo']) && $request['del_preview_photo'] == 'T') {
            R3EcoGisCacheHelper::resetPhotoPreviewCache(null);
        }
        if (isset($request['del_temp_files']) && $request['del_temp_files'] == 'T') {
            R3EcoGisCacheHelper::removeTmpFiles();
            R3EcoGisCacheHelper::removeMapOutputFiles(null, true);
        }
        return array('status' => R3_AJAX_NO_ERROR);
    }

    public function checkPerm() {
        $act = 'SET';
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
