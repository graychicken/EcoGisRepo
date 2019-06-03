<?php

if (defined("__SIMPLEGRID_PHP__"))
    return;
define("__SIMPLEGRID_PHP__", 1);

define('R3SIMPLEGRID_VERSION_SUPPORT', '1.0.0');

class simpleGrid {

    protected $fields = array();          // Field definition list
    protected $width;                     // Table width (pixel or %)
    protected $baseurl;                   // Base URL for ajax request
    protected $name = 'simplegrid';       // Nome della simple table
    protected $calcvalues = array();      // Calculated values
    protected $order = null;              // The table order
    protected $options = array();         // Table options

    /**
     * Constructor
     * @param mixed $width       Table width (in pixel or 100%)
     * @param mixed $baseclass   NOT USED
     * @param string $baseurl    The base url
     */

    public function __construct($width = 500, $baseclass = 'grid', $baseurl = '') {
        if ($baseurl == '')
            $this->baseurl = basename($_SERVER['PHP_SELF']);
        else
            $this->baseurl = $baseurl;
        $this->options = array('min_width' => 400, // Minimum table width
            'min_height' => 100, // Minimum table height
            'viewrecords' => false,
            'caption' => '',
            'hidegrid' => false,
            'hoverrows' => true,
            'sortable' => false,
            'width' => $width,
            'height' => '100%');
    }

    /**
     * Return the module version
     *
     * @return string with the version number
     * @access public
     */
    public function getVersionString() {
        return R3SIMPLEGRID_VERSION_SUPPORT;
    }

