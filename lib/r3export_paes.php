<?php

define('R3_PAES_PREPARE_DATA', 10);
define('R3_PAES_READ_TEMPLATE', 20);
define('R3_PAES_READ_CONFIG', 30);
define('R3_PAES_REPLACE', 40);
define('R3_PAES_EMISSION_TABLE', 50);
define('R3_PAES_ACTION_PLAN_TABLE', 60);
define('R3_PAES_FINALYZE', 70);
define('R3_PAES_SAVE', 80);
define('R3_PAES_DONE', 90);

abstract class R3ExportPAESDriver {

    protected $auth;
    protected $opt;

    public function __construct($auth, $opt) {
        $this->auth = $auth;
        $this->opt = $opt;
    }

    abstract public function export($outputName, $template, array $opt);
}

interface R3ExportPAESLogger {

    public function log($level, $text);

    public function initProgress($totSteps);

    public function step($kind, $table, $tableNo);
}

class R3ExportPAES {

    static function factory($driver, $auth, $opt) {
        $includeName = dirname(__FILE__) . '/r3export_paes_' . strToLower($driver) . '.php';
        if (file_exists($includeName)) {
            require_once $includeName;
            $className = 'R3ExportPAESDriver_' . strToLower($driver);
            return new $className($auth, $opt);
        } else {
            throw new Exception('Unsupported driver "' . $driver . '"');
        }
    }

}
