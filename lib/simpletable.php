<?php

/* * ***************************************************** */

// SimpleTable v1.0.2     26/09/2005
// 1.0.0 Versione base
// 1.0.1 supporto immagini (10/11/2005)
// 1.0.2 supporto tabelle ridimensionabili (29/12/2005)
// 1.0.3 i testi non vengono tagliati in modo, che non
// ci siano due righe (03/02/2006)
// 1.0.4 Double click sulla riga fa un'azione
// (26/06/2006)
// 1.0.5 Aggiunto tipo EURO (20/03/2007)
// 1.0.6 function valueToEuro is E_STRICT combatible
// 1.1.0 new function AddSimpleField
// 1.1.0 function AddField has a new parameter to set the sort fields
// 1.1.0 function AddCalcValue accept a numeric position or
//       a string with the name of the field
// 1.1.0 function CreateTableRow accept also an associative array
// 1.1.0 function getSQLOrder support multiple sort
// 1.1.1 new function checkImage() if set to false the image size of the links are not checked
// 1.1.2 new private variable charset. Default constant R3_APP_CHARSET then global variable $charset, then ISO-8859-1. New function get/setCharset
// 1.1.3 new private variable name. Default value simpletable. New function get/setName
// 1.1.3 add checkbox to the simple table.
// 1.1.3 new function getCalendarHeader to het the javascript to put in the header function. Needed by checkbox
// 1.1.3 the field definition is internally converted to upper
// 1.1.3 embedded navigation bar (mkNavigationBar)
// 1.1.3 renamed constructor pSimpleTable to __construct
// 1.1.4 Prevent to check image if checkImage is set to false
// 17/01/2008
// 1.1.5 Add param "SetHtmlEntities" to function "AddCalcValue", which use htmlentities on value if true
// 1.1.5 Add param "ShowAlways" to function "mkNavigationBar", which returns empty string if only one page is present
// 1.1.5 Add function "getVersionString"
// 1.1.5 Add function "setCheckboxValue" as not implemented
// 1.1.5 Bug Fix for function "valueToEuro". If value is empty, return empty string
// 01/02/2008
// 1.1.6 if addSimpleField $extraData accept the "hint" param, wich force the table header hint (strip the html tags)
// 1.1.6 in function CreateTableHeader the old hint (same as the label) is trimmed
// 1.1.6 if addSimpleField $extraData accept the "format" param, wich set the output format of the colums.
//       Applicable to DATE, TIME, DATETIME (using SQLDateToStr),  NUMBER, INTEGER, DOUBLE, FLOAT, STRING, TEXT using printf function
// 1.1.6 The column type EURO is now deprecated
// 08/02/2008
// 1.1.7 Add constant R3SIMPLETABLE_VERSION_SUPPORT
// 21/03/2008
// 1.1.8 Use php function gettext instead of variable $lbl in case when index is not set
//       Add new function appendCustomHTMLRow
// 15/05/2008
// 1.1.9 addSimpleField $extraData accept the "number_format". It can be the number of decimal or an array with the format parameters
//       If this parameter is given the r3locale.php library is required
// 29/05/2008
// 1.2 new function addLink, addAlert
// 03/06/2008
// 1.2.1 New parameter "wrap" for "$opt" in addSimpleField. If true don't add the &nbsp; to the string
//       in addCalcValue the parameter can be an array[2] of boolean: if the 1st is true the HtmlEntities function applied to the text
//                       if the 2st is true the HtmlEntities function applied to the hint
// 24/06/2008
// 1.2.2 Add field-types: URL, URL:MAILTO
// 24/06/2008
// 1.2.3 Add possibility to overwrite js event calls
// 29/10/2008
// 1.2.3.1 Force array for extraData in function addSimpleField
// 30/12/2008
// 1.2.3.2 in number format the param decimals can be null. In this case the decimal part length is the same as printf('%s') with locale settings
// 27/01/2009
// 1.2.3.3 The hidden field pg set the name and the id of itself
// 11/07/2009
// 1.2.3.4 Some html validation adjustment
// 16/12/2010
// 1.2.3.5 add extra text to the link field (eg: to show the number of record of detail table)

/* * ****************************************************** */

if (defined("__SIMPLETABLE_PHP__"))
    return;
define("__SIMPLETABLE_PHP__", 1);

if (!defined("__DATEUTILS_PHP__")) {
    require_once 'dateutils.php';
}

define('R3SIMPLETABLE_VERSION', '1.2');

/**
 * Change this constant will increase some of new features used by simpletable. Default set to 1.1.2, which is the most used and stable Version.
 * @since 1.1.7
 */
if (!defined('R3SIMPLETABLE_VERSION_SUPPORT'))
    define('R3SIMPLETABLE_VERSION_SUPPORT', '1.1.2');

// Config: Type EURO

$ST_EURO_PRECISION = 2;
$ST_EURO_SYMBOL = '&euro;';
$ST_EURO_DELIMETER = ',';

/**
 * convert value to formated EURO value
 * @since Version 1.0.5
 *
 * @param $value
 * @return string formated value
 */
function valueToEuro($value) {
    global $ST_EURO_PRECISION, $ST_EURO_SYMBOL, $ST_EURO_DELIMETER;

    if (trim($value) == '')
        return '';

    $partArr = explode('.', $value);

    if (isset($partArr[1]) && strlen($partArr[1]) > $ST_EURO_PRECISION) {
        $partArr[1] = substr($partArr[1], 0, $ST_EURO_PRECISION);
    } else if (isset($partArr[1])) {
        $partArr[1] = str_pad($partArr[1], $ST_EURO_PRECISION, '0', STR_PAD_RIGHT);
    } else {
        $partArr[1] = str_pad('', $ST_EURO_PRECISION, '0', STR_PAD_RIGHT);
    }

    $value = $partArr[0] . $ST_EURO_DELIMETER . $partArr[1] . ' ' . $ST_EURO_SYMBOL;

    return $value;
}

// Support functions
// Add a parameter to an URL
if (!function_exists('AddURLParam')) {

    function AddURLParam($url, $param, $value) {
        if (strpos($url, '?') === false)
            $url .= '?';
        else
            $url .= '&amp;';
        return $url . $param . '=' . urldecode($value);
    }

}

class pSimpleTable {

    public $fields;           // Elenco dei campi
    public $width;            // Dimensione in pixel della tabella
    public $baseclass;        // Classe di base della tabella
    public $baseurl;          // URL di base della tabella (usato per ordinamenti)
    public $orderparam;       // Parametro GET usato per l'ordinamento
    public $startparam;       // Parametro GET usato per l'inizio visualizzazione
    public $calcvalues;       // Valori campi calcolati
    public $SetHtmlEntities;  // Se true applica HtmlEntities a tutte le label
    private $checkImage = false;  // If false don't check the image size (usefull for http images)
    private $charset = 'ISO-8859-1';
    private $jsOutputDone = false;  // Se true l'output javascript è stato fatto
    private $name = 'simpletable';  // Nome della simple table
    private $checkValuesTmp = array();  // Temporary Array for checkbox values
    private $headerOutputDone = false;  // If true the CreateTableHeader function was called
    private $jsEvents = array();

