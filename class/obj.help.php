<?php

class eco_help extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->id = initVar('id');
        $this->act = initVar('act', 'show');
    }

    public function getPageTitle() {
        return _('Aiuto');
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getData($id = null) {
        
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
