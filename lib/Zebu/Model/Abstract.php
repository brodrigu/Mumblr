<?php

abstract class Zebu_Model_Abstract
{
    const MODEL_PRIMARY_KEY   = 'primary_key';

    const MODEL_PERMISSION_RO = 'RO';

    const MODEL_TYPE_DATETIME = 'datetime';
    const MODEL_TYPE_DATE     = 'date';
    const MODEL_TYPE_OBJECT   = 'object';
    const MODEL_TYPE_INT      = 'int';
    const MODEL_TYPE_STRING   = 'varchar';
    const MODEL_TYPE_ENUM     = 'enum';
    const MODEL_TYPE_DECIMAL  = 'decimal';
    const MODEL_TYPE_MANY_MANY = 'is_many_many';
    const MODEL_TYPE_HAS_MANY = 'has_many';

    static protected $lazyLoading = true;
    static protected $caching = false;

    protected $memberVars = array();
    protected $isDirty = false;
    protected $tableDefinition;
    protected $tableDefinitionOverride = array();
    protected $user;
    protected $cache = array();

    //////////////////////////////////////////////////////////////////////
    // NOTE: by design, if you pass a null argument this will FAIL so
    // that when you are passing id's as args, you don't accidentally
    // create a new record

    function __construct()
    {
        $this->initTableDefinition();

        if(!$this->memberVars){

            if (func_num_args() == 1) {
                $id = func_get_arg(0);

                if(is_null($id)) {
                    throw new Zebu_Model_Exception(
                        'cannot instantiate '.get_called_class() .
                        ' model, id arg was NULL');
                }

                $this->memberVars = $this->fetchRecord($id);

                if(empty($this->memberVars)){
                    // create a pretty message
                    $id = '\''.$id.'\'';
                    throw new Zebu_Model_Exception(
                        'cannot instantiate '.get_called_class() .
                        ' model, id '.$id.' was not found (for current user)');
                }
            }

            if ($this->memberVars == null) {
                $this->isDirty = true;
                $this->memberVars = static::$table->createRow();
            }
        }
    }

    //////////////////////////////////////////////////////////////////////
    protected function fetchRecord($id)
    {
        if(!is_numeric($id)) {
            throw new Zebu_Model_Exception(
                'cannot instantiate '.get_called_class() .
                ' model, arg \''.$id.'\' is not an ID and class '.
                'does not override fetchRecord()');
        }

        $primaryKeyName = static::getPrimaryKey();
        $select = static::$table->select();
        $select->where( "$primaryKeyName = ?", $id);
        
        return static::$table->fetchRow($select);
    }

    //////////////////////////////////////////////////////////////////////
    protected static function initTable() {
        global $db;
        if (!static::$table) {
            static::$table =
                new Zebu_Model_Table(array('name' => static::$tableName,
                                                'db'   => $db));
        }
    }

    //////////////////////////////////////////////////////////////////////