    // Constructor
    public function __construct($width = 500, $baseclass = 'grid', $baseurl = '', $orderparam = 'order', $startparam = 'start', $SetHtmlEntities = true) {
        global $charset;

        $this->fields = array();
        $this->links = array();
        // $this->calcvalues = array()
        $this->width = $width;
        $this->baseclass = $baseclass;
        if ($baseurl == '')
            $this->baseurl = basename($_SERVER['PHP_SELF']);
        else
            $this->baseurl = $baseurl;
        $this->orderparam = $orderparam;
        $this->startparam = $startparam;
        $this->SetHtmlEntities = $SetHtmlEntities;

        if (defined('R3_APP_CHARSET'))
            $this->charset = R3_APP_CHARSET;
        else if (isset($charset))
            $this->charset = $charset;

        // - register javascript default functions
        $this->jsEvents['onChangeOrder'] = "simpletable_onOrderChange";
        $this->jsEvents['onChangePage'] = "simpletable_onPageChange";
        $this->jsEvents['onChangeNavi'] = "simpletable_onNaviEvent";
    }

    /**
     * Get the module version
     *
     * @return string with the version number
     * @access public
     */
    public function getVersionString() {
        return R3SIMPLETABLE_VERSION;
    }

    /**
     * add a new field definition (this function will replace "addField")
     * @since Version 1.1.0
     *
     * @param string $label
     * @param string $fieldName
     * @param string $field_type
     * @param mixed $width
     * @param array $extraData
     * @return array with field definition
     */
    public function addSimpleField($label, $fieldName, $field_type, $width = -1, array $extraData = array()) {

        $res = array();
        $res['label'] = $label;
        $res['field'] = $fieldName;
        $res['type'] = strToUpper($field_type);
        if ($res['type'] == 'CHECK') {
            $res['type'] = 'CHECKBOX';
        }
        $res['width'] = $width;
        if (isset($extraData['len']))
            $res['len'] = $extraData['len'];
        else
            $res['len'] = -1;
        if (isset($extraData['align']))
            $res['align'] = $extraData['align'];
        else
            $res['align'] = '';
        if (isset($extraData['key']))
            $res['key'] = $extraData['key'];
        else
            $res['key'] = false;
        if (isset($extraData['visible']))
            $res['visible'] = $extraData['visible'];
        else
            $res['visible'] = true;
        if (isset($extraData['editable']))
            $res['editable'] = $extraData['editable'];
        else
            $res['editable'] = true;
        if (isset($extraData['mandatory']))
            $res['mandatory'] = $extraData['mandatory'];
        else
            $res['mandatory'] = false;
        if (isset($extraData['sortable']))
            $res['sortable'] = $extraData['sortable'];
        else
            $res['sortable'] = false;
        if (isset($extraData['order_fields']))
            $res['order_fields'] = $extraData['order_fields'];
        else
            $res['order_fields'] = $fieldName;
        if (isset($extraData['header_checkbox']))
            $res['header_checkbox'] = $extraData['header_checkbox'];
        else
            $res['header_checkbox'] = false;
        if (isset($extraData['hint']))
            $res['hint'] = $extraData['hint'];
        else
            $res['hint'] = false;
        if (isset($extraData['format']))
            $res['format'] = $extraData['format'];
        else
            $res['format'] = false;
        if (isset($extraData['number_format']))
            $res['number_format'] = $extraData['number_format'];
        else
            $res['number_format'] = false;
        if (isset($extraData['wrap']))
            $res['wrap'] = $extraData['wrap'];
        else
            $res['wrap'] = false;

        $this->fields[$fieldName] = $res;

        return $res;
    }

    /**
     * Remove column by field name
     * @param string $fieldName
     */
    public function removeField($fieldName) {
        if (!empty($this->fields[$fieldName])) {
            unset($this->fields[$fieldName]);
        }
    }

    /**
     * set Charset to use for SimpleTable
     * @since Version 1.1.2
     *
     * @param string $charset
     */
    public function setCharset($charset) {
        $this->charset = $charset;
    }

    /**
     * get Charset used by SimpleTable
     * @since Version 1.1.2
     *
     * @return string with charset
     */
    public function getCharset() {
        return $this->charset;
    }

    /**
     * set Name to use for SimpleTable
     * @since Version 1.1.3
     *
     * @param string $charset
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * get Name used by SimpleTable
     * @since Version 1.1.3
     *
     * @return string with name
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Store values from checkbox definitively if checkbox field exists
     * @since Version 1.1.3
     *
     * @param boolean $reset
     */
    private function storeCheckboxValues($reset) {
        foreach ($this->fields as $field) {
            if ($field['type'] == 'CHECKBOX') {
                if ($reset === true) {
                    $_SESSION[$this->name][$field['field'] . '_header'] = 'F';
                    if (isset($_SESSION[$this->name][$field['field']])) {
                        foreach ($_SESSION[$this->name][$field['field']] as $key => $value) {
                            unset($_SESSION[$this->name][$field['field']][$key]);
                        }
                    }
                    if (isset($_SESSION[$this->name][$field['field'] . '_off'])) {
                        foreach ($_SESSION[$this->name][$field['field'] . '_off'] as $key => $value) {
                            unset($_SESSION[$this->name][$field['field'] . '_off'][$key]);
                        }
                    }
                    continue;
                }

                // - header Element
                if (isset($this->checkValuesTmp[$field['field'] . '_header']))
                    $_SESSION[$this->name][$field['field'] . '_header'] = $this->checkValuesTmp[$field['field'] . '_header'];
                else
                    $_SESSION[$this->name][$field['field'] . '_header'] = 'F';

                // if (!isset($this->checkValuesTmp[$field['field']]))
                // continue;
                // - store Values (row Elements)
                if (!isset($this->checkValuesTmp[$field['field']]))
                    $storeValues = array();
                else
                    $storeValues = $this->checkValuesTmp[$field['field']];
                if (is_array($storeValues) && count($storeValues) > 0) {
                    if (!isset($_SESSION[$this->name][$field['field']]))
                        $_SESSION[$this->name][$field['field']] = array();

                    foreach ($storeValues as $value) {
                        if (!in_array($value, $_SESSION[$this->name][$field['field']]))
                            $_SESSION[$this->name][$field['field']][] = $value;
                    }
                }

                // - difference Values
                if (array_key_exists($field['field'] . '_hd', $this->checkValuesTmp)) {
                    $allValues = $this->checkValuesTmp[$field['field'] . '_hd'];
                } else {
                    $allValues = array();
                }
                if (is_array($allValues) && count($allValues) > 0) {
                    $diffValues = array_diff($allValues, $storeValues);
                    if (!isset($_SESSION[$this->name][$field['field']]))
                        $_SESSION[$this->name][$field['field']] = array();
                    if (!isset($_SESSION[$this->name][$field['field'] . '_off']))
                        $_SESSION[$this->name][$field['field'] . '_off'] = array();

                    foreach ($diffValues as $value) {
                        if ($_SESSION[$this->name][$field['field'] . '_header'] == 'T' && !in_array($value, $_SESSION[$this->name][$field['field'] . '_off'])) {
                            $_SESSION[$this->name][$field['field'] . '_off'][] = $value;
                        }
                    }
                    foreach ($_SESSION[$this->name][$field['field']] as $key => $value) {
                        if (in_array($value, $diffValues)) {
                            if ($_SESSION[$this->name][$field['field'] . '_header'] == 'T' && !in_array($_SESSION[$this->name][$field['field']][$key], $_SESSION[$this->name][$field['field'] . '_off'])) {
                                $_SESSION[$this->name][$field['field'] . '_off'][] = $_SESSION[$this->name][$field['field']][$key];
                            }
                            unset($_SESSION[$this->name][$field['field']][$key]);
                        } else if (in_array($value, $_SESSION[$this->name][$field['field'] . '_off'])) {
                            $tmpKey = array_search($value, $_SESSION[$this->name][$field['field'] . '_off']);
                            unset($_SESSION[$this->name][$field['field'] . '_off'][$tmpKey]);
                        }
                    }
                    if ($_SESSION[$this->name][$field['field'] . '_header'] == 'T' && isset($_SESSION[$this->name][$field['field']])) {
                        unset($_SESSION[$this->name][$field['field']]);
                    } else if ($_SESSION[$this->name][$field['field'] . '_header'] == 'F' && isset($_SESSION[$this->name][$field['field'] . '_off'])) {
                        unset($_SESSION[$this->name][$field['field'] . '_off']);
                    }
                }
            }
        }
    }

