<?php

// insert here JS/CSS library files, which must be included for the UM.

class R3UmDependenciesDefault {

    protected $jsDefaultVars;
    protected $jsObjectVars = array();
    protected $jsDefaultFiles;
    protected $jsObjectFiles = array();
    protected $cssDefaultFiles;
    protected $cssObjectFiles = array();

    public function __construct() {
        global $lang;

        // Need this to avoid problems with the application (while lang is global)
        $objlang = $lang;

        if (empty($objlang))
            $objlang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'it';
        if ($objlang == 1)
            $objlang = 'it';
        if ($objlang == 2)
            $objlang = 'de';

        $auth = R3AuthInstance::get();

        $this->jsDefaultVars = array('js_lang' => $objlang);

        if (!defined('R3_UM_JQUERY') || !R3_UM_JQUERY) {
            $this->jsDefaultFiles = array(R3_JS_URL . "simplecalendar.js");
        } else {
            $this->jsDefaultFiles = array(R3_JS_URL . "jquery/jquery.js",
                R3_JS_URL . "jquery/plugins/jquery.cookie.js",
                R3_JS_URL . "jquery/ui/ui.core.js",
                R3_JS_URL . "jquery/ui/ui.tabs.js",
                R3_JS_URL . "jquery/ui/ui.datepicker.js",
                R3_JS_URL . "jquery/ui/i18n/ui.datepicker-{$objlang}.js",
                R3_JS_URL . "r3um.js");
        }
        //$this->jsDefaultFiles[] = R3_JS_URL . "charset.js";
        $this->jsDefaultFiles[] = R3_JS_URL . "xajax_required_tag.js";
        $this->jsDefaultFiles[] = R3_JS_URL . "ajax_select.js";

        $this->cssDefaultFiles = array(R3_CSS_URL . "default.css",
            R3_CSS_URL . "user_manager.css",
            R3_CSS_URL . "simpletable.css");
        if (!defined('R3_UM_JQUERY') || !R3_UM_JQUERY) {
            $this->cssDefaultFiles[] = R3_CSS_URL . "calendar.css";
        } else {
            if ($auth->getConfigValue('SETTINGS', 'THEMA', '') != '') {
                $this->cssDefaultFiles[] = R3_CSS_URL . $auth->getConfigValue('SETTINGS', 'THEMA', '') . ".css";
                $this->cssDefaultFiles[] = R3_JS_URL . "jquery/themes/r3gis/" . $auth->getConfigValue('SETTINGS', 'THEMA', '') . "/r3gis.css";
            } else {
                $this->cssDefaultFiles[] = R3_JS_URL . "jquery/themes/r3gis/r3gis.css";
            }
        }
    }

    public function get() {
        $ret = array();
        $ret['css'] = $this->cssDefaultFiles;
        $ret['js'] = $this->jsDefaultFiles;
        $ret['js_vars'] = $this->jsDefaultVars;
        return $ret;
    }

}
