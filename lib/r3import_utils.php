<?php

/**
 * The R3ImportUtils class provides methods for handling a catalog
 * of temporary tables
 *
 * @category   Import and Export of data
 * @package    R3ImpExp
 * @author     Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright  R3 GIS s.r.l.
 * @link       http://www.r3-gis.com
 */
if (defined('R3_LIB_DIR')) {
    require_once R3_LIB_DIR . 'r3dbcatalog.php';
} else {
    require_once 'r3dbcatalog.php';
}


/**
 * The import system table name
 */
define('R3IMPORT_UTILS_VERSION', '0.3');

/**
 * The import system table name
 */
define('IMPORT_SYSTEM_TABLE_NAME', 'import_tables');

/**
 * The import system table name
 */
define('IMPORT_TABLE_PREFIX', 'tmp_');

/**
 * Maximum table length (63 - 8 chars used for sequence names)
 */
define('MAX_TABLE_LENGHT', 63 - 8);

/**
 * The default lifetime of a temporary table
 */
define('IMPORT_DEFAULT_LIFETIME', 3 * 24 * 60 * 60);

/**
 * R3ImportUtils collects a set of methods to handle tables
 * that are of temporary use, typically used during import of data
 * from external sources.
 * typical initilization is:
 * <code>
 * <?php
 *  $impUtils = new R3ImportUtils($db, array('schema'=> 'import'));
 *  $impUtils->createImportSystemTable(); // necessary only the first time, it creates a table with a catalog of all temporary tables
 *  $impUtils->dropTemporaryTableBySessionID(session_id());
 *  $impUtils->cleanTemporaryTables(3600); // ttl in seconds
 *  $tmpTableName = $impUtils->getSchema() . '.' . $impUtils->createTemporaryTableEntry('my_temp_table', 'my_temp_table', session_id());
 *  // create your table like 'CREATE TABLE $tmpTableName' ...
 *  // after finishing drop it and delete it from catalog with the following
 *  $impUtils->dropTemporaryTable($tmpTableName);
 * ?>
 * </code>
 *
 */
class R3ImportUtils
{

    /**
     * Class options (?)
     * @protected string
     */
    protected $options = null;

    /**
     * PDO database link
     * @private resource
     */
    private $db = null;

    /**
     * R3DbCatalog
     * @private resource
     */
    private $dbUtils;

    /**
     * Constructor
     *
     * Create the class
     *
     * @param PDO       database
     * @param array     options (?)
     * @return void
     */
    public function __construct(PDO $db, $options = array())
    {
        $defaultOptions = array(
            'schema' => 'public',
        );
        $this->options = array_merge($defaultOptions, $options);
        $this->dbUtils = R3DbCatalog::factory($db, $this->options);

        $this->db = $db;
    }

    /**
     * Get the module version
     *
     * @access public
     */
    public function getVersionString()
    {
        return R3IMPORT_UTILS_VERSION;
    }

    /**
     * Create the import-system table. The schema must exists
     *
     * @param string    the table name
     * @param string    the temporary table mask
     * @param integer   the version of the table
     * @return string   the name of the temporary table
     */
    public function createImportSystemTable()
    {
        if (!$this->dbUtils->tableExists(IMPORT_SYSTEM_TABLE_NAME, $this->options['schema'])) {
            $sql = sprintf("CREATE TABLE %s.%s (\n" .
                    "  it_table VARCHAR(63) NOT NULL, \n" .
                    "  it_session VARCHAR(63), \n" .
                    "  it_name VARCHAR(63) NOT NULL, \n" .
                    "  it_version INTEGER NOT NULL, \n" .
                    "  it_date  TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP NOT NULL, \n" .
                    "  CONSTRAINT %s_it_table_key PRIMARY KEY(it_table), \n" .
                    "  CONSTRAINT %s_version_chk CHECK (it_version > 0))\n", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, IMPORT_SYSTEM_TABLE_NAME, IMPORT_SYSTEM_TABLE_NAME);
            //$this->log($sql);
            $res = $this->db->query($sql);

            $sql = $this->dbUtils->createindexDDL($this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, array('it_session', 'it_name', 'it_version'), array('unique' => 1)
            );

            $res = $this->db->query($sql);
        }
        return false;
    }

    /**
     * Drop the import-system table
     *
     * @param boolean    if true remove all the import table cascade
     * @return boolean   on successfull return true
     */
    public function dropImportSystemTable($cascade)
    {
        if ($this->dbUtils->tableExists(IMPORT_SYSTEM_TABLE_NAME, $this->options['schema'])) {
            if ($cascade) {
                $this->cleanTemporaryTables(0);
            }
            $sql = sprintf("DROP TABLE IF EXISTS %s.%s", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME);
            // $this->log($sql);
            $res = $this->db->query($sql);

            return true;
        }
        return false;
    }

