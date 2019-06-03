<?php

class eco_document extends R3AppBaseFileObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'document';

    /**
     * ecogis.document fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'doc_id', 'type' => 'integer', 'label' => _('doc_id'), 'is_primary_key' => true),
            array('name' => 'doc_file_id', 'type' => 'integer', 'label' => _('doc_file_id')),
            array('name' => 'doct_id', 'type' => 'lookup', 'required' => true, 'label' => _('doct_id'), 'lookup' => array('table' => 'document_type')),
            array('name' => 'doc_object_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'mixed', 'field' => 'mixed')),
            array('name' => 'doc_title_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Titolo')),
            array('name' => 'doc_title_2', 'type' => 'text', 'size' => 80, 'label' => _('Titolo')),
            array('name' => 'doc_date', 'type' => 'date', 'required' => true, 'label' => _('Data')),
            array('name' => 'doc_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'doc_descr_2', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'doc_file', 'type' => 'text', 'size' => 64, 'required' => $this->act == 'add', 'label' => _('File')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->file_id = initVar('file_id');
        $this->doc_object_id = initVar('doc_object_id');
        $this->type = initVar('type');
        $this->act = initVar('act', 'list');
        $this->tab_mode = initVar('tab_mode');

        $this->parent_act = PageVar('parent_act');
        $this->disposition = initVar('disposition', 'inline');
        $this->limit = 0;                                    
        $this->fields = $this->defFields();
        $this->documentType = 'document';
        $this->isDialog = true;

        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('confirm_delete_document');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array();
        $where[] = 'doc_object_id=' . (int) $this->doc_object_id . " AND doct_code=" . $db->quote(strtoupper($this->type));
        $q->select("doc_id, doc_file_id, doc_title_$lang AS doc_title, doc_date, doc_file")
                ->from('document_data')
                ->where($where)
                ->orderBy('doc_title, doc_date DESC, doc_id DESC');
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        $this->simpleTable->addSimpleField(_('Titolo'), 'doc_title', 'text');
        $this->simpleTable->addSimpleField(_('Data'), 'doc_date', 'date', 100);
        $this->simpleTable->addSimpleField(_('File'), 'doc_file', 'calculated', 250);
        $this->simpleTable->addSimpleField(_('Dim.'), 'doc_size', 'calculated', 80, array('align' => 'right'));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 80);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['doc_id'];
        $file_id = $row['doc_file_id'];
        $openExt = $this->auth->getConfigValue('APPLICATION', 'DOCUMENT_OPEN_EXT', array());

        $name = $this->getDocFullName($row['doc_file'], $this->documentType, '', $file_id, false);
        $fileExists = file_exists($name);

        $ext = strtolower(substr(strrchr($row['doc_file'], '.'), 1));
        $s = R3_WEB_IMAGES_DIR . "icons/ico_{$ext}.gif";
        if (file_exists($s)) {
            $ico = R3_ICONS_URL . "ico_{$ext}.gif";
        } else {
            $ico = R3_ICONS_URL . 'default.gif';
        }
        if ($fileExists && in_array($ext, $openExt)) {
            $this->simpleTable->addCalcValue('doc_file', sprintf("<a href=\"javascript:openDocument('{$file_id}', '{$this->documentType}')\" class=\"document\"><img src=\"$ico\" border=\"0\"> %s</a>", $row['doc_file']));
        } else {
            $this->simpleTable->addCalcValue('doc_file', sprintf("<img src=\"$ico\"> %s", $row['doc_file']));
        }
        if ($fileExists) {
            $this->simpleTable->addCalcValue('doc_size', sprintf("%s KB", R3NumberFormat(ceil(filesize($name) / 1024), null, true)));
        } else {
            $this->simpleTable->addCalcValue('doc_size', '-');
        }
        $links = array();
        $canShow = false;
        $objName = strToUpper($this->baseName);
        $parent_act = $this->parent_act == 'show' ? $this->parent_act : 'mod';
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        if ($fileExists) {
                            $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Scarica'), "javascript:downloadDocument('{$file_id}', '{$this->documentType}')", "", "{$this->imagePath}ico_doc_download.gif");
                        } else {
                            $links['SHOW'] = $this->simpleTable->AddLinkCell('', '', '', "{$this->imagePath}ico_spacer.gif");
                        }
                        break;
                    case 'mod':
                        if ($this->parent_act <> 'show') {
                            if ($this->isDialog) {
                                $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modDocument('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                            } else {
                                $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                            }
                        }
                        break;
                    case 'del':
                        if ($this->parent_act <> 'show') {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelDocument('{$id}', '{$this->documentType}')", "", "{$this->imagePath}ico_{$act}.gif");
                        }
                        break;
                }
            } else {
                $links[] = $this->simpleTable->AddLinkCell('', '', '', "{$this->imagePath}ico_spacer.gif");
            }
        }

        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['doc_id'] == $this->last_id)
            return array('normal' => 'selected_row');
        return array();
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {

        $db = ezcDbInstance::get();
        if ($this->act == 'open') {
            // open/download the document
            $this->deliver($this->documentType);
            die();
        }

        $db = ezcDbInstance::get();
        if ($this->act == 'add') {
            $sep = R3Locale::getDateSeparator();
            $vlu = array();
            $vlu['doc_date'] = date('Y-m-d');
        } else {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('document_data')
                    ->where('doc_id=' . (int) $this->id);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('document', $vlu['doc_id']));
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    public function getPageVars() {
        return array('tab_mode' => $this->tab_mode,
            'type' => $this->type,
            'doc_object_id' => $this->doc_object_id,
            'parent_act' => $this->parent_act,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array(
            'txtShowDocument' => _('Visualizza documento'),
            'txtAddDocument' => _('Aggiungi documento'),
            'txtModDocument' => _('Modifica documento'),
            'txtUploadVirus' => _('Attenzione! Il file non può essere salvato nel sistema perchè si ritiene che possa contiene un virus.'),
            'popupAlreadyOpen' => _('Attenzione: una finestra di visualizzazione documento è già aperta. Chiuderla prima di proseguire'),
            'PopupErrorMsg' => _('Attenzione: Impossibile aprire la fimnestra cdei documenti: blocco dei popup attivo'));
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();

        if (isset($_FILES['doc_file'])) {
            if (is_array($_FILES['doc_file']['name'])) {
                // Consider only the first file
                $files = array('name' => $_FILES['doc_file']['name'][0],
                    'type' => $_FILES['doc_file']['type'][0],
                    'tmp_name' => $_FILES['doc_file']['tmp_name'][0],
                    'error' => $_FILES['doc_file']['error'][0],
                    'size' => $_FILES['doc_file']['size'][0]);
            } else {
                $files = array('name' => $_FILES['doc_file']['name'],
                    'type' => $_FILES['doc_file']['type'],
                    'tmp_name' => $_FILES['doc_file']['tmp_name'],
                    'error' => $_FILES['doc_file']['error'],
                    'size' => $_FILES['doc_file']['size']);
            }
        } else {
            $files = array('name' => null,
                'type' => null,
                'tmp_name' => null,
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => null);
        }
        $request['doc_id'] = forceInteger($request['id'], 0, false, '.');
        $request['type'] = strtoupper($request['type']);
        // Foreign key
        $file_info = $this->getDocFileInfo($request['doc_id']);
        if ($this->act == 'mod') {
            // Rimuove il campo: Evita lo spostamento di oggetti da una tipologia ad un altra
            $this->removeField('doc_object_id');
        } else if (in_array($request['type'], array('BUILDING', 'BUILDING_PHOTO', 'BUILDING_THERMOGRAPHY', 'BUILDING_LABEL'))) {
            // Check foreign key per edifici
            $this->setFieldAttrib('doc_object_id', array('lookup' => array('table' => 'building', 'field' => 'bu_id')));
        } else if (in_array($request['type'], array('STREET_LIGHTING'))) {
            // Check foreign key per illuminazione pubblica
            $this->setFieldAttrib('doc_object_id', array('lookup' => array('table' => 'street_lighting', 'field' => 'sl_id')));
        } else if (in_array($request['type'], array('GLOBAL_ENTRY'))) {
            // Check foreign key per illuminazione pubblica
            $this->setFieldAttrib('doc_object_id', array('lookup' => array('table' => 'global_entry', 'field' => 'ge_id')));
        } else if (in_array($request['type'], array('GLOBAL_PLAIN'))) {
            // Check foreign key per illuminazione pubblica
            $this->setFieldAttrib('doc_object_id', array('lookup' => array('table' => 'global_plain', 'field' => 'gp_id')));
        }

        if ($files['error'] == 0) {
            $request['doc_file'] = $files['name'];
        }
        if ($this->act <> 'del') {
            if ($this->act == 'add') {
                $request['doct_id'] = R3EcoGisHelper::getDocumentTypeIdByCode($request['type']);
            } else {
                $request['doct_id'] = R3EcoGisHelper::getDocumentTypeByDocumentId($request['doc_id']);
            }
            $errors = $this->checkFormData($request);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db = ezcDbInstance::get();
            $db->beginTransaction();
            if ($files['error'] == 0) {
                if ($this->hasVirus($files['tmp_name']) === true) {
                    // Verifica la presenza di un virus nel file da caricare
                    return array('status' => R3_AJAX_NO_ERROR,
                        'js' => "submitFormDataDocumentVirusError()");
                }
                if ($file_info !== false) {
                    //Remove the old file (Replacement)
                    $this->removeOldFile($file_info['doc_file'], 'document', '', $file_info['doc_file_id']);
                }
                $new_id = $this->getDocFileId($request['doc_id']);
                $request['doc_file_id'] = $new_id;
                $this->addFile($request['doc_file'], 'document', '', $request['doc_file_id'], $files['tmp_name']);
            } else if ($this->act == 'del') {
                $this->removeOldFile($file_info['doc_file'], 'document', '', $file_info['doc_file_id']);
            } else {
                // Keey the old values
                $request['doc_file'] = $file_info['doc_file'];
                $request['doc_file_id'] = $file_info['doc_file_id'];
            }
            $id = $this->applyData($request);
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneDocument($id)");
        }
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    protected function hasVirus($fileName) {
        if ($this->auth->getConfigValue('APPLICATION', 'ANTIVIRUS_CMD') <> '') {
            $antivirusCmd = escapeshellcmd(str_replace('%1', $fileName, $this->auth->getConfigValue('APPLICATION', 'ANTIVIRUS_CMD')));
            exec($antivirusCmd, $output, $ret);
            return $ret != 0;
        }
        return null;    // No antivirus software installed or configured
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirm_delete_document($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $name = $db->query("SELECT doc_title_$lang AS doc_title FROM document WHERE doc_id=" . (int) $request['id'])->fetchColumn();
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_('Sei sicuro di voler cancellare il documento "%s"?'), $name));
    }

    /**
     * Deliver a file
     */
    public function deliver($kind) {
        $file_info = $this->getDocFileInfoByFileId($this->file_id);
        if ($file_info === false) {
            $errorMessage = _("Documento non trovato");
            echo "<script language=\"javascript\">
                 document.write(\"$errorMessage\");
                 </script>";
            die();
        }
        $name = $this->getDocFullName($file_info['doc_file'], $kind, '', $file_info['doc_file_id'], false);
        if ($this->hasVirus($name) === true) {
            $virusMessage = _("ATTENZIONE! E' stato impedito lo scaricamento del file desiderato in quanto si ritiene che possa contenere un virus");
            echo "<script language=\"javascript\">
                 document.write(\"$virusMessage\");
                 </script>";
            die();
        }
        deliverFile($name, array('name' => $file_info['doc_file'],
            'disposition' => $this->disposition,
            'cacheable' => $this->auth->getConfigValue('APPLICATION', 'DOCUMENT_CACHE_TTL') > 0,
            'cache_ttl' => $this->auth->getConfigValue('APPLICATION', 'DOCUMENT_CACHE_TTL')));
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if ($this->act == 'open') {
            // Download
            R3Security::checkDocumentByFileId($this->file_id);
        } else {
            // Attribute
            R3Security::checkDocumentForObject($this->act, $this->doc_object_id, $this->id, array('kind' => $this->type));
        }
    }

}
