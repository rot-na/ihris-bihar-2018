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
 * @package I2CE
 * @author Luke Duncan <lduncan@intrahealth.org>
 * @since v3.2.0
 * @version v3.2.0
 */
/**
 * Class for defining all the database fields used by a {@link I2CE_Form} object.
 * @package I2CE
 * @access public
 */
class I2CE_FormField_MAP extends I2CE_FormField_MAPPED {


    /**
     * Hooked method to remap a given id on a given form and field
     * @param I2CE_List $lsit
     * @param string $oldid
     * @param string $newid
     */
    public function remapField($form,$oldid,$newid) {
        $where = array(
            'operator'=>'FIELD_LIMIT',
            'style'=>'equals',
            'field'=>$this->getName(),
            'data'=>array('value'=>"$form|$oldid")
            );

        $set_sql = "'" . mysql_escape_string($form .'|'  . $newid) . "'";
        $set_func = create_function('$val',"return '$form|$newid';");
        return $this->globalFieldUpdate($where,$set_func,$set_sql);
    }

    

    /**
     * Componentizes the given $db_value based on component
     * @param string $db_value.  The non-componentized value
     * @param array $forms of stirng. The form names which we wish to componentize.
     * @param string $component The component we wish to encode
     * @returns string The componentized db_value
     */
    public function getComponentizedValue($db_value,$forms,$component) {
        list ($form,$id) = array_pad(explode('|',$db_value,2),2,'');
        if (in_array($form,$forms)) {
            return $db_value . '@' . $component;
        } else {
            return $db_value;
        }
    }


    /**
     * Componentizes the given $db_value based on component
     * @param string $db_ref.  The reference to the data
     * @param array $forms of stirng. The form names which we wish to componentize.
     * @param string $component The component we wish to encode
     * @returns string The componentized db_value
     */
    public function getSQLComponentization($db_ref,$forms,$component) {
        return I2CE_List::componentizeQuery($db_ref,$forms,$component);
    }



    /**
     *get the default display style for the given type of display
     */         
    public function getDefaultDisplayStyle($type) {
        if (count($this->getDisplayedFields($type)) > 1) {                
            return 'tree';
        } else {
            return 'list';
        }
    }
    



    /**
     * Gets the default value from the field's data
     * @param array $fieldData
     * @returns array where the first element is boolean (true if the has been a default value set, false otherwise) and the 
     * second element is the default value to be set.
     */
    protected function getDefaultValue($fieldData) {
        $default_lookup = null;
        $value_set = false;
        $value = null;
        if (array_key_exists('default_lookup',$fieldData)  && is_string($fieldData['default_lookup']) && strlen($fieldData['default_lookup'])>0) {
            $default_lookup = $fieldData['default_lookup'];
            foreach ( $this->getMapOptions() as $data) {
                if (array_key_exists('display',$data) && $data['display'] == $default_lookup) {
                    return array(true,$data['value']);
                }
            }
            //if we made it here we did not find our lookup value
            I2CE::raiseError( "Lookup value $default_lookup not found for " . $this->getHTMLName());
            return array(false,null);
        }
        //nothing to lookup handle it with the parent
        return parent::getDefaultValue($fieldData);
    }


    /**
     * Return the value of this field from the database format for the given type.
     * @param mixed $value
     */
    public function getFromDB( $value ) {
        if (strpos($value,'|') !== false) {
            return  explode( "|", $value, 2 );
        } else {
            return false;
        }
    }

    /**
     * Sets the value of this field from the posted form.
     * @param array $post The $_POST array holding the values for this form.
     */
    public function setFromPost( $post ) {
        $val = $this->getFromDB( $post);
        if (is_array($val)) {               
            $this->value = $val;
        } else {
            $this->value = array("","");
        }
    }


    /**
     * Checks to see if the value has been set.
     * @return boolean
     */
    public function issetValue() {
        return is_array($this->value);
    }