    /**
     * Set an array of values into temporary array
     * @since Version 1.1.3
     *
     * @param array $data
     * @param boolean $reset
     */
    public function setCheckboxValues($data, $reset = false) {

        if ($this->headerOutputDone == true)
            echo "Warning table header already generated!";
        $this->checkValuesTmp = $data;
        if (count($this->fields) > 0)
            $this->storeCheckboxValues($reset);
    }

    /**
     * Remove all checkbox values saved in $_SESSION
     * @param array $data
     * @param boolean $reset
     */
    public function unsetCheckboxValues() {
        foreach ($this->fields as $field) {
            if ($field['type'] == 'CHECKBOX') {
                $_SESSION[$this->name][$field['field'] . '_header'] = 'F';
                if (isset($_SESSION[$this->name][$field['field']])) {
                    foreach ($_SESSION[$this->name][$field['field']] as $key => $value) {
                        unset($_SESSION[$this->name][$field['field']][$key]);
                    }
                }
                if (isset($_SESSION[$this->name][$field['field'] . '_off'])) {
                    foreach ($_SESSION[$this->name][$field['field'] . '_off'] as $key => $value) {
                        unset($_SESSION[$this->name][$field['field'] . '_off'][$key]);
                    }
                }
            }
        }
    }

    /**
     * Set an array of values into temporary array
     * @since Version 1.1.5
     *
     * @param string $name
     * @param mixed $value
     * @param boolean $status
     * @param boolean $force
     */
    public function setCheckboxValue($name, $value, $status, $force = false) {
        echo "NOT IMPLEMENTED";
    }

    /**
     * get JavaScript used by SimpleTable
     * @since Version 1.1.3
     * @todo include external javascript files simpletable.js
     *
     * @return string with javascript
     */
    public function getJSHeader() {
        $ret = '';
        $ret .= "\n<!-- simple-table header (main) -->\n\n";
        $ret .= "<script type=\"text/javascript\" language=\"JavaScript\">\n";
        $ret .= "function simpletable_onHeaderCheckboxClick(elem) {\n";
        $ret .= "    if (typeof simpletable_onCustomHeaderCheckboxClick == 'function') {\n";
        $ret .= "        simpletable_onCustomHeaderCheckboxClick();\n";
        $ret .= "    }\n";
        $ret .= "    window['simpletable_'+elem.name.replace('_header', '')+'_off_amount'] = 0;\n";
        $ret .= "    var targetName = elem.getAttribute('name').replace('_header', '');\n";
        $ret .= "    var elems = document.getElementsByTagName('input');\n";
        $ret .= "    if (!elem.checked)\n";
        $ret .= "        elem.className = '';\n";
        $ret .= "    for(var i=0; i<elems.length; i++) {\n";
        $ret .= "        reqType = elems[i].getAttribute('type');\n";
        $ret .= "        if (typeof reqType == 'string' && reqType.toUpperCase() == 'CHECKBOX') {\n";
        $ret .= "            if (elems[i].name == targetName + '[]' && elem.checked) {\n";
        $ret .= "                elems[i].checked = true; \n";
        $ret .= "            } else if (elems[i].name == targetName + '[]' && !elem.checked) {\n";
        $ret .= "                elems[i].checked = false;\n";
        $ret .= "            }\n";
        $ret .= "       }\n";
        $ret .= "    }\n";
        $ret .= "    if (typeof simpletable_afterCustomHeaderCheckboxClick == 'function') {\n";
        $ret .= "        simpletable_afterCustomHeaderCheckboxClick(elem);\n";
        $ret .= "    }\n";
        $ret .= "}\n";
        $ret .= "function simpletable_setHeaderCheckboxStatus(elem) {\n";
        $ret .= "    var targetName = elem.getAttribute('name').replace('[]', '');\n";
        $ret .= "    var elems = document.getElementsByName(targetName + '_header');\n";
        $ret .= "    if (elem.checked) {\n";
        $ret .= "        window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] = window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] - 1;\n";
        $ret .= "    } else {\n";
        $ret .= "        window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] = window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] + 1;\n";
        $ret .= "    }\n";
        // FOR DEBUG USE
        // $ret .= "    alert(window['simpletable_'+elem.name.replace('[]', '')+'_off_amount']);\n";
        $ret .= "    if (elems[0]) {\n";
        $ret .= "        if (elems[0].checked && window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] > 0) {\n";
        $ret .= "            elems[0].className = 'header_checkbox_intermediate';\n";
        $ret .= "        } else if (window['simpletable_'+elem.name.replace('[]', '')+'_off_amount'] <= 0) {\n";
        $ret .= "            elems[0].className = '';\n";
        $ret .= "        }\n";
        $ret .= "    }\n";
        $ret .= "    if (typeof simpletable_afterHeaderCheckboxStatus == 'function') { \n";
        $ret .= "        if (!simpletable_afterHeaderCheckboxStatus(elem));\n";
        $ret .= "          return;\n";
        $ret .= "    }\n";
        $ret .= "}\n";
        $ret .= "function simpletable_getParentForm(elem) {\n";
        $ret .= "    while(elem.tagName != 'FORM') {\n";
        $ret .= "        elem = elem.parentNode;\n";
        $ret .= "    }\n";
        $ret .= "    return elem;\n";
        $ret .= "}\n";
        $ret .= "function simpletable_onOrderChange(elem, url) {\n";
        $ret .= "    if (typeof simpletable_onCustomOrderChange == 'function') { \n";
        $ret .= "        if (!simpletable_onCustomOrderChange(elem, url))\n";
        $ret .= "          return;\n";
        $ret .= "    }\n";
        $ret .= "    var formElem = simpletable_getParentForm(elem);\n";
        $ret .= "    formElem.setAttribute('action', url);\n";
        $ret .= "    formElem.submit();\n";
        $ret .= "}\n";
        $ret .= "function simpletable_onPageChange(table, page, form) {\n";
        $ret .= "    if (typeof simpletable_onCustomPageChange == 'function') { \n";
        $ret .= "        if (!simpletable_onCustomPageChange(table, page, form))\n";
        $ret .= "          return;\n";
        $ret .= "    }\n";
        $ret .= "    if (form.navigation_page_max && Number(page) > Number(form.navigation_page_max.value)) \n";
        $ret .= "        page = Number(form.navigation_page_max.value); \n";
        $ret .= "    form." . $this->get_pg_prefix() . "pg.value=page; \n";
        $ret .= "    form.submit(); \n";
        $ret .= "    if (form.navigation_page_edit)\n";
        $ret .= "        form.navigation_page_edit.disabled = true; \n";
        $ret .= "}\n";
        $ret .= "function simpletable_onNaviEvent(e, input) {\n";
        $ret .= "    var eventType = ''; \n";
        $ret .= "    if (window.document.all) \n";
        $ret .= "        eventType = window.event.type; \n";
        $ret .= "    else \n";
        $ret .= "        eventType = e.type; \n";
        $ret .= "    switch(eventType) { \n";
        $ret .= "      case 'keyup': \n";
        $ret .= "          if ((window.event && window.event.keyCode == 13) || e && e.keyCode == 13)\n";
        $ret .= "              simpletable_onPageChange('" . $this->name . "', input.value, input.form); \n";
        $ret .= "      break; \n";
        $ret .= "    } \n";
        $ret .= "}\n";
        $ret .= "</script>\n";
        $ret .= "\n<!-- end of simple-table header (main) -->\n\n";
        $this->jsOutputDone = true;
        return $ret;
    }

