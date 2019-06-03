<?php

class R3ImpExp_Exp_OgrShp extends R3ExportDriver
{
    const SHP_EXPORT_PREFIX = 'shpdmup_%s_';

    private $dsn;
    
    public function getExtensions()
    {
        return array('ESRI Shape file' => array('shp', 'dbf', 'shx', 'prj', 'cpg'));
    }

    /**
     * Get supported geometry types
     * 
     * @see option nlt in http://www.gdal.org/ogr2ogr.html
     *
     * @return array
     */
    private function getSupportedGeometryTypes()
    {
        return array(
            'NONE',
            'GEOMETRY',
            'POINT',
            'LINESTRING',
            'POLYGON',
            'GEOMETRYCOLLECTION',
            'MULTIPOINT',
            'MULTIPOLYGON',
            'MULTILINESTRING',
            'PROMOTE_TO_MULTI',
        );
    }

    /**
     * Export the shape to sql data
     *
     * @param string         table with postgis data
     * @param string         final shape file name
     * @param array          DSN
     * @return array         options
     * @access private
     */
    private function ogr2ogr($table, $file, array $opt)
    {
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s, %s) called', $table, $file, print_r($opt, true)));
        }

        $ogr2ogrOutput = array();
        $retVal = -1;

        // add shp extension to avoid creating a sub-directory
        if (strpos($file, '.shp') !== false) {
            $shapeFile = $file;
        } else {
            $shapeFile = $file . '.shp';
        }
        
        $std_err_file = $opt['tmp_path']  . sprintf(self::SHP_EXPORT_PREFIX, date('YmdHis')) . md5(microtime(true) + rand(0, 65535)) . '.err';

        $cmd = $opt['cmd_path']."ogr2ogr -f ".escapeshellarg('ESRI Shapefile').
        " ".escapeshellarg($shapeFile);
        if ($this->dsn['phptype'] == 'pgsql') {
            $cmd .= " PG:" .
                    escapeshellarg("host={$this->dsn['hostspec']} user={$this->dsn['username']} " .
                            "dbname={$this->dsn['database']} password={$this->dsn['password']}");
        } elseif ($this->dsn['phptype'] == 'oci8') {
            $cmd .= " OCI:" .escapeshellarg("{$this->dsn['username']}/{$this->dsn['password']}");
        }
        $cmd .= " -sql ".escapeshellarg('SELECT * FROM '.$table);
        
        // force output srid
        if (isset($opt['srid']) && $opt['srid'] !== -1) {
            $cmd .= sprintf(" -a_srs EPSG:%d", $opt['srid']);
        }

        // force geometry type
        if (!is_null($opt['geometry_type'])) {
            if (in_array($opt['geometry_type'], $this->getSupportedGeometryTypes())) {
                $cmd .= sprintf(' -nlt %s', $opt['geometry_type']);
            } else {
                throw new \Exception(sprintf("Geometry type '%s' is not supported. Choose one of this: %s", $opt['geometry_type'], print_r($this->getSupportedGeometryTypes(), true)));
            }
        }
        
        // force output encoding to UTF-8
        $cmd .= " -lco ENCODING=UTF-8 ";

        // write error output to file
        $cmd .= " 2> ".escapeshellarg($std_err_file);

        // execute the ogr2ogr command
        if (null !== $this->logger) {
            $this->logger->notice(__METHOD__ . sprintf(": executing '%s'", $cmd));
        }
        $startTime = microtime(true);
        exec($cmd, $ogr2ogrOutput, $retVal);
        $totTime = $start = microtime(true) - $startTime;
        if (null !== $this->logger) {
            $this->logger->info(__METHOD__ . sprintf(": execution time: %.1f sec", ceil($totTime * 10) / 10));
        }
        $ogr2ogrOutputComplete = array_merge($ogr2ogrOutput, file($std_err_file));
        if (LOG_DEBUG >= $opt['debug_level']) {
            @unlink($std_err_file);
        }
        if ($retVal != 0) {
            $msg = "ogr2ogr return $retVal, command was\n"
            .$cmd."\nmessage was:\n".var_export($ogr2ogrOutputComplete, true);
            if (null !== $this->logger) {
                $this->logger->error($msg);
            }
            // 2012-03-19: TODO: currently, ogr2ogr gives warnings, if field names get truncated
            // If we can change the field names before, that the Exception can be enabled, again
            // throw new Exception($msg);
        }

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
    }

    public function setDsn(array $dsn)
    {
        $this->dsn = $dsn;
    }
    
    public function export($table, $file, \PDO $db, array $opt=array())
    {
        $oldIgnoreUserAbort = ignore_user_abort(true);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf(': (%s, %s, \PDO, %s) called', $table, $file, print_r($opt, true)));
        }

        $this->db = $db;

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': setting default values');
        }
        $opt = array_merge($this->defaultOpt, $opt);

        if ($opt['cmd_path'] != '' && substr($opt['cmd_path'], -1) != '/') {
            $opt['cmd_path'] .= '/';
        }
        if ($opt['tmp_path'] != '' && substr($opt['tmp_path'], -1) != '/') {
            $opt['tmp_path'] .= '/';
        }

        if (is_null($this->dsn)) {
            throw new Exception("DSN must be set before calling export()");
        }
        
        $this->ogr2ogr($table, $file, $opt);

        ignore_user_abort($oldIgnoreUserAbort);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
    }
    
    public function closeDatabase()
    {
    }
}
