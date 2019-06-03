<?php

define('CLASS_PREFIX', 'eco_');

define('USE_JQGRID', false);
define('TAB_MODE', 'iframe');

define('MISSING_VALUE', 'MISSING_VALUE');
define('INVALID_VALUE', 'INVALID_VALUE');
define('UNIQUE_VIOLATION', 'UNIQUE_VIOLATION');
define('INVALID_LOOKUP_VALUE', 'INVALID_LOOKUP_VALUE');
define('INVALID_SIZE', 'INVALID_SIZE');
define('INVALID_DATE_FROM_TO_VALUE', 'INVALID_DATE_FROM_TO_VALUE');
define('CUSTOM_ERROR', 'CUSTOM_ERROR');

define('R3_AJAX_NO_ERROR', 'OK');
define('R3_AJAX_ERROR', 'ERROR');

require_once R3_LIB_DIR . 'obj.base_locale.php';
require_once R3_LIB_DIR . 'obj.base_security.php';
require_once R3_LIB_DIR . 'obj.base_table_config.php';
require_once R3_LIB_DIR . 'dbfunc.php';


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
        header('Content-Type: application/json');
        echo json_encode(array('exception' => $text));
    } else {
        echo $text;
    }
    die();
    return true;  /* Don't execute PHP internal error handler */
}

class R3Controller {

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

        return CLASS_PREFIX . R3Controller::getObjectType($request);   // Prefix!!!
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

        $name = R3Controller::getObjectType($request);
        $fileName = R3_CLASS_DIR . 'obj.' . $name . '.php';
        if (!file_exists($fileName)) {
            throw new Exception('Invalid value for parameter "on": "' . $request['on'] . '"');
        }

        require_once $fileName;
        $className = R3Controller::getObjectClassName($request);
        return new $className($request, $opt);
    }

}

/**
 * The base R3AppBaseObject class
 *
 */
abstract class R3AppBaseObject {

    // The object name
    protected $baseName;
    // Simple table extra parameters
    protected $tableURL;
    // Request array
    protected $request;
    // Option array
    protected $opt;
    // The auth object
    /**
     * User manager
     * @var IR3Auth
     */
    protected $auth;
    // List (simple)table
    protected $simpleTable;
    // Simple table ID prefix
    protected $simpleTablePrefixId = null;  // Used to set the id of the table. If null the object name is getted
    // Simple table html
    protected $tableHtml;
    // List limit
    protected $limit;
    // Page
    protected $pg;
    // Order
    protected $order;
    // Risultato ultima chiamata a getTotRecord (usato per nvaigation bar)
    protected $lastTotRecord;
    // Percorso con le immaigni (comprensivo di cache)
    protected $imagePath;
    // The registered ajax function list
    protected $ajaxFuncList = array();

    // Number of languages for the domain
    //protected $numLanguages;

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

        $this->imagePath = defined('BUILD') ? R3_APP_URL . substr(R3_IMAGES_URL, strlen(R3_APP_URL)) . BUILD . '/' : R3_IMAGES_URL;

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        if (defined('USE_JQGRID') && USE_JQGRID === true) {
            $this->registerAjaxFunction('getListData');
        }

        $this->method = isset($request['method']) ? $request['method'] : null;
        $this->isAjaxCall = false;