    /**
     * Return the native value for this form field.
     * @return array
     */
    public function getValue() {
        if ( !$this->isSetValue() ) {
            return array( "", "" );
        }
        return $this->value;
    }
    
    /**
     * Get the form that this field maps to
     * @returns string.
     */
    public function getMappedForm() {
        if (!$this->isSetValue()) {
            return '';
        }
        return $this->value[0];
    }

    /**
     * Get the id of the form that this field maps to
     * If not set, returns 0.
     * @returns string
     */
    public function getMappedID() {
        if (!$this->isSetValue()) {
            return '0';
        }
        if ($this->value[1] == '') {
            return '0';
        }
        return $this->value[1];
    }

    /**
     * Return the DB value for this form field.
     * @return string
     */
    public function getDBValue() {
        if ( $this->isValid() ) {
            return implode( "|", $this->getValue() );
        } else {
            return "";
        }
    }

    /** 
     * Return the mapped form object.
     * @param boolean $populate 
     * @return I2CE_Form
     */
    public function getMappedFormObject( $populate = true ) { 
        $mapped_form = I2CE_FormFactory::instance()->createContainer( $this->getDBValue() );
        if (!$mapped_form instanceof I2CE_Form) {
            return null;
        }
        if ( $populate ) { 
            $mapped_form->populate();
        }   
        return $mapped_form;
    }   


    /**
     * Checks to see if the current value for this is set and valid.
     * @return boolean
     */
    public function isValid() {
        if ( !$this->isSetValue() ) {
            return false;
        }
        return count($this->value) == 2 && I2CE_Validate::checkString( $this->value[1] ) 
            && ( $this->canSelectAnyForm() || in_array($this->value[0],$this->getSelectableForms()));
    }


    /**
     * Returns the value of this field as a human readable format.
     * @param I2CE_Entry $entry If a I2CE_Entry object has been passed to this method then it will return the value for that entry assuming it's an 
     * entry for this field.
     * @return mixed
     */
    public function getDisplayValue( $entry=false ) {
        if ( $entry instanceof I2CE_Entry ) {
            $value = $entry->getValue();
        } else {
            if ( !$this->isValid() ) {
                return "";
            }
            $value = $this->getValue();
        }
        return I2CE_List::lookup( $value[1], $value[0] );
    }


    /**
     * Creates a drop down list of options.
     * @param I2CE_Template $template
     * @param DOMNode $node -- the node we wish to add the drop down list under
     * @param boolean $show_hidden.  Show the hidden members of the list, defaults to false.
     * @returns mixed DOMNode or an array of DOMNodes to add.
     */
    protected function create_DOMEditable_list($node, $template, $form_node,$show_hidden= false) {
        $add_limits = $this->getAdditionalLimits($template,$node,$form_node->getAttribute('limit'));
        $display_style = 'default';
        if ( $form_node->hasAttribute( 'display_style' ) ) {
            $display_style = $form_node->getAttribute('display_style');
        }
        $list = $this->getMapOptions($display_style,$show_hidden,true,$add_limits);
        $selected =$this->getDBValue();
        $selectNode = $template->createElement('select',array( 'name'=>$this->getHTMLName()));
        $attrs = array('id','class');
        foreach ($attrs as $attr) {
            if ($form_node->hasAttribute($attr)) { 
                $selectNode->setAttribute($attr,$form_node->getAttribute($attr));
            }                
        }
        $this->setElement($selectNode);
        if ( $form_node->hasAttribute( "blank" ) ) {
            $blank_text = $form_node->getAttribute( "blank" );
        } else {
            $blank_text = "Select One";
            I2CE::getConfig()->setIfIsSet( $blank_text, "/modules/forms/template_text/blank" );
        }
        $selectNode->appendChild( $template->createElement( "option", array( 'value' => '' ), $blank_text ) );
        foreach ($list as $d) {
            $attrs = array('value'=>$d['value']);
            if ($d['value'] == $selected) {
                $attrs['selected'] = 'selected';
            }            
            $selectNode->appendChild($template->createElement('option', $attrs, $d['display']));
        }
        $node->appendChild($selectNode);
    }




