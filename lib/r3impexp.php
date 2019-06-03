<?php

if (!class_exists('R3DbCatalog_Base')) {
    require_once __DIR__ . '/r3dbcatalog.php';
}

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * The main include file for R3Import and R3Export package
 *
 * PHP versions 5
 *
 * LICENSE: Commercial
 *
 * @category  Database import / export
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright 2008 R3 GIS s.r.l.
 * @license   Commercial http://www.r3-gis.com
 * @version   0.1a
 * @link      http://www.r3-gis.com
 */

/**
 *
 * The R3ArchiveImport class provides methods to manage files to be imported
 *
 * @category  Database import / export utility
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
//define('R3ArchiveImport', '0.1a');

abstract class R3ArchiveImport
{

    /**
     * return an array with the files in the specified path (or compressed file)
     *
     * @param string|array  path or (compressed) file name to search for.
     *                      If  the param is an array all the values are checked
     * @param  string       valid file pattern. Default = '*'
     * @param  boolean      if True the function is recursive
     * @return array        Return an array with the list of the matched files.
     *                      If the input parameter is an array the returned array is a 2D array. The first key is the path
     * @access public
     */
    abstract public function getFileList($archives, $pattern = null, $recursive = false);

    /**
     * Expand the archive or a part of it
     *
     * @param string|array  path or (compressed) file name to expand.
     *                      If  the param is an array all the values are checked
     * @param  null|array   Files to extract. If null all files will be extract
     * @param  string       The output base directory. Default current directory
     * @return array        Return an array with the list of the expanded files (with path)
     * @access public
     */
    abstract public function expandFile($archive, $files = null, $output_dir = null);


    /**
     * Compress files into an archive
     *
     * @param string         path or (compressed) file name to create/append.
     * @param  string|array  Files to add. If null all the files of the current directory are added
     * @param  string        The output base directory. Default current directory
     * @return array         Return an array with the list of the expanded files (with path)
     * @access public
     */
    //abstract public function compressFile($archive, $files=null);
    //pub func compressFile(zip_file(s), $tmp_dir);
    //pub func getFileInfo(zip_file(s)); // ritorna array('compress_size' =>, 'expendanded_size' =>)

    /**
     * Return the extension of a file (without dot)
     *
     * @param string         file name
     * @param  boolean       if true the extension is returned in lower case
     * @return string        the extensione
     * @access public
     */
    public static function getExt($fileName, $forceLower = false)
    {
        return substr(strrchr($fileName, '.'), 1);
    }
}

class R3DirImport extends R3ArchiveImport
{

    private function doGetFileList($archive, $pattern = null, $recursive = false)
    {
        $res = array();

        if (substr($archive, -1) != '/') {
            $archive .= '/';
        }
        $files = glob($archive . '*');
        foreach ($files as $file) {
            if ($recursive && is_dir($file)) {
                $filesInDir = $this->doGetFileList($file, $pattern, $recursive);
                $res = array_merge($res, $filesInDir);
            } elseif (is_file($file) && ($pattern === null || preg_match($pattern, basename($file)))) {
                $res[] = $file;
            }
        }
        return $res;
    }

    public function getFileList($archives, $pattern = null, $recursive = false)
    {
        $res = array();
        if (is_array($archives)) {
            foreach ($archives as $archive) {
                $res = array_merge($res, $this->doGetFileList($archive, $pattern, $recursive));
            }
        } else {
            $res = $this->doGetFileList($archives, $pattern, $recursive);
        }
        sort($res, SORT_REGULAR);
        return $res;
    }

    public function expandFile($archive, $files = null, $output_dir = null)
    {
    }
}

class R3ZIPImport extends R3ArchiveImport
{

    public function __construct()
    {
        if (!extension_loaded('zip')) {
            throw new Exception('ZIP extension not available');
        }
    }

