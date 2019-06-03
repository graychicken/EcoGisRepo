<?php

abstract class R3DbCatalog_Base
{
    protected $db, $options;

    public function __construct($db, $options = array())
    {
        $this->db = $db;
        $this->options = $options;
    }


    public static function cropId($identifier)
    {
        // abstract
        throw new Exception("This is a abstract function");
    }

    abstract public function tableExists($name, $schema=null);

    abstract public function createIndexDDL($schema, $table, $columns, $options = array());

    abstract public function getTableDesc($table);

    abstract public function setClientEncoding($encoding);

    public static function getPath($name, $defaultSchema = 'public')
    {
        if (($p = strpos($name, '.')) === false) {
            $schema = $defaultSchema; // Read seach_path?
            $table = $name;
        } else {
            $schema = substr($name, 0, $p);
            $table = substr($name, $p + 1);
        }
        return array($schema, $table);
    }
}


class R3DbCatalog
{

    private static $instance;
    
        /**
     * Return a catalog driver
     *
     * @param  PDO         database connection
     * @return array       options
     * @access public
     */

    public static function factory($db, $options1 = null, $options = array())
    {
        /**
             * 2 APIs are supported, for backward compatibility
             * 
             * The newer one has 2 arguments: a PDO object as a first argument
             * and an option array as a second
             * 
             * The elder one is strongly deprecated
             */
            if (is_a($db, 'PDO')) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if (!empty($options)) {
                    throw new Exception("function has only two arguments");
                } elseif (is_array($options1)) {
                    $options = $options1;
                } elseif (!is_null($options1)) {
                    throw new Exception("second parameter must be an array");
                }
            } elseif (is_string($db)) {
                // obsolete API!!!
                $driver = $db;
                if (is_a($options1, 'PDO')) {
                    $db = $options1;
                } else {
                    throw new Exception("Invalid db parameter");
                }
            }
            
            // TODO: extract driver from database object
        $includeName = dirname(__FILE__) . '/r3dbcatalog/drivers/'.strToLower($driver) . '.php';
        if (file_exists($includeName)) {
            require_once $includeName ;
            $className = 'R3DbCatalog_' . ucfirst(strToLower($driver));
            return new $className($db, $options);
        } else {
            throw new Exception('Unsupported database "' . $driver . '"');
        }
    }
    
    public static function set($instance)
    {
        return (R3DbCatalog::$instance = $instance);
    }
    
    public static function get()
    {
        return R3DbCatalog::$instance;
    }
}