    /* Here is where the magic happens. This function gets the table
     * definition from the database and constructs the object details
     * based on that info.
     */
    protected function initTableDefinition() {

        global $db;
        if ( !$this->tableDefinition ) {
            //make sure we have a table object
            static::initTable();

            //grab the table definition from the database
            $tableInfo = static::$table->info();
            $tableInfo = $tableInfo['metadata'];

            $standardPrefix = '';

            foreach($tableInfo as $col => $data){
                //save all data but with lowercase strings
                foreach($data as $k => $v){
                    $thisData[strtolower($k)] = $v;
                }

                //by default we just use the column name as the accessor
                $thisName = $col;

                //take care of primary key special treatment
                if($data['PRIMARY'] == 1){
                    $thisName = 'id';
                    $thisData['column'] = static::MODEL_PRIMARY_KEY;
                    $thisData['permission'] = static::MODEL_PERMISSION_RO;
                    $standardPrefix = str_ireplace('_id','',$col);

                //if column name contains '_id' we assume this is an object
                } elseif(preg_match('/_id$/',$col)){
                    $thisName = str_ireplace('_id','',$col);
                    $thisData['data_type'] = static::MODEL_TYPE_OBJECT;

                    // determine if there is an override for this
                    if(!$this->hasObjectOverride($col, $thisName)){
                        // try to find the correct class, it could be
                        // in any namespace
                        $thisData['class'] = $this->getRealClassName($thisName);
                    }

                } elseif($col == "{$standardPrefix}_name") {
                    $thisName = 'label';
                }

                // if its an enum we want to create an array of all
                // possible values
                elseif(stristr($data['DATA_TYPE'],'enum')){
                    $thisData['data_type']= static::MODEL_TYPE_ENUM;
                    $thisData['enum_values'] = array();
                    $values = str_ireplace("enum('",'',$data['DATA_TYPE']);
                    $values = str_ireplace("')",'',$values);
                    $values = explode("','",$values);
                    $thisData['enum_values'] = $values;
                }

                //save this data set to be assigned to the
                //tableDefinition attribute
                $newInfo[$thisName] = $thisData;

                //unset data for next round of the loop
                unset($thisData);
                unset($thisName);
            }

            //check for any overrides
            if($this->tableDefinitionOverride){
                foreach($this->tableDefinitionOverride as $name => $options){

                    // Apply the column_name override first to ensure
                    // the other overrides work properly
                    if(array_key_exists('column_name',$options)){
                        $newInfo = $this->renameAccessor($name, $options['column_name'], $newInfo);
                    }

                    // Apply all other overrrides
                    foreach($options as $option=>$value) {
                        if(array_key_exists($name,$newInfo) ||
                           $options['data_type']==static::MODEL_TYPE_MANY_MANY || $options['data_type']==static::MODEL_TYPE_HAS_MANY){
                            $newInfo[$name][$option] = $value;
                        }
                    }
                }
            }


            foreach($newInfo as $accessor => $data) {
                // Make sure any attributes with type object have a
                // class assigned
                if(   $data['data_type'] == static::MODEL_TYPE_OBJECT
                   && empty($data['class'])) {
                    throw new Zebu_Model_Exception("accessor type is MODEL_TYPE_OBJECT, but no class found for accessor: $accessor");
                }

                // Automagically figure out class name for linking tables
                if(   $data['data_type'] == static::MODEL_TYPE_MANY_MANY
                   && !isset($data['linking_class'])){
                    $newInfo[$accessor]['linking_class'] =
                        $this->getLinkingClass($data['class']);
                    $newInfo[$accessor]['linking_accessor'] =
                        $this->getAccessorFromClassName($data['class']);
                }

                // Automagically figure out accessor name for linking class
                if(   $data['data_type'] == static::MODEL_TYPE_MANY_MANY
                   && !isset($data['linking_accessor'])){
                    $newInfo[$accessor]['linking_accessor'] =
                        $this->getAccessorFromClassName($data['class']);
                }
            }

            //use the info to generate table definition array
            $this->tableDefinition = $newInfo;
        }
    }

    //////////////////////////////////////////////////////////////////////
    // Return the name of the class that we want to instantiate.
    // This magically determines that from (field) name passed in.
    protected function getRealClassName($name){

        $exploded = explode('_',$name);

        for($i=0;$i<sizeof($exploded);$i++){
            $exploded[$i] = ucfirst($exploded[$i]);
        }

        $name = implode('',$exploded);

        global $autoloader, $namespaces;
        foreach($namespaces as $thisNamespace){
            $autoloader->suppressNotFoundWarnings(true);
            $error_level=error_reporting(0);
            try{
                Zend_Loader::loadClass($thisNamespace.$name);
                                //This line significantly speeds up the apply
                                if(class_exists($thisNamespace.$name)){
                                        error_reporting($error_level);
                                        return $thisNamespace.$name;
                                }

            } catch (Exception $e) {
                // do nothing, keep looking
            }
            error_reporting($error_level);

            global $log;
            $log->warn("running through all declared classes, this should not have to happen [".get_called_class()."][$name]");

            foreach(get_declared_classes() as $class){
                if(strtolower($class)==strtolower($thisNamespace.$name)){
                    return $class;
                }
            }

        }
        return false;
    }

