<?php

//require_once R3_LIB_DIR . 'r3mdb2.php';
require_once R3_LIB_DIR . 'r3dbcatalog.php';

/**
 * Utility function class for EcoGIS
 */
class R3LogTableHelper {

    static public function setCatalog(R3DbCatalog $catalog) {
        R3LogTableHelper::$catalog = $catalog;
    }

    // Crea/aggiorna il trigger per tracciare le modifiche
    static public function createUpdateTrigger($table) {
        
    }

}
