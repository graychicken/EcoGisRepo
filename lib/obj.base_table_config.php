<?php

class R3BaseTableConfig {

    private $auth;
    private $options;

    function __construct(IR3Auth $auth, array $options = array()) {
        $default = array('section' => 'SETTINGS', 'param_mask' => '%s_TABLE_CONFIG');

        $this->options = array_merge($options, $default);
        $this->auth = $auth;
    }

    /**
     * Return the configuration from user manager or default
     * param array $defaultConfig        default values
     * return array                      configuration array
     */
    public function getConfig(array $defaultConfig, $table) {
        $defaultRow = array('label' => '', 'type' => 'text', 'width' => null, 'visible' => true, 'position' => null, 'sort_order' => null, 'sort_dir' => 'A', 'options' => array('sortable' => true));

        $data = $this->auth->getConfigValue($this->options['section'], sprintf($this->options['param_mask'], strtoupper($table)));
        if (empty($data)) {
            $data = $defaultConfig;
        } else {
            foreach ($data as $key => $val) {
                if (isset($defaultConfig[$key])) {
                    $data[$key] = array_merge($defaultConfig[$key], $val);
                }
            }
        }
        // Set default values
        $hugeNumber = 9999999;
        foreach ($data as $key => $val) {
            $data[$key] = array_merge($defaultRow, $val);
            if (isset($val['options'])) {
                $data[$key]['options'] = array_merge($defaultRow['options'], $val['options']);
            } else {
                $data[$key]['options'] = $defaultRow['options'];
            }
            if ($data[$key]['position'] === null) {
                $hugeNumber++;
                $data[$key]['position'] = $hugeNumber;
            }
        }
        return self::sortConfig($data);
    }

    /**
     * Return the configuration array sorted by position
     * param array $config              config
     * return array                     configuration array
     */
    static public function sortConfig(array &$config) {
        $orderArray = array();
        foreach ($config as $key => $val) {
            $orderArray[$key] = $val['position'];
        }
        asort($orderArray);
        $result = array();
        $i = 1;
        foreach ($orderArray as $key => $dummy) {
            $result[$key] = $config[$key];
            $result[$key]['position'] = $i;
            $i++;
        }
        return $result;
    }

    /**
     * Save into the user manager the table column definition
     * param array $defaultConfig        default values
     */
    public function setConfig(array $config, $table, $defaultConfig = array()) {
        $toSave = array();
        foreach ($config as $key => $rowData) {
            foreach ($rowData as $param => $value) {
                if (!isset($defaultConfig[$key][$param]) || $defaultConfig[$key][$param] <> $config[$key][$param]) {
                    $toSave[$key][$param] = $config[$key][$param];
                }
            }
            $toSave[$key]['visible'] = isset($config[$key]['visible']);
        }
        $toSave = $this->sortConfig($toSave);
        $toSaveSerialyzed = serialize($toSave);
        $this->auth->setConfigValue($this->options['section'], sprintf($this->options['param_mask'], strtoupper($table)), $toSaveSerialyzed, array('persistent' => true, 'type' => 'array', 'description' => _("List-table settings for {$table}")));
    }

    public function resetConfig($table) {
        $this->auth->setConfigValue($this->options['section'], sprintf($this->options['param_mask'], strtoupper($table)), null);
    }

}