    /**
     * Override function call of javascript event
     */
    public function setJSEvent($eventName, $functionName) {
        if (isset($this->jsEvents[$eventName]))
            $this->jsEvents[$eventName] = $functionName;
    }

    private function get_pg_prefix() {
        if ($this->name != 'simpletable')
            return $this->name;
        return '';
    }

    /**
     * get JavaScript used by SimpleTable (Footer)
     * @since Version 1.1.3
     *
     * @return string with javascript
     */
    public function getJSFooter() {
        $ret = '';
        $ret .= "\n<!-- simple-table footer (main) -->\n\n";
        $ret .= "<script type=\"text/javascript\" language=\"JavaScript\">\n";
        foreach ($this->fields as $field) {
            if ($field['type'] == 'CHECKBOX') {
                $tot = isset($_SESSION[$this->name][$field['field'] . '_off']) ? count($_SESSION[$this->name][$field['field'] . '_off']) : 0;
                $ret .= "var simpletable_" . $field['field'] . "_off_amount = {$tot};\n";
            }
        }
        $ret .= "</script>\n";
        $ret .= "\n<!-- end of simple-table footer (main) -->\n\n";
        return $ret;
    }

    function addField($Label, $Field, $FieldType, $Len = -1, $Width = -1, $Align = '', $Key = false, $Visible = true, $Editable = true, $Mandatory = false, $Sortable = false, $OrderFields = null) {
        $res = array();
        $res['label'] = $Label;
        $res['field'] = $Field;
        $res['type'] = strToUpper($FieldType);
        if ($res['type'] == 'CHECK') {
            $res['type'] = 'CHECKBOX';
        }
        $res['len'] = $Len;
        $res['width'] = $Width;
        $res['align'] = $Align;
        $res['key'] = $Key;
        $res['visible'] = $Visible;
        $res['editable'] = $Editable;
        $res['mandatory'] = $Mandatory;
        $res['sortable'] = $Sortable;
        if ($OrderFields == '')
            $res['order_fields'] = $Field;
        else
            $res['order_fields'] = $OrderFields;
        $res['hint'] = false;
        $res['format'] = false;
        $res['number_format'] = false;
        $res['wrap'] = false;
        $this->fields[$Field] = $res;
        return $res;
    }

    function setFieldParam($name, $param, $value) {

        foreach ($this->fields as $key => $val) {
            if ($val['field'] == $name) {
                $this->fields[$key][$param] = $value;
            }
        }
    }

    // Clear one field (-1 for all)
    function ClearField($count) {
        if ($count < 0)
            unset($this->fields);
        else
            unset($this->fields[$count]);
    }

    // Clear all fields
    function ClearAllFields() {
        $this->ClearField(-1);
    }

    function AddLink($arr = array()) {
        $default = array('kind' => '', 'text' => '', 'url' => '', 'target' => '', 'image' => '', 'prependHTML' => '', 'appendHTML' => '', 'msg' => array());

        return array_merge($default, $arr);
    }

    // Add a new field definition
    function AddLinkCell($text, $url, $target = '', $image = '', $text2 = '') {

        return $this->AddLink(
                        array('kind' => 'link', 'text' => $text, 'url' => $url, 'target' => $target, 'image' => $image, 'text2' => $text2));
    }

    function AddAlert($text, $image, $msg = array()) {
        $txt = $text;
        if (count($msg) > 0) {
            $txt .= '\n - ' . implode('\n - ', $msg);
        }

        $alert = "JavaScript:alert('$txt');";
        return $this->AddLink(array('kind' => 'alert', 'text' => $text, 'url' => $alert, 'image' => $image, 'msg' => $msg));
    }

    /**
     * Add a field value for calculated fields. Name can be the index or the field_name
     *
     * @param mixed $name with index or field name
     * @param string $value to show in column
     * @param string $hint to show as hint. default is empty
     * @param boolean $setHtmlEntities if true call htmlentities for value
     */
    function addCalcValue($name, $value, $hint = '', $setHtmlEntities = array(false, false)) {
        if (!isset($this->calcvalues))
            $this->calcvalues = array();

        if (is_string($name)) {
            $found = false;
            $pos = 0;
            foreach ($this->fields as $field) {
                if ($field['field'] == $name) {
                    $found = true;
                    break;
                }
                $pos++;
            }
        } else {
            $found = true;
            $pos = $name;
        }

        if ($found) {
            if ($setHtmlEntities === true || is_array($setHtmlEntities) && $setHtmlEntities[0] === true) {
                $value = HtmlEntities($value, ENT_QUOTES, $this->charset);
            }
            if ($setHtmlEntities === false || is_array($setHtmlEntities) && $setHtmlEntities[1] === true) {
                $hint = HtmlEntities($hint, ENT_QUOTES, $this->charset);
            }

            $this->calcvalues[$pos]['value'] = $value;
            $this->calcvalues[$pos]['hint'] = $hint;
        }
    }

    function checkImage($value) {
        $this->checkImage = $value;
    }

    // Return the order param and the order mode (asc, desc)
    function ExtractOrderParam($OrderParam, &$Order, &$OrderMode) {
        if ($OrderParam == '') {
            $Order = 1;
            $OrderMode = 'asc';
        } else {
            if (strcasecmp(substr($OrderParam, -1), 'A') == 0) {
                $OrderMode = 'asc';
                $Order = substr($OrderParam, 0, -1);
            } else if (strcasecmp(substr($OrderParam, -1), 'D') == 0) {
                $OrderMode = 'desc';
                $Order = substr($OrderParam, 0, -1);
            }
            if (!is_numeric($Order)) {
                $OrderMode = 'asc';
                $Order = 1;
            }
        }
    }