    public function doGetFileList($archive, $pattern = null, $recursive = false)
    {
        $res = array();
        $zip = new ZipArchive();
        $zip->open($archive);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $node = $zip->statIndex($i);
            $res[] = $node['name'];
        }
        $zip->close();
        return $res;
    }

    public function getFileList($archives, $pattern = null, $recursive = false)
    {
        $res = array();
        if (is_array($archives)) {
            foreach ($archives as $archive) {
                $res[$archive] = $this->doGetFileList($archive, $pattern, $recursive);
                sort($res[$archive], SORT_REGULAR);
            }
        } else {
            $res = $this->doGetFileList($archives, $pattern, $recursive);
            sort($res, SORT_REGULAR);
        }
        return $res;
    }

    public function expandFile($archive, $files = null, $output_dir = null)
    {
    }
}

//    pub func getFileList(pattern, zip_file(s));
//pub func expandFile(zip_file(s), $tmp_dir);
//pub func getFileInfo(zip_file(s)); // ritorna array('compress_size' =>, 'expendanded_size' =>)
//class TGZImport
//class DirImport
//    pub func getFileList(pattern, zip_file(s), $options); // $options array('recursive' => s/n, 'expandCompressed' s/n)
//  class TGZImport
//  class DirImport
//pub func getFileList(pattern, zip_file(s), $options); // $options array('recursive' => s/n, 'expandCompressed' s/n)

/**
 *
 * The R3Import class provides methods to import files in different formats to postgres and oracle
 *
 * @category  Database import
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
abstract class R3ImportDriver implements LoggerAwareInterface
{
    /**
     * Logger instance
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /* The current debug level */
    protected $currentDebugLevel = LOG_NOTICE;

    /* Default options */
    protected $defaultOpt = array(
        'srid' => -1,
        'create' => true,
        'data' => true,
        'geometry_column' => 'the_geom',
        'dump_format' => 'B',
        'case_sensitive' => false,
        'force_int4' => false,
        'keep_precision' => true,
        'simple_geometry' => false,
        'source_encoding' => 'AUTO',
        'policy' => 'INSERT',
        'debug_level' => LOG_NOTICE,
        'cmd_path' => '',
        'tmp_path' => '/tmp',
        'table' => null,
        'table_nr' => null,
        'sql' => null,
        'read_buffer' => 8192,
        'first_line_header' => true,
        'separator' => ',',
        'quote_char' => '"',
        'line_feed' => "\r\n",
        'validate_schema' => false,
        'dbtype' => 'pgsql',
        'create_gid' => true
    );

    /** database connection */
    protected $db;

    /** informations about the import */
    protected $importInfo = array();

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Set logger instance
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return the valid extension in a 2D array. The 1D is the generic name and the 2D is the index of the mandatory extensions
     * If no extension available null is returned (eg: database connection)
     *
     * @return array  The valid extension(s)
     * @access public
     */
    abstract public function getExtensions();

    /**
     * Return the priority of the driver (if you have same extensions)
     *
     * @return integer  The priority. Default 50
     * @access public
     */
    abstract public function getPriority();

    /**
     * the file format may potentially contain more then 1 table
     *
     * @return boolean
     * @access public
     */
    public function isMultiTable()
    {
        return false;
    }

    /**
     * return an array with the table names
     *
     * @param string $fileName  Source file name
     * @param array  $opt       optional array with driver specific options
     * @return array            numerically indexed array with table names
     * @access public
     */
    public function getMultiTableIndex($fileName, $opt = array())
    {
        return array();
    }

    /**
     * Import the specified file to the specified database
     *
     * @param string   Source file name (or database connection)
     * @param string   Destination (schema.)table name
     * @param PDO      A valid PDO connection
     * @param array    Options. Valid options are:
     *  - srid: postgis srid (default -1)
     *  - create: If true the create the table (default true)
     *  - data: If true append the data to the table (default true)
     *  - geometry_column: The name of the geometry column  (default the_geom)
     *  - dump_format: 'B'=bulk, 'I'=insert statement (default 'B')
     *  - case_sensitive: true to maintain the case on the table. Default false
     *  - unique_field: Unique field name. If '' a gid field and a sequence are created.
     *  - force_int4: If true all the integer field are converted in int4. Default (false)
     *  - keep_precision: If true the precision of the data is maintained and NOT converted. Default true
     *  - simple_geometry: If true a simple geometry is created instead of a multi geomerty
     *  - source_encoding: Specify the character encoding of Shape's attribute column. (default : "ASCII")
     *  - policy: Specify NULL geometries handling policy (INSERT, SKIP, ABORT)
     *  - cmd_path: The path of the command(s) to execute. If empty the command must be in the system path. Default ''
     *  - tmp_path: The temporary path to use. Default '/tmp';
     *  - debug_level: specify the debug level (?)
     *  - table if multi-table format or database, set the table to import
     *  - table_nr if multi-table format or database, set the table index (start 0) to import
     *  - sql   if database, set the sql to execute to extract data (> priority than table)
     *  - read_buffer   the read buffer size (default 8192)
     *  - first_line_header  if true the first line of the file is the header line
     *  - separator  the field sepatatr character
     *  - line_feed  the CR (or LF or CRLF sequence)
     *  - quote_char the quote char
     * @return string  ?????????????
     * @access public
     */
    abstract public function import($file, $table, PDO $db, array $opt = array());

    /**
     * Clear the info data
     *
     * @access public
     */
    public function clearInfo()
    {
        return $this->importInfo = array();
    }

    /**
     * Get info data
     *
     * @access public
     */
    public function getInfo()
    {
        return $this->importInfo;
    }

    /**
     * Return the current debug level of the driver
     *
     * @return integer   The debug level
     * @access public
     */
    public function getDebugLevel()
    {
        return $this->currentDebugLevel;
    }

    /**
     * Set the debug level of the driver
     *
     * @parma integer $level   The new debug level
     * @access public
     */
    public function setDebugLevel($level)
    {
        $this->currentDebugLevel = $level;
    }

    /**
     * Eventually remove the extension.
     *
     * @param string $file File name
     * @return string
     */
    public function getBaseName($file)
    {
        $baseName = null;
        $extensions = $this->getExtensions();
        foreach ($extensions as $name => $exts) {
            foreach ($exts as $ext) {
                $extLen = strlen($ext) + 1;
                if (strlen($file) > $extLen) {
                    if (strtolower(mb_substr($file, -$extLen)) == '.' . $ext) {
                        $baseName = mb_substr($file, 0, -$extLen);
                        break;
                    }
                }
            }
            if (!is_null($baseName)) {
                break;
            }
        }
        if (is_null($baseName)) {
            $baseName = $file;
        }
        return $baseName;
    }
}

