<?php
/**
 * @copyright © 2008, 2009 Intrahealth International, Inc.
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
*  I2CE_CustomRelationship
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @version 2.1
* @access public
*/


class I2CE_FormRelationship extends I2CE_Fuzzy {



    protected $primaryForm;
    protected $primaryFormName;
    protected $parentFormNames;
    protected $formConfigs;
    protected $relationship;
    protected $relConfig;
    protected $form_reference;


    /**
     * @param string $relationship
     * @param callback $form_reference
     */
    public function __construct($relationship, $relationship_base = '/modules/CustomReports/relationships', $form_reference = null) {
        $this->relationship = $relationship;
        $this->relConfig = I2CE::getConfig()->traverse("$relationship_base/{$this->relationship}",false,false);
        if (!$this->relConfig instanceof I2CE_MagicDataNode) {
            throw new Exception("Relationship $relationship does not exist under $relationship_base");
        }
        $this->form_reference = $form_reference;
        $this->primaryFormName = $this->relConfig->getName();
        if (!$this->relConfig->setIfIsSet($this->primaryForm,'form')) {
            throw new Exception("No primary form set for $relationship");
        }
        $formConfigs = array(0=>array('primary_form'=>$this->relConfig));
        $this->parentFormNames = array('primary_form' => 'primary_form', $this->relConfig->getName()=>'primary_form');        
        $this->getForms($this->relConfig,$formConfigs, $this->parentFormNames, 'primary_form');
        ksort($formConfigs);
        $this->formConfigs = array();
        foreach  ($formConfigs as $configs) {
            foreach ($configs as $fn=>$conf) {
                $this->formConfigs[$fn] = $conf;
            }
        }
    }



    /**
     * Returns the form for a given named form in the relationship
     * @param string $formname
     * @returns mixed. A string, the form, on success. false on failure
     */
    public function getForm($formname) {
        $form = false;
        if (!is_string($formname)) {
            return false;
        }
        if (array_key_exists($formname,$this->formConfigs)) {
            $this->formConfigs[$formname]->setIfIsSet($form,'form');
        }
        return $form;
    }
    
    /**
     * Gets the forms required by the relationship
     * @returns array of string
     */
    public function getRequiredForms() {
        $forms = array();
        foreach ($this->formConfigs as $formName=>$formConfig) {
            $form = null;
            if ($formConfig->setIfIsSet($form,'form')) {                
                $forms[] = $form;
            }
            //now we need to see if there are any linked fields:
            if ($formName == 'primary_form') {
                continue;
            }
            $joinStyle = false;
            $formConfig->setIfisSet($joinStyle,"join_style");
            if (!$joinStyle) {
                I2CE::raiseError("No join style specfied for $formName");
                continue;
            }
            switch ($joinStyle) {
            case  'parent_field':
                $joinData = array();
                $formConfig->setIfIsSet($joinData,"join_data",true);
                if (!array_key_exists('field',$joinData) || !$joinData['field']) {
                    continue 2;
                }                    
                if ( ($parentFormName = $this->getParentFormNames($formName)) === false) {
                    continue 2;
                }
                $style = 'default';
                if (array_key_exists('displaystyle',$joinData) && $joinData['displaystyle']) {
                    $style = $joinData['displaystyle'];
                }
                if ( ($linking_data = $this->getLinkingData($parentFormName,$joinData['field'], $formName, 'id', $style)) == false) {
                    continue 2;                    
                }
                $forms = array_merge($forms,$linking_data['forms']);
                break;
            default: 
                continue 2;
            }            
        }
        return array_unique($forms);
    }





    /**
     * Internal method to walk the relationship and get all the form configs and parent form names and store them in  arrays
     * @param I2CE_MagicDataNode $config.  The node we are walking
     * @param array $formConfigs  The array we are storing magic data nodes.  Indexed by named forms
     * @param array $parentForms The array of string which maps the named parent form of a named form
     * @param string $parent_form_name.  The name of the parent form for the given $config
     */
    protected function getForms($config, &$formConfigs, &$parentForms, $parent_form_name, $depth = 0) {
        if (!$config->is_parent('joins')) {
            return;
        }
        if (!array_key_exists($depth,$formConfigs)) {
            $formConfigs[$depth] = array();
        }
        foreach ($config->joins as $childForm=>$childConfig) {
            $formConfigs[$depth][$childForm] = $childConfig;
        }
        foreach ($config->joins as $childForm=>$childConfig) {
            $parentForms[$childForm] = $parent_form_name;
            $this->getForms($childConfig,$formConfigs, $parentForms, $childConfig->getName(), $depth+1);
        }
    }


    /**
     * Get the magic data node(s) for the name form in the relationship
     * @param string $form.
     * @returns mixed. false on failture.  If $forname is null, we return an array of I2CE_MagicDataNodes index by the form names.  If $formma,e is a string then we return 
     * the  config for the named form.
     */
    public function getFormConfig($formname = null) {
        if ($formname === null) {
            return $this->formConfigs;
        } else {
            if (array_key_exists($formname,$this->formConfigs)) {
                return $this->formConfigs[$formname];
            } else {
                return false;
            }
        }
    }


    /**
     * Get the name(s) of parent forms
     * @param string $formname.  
     * @returns mixed. If $formname is  null we return an array index by relationship form names of all the parent forms.  If $formname is a string, it is the parentform name, if it exists of false otherwise. 
     */
    public function getParentFormNames($formname = null) {
        if ($formname === null) {
            return $this->parentFormNames;
        } else {
            if (array_key_exists($formname,$this->parentFormNames)) {
                return $this->parentFormNames[$formname];
            } else {
                return false;
            }
        }
    }

    /**
     * Get the primary form 
     * @returns string
     */
    public function getPrimaryForm() {
        return $this->primaryForm;
    }

    /**
     * Gets the name of the primary from (e.g. the  name of the relationship)
     * @returns string
     */
    public function getPrimaryFormName() {
        return $this->primaryFormName;
    }
    /**
     * Check to see if the given form name is the primary form name.  Note the primary form name is the same as the relationship name
     * @param string $formname
     * @returns boolean
     */
    public function isPrimaryFormName($formname) {
        return ($this->primaryFormName === $formname);
    }


    /**
     * Get the names of the forms in the relationship
     * @returns array of string.
     */
    public function getFormNames() {
        return array_keys($this->formConfigs);
    }


    /**
     * Determine if we should consider a relationship to not be satisifed if there is no form for the given named form
     * @param string $formName
     * @returns boolean
     */ 
    public function limitOne($formName) {        
        if ( ! ($formConfig = $this->getFormConfig($formName)) instanceof I2CE_MagicDataNode) {
            return true;
        }
        //defaults to true
        if (!$formConfig->is_scalar('limit_one')) {
            return true;
        }
        return ($formConfig->limit_one == 1);
    }

    /**
     * Determine if we should consider a relationship to not be satisifed if there is no form for the given named form
     * @param string $formName
     * @returns boolean
     */ 
    public function isJoin($formName) {
        $formConfig = $this->getFormConfig($formName);
        return ($formConfig instanceof I2CE_MagicDataNode && $formConfig->is_scalar('drop_empty') && $formConfig->drop_empty == 1);
    }