    // Create the order param
    function CreateOrderParam($Order, $OrderMode = 'asc') {
        if ($OrderMode == 'desc')
            return $Order . 'A';
        else
            return $Order . 'D';
    }

    // Return the field align
    function GetDefaultAlign($FieldType) {
        $FieldType = strtoupper($FieldType);
        if ($FieldType == 'NUMBER' ||
                $FieldType == 'INTEGER' ||
                $FieldType == 'DOUBLE' ||
                $FieldType == 'FLOAT') {
            return "right";
        } else if ($FieldType == 'LINK' ||
                $FieldType == 'CHECKBOX') {
            return "center";
        }

        return "left";
    }

    // Create a link-cell (buttons)
    function CreateLinksCell($links) {

        $res = '';
        //for ($cont = 0; $cont < count($links); $cont++) {
        foreach ($links as $link) {
            //$link = $links[$cont];
            $text = $link['text'];
            $text2 = isset($link['text2']) ? $link['text2'] : '';
            $url = $link['url'];
            $image = $link['image'];
            $prependHTML = $link['prependHTML'];
            $appendHTML = $link['appendHTML'];
            if ($link['target'] != '')
                $t = "target=\"" . $link['target'] . "\"";
            else
                $t = "";
            if ($image != '' && !$this->checkImage) {
                $s = "<img src=\"$image\" border=\"0\" alt=\"$text\" title=\"$text\" />";
            } else if ($image != '' && (!$this->checkImage || is_readable($image))) {
                $size = @GetImageSize($image);
                if ($text == '')
                    $s = "<img src=\"$image\" border=\"0\" " . $size[3] . " alt=\"\" />";
                else
                    $s = "<img src=\"$image\" border=\"0\" " . $size[3] . " alt=\"$text\" title=\"$text\" />";
            } else {
                $s = $text;
            }
            if (strlen($text2) > 0) {
                $s .= "<span class=\"{$this->baseclass}_text2\">{$text2}</span>";
            }
            if ($url == '') {
                $res .= $prependHTML . $s . $appendHTML;
            } else {
                if (strpos($url, 'javascript:') !== false) {
                    $js = substr(strstr($url, ':'), 1);
                    $res .= "<span onclick=\"{$js}\" style=\"cursor:pointer;\">{$prependHTML}{$s}{$appendHTML}</span>";
                } else {
                    $res .= "<a $t href=\"$url\">{$prependHTML}{$s}{$appendHTML}</a>";
                }
            }
        }
        return $res;
    }

    // Create the header of the simple table
    function CreateTableHeader($order = '', $elemID = null) {

        $this->headerOutputDone = true;

        $res = '';
        // Check for javascript output
        if (str_replace('.', '', R3SIMPLETABLE_VERSION_SUPPORT) > 112 && !$this->jsOutputDone) {
            $res .= $this->getJSHeader();
        }

        if ($order == '' && isset($_REQUEST[$this->orderparam])) {
            $order = $_REQUEST[$this->orderparam];
        }
        $this->ExtractOrderParam($order, $OrderBy, $OrderType);
        if ($elemID === null && strlen($this->name) > 0)
            $elemID = $this->name;
        if ($elemID !== null)
            $res .= "<table id=\"$elemID\" class=\"$this->baseclass\" width=\"$this->width\">\n";
        else
            $res .= "<table class=\"$this->baseclass\" width=\"$this->width\">\n";
        // Create col proportion
        foreach ($this->fields as $field) {
            if ($field['width'] < 0)
                $field['width'] = "";
            if ($field['width'] == '') {
                $res .= "<col>\n";
            } else {
                $res .= "<col width=\"" . $field['width'] . "\">\n";
            }
        }
        // Create table header
        $res .= "<tr>\n";
        $cont = 0;
        foreach ($this->fields as $field) {
            if ($field['visible']) {
                $label = $field['label'];
                $width = $field['width'];
                if ($width > 0)
                    $w = "width=\"$width\"";
                else
                    $w = '';

                if ($field['hint'] !== false) {
                    $title = 'title="' . str_replace('"', '&quot;', strip_tags($field['hint'])) . '"';
                } else if ($label != '') {
                    $title = 'title="' . trim(str_replace('&nbsp;', ' ', $label)) . '"';
                } else {
                    $title = '';
                }
                // Checkbox for header
                if (isset($field['header_checkbox']) && $field['header_checkbox'] == true) {
                    if (!isset($_SESSION[$this->name][$field['field'] . '_off']))
                        $_SESSION[$this->name][$field['field'] . '_off'] = array();

                    if (is_array($_SESSION[$this->name][$field['field'] . '_off']) && count($_SESSION[$this->name][$field['field'] . '_off']) > 0)
                        $className = "class=\"header_checkbox_intermediate\"";
                    else
                        $className = "";
                    if (isset($_SESSION[$this->name][$field['field'] . '_header']) && $_SESSION[$this->name][$field['field'] . '_header'] == 'T')
                        $label = "<input type=\"checkbox\" name=\"" . $field['field'] . "_header\" value=\"T\" onclick=\"simpletable_onHeaderCheckboxClick(this)\" $className checked>" . $label;
                    else
                        $label = "<input type=\"checkbox\" name=\"" . $field['field'] . "_header\" value=\"T\" onclick=\"simpletable_onHeaderCheckboxClick(this)\" $className>" . $label;
                    unset($field['sortable']);
                }
                if (isset($field['sortable']) && $field['sortable']) {
                    $class = $this->baseclass . "_header_sort";
                    //if ($_REQUEST[$this->orderparam] == ($cont + 1) && $OrderType == 'asc')
                    if ($order == ($cont + 1) && $OrderType == 'asc')
                        $url = AddURLParam($this->baseurl, $this->orderparam, $this->CreateOrderParam($cont + 1));
                    else
                        $url = AddURLParam($this->baseurl, $this->orderparam, $this->CreateOrderParam($cont + 1, 'desc'));
//  echo $url;
                    // TODO: find solution for label as text
                    if ($order == ($cont + 1)) {
                        if ($OrderType == 'asc')
                            $label = "$label<img class=\"ui-icon ui-icon-triangle-1-n\" src=\"" . R3_APP_URL . "images/ico_spacer.gif\" />";
                        else
                            $label = "$label<img class=\"ui-icon ui-icon-triangle-1-s\" src=\"" . R3_APP_URL . "images/ico_spacer.gif\" />";
                    }

                    if (str_replace('.', '', R3SIMPLETABLE_VERSION_SUPPORT) > 112)
                        $res .="  <th class=\"$class\" $w onclick=\"" . $this->jsEvents['onChangeOrder'] . "(this, '$url'); \" $title>$label</th>\n";
                    else
                        $res .="  <th class=\"$class\" $w onclick=\"document.location='$url'\" $title>$label</th>\n";
                } else {
                    $class = $this->baseclass . "_header";
                    $res .="  <th class=\"$class\" $w $title>$label</th>\n";
                }
            }
            $cont++;
        }
        $res .= "</tr>\n";
        return $res;
    }