/**
 *
 * The R3Import class provides methods to import files in different formats to postgres and oracle
 *
 * @category  Database import
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
class R3Import
{

    /**
     * Return the R3ImportDriver
     *
     * @param string $driver
     * @param string $tool
     * @return \R3ImportDriver
     * @throws \Exception
     */
    public static function factory($driver, $tool = null)
    {
        $toolPart = '';
        if (!is_null($tool)) {
            $toolPart = ucfirst(strToLower($tool));
        }
        $name = $toolPart . ucfirst(strToLower($driver));
        $includeName = __DIR__ . "/R3ImpExp/Imp/{$name}.php";
        if (file_exists($includeName)) {
            require_once $includeName;
            $className = "R3ImpExp_Imp_{$name}";
            return new $className;
        } else {
            throw new \Exception('Unsupported format "' . $driver . '"');
        }
    }

    /**
     * Return the R3ImportDriver from a file name
     *
     * @param string  $fileName
     * @param boolean $exceptOnFail if true an exception is raised if no driver available, if false null is returned (on failure)
     * @return \R3ImportDriver|null
     * @throws \Exception
     */
    public static function factoryFromFile($fileName, $exceptOnFail = true)
    {
        $capabilities = R3Import::getCapabilities();

        /* Search in every available driver */
        foreach ($capabilities as $capability) {
            try {
                $driver = R3Import::factory($capability);
                $extensions = $driver->getExtensions();
                if ($extensions !== null) {
                    $ext = strtolower(substr(strrchr($fileName, '.'), 1));
                    /* Is the given file extension in the extensions list? */
                    if (in_array($ext, $extensions[key($extensions)])) {
                        $nameNoExt = substr($fileName, 0, -strlen($ext));
                        $extToCheck = $extensions[key($extensions)];
                        /* Are all extensions present? */
                        $done = true;
                        foreach ($extToCheck as $e) {
                            if (!file_exists($nameNoExt . $e)) {
                                $done = false;
                                break;
                            }
                        }
                        if ($done) {
                            if ($ext == $extensions[key($extensions)][0]) {
                                return $driver;
                            } else {
                                return null;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }
        if ($exceptOnFail) {
            throw new \Exception('Unsupported format for file "' . basename($fileName) . '"');
        }
        return null;
    }

    /**
     * Return the available capabilities. You can use the capabilities to factory the import class
     *
     * @return string        the available capabilities
     * @access public
     */
    public static function getCapabilities()
    {
        static $capabilities = null; /* Cache the capabilities to prevent multiple filesystem access */

        if ($capabilities === null) {
            $capabilityTmp = array();
            $files = glob(__DIR__ . '/R3ImpExp/Imp/*.php');
            foreach ($files as $file) {
                $capability = substr(basename($file), 0, -4);
                try {
                    $driver = R3Import::factory($capability);
                    $priority = $driver->getPriority();
                    $capabilityTmp[$priority][] = $capability;
                } catch (Exception $e) {
                    // echo "AA";
                }
                // print_r($capabilityTmp);
            }
            ksort($capabilityTmp);
            $capabilities = array();
            foreach ($capabilityTmp as $capabilitySorted) {
                foreach ($capabilitySorted as $capability) {
                    $capabilities[] = $capability;
                }
            }
        }
        // print_r($capabilities);
        // die();
        return $capabilities;
    }

    // static function factoryFromFileName(file_name); // retirna un oggetto figlio di R3ImportDriver
    // include ...R3ImportDriverShape .  tipo_import;
    // $classname = R3ImportDriverShape .  tipo_import;
}

/**
 *
 * The R3ExportDriver class provides methods to export files in different formats from postgres and oracle
 *
 * @category  Database export
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
abstract class R3ExportDriver implements LoggerAwareInterface
{
    /**
     * Logger instance
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Export format
     *
     * @var string
     */
    protected $format = null;

    /**
     * Default options
     *
     * @var array
     */
    protected $defaultOpt = array(
        'srid' => -1,
        'create' => true,
        'data' => true,
        'geometry_type' => null, // used in OgrShp export driver
        'geometry_column' => 'the_geom',
        'raw_format' => false,
        'case_sensitive' => false, /* 'force_int4'=>false, */
        'keep_precision' => true, /* 'simple_geometry'=>false, */
        'destination_encoding' => 'AUTO',
        'policy' => 'INSERT',
        'debug_level' => LOG_NOTICE,
        'cmd_path' => '',
        'tmp_path' => '/tmp',
        'table' => null,
        'separator' => ',',
        'quote_char' => '"',
        'line_feed' => "\r\n",
        'id' => 'gid',
        'dbtype' => 'pgsql'
    );

    /**
     * Current options
     * 
     * @var array
     */
    protected $opt = array();

    /**
     * Database instance
     *
     * @var \PDO
     */
    protected $db;

    const STYLE_SHEET_FILE = 1;
    const STYLE_SHEET_STRING = 2;
    const STYLE_SHEET_ARRAY = 3;

    /** Style sheet */
    protected $styleSheet;

    /** Style sheet type */
    protected $styleSheetType;

    /**
     * Constructor
     *
     * @param string $format
     */
    public function __construct($format)
    {
        $this->format = $format;
    }

    /**
     * Set logger instance
     * 
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return the valid extension in a 2D array. The 1D is the generic name and the 2D is the index of the mandatory extensions
     *
     * @return array  The valid extension(s)
     */
    abstract public function getExtensions();

    /**
     * the file format may potentially contain more then 1 table
     *
     * @return boolean
     */
    public function isMultiTable()
    {
        return false;
    }

    /**
     * return the table index
     *
     * @param string   Source file name
     * @return array
     */
    public function getMultiTableIndex($fileName, array $opt = array())
    {
        return array();
    }

    /**
     * export the specified file to the specified database
     *
     * @param string   Destination (schema.)table name
     * @param string   Source file name
     * @param \PDO      A valid PDO connection
     * @param array    Options. Valid options are:
     *  - srid: postgis srid (default -1)
     *  - create: If true the create the table (default true)
     *  - data: If true append the data to the table (default true)
     *  - geometry_column: The name of the geometry column  (default the_geom)
     *  - dump_format: 'B'=bulk, 'I'=insert statement (default 'B')
     *  - case_sensitive: true to maintain the case on the table. Default false
     *  - unique_field: Unique field name. If '' a gid field and a sequence are created.
     *  - force_int4: If true all the integer field are converted in int4. Default (false)
     *  - keep_precision: If true the precision of the data is maintained and NOT converted. Default true
     *  - simple_geometry: If true a simple geometry is created instead of a multi geomerty
     *  - source_encoding: Specify the character encoding of Shape's attribute column. (default : "ASCII")
     *  - policy: Specify NULL geometries handling policy (INSERT, SKIP, ABORT)
     *  - cmd_path: The path of the command(s) to execute. If empty the command must be in the system path. Default ''
     *  - tmp_path: The temporary path to use. Default '/tmp';
     *  - debug_level: specify the debug level (?)
     *  - table if multi-table format, set the table to export
     * @return string  ?????????????
     */
    abstract public function export($table, $file, \PDO $db, array $opt = array());

    /**
     * Set a style sheet for the exported data
     *
     * @param string $styleSheet filename or string
     * @param unknown_type $type       
     */
    public function setStyleSheet($styleSheet, $type = self::STYLE_SHEET_FILE)
    {
        $this->styleSheet = $styleSheet;
        $this->styleSheetType = $type;
    }

    /**
     * This method can be used to close file handles, clean up stuff, etc.
     */
    abstract public function closeDatabase();
}

/**
 *
 * The R3Export class provides methods to export files from postgis (and oracle) to different formats
 *
 * @category  Database export
 * @package   R3ImpExp
 * @author    Sergio Segala <sergio.segala@r3-gis.com>
 * @copyright R3 GIS s.r.l.
 * @link      http://www.r3-gis.com
 */
class R3Export
{

    /**
     * Return the R3ExportDriver
     *
     * @param string  $driver
     * @param string  $tool
     * @param boolean $rawFactory
     * @return \R3ExportDriver
     * @throws \Exception
     */
    public static function factory($driver, $tool = null, $rawFactory = false)
    {
        $toolPart = '';
        if ($rawFactory) {  // Multi format for a single driver
            $includeName = __DIR__ . "/R3ImpExp/Exp/{$tool}.php";
            if (file_exists($includeName)) {
                require_once $includeName;
                $className = "R3ImpExp_Exp_{$tool}";
                return new $className($driver);
            } else {
                throw new \Exception("Unsupported tool \"{$tool}\"");
            }
        } else {
            if (!is_null($tool)) {
                $toolPart = ucfirst(strToLower($tool));
            }
            $name = $toolPart . ucfirst(strToLower($driver));
            $includeName = __DIR__ . "/R3ImpExp/Exp/{$name}.php";
            if (file_exists($includeName)) {
                require_once $includeName;
                $className = "R3ImpExp_Exp_{$name}";
                return new $className($driver);
            } else {
                throw new \Exception('Unsupported format "' . $driver . '"');
            }
        }
    }

    /**
     * Return the available capabilities. You can use the capabilities to factory the export class
     *
     * @access array
     */
    public static function getCapabilities()
    {
        static $capabilities = null; /* Cache the capabilities to prevent multiple filesystem access */

        if ($capabilities === null) {
            $files = glob(dirname(__FILE__) . '/r3impexp/exp/*.php');
            foreach ($files as $file) {
                $capabilities[] = substr(substr(strrchr($file, '/'), 7), 0, -4);
            }
        }
        return $capabilities;
    }
}
