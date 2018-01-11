<?php
/**
 * @copyright © 2007, 2008, 2009 Intrahealth International, Inc.
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
 ** The module that adds in an image data type
 * @package I2CE
 * @access public
 * @author Luke Duncan <lduncan@intrahealth.org>
 * @since v3.1.0
 * @version v3.1.0
 */

/*
 *The module meta data
Module Name: Float
Module Version: 1.0
Module URI: http://www.capacityproject.org
Description:  Module to handle float form fields
Author: Luke Duncan
Author Email:lduncan@intrahealth.org
*/


class I2CE_Module_Float extends I2CE_Module {
     public function upgrade($old_vers,$new_vers) {
        if (I2CE_Validate::checkVersion($old_vers,'<','4.1.9.2')) {  
              foreach (array('entry','last_entry') as $table) {
                   $qry = "ALTER TABLE $table CHANGE `float_value` `float_value` decimal(10,2) NULL DEFAULT NULL ;";  
                    $result = $this->db->exec( $qry);
                    if (I2CE::pearError($result, "Could not change to decimal data type in float_value column of $table" )) {
                        return false;
                    }
                     
              }
        }
        return true;
     }
    
    /**                                                                                                                                                                                          
     * @var MDB2 The instance of the database to perform queries on.                                                                                                                             
     */
    private $db;
    public function __construct() {
        parent::__construct();
        $this->db = MDB2::singleton();
        I2CE::pearError( $this->db, "Error getting database connection: " );
    }
    /**                                                                                                                                                                                          
     * Make sure the float column is in the database in the entry/last entry tables.                                                                                                             
     */
    public function action_initialize() {
        //check to see that the large blobs are there.                                                                                                                                           
        foreach( array('entry','last_entry') as $table ) { 
            $qry_show = "SHOW COLUMNS FROM $table LIKE '%_value'"; 
            $qry_alter = "ALTER TABLE $table ADD COLUMN `float_value` decimal(10,2)"; 
            $results = $this->db->query( $qry_show );
            if ( I2CE::pearError( $results, "Error getting columns from $table table: on {$qry_show}" ) ) {
                return false;
            }
            $found = false;
            while( $row = $results->fetchRow() ) {
                if ($row->field == 'float_value') {
                    $found = true;
                }
            }
            if (!$found) {
                //add the blob column to last_entry                                                                                                                                              
                if ( I2CE::pearError( $this->db->exec($qry_alter), "Error adding float column to $table:")) {
                    return false;
                }
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