    /**
     * Create Table Footer String
     */
    function MkTableFooter() {
        if (str_replace('.', '', R3SIMPLETABLE_VERSION_SUPPORT) > 112)
            return "</table>\n" . $this->getJSFooter();
        else
            return "</table>\n";
    }

    // Create a simple table row (data)
    // TODO: Force $links, $styles, $events as array
    public function CreateTableRow($row, $links = null, $styles = null, $events = null, $id = null) {

        // Replace Standart Styles
        $class_normal = $this->baseclass . '_normal';
        $class_over = $this->baseclass . '_over';
        $class_td = $this->baseclass . '_td';
        if (count($styles) > 0) {
            if (array_key_exists('normal', $styles))
                if (is_array($styles['normal']))
                    $class_normal = implode(' ', $styles['normal']);
                else
                    $class_normal = $styles['normal'];
            if (array_key_exists('over', $styles))
                $class_over = $styles['over'];
            if (array_key_exists('td', $styles))
                $class_td = $styles['td'];
        }

        // Get Additional Events
        $AddEvents = '';
        if ($events !== null && count($events) > 0) {
            while (list($event, $action) = each($events)) {
                if (strtolower($event) != 'onmouseover' && strtolower($event) != 'onmouseout')
                    $AddEvents = " $event=\"$action;\" ";
            }
        }
        if ($id !== null)
            $id = "id=\"{$id}\" ";
        $res = "<tr {$id}class=\"$class_normal\" onmouseover=\"this.className='$class_over';\" onmouseout=\"this.className='$class_normal';\" $AddEvents>\n";
        $cont = 0;
        foreach ($this->fields as $field) {
            $hint = '';
            if ($field['visible']) {
                $type = strtoupper($field['type']);
                if ($type == 'LINK') {
                    $value = $this->CreateLinksCell($links);
                    $res .= "  <td class=\"$class_td\" align=\"center\">$value</td>\n";
                } else {
                    if ($field['align'] == '')
                        $align = $this->GetDefaultAlign($field['type']);
                    else
                        $align = strtolower($field['align']);
                    if ($field['type'] != 'CALCULATED') {
//            if (is_array($row))        $value = $row[$field['field']];
//            else if (is_object($row))  $value = $row->getField($field['field']);
                        if (is_array($row))
                            $value = $row[$field['field']];
                        else
                            $value = $row->getField($field['field']);

                        // PH: do not escape anything different from a string
                        // TODO: check if a not empty string is returned as empty string. Give a warning mentioning the charset in that case
                        if ($this->SetHtmlEntities && is_string($value))
                            $value = HtmlEntities($value, ENT_QUOTES, $this->charset);

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
                                $value = SQLDateToStr($value, $format === false ? 'H:i:s' : $format);
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
                        } else if ($field['type'] == 'EURO') {
                            // DEPRECATED!
                            $value = valueToEuro($value);
                        } else if ($field['type'] == 'CHECKBOX') {

                            if (!isset($_SESSION[$this->name][$field['field']]))
                                $_SESSION[$this->name][$field['field']] = array();
                            if (!isset($_SESSION[$this->name][$field['field'] . '_off']))
                                $_SESSION[$this->name][$field['field'] . '_off'] = array();
                            if (in_array($value, $_SESSION[$this->name][$field['field']]) || ($_SESSION[$this->name][$field['field'] . '_header'] == 'T' && !in_array($value, $_SESSION[$this->name][$field['field'] . '_off'])))
                                $checked = 'checked';
                            else
                                $checked = '';
                            // if (1==1)  $checked = 'checked';
                            // else       $checked = '';
                            // $value = "<input type=\"checkbox\" name=\"" . $field['field'] . "[]\" id=\"" . $field['field'] . "_$value\" value=\"$value\" $checked onclick=\"simpletable_onCheckboxClick(this, '" . $field['field'] . "', '" . $value . "')\">";
                            $tmp_value = '';

                            // - checkbox
                            $tmp_value .= "<input type=\"checkbox\" name=\"" . $field['field'] . "[]\" id=\"" . $field['field'] . "_$value\" value=\"$value\" onClick=\"simpletable_setHeaderCheckboxStatus(this);\" $checked>";

                            // - hidden var (to store/remove old status)
                            $tmp_value .= "<input type=\"hidden\" name=\"" . $field['field'] . "_hd[]\" id=\"" . $field['field'] . "_hd_$value\" value=\"$value\">";

                            $value = $tmp_value;

                            //if (isset($field['header_checkbox']) && $field['header_checkbox'] == true) {
                            //$label = "<input type=\"checkbox\" name=\"" . $field['field'] . "_header\" value=\"T\" onclick='simpletable_onHeaderCheckboxClick()'>" . $label;
                            //unset($field['sortable']);
                            //}
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
                    } else {  // Calculated fields
                        if (isset($this->calcvalues[$cont]['value']))
                            $value = $this->calcvalues[$cont]['value'];
                        else
                            $value = '';
                        if (isset($this->calcvalues[$cont]['hint']))
                            $hint = 'title="' . htmlspecialchars($this->calcvalues[$cont]['hint']) . '"';
                    }

                    // Replace spaces from value with &nbsp;
                    if ($field['type'] != 'CALCULATED' && $field['type'] != 'CHECKBOX' && strpos($field['type'], 'URL') === false && $field['wrap'] != true) {
                        $value = str_replace(" ", "&nbsp;", $value);
                    }

                    // add new line
                    if ($field['wrap'])
                        $value = nl2br($value);

                    $res .= "  <td class=\"$class_td\" align=\"$align\" $hint>$value</td>\n";
                }
            }
            $cont++;
        }
        $res .= "</tr>\n";

        // Clear calculated array
        unset($this->calcvalues);

        return $res;
    }

    /**
     * Append HTML as new tr
     * @since 1.1.8
     *
     * @param string $html
     * @param array $styles to override standard styles
     */
    public function appendCustomHTMLRow($html, $styles = null) {
        $class_normal = $this->baseclass . '_normal';
        $class_over = $this->baseclass . '_over';
        $class_td = $this->baseclass . '_td';
        if (count($styles) > 0) {
            if (array_key_exists('normal', $styles))
                $class_normal = $styles['normal'];
            if (array_key_exists('over', $styles))
                $class_over = $styles['over'];
            if (array_key_exists('td', $styles))
                $class_td = $styles['td'];
        }

        $colspan = count($this->fields);
        return "<tr class=\"$class_normal\" onmouseover=\"this.className='$class_over';\" onmouseout=\"this.className='$class_normal';\">\n" .
                "  <td class=\"$class_td\" colspan=\"$colspan\">" . $html . "</td>\n" .
                "</tr>";
    }

    // Return the order tag for SQL command
    function CreateSQLOrder($order = '') {

        return $this->getSQLOrder($order);

        // if ($order=='')  $order = $_REQUEST[$this->orderparam];
        // $this->ExtractOrderParam($order, $OrderBy, $OrderType);
        // $order = $this->fields[$OrderBy - 1]['field'];
        // return "order by $order $OrderType";
    }

