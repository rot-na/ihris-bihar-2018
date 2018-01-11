<?php
/**
 * @copyright © 2007, 2009 Intrahealth International, Inc.
 * This File is part of I2CE
 *
 * I2CE is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
*  I2CE_CachedForm
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @version 2.1
* @access public
*/


class I2CE_CachedForm extends I2CE_Fuzzy{
    /**
     * @var protected string $form The form we are caching
     */
    protected $form;

    /**
     * @var protected  string $database  the database name (unquoted)
     */ 
    protected $database;
    /**
     * @var protected  string $table_name  the table name for this form.
     */ 
    protected $table_name;
    /**
     * @var protected  string $short_table_name  the table name for this form without quotes and without the databse
     */ 
    protected $short_table_name;
    /**
     * @var protected  string $last_entry_database  the database name (unquoted) where last_entry is
     */ 
    protected $last_entry_database;


    /**
     * @var protected I2CE_Form $formObj   An instance of the form object
     */
    protected $formObj;

    /**
     * @var protected I2CE_FormStorage_Mechanism $formMech   An instance of the form storage mechansim for the form
     */
    protected $formMech;


    /**
     * The constructor
     * @param string $form  The form we wish to cash into a table
     */
    public function __construct($form) {
        $this->form = $form;
        $factory = I2CE_FormFactory::instance();
        if (!$factory->exists($form)) {
            $msg = "Trying to cache form $form, but the form does not exist";
            I2CE::raiseError($msg);
            throw new Exception($msg);
        }
        $this->formObj = $factory->createContainer($this->form);
        if (!$this->formObj instanceof I2CE_Form) {
            $msg = "Cannot instantiate {$this->form}";
            I2CE::raiseError($msg);
            throw new Exception($msg);
        }
        $this->formMech = I2CE_FormStorage::getStorageMechanism($form);
        if (!$this->formMech instanceof I2CE_FormStorage_Mechanism) {
            $msg = "Cannot get storage mechansim for form $form";
            I2CE::raiseError($msg);
            throw new Exception($msg);
        }
        $this->short_table_name = $this->getCachedTableName($form,false);
        $this->table_name = $this->getCachedTableName($form,true);
        $this->tmp_table_name = $this->getCachedTableName($form,true , 'tmp_cached_form');
    }




    /**
     * Get the id's of the cached forms.
     */
    public function getIDs() {
        $ids = array();
        $qry = "SELECT id from {$this->table_name}";
        $results =$db->query($qry);
        if (I2CE::pearError($results,"Cannot access database:\n$qry")) {
            return $ids;
        }
        while($result = $results->fetchRow) {
            $ids[] = $result->id();
        }
        return $ids;
    }

    /**
     * Get the name of the database that the cached tables are stored in.
     * @returns string The string may be empty meaning that we are using the database for the DB connection
     */
    public static function getCacheDatabase() {
        $db_name = '';
        I2CE::getConfig()->setIfIsSet($db_name,"/modules/CachedForms/database_options/database");        
        if ( !$db_name || $db_name == "" ) {
            $db_name = MDB2::singleton()->database_name;
        }
        return $db_name;
    }

    /**
     * Get the name of the cached table for the specfiied form.
     * @param string $form
     * @param boolean $withDB defaults to true.  If true we return the table in the form `database_name`.`table_name`.  Otherwise
     * we return simplt table_name
     * @returns string
     */
    public static function getCachedTableName($form,$withDB = true , $table_prefix = '') {        
        $db_name = '';
        if ($withDB) {
            $db_name = self::getCacheDatabase();
            if (strlen($db_name) > 0) {
                $db_name = '`' . $db_name . '`.';
            }
        }
        if (!$table_prefix) {
            $table_prefix = '';
            $DBConfig = I2CE::getConfig()->setIfIsSet($table_prefix,"/modules/CachedForms/database_options/table_prefix");        
        }
        if (strlen($table_prefix) > 0) {
            if ($table_prefix[strlen($table_prefix)-1] !== '_') {
                $table_prefix .= '_';
            }
        }     
        if ($withDB) {
            return            $db_name . '`' .  $table_prefix . $form  . '`';
        } else {
            return $table_prefix . $form;
        }
    }



    /**
     * Get the last time that this form was chached
     */
    public function getLastCachedTime() {
        $timeConfig = I2CE::getConfig()->traverse("/modules/CachedForms/times/generation");
        if (!$timeConfig instanceof I2CE_MagicDataNode) {
            return 0;
        }
        $timeConfig->volatile(true);
        $generation_time = 0;
        $timeConfig->setIfIsSet($generation_time,$this->form);
        return $generation_time;
    }