    /**
     * Determine if we should consider a relationship to not be satisifed if there is no form for the given named form
     * @param string $formName
     * @returns boolean
     */ 
    public function isRightJoin($formName) {
        $formConfig = $this->getFormConfig($formName);
        return ($formConfig instanceof I2CE_MagicDataNode && $formConfig->is_scalar('drop_empty') && $formConfig->drop_empty ==2);
    }


    /**
     * Checks to see if a given named form and id satisfies the relationship for the given primary form
     * @param  mixed $form_id.  Either  an I2CE_Form instnace, a  string ( the  form id) or an array of string (priamry_form, id) for the primary form of the relationship
     * @param  string $namedForm. The name of the form in the relationship  
     * @param string $namedFormId.  The id of the named form we wish to check. Can be either "$form|$id" oe "$id"
     * @returns boolean or  Null on failure
     */
    public function formSatisfiesRelationship($form_id,$namedForm, $namedFormID ) {
        if (! is_string ($namedForm)) {
            I2CE::raiseError("Invalid Named Form");
            return null;
        }
        $forms =  $this->getFormsSatisfyingRelationship($form_id);
        if (count($forms) == 0) {
            return false;
        }
        if (!array_key_exists($namedForm,$forms) || !($formObj  = $forms[$namedForm]) instanceof I2CE_Form) {
            return false;
        }
        if ( ($pos = strpos($namedFormId,"|")) === false) {
            return ($namedFormId == $forms[$namedForm]->getId());
        } else {
            $form = substr($namedFormId,0,$pos);
            $namedFormId = substr($namedFormId,$pos+1);
            return ($form == $formObj->getName() ) && ($namedFormId == $formObj->getId());
        }
    }

    /**
     * Get the forms that satisfy a relationship for the given primary form id
     * @param  mixed $form_id.  Either  an I2CE_Form instnace, a  string ( the  form id) or an array of string (priamry_form, id) for the primary form of the relationship
     * @returns array of mixed.  The array may be empty if the $form_id  does not satisfy the relationship.  It is indexed by the named form and the form objects
     * are already populated.  If there was no matching form for a given named form, then the value of the array elemet will be false
     */
    public function getFormsSatisfyingRelationship($form_id) {
        if ($form_id instanceof I2CE_Form) {
            $form = $form_id->getName();
            $id = $form_id->getID();
        } else if ( is_string($form_id)) {
            if (   ( $pos = strpos( $form_id, '|' ) ) !== false ) {
                    $form = substr($form_id,0,$pos);
                $id = substr($form_id,$pos+1);
            } else {
                $form = $this->getPrimaryForm();
                $id = $form_id;
            }
        } else if (is_array($form_id) && count($form_id) == 2) {
            reset($form_id);
            $form = array_shift($form_id);
            $id = array_shift($form_id);
        }else {
            I2CE::raiseError("Invalid form id");
            return array();
        }
        if ($form != $this->getPrimaryForm()) {
            I2CE::raiseError("The given form id does not match the primary form: " . $this->getPrimaryForm());
            return array();
        }
        if (strlen($id) == 0 || $id == '0') {
            I2CE::raiseError("Passed empty id");
            return array();
        }
        $ff = I2CE_FormFactory::instance();
        //now we should have a valid $id for the primary form.
       
        $res = array();
        $ancestorObjs = array();
        $formNames = $this->getFormNames();
        foreach ($formNames as $formName) {
            $form = $this->getForm($formName);
            $formObj = false;            
            if ($formName != 'primary_form') {
                $id = false;
                $parentFormName = $this->getParentFormNames($formName);
                if (!is_string($parentFormName) || !array_key_exists($parentFormName,$res) ) {
                    I2CE::raiseError("Could not get the named parent form for $formName");
                    return array();
                }
                if ( ($parentFormObj = $res[$parentFormName]) instanceof I2CE_Form  && $parentFormObj->getId() !== '0') {
                    $id = $this->getFormIdsJoiningOn($formName,$parentFormObj);
                    if ($id === false) {
                        return array();
                    } else if ($id === '0') {
                        //no matches and we need to not return anything
                        continue;
                    } 
                }
            }
            if ($id !== '0') {
                $formObj = $ff->createContainer(array($form,$id));
                if (!$formObj instanceof I2CE_Form) {
                    I2CE::raiseError("Could not instantaiate $id of form $form from named form $formName");
                    return array();
                }
                $formObj->populate();
            } else {
                //create a "blank" form
                $formObj = $ff->createContainer($form);
                if (!$formObj instanceof I2CE_Form) {
                    I2CE::raiseError("Could not instantaiate $id of form $form from named form $formName");
                    return array();
                }             
            }
            $res[$formName] = $formObj;           
        }
        return $res;
    }