    //////////////////////////////////////////////////////////////////////
    protected function hasObjectOverride($col, $thisName){
        if($this->tableDefinitionOverride){
            foreach($this->tableDefinitionOverride as $name => $options){
                if($name == $col || $name == $thisName){
                    if(array_key_exists('class',$options)){
                        return true;
                    } else if(   array_key_exists('data_type',$options)
                              && $options['data_type']!=static::MODEL_TYPE_OBJECT){
                        return true;
                    }
                }
                if(   array_key_exists('column_name',$options)
                   && $options['column_name']==$col){
                    if(array_key_exists('class',$options)){
                        return true;
                    } elseif(   array_key_exists('data_type',$options)
                             && $options['data_type']!=static::MODEL_TYPE_OBJECT){
                        return true;
                    }
                }
            }
        }
        return false;
    }


    //////////////////////////////////////////////////////////////////////
    protected function renameAccessor($newKey, $column_name, $data){
        $newData = array();
        foreach($data as $currentKey => $info){
            if ($info['column_name'] == $column_name){
                $newData[$newKey] = $info;
            } else {
                $newData[$currentKey] = $info;
            }
        }
        return $newData;
    }

    //////////////////////////////////////////////////////////////////////
    public static function getPrimaryKey()
    {
        static::initTable();
        $info = static::$table->info();
        return $info['primary'][1];
    }

    //////////////////////////////////////////////////////////////////////
    public function isDirty()
    {
        return $this->isDirty;
    }

    //////////////////////////////////////////////////////////////////////
    public function isManyMany($accessor)
    {
        if($this->tableDefinition[$accessor]['data_type'] ==
           static::MODEL_TYPE_MANY_MANY) {
            return true;
        }
        return false;
    }

    public function hasMany($accessor)
    {
        if($this->tableDefinition[$accessor]['data_type'] ==
           static::MODEL_TYPE_HAS_MANY) {
            return true;
        }
        return false;
    }

    public function isArrayable($accessor)
    {
        if($this->tableDefinition[$accessor]['data_type'] ==
           static::MODEL_TYPE_MANY_MANY) {
            return false;
        }
        return true;
    }

    protected function isNull($accessor)
    {
        $colname = $this->getColName($accessor);
        if ($this->memberVars->$colname == null) {
            return true;
        }
        return false;
    }

    //////////////////////////////////////////////////////////////////////
    public function isObjectType($accessor)
    {
        if (!empty($this->tableDefinition[$accessor]['data_type'])) {
            $type = $this->tableDefinition[$accessor]['data_type'];
            if ($type == static::MODEL_TYPE_OBJECT) {
                return true;
            }
        }
        return false;
    }

    //////////////////////////////////////////////////////////////////////
    public function invertArray($objArray, $onaccessor)
    {
        throw new Zebu_Model_Exception('not yet implemented');
    }

    //////////////////////////////////////////////////////////////////////
    static public function objArrayToJson($objArray, $loadChildren=array() )
        {
            if(!is_array($objArray)) {
                throw new Zebu_Model_Exception('$objArray arg must be of type array');
            }

            $json = '[';
            foreach ($objArray as $obj) {
                $json .= $obj->toJson($loadChildren).",";
            }
            $json = rtrim($json,',');
            $json .= ']';
            return $json;
        }

    //////////////////////////////////////////////////////////////////////

    public function isValidAccessor($accessor){
        if ( !array_key_exists( $accessor,
                                $this->tableDefinition ) ) {
            throw new Zebu_Model_Exception("\"$accessor\" is not a valid ".
                                                get_called_class()." accessor");
        }
        return true;
    }

    //////////////////////////////////////////////////////////////////////