    /**
     *Get the time the cached form is considered stale
     * @returns int the number of seconds for this form to be considered stale.  if 0 it is considered to be always stale
     */
    public function getStaleTime() {
        $timeConfig = I2CE::getConfig()->modules->CachedForms->times;
        $stale_time  = 10; 
        $timeConfig->setIfIsSet($stale_time,"stale_time");
        if (is_integer($stale_time) ||  (is_string($stale_time) && ctype_digit($stale_time))) {
            if ($stale_time <= 0) {
                return 0;
            } 
        } else {
            $stale_time = 10;
        }
        //lookup storage mechanism stale time and override if necc.
        $t_stale_time = null;
        if ($timeConfig->setIfIsSet($t_stale_time,"stale_time_by_mechanism/" . I2CE_FormStorage::getStorage($this->form))) {
            if (is_integer($t_stale_time) ||  (is_string($t_stale_time) && ctype_digit($t_stale_time))) {
                if ( $t_stale_time > 0 ) {
                    $stale_time = $t_stale_time;
                } else {
                    return 0;
                }
            }
        }
        //lookup form stale time and override if  necc.
        $t_stale_time = null;
        if ($timeConfig->setIfIsSet($t_stale_time,"stale_time_by_form/{$this->form}")) {
            if (is_integer($t_stale_time) ||  (is_string($t_stale_time) && ctype_digit($t_stale_time))) {
                if ( $t_stale_time > 0 ) {
                    $stale_time = $t_stale_time;
                } else {
                    return 0;
                }
            }
        }
        $stale_time = $stale_time * 60; //convert from minutes
        return $stale_time;
    }
    
    /**
     * Checks to see if the cached table is stale
     * @returns boolean
     */
    public function isStale() {
        $cache_status = '';
        $config =   I2CE::getConfig()->modules->CachedForms;
        $config->status->volatile(true);
        $cache_status = $config->status->{$this->form};
        if ($cache_status == 'in_progress') {
            return false;
        }
        if (!$this->tableExists()) {
            return true;
        }
        $generation_time = $this->getLastCachedTime();
        if ( $generation_time <= 0) {
            return true;
        }
        if ($generation_time > time()) { //make sure there is no time sync. problem
            I2CE::raiseError("You have a time-sync problem");
            return true;
        }
        //set/lookup default stale time for forms 
        $stale_time = $this->getStaleTime();
        if ($stale_time <= 0) {  //always considered stale
            return true;
        }
        return  (($generation_time + $stale_time) < time()); 
    }

    /**
     * Drops the existing cached table from the database
     * @returns boolean
     */
    public function dropTable()  {
        $qry = "DROP TABLE IF EXISTS " . $this->table_name ;
        $db =  MDB2::singleton();
        $result =$db->query($qry);
        if (I2CE::pearError($result,"Cannot access database:\n$qry")) {
            return false;
        }
        $timeConfig = I2CE::getConfig()->traverse("/modules/CachedForms/times/generation/{$this->form}",false,false);
        if ($timeConfig instanceof I2CE_MagicDataNode) {
            $timeConfig->erase();
        }
        return true;
    }

    /**
     * Check to see if the cached table for this table exists and has the the proper fields for its columns.  If it is invalud, it will
     * drop the table.
     * @return boolean
     */
    public function tableExists() {
        if ($this->database) {
            $qry = "SHOW TABLES FROM " . $this->database . " LIKE '" . $this->short_table_name . "'";
        } else {
            $qry = "SHOW TABLES  LIKE '" . $this->short_table_name . "'";
        }
        $db =  MDB2::singleton();
        $result =$db->query($qry);
        if (I2CE::pearError($result,"Cannot access database:\n$qry")) {
            return false;
        }
        if ($result->numRows() == 0) {
            return false;
        }
        $qry = "SHOW COLUMNS FROM ". $this->table_name  ;
        $results =$db->query($qry);
        if (I2CE::pearError($results,"Cannot access database:\n$qry")) {
            return false;
        }
        $factory = I2CE_FormFactory::instance();
        $field_defs = array();
        foreach ($this->formObj as $field=>$fieldObj) {            
            $field_defs[$field] = $fieldObj->getDBType();  //we really should be checking that the column types are correct.
        }
        $special = array();
        while ( $row = $results->fetchRow()) {
            $field = $row->field;
            if ($field == 'id' || $field=='parent' || $field =='last_modified') {
                $special[$field] = true;
                continue;
            }
            $fieldObj = $this->formObj->getField($field);
            if (!$fieldObj instanceof I2CE_FormField) {
                I2CE::raiseError("The form field, {$this->form}:$field, is present in the cached table but is not a valid I2CE_FormField");
                $this->dropTable();
                return false;
            }
            if (!$fieldObj->isInDB()) {
                I2CE::raiseError("The form field, {$this->form}:$field, is present in the cached table but is not supposed to be saved to the cached table");
                $this->dropTable();
                return false;
            }
            if (!array_key_exists($field,$field_defs)) {
                I2CE::raiseError("Field $field present in cached table but not the form");
                $this->dropTable();
                return false;
            }
            unset($field_defs[$field]);
        }
        if (count($special) !== 3) {
            I2CE::raiseError("Could not find id, parent, or last_modified");
            $this->dropTable();
            return false;
        }
        foreach ($field_defs as $field=>$def) {
            $fieldObj = $this->formObj->getField($field);
            if (!$fieldObj instanceof I2CE_FormField) {
                I2CE::raiseError("The form field, {$this->form}:$field,  is not a valid I2CE_FormField");
                return false;
            }
            if (!$fieldObj->isInDB()) {
                unset($field_defs[$field]);
            }
        }
        if (count($field_defs) > 0) {
            I2CE::raiseError("The fields  "  . implode(',',array_keys($field_defs)) . " are not present in the existing cached table");
            $this->dropTable();
            return false;
        }
        return true;
    }


