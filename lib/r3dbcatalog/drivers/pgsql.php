<?php

class R3DbCatalog_Pgsql extends R3DbCatalog_Base
{
    const maxIdLength = 63;

    public static function cropId($identifier)
    {
        if (mb_strlen($identifier) > self::maxIdLength) {
            $identifier = mb_substr($identifier, 0, self::maxIdLength);
        }
        return $identifier;
    }

    /**
     * Return the current search path
     * @access public
     * @return array
     */
    public function getSearchPath()
    {
        $sql = <<<SQL
        SELECT array_index AS index, schemas[array_index] AS schema FROM (
            SELECT generate_series(1, replace(split_part(array_dims(schemas),':',1),'[','')::int) AS array_index, schemas FROM (
                SELECT current_schemas(FALSE) AS schemas
            ) AS foo
        ) AS foo
SQL;
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $result = array();
        foreach ($rows as $row) {
            $result[] = $row['schema'];
        }
        return $result;
    }

    /**
     * split table information into structured array for queries
     * @access public (DD: Could be private)
     * @param string $name
     * @return array
     */
    public function extractTableDesc($name)
    {
        //print_r($this->getSearchPath());
        // TODO: bug fix current_schema returns the username if some schema_name corrisponds to the current username
        //$currentSchema = $this->db->query("SELECT current_schema()")->fetchColumn(0);

        $currentSchemas = $this->getSearchPath();

        $result = array();
        foreach ($currentSchemas as $currentSchema) {
            $result = array('table' => '', 'schema' => $currentSchema);   //SS: Change public to searchpath!
            if (is_array($name)) {
                $result = array_merge($result, $name);
            } else {
                list($result['schema'], $result['table']) = self::getPath($name, $result['schema']);
            }
            $sql = <<<SQL
            SELECT count(*) FROM (
                SELECT table_schema, table_name FROM information_schema.tables
                    UNION
                SELECT table_schema, table_name FROM information_schema.views
            ) AS foo
            WHERE table_schema='{$result['schema']}' AND table_name='{$result['table']}'
SQL;
            // stop search at first object found
            if ($this->db->query($sql)->fetchColumn(0) > 0) {
                break;
            }
        }
        return $result;
    }

    /**
     * Return if the table exists
     * @todo if no schema is specified, check on all current_schemas
     * @access public
     * @param string $name
     * @param string $schema
     * @return boolean
     */
    public function tableExists($name, $schema=null)
    {
        if ($schema !== null) {
            $name = "{$schema}.{$name}";
        }
        $name = $this->extractTableDesc($name);
        return array_key_exists("{$name['schema']}.{$name['table']}", $this->getTableList());
        /*
          $sql = "SELECT count(*) FROM pg_tables WHERE " .
          "schemaname=" . $this->db->quote($name['schema']) . " AND " .
          "tablename=" . $this->db->quote($name['table']);
          return $this->db->query($sql)->fetchColumn(0) == 1;
         */
    }
    
    /**
     * Return true if the constraint exists
     * @access public
     * @param type $table
     * @param type $constraintName
     * @return boolean 
     */
    public function constraintExists($table, $constraintName)
    {
        $retval = false;
        
        $constraints = $this->getTableConstraintList($table);
        foreach ($constraints as $constraint) {
            if ($constraint['conname'] == $constraintName) {
                $retval = true;
            }
        }
        
        return $retval;
    }

    /**
     * Return if the view exists
     * @access public
     * @param string $name
     * @param string $schema
     * @return boolean
     */
    public function viewExists($name, $schema=null)
    {
        if ($schema !== null) {
            $name = "{$schema}.{$name}";
        }
        $name = $this->extractTableDesc($name);
        $sql = "SELECT count(*) FROM pg_views WHERE " .
                "schemaname=" . $this->db->quote($name['schema']) . " AND " .
                "viewname=" . $this->db->quote($name['table']);
        return $this->db->query($sql)->fetchColumn(0) == 1;
    }