    /**
     * Find all form ids matching the given join
     * @param string $formName The named form
     * @param I2CE_Form $parentFormObj The parent of this form
     * @returns array of I2CE_Form.  An array of unpopulated form objects matching the join conditions on the relationship 
     * @returns array of string, the form ids
     */
    public function getFormIdsJoiningOn($formName, $parentFormObj) {
        if ( !$parentFormObj instanceof I2CE_Form) {
            I2CE::raiseError("Invalid form object");
            return false;
        }
        if (!array_key_exists($formName, $this->formConfigs)) {
            I2CE::raiseError($formName . " is not a valid named form in this relationship");
            return false;
        }
        $formConfig = $this->formConfigs[$formName];
        $joinStlye = '';
        $formConfig->setIfisSet($joinStyle,"join_style");
        if (!$joinStyle) {
            I2CE::raiseError("No join style specfied for $formName");
            return false;
        }
        $joinData = array();
        $formConfig->setIfIsSet($joinData,"join_data",true);
        $joinMethod = "getFormIdsJoiningOn_$joinStyle";
        if (!$this->_hasMethod($joinMethod)) {
            I2CE::raiseError("No method registererd to handle get forms for join style {$joinStyle} for $formName");
            return false;
        }
        if ($this->formConfigs[$formName]->is_parent('where')) {
            $where = $this->formConfigs[$formName]->getAsArray('where');
            $this->addParentIdToWhereClause($where,$parentFormObj->getNameId() );
        } else {
            $where = array();
        }
        $anc_where = array();
        if ($this->formConfigs[$formName]->is_parent('ancestral_conditions')) {
            foreach ($this->formConfigs[$formName]->ancestral_conditions as $ancestorData) {
                if (!$ancestorData instanceof I2CE_MagicDataNode) {
                    continue;
                }
                $anc_form = '';
                if (! $ancestorData->setIfIsSet($anc_form,'anc_form') || !$anc_form || strlen(trim($anc_form)) == 0) {
                    continue;
                }
                if (!in_array($anc_form,$ancForms)) {
                    I2CE::raiseError("Ancestral form $anc_form of $formName is not in " . implode(",",$ancForms));
                    return false;
                }

                $anc_field = '';
                if (! $ancestorData->setIfIsSet($anc_field,'anc_field') || !$anc_field || strlen(trim($anc_field) == 0)) {
                    I2CE::raiseError("Ancestral field is not chosen in ancestor form $anc_form when joining $formName");
                    return false;
                }

                $child_field = '';
                if (! $ancestorData->setIfIsSet($child_field,'child_field') || !$child_field || strlen(trim($child_field) == 0)) {
                    I2CE::raiseError("Child field is not chosen in ancestor form $anc_form when joining $formName");
                    return false;
                }
                if (!array_key_exists($anc_form,$ancestorFormObjs) || !$ancestorFormObjs[$anc_form] instanceof I2CE_Form) {
                    I2CE::raiseError("Could not get ancestor form $anc_form");
                    return false;
                }
                $ancFieldObj = $ancestorFormObjs[$anc_form]->getField($anc_field);
                if (!$ancFieldObj instanceof I2CE_FormField) {
                    I2CE::raiseError("Could not get field $anc_field of $anc_form");
                    return false;
                }
                $anc_where[] = array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>$child_field,
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$ancFieldObj->getDBValue()
                        )
                    );
            }
        }
        if (count($anc_where) > 0) {
            $anc_where = array(
                    'operator'=>'AND',
                    'operand'=>$anc_where
                    );            
            if (count($where) > 0 ) {
                $where = array(
                    'operator'=>'AND',
                    'operand'=>array($where,$anc_where)
                    );
            } else {
                $where = $anc_where;
            }
        }
        $ids =  $this->$joinMethod($formName, $parentFormObj,$joinData,$where, 1);
        
        if (!is_array($ids) || count($ids) > 1) {
            I2CE::raiseError("Unexpected data");
            return false;
        } else    if ( count($ids) == 0) {
            return '0';
        } else {
            reset($ids);
            return current($ids);
        }
    }
    



    protected function addParentIdToWhereClause( &$where, $parent_id) {
        if (!is_array($where) || !array_key_exists('operator',$where)) {
            return;
        }
        if ($where['operator'] == 'FIELD_LIMIT') {
            if (!array_key_exists('data',$where)) {
                $where['data'] = array();
            }
            $where['data']['parent_id'] = $parent_id;
        } else if (in_array($where['operator'],self::$operands) && array_key_exists('operand',$where) && is_array($where['operand'])) {
            foreach ($where['operand'] as &$s_where) {
                $this->addParentIdToWhereClause($s_where, $parent_id);
            }
        }
    }



    /**
     * Get the SQL name of a form via the registered callback functin
     * @param string $form
     * @returns mixed.  False on failure. A string on success
     */
    public function getReferencedForm($form) {
        if ($this->form_reference == null) {
            I2CE::raiseError("No form reference set");
            return false;
        }
        $ret = @call_user_func($this->form_reference,$form);
        if (!is_string($form)) {
            I2CE::raiseError("Could not get reference for form $form");
            return false;
        }
        return $ret;
    }

    
    /**
     * Walks the function data tree to creates the list of function details
     * @param I2CE_MagicDataNode $config
     * @param array &$functions.  The working list fo functions.
     * @param array $dependents  Defaul to empty array.  The functions that all the functions at this level depend on
     */
    protected function getFunctionDetailsWalker($config,&$functions, $dependents =array()){
        if (!$config->is_parent('reporting_functions')) {
            return;
        }
        foreach ( $config->reporting_functions as $function=>$functionConfig) {
            if (!$functionConfig instanceof I2CE_MagicDataNode) {
                I2CE::raiseError("Skipping  $function b/c not magic data node");
                continue;
            }
            $t_dependents = $dependents;
            $t_dependents[] = $function;
            $t_functions = array();
            $this->getFunctionDetailsWalker($functionConfig,$t_functions,$t_dependents);
            //any of depenedent function details are we want to before thisfunctions.  this ordering is intentional, but not gauranteed
            $functions = array_merge($functions,$t_functions);
            //now we put this functions details
            $functions[$function] = $this->_getFunctionDetails($functionConfig, $dependents);
        }        
        return;
    }

    /**
     *Get the array of function details at the specified for the specified function at the speci
     * @var I2CE_MagicDataNode $funcConfig
     * @var array $dependents  A list of functions this function depends on
     */
    protected function _getFunctionDetails($funcConfig, $dependents) {
        if (!$funcConfig instanceof I2CE_MagicDataNode) {
            I2CE::raiseError("Bad function config");
            return false;
        }
        $qry = '';
        if (!$funcConfig->setIfIsSet($qry,'qry')) {
            I2CE::raiseError("Function {$function} is not defined");
            return false;
        }
        $qry= trim($qry);
        if (!$qry) { //no query set so skip it
            I2CE::raiseError("Function {$function} is empty");
            return false;
        }
        $function = $funcConfig->getName();
        if (!$funcConfig->setIfIsSet($formfield,'formfield') 
            || ! ($fieldObj = I2CE_FormField::createField($funcConfig->formfield,$function)) instanceof I2CE_FormField) {
            I2CE::raiseError("Function {$function} cannot be associated to a form field:\n" . print_r($funcConfig->getAsArray(),true));
            return false;
        }
        preg_match_all('/`([a-zA-Z0-9\_\-]+\+[a-zA-Z0-9\_\-]+)`/',$qry,$required_fields);
        $aggregate = false;
        $funcConfig->setIfIsSet($aggregate,'aggregate');
        return array('qry'=>$qry,'type'=>$fieldObj->getDBType(),'required_fields'=>array_unique($required_fields[1]), 'field'=>$fieldObj, 'aggregate'=>$aggregate, 'dependents'=>$dependents);


    }

    


    protected $funcDetails = null;
    
    /**
     * Get the details of a relationship function
     * @param mixed  $functions string or an array of string
     * @return mixed. false on failure.  On success an array with the keys 'qry' the query string, 'type' the return SQL type of the function, 
     * 'required_fields' the form fields reuquired in the relationship,  and 'field' which is an instance of I2CE_FormField for the function
     */
    public function getFunctionDetails($functions) {
        if (is_string($functions)) {
            $functions = array($functions);
        }
        if (!is_array($functions)) {
            return array();
        }
        if ($this->funcDetails == null) {
            $this->funcDetails = array();
            $this->getFunctionDetailsWalker($this->relConfig,$this->funcDetails);
        }
        $details = array();
        while (count($functions) > 0) {
            reset($functions);
            $function = current($functions);
            if (!array_key_exists($function,$this->funcDetails) || !is_array($this->funcDetails[$function])) {
                array_shift($functions);
                $details[$function] = false;
                continue;
            }
            $added_deps = false;
            foreach ($this->funcDetails[$function]['dependents'] as $func) {
                if ( !array_key_exists($func,$details) ) {
                    if ( in_array($func,$functions) ) {
                        $func_key = array_search( $func, $functions );
                        if ( $func_key !== false ) {
                            unset( $functions[$func_key] );
                        }
                    }
                    $added_deps = true;
                    array_unshift( $functions, $func );
                }
            }
            if ($added_deps) {
                //do nothing
            } else {
                $details[$function] = $this->funcDetails[$function];
                array_shift($functions);
            }
        }
        return $details;
    }
        
    
    /**
     * Evaluate a given relationship function on the given form data
     * @param string $funciton
     * @param array $formData of I2CE_Form as returend by the @getFormsSatisfyingRelationship()
     * @param boolean $displayValue.  Defaults to true, in which case it returns the display value of the function.  If false, it returns the DB value
     * @returns string
     */
    public function evaluateFunction($function,$formData,$displayValue = true) {
        //this DOES NOT handle dependent functions.
        $details = $this->getFunctionDetails($function);
        if (!is_array($details) || !array_key_exists($function,$details) || !is_array($details[$function])) {
            return '';
        }
        $details = $details[$function];
        $tmp_table = "`tmp_rel_func_eval_" . $this->relationship . '+' . $function . "`";
        $fieldDefs = array();
        $fieldVals = array();        
        foreach ($details['required_fields'] as $formfield) {
            list($namedform,$field) = array_pad(explode("+",$formfield,2),2,'');
            if (!$namedform || !$field || !$formData) {
                I2CE::raiseError("$formfield not found in form data");
                return '';
            }
            if ($namedform == $this->relationship) {
                $namedform = 'primary_form';
            }
            if (!array_key_exists($namedform,$formData) || !$formData[$namedform] instanceof I2CE_Form) {
                I2CE::raiseError("$namedform is not in form data");
                return '';
            }
            $fieldObj = $formData[$namedform]->getField($field);
            if (!$fieldObj instanceof I2CE_FormField) {
                I2CE::raiseError("Invalid field $field for named form $namedform");
                return '';
            }
            $fieldDefs[] = '`' . $formfield . "` " . $fieldObj->getDBType();
            $fieldVals[] = "'" . addslashes($fieldObj->getDBValue()) . "'";
        }
        $qrys = array(
            "CREATE TEMPORARY TABLE  IF NOT EXISTS $tmp_table  (" . implode(",",$fieldDefs) . ") ",
            "TRUNCATE $tmp_table",
            "INSERT INTO $tmp_table VALUES( " . implode(",", $fieldVals) . ")", 
            );
        $db = MDB2::singleton();
        foreach ($qrys as $qry) {
            $res = $db->exec($qry);
            if (I2CE::pearError($res,"Could not evaluate $qry")) {
                return '';
            }
        }
        $qry = 'SELECT ' . $details['qry'] . ' AS res FROM ' . $tmp_table;
        $res = $db->queryRow($qry);
        if (I2CE::pearError($res,"Could not evaluate $qry")) {
            return '';
        }
        //$db->exec("DROP TABLE $tmp_table");                            
        $details['field']->setFromDB($res->res);
        if ($displayValue) {
            return $details['field']->getDisplayValue();
        } else {
            return $details['field']->getDBValue();
        }
    }



    /**
     * @var protected static array $operands of string.  The SQL operands recognzied
     */
    protected static $operands = array('OR','XOR','AND','NOT');

    /** 
     * Gets the names of the fields which are in a limit for a where clause of a given form
     * @param string $formName The anmed form in the relationship
     * @returns array of string, the fields that are present in the limit
     */
    public function getLimitingFields ($formName) {
        $limiting_fields = array();
        if (!array_key_exists($formName,$this->formConfigs)) {
            return $limiting_fields;
        }
        if (!$this->formConfigs[$formName]->is_parent('where')) {
            return $limiting_fields;
        }
        $this->_getLimitingFields($this->formConfigs[$formName]->where, $limiting_fields);
        return $limiting_fields;
    }


    /**
     * internal function to get the fields which are in the limits for one where clause in a relationship
     * @param I2CE_MagicDataNode $whereConfig
     * @param array $limited_fields array of boolean indexed by the names of the fields
     */
    protected function _getLimitingFields($whereConfig, &$limiting_fields) {
        if (!$whereConfig instanceof I2CE_MagicDataNode) {
            I2CE::raiseError("Invalid where clause");
            return;
        }
        if (!isset($whereConfig->operator)) {
            return;
        }
        if ($whereConfig->operator == 'FIELD_LIMIT') {
            //see if we have a valid field, if so add it to our list
            $whereConfig->setIfIsSet($field,'field');
            if ($field && !in_array($field,$limiting_fields)) {
                $limiting_fields[$field] =true;
            }
        } else if (in_array($whereConfig->operator,self::$operands)) {
            //go down
            if (!$whereConfig->is_parent('operand')) {
                return;
            }
            foreach ($whereConfig->operand as $operand=>$opConfig) {
                $this->_getLimitingFields($opConfig,$limiting_fields);
            }
            
        } //else do nothing
    }


    /**
     * Generate the where clause for the join of a given named form
     * @param string $formName
     * @returns mixed. false on failure or a SQL string on success
     */
    public function generateWhereClause($formName) {
        if (!array_key_exists($formName,$this->formConfigs) || !$this->formConfigs[$formName]->is_scalar('where/operator')) {
            return '';
        }        
        $form = $this->getForm($formName);
        $formObj = I2CE_FormFactory::instance()->createContainer($form.'|0');
        if (!$formObj instanceof I2CE_Form) {
            I2CE::raiseError("Could not instantiate $formName=>$form");
            return false;
        }
        //$callback = create_function('$form,$field',"return \"`$formName+\$field`\";");
        if ( $formName == "primary_form" ) {
            $callback = create_function('$form,$field',"return \"`$form`.`\$field`\";");
        } else {
            $callback = create_function('$form,$field',"return \"`$formName`.`\$field`\";");
        }
        $where =  $formObj->generateWhereClause($this->formConfigs[$formName]->where->getAsArray(),$callback, "`" . $this->getParentFormNames($formName) . "+id`" );
        $formObj->cleanup();
        return $where;
    }



    /**
     * Get linking data, if any, for the indicated field of the named form
     * @param string $formName The named form in the relationship
     * @param string $fieldName The field name 
     * @param string $joinForm the named form we are trying to join
     * @param string $joinField The field of the joined form we are looking for
     * @returns mixed If no linking data then return false.  Otherwise is in an array with keys sub_select, forms and  fields
     */
    protected function getLinkingData($formName,$fieldName, $joinFormName, $joinField, $style = 'default', $reversed = false, $primary_table = 'primary_table') {
        if ($joinFormName == $primary_table) {
            if ( ($joinForm = $this->getForm('primary_form')) === false ) {
                I2CE::raiseError("Could not map primary_form to a form");
                return false;
            }        
        } else {
            if ( ($joinForm = $this->getForm($joinFormName)) === false ) {
                I2CE::raiseError("Could not map $joinFormName to a form");
                return false;
            }        
        }
        if ( ($refJoinForm = $this->getReferencedForm($joinForm)) === false ) {
            I2CE::raiseError("COuld not get reference for $joinForm");
            return false;
        }        
        $form = $this->getForm($formName);
        $formObj = I2CE_FormFactory::instance()->createContainer($form.'|0');
        if (!$formObj instanceof I2CE_Form) {
            I2CE::raiseError("Could not instantiate $formName=>$form");
            return false;
        }
        $ret = array(
            'on_fields'=> array("`$joinFormName`.`$joinField`"),
            'sub_select'=> "$refJoinForm as `$joinFormName`",
            'forms' => array($form)
            );
        if (!I2CE_ModuleFactory::instance()->isEnabled('Lists')) {
            I2CE::raiseError("lists module is not enabled");
            return $ret;
        }
        $fieldObj = $formObj->getField($fieldName);
        if (!$fieldObj instanceof I2CE_FormField_MAP) {
            $formObj->cleanup();
            I2CE::raiseError("Field $fieldName does not exist in $joinForm: " .get_class($fieldObj));
            return $ret;
        }
        $sub_fields = $fieldObj->getDisplayedFields($style,false);
        $formObj->cleanup();
        if (count($sub_fields) <= 1)  {
            return $ret; //there is no linking data
        }
        if ( ($refJoinForm = $this->getReferencedForm($joinForm)) === false ) {
            I2CE::raiseError("Could not get reference to $joinForm");
            return $ret;
        }                
        $link_selects = array("$refJoinForm.*");
        $joins = array();
        $on = array();
        $fields = array("`$joinFormName`.`$joinField`");
        $field_names = array();
        $firstRefJoinForm = false;
        if (is_array($sub_fields)) {
            //$sub_fields = array('county' , 'district' , '[region]' , 'country')
            //$sub_fields = array('county+district','district+region','[region]','country')
            $sub_form = false;
            $sub_form_ref = false;
            $store = false;
            $forms =array();
            while (count($sub_fields) > 0) {           
                $sub_field = array_pop($sub_fields);
                if (strlen($sub_field) == 0) {
                    continue;
                }
                if ($sub_field[0] == '[' && $sub_field[strlen($sub_field)-1] == ']') {
                    //turn [region] into region
                    $sub_field = substr($sub_field,1,strlen($sub_field) -2);
                }
                $prev_sub_form_ref = $sub_form_ref;
                if (strpos($sub_field,'+') !== false) {
                    list($sub_form,$sub_link) = explode('+',$sub_field,2);
                } else {
                    $sub_link = $sub_form;
                    $sub_form = $sub_field;
                }
                //pass1: $sub_form =  country   $sub_link = false
                //pass2: $sub_form = region  $sub_link = country
                //pass3: $sub_form = district $sub_link = region
                //pass4: $sub_form = county $sub_link = distrct

                $store |= ($sub_form == $joinForm);  //turns store to true once we get to $sub_form = region if we are joining region, or $sub_form = distrct if we are joining distrct
                if (!$store) {
                    //I2CE::raiseError("Skipping $sub_field as not store $sub_form/$joinForm");
                    continue;
                }
                $forms[] = $sub_form;
                if ( ($sub_form_ref = $this->getReferencedForm($sub_form)) === false ) {
                    I2CE::raiseError("Could not get reference to linked form $sub_form");
                    return $ret;
                }              
                if ($firstRefJoinForm === false) {
                    $firstRefJoinForm = $sub_form_ref;
                }

                //sub_form_ref:  pass2: hippo_region, pass3: hippo_district, pass4: hippo_county
                $field_names[] = $sub_form;  //if we are joining region then.... pass2: (region), pass3: (region,distrinct), pass4: (region,disitrct,county)
                $field_name ='`' . implode('+',$field_names) . '+' . $joinField . '`';  //pass2: region+id, pass3: region+district+id, pass4: region+district+county+id
                $link_selects[] =   "$sub_form_ref.id AS ". $field_name;
                if ($sub_form != $joinForm) {
                    $fields[] = "`$joinFormName`.$field_name";
                    $joins[] =  " $sub_form_ref ";
                    $ons[] = " $prev_sub_form_ref.id = $sub_form_ref.`$sub_link` " ;
                }
                //pass2: value is "LEFT JOIN `hippo_
            }
        }
        if (count($field_names) > 1) {
//false                FROM `manage_4_0_dev`.`hippo_facility_type` LEFT JOIN  `manage_4_0_dev`.`hippo_facility`  ON `manage_4_0_dev`.`hippo_facility_type`.id = `manage_4_0_dev`.`hippo_facility`.`facility_type`
//true               FROM `manage_4_0_dev`.`hippo_facility_type` LEFT JOIN  `manage_4_0_dev`.`hippo_facility`  ON `manage_4_0_dev`.`hippo_facility_type`.id = `manage_4_0_dev`.`hippo_facility`.`facility_type`
            if ($reversed ) {
                $first = array_shift($joins);
                $joins[] = $firstRefJoinForm;
            } else {
                $first = $firstRefJoinForm;
            }
            foreach ($joins as $i=>&$join) {
                $join = $join . ' ON ' . $ons[$i];
            }
            $left_join = "";
            if ( count($joins) > 0 ) {
                $left_join = " LEFT JOIN " . implode( ' LEFT JOIN ', $joins );
            }
            $ret =   array(
                'sub_select' => " (SELECT " . implode(',',$link_selects) . " FROM $first $left_join ) AS `$joinFormName` ",
                'on_fields' => $fields,   //region.id, reiion.district+id, region.
                'forms'=>$forms
                );

        }
        return $ret;
        //Examples (calling from joinOn_parent_field):
        //JOIN (SELECT hippo_district.*, hippo_county.id as `district+county+id` FROM hippo_district  LEFT JOIN hippo_district on hippo_county.district = district.id)  AS `district`
        //ON `parent_form`.`location` IN (district.id,district.`county+id`)

        //if we are joining the region then it would be
        //JOIN (SELECT hippo_region.*,hippo_district.id as `region+district+id`, hippo_county.id as `region+district+county+id` FROM hippo_region LEFT JOIN hippo_district ON hippo_district.region = region.ID  LEFT JOIN hippo_district on hippo_county.district = district.id)  AS `region`
        //ON `parent_form`.`location` IN (region.id,region.`region+district+id`,region.`region.region+district+county+id`)
    }


        



    /**
     * Get the named forms in the relationship which are ancestral to the given named form
     * @param string $childFormName
     * @returns array of string, the named ancestral forms
     */
    public function getAncestorFormNames($childFormName) {
        if (!array_key_exists($childFormName, $this->formConfigs) || !$this->formConfigs[$childFormName] instanceof I2CE_MagicDataNode) {        
            return $ancForms;
        }
        if ($this->primaryFormName === $childFormName || 'primary_form' === $childFormName ) {
            return array();
        }
        $ancForms = array();
        $childFormConfig = $this->formConfigs[$childFormName];                        
        //we want to walk up the join tree, and add all child forms to a depth of max_depth.
        $depth = 0;
        $a = $childFormName;
        while ($this->primaryFormName !== $childFormName && 'primary_form' !== $childFormName ) {
            $childFormName = $childFormConfig->traverse("../../")->getName(); //../ takes us to the joins, ../ takes us to the parent fomr conifg            
            if (!array_key_exists($childFormName, $this->formConfigs) || !$this->formConfigs[$childFormName] instanceof I2CE_MagicDataNode) {
                break;
            }
            $childFormConfig = $this->formConfigs[$childFormName];                        
            $ancForms[] = $childFormName;
            $depth++;
            $ancForms = array_unique(array_merge($ancForms,$this->getChildFormNames($childFormName,$depth)));
        } 
        return array_unique($ancForms);
        
    }

    
    /**
     * Get the named forms for the given named form upto a specified depth
     * @param string $formName
     * @var int $depth.  Defaults to 1 in which case we only get the immediate children.  If depth = null, we get all child forms
     * @returns array
     */
    public function getChildFormNames($formName,$depth = 1) {
        if (!array_key_exists($formName, $this->formConfigs) 
            || !$this->formConfigs[$formName] instanceof I2CE_MagicDataNode 
            || !$this->formConfigs[$formName]->is_parent('joins')) {
            return array();
        }
        if ($depth !==  null) {
            if ( !is_int($depth) || $depth <= 0) {
                return array();
            }
        }
        $childFormNames = array();
        if ($depth !== null) {
            $depth--;
        }
        foreach ($this->formConfigs[$formName]->joins as $childName => $childConfig) {
            if (!$childConfig instanceof I2CE_MagicDataNode) {
                continue;
            }
            $childFormNames[] = $childName;
            $childFormNames = array_unique(array_merge($childFormNames,$this->getChildFormNames($childName,$depth)));
        }
        return $childFormNames;
    }



    /**
     * Get the SQL join statemetn for the given form
     * @param string $childFormName the named form in the relationship
     * @returns mixed.  String, the SQL join statements on succes, false on failure
     */
    public function getJoin($childFormName, $join_method = 'JOIN') {
        if (!array_key_exists($childFormName, $this->formConfigs)) {
            return false;
        }
        $formConfig = $this->formConfigs[$childFormName];
        if (!$formConfig instanceof I2CE_MagicDataNode) {
            I2CE::raiseError("Bad joining data for $childFormName");
        }
        $joinStyle = '';            
        $formConfig->setIfisSet($joinStyle,"join_style");
        if (!$joinStyle) {
            I2CE::raiseError("No join style specfied for $childFormName");
            return false;
        }
        $joinData = array();
        $formConfig->setIfIsSet($joinData,"join_data",true);
        $joinMethod = "joinOn_$joinStyle";
        if (!$this->_hasMethod($joinMethod)) {
            I2CE::raiseError("No method registererd to handle join style {$joinStyle} for $childFormName");
            return false;
        }
        $joinData['primary_table'] = 'base_table';
        $join =  $this->$joinMethod($childFormName, $joinData );
        if (!is_array($join) || 
            !array_key_exists('on',$join) || !is_string($join['on']) || strlen(trim($join['on'])) == 0 ||
            !array_key_exists('condition',$join) || !is_string($join['condition']) || strlen(trim($join['condition'])) == 0) {
            I2CE::raiseError("Problem joining $childFormName as $joinStyle ($joinMethod)");
            return false;
        }
        //$start_condition = $join['condition'];
        //$start_on = $join['on'];
        $conditions = array();
        $joins = array();
        if ($formConfig->is_parent('ancestral_conditions')) {
            $ancForms = $this->getAncestorFormNames($childFormName);
            foreach ($formConfig->ancestral_conditions as $ancestorData) {
                if (!$ancestorData instanceof I2CE_MagicDataNode) {
                    continue;
                }
                $anc_form = '';
                if (! $ancestorData->setIfIsSet($anc_form,'anc_form') || !$anc_form || strlen(trim($anc_form)) == 0) {
                    continue;
                }
                if (!in_array($anc_form,$ancForms)) {
                    I2CE::raiseError("Ancestral form $anc_form is not in " . implode(",",$ancForms));
                    return false;
                }

                $anc_field = '';
                if ( (! $ancestorData->setIfIsSet($anc_field,'anc_field')) || !$anc_field || strlen(trim($anc_field)) == 0 ) {
                    I2CE::raiseError("Ancestral field  is not chosen in ancestor form $anc_form when joining $childFormName");
                    return false;
                }
                $child_field = '';
                if ( (! $ancestorData->setIfIsSet($child_field,'child_field')) || !$child_field || strlen(trim($child_field)) == 0) {
                    I2CE::raiseError("Child field is not chosen in ancestor form $anc_form when joining $childFormName");
                    return false;
                }
                //$join['condition'][] = " `$childFormName`.`$child_field` = `{$anc_form}+{$anc_field}`";
                $style ='default';
                if ($ancestorData->setIfIsSet($style,'style')) {
                    $style = $joinData['displaystyle'];
                }
                if ( ($linking_data = $this->getLinkingData($childFormName,$child_field,$anc_form, $anc_field, $style,true)) == false) {
                    I2CE::raiseError("Bad Linking Data for $anc_form+$anc_field on form $childFormName");
                    return false;
                }
                if (count($linking_data['on_fields']) >  1) {
                    end($linking_data['forms']);
                    $form = current($linking_data['forms']);
                    //$joins[] = $linking_data['sub_select'] . " ON `$anc_form`.`$anc_form+$form+$anc_field` = `primary_table`.`$form+$anc_field` " ;
                    $joins[] = $linking_data['sub_select'] . " ON `$anc_form`.`$anc_form+$form+$anc_field` = `base_table`.`$form+$anc_field` " ;
                    $conditions[] =  " `$childFormName`.`$child_field`  IN (" . implode(',',$linking_data['on_fields']) . ")";
                } else {
                    //$limit = "`$parentFormName+{$joinData['field']}` = " . $linking_data['on_fields'][0];
                    //$joins[] = $linking_data['sub_select'] . " ON `$anc_form`.`$anc_field` = `primary_table`.`$anc_form+$anc_field` " ;                
                    $joins[] = $linking_data['sub_select'] . " ON `$anc_form`.`$anc_field` = `base_table`.`$anc_form+$anc_field` " ;                
                    $conditions[] = " `$childFormName`.`$child_field` = `{$anc_form}+{$anc_field}`";
                }

            }
        }
        $conditions[] = $join['condition'];
        $join_prefix = '';
        if (count($joins) > 0) {
            $join_prefix =  ' LEFT JOIN ' . implode( 'LEFT JOIN' , $joins ) . ' ' ;
        }
        return $join_prefix  .  $join_method  . '  ' . $join['on'] . ' ON ( ( ' . implode( ' ) AND  (' , $conditions) . ' ) ) ' ;
    }


    





    /**
     * Generate SQL join statement for joining as the named child form's id with the parent  of the named parent form
     * @param string $childFormName the name of the child form in the relationship
     * @param array $joinData The array containg the join data     
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */     
    public function joinOn_parent($childFormName, $joinData) {
        if ( ($refChildForm = $this->getReferencedForm($this->getForm($childFormName))) === false ) {
            return false;
        }
        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        return array('on'=>"$refChildForm AS `$childFormName` " , 'condition'=>  "`$childFormName`.id = `$parentFormName+parent` ");
    }
    /**
     * Get the forms ids for joining as the named child form's id with the parent  of the named parent form
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_parent($childFormName, $parentFormObj, $joinData, $where,$limit) {
        $form = $this->getForm($childFormName);
        if ($form != $parentFormObj->getParentForm()) {
            return array();
        }
        $ids = I2CE_FormStorage::search($form, false  ,$where,array());
        if (is_string($ids)) {
            $ids = array($ids);
        }
        $id = $parentFormObj->getParentId();
        if (is_array($ids) && in_array($id,$ids)) {
            return array($id);
        } else {
            return array();
        }
        //return " JOIN $refChildForm AS `$childFormName`  ON `$childFormName`.id = `parent_form`.parent ";
    }







    /**
     * Generate SQL join statement for joining as a named child form's id with the named parent form on a given field
     * @param string $childForm the name of the child form in the relationship
     * @param array $joinData The array containg the join data
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */         
    public function joinOn_parent_field ($childFormName, $joinData) {
        if  ((!is_array($joinData)) || 
             (!array_key_exists('field', $joinData)) || 
             (!is_string($joinData['field'])) ||
             (strlen($joinData['field']) == 0) ){
            I2CE::raiseError("Join parent field not specified for parent_form/$childFormName");
            return false;
        }
        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        $style = 'default';
        if (array_key_exists('displaystyle',$joinData) && $joinData['displaystyle']) {
            $style = $joinData['displaystyle'];
        }
        if ( ($linking_data = $this->getLinkingData($parentFormName,$joinData['field'], $childFormName, 'id', $style)) == false) {
            return false;
        }
        if (count($linking_data['on_fields']) >  1) {
            $limit = "`$parentFormName+{$joinData['field']}` IN (" . implode(',',$linking_data['on_fields']) . ")";
        } else {
            $limit = "`$parentFormName+{$joinData['field']}` = " . $linking_data['on_fields'][0];
        }
        return array('on'=> $linking_data['sub_select'] , 'condition'=>$limit);
    }
    /**
     * Get the forms ids for joining as a named child form's id with the named parent form on a given field
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_parent_field($childFormName, $parentFormObj, $joinData, $where,$limit) {
        if  ((!is_array($joinData)) || 
             (!array_key_exists('field', $joinData)) || 
             (!is_string($joinData['field'])) ||
             (strlen($joinData['field']) == 0) ){
            I2CE::raiseError("Join parent field not specified for parent_form/$childFormName");
            return array();
        }
        $form = $this->getForm($childFormName);
        $fieldObj = $parentFormObj->getField($joinData['field']);
        if (!$fieldObj instanceof I2CE_FormField) {
            I2CE::raiseError("Invalid field " . $joinData['field']);
            return array();
        }
        $formid = $fieldObj->getDBValue();
        if ( !is_string($formid) || ($pos = strpos($formid,'|')) === false) {
            return array();
        }

        //here parentForm is person_positon which links to positoin by person_positon+position = position+id
        //what we want to do is get all positions 
        //we already have the id we want, we just need to make sure that is satisfies the limit
        $childFormObj = I2CE_FormFactory::instance()->createContainer($formid);
        if (!$childFormObj instanceof I2CE_Form) {
            return array();
        }
        if (is_array($where) && count($where) > 0 && !$childFormObj->checkWhereClause($where)) {
            return array();
        } else{
            return array( substr($formid,$pos+1)); //this is the id of person_position.
        }

        // I2CE::raiseError("Seraching $form on:\n" . print_r($where,true));
        // $ids = I2CE_FormStorage::search($form, $formid  ,$where,array());
        // if (is_string($ids)) {
        //     $ids = array($ids);
        // }
        // if (is_array($ids) && in_array($id,$ids)) {
        //     return array($id);
        // } else {
        //     return array();
        // }
        //return " JOIN  $refChildForm AS `$childFormName`  ON `$childFormName`.id = `parent_form`.`{$joinData['field']}` ";
    }






    /**
     * Generate SQL join statement for joining on ids of the named parent/child forms
     * @param string $childFormName the name of the child form in the relationship
     * @param array $joinData The array containg the join data
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */     
    public function joinOn_ids($childFormName, $joinData) {
        if ( ($refChildForm = $this->getReferencedForm($this->getForm($childFormName))) === false ) {
            return false;
        }
        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        $limit = "`$childFormName`.id = `$parentFormName+id` ";
        return array('on'=>"  $refChildForm AS `$childFormName`" , 'condition'=>$limit);
    }
    /**
     * Get the forms ids for joining on ids of the named parent/child forms
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_ids($childFormName, $parentFormObj, $joinData, $where,$limit) {
        $ids = I2CE_FormStorage::search($this->getForm($childFormName), false  ,$where,array());
        if (is_string($ids)) {
            $ids = array($ids);
        }
        $parent_id = $parentFormObj->getId();
        if (is_array($ids) && in_array($parent_id,$ids)) {
            return array($parent_id);
        } else {
            return array();
        }
        //return " JOIN  $refChildForm AS `$childFormName`  ON `$childFormName`.id = `parent_form`.id ";
    }












    /**
     * Generate SQL join statement for joining  on the named parent forms if with the parent of the named child form
     * @param string $childFormName the name of the child form in the relationship
     * @param array $joinData The array containg the join data
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */     
    public function joinOn_child($childFormName, $joinData) {
        if ( ($refChildForm = $this->getReferencedForm($this->getForm($childFormName))) === false ) {
            return false;
        }
        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        $limit = "`$childFormName`.parent = `$parentFormName+id` "  ;
        return array('on'=>"  $refChildForm AS `$childFormName`  " , 'condition'=>$limit);
    }
    /**
     * Get the forms ids for joining  on the named parent forms if with the parent of the named child form
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_child($childFormName, $parentFormObj, $joinData, $where,$limit) {
        $form = $this->getForm($childFormName);
        $ids = I2CE_FormStorage::search($form, $parentFormObj->getNameId()  ,$where,array(),$limit);
        if (is_string($ids)) {
            $ids = array($ids);
        }
        if (is_array($ids)) {
            return $ids;
        } else {
            return array();
        }
        //return " JOIN $refChildForm AS `$childFormName`  ON `$childFormName`.parent = `parent_form`.id "  ;
    }


    


    /**
     * Generate SQL join statement for joining on the named parent form's id with the named child form on a given field
     * @param string $childFormName the name of the child form in the relationship
     * @param array $joinData The array containg the join data
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */     
    public function joinOn_child_field($childFormName, $joinData ) {
        if  ((!is_array($joinData)) || 
             (!array_key_exists('field', $joinData)) || 
             (!is_string($joinData['field'])) ||
             (strlen($joinData['field']) == 0) ){
            I2CE::raiseError("Join child field not specified for parent_form/$childFormName");
            return false;
        }
        if ( ($refChildForm = $this->getReferencedForm($this->getForm($childFormName))) === false ) {
            return false;
        }
        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        if (!array_key_exists('primary_table',$joinData)) {
            $joinData['primary_table'] = 'primary_table';
        }     
        if ($parentFormName == 'primary_form') {
            $parentFormName = $joinData['primary_table'];
        }
        $style = 'default';
        if (array_key_exists('displaystyle',$joinData) && $joinData['displaystyle']) {
            $style = $joinData['displaystyle'];
        }
        //if ( ($linking_data = $this->getLinkingData( $childFormName, $joinData['field'], $parentFormName,'id',$style, true)) == false) {   
        if ( ($linking_data = $this->getLinkingData( $childFormName, $joinData['field'], $parentFormName,'id',$style, true, $joinData['primary_table'])) == false) {   
            return false;
        }
        if (count($linking_data['on_fields']) >  1) {            
            //$on =  " $refChildForm AS `$childFormName`  "; 
            //$limit = " `$childFormName`.`{$joinData['field']}` IN  (SELECT "     . implode(',',$linking_data['on_fields']). " FROM "  . $linking_data['sub_select'] . " ) ";
            //$on = " $refChildForm AS `$childFormName`  "; 
            //$limit = " `$childFormName`.`{$joinData['field']}` IN  (SELECT "     . implode(',',$linking_data['on_fields']). " FROM "  . $linking_data['sub_select'] . " ) ";
            end($linking_data['forms']);
            $form = current($linking_data['forms']);
            if ($form == $this->primaryForm) {
                $pform = 'primary_form';
            } else {
                $pform = $form;
            }
            $on = $linking_data['sub_select'] . " ON {$joinData['primary_table']}.`$pform+id` =  `$parentFormName`.`$parentFormName+$form+id`  JOIN  $refChildForm AS `$childFormName`  ";  
            $limits = array();
            foreach($linking_data['on_fields'] as $on_field) {
                $limits[] = "`$childFormName`.`{$joinData['field']}` = $on_field";
            }
            $limit = "(" . implode (" OR " , $limits) . ")";

        } else {
            $on =    " $refChildForm AS `$childFormName`  ";
            //$limit = "`$childFormName`.`{$joinData['field']}` = " . $linking_data['on_fields'][0];
            //$limit = "`$childFormName`.`{$joinData['field']}` = `" . $parentFormName . '+id` ';
            if ($parentFormName == $joinData['primary_table']) {
                $limit = "`$childFormName`.`{$joinData['field']}` = `primary_form+id` ";
            } else {
                $limit = "`$childFormName`.`{$joinData['field']}` = `" . $parentFormName . '+id` ';
                //$limit = "`$childFormName`.`{$joinData['field']}` = `" . $parentFormName . '`.`id` ';
            }
            //$limit = " = `$parentFormName+id` ";
        }
        return array('on'=> $on, 'condition'=>$limit);
    }






    /**
     * Get the forms ids  for joining on the named parent form's id with the named child form on a given field
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_child_field($childFormName, $parentFormObj, $joinData, $where,$limit) {
        if  ((!is_array($joinData)) || 
             (!array_key_exists('field', $joinData)) || 
             (!is_string($joinData['field'])) ||
             (strlen($joinData['field']) == 0) ){
            I2CE::raiseError("Join child field not specified for $childFormName");
            return array();
        }
        $form = $this->getForm($childFormName);
        $sub_where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>$joinData['field'],
            'style'=>'equals',
            'data'=>array(
                'value'=>$parentFormObj->getNameId()
                )
            );
        if (count($where) > 0) {
            $where = array('operator'=>'AND', 'operand'=>array($sub_where,$where));
        } else {
            $where = $sub_where;
        }        
        $ids = I2CE_FormStorage::search($form, $parentFormObj->getNameId()  ,$where,array(),$limit);
        if (is_string($ids)) {
            $ids = array($ids);
        }
        if (is_array($ids)) {
            return $ids;
        } else {
            return array();
        }
        //return  " JOIN $refChildForm AS `$childFormName` ON `$childFormName`.`{$joinData['field']}` = `parent_form`.id ";
    }



    
    /**
     * Generate SQL join statement for joining on a given field of  a named child form's with the named parent form on a given field
     * @param string $childFormName the name of the child form in the relationship
     * @param array $joinData The array containg the join data
     * @return mixed. On success, a string which is the SQL join statement.  On failure, false
     */             
    public function joinOn_fields ($childFormName, $joinData) {        
        if  (!is_array($joinData)) {
            I2CE::raiseError("Join data  specified for parent_form/$childFormName");
            return false;
        }
        foreach (array('parent','child') as $field) { 
            if ((!array_key_exists($field, $joinData)) || 
                (!is_string($joinData[$field])) ||
                (strlen($joinData[$field]) == 0) ){
                I2CE::raiseError("Join $field field not specified for parent_form/$childFormName");
                return false;
            }
        }
        if ( ($refChildForm = $this->getReferencedForm($this->getForm($childFormName))) === false ) {
            return false;
        }

        if ( ($parentFormName = $this->getParentFormNames($childFormName)) === false) {
            return false;
        }
        $limit = "`$childFormName`.`{$joinData['child']}` = `$parentFormName+{$joinData['parent']}` " ;
        return array('on'=>"    $refChildForm AS `$childFormName`   " , 'condition'=>$limit);
    }
    /**
     * Get the forms ids for joining on a given field of  a named child form's with the named parent form on a given field
     * @param string $childFormName the name of the child form in the relationship
     * @param I2CE_Form $parentFormObj
     * @param array $joinData The array containg the join data
     * @param array $where
     * @param array $limit
     * @return mixed. An array of form ids
     */     
    public function getFormIdsJoiningOn_fields($childFormName, $parentFormObj, $joinData, $where,$limit) {
        if  (!is_array($joinData)) {
            I2CE::raiseError("Join data  specified for parent_form/$childFormName");
            return array();
        }
        foreach (array('parent','child') as $field) { 
            if ((!array_key_exists($field, $joinData)) || 
                (!is_string($joinData[$field])) ||
                (strlen($joinData[$field]) == 0) ){
                I2CE::raiseError("Join $field field not specified for parent_form/$childFormName");
                return array();
            }
        }
        $form = $this->getForm($childFormName);
        $fieldObj = $parentFormObj->getField($joinData['field']);
        if (!$fieldObj instanceof I2CE_FormField) {
            I2CE::raiseError("Invalid field " . $joinData['field']);
            return array();
        }
        $sub_where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>$joinDta['child'],
            'style'=>'equals',
            'data'=>array('value'=> $fieldObj->getDBValue())
            );
        if (count($where) > 0) {
            $where = array(
                'operator'=>'AND',
                'operand'=>array($sub_where,$where)
                );
        }else{
            $where = $sub_where;
        }
        $ids = I2CE_FormStorage::search($form, false  ,$where,array(),$limit);
        if (is_string($ids)) {
            $ids = array($ids);
        }
        if (is_array($ids)) {
            return $ids;
        } else {
            return array();
        }
        //return " JOIN $refChildForm AS `$childFormName` ON `$childFormName`.`{$joinData['child']}` = `parent_form`.`{$joinData['parent']}` " ;
    }
    

    


    public function getJoiningFields($childFormName) {
        if (!array_key_exists($childFormName, $this->formConfigs)) {
            return array();
        }
        $formConfig = $this->formConfigs[$childFormName];
        $joinStlye = '';
        $formConfig->setIfisSet($joinStyle,"join_style");
        if (!$joinStyle) {
            I2CE::raiseError("No join style specfied for $childFormName");
            return array();
        }
        $joinData = array();
        $formConfig->setIfIsSet($joinData,"join_data",true);
        $ret = array();
        switch ($joinStyle) { //this really should be replace by a magic method call
        case 'parent':
            $ret['parent']=true;            
            break;
        case 'fields':
            if (!array_key_exists('parent',$joinData)  || !$joinData['parent']) {
                break;
            }
            $ret[$joinData['parent']] = true;
            break;
        case  'parent_field':
            if (!array_key_exists('field',$joinData) || !$joinData['field']) {
                break;
            }
            $ret[$joinData['field']] = true;
            break;
        default: 
            break;
        }
        //now we need to walk through each of the forms to see if there are any fields used in an ancenstral condition
        foreach ($this->formConfigs as $formConfig) {
            if (!$formConfig instanceof I2CE_MagicDataNode || !$formConfig->is_parent('ancestral_conditions')) {
                continue;
            }
            foreach ($formConfig->ancestral_condtions as $conConfig) {
                if (!$conConfig instanceof I2CE_MagicDataNode) {
                    continue;
                }
                if (!$conConfig->is_scalar('anc_form') || $conConfig->anc_form != $childFormName || !$conConfig->is_scalar('anc_field') || !$conConfig->anc_field) {
                    continue;
                }
                $ret[$conConfig->anc_field] = true;
            }
        }
        return $ret;
    }



}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: