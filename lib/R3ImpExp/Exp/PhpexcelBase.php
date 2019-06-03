<?php

abstract class R3ImpExp_Exp_PhpexcelBase extends R3ExportDriver
{

    /* Multi-table database file name */
    protected $databaseFile = null;

    /* Excel object */
    protected $xls = null;

    /* Formats */
    protected $formats = array();

    /** database catalog adapter */
    protected $catalog;

    /** number of sheets */
    protected $nrSheets;
    
    private function createWorksheetHeader($file, array $def, array $opt, $worksheet)
    {
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s) called', $file, print_r($def, true)));
        }
        $fields = array();
        $i = 0;

        foreach ($def as $field) {
            if (!empty($opt['header']) && isset($opt['header'][strtolower($field['column_name'])])) {
                $columnHeader = $opt['header'][strtolower($field['column_name'])];
            } elseif (!$opt['case_sensitive']) {
                $columnHeader = strToUpper($field['column_name']);
            } else {
                $columnHeader = $field['column_name'];
            }
            switch ($field['data_type']) {
                case 'smallint':
                case 'integer':
                case 'bigint':
                case 'decimal':
                case 'numeric':
                case 'number': // Oracle
                case 'double precision':
                case 'char': // Oracle
                case 'varchar': // MySQL
                case 'varchar2': // Oracle
                case 'character':
                case 'character varying':
                case 'text':
                case 'longtext': // MySQL
                case 'timestamp':
                case 'timestamp with time zone':
                case 'timestamp without time zone':
                case 'time':
                case 'time with time zone':
                case 'time without time zone':
                case 'date':
                case 'boolean':
                    $fields[] = $columnHeader;
                    break;
                case 'USER-DEFINED':
                // User defined type not handled (like geometry)
                    break;

                default:
                    if (null !== $this->logger) {
                        $this->logger->warning(__METHOD__ . sprintf(": Unknown field type '%s'", $field['data_type']));
                    }

            }
            $i++;
        }

        $x = 0;
        foreach ($fields as $field) {
            $worksheet->setCellValueByColumnAndRow($x++, 1, $field);
        }
        $headerStyleArray = array(
            'font' => array('bold' => true),
        );
        $worksheet->getStyle('A1:'.(PHPExcel_Cell::stringFromColumnIndex($x)).'1')->applyFromArray($headerStyleArray);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
        return true;
    }

    /*
     * Return a valid field name
    */
    private function adjFieldName($name)
    {
        return $this->opt['quote_char'] . $name . $this->opt['quote_char'];
    }

    private function populateWorksheet($file, $table, array $def, array $opt, $worksheet)
    {
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s, %s, %s, CLASS::%s) called', $file, $table, print_r($def, true), print_r($opt, true), get_class($worksheet)));
        }

        $fields = array();
        $fieldsFmt = array();
        foreach ($def as $field) {
            switch ($field['data_type']) {
                case 'smallint':
                case 'integer':
                case 'bigint':
                    $fields[] = $this->adjFieldName($field['column_name']);
                    $fieldsFmt[] = array('size'=>1, 'format'=>'0');
                    break;
                case 'decimal':
                case 'numeric':
                    $fields[] = $this->adjFieldName($field['column_name']);
                    $len = $field['numeric_scale'];
                    if ($len > 0) {
                        $fieldsFmt[] = array('size'=>$len + 1, 'format'=>'0.' . str_pad('0', $len, '0'));
                    } else {
                        $fieldsFmt[] = array('size'=>1, 'format'=>'0');
                    }
                    break;
                case 'number':
                case 'double precision':
                    $fields[] = $this->adjFieldName($field['column_name']);
                    $fieldsFmt[] = array('size'=>1, 'format'=>'0');  //SS: Se metto vuoto viene formattato come testo in open office. Verificare con altri dati
                    break;
                case 'time':
                case 'time with time zone':
                case 'time without time zone':
                case 'timestamp':
                case 'timestamp with time zone':
                case 'timestamp without time zone':
                    $fields[] = $this->adjFieldName($field['column_name']);
                    $fieldsFmt[] = array('size'=>8, 'format'=>'');  // gg/mm/aaaa hh:mm:ss

                    break;
                case 'date':
                    $fields[] = $this->adjFieldName($field['column_name']) . " - CAST('1899-12-30' AS DATE) AS " . $this->adjFieldName($field['column_name']);
                    $fieldsFmt[] = array('size'=>10, 'format'=>'dd/mm/yy');
                    break;
                case 'boolean':
                    $fields[] = "CASE {$this->adjFieldName($field['column_name'])} WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS {$this->adjFieldName($field['column_name'])}";
                    $fieldsFmt[] = array('size'=>1, 'format'=>'');
                    break;
                case 'char':
                case 'varchar':
                case 'varchar2':
                case 'character':
                case 'character varying':
                case 'text':
                case 'longtext':
                    $fields[] = $this->adjFieldName($field['column_name']);
                    $fieldsFmt[] = array('size'=>1, 'format'=>'');
                    break;
                case 'USER-DEFINED':
                // User defined type not handled (like geometry)
                    break;

                default:
                // echo "populateWorksheet() Unknown field type '" . $field['data_type'] . "'";
                    if (null !== $this->logger) {
                        $this->logger->warning(__METHOD__ . sprintf(": Unknown field type '%s'", $field['data_type']));
                    }
            }
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM $table";
        $res = $this->db->query($sql);

        $y = 2;
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $x = 0;
            foreach ($row as $data) {
                if ($fieldsFmt[$x]['format'] == '') {
                    $worksheet->setCellValueExplicitByColumnAndRow($x, $y, $data, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $worksheet->setCellValueByColumnAndRow($x, $y, $data);
                }
                $fieldsFmt[$x]['size'] = min(40, max($fieldsFmt[$x]['size'], strlen($data)));
                $x++;
            }
            $y++;
        }

        /* Set column width and style */
        $x = 0;
        foreach ($fieldsFmt as $style) {
            $colString = PHPExcel_Cell::stringFromColumnIndex($x);
            if ($fieldsFmt[$x]['format'] !== '') {
                $worksheet->getStyle("{$colString}2:{$colString}".($y-1))->getNumberFormat()->setFormatCode($fieldsFmt[$x]['format']);
            }
            $worksheet->getColumnDimension($colString)->setWidth($style['size'] + 1);
            $x++;
        }

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
        return true;
    }
    // $opt['header'] = array(sql_name => label)

    public function getWorksheet()
    {
        return $this->xls->getActiveSheet();
    }

    public function export($table, $file, \PDO $db, array $opt=array())
    {
        $oldIgnoreUserAbort = ignore_user_abort(true);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s, \PDO, %s) called', $table, $file, print_r($opt, true)));
        }

        $this->db = $db;
        $opt = array_merge($this->defaultOpt, $opt);
        $this->opt = $opt;

        if ($opt['tmp_path'] != '' && $opt['tmp_path'][strlen($opt['tmp_path']) - 1] != '/') {
            $opt['tmp_path'] .= '/';
        }

        $this->catalog = R3DbCatalog::factory($this->db);
        if ($this->databaseFile === null) {
            $isAutoGenerated = true;
            $this->createDatabase($file, $opt);
            if (strrchr($file, '.') === false) {
                $file = basename($file);
            } else {
                $file = substr(basename($file), 0, -strlen(strrchr($file, '.')));
            }
        } else {
            $isAutoGenerated = false;
        }

        /* Add a work sheet */
        if ($this->nrSheets == 0) {
            $worksheet = $this->xls->getActiveSheet();
            $worksheet->setTitle($file);
        } else {
            $worksheet = $this->xls->createSheet();
            $worksheet->setTitle($file);
        }
        $this->nrSheets++;
        /*
        if ($opt['destination_encoding'] != 'AUTO') {
            $worksheet->setInputEncoding($opt['destination_encoding']);
        }
*/
        // Return the PostgreSQL table description */
        $def = $this->catalog->getTableDesc($table);

        if (!$opt['create']) {
            throw new Exception('Invalid option \'create\': Must be true');
        }

        $this->createWorksheetHeader($file, $def, $opt, $worksheet);
        if ($opt['data']) {
            $this->populateWorksheet($file, $table, $def, $opt, $worksheet);
        }

        if ($isAutoGenerated) {
            $this->closeDatabase();
        }

        ignore_user_abort($oldIgnoreUserAbort);

        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
    }

    /* Create a multi-table database */
    abstract public function createDatabase($file, array $opt);
    
    public function isMultiTable()
    {
        return true;
    }
}
