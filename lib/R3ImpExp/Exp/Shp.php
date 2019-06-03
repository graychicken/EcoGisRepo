<?php

class R3ImpExp_Exp_Shp extends R3ExportDriver
{
    const SHP_EXPORT_PREFIX = 'shpdmup_%s_';
    
    public function getExtensions()
    {
        return array('ESRI Shape file' => array('shp', 'dbf', 'shx', 'prj', 'cpg'));
    }

    /**
     * Export the shape to sql data
     *
     * @param string         table with postgis data
     * @param string         final shape file name
     * @return array         options
     * @access private
     */
    private function pgsql2shp($table, $file, array $opt)
    {
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s, %s) called', $table, $file, print_r($opt, true)));
        }

        $pgsql2shpOutput = array();
        $retVal = -1;

        $cmd_opt = '';

        /* Host */
        if ($this->dsn['hostspec'] != '') {
            $cmd_opt .= '-h ' . $this->dsn['hostspec'] . ' ';
        }
        
        /* Port */
        if ($this->dsn['port'] != '') {
            $cmd_opt .= '-p ' . $this->dsn['port'] . ' ';
        }

        /* User */
        if ($this->dsn['username'] != '') {
            $cmd_opt .= '-u ' . $this->dsn['username'] . ' ';
        }
        
        /* Password */
        if ($this->dsn['password'] != '') {
            $cmd_opt .= '-P ' . $this->dsn['password'] . ' ';
        }

        /* dump policy */
        if ($opt['policy'] != 'INSERT') {
            throw new Exception('Invalid options: policy must be "INSERT"');
        }

        /* dump type */
        if ($opt['create'] != true || $opt['data'] != true) {
            throw new Exception('Invalid options: create and data must be true');
        }

        /* Geometry column */
        $cmd_opt .= '-g ' . $opt['geometry_column'] . ' ';

        /* Case sensitive */
        if ($opt['case_sensitive'] == true) {
            $cmd_opt .= '-k ';
        }

        /* Raw format */
        if ($opt['raw_format'] == true) {
            $cmd_opt .= '-r ';
        }

        $dbName = $this->dsn['database'];

        $std_err_file = $opt['tmp_path'] . sprintf(self::SHP_EXPORT_PREFIX, date('YmdHis')) . md5(microtime(true) + rand(0, 65535)) . '.err';

        $cmd = $opt['cmd_path'] . "pgsql2shp $cmd_opt -f " .
                escapeshellarg($file) . " " . escapeshellarg($dbName) . " " .
                escapeshellarg($table) . " 2> " . escapeshellarg($std_err_file);
        /* Executing the pgsql2shp command */
        if (null !== $this->logger) {
            $this->logger->notice(__METHOD__ . sprintf(": executing %s''", $cmd));
        }
        $startTime = microtime(true);
        exec($cmd, $pgsql2shpOutput, $retVal);
        if (($dbfFile = fopen($file . '.dbf', "r+")) === false) {
            throw new Exception("Could not open " . $file . '.dbf');
        }
        if (fseek($dbfFile, 29) === -1) {
            throw new Exception("Could not seek LDID");
        }
        if (($ldid = fread($dbfFile, 1)) === false) {
            throw new Exception("Could not get LDID");
        }
        // if LDID is not 0, rewrite it as 0
        if ($ldid != chr(0)) {
            if (fseek($dbfFile, 29) === -1) {
                throw new Exception("Could not seek LDID");
            }
            if (fwrite($dbfFile, chr(0)) === false) {
                throw new Exception("Could not write 0");
            }
        }
        fclose($dbfFile);
        file_put_contents($file . '.cpg', 'UTF-8');
        $totTime = $start = microtime(true) - $startTime;
        if (null !== $this->logger) {
            $this->logger->info(__METHOD__ . sprintf(": execution time: %.1f sec", ceil($totTime * 10) / 10));
        }
        $pgsql2shpOutput = array_merge($pgsql2shpOutput, file($std_err_file));
        if (LOG_DEBUG >= $opt['debug_level']) {
            @unlink($std_err_file);
        }
        $lineNo = 0;
        foreach ($pgsql2shpOutput as $line) {
            $lineNo++;
            if (strtolower(substr($line, 0, 15)) != 'initializing...') {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $param = substr($line, 0, $p);
                    $value = trim(substr($line, $p + 1));
                    switch (strtolower($param)) {
                        case 'output shape':
                            if (null !== $this->logger) {
                                $this->logger->notice(__METHOD__ . sprintf(': shape type=%s', strToUpper($value)));
                            }
                            break;
                        case 'dumping':
                            if (null !== $this->logger) {
                                $this->logger->notice(__METHOD__ . sprintf(': records=%s', substr($value, strpos($value, '[') + 1, -7)));
                            }
                            break;
                        default:
                            if (null !== $this->logger) {
                                $this->logger->warning(__METHOD__ . sprintf(': Unknown text at line %s: %s', $lineNo, $line));
                            }
                    }
                } else {
                    if (null !== $this->logger) {
                        $this->logger->warning(__METHOD__ . sprintf(': Unknown text at line %s: %s', $lineNo, $line));
                    }
                }
            }
        }
        if ($retVal <> 0) {
            throw new Exception("pgsql2shp returned $retVal\n" .
                            // "Command was:\n    $cmd\n". // diasabled, because password is shown
                            implode("\n", $pgsql2shpOutput));
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
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s, \PDO, %s) called', $table, $file, print_r($opt, true)));
        }

        $this->db = $db;

        $opt = array_merge($this->defaultOpt, $opt);

        if ($opt['cmd_path'] != '' && $opt['cmd_path'][strlen($opt['cmd_path']) - 1] != '/') {
            $opt['cmd_path'] .= '/';
        }
        if ($opt['tmp_path'] != '' && $opt['tmp_path'][strlen($opt['tmp_path']) - 1] != '/') {
            $opt['tmp_path'] .= '/';
        }

        $this->pgsql2shp($table, $file, $opt);

        ignore_user_abort($oldIgnoreUserAbort);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
    }
    
    public function closeDatabase()
    {
    }
}