    /**
     * Generates the cahced table for the form
     * @param boolean $check_stale.  Defaults to true.  If false, it skips the staleness check
     * @param boolean $check_dirty.  Defaults to true.  If false, it skips the dirtiness check
     */
    public function generateCachedTable($check_stale = true, $check_dirty = true) {
        $time = time();        
        if ($check_stale && !$this->isStale()) {
            //I2CE::raiseMessage("Skipping cached table for {$this->form} as it is not stale" );
            return true;
        }
        if ($this->tableExists()){ 
            if ( $check_dirty && !I2CE_Module_CachedForms::formIsDirty($this->form)) {
                //I2CE::raiseMessage("Skipping cached table for {$this->form} as it is not dirty" );
                return true;            
            }
            I2CE::raiseMessage("The form {$this->form} is dirty" );
        }
        I2CE::getConfig()->modules->CachedForms->status->{$this->form} = 'in_progress';
        if (!$this->tableExists() && !$this->createCacheTable()) {
            return false;
        }
        I2CE::raiseError("Populating fields for {$this->form}");
        if ($this->formMech instanceof I2CE_FormStorage_DB) {
            if (!$this->fastPopulate($check_dirty)) {
                return false;
            }
        } else {
            if (!$this->slowPopulate($check_dirty)) {
                return false;
            }
        }
        I2CE::raiseMessage("Populated {$this->form} at $time:" . date('r',$time) .  "\n");
        I2CE::getConfig()->modules->CachedForms->times->generation->{$this->form} = $time;
        I2CE::getConfig()->modules->CachedForms->status->{$this->form} = 'done';
        I2CE_Module_CachedForms::markFormClean($this->form,$time); //will mark the form as clean if the dirty timestamp  does not exceed time
        return true;
    }

    /**
     * Method used to populate the cache table in case the form storage mechanism is  DB like
     * @param boolean $check_mod.  Defaults to true.  If false, it skips the mod time check
     */
    protected function fastPopulate($check_mod=true) {
        $fields = array();
        foreach ($this->formObj as $field=>$fieldObj) {
            if (!$fieldObj->isInDB()) {
                continue;
            }
            $fields[] = $field;
        }
        $insert_fields = $fields;
        $insert_fields[] ='id';
        $insert_fields[] = 'parent';
        $update_fields = array();
        foreach ($insert_fields as &$field) {
            $field = '`' . $field . '`';
            $update_fields[] = "$field=values($field)";
        }
        $callback = @create_function('$a,$b','return "`" . $b . "`";');
        if (!$callback) {
            I2CE::raiseError("Could not create callback reference");
            return false;
        }
        if ($check_mod) {
            $mod_time = $this->getLastCachedTime();
        } else {
            $mod_time = 0;
        }
        $select = $this->formMech->getRequiredFieldsQuery($this->form,$fields,null,true, $callback, $mod_time);
        if (!$select) {
            I2CE::raiseError("Could not get required fields for {$this->form}");
            return false;
        }
        $fields = array_diff($fields, array('parent','id'));
        $select = "SELECT  concat('{$this->form}|',id) as id, parent , `last_modified`, `" . implode('`,`',$fields) . "` FROM ($select) AS cached_table";
        $insertQry = 'INSERT INTO ' . $this->table_name . '(id,parent,`last_modified`,`' . implode( '`,`', $fields) . '`) ('  .  $select    .") ON DUPLICATE KEY UPDATE " . implode(',',$update_fields) ;
        I2CE::raiseError("Fast Populate Query:$insertQry\n");
        $res = MDB2::singleton()->exec($insertQry);
        if (I2CE::pearError( $res, "Could not populate cach for {$this->form}:")) {
            return false;
        }
        I2CE::raiseError("Updated $res records for {$this->form}");
        return true;
    }


