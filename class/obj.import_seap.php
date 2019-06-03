<?php

require_once R3_LIB_DIR . 'r3import_paes.php';
require_once R3_CLASS_DIR . 'obj.document.php';

class eco_import_seap extends eco_document {

    protected $fields;
    protected $table = 'document';

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array(
            'mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name')),
            'doc_file' => array('label' => _('Nome')),
            'doc_date' => array('label' => _('Data import'), 'width' => 100, 'type' => 'date'),
        );
        return $rows;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list';  // if true store the filter variables

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->id = (int) initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];
        $this->mu_id = initvar('mu_id');
        $this->documentType = 'import_seap';
        $this->isDialog = false;  // Cambia le azioni per edit / delete
        $this->parent_act = 'list';

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);

        $this->registerAjaxFunction('checkImport');
        $this->registerAjaxFunction('confirmDeleteImport');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        return _('Import PAES');
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array();
        $where[] = $q->expr->eq('do_id', $this->do_id);
        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS') && $this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->auth->getParam('mu_id')));
        }
        $where[] = $q->expr->eq('doct_code', "'SEAP'");
        $q->select("doc_id, doc_file_id, mu_name_{$lang} AS mu_name, doc_file, doc_date")
                ->from('ecogis.document_data doc')
                ->innerJoin('ecogis.municipality mu', 'doc.doc_object_id=mu_id');
        if (count($where) > 0) {
            $q->where($where);
        }
        // echo $q;
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($this->getTableColumnConfig(), $this->baseName);
        foreach ($tableColumns as $fieldName => $colDef) {
            if ($colDef['visible']) {
                $this->simpleTable->addSimpleField($colDef['label'], $fieldName, $colDef['type'], $colDef['width'], $colDef['options']);
            }
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_strategy_list_table');
    }

    public function dataImported($mu_id) {
        $mu_id = (int) $mu_id;
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select('COUNT(*)')->from('document_data')->where("doct_code='SEAP' AND doc_object_id={$mu_id}");
        return $db->query($q)->fetchColumn() > 0;
    }

    public function canDelete($mu_id) {
        $mu_id = (int) $mu_id;
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select('COUNT(*)')
                ->from('ecogis.global_plain gp')
                ->innerJoin('ecogis.global_plain_row gpr', 'gp.gp_id=gpr.gp_id')
                ->innerJoin('ecogis.global_plain_gauge gpg', 'gpr.gpr_id=gpg.gpr_id')
                ->where("mu_id={$mu_id} AND gp_imported_row IS TRUE");
        $tot = $db->query($q)->fetchColumn();
        if ($tot == 0) {
            $q = $db->createSelectQuery();
            $q->select('COUNT(*)')
                    ->from('ecogis.global_plain gp')
                    ->innerJoin('ecogis.global_plain_row gpr', 'gp.gp_id=gpr.gp_id')
                    ->where("mu_id={$mu_id} AND gp_imported_row IS TRUE AND gpr_imported_row IS FALSE");
            $tot = $db->query($q)->fetchColumn();
        }
        return $tot == 0;
    }

    public function canImport($mu_id) {
        $mu_id = (int) $mu_id;
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select('COUNT(*)')
                ->from('ecogis.global_plain gp')
                ->innerJoin('ecogis.global_plain_row gpr', 'gp.gp_id=gpr.gp_id')
                ->innerJoin('ecogis.global_plain_gauge gpg', 'gpr.gpr_id=gpg.gpr_id')
                ->where("mu_id={$mu_id} AND gp_imported_row is true");
        return $db->query($q)->fetchColumn() == 0;
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {
        if ($this->act == 'open') {
            // open/download the document
            $this->deliver('import_seap');
            die();
        }
        if ($this->act || $this->auth->getParam('mu_id') <> '') {
            if ($this->act <> 'add') {
                $db = ezcDbInstance::get();
                $q = $db->createSelectQuery();
                $q->select('*')->from('document_data');
                if ($this->auth->getParam('mu_id') <> '') {
                    $q->where("doct_code='SEAP' AND doc_object_id=" . $this->auth->getParam('mu_id'));
                } else {
                    $q->where("doct_code='SEAP' AND doc_id=" . (int) $this->id);
                }
                $q->orderBy("doc_id DESC");
                $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
                $vlu['doc_data'] = json_decode($vlu['doc_descr_1'], true);
                $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('document', $vlu['doc_id']));
            } else {
                $vlu = array();
            }
            $this->data = $vlu; // Save the data (prevent multiple sql)
            return $vlu;
        }
        return array();
    }

    public function getLookupData($id = null) {
        $lkp = array();

        if ($this->auth->getParam('mu_id') == '') {
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        return $lkp;
    }

    public function getPageVars() {
        return array('stay_to_edit' => $this->auth->getParam('mu_id') == '' ? 'F' : 'T');
    }

    public function getJSFiles() {
        return $this->includeJS(array('document.js', $this->baseName . '.js'), $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function importDataFromTemplate($fileName, $istat) {
        $db = ezcDbInstance::get();

        // setLocale('C');
        $paesImport = new R3ImportSEAP($db, $istat);
        $logger = new R3ImportSEAPLogger();
        $paesImport->setlogger($logger);
        try {
            $paesImport->load($fileName);
            if (!$paesImport->hasSheet('seap')) {
                throw New Exception(_("Il template caricato non è valido"));
            }
            $paesImport->apply($fileName);
            return array('done' => true, 'log' => $logger->getLogs());
        } catch (Exception $e) {
            return array('done' => false, 'log' => $e->getMessage());
        }
    }

    public function deleteImportedData($mu_id) {
        $db = ezcDbInstance::get();
        $mu_id = (int) $mu_id;
        $sql = "SELECT gst_id, mu_istat, ge_id, ge_id_2, gp_id
                FROM ecogis.global_strategy gs
                INNER JOIN ecogis.municipality mu ON gs.mu_id=mu.mu_id
                WHERE gs.mu_id={$mu_id}";
        $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

        $logger = new R3ImportSEAPLogger();
        $paesImport = new R3ImportSEAP($db, $data['mu_istat']);
        $paesImport->setlogger($logger);
        foreach (array('ge_id', 'ge_id_2') as $ge_id) {
            if ($data[$ge_id] <> '') {
                $paesImport->deleteInventoryConsumption($data[$ge_id]);
                $db->exec("DELETE FROM ecogis.global_entry WHERE ge_id={$data[$ge_id]} AND mu_id={$mu_id} AND ge_imported_row IS TRUE");
            }
        }
        if ($data['gp_id'] <> '') {
            $paesImport->deleteSeapActions($data['gp_id']);
            $db->exec("DELETE FROM ecogis.global_plain WHERE gp_id={$data['gp_id']} AND mu_id={$mu_id} AND gp_imported_row IS TRUE");
        }

        $sql = "SELECT COUNT(*) FROM ecogis.simulation_work WHERE gst_id={$data['gst_id']}";
        if ($db->query($sql)->fetchColumn() == 0) {
            // Cancello solo se non ho simulazioni collegate
            $db->exec("DELETE FROM ecogis.global_strategy WHERE gst_id={$data['gst_id']} AND mu_id={$mu_id} AND gst_imported_row IS TRUE");
        }
        return true;
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $db = ezcDbInstance::get();

        if ($this->act == 'del') {
            $id = (int) $request['id'];
            $mu_id = $db->query("SELECT mu_id
                                 FROM ecogis.document_data doc
                                 INNER JOIN ecogis.municipality mu ON doc.doc_object_id=mu_id AND doct_code='SEAP'
                                 WHERE do_id={$this->do_id} AND doc_id={$id}")->fetchColumn();
            $db->beginTransaction();
            $this->deleteImportedData($mu_id);
            // Delete document
            $file_info = $this->getDocFileInfo($id);
            $this->removeOldFile($file_info['doc_file'], 'import_seap', '', $file_info['doc_file_id']);
            $request['doc_id'] = $id;
            $id = $this->applyData($request);
            $db->commit();
            die();
        }
        $files = R3EcoGisHelper::getUploadedFile('doc_file');

        $errors = array();
        if ($files['error'] <> 0) {
            if ($files['error'] == UPLOAD_ERR_NO_FILE) {
                $errors['doc_file'] = array('CUSTOM_ERROR' => _('Bisogna indicare il file da caricare'));
            } else {
                $errors['doc_file'] = array('CUSTOM_ERROR' => _("Si è veificato un errore durante il caricamento del file. Si prega di riprovare. Errore #") . $files['error']);
            }
        }
        $mu_id = $this->auth->getParam('mu_id') <> '' ? $this->auth->getParam('mu_id') : (int) $this->mu_id;
        $istat = $db->query("SELECT mu_istat FROM ecogis.municipality WHERE do_id={$this->do_id} AND mu_id={$mu_id}")->fetchColumn();
        if ($istat == '') {
            $errors['mu_id'] = array('CUSTOM_ERROR' => _("Indicare il comune per cui si intende importare i dati"));
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        }

        $db->beginTransaction();
        $result = $this->importDataFromTemplate($files['tmp_name'], $istat);
        if (!$result['done']) {
            $errors['doc_file'] = array('CUSTOM_ERROR' => $result['log']);
            return $this->getAjaxErrorResult($errors);
        }
        // Insert into document
        // Remove old document
        $doc_id = $db->query("SELECT doc_id
                              FROM ecogis.document_data doc
                              INNER JOIN ecogis.municipality mu ON mu_id=doc_object_id
                              WHERE doct_code='SEAP' AND do_id={$this->do_id} AND doc_object_id={$mu_id}")->fetchColumn();
        if ($doc_id <> '') {
            $file_info = $this->getDocFileInfo($doc_id);
            $this->removeOldFile($file_info['doc_file'], 'import_seap', '', $file_info['doc_file_id']);
            $db->exec("DELETE FROM ecogis.document WHERE doc_id={$doc_id}");
        }

        $request['doc_title_1'] = _('Import PAES');
        $request['doc_descr_1'] = json_encode($result['log']);
        $request['doc_object_id'] = $mu_id;
        $request['doc_file'] = $files['name'];
        $request['doc_file_id'] = $this->getDocFileId();
        $request['doct_id'] = R3EcoGisHelper::getDocumentTypeIdByCode('SEAP');
        $request['doc_date'] = date('Y-m-d');
        $this->addFile($request['doc_file'], 'import_seap', '', $request['doc_file_id'], $files['tmp_name']);
        $id = $this->applyData($request);
        $db->commit();

        R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneImportSeap($id)");
    }

    public function checkImport($request) {
        if (!$this->canImport($request['mu_id'])) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile importare i dati, poichè vi sono degli indicatori legati alle azioni'));
        }
        if ($this->dataImported($request['mu_id'])) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => _("Un import PAES è già stato effettuato. Si desidera sostituirlo?"));
        }
        return array('status' => R3_AJAX_NO_ERROR, 'can_import' => true);
    }

    public function confirmDeleteImport($request) {
        $db = ezcDbInstance::get();
        $mu_id = $db->query("SELECT doc_object_id FROM ecogis.document WHERE doc_id=" . (int) $request['id'])->fetchColumn();

        if (!$this->canDelete($mu_id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile importare i dati, poichè vi sono degli indicatori legati alle azioni o azioni inserite manualmente'));
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => _("Eliminare l'import selezionato e tutti i dati ad esso associati (Strategie generali, inventario emissioni, PAES)? NOTA: Alcuni dati caricati, quali i fattori di conversione utilizzati, non saranno aggiornati"));
    }

    public function checkPerm() {
        $mu_id = $this->auth->getParam('mu_id');
        $this->act = $mu_id == '' ? $this->act : 'show';

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