    /**
     * Return if the field exists
     *
     * @access public
     * @param string $name table or schema.table
     * @param string $fieldName name of field
     * @return boolean
     */
    public function fieldExists($name, $fieldName)
    {
        $fieldDefinition = $this->getTableDefinition($name);
        foreach ($fieldDefinition as $field) {
            if ($fieldName == $field['column_name']) {
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * Return if sequence exists
     *
     * @access public
     * @param string sequence or schema.sequence
     * @param string schema
     * @return boolean
     */
    public function sequenceExists($sequence, $schema=null)
    {
        if (is_null($schema) && strpos('.', $sequence) !== true) {
            list($schema, $sequence) = explode('.', $sequence);
        }
        
        $params = array();
        $sql = "SELECT c.relname as sequence_name ".
            " FROM pg_catalog.pg_class c ".
            " LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace ".
            " WHERE c.relkind IN ('S','') ";
        
        if (!empty($schema)) {
            $sql .= " AND n.nspname = :schema ";
            $params['schema'] = $schema;
        } else {
            $sql .= " AND pg_catalog.pg_table_is_visible(c.oid) ".
                " AND n.nspname <> 'pg_catalog' ".
                " AND n.nspname <> 'information_schema' ".
                " AND n.nspname !~ '^pg_toast' ";
        }
        $sql .= " AND c.relname = :sequence ";
        $params['sequence'] = $sequence;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return ($stmt->rowCount() == 1);
    }

    public function createIndexDDL($schema, $table, $columns, $options = array())
    {
        $unique = '';
        if (isset($options['unique']) && $options['unique']) {
            $unique = " UNIQUE ";
        }
        $sql = "CREATE $unique INDEX ";
        $sql .= $table . "_" . implode('_', $columns) . "_uq \n";
        $sql .= "ON $schema.$table USING btree (" . implode(',', $columns) . ")";
        return $sql;
    }

    /**
     * Return the table definition DDL
     *
     * @param string         table or schema.table
     * @return array
     * @access public
     */
    public function getTableDefinitionDDL($name)
    {
        /*
          $table = strTolower($table);
          // TODO: if table has no schema part, should we then read the search_path?
          if (strpos($table, '.') === false) {
          // Search for temporary first!
          $schema = 'public';
          } else {
          list($schema, $table) = explode('.', $table);
          }
         */
        $name = $this->extractTableDesc($name);
        $sql = "SELECT " .
                "  column_name, column_default, is_nullable, " .
                "  CASE WHEN data_type = 'USER-DEFINED' THEN udt_name ELSE data_type END AS data_type, " .
                "  character_maximum_length, numeric_precision, numeric_scale, datetime_precision " .
                "FROM " .
                "  information_schema.columns " .
                "WHERE " .
                "  table_schema=" . $this->db->quote($name['schema']) . " AND " .
                "  table_name=" . $this->db->quote($name['table']) . " " .
                "ORDER BY " .
                "  ordinal_position";
        return $sql;
    }

    /**
     * Alias for getTableDefinitionDDL
     *
     * @deprecated use getTableDefinitionDDL
     * @param string $table
     * @return string
     */
    public function getTableDescDDL($table)
    {
        return $this->getTableDefinitionDDL($table);
    }

    /**
     * Return the table definition
     * @access public
     * @param string         table or schema.table
     * @return array
     */
    public function getTableDefinition($name)
    {
        $sql = $this->getTableDefinitionDDL($name);
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alias for getTableDefinition
     *
     * @deprecated use getTableDefinition
     * @param string $table
     * @return string
     */
    public function getTableDesc($table)
    {
        return $this->getTableDefinition($table);
    }

    /**
     * Set the client encoding
     *
     * @param string $encoding        a valid postgres encoding
     * @access public
     */
    public function setClientEncoding($encoding)
    {
        $sql = "SET client_encoding to '$encoding'";
        $this->db->exec($sql);
    }

    /**
     * Return the user list DDL
     *
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
// SS: Non static perchè potrebbe essere necessario quotare (per cui mi serve il db)
    public function getUserListDDL(array $opt=array())
    {
        return "SELECT rolname AS name FROM pg_catalog.pg_roles ORDER BY rolname";
    }

    /**
     * Return the user list
     *
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
    public function getUserList(array $opt=array())
    {
        $sql = $this->getUserListDDL();
        $res = $this->db->query($sql);
        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['name']] = $row['name'];
        }
        return $result;
    }

    /**
     * Return a single-user attribute SQL
     * @param string $name
     * return string
     */
    public function getUserDataDDL($name)
    {
        $name = $this->db->quote($name);
        $sql = "SELECT rolname, rolsuper, rolinherit, rolcreaterole, rolcreatedb, rolcanlogin, rolconnlimit, rolvaliduntil
                FROM pg_catalog.pg_roles
                WHERE rolname={$name}
                ORDER BY rolname";
        return $sql;
    }

    /**
     * Return a single-user attribute
     * @param string $name
     * return string
     */
    public function getUserData($name=null)
    {
        if ($name === null) {
            $name = $this->db->query("SELECT current_user")->fetchColumn(0);  // Current user
        }
        $sql = $this->getUserDataDDL($name);
        $res = $this->db->query($sql);
        while (($row = $res->fetch(PDO::FETCH_ASSOC))) {
            return $row;
        }
        return null;
    }

    /**
     * Return true if an user is superuser
     * @param string $name
     * return boolean
     */
    public function isSuperuser($name=null)
    {
        if ($name === null) {
            $name = $this->db->query("SELECT current_user")->fetchColumn(0);  // Current user
        }
        $sql = $this->getUserDataDDL($name);
        $res = $this->db->query($sql);
        while (($row = $res->fetch(PDO::FETCH_ASSOC))) {
            return $row['rolsuper'] == 't';
        }
        return null;
    }

    /**
     * Return if a user exists
     *
     * @param string $login      the user login
     * @param array $opt         options (not used now)
     * @return boolean
     * @access public
     */
    public function userExists($login, array $opt=array())
    {
        //SS: TODO: Use where instead of array
        return array_key_exists($login, $this->getUserList());
    }

    /**
     * Return the create user statement
     *
     * @param string $login      the user login
     * @param string $password   the user password
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
    public function createUserDDL($login, $password, array $opt=array())
    {
        return "CREATE USER $login LOGIN PASSWORD '$password' NOINHERIT VALID UNTIL 'infinity'";
    }

    /**
     * Create a new user
     *
     * @param string $login      the user login
     * @param string $password   the user password
     * @param array $opt         options (not used now. Values tip: privileges, dba, ecc)
     * @access public
     */
    public function createUser($login, $password, array $opt=array())
    {
        $sql = $this->createUserDDL($login, $password, $opt);
        $this->db->exec($sql);
    }

    /**
     * Return the schema list DDL
     *
     * @todo SELECT * FROM information_schema.schemata
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
    public function getSchemaListDDL(array $opt=array())
    {
        return "SELECT  nspname AS name " .
        "FROM pg_catalog.pg_namespace " .
        "WHERE (nspname !~ '^pg_temp_' AND " .
        "       nspname <> 'pg_catalog' AND " .
        "       nspname <> 'information_schema' AND " .
        "       nspname !~ '^pg_toast') " .
        "ORDER BY name";
    }

    /**
     * Return the schema list
     *
     * @access public
     * @param array $opt         options (not used now)
     * @return array
     */
    public function getSchemaList(array $opt=array())
    {
        $sql = $this->getSchemaListDDL();
        $res = $this->db->query($sql);
        $result = array();
        while (($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $result[$row['name']] = $row['name'];
        }
        return $result;
    }

    /**
     * Return if the schema exists
     *
     * @access public
     * @param string $name the schema name
     * @return boolean
     */
    public function schemaExists($name)
    {
        return array_key_exists($name, $this->getSchemaList());
    }

    /**
     * Return the create schema statement
     *
     * @param string $name       the schema name
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
    public function createSchemaDDL($name, array $opt=array())
    {
        $sql = "CREATE SCHEMA $name ";
        if (isset($opt['owner']) && $opt['owner'] <> '') {
            $sql .= "AUTHORIZATION {$opt['owner']} ";
        }
        return $sql;
    }

    /**
     * Create a new schema
     *
     * @param string $name       the schema login
     * @param array $opt         options (not used now. Values tip: privileges, dba, ecc)
     * @access public
     */
    public function createSchema($name, array $opt=array())
    {
        $sql = $this->createSchemaDDL($name, $opt);
        $this->db->exec($sql);
    }

    /**
     * Return the database list DDL
     *
     * @param array $opt         options (not used now)
     * @return string
     * @access public
     */
    public function getDatabaseListDDL(array $opt=array())
    {
        return "SELECT datname AS name FROM pg_catalog.pg_database WHERE datname not in ('postgres', 'postgis', 'template0', 'template1') ORDER BY name";
    }

    /**
     * Create a new user
     *
     * @param array $opt         options (not used now)
     * @access public
     */
    public function getDatabaseList(array $opt=array())
    {
        $sql = $this->getDatabaseListDDL($opt);
        $res = $this->db->query($sql);
        $result = array();
        while (($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $result[$row['name']] = $row['name'];
        }
        return $result;
    }
    
    /**
     * Get database owner query
     *
     * @param array $opt options (database required)
     * @access public
     */
    public function getDatabaseOwnerDDL(array $opt= array())
    {
        if (empty($opt['database'])) {
            throw new Exception('Missing option parameter database');
        }
        
        return "SELECT usename AS name FROM pg_catalog.pg_database INNER JOIN pg_catalog.pg_user ON datdba=usesysid WHERE datname=".$this->db->quote($opt['database']);
    }
    
    /**
     * Get database owner
     *
     * @param array $opt options (database required)
     * @access public
     */
    public function getDatabaseOwner(array $opt=array())
    {
        $sql = $this->getDatabaseOwnerDDL($opt);
        return $this->db->query($sql)->fetchColumn(0);
    }
    
    /**
     * Get schema owner query
     *
     * @param array $opt options (schema required)
     * @access public
     */
    public function getSchemaOwnerDDL(array $opt= array())
    {
        if (empty($opt['schema'])) {
            throw new Exception('Missing option parameter database');
        }
        
        return "SELECT usename AS name " .
               " FROM pg_catalog.pg_namespace " .
               " INNER JOIN pg_catalog.pg_user ON nspowner=usesysid " .
               " WHERE (nspname !~ '^pg_temp_' AND " .
               " nspname <> 'pg_catalog' AND " .
               " nspname <> 'information_schema' AND " .
               " nspname !~ '^pg_toast') AND " .
               " nspname=".$this->db->quote($opt['schema']);
    }
    
    /**
     * Get schema owner
     *
     * @param array $opt options (schema required)
     * @access public
     */
    public function getSchemaOwner(array $opt=array())
    {
        $sql = $this->getSchemaOwnerDDL($opt);
        return $this->db->query($sql)->fetchColumn(0);
    }

    public function getVersion()
    {
        // Versione database
    }

    /**
     * Return the unique index of a table DDL
     * @todo test user rights on it, eventually call table exists
     * @access public
     * @param string $table table or schema.table
     * @return string
     */
    public function getTableIndexListDDL($table)
    {
        $name = $this->extractTableDesc($table);
        return "SELECT c.relname, i.indisprimary, i.indisunique, pg_catalog.pg_get_indexdef(i.indexrelid, 0, true) " .
        "FROM pg_catalog.pg_class c" .
        "     JOIN pg_catalog.pg_roles r ON r.oid = c.relowner" .
        "     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace " .
        "     LEFT JOIN pg_catalog.pg_index i ON i.indexrelid = c.oid " .
        "     LEFT JOIN pg_catalog.pg_class c2 ON i.indrelid = c2.oid " .
        "WHERE n.nspname='{$name['schema']}' AND c2.relname = '{$name['table']}' " .
        "ORDER BY i.indisprimary DESC, i.indisunique DESC, c2.relname ";
    }

    /**
     * Return all indexes from the table
     * @todo test user rights on it, eventually call table exists
     * @access public
     * @param string $table table or schema.table
     * @return array
     */
    public function getTableIndexList($table, $opt=array())
    {
        $sql = $this->getTableIndexListDDL($table);
        
        $res = $this->db->query($sql);
        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            if ($row['indisprimary'] === true && $row['indisunique'] === true) {
                $result['primary'][$row['relname']] = $this->getIndexInformation($row['pg_get_indexdef']);
            } elseif ($row['indisprimary'] === false && $row['indisunique'] === true) {
                $result['unique'][$row['relname']] = $this->getIndexInformation($row['pg_get_indexdef']);
            } else {
                $result['index'][$row['relname']] = $this->getIndexInformation($row['pg_get_indexdef']);
            }
        }
        return $result;
    }

    /**
     * Return the table list DDL
     * @todo SELECT * FROM information_schema.tables
     * @access public
     * @param array $options
     * @return string
     */
    public function getTableListDDL(array $options = array())
    {
        $where = '';
        if (!empty($options['schema'])) {
            $where = " AND n.nspname=" . $this->db->quote($options['schema']) . " ";
            //$values[] = $options['schema'];
        }
        return "SELECT n.nspname AS schema_name, c.relname AS table_name
                FROM pg_catalog.pg_class c
                LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE
                  c.relkind IN ('r','') AND
                  n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
                {$where}
                ORDER BY schema_name, table_name";
    }

    /**
     * Return all the table of the system
     * @access public
     * @param array $options
     * @return array
     */
    public function getTableList($options = array())
    {
        $sql = $this->getTableListDDL($options);
        $res = $this->db->query($sql);
        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $identifier = "{$row['schema_name']}.{$row['table_name']}";
            $result[$identifier] = array('schema' => $row['schema_name'], 'table' => $row['table_name']);
        }
        return $result;
    }

    /**
     * Return the view list DDL
     * @access public
     * @param array $options
     * @return string
     */
    public function getViewListDDL(array $options = array())
    {
        $where = '';
        if (!empty($options['schema'])) {
            $where = " WHERE table_schema=" . $this->db->quote($options['schema']) . " ";
        }
        return "SELECT table_schema, table_name
                FROM information_schema.views
                {$where}
                ORDER BY table_schema, table_name";
    }

    /**
     * Return all the views of the system
     * @access public
     * @param array $options
     * @return array
     */
    public function getViewList($options = array())
    {
        $sql = $this->getViewListDDL($options);
        $res = $this->db->query($sql);
        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $result[] = array('schema' => $row['table_schema'], 'table' => $row['table_name']);
        }
        return $result;
    }

    public function getTableConstraintListDDL($table)
    {
        $name = $this->extractTableDesc($table);
        return "SELECT conname, consrc " .
        " FROM pg_constraint ch " .
        " INNER JOIN pg_class t ON ch.conrelid = t.oid " .
        " INNER JOIN pg_namespace n ON ch.connamespace = n.oid " .
        " WHERE ch.contype = 'c' AND " .
        "       n.nspname || '.' || t.relname='{$name['schema']}.{$name['table']}' " .
        " ORDER BY ch.conname ";
    }

    public function getTableConstraintList($table, array $opt=array())
    {
        $sql = $this->getTableConstraintListDDL($table);

        $res = $this->db->query($sql);
        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Returns the index information
     * @access private
     * @param string $indexDefinition
     * @return array
     */
    private function getIndexInformation($indexDefinition)
    {
        $info = array();
        //$pattern = "/(btree|rtree|hash|gist)\s\(([\s\w_,]+)\)/i";
        $pattern = "/CREATE(?:\sUNIQUE)? INDEX ([\s\w_,]+) ON ([\s\w_,\.]+) USING (btree|rtree|hash|gist)\s\(([\s\w_,]+)\)(\sWHERE\s(.*))?/i";
        if (preg_match($pattern, $indexDefinition, $a) > 0) {
            $info = array('name' => $a[1], 'type' => $a[3], 'fields' => preg_split('/\s*,\s*/', $a[4]));
            if (!empty($a[6])) {
                $info['constraint'] = $a[6];
            }
        }
        return $info;
    }
    
    public function getPrimaryKeys($table, array $opt=array())
    {
        $name = $this->extractTableDesc($table);
        
        $sql = <<<EOQ
SELECT
  pg_attribute.attname,
  format_type(pg_attribute.atttypid, pg_attribute.atttypmod)
FROM pg_index, pg_class, pg_attribute
WHERE
  pg_class.oid = '{$name['schema']}.{$name['table']}'::regclass AND
  indrelid = pg_class.oid AND
  pg_attribute.attrelid = pg_class.oid AND
  pg_attribute.attnum = any(pg_index.indkey)
  AND indisprimary
EOQ;
        $stmt = $this->db->query($sql);
        $columns = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['attname'];
        }
        return $columns;
    }

    /**
     * Return the foreign key list DDL
     * @todo test user rights on it, eventually call table exists
     * @access public
     * @param string $table table or schema.table
     * @return string
     */
    public function getTableForeignKeyListDDL($table)
    {
        $name = $this->extractTableDesc($table);
        return "SELECT conname, pg_catalog.pg_get_constraintdef(ch.oid, false) AS condef 
                FROM pg_catalog.pg_constraint ch 
                INNER JOIN pg_class t ON ch.conrelid=t.oid 
                INNER JOIN pg_namespace n ON ch.connamespace=n.oid 
                WHERE ch.contype='f' AND 
                      n.nspname='{$name['schema']}' AND t.relname='{$name['table']}' 
                ORDER BY ch.conname";
    }

    /**
     * Return the foreign key list
     * @todo test user rights on it, eventually call table exists
     * @access public
     * @param string $table
     * @param array $opt not used
     * @return array
     */
    public function getTableForeignKeyList($table, $opt=array())
    {
        $sql = $this->getTableForeignKeyListDDL($table);
        $res = $this->db->query($sql);

        $result = array();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['conname']] = $this->getForeignKeyInformation($row['condef']);
        }
        return $result;
    }

    /**
     * Returns the foreign key information
     * @todo add schema to foreign_table if missing
     * @access private
     * @param string $foreignKeyDefinition
     * @return array
     */
    private function getForeignKeyInformation($foreignKeyDefinition)
    {
        // check with quotes to support case sensitive schema and table names
        $pattern = "/FOREIGN KEY \(([a-zA-Z_0-9,\s]+)\) REFERENCES (\"?[a-zA-Z_0-9]+\.\"?)?(\"?[a-zA-Z_0-9]+\"?)\(([a-zA-Z_0-9,\s]+)\)/";
        $info = array();
        if (preg_match($pattern, $foreignKeyDefinition, $a)) {
            $info = array(
                'fields' => explode(',', str_replace(' ', '', $a[1])),
                //'foreign_table'=>($a[3] == '' ? $name['schema'] : $a[3]) . '.' . $a[4],
                'foreign_table' => ($a[2] != '' ? $a[2] : '') . $a[3],
                'foreign_fields' => explode(',', str_replace(' ', '', $a[4]))
            );
        }
        return $info;
    }
}