    function getSQLOrder($order = '') {
        $res = '';

        if ($order == '' && isset($_REQUEST[$this->orderparam])) {
            $order = $_REQUEST[$this->orderparam];
        }

        $this->ExtractOrderParam($order, $OrderBy, $OrderType);
        //echo "[$order, $OrderBy, $OrderType]";

        $fieldKeys = array_keys($this->fields);
        $fieldName = $fieldKeys[$OrderBy - 1];

        $order = $this->fields[$fieldName]['field'];

        // support for multiple sort

        if ($this->fields[$fieldName]['order_fields'] != '' &&
                $this->fields[$fieldName]['order_fields'] != $this->fields[$fieldName]['field']) {
            $res = '';
            $in_array = explode(',', $this->fields[$fieldName]['order_fields']);
            $out_array = array();
            foreach ($in_array as $field) {
                $field = trim($field);

                if (strtolower(substr($field, -4)) == ' asc' ||
                        strtolower(substr($field, -5)) == ' desc') {
                    $out_array[] = $field;
                } else {
                    $out_array[] = $field . ' ' . $OrderType;
                }
            }
            return ' ' . implode(', ', $out_array);
        }

        // old single sort
        // More then 1 field
        $orderArr = explode(',', $order);
        //order_fields
        if (count($orderArr) > 0) {
            $resArr = array();
            foreach ($orderArr as $key => $val) {
                $resArr[] = trim($val) . " $OrderType";
            }
            $res = implode(', ', $resArr);
        } else
            $res = $order . " $OrderType";

        return " $res";
    }

    /** Return the limit for SQL command (MySQL)
     * @deprecated
     *
     */
    function CreateSQLLimit($start, $max) {
        if ($start == '')
            $start = $_REQUEST[$this->startparam];
        //if ($start > 0)  return "limit " . $start . ", $max";
        //else             return "limit 0, $max";
        if ($start > 0)
            return "LIMIT {$max} OFFSET {$start}";
        else
            return "LIMIT {$max} OFFSET 0";
    }

    /**
     * get HTML Navigation Bar
     * @since Version 1.1.3
     *
     * @param integer $page
     * @param integer $totRecord
     * @param integer $maxRecordPerPage
     * @param boolean $showAlways
     * @return string with navigation bar
     */
    public function mkNavigationBar($page, $totRecord, $maxRecordPerPage = 0, $showAlways = false) {
        global $lbl;

        /* Numero massimo di link alle pagine (pi� avanti/indietro) */
        $maxPages = 9;

//        $page = 3;
//        $totRecord = 1300;
//        $maxRecordPerPage = 25;

        if ($maxRecordPerPage <= 0) {
            return '';
        }
        $minPage = 1;
        $maxPage = ceil($totRecord / $maxRecordPerPage);
        $page = min(max($minPage, $page), $maxPage);

        $navigationBar_html = "<input type=\"hidden\" name=\"" . $this->get_pg_prefix() . "pg\" id=\"" . $this->get_pg_prefix() . "pg\" value=\"$page\">\n";
        $navigationBar_html .= "<table class=\"navigation\" align=\"center\">\n";
        $navigationBar_html .= "  <tr>\n";
        $navigationBar_html .= '    %s';
        $navigationBar_html .= "  </tr>\n";
        $navigationBar_html .= "</table>\n";

        $ret = "";
        if ($totRecord < 1) {
            if (isset($lbl["navi_no_records"]))
                $ret = "<span class=\"navigation_not_found\">" . $lbl["navi_no_records"] . "</span>";
            else
                $ret = "<span class=\"navigation_not_found\">" . _('Nessun dato trovato') . "</span>";;
//            $ret .= "<td>" . isset($lbl["navi_no_records"]) ? $lbl["navi_no_records"] : '' . "</td>\n";
            return $ret;
        }
        if ($page > $minPage) {
            $pg = $page - 1;
            // $ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $pg, this.form)\">" . sprintf($lbl["navi_prev"], $maxRecordPerPage) . "</td>";

            if (isset($lbl["navi_prev"]))
                $ret .= "<td><input type=\"text\" value=\"&lt;&lt; " . sprintf($lbl["navi_prev"], $maxRecordPerPage) . "\" onclick=\"" . $this->jsEvents['onChangePage'] . "('$this->name', $pg, this.form)\" readonly tabindex=\"999999\" class=\"navigation_buttons\" onmouseover=\"this.className='navigation_buttons navigation_over';\" onmouseout=\"this.className='navigation_buttons';\"></td>\n";
            else
                $ret .= "<td><input type=\"text\" value=\"&lt;&lt; " . _('Indietro') . "\" onclick=\"" . $this->jsEvents['onChangePage'] . "('$this->name', $pg, this.form)\" readonly tabindex=\"999999\" class=\"navigation_buttons\" onmouseover=\"this.className='navigation_buttons navigation_over';\" onmouseout=\"this.className='navigation_buttons';\"></td>\n";
        } else {
            $pg = $page - 1;
            if (isset($lbl["navi_prev"]))
                $ret .= "<td><input type=\"text\" value=\"&lt;&lt; " . sprintf($lbl["navi_prev"], $maxRecordPerPage) . "\" class=\"navigation_buttons navigation_disabled\" readonly tabindex=\"999999\"></td>\n";
            else
                $ret .= "<td><input type=\"text\" value=\"&lt;&lt; " . _('Indietro') . "\" class=\"navigation_buttons navigation_disabled\" readonly tabindex=\"999999\"></td>\n";
        }

        $start = max($minPage, min($page - floor($maxPages / 2), $maxPage - $maxPages + 1));

        for ($pg = $start; $pg < $start + min($maxPages, $maxPage); $pg++) {
            if ($pg == $page) {
                $ret .= "<td><input value=\"$pg\" class=\"navigation_on\" readonly tabindex=\"999999\" /></td>\n";
            } else {
                $ret .= "<td><input type=\"text\" value=\"$pg\" onclick=\"" . $this->jsEvents['onChangePage'] . "('$this->name', $pg, this.form)\" readonly tabindex=\"999999\" class=\"navigation_off\" onmouseover=\"this.className='navigation_over';\" onmouseout=\"this.className='navigation_off';\" /></td>\n";
            }
        }

        if ($page < $maxPage) {
            $pg = $page + 1;
            // $ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $pg)\">" . sprintf($lbl["navi_next"], $maxRecordPerPage) . "</td>";
            if (isset($lbl["navi_next"]))
                $ret .= "<td><input type=\"text\" value=\"" . sprintf($lbl["navi_next"], $maxRecordPerPage) . " &gt;&gt;\" onclick=\"" . $this->jsEvents['onChangePage'] . "('$this->name', $pg, this.form)\" readonly tabindex=\"999999\" class=\"navigation_buttons\" onmouseover=\"this.className='navigation_buttons navigation_over';\" onmouseout=\"this.className='navigation_buttons';\"></td>\n";
            else
                $ret .= "<td><input type=\"text\" value=\"" . _('Avanti') . " &gt;&gt;\" onclick=\"" . $this->jsEvents['onChangePage'] . "('$this->name', $pg, this.form)\" readonly tabindex=\"999999\" class=\"navigation_buttons\" onmouseover=\"this.className='navigation_buttons navigation_over';\" onmouseout=\"this.className='navigation_buttons';\"></td>\n";
        } else {
            $pg = $page + 1;
            if (isset($lbl["navi_next"]))
                $ret .= "<td><input type=\"text\" value=\"" . sprintf($lbl["navi_next"], $maxRecordPerPage) . " &gt;&gt;\" class=\"navigation_buttons navigation_disabled\" readonly tabindex=\"999999\"></td>\n";
            else
                $ret .= "<td><input type=\"text\" value=\"" . _('Avanti') . " &gt;&gt;\" class=\"navigation_buttons navigation_disabled\" readonly tabindex=\"999999\"></td>\n";
        }

        if ($maxPage > $maxPages) {
            // simpletable_onNaviEvent
            // $ret .= "<td><input type=\"text\" name=\"navigation_page_edit\" id=\"navigation_page_edit\" onKeyUp=\"if ((window.event && window.event.keyCode == 13) || event && event.keyCode == 13) ".$this->jsEvents['onChangePage']."('$this->name', this.value)\" value=\"$page\">/<input type=\"text\" name=\"navigation_page_edit\" id=\"navigation_page_edit\" value=\"$maxPage\" readonly></td>";
            if (isset($lbl['navi_page'])) {
                $ret .= "<td class=\"navigation_search\">
			          " . $lbl['navi_page'] . "
			           <input type=\"text\" name=\"navigation_page_edit\" id=\"navigation_page_edit\" onKeyUp=\"" . $this->jsEvents['onChangeNavi'] . "(event, this);\" value=\"$page\">
			           / $maxPage
					   <input type=\"hidden\" name=\"navigation_page_max\" id=\"navigation_page_max\" value=\"$maxPage\" />
					 </td>\n";
            } else {
                $ret .= "<td class=\"navigation_search\">
			          " . _('Pagina') . "
			           <input type=\"text\" name=\"navigation_page_edit\" id=\"navigation_page_edit\" onKeyUp=\"" . $this->jsEvents['onChangeNavi'] . "(event, this);\" value=\"$page\">
			           / $maxPage
					   <input type=\"hidden\" name=\"navigation_page_max\" id=\"navigation_page_max\" value=\"$maxPage\" />
					 </td>\n";
            }
        }


        // } else {
        // $sl = $act_page-floor($max_pages/2);
        // if($sl < 1) $sl = 1;
        // $el = $sl+$max_pages-1;
        // if($el>$pages) {
        // $el = $pages;
        // $sl = $el-$max_pages+1;
        // }
        // if($sl > 1) {
        // $ref = $prog."?st=0&".$param;
        //$ret .= "<a href=\"$ref\" class=\"nav\">1</a> ... ";
        //$ret .= "<span class=\"nav\">1</span> ... ";
        // }
        // for($i=$sl; $i <= $el; $i++) {
        // if($i == $act_page) {
        // $ret .= "<span class=\"navsel\">[".$i."]</span>".$separator;
        // $ret .= "<td class=\"navigation_on\">[$i]</td>";
        // $ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $i)\">[$i]</td>";
        // } else {
        // $np = ($i-1)*$max;
        // $ref = $prog."?st=".$np."&".$param;
        // $ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $i)\">$i</td>";
        // }
        // }
        // if($el < $pages) {
        // $np = ($pages-1)*$max;
        // $ref = $prog."?st=".$np."&".$param;
        // $ret .= " ... <a href=\"$ref\" class=\"nav\">$pages</a>";
        // $ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $np)\">aaaaa" . sprintf($lbl["navi_next"], $max) . "</td>";
        // }
        // }
        // if($act_page < $pages) {
        // $np = ($act_page)*$max;
        // $ref = $prog."?st=".$np."&".$param;
        //$ret .= $separator.$separator.$separator."<a href=\"$ref\" class=\"nav\">".$lbl["navi_next"]." $max &gt;</a>";
        //$ret .= "<td onclick=\"".$this->jsEvents['onChangePage']."('$this->name', $np)\">" . sprintf($lbl["navi_next"], $max) . "</td>";
        // }
        if ($showAlways !== true && $minPage == $maxPage)
            return "";
        else
            return sprintf($navigationBar_html, $ret);
    }

}

