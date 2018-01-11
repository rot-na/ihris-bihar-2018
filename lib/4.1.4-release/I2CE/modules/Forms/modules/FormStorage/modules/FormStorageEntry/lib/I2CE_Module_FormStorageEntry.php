<?php
/**
* © Copyright 2009 IntraHealth International, Inc.
* 
* This File is part of I2CE 
* 
* I2CE is free software; you can redistribute it and/or modify 
* it under the terms of the GNU General Public License as published by 
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
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @version v3.2.69
* @since v3.2.69
* @filesource 
*/ 
/** 
* Class I2CE_Module_FormStorageEntry
* 
* @access public
*/


class I2CE_Module_FormStorageEntry  extends I2CE_Module{


    /**
     * Method called when the module is enabled for the first time.
     * @param boolean -- returns true on success. false on error.
     */
    public function action_initialize() {
        I2CE::raiseError("Initializing Form Tables");
        if (! $this->addBlobValue()) {
            return false;
        }
        if (!I2CE_Util::runSQLScript('initialize_form.sql')) {
            I2CE::raiseError("Could not initialize I2CE form tables");
            return false;
        }
        if (! $this->addParentFormColumnToRecordTable()) { 
            //this is here if we are an upgrade from 3.1
            return false;
        }    
        if (! $this->addParentFormColumnToRecordTable('deleted_record')) { 
            //this is here if we are an upgrade from 3.1
            return false;
        }            
        return true;
    }

    private function addBlobValue() {
        $db = MDB2::singleton();
        $rows = $db->queryAll("SHOW FULL COLUMNS FROM entry WHERE Field='blob_value'");
        if(count($rows)) {
            I2CE::raiseError("entry table already has blob_value");
            return true;
        }
        I2CE::raiseError("Adding blob_value to entry tables");
        return I2CE_Util::runSQLScript('add_blob_fields.sql');
    }


    /**
     * Upgrade module method
     * @param string $old_vers
     * @param string $new_vers
     */
    public function upgrade($old_vers,$new_vers) {
        if (I2CE_Validate::checkVersion($old_vers,'<','3.2.69')) {            
            if (! $this->addParentFormColumnToRecordTable()) {
                return false;
            }
        }
        if (I2CE_Validate::checkVersion($old_vers,'<','4.0.2')) {            
            if (! $this->addParentFormColumnToRecordTable('deleted_record')) {
                return false;
            }
        }
        if (I2CE_Validate::checkVersion($old_vers,'<','4.0.3')) {            
            if (! $this->dropColumnFromTable('parent','record')) {
                return false;
            }            
            if (! $this->dropColumnFromTable('parent','deleted_record')) {
                return false;
            }            

        }
        if (I2CE_Validate::checkVersion($old_vers,'<','4.0.3.1')) {            
            I2CE::raiseError( "Creating new indexes.  This may take a long time if you have many records." );
            if (! $this->createIndexOnLastEntry('ff_string_value', array('form_field','string_value'))) {
                return false;
            }
            if (! $this->createIndexOnLastEntry('ff_integer_value', array('form_field','integer_value'))) {
                return false;
            }
        }

        return true;
    }



    /**
     * Drop a column from the indicated table
     *@param string $column
     *@param string $table
     */
    protected function dropColumnFromTable($column,$table) {
        $db = MDB2::singleton();
        $qry_check = "SHOW FULL COLUMNS FROM $table WHERE Field='$column'";
        $rows = $db->queryAll($qry_check);
        if(count($rows) == 0) {
            I2CE::raiseError("$table table does not have $column");
            return true;
        }
        $qry_drop = "ALTER TABLE `$table` DROP COLUMN `$column`";
        return (!(I2CE::pearError( $db->exec($qry_drop), "Error dropping $column from table $table:")));
    }

    /**
     * Checks to make sure there is the given index on the last_entry.  If it does not exist it adds it.
     * @param string $index_name 
     * @param mixed $fields.  Either a string or array of string, the fields we want to make an index on
     *
     * If the field names need backtics, you are required to provide them
     */
    protected function createIndexOnLastEntry($index_name,$fields) {
        if (is_string($fields)) {
            $fields = array($fields);
        }
        if (!is_array($fields) || count($fields) == 0) {
            I2CE::raiseError("Invalid fields");
            return false;
        }
        $db = MDB2::singleton();
        $qry =
        "SELECT  null FROM information_schema.statistics WHERE
table_schema = '{$db->database_name}'
and table_name = 'last_entry'
and index_name = '{$index_name}'";
        $result = $db->query($qry);
        if (I2CE::pearError($result,"Cannot execute  query:\n$qry")) {
            return false;
        }
        if ($result->numRows() > 0) {
            //the index has already been created.
            return true;
        }
        //the index has not been created.
        I2CE::longExecution(); //it may take a shilw
        I2CE::raiseError("Creating index '$index_name' on the field(s) " . implode(",",$fields) . " of  last_entry.  This may take a long time if you have many records.");
        $qry = "CREATE INDEX $index_name ON last_entry (" . implode(',',$fields) . ")";
        $result = $db->query($qry);
        if (I2CE::pearError($result,"Cannot execute  query:\n$qry")) {
            return false;
        }
        return true;
        
    }
    

    /**
     * Adds in the parent form column to the record table
     * @param string $table  the table to fixup the parent columns on.  Defaulst to 'record'
     */
    protected function addParentFormColumnToRecordTable($table = 'record') {
        $db = MDB2::singleton();
        $rows = $db->queryAll("SHOW FULL COLUMNS FROM $table WHERE Field='parent_id'");
        if(count($rows)> 0) {
            I2CE::raiseError("$table table already has parent_form");
        } else {
            $qry_alter = "ALTER TABLE $table ADD COLUMN `parent_id` int(10) unsigned default 0, ADD COLUMN `parent_form` varchar(255), ADD INDEX (`parent_form`,`parent`);";
            if ( I2CE::pearError( $db->exec($qry_alter), "Error adding parent_id, parent_form column to $table table:")) {
                return false;
            }
        }
        // If it's a brand new installation then parent doesn't exist so 
        // no update needs to happen.
        $rows = $db->queryAll("SHOW FULL COLUMNS FROM $table WHERE Field='parent'");
        if ( count($rows) > 0 ) {
            $qry_insert = "UPDATE $table r JOIN $table pr ON pr.id = r.parent JOIN  form pf ON pf.id = pr.form  SET r.`parent_id` = r.`parent` , r.`parent_form` = pf.name";
            if ( I2CE::pearError( $db->exec($qry_insert), "Error updating parent_id, parent_form columns:")) {
                return false;
            }        
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
