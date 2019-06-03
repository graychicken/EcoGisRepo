<?php

require_once __DIR__ . '/PhpexcelBase.php';

class R3ImpExp_Exp_PhpexcelXlsAutoload extends R3ImpExp_Exp_PhpexcelBase
{
    
    public function getExtensions()
    {
        return array('Excel' => array('xls'));
    }

    /* Create a multi-table database */
    public function createDatabase($file, array $opt)
    {
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . sprintf('(%s, %s) called', $file, print_r($opt, true)));
        }
        
        $opt = array_merge($this->defaultOpt, $opt);

        $this->databaseFile = $file;
        $this->xls = new PHPExcel();
/*
        if ($opt['destination_encoding'] != 'AUTO') {
            $this->xls->setVersion(8);
        }
 *
 */
        if (null !== $this->logger) {
            $this->logger->debug(__METHOD__ . ': done');
        }
    }

    public function closeDatabase()
    {
        $writer = new PHPExcel_Writer_Excel5($this->xls);
        $writer->save($this->databaseFile);
    }

    public function isMultiTable()
    {
        return true;
    }
}