    public function getColName($accessor){
        if ( !array_key_exists( $accessor,
                                $this->tableDefinition ) ) {
            throw new Zebu_Model_Exception("\"$accessor\" is not a valid ".
                                                get_called_class()." accessor");
        }
        if(isset($this->tableDefinition[$accessor]['column_name'])){
            $colname = $this->tableDefinition[$accessor]['column_name'];
        } if ( !isset($colname) ) {
            $colname = $accessor;
        } elseif ( $colname == static::MODEL_PRIMARY_KEY ) {
            $colname = static::getPrimaryKey();
        }
        return $colname;
    }


    //////////////////////////////////////////////////////////////////////
    public function toJson($loadChildren = array())
    {
        return Zend_Json::encode($this->toArray($loadChildren));
    }

    //////////////////////////////////////////////////////////////////////
    public function toArray($loadChildren = array()) {

        // we always return array
        $objAsPhpArray = array();

        foreach ( array_keys( $this->tableDefinition ) as $accessor ) {

            // for objects, we either fetch the column value, or load
            // a child object
            if ($this->isObjectType($accessor) ) {

                // the literal column name in the table, not the
                // mapped accessor
                $colname = $this->tableDefinition[$accessor]['column_name'];

                // if loading childen, recursively call toArray on the
                // constituent childen
                if (in_array($accessor, $loadChildren, true) ||
                    in_array($colname, $loadChildren, true) || !static::$lazyLoading) {
                    $obj = $this->$accessor;
                    if($obj) {
                        $objAsPhpArray[$accessor] = $obj->toArray($loadChildren);
                    }
                }

                // else, just fetch the value of the columnm
                else {
                    if (preg_match('/_id$/',$colname)) {
                        $objAsPhpArray[$colname] = $this->memberVars->$colname;
                    }
                    // TODO: after drinking less jaeger, figure out
                    // what this edge case is.
                    else {
                        $objAsPhpArray[$accessor] = $this->memberVars->$colname;
                    }
                }
            } else if($this->isArrayable($accessor)) {
                $objAsPhpArray[$accessor] = $this->$accessor;
            }
        }
        return $objAsPhpArray;
    }

    //////////////////////////////////////////////////////////////////////
    public function __get($accessor)
    {
        $colname = $this->getColName($accessor);

        if( $this->isObjectType($accessor) ) {
            $id = $this->memberVars->$colname;
            if(static::$caching && isset($this->cache[$accessor])){
                return $this->cache[$accessor];
            }
            // 0 is a reserved id for system use (mainly, facilitate FK
            // contraints in mysql). Don't return nuthin for it.
            // TODO: this behavior should be configurable
            if( !$id && $id != 0 ) {
                throw new Zebu_Model_Exception('cannot return object, row has no data for column:'.$colname );
            }
            $obj = null;
            if (!$this->isNull($accessor)) {
                //TODO can of worms not opened at the moment
                //$obj = Zebu_Model_Factory::createInstance(Zebu_Model_Blueprint::createInstance()->className($this->tableDefinition[$accessor]['class'])->id($id)->type('unrestricted'));
                $obj = new $this->tableDefinition[$accessor]['class']($id);
            }
            if(static::$caching){
                $this->cache[$accessor] = $obj;
            }
            return $obj;
        }

        //if this is a linking table accessor then return array of all
        //related objects
        if( $this->isManyMany($accessor) ){

            if(static::$caching && isset($this->cache[$accessor])){
                return $this->cache[$accessor];
            }
            $criteria = new Zebu_Model_Criteria;
            $criteria->addCondition(static::getPrimaryKey(),'=',$this->id);
            $linkingClass = $this->tableDefinition[$accessor]['linking_class'];
            $linkingArray=$linkingClass::find($criteria);
            $accessor = $this->tableDefinition[$accessor]['linking_accessor'];
            $returnArray = array();
            foreach($linkingArray as $linkingObject){
                array_push($returnArray,$linkingObject->$accessor);
            }

            if(static::$caching){
                $this->cache[$accessor] = $returnArray;
            }

            return $returnArray;
        }

         //if this is a has many accessor then return array of all
        //related objects
        if( $this->hasMany($accessor) ){
            if(static::$caching && isset($this->cache[$accessor])){
                return $this->cache[$accessor];
            }
            $objectClass = $this->tableDefinition[$accessor]['class'];
            $criteria = new Zebu_Model_Criteria;
            $criteria->addCondition(static::getPrimaryKey(),'=',$this->id);
            $returnArray =  $objectClass::find($criteria);

            if(static::$caching){
                $this->cache[$accessor] = $returnArray;
            }
            return $returnArray;
        }

        return $this->memberVars->$colname;
    }