    /**
     * Create the temporary table entry in the import_tables table (Doesn't create the table itself)
     *
     * @param string    the table name
     * @param string    the temporary table mask
     * @param string    the session id (optional)
     * @param integer   the version of the table.
     * @return string   the name of the temporary table
     */
    public function createTemporaryTableEntry($name, $mask = null, $session = null, $version = 1)
    {

        //CONVERTE CARATTERI NON VALIDI
        if (strlen($name) == 0) {
            throw new EDatabaseError("Missing name");
        }
        if ($mask === null) {
            $mask = $name;
        }
        $name = strtolower($name);
        $mask = strtolower($mask);
        $mask = IMPORT_TABLE_PREFIX . substr($mask, 0, MAX_TABLE_LENGHT - (32 + strlen(IMPORT_TABLE_PREFIX) + 1)) . "_" . md5(microtime(true) + rand(0, 65535));
        // table name is lower and a-z0-9
        $mask = $this->dbUtils->cropId(preg_replace('/[^a-z0-9]+/i', '_', $mask));

        $sql = sprintf("INSERT INTO %s.%s \n" .
                "  (it_table, it_session, it_name, it_version, it_date)\n" .
                "  SELECT '$mask', '$session', '$name', COALESCE(MAX(it_version), 0) + 1, CURRENT_TIMESTAMP FROM %s.%s WHERE it_session='$session' AND it_name='$name'", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME);
        $res = $this->db->query($sql);

        return $mask;
    }

    /**
     * Create multiple temporary table entry in the import_tables table (Doesn't create the table itself)
     *
     * @param array     the table array
     * @param string    the temporary table mask
     * @param string    the session id (optional)
     * @param integer   the version of the table.
     * @return array    the name of the temporary tables
     */
    public function createMultiTemporaryTableEntry(array $names, $mask = null, $session = null, $version = 1)
    {

        //CONVERTE CARATTERI NON VALIDI
        if (count($names) == 0) {
            throw new EDatabaseError("Missing name");
        }
        $result = array();
        foreach ($names as $key => $name) {
            $result[$key] = $this->createTemporaryTableEntry($name, $mask, $session, $version);
        }
        return $result;
    }

    /**
     * Get current table-name by session-id from the temporary table entry in the import_tables
     *
     * @param string    the session-id
     * @param string    the table name
     * @return string   name of temporary table entry
     */
    public function getCurrentTemporaryTablesBySessionID($session, $name)
    {
        if ($this->dbUtils->tableExists(IMPORT_SYSTEM_TABLE_NAME, $this->options['schema'])) {
            $sql = sprintf("SELECT it_table from %s.%s \n" .
                    "WHERE 1=1", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME);
            if ($session !== null) {
                $sql .= " AND it_session=" . $this->db->quote($session);
            }
            if ($name !== null) {
                if (($p = strpos($name, '.')) !== false) {
                    $name = substr($name, $p + 1);
                }
                $sql .= " AND it_name=" . $this->db->quote(strtolower($name));
            }
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'oci') {
                $sql .= " AND ROWNUM <= 1";
            }
            $sql .= " ORDER BY it_version DESC";
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) != 'oci') {
                $sql .= " LIMIT 1";
            }
            $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

            return $row['it_table'];
        }
        return '';
    }

    /**
     * Drop the temporary table entry in the import_tables table AND (if exists) the temporary table too!
     *
     * @param string    the real table name (returned with createTemporaryTable)
     * @return boolean  return true on success
     */
    public function dropTemporaryTable($realname)
    {
        if (strpos($realname, '.') === false) {
            $schema = $this->options['schema'];
        } else {
            list($schema, $realname) = explode('.', $realname);
            if ($schema != $this->options['schema']) {
                throw new Exception('Invalid schema');
            }
        }

        if ($this->dbUtils->tableExists($realname, $schema)) {
            $drop_sql = sprintf("DROP TABLE IF EXISTS %s.%s", $this->options['schema'], $realname);
            $this->db->query($drop_sql);
        }

        $delete_sql = sprintf("DELETE FROM %s.%s WHERE it_table='%s'", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, $realname);
        $this->db->query($delete_sql);

        if ($this->dbUtils->tableExists('geometry_columns', 'public')) {
            $sql = sprintf("DELETE FROM public.geometry_columns WHERE f_table_schema=" . $this->db->quote($this->options['schema']) . " AND f_table_name=" . $this->db->quote($realname));
            $this->db->exec($sql);
        }

        if (!$this->dbUtils->tableExists($realname, $schema)) {
            return false;
        }

        $drop_sql = sprintf("DROP TABLE IF EXISTS %s.%s", $this->options['schema'], $realname);
        $this->db->query($drop_sql);

        return true;
    }

    /**
     * Get a list of all temporary tables matching the name, session, etc.
     *
     * @param string $name
     * @param string $session
     * @param integer $version
     *
     * @return array with temporary table names
     */
    public function getTemporaryTableByName($name, $session = null, $version = null)
    {
        $result = array();

        $sql = sprintf("SELECT it_table from %s.%s \n" .
                "WHERE 1=1", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME);
        if ($name !== null) {
            $sql .= " AND trim(it_name)=" . $this->db->quote(strtolower($name));
        }
        if ($session !== null) {
            $sql .= " AND trim(it_session)=" . $this->db->quote($session);
        }
        if ($version !== null) {
            $sql .= " AND it_version=" . $this->db->quote($version, PDO::PARAM_INT);
        }
        $sql .= " ORDER BY it_version ";
        $res = $this->db->query($sql);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row['it_table'];
        }