        // Apply filter
        $this->mangleFilter($request);
    }

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
    abstract function getPageTitle();

    // The SQL to generate the list
    abstract function getListSQL();

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

    public function getHTMLFilter() {
        return '';  // No filter
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

        $urlExtra = '';
        if (is_array($this->tableURL)) {
            foreach ($this->tableURL as $key => $val) {
                $urlExtra .= "&{$key}=" . urlencode($val);
            }
        }

        if (defined('USE_JQGRID') && USE_JQGRID === true) {
            // jqGrid
            $this->simpleTable = new simpleGrid("100%", 'grid', basename($_SERVER['PHP_SELF']) . "?on={$this->baseName}{$urlExtra}&method=getListData");
            $this->simpleTable->checkImage(false);
        } else {
            $this->simpleTable = new pSimpleTable("100%", 'grid', basename($_SERVER['PHP_SELF']) . "?on={$this->baseName}{$urlExtra}");
            $this->simpleTable->checkImage(false);
        }
    }

    // Return the array with all the filter values
    public function getFilterValues() {
        return array();
    }

    // Create the table headers
    abstract public function createListTableHeader(&$order);

    // Create the table footer
    public function createListTableFooter() {
        $this->tableHtml .= $this->simpleTable->MkTableFooter();
    }

    public function getListTableRowOperations(&$row) {
        return array();
    }

    public function getListTableRowStyle(&$row) {
        return array();
    }

    public function getListTableRowDefaultEvent(&$row) {
        if ($this->auth->hasPerm('MOD', strtoupper($this->baseName))) {
            return 'MOD';
        } else if ($this->auth->hasPerm('SHOW', strtoupper($this->baseName))) {
            return 'SHOW';
        }
        return null;
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
        if (get_class($this->simpleTable) == 'simpleGrid') {
            $this->simpleTable->setOptions(array('height' => -5));
        }

        // Apply the SQL order
        if ($this->order <> '') {
            $orderFields = $this->simpleTable->getSQLOrder($this->order);
            if ($orderFields <> '') {
                $sql .= " ORDER BY {$orderFields}";
            }
        }

        if ($this->limit > 0) {
            $sql .= " LIMIT " . (int) max(0, $this->limit);
            $sql .= " OFFSET " . (int) max(0, ($this->pg - 1) * $this->limit);
        }
        $stmt = $db->query($sql, PDO::FETCH_ASSOC);
        $rowNo = 0;
        $pkName = $this->getPrimaryKeyName();
        $prefix = $this->simpleTablePrefixId === null ? strtoupper($this->baseName) . '_' : $this->simpleTablePrefixId;
        $id = null;
        while ($row = $stmt->fetch()) {
            $rowNo++;
            $row['ROW_NO'] = $rowNo;
            $links = $this->getListTableRowOperations($row);
            $style = $this->getListTableRowStyle($row);
            $events = $this->getListTableRowEvent($row, $links);
            if ($pkName !== null && isset($row[$pkName]))
                $id = $row[$pkName];
            $this->tableHtml .= $this->simpleTable->CreateTableRow($row, $links, $style, $events, $prefix . $id);
        }

        $this->createListTableFooter();
        return $this->tableHtml;
    }

    public function getTemplateName() {
        if ($this->act == 'list' || $this->act == 'del') {
            return $this->baseName . '_list.tpl';
        } else {
            return $this->baseName . '_edit.tpl';
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
                $sql .= " ORDER BY {$orderFields}"; // . $this->simpleTable->getSQLOrder($this->order);
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
                            $value = SQLDateToStr($value, $format === false ? 'd/m/Y' : $format);  // TODO: Formato e separatore data/ora
                        }
                    } else if ($field['type'] == 'TIME') {
                        if ($value == '00:00:00') {
                            $value = '';
                        } else {
                            $value = SQLDateToStr($value, $format === false ? 'h:i:s' : $format);  // TODO: Formato e separatore data/ora
                        }
                    } else if ($field['type'] == 'DATETIME') {
                        if ($value == '0000-00-00 00:00:00') {
                            $value = '';
                        } else {
                            $value = SQLDateToStr($value, $format === false ? 'd/m/Y H:i:s' : $format); // TODO: Formato e separatore data/ora
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

    /**
     * override this method to if the form has the map
     */
    function hasDialogMap() {
        return false;
    }

    // Ricava i dati del singolo contribuente
    abstract public function getData($id = null);

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
                        $vlu[$key] = '';  // IE Fix in json response
                    } else {
                        $type = strToLower($field['type']);
                        switch ($field['type']) {
                            case 'real':
                            case 'double':
                            case 'number':
                            case 'float':
                                if (is_array($vlu[$key])) {
                                    foreach ($vlu[$key] as $key2 => $val2) {
                                        $vlu[$key][$key2] = forceFloat($vlu[$key][$key2], null, '.');
                                    }
                                } else {
                                    $vlu[$key] = forceFloat($vlu[$key], null, '.');
                                }

                                break;
                            case 'integer':
                            case 'lookup':
                                if (is_array($vlu[$key])) {
                                    foreach ($vlu[$key] as $key2 => $val2) {
                                        $vlu[$key][$key2] = forceInteger($vlu[$key][$key2], null, true, '.');
                                    }
                                } else {
                                    $vlu[$key] = forceInteger($vlu[$key], null, true, '.');
                                }
                                break;
                            case 'date':
                                if (is_array($vlu[$key])) {
                                    foreach ($vlu[$key] as $key2 => $val2) {
                                        $vlu[$key][$key2] = forceISODate($vlu[$key][$key2]);
                                    }
                                } else {
                                    $vlu[$key] = forceISODate($vlu[$key]);
                                }
                                break;
                            case 'datetime':
                            case 'now':
                                // TODO
                                break;
                            case 'boolean':
                                if (is_array($vlu[$key])) {
                                    foreach ($vlu[$key] as $key2 => $val2) {
                                        $vlu[$key][$key2] = forceBool($vlu[$key][$key2], $vlu[$key][$key2]);
                                    }
                                } else {
                                    $vlu[$key] = forceBool($vlu[$key], $vlu[$key]);
                                }
                        }
                    }
                }
            }
        }
        return $vlu;
    }

    // Convert data into locale data 
    public function convert2Locale(array $vlu) {
        // Change to locale
        $oldMessageLocale = setlocale(LC_MESSAGES, 0);
        $oldNumericLocale = setlocale(LC_NUMERIC, 0);

        setlocale(LC_NUMERIC, getLocaleInfo(R3Locale::getLanguageCode()));
        setLangInfo(array('thousands_sep' => "."));
        foreach ($this->fields as $key => $field) {
            if (isset($field['name'])) {
                $key = $field['name'];
                if (isset($field['dec']) && !isset($field['precision'])) {
                    $field['precision'] = $field['dec'];
                }
                if (isset($field['is_primary_key']) && $field['is_primary_key'] === true)
                    continue;
                if (array_key_exists($key, $vlu)) {
                    if ($vlu[$key] === null) {
                        $vlu[$key] = '';  // IE Fix in json response
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

        setlocale(LC_MESSAGES, $oldMessageLocale);
        setlocale(LC_NUMERIC, $oldNumericLocale);
        return $vlu;
    }

    public function getDataAsLocale($id = null) {
        // Set the locale to C before calling getData
        $oldMessageLocale = setlocale(LC_MESSAGES, 0);
        $oldNumericLocale = setlocale(LC_NUMERIC, 0);

        setlocale(LC_NUMERIC, 'C');
        $vlu = $this->getData($id);

        setlocale(LC_MESSAGES, $oldMessageLocale);
        setlocale(LC_NUMERIC, $oldNumericLocale);

        if ($vlu !== null && isset($this->fields) && is_array($this->fields)) {
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

    // Return the previewmap definition array or null if no preview map
    public function getPreviewMap() {
        return null;
    }

    public function checkField(array $formData, $key, $field, &$errors) {
        throw new exception('Unknown field type "' . $field['type'] . '"');
    }

    // Store/clear filter values
    public function mangleFilter(array $request, $params = null, array $ignoreParams = array()) {
        if ($params === null) {
            $params = $_GET;
        }
        $ignoreParams = array_merge(array('is_filter', 'on', 'act', 'pg', 'order'), $ignoreParams);
        $reset = array_key_exists('reset', $this->request);
        $init = array_key_exists('init', $this->request);
        if (!isset($_SESSION[$this->baseName]['filter']) || $reset || $init) {
            $this->filter = array();
        } else {
            $this->filter = $_SESSION[$this->baseName]['filter'];
        }
        if ($init) {
            $_SESSION[$this->baseName]['filter'] = array();
        }
        if (array_key_exists('is_filter', $request)) {
            // Set/clear filter
            foreach ($params as $paramName => $dummy) {
                if (isset($request[$paramName]) && array_search($paramName, $ignoreParams) === false) {
                    $this->filter[$paramName] = $request[$paramName];
                } else {
                    if (isset($this->filter[$paramName])) {
                        unset($this->filter[$paramName]);
                    }
                }
            }
            $_SESSION[$this->baseName]['filter'] = $this->filter;
        }
    }

    // get filter value
    public function getFilterValue($name, $default = null) {
        if (isset($_SESSION[$this->baseName]['filter'][$name])) {
            return $_SESSION[$this->baseName]['filter'][$name];
        }
        return $default;
    }

    /**
     * Set the mu_id for the user from auth, installation, mu_id & mu_name
     * @param type $request 
     */
    public function setMunicipalityForUser(&$request) {
        if ($this->auth->getParam('mu_id') <> '') {
            $request['mu_id'] = $this->auth->getParam('mu_id');
            return true;
        }
        $muList = R3EcoGisHelper::getMunicipalityList($this->do_id);
        if (count($muList) == 1) {
            $request['mu_id'] = key($muList);
            return true;
        }
        if (isset($request['mu_name'])) {
            $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
            return true;
        }
        // User has more municipality
        return false;
    }

    public function checkFormData(array $formData, array $errors = array()) {
        $db = ezcDbInstance::get();
        if (!isset($this->fields) || !is_array($this->fields)) {
            throw new exception("\$this->fields is null or is not an array. Check your constructor!");
        }
        foreach ($this->fields as $key => $field) {
            $toCheck = true;
            if (isset($field['name'])) {  // Accetto array con chiave nome del campo o il nome del compo in $field['name']
                $key = $field['name'];
            }
            // Auto values
            if (isset($field['value'])) {
                $formData[$key] = $field['value'];
            }

            // Required check
            if ($toCheck && isset($field['required']) && $field['required'] === true) {
                if (!isset($formData[$key]) || (string) $formData[$key] === '') {
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
                        if (is_array($formData[$key])) {
                            foreach ($formData[$key] as $key2 => $val2) {
                                if ($formData[$key][$key2] <> '' && forceFloat($formData[$key][$key2], null, '.') === null) {
                                    $errors[$key] = INVALID_VALUE;
                                    $toCheck = false;
                                }
                            }
                        } else {
                            if ($formData[$key] <> '' && forceFloat($formData[$key], null, '.') === null) {
                                $errors[$key] = INVALID_VALUE;
                                $toCheck = false;
                            }
                        }
                        break;
                    case 'year':
                    case 'integer':
                        if (is_array($formData[$key])) {
                            foreach ($formData[$key] as $key2 => $val2) {
                                if ($formData[$key][$key2] <> '' && forceInteger($formData[$key][$key2]) === null) {
                                    $errors[$key] = INVALID_VALUE;
                                    $toCheck = false;
                                }
                            }
                        } else {
                            if ($formData[$key] <> '' && forceInteger($formData[$key]) === null) {
                                $errors[$key] = INVALID_VALUE;
                                $toCheck = false;
                            }
                        }
                        break;
                    case 'date':
                        if (is_array($formData[$key])) {
                            foreach ($formData[$key] as $key2 => $val2) {
                                if ($formData[$key][$key2] <> '' && forceISODate($formData[$key][$key2], null, 'ISO') == '') {
                                    $errors[$key] = INVALID_VALUE;
                                    $toCheck = false;
                                }
                            }
                        } else {
                            if ($formData[$key] <> '' && forceISODate($formData[$key], null, 'ISO') == '') {
                                $errors[$key] = INVALID_VALUE;
                                $toCheck = false;
                            }
                        }
                        break;
                    case 'time':
                        break;
                    case 'text':
                    case 'string':
                        if (is_array($formData[$key])) {
                            foreach ($formData[$key] as $key2 => $val2) {
                                if (isset($field['size']) && mb_strlen($formData[$key][$key2]) > $field['size']) {
                                    $errors[$key] = INVALID_VALUE;
                                    $toCheck = false;
                                }
                            }
                        } else {
                            if (isset($field['size']) && mb_strlen($formData[$key]) > $field['size']) {
                                $errors[$key] = INVALID_SIZE;
                                $toCheck = false;
                            }
                        }
                        break;
                    case 'uid':   // UID
                    case 'boolean':
                    case 'lookup':
                        // No test
                        break;

                    default:
                        $this->checkField($formData, $key, $field, $errors);
                }
            }

            // Lookup check
            if (isset($field['lookup']) && isset($formData[$key]) && $formData[$key] <> '') {
                if (isset($field['lookup']['table'])) {
                    $masterField = isset($field['lookup']['field']) ? $field['lookup']['field'] : $key;

                    if (is_array($formData[$key])) {
                        foreach ($formData[$key] as $key2 => $val2) {
                            $sql = sprintf("SELECT COUNT(*) FROM %s WHERE $masterField=%s", $field['lookup']['table'], $db->quote($formData[$key][$key2]));
                            if ($formData[$key][$key2] == '' || $db->query($sql)->fetchColumn(0) == 0) {
                                $errors[$key] = INVALID_LOOKUP_VALUE;
                                $toCheck = false;
                            }
                        }
                    } else {
                        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE $masterField=%s", $field['lookup']['table'], $db->quote($formData[$key]));
                        if ($db->query($sql)->fetchColumn(0) == 0) {
                            $errors[$key] = INVALID_LOOKUP_VALUE;
                            $toCheck = false;
                        }
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

    // Imposta uno o più attributi per un campo. Se $add è true il campo se non esiste viene creato
    public function removeField($name) {
        $field = null;
        foreach ($this->fields as $key => $f) {
            if (isset($f['name']) && $f['name'] == $name) {
                unset($this->fields[$key]);
            }
        }
        return $field;
    }

    // Restituisce il nome della chiava primaria
    public function getPrimaryKeyName() {
        if (!isset($this->fields))
            return null;
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
            $label = isset($field['label']) ? $field['label'] : $key;
            if (is_array($val)) {
                // NOT Standard error text
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
        return _("Attenzione:") . "\n - " . implode("\n - ", $errs);
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
    function applyData($data = null, array $validActions = array('add', 'mod', 'del')) {
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

    // Prova a cancellare un oggetto (in transazione) per verificarne la possibilità
    public function tryDeleteData($id, $table = null, $pkName = null) {
        $table = $table === null ? $this->table : $table;
        $pkName = $pkName === null ? $this->getPrimaryKeyName() : $pkName;
        $db = ezcDbInstance::get();
        $db->beginTransaction();
        $result = true;
        try {
            $sql = "DELETE FROM {$table} WHERE {$pkName}={$id}";
            $db->exec($sql);
        } catch (Exception $e) {
            $result = false;
        }
        $db->rollback();
        return $result;
    }

    protected function null2text($value, $strict = true, $quote = false) {
        $db = ezcDbInstance::get();
        if ($value === null)
            return 'NULL';
        if ($strict && $value == '')
            return 'NULL';
        if ($quote)
            return $db->quote($value);
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
                if ((string) $value == '') {  // 2010-11-25: empty string is null, 0 is 0!
                    return $this->null2text($default);
                }
                if (!is_numeric($value))
                    $value = forceInteger($value);
                return (string) $value;
            case 'boolean':
                if ($value == '') {
                    if ($default === true || $default === false)
                        return $db->quote($default === true ? 'T' : 'F');
                    return $this->null2text($default);
                }
                return (strtoupper(substr($value, 0, 1)) == 'T' || $value == 1) ? 'TRUE' : 'FALSE';
            case 'lookup':
                if ((string) $value == '') {
                    return $this->null2text($default);
                }
                return $value;
            case 'date':
                if ($value == '') {
                    return $this->null2text($default);
                }
                return $db->quote(forceISODate($value, null, 'ISO'));
            case 'time':
                if ($value == '') {
                    return $this->null2text($default);
                }
                return $db->quote($value);
            case 'string':
            case 'color':
            case 'text':
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
        if (!isset($this->table))
            throw new Exception('Missing table name');

        $sql = 'INSERT INTO ' . $this->table . ' (' .
                implode(', ', $fields) . ' ' .
                ') VALUES (' .
                implode(', ', $values) . ')';

        if ($db->exec($sql) === false) {
            throw new Exception("Save faild: sql={$sql}; error-info: ") . var_export($sth->errorInfo(), true);
        }
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
        if ($db->exec($sql) === false) {
            throw new Exception("Save faild: sql={$sql}; error-info: ") . var_export($sth->errorInfo(), true);
        }
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
        if ($db->exec($sql) === false) {
            throw new Exception("Save faild: sql={$sql}; error-info: ") . var_export($sth->errorInfo(), true);
        }
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
        if ($this->method == $name) {
            $this->isAjaxCall = true;
        }
        $this->ajaxFuncList[$name] = array('name' => $name, 'output' => strtoupper($output));
    }

    /**
     * Process an jQuery ajax request
     */
    public function processAjaxRequest() {
        global $ajaxResponseType;
        if (isset($_REQUEST['method'])) {
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
                //$ret = array('exception'=>'Caught exception: ' .  $e->getMessage() . "\n\n" . $e->getTraceAsString());
            }

            if (is_null($ret)) {
                //do nothing!
            } else if (is_array($ret)) {
                if ($funcData['output'] == 'TEXT') {
                    echo implode("\n", $ret);
                } else if ($funcData['output'] == 'HTML') {
                    echo implode("<br />\n", $ret);
                } else {
                    if (isset($this->fields) && is_array($this->fields) && isset($ret['data']) && is_array($ret['data'])) {
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

    /**
     * Verifica che il comune inserito sia valido e dell'utente, altrimenti genera un errore
     * Attenzione: questa funzione viene usata anche in lib/custom.map.php
     *
     * @param array $formData      I dati del submit
     * @param array $errors        Array con altri errori
     * @return integer|null        Il codice del comune o null
     */
    static public function checkFormDataForMunicipality(array $formData, array &$errors) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $mu_id = '';
        if (1 == 2 && $this->auth->getParam('mu_id') <> '') {  // TODO: PERMISSION
            $mu_id = $this->auth->getParam('mu_id');
        } else if (isset($formData['mu_name']) && $formData['mu_name'] <> '') {
            $mu_id = $db->query("SELECT mu_id FROM municipality WHERE mu_name_{$lang} ILIKE " . $db->quote($formData['mu_name']))->fetchColumn();
            if ($mu_id == '') {
                $errors['mu_id'] = array('CUSTOM_ERROR' => _('Il comune immesso non è stato trovato'));
                if (isset($this))
                    $this->removeField('mu_id');
                return -1;
            }
        } else if (isset($formData['mu_id']) && $formData['mu_id'] <> '') {
            $mu_id = $formData['mu_id'];
        }
        if ($mu_id == '') {
            $errors['mu_id'] = array('CUSTOM_ERROR' => _("Il campo \"Comune\" è obbligatorio"));
            return -1;
        }
        return $mu_id;
    }

    /**
     * Return the help data (ajax)
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function getHelp($request) {
        require_once R3_LIB_DIR . 'eco_help.php';
        $body = R3Help::getHelpPartFromSection($request['section'], $request['id'], R3Locale::getLanguageCode());
        // Help can be cacheable
        $ttl = 1 * 24 * 60 * 60;
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
        header('Cache-Control: max-age=' . $ttl);
        header("Pragma: public", true);
        return array('data' => $body !== null ? $body : '');
    }

}

/**
 * The base R3AppBaseFileObject class
 *
 * Base class with file management facility (upload/download, ...)
 */
abstract class R3AppBaseFileObject extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        require_once R3_LIB_DIR . 'r3delivery.php';
    }

    /**
     * Return the document path from id and extra category
     */
    public function getDocPath($type, $kind, $id, $isPreview = false) {

        $path = R3_UPLOAD_DIR;
        if ($type != '') {
            $path .= $type . '/';
        }
        if ($kind != '') {
            $path .= $kind . '/';
        }
        $path .= sprintf('%08d', $id / 1000) . '/';
        if ($isPreview != '') {
            $path .= 'thumb/';
        }
        return $path;
    }

    /**
     * Return the document path + name form the specified $orgName
     */
    public function getDocFullName($orgName, $type, $kind, $id, $isPreview = false) {

        $path = $this->getDocPath($type, $kind, $id, $isPreview);
        $ext = strToLower(substr(strrchr($orgName, '.'), 1));
        $name = $path . ($isPreview ? 'thumb_' : '') . sprintf('%08d', $id) . '.' . $ext;
        return $name;
    }

    abstract public function deliver($type);

    // Return the full name
    public function extractFileName($name, $maxLen) {
        $ext = trim(mb_strtolower(mb_strrchr($name, '.')));
        $name = mb_substr($name, 0, mb_strlen($name) - mb_strlen($ext));
        $name = mb_substr($name, 0, max(1, $maxLen - mb_strlen($ext))) . $ext;
        return $name;
    }

    // Return the file ext
    public function extractFileExt($name) {
        $ext = trim(mb_strtolower(mb_strrchr($name, '.')));
        return $ext;
    }

    /**
     * Return the old document file id and its name
     */
    public function getDocFileInfo($doc_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT doc_file_id, doc_file, doct_id FROM document WHERE doc_id=" . (int) $doc_id;
        return $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return the old document file id and its name
     */
    public function getDocFileInfoByFileId($doc_file_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT doc_id, doc_file_id, doct_code, doc_object_id, doc_file
                FROM document doc
                INNER JOIN document_type doct ON doc.doct_id=doct.doct_id 
                WHERE doc_file_id=" . (int) $doc_file_id;
        return $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return the new document file id generated by a postgres sequence
     */
    public function getDocFileId() {

        $db = ezcDbInstance::get();
        $sql = "SELECT NEXTVAL('document_doc_file_id_seq')";
        return $db->query($sql)->fetchColumn(0);
    }

    /**
     * Add a file
     * param string  $orgName        the original file name with extension
     * param string  $type           the type (1st folder. Eg: document)
     * param string  $kind           the kind (2st folder. Eg: building)
     * param integer $id             the file id (doc_file_id)
     * param string  $fsName         the filesistem name ($_FILES[...][tmp_name])
     */
    public function removeOldFile($orgName, $type, $kind, $id) {
        require_once R3_LIB_DIR . 'simpledoc.php';
        $path = '';
        if ($type != '') {
            $path .= $type . '/';
        }
        if ($kind != '') {
            $path .= $kind . '/';
        }
        $doc = new pSimpleDoc(R3_UPLOAD_DIR, '', $path);
        return $doc->deleteDocument($id, $orgName);
    }

    /**
     * Add a file
     * param string  $orgName        the original file name with extension
     * param string  $type           the type (1st folder. Eg: document)
     * param string  $kind           the kind (2st folder. Eg: building)
     * param integer $id             the file id (doc_file_id)
     * param string  $fsName         the filesistem name ($_FILES[...][tmp_name])
     */
    public function addFile($orgName, $type, $kind, $id, $fsName) {
        require_once R3_LIB_DIR . 'simpledoc.php';
        $path = '';
        if ($type != '') {
            $path .= $type . '/';
        }
        if ($kind != '') {
            $path .= $kind . '/';
        }
        $doc = new pSimpleDoc(R3_UPLOAD_DIR, '', $path);
        return $doc->AddDocument($id, $fsName, $this->extractFileExt($orgName), true, true);
    }

}

/**
 * The base R3AppBasePhotoObject class
 *
 * Base class with photo management facility (upload/download/resize/rotate/convert, ...)
 */
abstract class R3AppBasePhotoObject extends R3AppBaseFileObject {

    function resizeImage($src, $dest, $width, $height) {

        if ($src == $dest) {
            throw new exception('Source and destination image are the same');
        }
        $prev_path = dirname($dest);
        if (!file_exists($prev_path)) {
            mkdir($prev_path);
        }
        require_once R3_LIB_DIR . 'simplephoto.php';
        return pSimplePhoto::CreateThumb($src, $dest, $width, $height);
    }

    /**
     * Add a file
     * param string  $orgName        the original file name with extension
     * param string  $type           the type (1st folder. Eg: document)
     * param string  $kind           the kind (2st folder. Eg: building)
     * param integer $id             the file id (doc_file_id)
     * param string  $fsName         the filesistem name ($_FILES[...][tmp_name])
     */
    public function removeOldFile($orgName, $type, $kind, $id) {
        require_once R3_LIB_DIR . 'simplephoto.php';
        $path = '';
        if ($type != '') {
            $path .= $type . '/';
        }
        if ($kind != '') {
            $path .= $kind . '/';
        }
        $doc = new pSimplePhoto(R3_UPLOAD_DIR . $path, '');
        return $doc->DeletePhoto($id, $orgName);
    }

    /**
     * Add a file
     * param string  $orgName        the original file name with extension
     * param string  $type           the type (1st folder. Eg: document)
     * param string  $kind           the kind (2st folder. Eg: building)
     * param integer $id             the file id (doc_file_id)
     * param string  $fsName         the filesistem name ($_FILES[...][tmp_name])
     */
    public function moveOldFile($orgName, $type, $kind, $id) {
        require_once R3_LIB_DIR . 'simplephoto.php';

        $ext = substr(strrchr($orgName, '.'), 1);
        $srcPath = R3_UPLOAD_DIR;
        $destPath = R3_UPLOAD_DIR . 'document/';
        if ($type != '') {
            $srcPath .= $type . '/';
        }
        if ($kind != '') {
            $srcPath .= $kind . '/';
        }
        $srcPath .= sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/';
        $destPath .= sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/';
        $srcFile = $srcPath . sprintf('%08d', $id) . ".{$ext}";
        $destFile = $destPath . sprintf('%08d', $id) . ".{$ext}";
        $thumbFile = $srcPath . PHOTO_THUMB_DIR . '/' . PHOTO_THUMB_DIR . '_' . sprintf('%08d', $id) . ".{$ext}";

        // Remove thumbnail
        if (file_exists($thumbFile)) {
            unlink($thumbFile);
        }
        // Move file
        if (!file_exists($destPath)) {
            mkdir($destPath);
        }
        if (file_exists($srcFile)) {
            if (!rename($srcFile, $destFile)) {
                throw new Exception("Error moving file from {$srcFile} to {$destFile}");
            }
        }
        return true;
    }

    static public function extractExifDate($exifDate) {

        $exifDate = str_replace('.', ':', $exifDate);
        $exifDate = str_replace('-', ':', $exifDate);
        $a1 = explode(' ', $exifDate);
        if (count($a1) == 1) {
            $a2 = explode(':', $a1[0]);
            if (count($a2) == 3) {
                if (checkdate($a2[1], $a2[2], $a2[0])) {
                    return $a2[0] . '-' . $a2[1] . '-' . $a2[2];
                }
            }
        } else {
            $a2 = explode(':', $a1[0]);
            if (count($a2) == 3) {
                if (!checkdate($a2[1], $a2[2], $a2[0])) {
                    return null;
                }
                $date = $a2[0] . '-' . $a2[1] . '-' . $a2[2];
            }
            $a2 = explode(':', $a1[1]);
            $date .= ' ' . sprintf('%02d', $a2[0]) . ':' . sprintf('%02d', $a2[1]) . ':' . sprintf('%02d', $a2[2]);
            return $date;
        }
        return null;
    }

    public function getExifData($fileName) {

        $result = array('title' => null, 'date' => null);
        if (extension_loaded('exif')) {
            $exif = @exif_read_data($fileName);
            if (is_array($exif)) {
                $s = trim(@$exif['Make']) . ' ' .
                        trim(@$exif['Model']) . ' ' .
                        trim(@$exif['ImageDescription']);
                if (isset($exif['XResolution']) && isset($exif['YResolution'])) {
                    $s .= str_replace('/1', '', $exif['XResolution']) . 'x' . str_replace('/1', '', $exif['YResolution']) . ' dpi';
                }
                $result['title'] = trim($s);
                if (isset($exif['DateTime']) && trim($exif['DateTime']) <> '') {
                    $result['date'] = $this->extractExifDate($exif['DateTime']);
                } else if (isset($exif['DateTimeOriginal']) && trim($exif['DateTimeOriginal']) <> '') {
                    $result['date'] = $this->extractExifDate($exif['DateTime']);
                } else if (isset($exif['DateTimeDigitized']) && trim($exif['DateTimeDigitized']) <> '') {
                    $result['date'] = $this->extractExifDate($exif['DateTime']);
                }
            }
        }
        return $result;
    }

}

/**
 * The base R3AppBaseReportObject class
 *
 * Base class for report facility
 */
abstract class R3AppBaseReportObject extends R3AppBaseObject {

    /**
     * Return basic information for the report
     */
    public function getBasicInformation($reportCode, $reportTitle = '', $opt = array()) {

        $result = array();
        // Configuration data
        if (defined('APPLICATION_CODE'))
            $result['application-application-code'] = APPLICATION_CODE;
        if (defined('DOMAIN_NAME'))
            $result['application-domain-name'] = DOMAIN_NAME;
        if (defined('R3_APP_ROOT'))
            $result['application-dir'] = R3_APP_ROOT;
        if (defined('R3_CONFIG_DIR'))
            $result['application-config-dir'] = R3_CONFIG_DIR;
        if (defined('R3_CUSTOMER_CONFIG_DIR'))
            $result['application-user-config-dir'] = R3_CUSTOMER_CONFIG_DIR;
        if (defined('R3_WEB_DIR'))
            $result['application-web-dir'] = R3_WEB_DIR;
        if (defined('R3_TMP_DIR'))
            $result['application-tmp-dir'] = R3_TMP_DIR;
        if (defined('R3_CACHE_DIR'))
            $result['application-cache-dir'] = R3_CACHE_DIR;
        if (defined('R3_APP_URL'))
            $result['application-url'] = R3_APP_URL;
        if (defined('R3_FOP_CMD'))
            $result['application-fop-cmd'] = R3_FOP_CMD;
        if (defined('R3_APP_CHARSET'))
            $result['application-charset'] = R3_APP_CHARSET;
        if (defined('R3_APP_CHARSET_DB'))
            $result['application-database-charset'] = R3_APP_CHARSET_DB;

        $result['application-title'] = $this->auth->getConfigValue('APPLICATION', 'TITLE', APPLICATION_CODE);

        // - Print Information
        $result['print-code'] = $reportCode;
        $result['print-title'] = $reportTitle;
        $result['print-date'] = date('d/m/Y');
        $result['print-datetime'] = date('d/m/Y H:i:s');
        $result['print-author'] = $this->auth->getParam('us_name');

        // User information
        foreach ($opt as $key => $val) {
            $result[$key] = $val;
        }

        return $result;
    }

}