    //////////////////////////////////////////////////////////////////////
    public function isReadOnly($accessor)
    {
        if(isset($this->tableDefinition[$accessor]['permission'])){
            if ($this->tableDefinition[$accessor]['permission'] ==
                static::MODEL_PERMISSION_RO) {
                return true;
            }
        }
        return false;
    }

    //////////////////////////////////////////////////////////////////////
    public function __set($accessor, $value)
    {
        $colname = $this->getColName($accessor);

        if($this->isReadOnly($accessor)){
            throw new Zebu_Model_Exception('Cannot set read only attribute '.$accessor);
        }

        $dataType = '';
        if(isset($this->tableDefinition[$accessor]['data_type'])){
            $dataType = $this->tableDefinition[$accessor]['data_type'];
        }

        switch ( $dataType ) {
        case static::MODEL_TYPE_DATETIME:
            if($value){
                $date = new DateTime( $value );
                $value = $date->format( 'Y-m-d H:i:s' );
            }
            break;

        case static::MODEL_TYPE_DATE:
            if($value){
                $date = new DateTime( $value );
                $value = $date->format( 'Y-m-d' );
            }
            break;

        case static::MODEL_TYPE_OBJECT:
            if(!is_object($value) || $value->getClass() !=
               $this->tableDefinition[$accessor]['class']){
                throw new Zebu_Model_Exception("object is not of correct class "
                                                    .$this->tableDefinition[$accessor]['class']);
            }
            $value = $value->id;
            break;
        }

        //if we are trying to set the value to null when it cannot be null....
        if($value === null && $this->tableDefinition[$accessor]['nullable'] != 1){
            throw new Zebu_Model_Exception('Cannot set value to null: '.
                                                get_called_class().'.'.
                                                $accessor);
        }

        $this->isDirty = true;
        $this->memberVars->$colname = $value;
    }

    //////////////////////////////////////////////////////////////////////
    public function __isset($accessor) {
        $value = $this->$accessor;
        return isset($value);
    }

    //////////////////////////////////////////////////////////////////////
    public function refresh()
    {
        $this->__construct( $this->id );
    }

    //////////////////////////////////////////////////////////////////////
    public function save()
    {
        if ( $this->isDirty() ) {

            // TODO: check that acl allows this save, and that
            // create_by and updated_by columns are set proppa,
            // whatever that is

            if ( $this->exists()
                 && array_key_exists( 'updated',
                                      $this->tableDefinition )) {
                $this->memberVars->updated = date( 'Y-m-d H:i:s' );
                $this->memberVars->save();
            }
            elseif(array_key_exists( 'created', $this->tableDefinition )) {
                $this->memberVars->created = date( 'Y-m-d H:i:s' );
                $this->memberVars->save();
            }else{
                $this->memberVars->save();
            }

            $this->isDirty = false;
        }

        $this->matchMakerUpdate();

        $this->refresh();
    }

