<?php

define('CLASS_PREFIX', 'lkp_');

define('MISSING_VALUE', 'MISSING_VALUE');  // Valore mancante nella form
define('INVALID_VALUE', 'INVALID_VALUE');  // Valore non valido form
define('UNIQUE_VIOLATION', 'UNIQUE_VIOLATION');  // Valore non univoco
define('INVALID_LOOKUP_VALUE', 'INVALID_LOOKUP_VALUE');  // Valore di lookup non valido form
define('INVALID_SIZE', 'INVALID_SIZE');  // Lunghezza testo non valida 
define('INVALID_DATE_FROM_TO_VALUE', 'INVALID_DATE_FROM_TO_VALUE');  // data 2 > data 1
define('CUSTOM_ERROR', 'CUSTOM_ERROR');  // Errore customizzato. Il testo dell'errore è presente nel valore dell'array

define('R3_AJAX_NO_ERROR', 'OK');
define('R3_AJAX_ERROR', 'ERROR');

require_once R3_LIB_DIR . 'obj.base_locale.php';
require_once R3_LIB_DIR . 'dbfunc.php';
require_once R3_LIB_DIR . 'r3dbcatalog.php';


// Error handle for ajax request
$ajaxResponseType = '';

function ajaxErrorHandler($errno, $errstr, $errfile, $errline) {
    global $ajaxResponseType;

    if (ini_get('error_reporting') == 0) {  // @ before command
        return false;
    }
    if ($errno == E_STRICT) {
        return false;   // Not checked now
    }
    $kind = 'Error';
    switch ($errno) {
        case E_WARNING: $kind = 'Warning';
            break;
        case E_NOTICE: $kind = 'Notice';
            break;
    }
    $text = "$kind: $errstr in $errfile at line $errline";
    if ($ajaxResponseType == 'JSON') {
        echo json_encode(array('exception' => $text));
    } else {
        echo $text;
    }
    die();
    return true;  /* Don't execute PHP internal error handler */
}

class R3LookupController {

    /**
     * Return the type of the given object
     *
     * @param array request      the $_REQUEST object. Extract the 'on' param to get the object type
     * @param bool upper         if TRUE the return value is in UPPER CASE
     * @return string            a new object of $_REQUEST['on'] type
     * @access public
     */
    public static function getObjectType(array $request = array(), $upper = false) {

        if (!isset($request['on'])) {
            throw new Exception('Invalid request for parameter "on"');
        }
        $name = basename($request['on']);
        if ($upper) {
            return strToUpper($name);
        } else {
            return $name;
        }
    }

    /**
     * Return the type of the given object
     *
     * @param array request      the $_REQUEST object. Extract the 'on' param to get the object type
     * @param bool upper         if TRUE the return value is in UPPER CASE
     * @return string            a new object of $_REQUEST['on'] type
     * @access public
     */
    public static function getObjectAction(array $request = array(), $upper = false) {
        if (!isset($request['act']) || $request['act'] == '') {
            return 'list';
        }
        return strTolower($request['act']);
    }

    /**
     * Return the id of the object
     *
     * @param array request      the $_REQUEST object. Extract the 'on' param to get the object type
     * @return mixed             $_REQUEST['id']
     * @access public
     */
    public static function getObjectId(array $request = array()) {
        return isset($request['id']) ? $request['id'] : null;
    }

    /**
     * Return the class name of the given object
     *
     * @param array request    the $_REQUEST object. Extract the 'on' param to get the object type
     * @return string          the calss name
     * @access public
     */
    public static function getObjectClassName(array $request = array()) {

        return CLASS_PREFIX . R3LookupController::getObjectType($request);   // Prefix!!!
    }

    /**
     * Return the object of the given type catched from request var
     *
     * @param array request   the $_REQUEST object. Extract the 'on' param to get the object type
     * @param array opt       option array. See class documentation for valid options
     * @return                a new object of $_REQUEST['on'] type
     * @access public
     */
    public static function factory(array $request = array(), array $opt = array()) {

        $name = R3LookupController::getObjectType($request);
        $fileName = R3_LOOKUP_DIR . 'lkp.' . $name . '.php';
        if (!file_exists($fileName)) {
            throw new Exception('Invalid value for parameter "on": "' . $request['on'] . '"');
        }

        require_once $fileName;
        $className = R3LookupController::getObjectClassName($request);
        return new $className($request, $opt);
    }

}

/**
 * The base R3AppBaseObject class
 *
 */
abstract class R3LookupBaseObject {

    // The object name
    protected $baseName;
    // Nome tabella
    protected $table;
    // Nome view (visualizza i dati. Se vuota, usa $table)
    protected $view;
    // Definizione campi
    protected $fields;
    // Definizione campi
    protected $filterTitle;
    // Azioni standard (add, mod, del, show)
    protected $actions;
    // Se true controlla foreign key per ogni riga per cambiare il bottone di cancellazione
    protected $checkForeignKey = false;
    // Se true effettua sempre il check per il dominio
    protected $checkDomian = false;
    // If true the table has an UUID field (called uuid)
    protected $UUID;
    // List limit
    protected $limit;
    // Page
    protected $pg;
    // Order
    protected $order;