    /**
     * Method used to populate the cache table in case the form storage mechanism is not DB like
     * @param boolean $check_mod.  Defaults to true.  If false, it skips the mod time check
     */
    protected function slowPopulate($check_mod= true) {
        $fields = array();
        $mdb2_types = array();
        foreach ($this->formObj as $field=>$fieldObj) {
            if (!$fieldObj->isInDB()) {
                continue;
            }
            $fields[] = $field;
            $mdb2_types[] = $fieldObj->getMDB2Type();
        }
        $insert_fields = $fields;
        $insert_fields[] = 'last_modified';
        $fields[] = 'last_modified';
        $insert_fields[] = "id";
        $insert_fields[] = 'parent';
        $update_fields = array();
        foreach ($insert_fields as &$field) {
            $field = '`' . $field . '`';
            $update_fields[] = "$field=values($field)";
        }
        unset($field);
        $insertQry = 'INSERT INTO ' . $this->table_name .   " (" . implode(',',$insert_fields) . " ) "
            ." VALUES (" . implode(',',array_fill(0,count($insert_fields),'?')) . ")" 
            ." ON DUPLICATE KEY UPDATE " . implode(',',$update_fields) ;
        I2CE::raiseError("Slow populate:\n$insertQry");
        $db = MDB2::singleton();
        $prep = $db->prepare($insertQry, $mdb2_types, MDB2_PREPARE_MANIP);
        if (I2CE::pearError( $prep, "Error setting up form in the database:" )) {
            return false;
        }
        if ($check_mod) {
            $mod_time = $this->getLastCachedTime();
        } else {
            $mod_time = 0;
        }
        $list = I2CE_FormStorage::listFields($this->form,$fields,true, array(), array(), false,$mod_time);
        $count = 0;
        foreach ($list as $id=>$data) {
            $count++;
            $t_data = array();
            foreach ($fields as $field) {
                if (array_key_exists($field,$data)) {
                    $t_data[] = $data[$field];
                } else {
                    $t_data[] = null;
                }
            }
            $t_data[] = $this->form . "|" . $id;
            if (array_key_exists('parent',$data)) {
                $t_data[] = $data['parent'];
            } else {
                $t_data[] = null;
            }
            $res = $prep->execute($t_data);
            if ( I2CE::pearError( $res, "Error insert into cache table:" ) ) {
                return false;
            }            
        }
        I2CE::raiseError("Populate $count entries for {$this->form}");
        return true;
    }




    /**
     * setup of the queries used to create and populate the cached table
     * @returns boolean.  True on success, false on error
     */
    protected function createCacheTable() {
        I2CE::raiseError("(Re)Creating cached table schema for {$this->form} as it either does not exist or is out of date");
        $timeConfig = I2CE::getConfig()->traverse("/modules/CachedForms/times/generation/{$this->form}",false,false);
        if ($timeConfig instanceof I2CE_MagicDataNode) {
            $timeConfig->erase();
        }
        $createFields = array('`id` varchar(255) NOT NULL', 'PRIMARY KEY  (`id`)'); 
        $createFields[] = '`parent` varchar(255) default "|" ';
        $createFields[] = 'INDEX (`parent`)';
        $createFields[] = '`last_modified` datetime default NULL' ;
        $createFields[] = 'INDEX (`last_modified`)';
        $field_defs = array();
        foreach ($this->formObj as $field=>$fieldObj) {
            if (!$fieldObj->isInDB()) {
                continue;
            }
            $createFields[] = '`' . $field . '` ' . $fieldObj->getDBType();  
            if ($fieldObj instanceof I2CE_FormField_MAPPED) {
                $createFields[] = 'INDEX (`' . $field . '`) ';
            }
        }
        $createQuery =  "CREATE TABLE  " . $this->table_name ." ( "  .  implode(',', $createFields) . ")  ENGINE=InnoDB DEFAULT CHARSET=utf8  DEFAULT COLLATE=utf8_bin";        
        I2CE::raiseError("Creating table for {$this->form} as:\n$createQuery");
        $db =  MDB2::singleton();
        $result =$db->query($createQuery);
        if (I2CE::pearError($result,"Cannot create cached table for {$this->form}:\n$createQuery")) {
            return false;
        }
        return true;
    }



  }
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