    /**
     *Check to see if the tree style is allowed.
     * @returns boolean
     */
    public function checkStyle_tree() {
        return I2CE_ModuleFactory::instance()->isEnabled('TreeSelect');
    }

    /**
     * Creates a selectable auto-suggest tree view of the options
     * @param I2CE_Template $template
     * @param DOMNode $node -- the node that requested this drop down
     * @param boolean $show_hidden.  Show the hidden members of the list, defaults to false.
     * @returns mixed DOMNode or an array of DOMNodes to add.
     */
    protected function create_DOMEditable_tree($node, $template, $form_node,$show_hidden =false) {
        $ele_name = $this->getHTMLName();
        $ele_id = 'tree:'.$ele_name;
        $tree_options = array();    
        if ($form_node->hasAttribute('treeOptions')) {
            $tree_options = $form_node->getAttribute('treeOptions');
            $form_node->removeAttribute('treeOptions');
        }
        $delayed = true;   //default behavior is delayed load
        if ($form_node->hasAttribute('delayed')) {
            $delayed = $form_node->getAttribute('delayed');
            $form_node->removeAttribute('delayed');
        }
        $auto_complete_options = array();
        if ($form_node->hasAttribute('autoCompleteOptions')) {
            $auto_complete_options = $form_node->getAttribute('autoCompleteOptions');
            $form_node->removeAttribute('autoCompleteOptions');
        }
        $add_limits = $this->getAdditionalLimits($template,$node,$form_node->getAttribute('limit'));
        $display_style = 'default';
        if ( $form_node->hasAttribute( 'display_style' ) ) {
            $display_style = $form_node->getAttribute('display_style');
        }
        $data = $this->getMapOptions($display_style,$show_hidden,false,$add_limits);
        $main = $template->createElement('span');        
        $node->appendChild($main);
        
        if ($this->isSetValue()) {
            $selected =  array(
                'value'=>$this->getDBValue(), 
                'display'=>I2CE_List::lookup($this->getMappedID(),$this->getMappedForm())
                );
        } else {
            $selected = array();
        }
        $template->addAutoCompleteInputTree($main, $ele_name, $ele_id, $selected,$data,$tree_options,$auto_complete_options,$delayed);        
        $nodes = $template->query('./input[@id="' . $ele_id . '_inputtree_display"]',$main);
        if ($nodes->length > 0) {
            $this->setElement($nodes->item(0));
        }        
    }