    /* fetch every row as a model object */
    //////////////////////////////////////////////////////////////////////
    public static function findAll($type = 'restricted')
    {
        $blueprint = new Zebu_Model_Blueprint();
        $criteria = Zebu_Model_Criteria_Factory::createInstance($blueprint->type($type));

        return static::find($criteria);

        /*
        static::initTable();
        $objects = array();

        $select = static::$table->select();

        /***
         * If the caller has security restrictions, add them to the
         * criteria list

        global $user;
        foreach(static::$aclRestrictions as $restriction) {
            if($restriction == Zebu_Acl::RESTRICTION_ACCOUNT_ID
               && !$user->hasRole('admin')) {
                $select->where('account_id = ?', $user->account->id);
            }
        }

        $rows = static::$table->fetchAll($select);
        foreach ( $rows as $row ) {
            $primaryKey = static::getPrimaryKey();
            $object = new static($row->$primaryKey);
            array_push($objects, $object);
        }
        return $objects;
        */
    }
    //////////////////////////////////////////////////////////////////////
    public static function find($criteria, $option = 'results')
    {
        if(   !is_object($criteria)
            || !$criteria instanceof Zebu_Model_Criteria_Interface) {
            throw new Zebu_Model_Exception("you must provide find() with a Criteria object");
        }

        // Call prepare so the criteria object can use object
        // information to apply any necessary modifications to the
        // conditions (such as acl restrictions)
        $criteria->prepare(get_called_class());

        static::initTable();
        $objects = array();

        $select = static::$table->select();

        // TODO: update Criteria.php, then use bind params here
        foreach ( $criteria->where as $thisWhere ) {
        //global $log; $log->debug($thisWhere['column_name']." ".$thisWhere['operation']." ? ".$thisWhere['bind']);
            if ($thisWhere['operation'] == Zebu_Model_Criteria::OP_IN ||
                $thisWhere['operation'] == Zebu_Model_Criteria::OP_NOT_IN) {
                $select->where( $thisWhere['column_name']." ".$thisWhere['operation']." (".$thisWhere['bind'].')');
            } else {
                $select->where( $thisWhere['column_name']." ".$thisWhere['operation']." ?", array($thisWhere['bind']) );
            }
        }

        foreach ( $criteria->sort as $thisOrder ) {
            $select->order( $thisOrder );
        }

        foreach ( $criteria->groupBy as $thisGroup ) {
            $select->group( $thisGroup );
        }

        /***
         * if limit or pagination is set in criteria, apply it
         ***/
        if(!empty($criteria->limit)){
            $select->limit( $criteria->limit, $criteria->offset );
        } elseif(!empty($criteria->pageNum)) {
            $select->limitPage($criteria->pageNum,$criteria->pageSize);
        }

        if ($option == 'query') {
            return $select->assemble();
        }

        //if option is count then only return the number of rows.
        if($option == 'count'){
            return static::$table->getAdapter()->query($select)->rowCount();
        }

        foreach ( static::$table->fetchAll( $select ) as $row ) {
            $primaryKey = static::getPrimaryKey();
            $class = get_called_class();
            $object = new $class($row->$primaryKey);
            array_push($objects, $object);
        }

        return $objects;
    }


    //////////////////////////////////////////////////////////////////////
    public function exists()
    {
        if ( $this->id == null ) {
            return false;
        }
        $primaryKey = static::getPrimaryKey();
        $select = static::$table->select()
            ->from( static::$table,
                    array( 'COUNT(1) AS count' ) )
            ->where( $primaryKey . '=?', $this->id );
        $row = static::$table->fetchRow($select);
        return( $row->count > 0 );
    }
    //////////////////////////////////////////////////////////////////////
    // this is a debugging function
    public function printInfo()
    {
        static::initTable();
        print_r( static::$table->info());
    }


    /***
     * this function is provided for the unit test framework to extract the
     * table definition and dynamically set all attributes.
     ***/
    //////////////////////////////////////////////////////////////////////
    public function Test_getTableDefinition(){
        return $this->tableDefinition;
    }

