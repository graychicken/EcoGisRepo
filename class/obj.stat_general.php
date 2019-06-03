<?php

class eco_stat_general extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'stat_general';

    /**
     * ecogis.global_strategy fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'sg_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'sg_title_1', 'type' => 'text', 'required' => false, 'label' => _('Titolo')),
            array('name' => 'sg_title_2', 'type' => 'text', 'required' => false, 'label' => _('Titolo')),
            array('name' => 'sg_upper_text_1', 'type' => 'text', 'required' => false, 'label' => _('Testo superiore')),
            array('name' => 'sg_upper_text_2', 'type' => 'text', 'required' => false, 'label' => _('Testo superiore')),
            array('name' => 'sg_lower_text_1', 'type' => 'text', 'required' => false, 'label' => _('Testo inferiore')),
            array('name' => 'sg_lower_text_2', 'type' => 'text', 'required' => false, 'label' => _('Testo inferiore')),
            array('name' => 'do_id', 'type' => 'integer')  // Calculated
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $this->act = initVar('act', 'mod');
        $this->do_id = $_SESSION['do_id'];

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        return 'Statistiche - Parametri generali';  // Unknown title
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {
        
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {
        $db = ezcDbInstance::get();
        $sql = "SELECT * FROM stat_general WHERE do_id=" . $this->do_id;
        $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($vlu === false) {
            $vlu = array('sg_id' => null);
        }
        $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('stat_general', $vlu['sg_id']));
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $db = ezcDbInstance::get();
        $errors = array();

        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $sql = "SELECT sg_id FROM stat_general WHERE do_id=" . $this->do_id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($vlu === false) {
                // Switch to add
                $request['act'] = 'add';
            } else {
                $request['sg_id'] = $vlu['sg_id'];
            }
            $request['do_id'] = $this->do_id;

            $id = $this->applyData($request);
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneStatGeneral($id)");
        }
    }

    public function checkPerm() {
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm('MOD', $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), 'MOD', $name));
        }
    }

}