    /**
     * Constructor
     */
    public function __construct(array $request = array(), array $opt = array()) {
        if (!isset($request['act'])) {
            $request['act'] = 'list';
        }
        $this->request = $request;
        $this->opt = $opt;
        $this->baseName = substr(get_class($this), strlen(CLASS_PREFIX));
        $this->filterTitle = _("Filtro:");
        $this->actions = array('SHOW', 'ADD', 'MOD', 'DEL');

        // Set the locale for text only
        //setLang(R3Locale::getLanguageCode(), LC_MESSAGES);
        //setLangInfo(array('thousands_sep'=>"."));
        if (defined('USE_JQGRID')) {
            $this->registerAjaxFunction('getListData');
        }
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('confirmDeleteLookup');

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->isFilter = initVar('is_filter');
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];
        $this->id = initVar('id');
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);

        $this->fields = $this->defFields();
        $this->setupFilterVars();
    }

    public abstract function defFields();

    // Assign the auth object
    public function setAuth(R3Auth $auth) {
        $this->auth = $auth;
    }

    // Return the page variables
    public function getPageVars() {
        return array();
    }

    // Retittuisce un array con i file JS da includere
    // Se inline === true viene restituito il contenuto del JS (viene usata la costante R3_WEB_JS_DIR per il percorso del file)
    // Gli mette la costante R3_JS_URL se il file non inizia con / o http(s)
    // Return array
    public function includeJS($files, $inline = false) {
        $result = array();
        if (!is_array($files)) {
            $files = array($files);
        }
        foreach ($files as $file) {
            if ($inline) {
                $fileName = $file[0] <> '.' && $file[0] <> '/' ? R3_WEB_JS_DIR . $file : $file;
                if (!is_readable($fileName)) {
                    throw new exception('JS file not "' . $file . '" not found');
                }
                $result[] = "\n/*** " . basename($fileName) . " ***/\n\n" .
                        file_get_contents($fileName) .
                        "\n/*** End of " . basename($file) . " ***/\n";
            } else {
                $result[] = $file[0] <> '.' && $file[0] <> '/' ? R3_JS_URL . BUILD . '/' . $file : $file;
            }
        }
        return $result;
    }

    // Return the JS files to include
    public function getJSFiles() {
        return array();
    }

    // Return the JS variable (eg. text)
    public function getJSVars() {
        return array();
    }

    // The page title
    public function getPageTitle() {
        return '';
    }

    // Genera le variabili del filtro in base alla richiesta ed ai campi definiti
    private function setupFilterVars() {
        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if (isset($field['filter'])) {
                $this->$key = PageVar($key, null, $init || $reset, false, $this->baseName, $this->isFilter || $init || $reset);
            }
        }
    }

    // Extra where (always applied)
    public function getListWhere() {
        return null;
    }

    public function getDomainField($field) {
        $enableDomain = $this->auth->hasPerm('MOD', 'ALL_' . $this->getPermName());
        $result = array_merge(array('width' => 200,
            'label' => _('Ente'),
            'lookup' => array('table' => 'customer', 'list_field' => 'cus_name_<LANG>'),
            'visible' => $enableDomain), $field);
        if (!$enableDomain && R3_IS_MULTIDOMAIN) {
            $result['default'] = $this->do_id;
        }
        return $result;
    }

    // The SQL to generate the list
    public function getListSQL() {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $fields = array();
        $joins = array();
        $where = array();
        $totJoin = 0;
        $whereString = $this->getListWhere();
        $hasDomainField = false;
        if ($whereString != '') {
            $where[] = $whereString;
        }
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if ($key == 'do_id') {
                $hasDomainField = true;
            }
            if (isset($field['visible']) && $field['visible'] === false)
                continue; // Non uso campi nascosti
            if (isset($field['list']) && $field['list'] === false)
                continue; // Non uso in elenco
            if ($field['type'] == 'domain') {
                $field = $this->getDomainField($field);
            }
            if ($field['type'] == 'lookup' || $field['type'] == 'domain') {
                if (!isset($field['lookup']['list_field'])) {
                    $name = $key;
                } else {
                    $totJoin++;
                    $name = $field['lookup']['list_field'];
                    if (!isset($field['lookup']['pk'])) {
                        $field['lookup']['pk'] = array($key, $key);
                    }
                    $joins[] = "LEFT JOIN {$field['lookup']['table']} t{$totJoin} ON t0.{$field['lookup']['pk'][0]}=t{$totJoin}.{$field['lookup']['pk'][1]} ";
                }
                $fields[] = "t0.{$key}";
                $fields[] = "t{$totJoin}.$name" . (isset($field['lookup']['alias']) ? " AS {$field['lookup']['alias']}" : '');
            } else {
                $fields[] = "t0.{$key}";
            }
            if (isset($field['filter']) && $this->$key != '') {
                $mask = isset($field['filter']['mask']) ? $field['filter']['mask'] : "%s='%s'";
                $where[] = sprintf($mask, "t0.{$key}", substr($db->quote($this->$key), 1, -1));  // Substring prevent injection
            }
        }

        if (!$hasDomainField && $this->checkDomian) {
            $fields[] = 't0.do_id';
        }
        $table = $this->view <> '' ? $this->view : $this->table;
        $sql = $this->replaceMetadata("SELECT " . implode(', ', $fields) . " FROM {$table} t0 ");
        foreach ($joins as $join) {
            $sql .= $join;
        }
        $sql .= "WHERE 1=1 ";
        if ($this->checkDomian) {
            $sql .= "AND (t0.do_id IS NULL OR t0.do_id={$this->do_id}) ";
        }

        if (count($where) > 0) {
            $sql .= "AND " . implode(' AND ', $where) . " ";
        }
        return $sql;
    }

    // Return the SQL to get the records
    public function getListTotSQL() {
        return "SELECT COUNT(*) FROM (" . $this->getListSQL() . ") AS FOO";
    }

    // Return the total records 
    public function getTotRecord() {
        $db = ezcDbInstance::get();
        $this->lastTotRecord = $db->query($this->getListTotSQL())->fetchColumn();
        return $this->lastTotRecord;
    }

    // Restituisce il valore una label da un campo di tipo testo o array
    private function getFieldMixedData($data, $key, $kind) {
        $kinds = array('list' => 0, 'edit' => 1, 'filter' => 2);

        if (!isset($data[$key])) {
            return null;                            // Nessuna definizione
        }
        if (is_array($data[$key])) {
            if (isset($data[$key][$kind])) {
                return $data[$key][$kind];          // Array associativo
            } else {
                // Array numerico
                if (isset($data[$key][$kinds[$kind]])) {
                    return $data[$key][$kinds[$kind]];
                } else {
                    return $data[$key][0];
                }
            }
        } else {
            return $data[$key];                     // Definizione stringa o numero        
        }
    }

    private function replaceMetadata($str) {
        $str = str_replace(array('<LANG>',
            '<DOMAIN_ID>'), array(R3Locale::getLanguageID(),
            $this->do_id), $str);

        return $str;
    }

    public function getFilter() {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $filter = array();
        $hasFilter = false;
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if ($field['type'] == 'domain') {
                $field = $this->getDomainField($field);
            }
            if (isset($field['filter']) && $field['filter'] !== false) {
                $label = $this->getFieldMixedData($field['filter'], 'label', 'filter');
                if ($label == '') {
                    // Standard label
                    $label = $this->getFieldMixedData($field, 'label', 'filter');
                }

                if (!is_array($field['filter'])) {
                    $field['filter'] = array('type' => $field['filter']);
                }
                $type = strtolower($field['filter']['type']);

                $width = $this->getFieldMixedData($field, 'width', 'filter');
                $data = null;
                if ($type == 'select') {
                    if (isset($field['filter']['data'])) {
                        // Filtro select statico
                        $data = $field['filter']['data'];
                    } else if (isset($field['filter']['sql'])) {
                        // Filtro select con comando SQL diretto
                        $data = $db->query($this->replaceMetadata($field['filter']['sql']))->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
                    } else {
                        // Filtro select con creazione SQL da parametri
                        if (isset($field['filter']['fields'])) {
                            if (!is_array($field['filter']['fields'])) {
                                $field['filter']['fields'] = array($field['filter']['fields']);
                            }
                            if (count($field['filter']['fields']) == 1) {
                                $field['filter']['fields'][1] = $field['filter']['fields'][0];
                            }
                        } else {
                            $field['filter']['fields'] = array($key, $key);
                        }

                        if ($field['type'] == 'domain') {
                            $field = $this->getDomainField($field);
                        }
                        if (isset($field['lookup'])) {
                            if (!is_array($field['lookup'])) {
                                $field['lookup'] = array('table' => $field['lookup']);
                            }
                            $tableName = $field['lookup']['table'];
                        } else {
                            $tableName = $this->table;
                        }
                        $sql = "SELECT {$field['filter']['fields'][0]}, {$field['filter']['fields'][1]} " .
                                "FROM {$tableName} ";
                        $sql .= "WHERE 1=1 ";
                        if (isset($field['filter']['where'])) {
                            $sql .= "AND {$field['filter']['where']} ";
                        }
                        if (isset($field['filter']['cond_where'])) {
                            foreach ($field['filter']['cond_where'] as $k => $v) {
                                if ($v <> '') {
                                    $sql .= "AND {$k}=" . $db->quote($v) . " ";
                                }
                            }
                        }
                        $sql .= "GROUP BY {$field['filter']['fields'][0]}, {$field['filter']['fields'][1]} ";
                        if (isset($field['filter']['orderby'])) {
                            $sql .= "ORDER BY {$field['filter']['orderby']} ";
                        } else {
                            $sql .= "ORDER BY {$field['filter']['fields'][1]}, {$field['filter']['fields'][0]}";
                        }
                        $data = $db->query($this->replaceMetadata($sql))->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
                    }
                    if (count($data) > 1) {
                        $hasFilter = true;
                    }
                } else {
                    // Filtro non select
                    $hasFilter = true;
                }
                $filter[] = array('label' => $label, 'name' => $key, 'width' => $width, 'type' => $type, 'data' => $data);
            }
        }
        if ($hasFilter)
            return array('has_filter' => true, 'title' => $this->filterTitle, 'data' => $filter);
        return null;
    }

    // Return an array with the table legend
    public function getTableLegend() {
        return array();
    }

    public function getHTMLTableLegend() {
        if (count($this->getTableLegend()) == 0) {
            return '';
        }
        $result = "\n<!-- table legend -->\n";
        $result .= "<table>\n<tr>\n";
        $result .= "<td>" . _('Legenda') . ":</td>";
        foreach ($this->getTableLegend() as $val) {
            $result .= "<td class=\"" . $val['className'] . "\">" . $val['text'] . "</td>";
        }
        $result .= "\n</tr>\n</table>";
        return $result;
    }

    // Create the list table
    protected function createListTable() {
        if (defined('USE_JQGRID')) {
            // jqGrid
            $this->simpleTable = new simpleGrid("100%", 'grid', basename($_SERVER['PHP_SELF']) . '?on=' . $this->baseName . '&method=getListData&');
            $this->simpleTable->checkImage(false);
        } else {
            $this->simpleTable = new pSimpleTable("100%", 'grid', basename($_SERVER['PHP_SELF']) . '?on=' . $this->baseName);
            $this->simpleTable->checkImage(false);
        }
    }

    // Return the array with all the filter values
    public function getFilterValues() {
        $filters = array();
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if (isset($field['filter'])) {
                $filters[$key] = $this->$key;
            }
        }
        return $filters;
    }

    // Create the table headers
    //abstract public function createListTableHeader(&$order);
    public function createListTableHeader(&$order) {
        if ($this->table == '')
            throw new Exception("Missing table in {$this->baseName}");
        if ($this->fields == '')
            throw new Exception("Missing field definition in {$this->baseName}");
        $pkName = $this->getPrimaryKeyName();

        // Calculate default order
        $defaultOrder = array();
        foreach ($this->fields as $key => $field) {
            if ($field['type'] == 'domain') {
                $field = $this->getDomainField($field);
            }
            if (isset($field['is_primary_key']) && $field['is_primary_key'] === true && !isset($field['list']))
                continue;  // Non mostro primary key
            if (isset($field['visible']) && $field['visible'] === false)
                continue; // Non mostro campi nascosti
            if (isset($field['list']) && $field['list'] === false)
                continue; // Non mostro in elenco
            if (isset($field['name'])) {
                $defaultOrder[] = $field['name'];
            }
        }
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if ($field['type'] == 'domain') {
                $field = $this->getDomainField($field);
            }
            if ($field['type'] == 'domain' && !R3_IS_MULTIDOMAIN) {
                continue;
            }
            if (isset($field['is_primary_key']) && $field['is_primary_key'] === true && !isset($field['list']))
                continue;  // Non mostro primary key
            if (isset($field['visible']) && $field['visible'] === false)
                continue; // Non mostro campi nascosti
            if (isset($field['list']) && $field['list'] === false)
                continue; // Non mostro in elenco
            $label = $this->getFieldMixedData($field, 'label', 'list');
            $type = isset($field['type']) ? $field['type'] : 'text';
            $width = $this->getFieldMixedData($field, 'width', 'list');
            if ($field['type'] == 'lookup' || $field['type'] == 'domain') {
                if (!isset($field['lookup']['list_field'])) {
                    $field_name = $key;
                } else {
                    $field_name = isset($field['lookup']['alias']) ? $field['lookup']['alias'] : $field['lookup']['list_field'];
                }
            } else {
                $field_name = $key;
            }
            $field_name = $this->replaceMetadata($field_name);
            $orderField = array_merge(array($field_name), $defaultOrder, array($pkName));
            $attr = isset($field['attr']) ? $field['attr'] : array('sortable' => true, 'order_fields' => implode(', ', $orderField)); //"{$field_name}, {$pkName}");
            if ($width == '') {
                if ($type == 'boolean') {
                    $width = 50;
                }
            }
            if (in_array($type, array('boolean')) && !isset($attr['align'])) {
                $attr['align'] = 'center';
            }
            $this->simpleTable->addSimpleField($label, $field_name, $type, $width, $attr);
        }

        $totActions = 0;
        foreach ($this->actions as $act) {
            if ($this->auth->hasPerm(strtoupper($act), $this->getPermName())) {
                $totActions++;
            }
        }
        if ($totActions > 0) {
            $this->simpleTable->addSimpleField(_('Azione'), '', 'link', max(20 * $totActions, 50));
        }
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    // Create the table footer
    public function createListTableFooter() {
        $this->tableHtml .= $this->simpleTable->MkTableFooter();
    }

    public function getListTableRowOperations(&$row) {
        $links = array();
        $id = $row[$this->getPrimaryKeyName()];
        $baseURL = 'lookup_edit.php?on=' . $this->baseName;

        // Corregge boolean values
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            $type = isset($field['type']) ? $field['type'] : 'text';
            if ($type == 'boolean' && $row[$key] == true) {
                $row[$key] = 'X';
            }
        }
        $objName = strToUpper($this->baseName);
        $canShow = $this->auth->hasPerm('SHOW', $this->getPermName());
        $canMod = (($this->checkDomian && $row['do_id'] == $this->do_id) || !R3_IS_MULTIDOMAIN) && $this->auth->hasPerm('MOD', $this->getPermName());
        $canMod = $canMod || $this->auth->hasPerm('MOD', 'ALL_' . $this->getPermName());
        $canMod = $canMod && $this->canMod();
        $canDel = (($this->checkDomian && $row['do_id'] == $this->do_id) || !R3_IS_MULTIDOMAIN) && $this->auth->hasPerm('DEL', $this->getPermName());
        $canDel = $canDel || $this->auth->hasPerm('DEL', 'ALL_' . $this->getPermName());
        $canDel = $canDel && $this->canDel();

        foreach ($this->actions as $act) {
            if ($this->auth->hasPerm(strtoupper($act), $this->getPermName())) {
                $act = strtolower($act);
                switch ($act) {
                    case 'show':
                        if ($canShow && !$canMod) {
                            $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showLookup('$id')", "", "../images/ico_" . $act . ".gif");
                        }
                        break;
                    case 'mod':
                        if ($canMod) {
                            $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modLookup('$id')", "", "../images/ico_" . $act . ".gif");
                        }
                        break;
                    case 'del':
                        if ($canDel) {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelLookup('$id')", "", "../images/ico_" . $act . ".gif");
                        } else {
                            $links['DEL'] = $this->simpleTable->AddLinkCell('', '', "", "../images/ico_spacer.gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        return array();
    }

    public function getListTableRowDefaultEvent(&$row) {
        if ($this->auth->hasPerm('MOD', 'LOOKUP')) {
            return 'MOD';
        } else if ($this->auth->hasPerm('SHOW', 'LOOKUP')) {
            return 'SHOW';
        }
        return null;
    }

    public function canAdd() {
        return true;
    }

    public function canMod() {
        return true;
    }

    public function canDel() {
        return true;
    }

    public function getListTableRowEvent(&$row, $links = array()) {
        $defaultAction = $this->getListTableRowDefaultEvent($row);

        if (isset($links[$defaultAction]['url']) && $links[$defaultAction]['url'] <> '') {
            return array('ondblclick' => $links[$defaultAction]['url']);
        }
        return null;
    }

    public function getHTMLTable() {

        $db = ezcDbInstance::get();
        $sql = $this->getListSQL();

        // Tacking session/auth variables for limit, page, order
        $init = array_key_exists('init', $this->request) || array_key_exists('reset', $this->request);
        if (!isset($this->limit)) {
            $this->limit = $this->auth === null ? 10 : max(10, $this->auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
        }
        if (!isset($this->pg)) {
            $this->pg = PageVar('pg', 1, $init);
        }
        $this->createListTable();
        $this->createListTableHeader($this->order);

        // Apply the SQL order
        if ($this->order <> '') {
            $orderFields = $this->simpleTable->getSQLOrder($this->order);
            if ($orderFields <> '') {
                $sql .= " ORDER BY " . $this->simpleTable->getSQLOrder($this->order);
            }
        }
        if ($this->limit > 0) {
            $sql .= " LIMIT " . (int) max(0, $this->limit);
            $sql .= " OFFSET " . (int) max(0, ($this->pg - 1) * $this->limit);
        }
        $stmt = $db->query($sql, PDO::FETCH_ASSOC);
        $rowNo = 0;
        while ($row = $stmt->fetch()) {
            $rowNo++;
            $row['ROW_NO'] = $rowNo;
            $links = $this->getListTableRowOperations($row);
            $style = $this->getListTableRowStyle($row);
            $events = $this->getListTableRowEvent($row, $links);
            $this->tableHtml .= $this->simpleTable->CreateTableRow($row, $links, $style, $events);
        }

        $this->createListTableFooter();
        return $this->tableHtml;
    }

    public function getTemplateName() {
        if ($this->act == 'list' || $this->act == 'del') {
            return 'lookup_list.tpl';
        } else {
            return 'lookup_edit.tpl';
        }
    }

    // Return the html navigation bar
    public function getHTMLNavigation() {

        return $this->simpleTable->mkNavigationBar($this->pg, $this->lastTotRecord, $this->limit);
    }

    // Ajax call to get the table list data
    public function getListData($request) {
        $db = ezcDbInstance::get();
        $sql = $this->getListSQL();

        // Crea la tabella per poter chiamare createListTableHeader
        $this->createListTable();
        $this->createListTableHeader($this->order);

        $init = array_key_exists('init', $this->request) || array_key_exists('reset', $this->request);
        if (!isset($this->limit)) {
            $this->limit = $this->auth === null ? 10 : max(10, $this->auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
        }
        if (!isset($this->pg)) {
            $this->pg = PageVar('pg', 1, $init);
        }

        // Apply the SQL order
        if ($this->order <> '') {
            $orderFields = $this->simpleTable->getSQLOrder($this->order);
            if ($orderFields <> '') {
                $sql .= " ORDER BY " . $this->simpleTable->getSQLOrder($this->order);
            }
        }

        if ($this->limit > 0) {
            $sql .= " LIMIT " . (int) max(0, $this->limit);
            $sql .= " OFFSET " . (int) max(0, ($this->pg - 1) * $this->limit);
        }

        $rows = array();

        $stmt = $db->query($sql);
        $rowNo = 0;
        while ($row = $stmt->fetch()) {
            $rowNo++;
            $data = array();
            foreach ($this->simpleTable->getFields() as $field) {
                $links = $this->getListTableRowOperations($row);
                if ($field['type'] == 'LINK') {
                    $data[] = $this->simpleTable->CreateLinksCell($links);
                } else if ($field['type'] == 'CALCULATED') {
                    $data[] = $this->simpleTable->getCalcValue($field['field']);
                } else {

                    $value = $row[$field['field']];
                    $format = $field['format'];
                    $number_format = $field['number_format'];
                    // Corregge il campo data
                    if ($field['type'] == 'DATE') {
                        if ($value == '0000-00-00') {
                            $value = '';
                        } else {
                            $value = SQLDateToStr($value, $format === false ? 'd/m/Y' : $format);
                        }
                    } else if ($field['type'] == 'TIME') {
                        if ($value == '00:00:00') {
                            $value = '';
                        } else {
                            $value = SQLDateToStr($value, $format === false ? 'h:i:s' : $format);
                        }
                    } else if ($field['type'] == 'DATETIME') {
                        if ($value == '0000-00-00 00:00:00') {
                            $value = '';
                        } else {
                            $value = SQLDateToStr($value, $format === false ? 'd/m/Y H:i:s' : $format);
                        }
                    } else if (strpos($field['type'], 'URL') !== false) {
                        if (strpos($field['type'], 'MAILTO') !== false)
                            $value = sprintf("<a href=\"mailto:%s\">%s</a>", $value, $value);
                        else
                            $value = sprintf("<a href=\"%s\" target=\"_BLANK\">%s</a>", $value, $value);
                    } else {
                        // Format or number_format
                        if ($value != '') {
                            if ($format !== false) {
                                $value = sprintf($format, $value);
                            } else if ($number_format !== false) {
                                if (!defined("__R3_LOCALE__")) {
                                    require_once 'r3locale.php';
                                }
                                $localeInfo = getLocaleInfo();
                                if (!is_array($number_format)) {
                                    $number_format = array('decimals' => $number_format,
                                        'dec_point' => $localeInfo['decimal_point'],
                                        'thousands_sep' => $localeInfo['thousands_sep']);
                                } else {
                                    $number_format = array_merge(array('decimals' => 0,
                                        'dec_point' => $localeInfo['decimal_point'],
                                        'thousands_sep' => $localeInfo['thousands_sep']), $number_format);
                                }
                                if ($number_format['decimals'] === null && is_numeric($value)) {
                                    $diff = round($value - (int) $value, 10);
                                    if ($diff == 0) {
                                        $number_format['decimals'] = 0;
                                    } else {
                                        $number_format['decimals'] = strlen($diff) - 2;  // -2 is 0. of the number
                                    }
                                }
                                $value = number_format($value, $number_format['decimals'], $number_format['dec_point'], $number_format['thousands_sep']);
                            }
                        }
                    }
                    $data[] = $value;
                }
            }
            $rows[] = array('id' => $rowNo, 'cell' => $data);
        }
        $result = array();
        $tot = $db->query($this->getListTotSQL())->fetchColumn();
        $result['total'] = ceil($tot / $this->limit);
        $result['page'] = $this->pg;
        $result['records'] = $tot;
        $result['rows'] = $rows;
        return $result;
    }

    // Restituisce i dati della form in base ai campi di lookup
    public function getFormDefinition() {
        $db = ezcDbInstance::get();

        $form = array();
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if ($field['type'] == 'domain') {
                $field = $this->getDomainField($field);
            }
            $label = $this->getFieldMixedData($field, 'label', 'edit');
            $width = $this->getFieldMixedData($field, 'width', 'edit');
            $height = $this->getFieldMixedData($field, 'height', 'edit');
            $required = isset($field['required']) && $field['required'] === true ? 'T' : 'F';
            $show_label = isset($field['show_label']) && $field['show_label'] === false ? 'F' : 'T';
            $kind = isset($field['kind']) ? strtoupper($field['kind']) : null;
            $wrap = !isset($field['wrap']) ? 'F' : 'T';
            $visible = !isset($field['visible']) ? 'T' : ($field['visible'] === true ? 'T' : 'F');
            $type = $field['type'];
            $size = isset($field['size']) ? $size : null;
            $data = null;
            if ($width == '') {
                if ($type == 'text') {
                    $width = 300;
                } else if ($type == 'memo') {
                    $width = 500;
                } else if ($type == 'number') {
                    $width = 80;
                }
            }
            if ($height == '' && $type == 'memo') {
                $height = 100;
            }
            if (isset($field['is_primary_key']) && $field['is_primary_key'] === true && !isset($field['edit']))
                $type = 'hidden';
            if (isset($field['visible']) && $field['visible'] === false)
                $type = 'hidden';
            if (isset($field['edit']) && $field['edit'] === false)
                $type = 'hidden';
            if ($field['type'] == 'color') {
                $type = 'text';
                $width = 60;
                $size = 7;
            }
            if ($type <> 'hidden' && isset($field['lookup'])) {
                $type = 'select';
                if (!is_array($field['lookup'])) {
                    $field['lookup'] = array('table' => $field['lookup']);
                }
                if (!isset($field['lookup']['list_field'])) {
                    $field['lookup']['list_field'] = $key;
                }
                if (!isset($field['lookup']['pk'])) {
                    $field['lookup']['pk'] = array($key, $key);
                }
                $sql = "SELECT {$field['lookup']['pk'][1]}, {$field['lookup']['list_field']} 
                        FROM {$field['lookup']['table']}
                        WHERE 1=1 ";
                if (isset($field['lookup']['where'])) {
                    $sql .= "AND {$field['lookup']['where']} ";
                }
                if (isset($field['filter']['where'])) {
                    $sql .= "AND {$field['filter']['where']} ";
                }
                if (isset($field['filter']['cond_where'])) {
                    foreach ($field['filter']['cond_where'] as $k => $v) {
                        if ($v <> '') {
                            $sql .= "AND {$k}=" . $db->quote($v) . " ";
                        }
                    }
                }

                $sql .= "GROUP BY {$field['lookup']['list_field']}, {$field['lookup']['pk'][1]} ";
                if (isset($field['lookup']['orderby'])) {
                    $sql .= ", {$field['lookup']['orderby']} ";
                }
                if (isset($field['lookup']['orderby'])) {
                    $sql .= "ORDER BY {$field['lookup']['orderby']} ";
                } else {
                    $sql .= "ORDER BY {$field['lookup']['list_field']}, {$field['lookup']['pk'][1]}";
                }
                $data = array('' => _('-- Selezionare --'));
                foreach ($db->query($this->replaceMetadata($sql), PDO::FETCH_NUM) as $row) {
                    $data[$row[0]] = $row[1];
                }
            }
            $form[] = array('label' => $label,
                'type' => $type,
                'name' => $key,
                'size' => $size,
                'width' => $width,
                'height' => $height,
                'required' => $required,
                'show_label' => $show_label,
                'visible' => $visible,
                'data' => $data,
                'wrap' => $wrap,
                'kind' => $kind);
        }
        return $form;
    }

    // Ricava i dati del singolo contribuente
    //abstract public function getData($id=null);
    public function getData() {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $id = $this->id;

        $vlu = array();
        if ($this->act <> 'add') {
            $id = (int) $this->id;
            try {
                $this->checkLookupDomainSecurity($id, $this->act);
            } catch (Exception $e) {
                die('Security error');
            }
            $pkName = $this->getPrimaryKeyName();
            $q = "SELECT * FROM {$this->table} WHERE {$pkName}={$id}";
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);

            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData($this->table, $id));
        } else {
            $vlu = array();
            foreach ($this->fields as $key => $field) {
                if (isset($field['name'])) {
                    $key = $field['name'];
                }
                if (isset($field['default'])) {
                    $vlu[$key] = $field['default'];
                }
            }
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    // Check if the user cha show, mod, del the object
    public function checkLookupDomainSecurity($id, $act) {
        $id = (int) $id;
        $uid = $this->auth->getUID();
        if (!$this->auth->hasPerm(strtoupper($act), $this->getPermName())) {
            ezcLog::getInstance()->log("Security error for object=\"{$this->table}\", id=\"{$id}\", action=\"{$this->act}\" user={$uid}", ezcLog::ERROR);
            throw new Exception('Security error #1');
        }
        if (R3_IS_MULTIDOMAIN) {
            if (($this->checkDomian && !$this->auth->hasPerm(strtoupper($act), 'ALL_' . $this->getPermName()))) {
                // Se non vedo nell elenco, non posso entrare in visualizaza, modifica, cancella
                if ($act <> 'add') {
                    $db = ezcDbInstance::get();
                    $table = $this->view <> '' ? $this->view : $this->table;
                    $sql = $this->getListSQL();
                    $pkName = $this->getPrimaryKeyName();
                    $sql = "SELECT do_id FROM ({$sql}) AS foo WHERE {$pkName}={$id}";
                    $data = $db->query($sql)->fetch();
                    if ($data === false) {
                        ezcLog::getInstance()->log("Security error for object=\"{$this->table}\", id=\"{$id}\", action=\"{$this->act}\" user={$uid}", ezcLog::ERROR);
                        throw new Exception('Security error #2');
                    }
                    if (strtoupper($act) <> 'SHOW' && $data['do_id'] <> $this->do_id) {
                        ezcLog::getInstance()->log("Security error for object=\"{$this->table}\", id=\"{$id}\", action=\"{$this->act}\" user={$uid}", ezcLog::ERROR);
                        throw new Exception('Security error #3');
                    }
                }
            }
        }
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();

        $pkName = $this->getPrimaryKeyName();
        if (!isset($request[$pkName])) {
            $request[$pkName] = $request['id'];
        }
        $this->checkLookupDomainSecurity($request[$pkName], $request['act']);
        if ($this->act == 'add') {
            if ($this->checkDomian && !$this->auth->hasPerm('ADD', 'ALL_' . $this->getPermName())) {
                $request['do_id'] = R3_IS_MULTIDOMAIN ? $this->do_id : null;
            }
        }
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $id = $this->applyData($request);
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneLookup($id)");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteLookup($request) {
        $db = ezcDbInstance::get();
        $id = forceInteger($request['id'], 0, false, '.');
        try {
            $this->checkLookupDomainSecurity($id, 'del');
        } catch (Exception $e) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Object not found'));
        }
        $name = '';
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            if (isset($field['delete_name']) && $field['delete_name'] === true) {
                $pkName = $this->getPrimaryKeyName();
                $sql = "SELECT {$key} FROM {$this->table} WHERE $pkName={$id}";
                $name = $db->query($sql)->fetchColumn();
            }
        }

        $hasDependencies = !$this->checkDependencies($id);
        $messageStatus = $hasDependencies ? 'error' : 'confirm';
        $message = $this->getDeleteMessage($id, $name, $messageStatus);
        if (!is_array($message)) {
            $message = array($messageStatus => $message);
        }
        if ($hasDependencies) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => sprintf($message[$messageStatus], $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf($message[$messageStatus], $name));
        }
    }

    public function checkDependencies($id) {
        $db = ezcDbInstance::get();
        $db->beginTransaction();
        $pkName = $this->getPrimaryKeyName();
        $result = true;
        try {
            $sql = "DELETE FROM {$this->table} WHERE $pkName={$id}";
            $db->exec($sql);
        } catch (Exception $e) {
            $result = false;
        }
        $db->rollback();
        return $result;
    }

    // Override se serve permission specifica
    public function getPermName() {
        return 'LOOKUP';
    }

    public function checkPerm() {
        global $smarty;

        if ($this->act == 'list') {
            $smarty->assign('HAS_ADD_BUTTON', $this->canAdd());
        }

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->getPermName());
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

    /**
     * Convert the locale data to the php data (date and numbers) using the field definizion
     * param array $data     array data to process
     */
    public function convert2PHP($vlu) {
        if (!isset($this->fields))
            return $vlu;
        setLangInfo(array('thousands_sep' => "."));
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
                if (array_key_exists($key, $vlu)) {
                    if ($vlu[$key] === null) {
                        $vlu[$key] = '';
                    } else {
                        $type = strToLower($field['type']);
                        switch ($field['type']) {
                            case 'real':
                            case 'double':
                            case 'number':
                            case 'float':
                                $vlu[$key] = forceFloat($vlu[$key], null, '.');
                                break;
                            case 'integer':
                            case 'lookup':
                            case 'domain':
                                $vlu[$key] = forceInteger($vlu[$key], null, true, '.');
                                break;
                            case 'date':
                                $vlu[$key] = forceISODate($vlu[$key]);
                                break;
                            case 'datetime':
                            case 'now':
                                break;
                            case 'boolean':
                                $vlu[$key] = forceBool($vlu[$key], $vlu[$key]);
                        }
                    }
                }
            }
        }
        return $vlu;
    }

    // Convert data into locale data 
    public function convert2Locale($vlu) {
        if (!is_array($vlu))
            return $vlu;
        setLangInfo(array('thousands_sep' => "."));
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
                if (isset($field['dec']) && !isset($field['precision'])) {
                    $field['precision'] = $field['dec'];
                }
                if (array_key_exists($key, $vlu)) {
                    if ($vlu[$key] === null) {
                        $vlu[$key] = '';
                    } else {
                        $type = strToLower($field['type']);
                        switch ($field['type']) {
                            case 'real':
                            case 'double':
                            case 'number':
                            case 'float':
                                $vlu[$key] = R3NumberFormat($vlu[$key], isset($field['precision']) ? $field['precision'] : null, true);
                                break;
                            case 'integer':
                                $vlu[$key] = R3NumberFormat($vlu[$key], isset($field['precision']) ? $field['precision'] : 0, true);
                                break;
                            case 'date':
                                $vlu[$key] = SQLDateToStr($vlu[$key], R3Locale::getPhpDateFormat());
                                break;
                            case 'datetime':
                            case 'now':
                                $vlu[$key] = SQLDateToStr($vlu[$key], R3Locale::getPhpDateTimeFormat());
                                break;
                            case 'boolean':
                                if ($vlu[$key] === true) {
                                    $vlu[$key] = 'T';
                                } else if ($vlu[$key] === false) {
                                    $vlu[$key] = 'F';
                                }
                        }
                    }
                }
            }
        }
        return $vlu;
    }

    public function getDataAsLocale($id = null) {
        $vlu = $this->getData($id = null);
        if (isset($this->fields) && is_array($this->fields)) {
            $vlu = $this->convert2Locale($vlu);
        }
        return $vlu;
    }

    // Ricava i dati di lookup del singolo contribuente (elenco città, vie, ecc)
    public function getLookupData($id = null) {
        return array();
    }

    // Return the tabs definition array or null if no tab
    public function getTabs() {
        return null;
    }

    public function checkField(array $formData, $key, $field, &$errors) {
        throw new exception('Unknown field type "' . $field['type'] . '"');
    }

    public function checkFormData(array $formData, array $errors = array()) {
        $db = ezcDbInstance::get();
        if (!isset($this->fields) || !is_array($this->fields)) {
            throw new exception("\$this->fields is null or is not an array. Check your constructor!");
        }
        foreach ($this->fields as $key => $field) {
            $toCheck = true;
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            // Auto values
            if (isset($field['value'])) {
                $formData[$key] = $field['value'];
            }

            // Required check
            if ($toCheck && isset($field['required']) && $field['required'] === true) {
                if (!isset($formData[$key]) || strlen($formData[$key]) == 0) {
                    $errors[$key] = MISSING_VALUE;
                    $toCheck = false;
                }
            }
            // Type check
            if ($toCheck && isset($formData[$key])) {
                switch (strToLower($field['type'])) {
                    case 'real':
                    case 'double':
                    case 'number':
                    case 'float':
                        if ($formData[$key] <> '' && forceFloat($formData[$key], null, '.') === null) {
                            $errors[$key] = INVALID_VALUE;
                            $toCheck = false;
                        }
                        break;
                    case 'integer':
                        if ($formData[$key] <> '' && forceInteger($formData[$key]) === null) {
                            $errors[$key] = INVALID_VALUE;
                            $toCheck = false;
                        }
                        break;
                    case 'date':
                        if ($formData[$key] <> '' && forceISODate($formData[$key], null, 'ISO') == '') {
                            $errors[$key] = INVALID_VALUE;
                            $toCheck = false;
                        }
                        break;
                    case 'text':
                    case 'memo':
                    case 'string':
                        if (isset($field['size']) && mb_strlen($formData[$key]) > $field['size']) {
                            $errors[$key] = INVALID_SIZE;
                            $toCheck = false;
                        }
                        break;
                    case 'color':
                        if (strlen($formData[$key]) > 0 && !preg_match('/^\#([0-9a-f]{6}|[0-9a-f]{3})$/i', $formData[$key])) {
                            $errors[$key] = INVALID_VALUE;
                            $toCheck = false;
                        }
                        break;
                    case 'uid':   // UID
                    case 'boolean':
                    case 'lookup':
                    case 'domain':
                        // No test
                        break;

                    default:
                        $this->checkField($formData, $key, $field, $errors);
                }
            }

            // Lookup check
            if (isset($field['lookup']) && isset($formData[$key]) && $formData[$key] <> '') {
                if (isset($field['lookup']['table'])) {
                    $lkpPK = isset($field['lookup']['pk'][1]) ? $field['lookup']['pk'][1] : $key;
                    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE $lkpPK=%s", $field['lookup']['table'], $db->quote($formData[$key]));
                    if ($db->query($sql)->fetchColumn(0) == 0) {
                        $errors[$key] = INVALID_LOOKUP_VALUE;
                        $toCheck = false;
                    }
                }
            }
        }
        return $errors;
    }

    // Restituisce la chiave della definizione del campo in base al suo nome
    public function getFieldKeyByName($name) {
        foreach ($this->fields as $key => $field) {
            if (isset($field['name']) && $field['name'] == $name) {
                return $key;
            }
        }
        return null;
    }

    // Restituisce n array con la definizione del campo in base al suo nome
    public function getFieldDefByName($name) {
        foreach ($this->fields as $field) {
            if (isset($field['name']) && $field['name'] == $name) {
                return $field;
            }
        }
        return null;
    }

    // Restituisce n array con la definizione dei campo in base al tipo
    // se index <> null restituisce solo l'indice index 
    public function getFieldDefByType($type, $index = null) {

        $result = array();
        foreach ($this->fields as $key => $field) {
            if ($field['type'] == $type) {
                if (!isset($field['name'])) {
                    $field['name'] = $key;
                }
                $result[] = $field;
            }
        }
        if ($index === null) {
            return $result;
        }
        if (isset($result[$index])) {
            return $result[$index];
        }
        return null;
    }

    // Imposta uno o più attributi per un campo. Se $add è true il campo se non esiste viene creato
    public function setFieldAttrib($name, $attrib, $add = false) {
        $field = null;
        foreach ($this->fields as $key => $f) {
            if (isset($f['name']) && $f['name'] == $name) {
                foreach ($attrib as $k => $v)
                    $this->fields[$key][$k] = $v;
                return $this->fields[$key];
            }
        }
        if ($field === null && $add === true) {
            $field[$name] = $name;
            foreach ($attrib as $k => $v)
                $field[$k] = $v;
            $this->fields[] = $field;
        }
        return $field;
    }

    // Restituisce il nome della chiava primaria
    public function getPrimaryKeyName() {
        foreach ($this->fields as $field) {
            if (isset($field['is_primary_key']) && $field['is_primary_key'] === true) {
                return $field['name'];
            }
        }
        return null;
    }

    public function checkErrorToText(array $errorArray, &$fisrtId = null) {
        $result = '';
        $errs = array();
        foreach ($errorArray as $key => $val) {
            if ($fisrtId === null) {
                $fisrtId = $key;
            }
            $field = $this->getFieldDefByName($key);
            $label = $this->getFieldMixedData($field, 'label', 'edit');
            if (is_array($val)) {
                // NON Standard error text
                $errs[] = sprintf(current($val), $label);
            } else {
                switch ($val) {
                    case 'MISSING_VALUE':
                        $errs[] = sprintf(_('Il campo "%s" è obbligatorio'), $label);
                        break;
                    case 'INVALID_VALUE':
                        $errs[] = sprintf(_('Il campo "%s" contiene un valore non valido'), $label);
                        break;
                    case 'INVALID_LOOKUP_VALUE':
                        $errs[] = sprintf(_('Il campo "%s" contiene un riferimento non valido'), $label);
                        break;
                    case 'INVALID_SIZE':
                        $errs[] = sprintf(_('Il campo "%s" è di lunghezza non valida'), $label);
                        break;
                    case 'UNIQUE_VIOLATION':
                        $errs[] = sprintf(_('Il campo "%s" deve essere univoco'), $label);
                        break;
                    case 'INVALID_DATE_FROM_TO_VALUE':
                        $errs[] = sprintf(_('Il campo data inizio è maggiore del campo data fine'), $label);
                        break;
                    default:
                        $errs[] = sprintf(_('Errore sconosciuto nel campo "%s"'), $label);
                        break;
                }
            }
        }
        return _("Attenzione:\n - ") . implode("\n - ", $errs);
    }

    // Return an array formatted for a ajax error result
    function getAjaxErrorResult($errors) {
        $errText = $this->checkErrorToText($errors, $firstId);
        $result = array('status' => R3_AJAX_ERROR,
            'error' => array('text' => $errText,
                'element' => $firstId));
        return $result;
    }

    // Return true if is applying data
    function isApplyingData($data = null) {
        if ($data === null) {
            $data = $this->request;
        }
        return isset($data['apply']);
    }

    // Apply the changes (add, mod, del, print)
    function ApplyData($data = null, array $validActions = array('add', 'mod', 'del')) {
        if ($data === null) {
            $data = $this->request;
        }
        if (!in_array($data['act'], $validActions))
            throw new Exception("Invalid action [{$data['act']}] for ApplyData");
        $funcName = 'do' . UCFirst($data['act']);
        if (is_callable(array($this, $funcName))) {
            return $this->$funcName($data);
        } else {
            throw new Exception('Function ' . R3Controller::getObjectType($_REQUEST) . '::' . $funcName . ' not found');
        }
    }

    protected function null2text($value, $strict = true) {
        if ($value === null)
            return 'NULL';
        if ($strict && $value == '')
            return 'NULL';
        return $value;
    }

    // Prepara il valore di un campo per l'inserimento.
    public function prepareFieldValue($act, $key, $value, $type, $size = null, $default = null) {
        $db = ezcDbInstance::get();
        switch ($type) {
            case 'real':
            case 'double':
            case 'number':
            case 'float':
                if ((string) $value == '') {
                    return $this->null2text($default);
                }
                if (!is_numeric($value))
                    $value = forceFloat($value, null, '.');
                return (string) $value;
            case 'year':
            case 'integer':
                if ((string) $value == '') {
                    return $this->null2text($default);
                }
                if (!is_numeric($value))
                    $value = forceInteger($value);
                return (string) $value;
            case 'boolean':
                if ($value == '') {
                    return 'FALSE';
                }
                return (strtoupper(substr($value, 0, 1)) == 'T' || $value == 1 || $value === true) ? 'TRUE' : 'FALSE';
            case 'lookup':
            case 'domain':
                if ((string) $value == '') {
                    return $this->null2text($default);
                }
                return $value;
            case 'date':
                if ($value == '') {
                    return $this->null2text($default);
                }
                return $db->quote(forceISODate($value, null, 'ISO'));
            case 'string':
            case 'color':
            case 'text':
            case 'memo':
                if ($value == '') {
                    return $this->null2text($default, true, true);
                }
                if ($size > 0) {
                    return $db->quote(mb_substr($value, 0, $size));
                }
                return $db->quote($value);

            case 'geometry':
                if ($value == '') {
                    return $this->null2text($default);
                }
                return $db->quote($value);
            default:
                return $value;
        }
    }

    public function getSQLFields($data, array &$fields, array &$values) {
        $oldLocale = getLocale();
        setLocale(LC_ALL, 'C');

        $db = ezcDbInstance::get();
        foreach ($this->fields as $key => $field) {
            $default = isset($field['default']) ? $field['default'] : null;
            if (isset($field['is_primary_key']) && $field['is_primary_key'] === true) {
                continue;  // ignore primary key 
            }
            if (isset($field['calculated']) && $field['calculated'] === true) {
                continue;  // ignore primary key
            }
            if (isset($field['name'])) {
                $key = $field['name'];
            }
            $fields[] = $key;
            if (isset($field['value'])) {
                $values[] = $db->quote($field['value']);
            } else if ($field['type'] == 'NOW') {
                $values[] = 'now()';
            } else if ($field['type'] == 'UID') {
                $values[] = $this->auth->getUID();
            } else {
                if (isset($data[$key])) {
                    $values[] = $this->prepareFieldValue($this->act, $key, $data[$key], $field['type'], isset($field['size']) ? $field['size'] : null, $default);
                } else {
                    $values[] = $this->prepareFieldValue($this->act, $key, null, $field['type'], isset($field['size']) ? $field['size'] : null, $default);
                }
            }
        }
        setLocale(LC_ALL, $oldLocale);
    }

    public function doAdd($data) {

        $db = ezcDbInstance::get();
        $fields = array();
        $values = array();
        $this->getSQLFields($data, $fields, $values);
        if ($this->UUID <> '') {
            $fields[] = $this->UUID;
            $values[] = $db->quote(uuid());
        }
        if (!isset($this->table))
            throw new Exception('Missing table name');
        $sql = 'INSERT INTO ' . $this->table . ' (' .
                implode(', ', $fields) . ' ' .
                ') VALUES (' .
                implode(', ', $values) . ')';
        $db->exec($sql);
        if ($this->getPrimaryKeyName() === null) {
            return 0;
        }
        return $db->lastInsertId($this->table . '_' . $this->getPrimaryKeyName() . '_seq');
    }

    public function doMod($data) {

        $db = ezcDbInstance::get();
        $fields = array();
        $values = array();
        $this->getSQLFields($data, $fields, $values);

        $PKName = $this->getPrimaryKeyName();
        if ($PKName === null) {
            throw new Exception('Missing primary key in field definitions');
        }
        $updArray = array();
        for ($i = 0; $i < count($fields); $i++) {
            if ($values[$i] === null) {
                $updArray[] = $fields[$i] . '=null';
            } else {
                $updArray[] = $fields[$i] . '=' . $values[$i];
            }
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' .
                implode(', ', $updArray) . ' ' .
                'WHERE ' . $PKName . '=' . $db->quote($data[$PKName]);
        $db->exec($sql);
        return $data[$PKName];
    }

    public function doDel($data) {

        $db = ezcDbInstance::get();

        $PKName = $this->getPrimaryKeyName();
        if ($PKName === null) {
            throw new Exception('Missing primary key in field definitions');
        }
        $sql = 'DELETE FROM ' . $this->table . ' ' .
                'WHERE ' . $PKName . '=' . $db->quote($data[$PKName]);
        $db->exec($sql);
        return $data[$PKName];
    }

    public static function checkSQLData(array $values) {
        foreach ($values as $key => $val) {
            if (is_bool($val)) {
                $values[$key] = $val ? 'T' : 'F';
            } else if (strlen($val) == 0) {
                $values[$key] = null;
            }
        }
        return $values;
    }

    /**
     * Register an ajax function
     */
    public function registerAjaxFunction($name, $output = 'JSON') {
        $this->ajaxFuncList[$name] = array('name' => $name, 'output' => strtoupper($output));
    }

    /**
     * Process an jQuery ajax request
     */
    public function processAjaxRequest() {
        global $ajaxResponseType;
        if (isset($_GET['method'])) {
            // Is a jquery-ajax request
            $request = array_merge($_GET, $_POST);

            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");

            if (!array_key_exists($request['method'], $this->ajaxFuncList))
                throw new Exception('Function ' . get_class($this) . '::' . $request['method'] . '" not registered');
            $funcData = $this->ajaxFuncList[$request['method']];

            $ajaxResponseType = $funcData['output'];
            set_error_handler('ajaxErrorHandler');

            if (!is_callable(array($this, $funcData['name'])))
                throw new Exception('Function ' . get_class($this) . '::' . $funcData['name'] . ' not found');

            // Convert locale data into php data
            $request = $this->convert2PHP($request);
            try {
                $ret = $this->$funcData['name']($request);
            } catch (Exception $e) {
                $ret = array('exception' => 'Caught exception: ' . $e->getMessage());
            }

            if (is_null($ret)) {
                //do nothing!
            } else if (is_array($ret)) {
                if ($funcData['output'] == 'TEXT') {
                    echo implode("\n", $ret);
                } else if ($funcData['output'] == 'HTML') {
                    echo implode("<br />\n", $ret);
                } else {
                    if (isset($this->fields) && is_array($this->fields) && isset($ret['data'])) {
                        $ret['data'] = $this->convert2Locale($ret['data']);
                    }
                    echo json_encode($ret);
                }
            } else {
                echo $ret;
            }
            die();
        }
    }

}