    //////////////////////////////////////////////////////////////////////
    private function getLinkingClass($className){
        list($linkedNamespace,$linkedAccessor) = $this->separateNamespace($className);
        list($thisNamespace,$thisAccessor) = $this->separateNamespace(get_called_class());

        if($thisNamespace != $linkedNamespace){
            throw new Zebu_Model_Exception("Linking namespaces must be the same.");
        }

        /***
         * We don't know which of the linking tables is names first
         * so we need to try the two possible class names. For example:
         * if a User can have many Addresses and an Address could have
         * many Users, then the linking class could either be named
         * UserAdDresses or AddressUser. We will try both.
         ***/
        $linkingClassNames[0] = $linkedNamespace.$thisAccessor.$linkedAccessor;
        $linkingClassNames[1] = $linkedNamespace.$linkedAccessor.$thisAccessor;

        foreach($linkingClassNames as $name){
                $error_level=error_reporting(0);
                try{
                    if(class_exists($name)){
                                                error_reporting($error_level);
                                                return $name;
                                        }
                } catch (Exception $e) {
                    // do nothing, keep looking
                }
                error_reporting($error_level);
                }
                global $log;
                $log->warn('Running through each class name in getLinkingClass, this should not happen ['.get_called_class().']['.$className."]");

        foreach(get_declared_classes() as $class){
                if(strtolower($class)==strtolower($name)){
                return $class;
            }
        }

    }

    //////////////////////////////////////////////////////////////////////
    protected static function getAccessorFromClassName($className){
        $breakdown = static::separateNamespace($className);
        return strtolower($breakdown[1]);
    }

    /*
     * This function seperates the namespace from the base class name
     * and returns an array of both
     */
    //////////////////////////////////////////////////////////////////////
    protected static function separateNamespace($className){
        //take the class name and seperate it by underscores
        $parts = explode('_',$className);
        //the string after the last underscore should be the class name
        $accessor = $parts[sizeof($parts)-1];
        //the rest is namespace
        $namespace=preg_replace('/'.$accessor.'$/','',$className);
        return array($namespace, $accessor);
    }

    //////////////////////////////////////////////////////////////////////
    public function unlink($accessor, $object, $deleteObject = false)
    {
        if(!is_string($accessor)){
            throw new Zebu_Model_Exception("First argument of unlink() must be the accessor, provided variable was not a string");
        }

        if(empty($object->id)){
            throw new Zebu_Model_Exception("Passed object must have a valid id. Make sure the object is saved before performing unlink operations.");
        }

        if(empty($this->id)){
            throw new Zebu_Model_Exception("Attempting to perform link on unsaved object. Make sure the object is saved before performing unlink operations.");
        }

        if( !$this->isManyMany($accessor) ){
            throw new Zebu_Model_Exception("$accessor is not of type ".static::MODEL_TYPE_MANY_MANY);
        }

        if($object->getClass() != $this->tableDefinition[$accessor]['class']){
            throw new Zebu_Model_Exception("object is not of correct class ".$this->tableDefinition[$accessor]['class']);
        }

        // TODO: esplain this block
        $criteria = new Zebu_Model_Criteria();
        $criteria->addCondition(static::getPrimaryKey(),Zebu_Model_Criteria::OP_EQ,$this->id);
        //TODO: this might not work, and should fail gracefully
        $criteria->addCondition($this->tableDefinition[$accessor]['linking_accessor'].'_id',
                                Zebu_Model_Criteria::OP_EQ,$object->id);
        $linkingClass = $this->tableDefinition[$accessor]['linking_class'];
        $linkingArray=$linkingClass::find($criteria);

        if(sizeof($linkingArray) != 1) {
            throw new Zebu_Model_Exception("found more than one link with provided criteria cannot unlink object");
        }
        $linkingArray[0]->delete();

        //if we are supposed to also delete the object, then lets delete it also.
        if($deleteObject){
            //we are currently not going to do this since we don't want to delete certain things
            // $object->delete();
        }

        $this->matchMakerUpdate();

        return true;
    }