    /**
     * Return the table options
     *
     * @return array        the options
     * @access public
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * Set the table options
     *
     * @param array $options   the options
     * @access public
     */
    public function setOptions($options) {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * add a new field definition (this function will replace "addField")
     * @since Version 1.1.0
     *
     * @param string $label
     * @param string $field_name
     * @param string $field_type
     * @param mixed $width
     * @param array $extraData
     * @return array with field definition
     */
    public function addSimpleField($label, $field_name, $field_type, $width = null, array $extraData = array()) {

        $data = array();
        $data['label'] = $label;
        $data['field'] = $field_name;
        $data['type'] = strToUpper($field_type);
        $data['width'] = $width;
        if (isset($extraData['align']))
            $data['align'] = $extraData['align'];
        else
            $data['align'] = $this->getDefaultAlign($field_type);
        if (isset($extraData['sortable']))
            $data['sortable'] = $extraData['sortable'];
        else
            $data['sortable'] = false;
        if (isset($extraData['order_fields']))
            $data['order_fields'] = $extraData['order_fields'];
        else
            $data['order_fields'] = $field_name;
        if (isset($extraData['format']))
            $data['format'] = $extraData['format'];
        else
            $data['format'] = false;
        if (isset($extraData['number_format']))
            $data['number_format'] = $extraData['number_format'];
        else
            $data['number_format'] = false;

        $this->fields[] = $data;
        return $data;
    }

    /**
     * return the fields array
     */
    public function getFields() {
        return $this->fields;
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

    function AddLink($arr = array()) {
        $default = array('kind' => '', 'text' => '', 'url' => '', 'target' => '', 'image' => '', 'msg' => array());
        return array_merge($default, $arr);
    }

    // Add a new field definition
    function AddLinkCell($Text, $url, $target = '', $image = '') {
        return $this->AddLink(array('kind' => 'link', 'text' => $Text, 'url' => $url, 'target' => $target, 'image' => $image));
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
        $this->calcvalues[$name]['value'] = $value;
    }

    /**
     * Add a field value for calculated fields. Name can be the index or the field_name
     *
     * @param mixed $name with index or field name
     * @param string $value to show in column
     * @param string $hint to show as hint. default is empty
     * @param boolean $setHtmlEntities if true call htmlentities for value
     */
    function getCalcValue($name) {
        if (isset($this->calcvalues[$name]['value']))
            return $this->calcvalues[$name]['value'];
        return '';
    }

    function checkImage($value) {
        
    }

    // Return the field align
    function getDefaultAlign($FieldType) {
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
            $url = $link['url'];
            $image = $link['image'];
            if ($link['target'] != '')
                $t = "target=\"" . $link['target'] . "\"";
            else
                $t = "";
            $s = "<img src=\"$image\" border=\"0\" alt=\"$text\" title=\"$text\" />";
            if ($url == '') {
                $res .= $s;
            } else {
                $res .= "<a href=\"$url\" $t title=\"$text\">$s</a>";
            }
        }
        return $res;
    }

    // Create the header of the simple table
    function createTableHeader($order = '') {
        if ($order == '')
            return;
        if (strpos($order, '|') === false) {
            // Ricavo elenco completo campi ordinamento
            $order = $this->fields[substr($order, 0, -1) - 1]['field'] . '|' .
                    (substr($order, -1, 1) == 'D' ? 'desc' : 'asc');
        }
        list($this->order) = explode('|', $order);
    }

    /**
     * Create Table Footer String
     */
    function mkTableFooter(array $opt = array()) {

        $opt = $this->options;
        $result = "<div id=\"{$this->name}_container\">\n";
        $result .= "<table id=\"{$this->name}\"></table>\n";
        $result .= "<div id=\"{$this->name}_pager\"></div>\n";
        $result .= "</div>\n";

        $colNames = array();
        foreach ($this->fields as $data) {
            $colNames[] = $data['label'];
        }
        $colNames = json_encode($colNames);
        $colModel = array();
        foreach ($this->fields as $data) {
            //$colNames[] = $data['label'];
            $model = array();
            $model['name'] = $data['field'];
            if ($data['align'] != null && $data['align'] != 'left') {
                $model['align'] = $data['align'];
            }
            if ($data['width'] != null) {
                $model['width'] = $data['width'];
                $model['fixed'] = true;
            } else {
                $model['resizable'] = true;
            }
            $model['sortable'] = $data['sortable'];

            $colModel[] = $model;
        }
        $colModel = json_encode($colModel);

        $result .= "
<script type=\"text/javascript\">
function setGridWidth(id, width, minWidth) {
    var isPercent = width.charAt(width.length - 1) == '%';
    if (isPercent) {
        width = parseFloat(width.substring(0, width.length - 1));
        var parentWidth = $('#' + id + '_container').parent().width();
        width = Math.round(parentWidth / 100 * width);
    } else {
        width = parseFloat(width);
    }
    if (width < 0) {
        var parentWidth = $('#' + id + '_container').parent().width();
        width = Math.round(parentWidth + width);
    }
    if (typeof minWidth != 'undefiend')
        width = Math.max(minWidth, width);
    $('#' + id).setGridWidth(width);
}

function setGridHeight(id, height, minHeight) {
    var isPercent = height.charAt(height.length - 1) == '%';
    if (isPercent) {
        height = parseFloat(height.substring(0, height.length - 1));
        var parentHeight = $(window).height() - $('#' + id).offset().top - $('#' + id + '_pager').height() - 20;
        height = Math.round(parentHeight / 100 * height);
    } else {
        height = parseFloat(height);
    }
    if (height < 0) {
        var parentHeight = $(window).height() - $('#' + id).offset().top - $('#' + id + '_pager').height() - 20;
        height = Math.round(parentHeight + height);
    }
    if (typeof minHeight != 'undefiend')
        height = Math.max(minHeight, height);
    $('#' + id).setGridHeight(height);
}

jQuery(document).ready(function(){
    jQuery(\"#{$this->name}\").jqGrid({
        url: '{$this->baseurl}',
        datatype: 'json',
        mtype: 'GET',
        pager: '#{$this->name}_pager',
        colNames: {$colNames},
        colModel: {$colModel},
        rowNum: -1,
        sortname: '{$this->order}',
        viewrecords: " . ($opt['viewrecords'] ? 'true' : 'false') . ",
        caption: '" . htmlspecialchars($opt['caption'], ENT_QUOTES) . "',
        hidegrid: " . ($opt['hidegrid'] ? 'true' : 'false') . ",
        hoverrows: " . ($opt['hoverrows'] ? 'true' : 'false') . ",
        sortable: " . ($opt['sortable'] ? 'true' : 'false') . ",
        prmNames: {page: 'pg'},
        loadComplete:
            function(xhr) {
                setGridWidth('{$this->name}', '{$opt['width']}', {$opt['min_width']});
            }
    });
    setGridWidth('{$this->name}', '{$opt['width']}', {$opt['min_width']});
    setGridHeight('{$this->name}', '{$opt['height']}', {$opt['min_height']});
});

$(window).resize(function() {
    setGridWidth('{$this->name}', '{$opt['width']}', {$opt['min_width']});
    setGridHeight('{$this->name}', '{$opt['height']}', {$opt['min_height']});
});
";
        $result .= "</script>";
        return $result;
    }

    public function createTableRow($row, $links = null, $styles = null, $events = null) {
        
    }

    function CreateSQLOrder($order = '') {
        
    }

    function getSQLOrder($order) {
        if (strpos($order, '|') === false) {
            // Ricavo elenco completo campi ordinamento
            $order = $this->fields[substr($order, 0, -1) - 1]['field'] . '|' .
                    (substr($order, -1, 1) == 'D' ? 'desc' : 'asc');
        }
        list($orderField, $orderType) = explode('|', $order);
        $orderType = $orderType == 'desc' ? 'desc' : 'asc';
        foreach ($this->fields as $field) {
            if ($field['field'] == $orderField && $field['sortable']) {
                $result = array();
                $orderFields = explode(',', $field['order_fields']);
                foreach ($orderFields as $f) {
                    $f = trim($f);
                    if (strtolower(substr($f, -4)) == ' asc' ||
                            strtolower(substr($f, -5)) == ' desc') {
                        // Fixed order
                        $result[] = $field;
                    } else {
                        $result[] = $f . ' ' . $orderType;
                    }
                }
                return ' ' . implode(', ', $result);
            }
        }
        return null;
    }

    public function mkNavigationBar($page, $totRecord, $maxRecordPerPage = 0, $showAlways = false) {
        
    }

}