//        print_r($result);
//        $res = $this->db->query("SELECT it_table, it_name, it_session  FROM ".IMPORT_SYSTEM_TABLE_NAME);
//        print_r($res->fetchAll());
        return $result;
    }
    
    /**
     * Get current version from temporary table, matching the name and session
     *
     * @param string $name
     * @param string $session
     *
     * @return integer version-number
     */
    public function getCurrentVersionFromTemporaryTableByName($name, $session = null)
    {
        $sql = sprintf("SELECT MAX(it_version) from %s.%s \n" .
                "WHERE 1=1", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME);
        if ($name !== null) {
            $sql .= " AND trim(it_name)=" . $this->db->quote(strtolower($name));
        }
        if ($session !== null) {
            $sql .= " AND trim(it_session)=" . $this->db->quote($session);
        }
        return $this->db->query($sql)->fetchColumn(0);
    }

    /**
     * Drop the temporary table entry in the import_tables table using the
     * table name, session id and the version
     *
     * @param string    the table name
     * @param string    the session id
     * @param integer   the table version
     * @return array    return list of the table removed
     */
    private function dropTemporaryTableBy($name, $session = null, $version = null)
    {
        $tempTables = array();
        if ($this->dbUtils->tableExists(IMPORT_SYSTEM_TABLE_NAME, $this->options['schema'])) {
            $tempTables = $this->getTemporaryTableByName($name, $session, $version);
            foreach ($tempTables as $tempTable) {
                $this->dropTemporaryTable($tempTable);
            }
        }
        return $tempTables;
    }

    /**
     * Drop the temporary table entry in the import_tables table using the table name and the version
     *
     * @param string    the table name
     * @param string    the session id. If not provided all tables of all sessions are removed
     * @param integer   the table version. If not provided all tables of all versions are removed
     * @return array    return list of the table removed
     */
    public function dropTemporaryTableByName($name, $session = null, $version = null)
    {
        return $this->dropTemporaryTableBy($name, $session, $version);
    }

    /**
     * Drop the temporary table entry in the import_tables table using the table name and the version
     *
     * @param string    the table name
     * @param string    the session id. If not provided all tables of all sessions are removed
     * @param integer   the table version. If not provided all tables of all versions are removed
     * @return array    return list of the table removed
     */
    public function dropTemporaryTableBySessionID($session, $name = null, $version = null)
    {
        return $this->dropTemporaryTableBy($name, $session, $version);
    }

    public function getTemporaryTablesByAge($seconds)
    {
        $tables = array();
        
        switch ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'pgsql':
                $sql = sprintf("SELECT it_table from %s.%s \n" .
                        "WHERE it_date + INTERVAL '%s seconds' < CURRENT_TIMESTAMP FOR UPDATE", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, $seconds);
                break;
            case 'oci':
                $sql = sprintf("SELECT it_table from %s.%s \n" .
                        "WHERE it_date + INTERVAL '%s' SECOND < CURRENT_TIMESTAMP", $this->options['schema'], IMPORT_SYSTEM_TABLE_NAME, $seconds);
                break;
            default:
                throw new Exception(sprintf("database driver %s not supported", $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)));
        }
        $res = $this->db->query($sql);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['it_table'];
        }
        return $tables;
    }

    /**
     * Drop the temporary older than the given time
     *
     * @param integer   the maximum life time of the temporary table
     * @return array    return list of the table removed
     */
    public function cleanTemporaryTables($time = IMPORT_DEFAULT_LIFETIME)
    {
        $tables = $this->getTemporaryTablesByAge($time);

        foreach ($tables as $table) {
            $this->dropTemporaryTable($table);
        }
        return $tables;
    }

    /**
     * Return the schema used to import the data
     *
     * @return string
     */
    public function getSchema()
    {
        return $this->options['schema'];
    }
    
    /**
     * Return true if the postgresql-server supports unlogged table feature
     * 
     * @return boolean
     */
    public function supportUnloggedTables()
    {
        $sql = "SELECT version() ";
        $versionString = $this->db->query($sql)->fetchColumn(0);
        if (preg_match("/^PostgreSQL (\d+)\.(\d+)\.(\d+)/", $versionString, $matches)) {
            if ($matches[1] > 9 || ($matches[1] == 9 && $matches[2] >= 1)) {
                return true;
            }
        }
        
        return false;
    }
}