    public function linkAll($accessor, $idArray)
    {
        $this->isManyMany($accessor);

        $objectName = $this->tableDefinition[$accessor]['class'];

        foreach( $idArray as $id) {
            if(!is_numeric(0+$id)){
                throw new Zebu_Model_Exception('Array members must be <integer> ids ');
            }
            $this->link($accessor,new $objectName($id));
        }

    }

    public function link($accessor, $object)
    {
        global $user;

        if(!is_string($accessor)){
            throw new Zebu_Model_Exception("First argument of link() must be the accessor, provided variable was not a string");
        }

        if(empty($object->id)){
            throw new Zebu_Model_Exception("Passed object must have a valid id. Make sure the object is saved before performing unlink operations.");
        }

        if(empty($this->id)){
            throw new Zebu_Model_Exception("Attempting to perform link on unsaved object. Make sure the object is saved before performing unlink operations.");
        }

        if(!$this->isManyMany($accessor)){
            throw new Zebu_Model_Exception("$accessor is not a of type ".static::MODEL_TYPE_MANY_MANY);
        }

        if($object->getClass() != $this->tableDefinition[$accessor]['class']){
            throw new Zebu_Model_Exception("object is not of correct class ".$this->tableDefinition[$accessor]['class']);
        }

        if(static::$caching && isset($this->cache[$accessor])){
            unset($this->cache[$accessor]);
        }

        $linkingClass = $this->tableDefinition[$accessor]['linking_class'];
        $thisColumnName = $this->getAccessorFromClassName(get_called_class());
        $linkColumnName = $this->tableDefinition[$accessor]['linking_accessor'];

        $linkingObject = new $linkingClass();
        $linkingObject->$thisColumnName = $this;
        $linkingObject->$linkColumnName = $object;

        if($linkingClass::hasColumn('account_id')) {
            $linkingObject->account = $user->account;
        }

        $linkingObject->save();

        $this->matchMakerUpdate();

        return true;
    }

    public function unlinkAll($accessor)
    {
        $objects = $this->$accessor;

        foreach($objects as $object) {
            $this->unlink($accessor,$object);
        }

    }

    public function delete()
    {
        throw new Zebu_Model_Exception('Delete called on model which does not support deletion');
    }

    public function isEnum($accessor)
    {
        if($this->isValidAccessor($accessor) && $this->tableDefinition[$accessor]['data_type'] == static::MODEL_TYPE_ENUM ){
            return true;
        }
        return false;
    }

    public function getEnumValues($accessor)
    {
        if($this->isEnum($accessor)) {
            return $this->tableDefinition[$accessor]['enum_values'];
        }
    }

    //this function calls the stored prodecude for searching the database for stuff...
    public static function matchMakerSearch($search,$filter=0)
    {
        global $db;
        //force filter to an integer value
        $filter = 0+$filter;
        if($filter !== 1 && $filter !== 0){
            throw new Zebu_Model_Exception("filter value must be either 1 or 0");
        }
        $searchableTypes = array('site','channel','account');
        $type = static::getAccessorFromClassName(get_called_class());
        if(!in_array($type,$searchableTypes)){
            throw new Zebu_Model_Exception("you can only call this function either site, channel, or account class");
        }
        $queryString = "CALL matchmaker_search( '$type', '$search', $filter );";

        $query = $db->query($queryString);
        $results=$query->fetchAll();
        $query->closeCursor();

        $class = get_called_class();
        $resultArray = array();
        foreach($results as $result){
            array_push($resultArray,new $class($result[$type.'_id']));
        }
        return $resultArray;
    }

    protected function matchMakerUpdate()
    {
        //this is just a stub, certain models need to call a stored
        //procedure to update the matchmaker when a change is made
        //if a model does not have this requirement, this function
        //will be called by default.
    }

    public static function hasColumn($colName)
    {
        static::initTable();
        $tableInfo = static::$table->info();
        return array_key_exists( $colName,
                                $tableInfo['metadata']);
    }

    public function getClass()
    {
        return get_called_class();
    }
}