//DEPRECATED (use pSimpleTable->mkNavigationBar!!!!
function createNavigationBar($start_rec, $num_rec, $max, $prog, $param = '') {
    global $lbl;

    $max_pages = 10;
    $separator = "&nbsp;&nbsp;\n";
    $ret = "";

    if ($num_rec < 1) {
        $ret .= "<span class=\"nav\">" . $lbl["navi_no_records"] . "</span>\n";
        return $ret;
    }

    $start_rec++;
    $pages = ceil($num_rec / $max);
    $act_page = ceil($start_rec / $max);

    if ($act_page > 1) {
        $np = ($act_page - 2) * $max;
        $ref = $prog . "?st=" . $np . "&" . $param;
        $ret .= "<a href=\"$ref\" class=\"nav\">&lt; " . $lbl["navi_prev"] . " $max</a>" . $separator . $separator . $separator;
    }

    if ($pages <= $max_pages) {
        for ($i = 1; $i <= $pages; $i++) {
            if ($i == $act_page) {
                $ret .= "<span class=\"navsel\">[" . $i . "]</span>" . $separator;
            } else {
                $np = ($i - 1) * $max;
                $ref = $prog . "?st=" . $np . "&" . $param;
                $ret .= "<a href=\"$ref\" class=\"nav\">$i</a>" . $separator;
            }
        }
    } else {
        $sl = $act_page - floor($max_pages / 2);
        if ($sl < 1)
            $sl = 1;
        $el = $sl + $max_pages - 1;
        if ($el > $pages) {
            $el = $pages;
            $sl = $el - $max_pages + 1;
        }

        if ($sl > 1) {
            $ref = $prog . "?st=0&" . $param;
            $ret .= "<a href=\"$ref\" class=\"nav\">1</a> ... ";
        }

        for ($i = $sl; $i <= $el; $i++) {
            if ($i == $act_page) {
                $ret .= "<span class=\"navsel\">[" . $i . "]</span>" . $separator;
            } else {
                $np = ($i - 1) * $max;
                $ref = $prog . "?st=" . $np . "&" . $param;
                $ret .= "<a href=\"$ref\" class=\"nav\">$i</a>" . $separator;
            }
        }

        if ($el < $pages) {
            $np = ($pages - 1) * $max;
            $ref = $prog . "?st=" . $np . "&" . $param;
            $ret .= " ... <a href=\"$ref\" class=\"nav\">$pages</a>";
        }
    }

    if ($act_page < $pages) {
        $np = ($act_page) * $max;
        $ref = $prog . "?st=" . $np . "&" . $param;
        $ret .= $separator . $separator . $separator . "<a href=\"$ref\" class=\"nav\">" . $lbl["navi_next"] . " $max &gt;</a>";
    }

    return $ret;
}