    /**
     * @param DOMNode $node
     * @param I2CE_Template $template
     * @param DOMNode $form_node
     * 
     */
    public function processDOMEditable_reportSelect($node,$template,$form_node) {
        $mf = I2CE_ModuleFactory::instance();
        if (!$mf->isEnabled('CustomReports-Selector')) {
            return false;
        }

        $style = 'default';
        if ($form_node->hasAttribute('show')) {
            //$style = $this->ensureEditStyle($form_node->getAttribute('show'));
            $style = $form_node->getAttribute('show');
        }
        $styles = array($style);
        if ($style != 'default') {
            $styles[] = 'default';
        }
        foreach ($styles as $style) {
            $edit_path = "meta/reportSelect/$style";
            $report_path = "$edit_path/reportView";
            if (!$this->optionsHasPath($edit_path) || ! is_array(  $options =$this->getOptionsByPath($edit_path) )) {
                continue;
            }
            if (!$this->optionsHasPath($report_path) || ! is_scalar(  $reportView = $this->getOptionsByPath($report_path) )) {
                continue;
            }    
            $report = false;
            $relationship = false;
            $form = false;
            I2CE::getConfig()->setIfIsSet($report,"/modules/CustomReports/reportViews/$reportView/report");
            if (!$report) {
                continue;
            }
            I2CE::getConfig()->setIfIsSet($relationship,"/modules/CustomReports/reports/$report/relationship");
            if (!$relationship) {
                continue;
            }
            $form = false;
            I2CE::getConfig()->setIfIsSet($form,"/modules/CustomReports/relationships/$relationship/form");
            if (!$form) {
                continue;
            }
            if (!$this->canSelectAnyForm()) {                
                $form_path = "meta/form";
                if (!$this->optionsHasPath($form_path) || ! is_array( $forms = $this->getOptionsByPath($form_path) )) {
                    continue;
                }
                if (!in_array($form,$forms)) {
                    continue;
                }
            }
            $printf_path = "meta/display/$form/$style/printf";
            $printfargs_path = "meta/display/$form/$style/printf_args";
            if (!$this->optionsHasPath($printf_path) || ! is_string( $printf = $this->getOptionsByPath($printf_path) )) {
                continue;
            }
            if (!$this->optionsHasPath($printfargs_path) || ! is_array( $printfargs = $this->getOptionsByPath($printfargs_path) )) {
                continue;
            }
            if (strlen($printf)== 0) {
                continue;
            }
            if (count($printfargs) == 0) {
                continue;
            }
            ksort($printfargs);
            $disp = $this->_getDisplayValue(null,$style);
            $node->setAttribute('id',$this->getHTMLName());
            $options = array(
                'reportview'=>$reportView,
                'printf'=>$printf,
                'printfargs'=>$printfargs,
                'display'=>$disp,
                'value'=>$this->getDBValue()
                );
            if ( $this->getOption('required') ) {
                $options['allow_clear'] =false;
            }
            $no_limits_path = "meta/display/$form/$style/no_limits";
            if ($this->optionsHasPath($no_limits_path)  && $this->getOptionsByPath($no_limits_path)) {
                $options['contentid']='report_results';
            } else {
                $options['contentid']='report_results_with_limits';
            }
            if ($template->addReportSelector($node,$options)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the value of this field as a human readable format.
     * @param I2CE_Entry $entry If a I2CE_Entry object has been passed to this method then it will return the value for that entry assuming it's an 
     * entry for this field.
     * @param string $style.  Defaults to 'default'
     * @return mixed
     */
    public function _getDisplayValue( $entry=false, $style='default' ) {
        if ( $entry instanceof I2CE_Entry ) {
            $value = $entry->getValue();
        } else {
            $value = $this->getValue();
        }
        if (!is_array($value) || count($value) != 2) {
            return '';
        }
        list($form,$id) = $value;
        if ( $id == '0' || $id == '') {
            return '';
        }
        if (!$form) {
            return '';
        }
        $formid = "$form|$id";
        $ff = I2CE_FormFactory::instance();
        $formObj = $ff->createForm($formid);
        if (!$formObj instanceof I2CE_Form) {
            return '';
        }
        $formObj->populate();
        if (!$this->isValidForm($form)) {
            return $formid;
        }
        $style =  $this->ensureStyle($style);                
        $styles = array($style);
        if ($style != 'default') {
            $styles[] = 'default';
        }
        foreach ($styles as $s) {
            $printf_path = "meta/display/$form/$s/printf";
            $printfargs_path = "meta/display/$form/$s/printf_args";
            if (!$this->optionsHasPath($printf_path) || ! is_string( $printf = $this->getOptionsByPath($printf_path) )) {
                continue;
            }
            if (!$this->optionsHasPath($printfargs_path) || ! is_array( $printfargs = $this->getOptionsByPath($printfargs_path) )) {
                continue;
            }
            if (strlen($printf)== 0) {
                continue;
            }
            if (count($printfargs) == 0) {
                continue;
            }
            ksort($printfargs);
            $printfvals = array();
            foreach ($printfargs as $printfarg) {
                if (! ($fieldObj = $formObj->getField($printfarg)) instanceof I2CE_FormField) {
                    $printfvals[] = '';
                    continue;
                }
                $printfvals[] = $fieldObj->getDisplayValue();
            }
            return  @vsprintf($printf , $printfvals );
                        
        }
        //if we made it here, we have no valid displays.  try to do something.
        return $formid;
    }



}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
