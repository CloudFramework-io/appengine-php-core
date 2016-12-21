<?php

class DataSQL
    {
    var $error = false;
    var $errorMsg = '';
    var $entity_schema = null;
    var $entity_name = null;
    var $keys = [];
    var $fields = [];
    var $mapping = [];
    private $use_mapping = false;

    /**
     * DataSQL constructor.
     * @param Core $core
     * @param array $model where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..]
     */
    function __construct(Core &$core, $model)
    {

        // Get core function
        $this->core = $core;

        // Name
        $this->entity_name = $model[0];
        if(!is_string($this->entity_name) || !strlen($this->entity_name)) return($this->addError('Missing schema name '));

        // Model
        $this->entity_schema = $model[1];
        if(!is_array($this->entity_schema)) return($this->addError('Missing schema in '.$this->entity_name));
        foreach ($this->entity_schema['model'] as $field =>$item) {
            if(stripos($item[1],'isKey')!==false) {
                $this->keys[] = [$field,(stripos($item[0],'int')!== false)?'int':'char'];
            }
            $this->fields[$field] = (stripos($item[0],'int')!== false)?'int':'char';
        }
        if(!count($this->keys)) return($this->addError('Missing Keys in the schema: '.$this->entity_name));

        if(isset($this->entity_schema['mapping'])) {
            foreach ($this->entity_schema['mapping'] as $field => $item) {
                if ($item['field']) {
                    $this->mapping[$item['field']] = $field;
                }
            }
        }
    }

    /**
     * Return the fields defined for the table in the schema
     * @return array|null
     */
    function getFields() {
        return array_keys($this->fields);
    }

    /**
     * Return the fields defined in the schema mapping
     * @return array
     */
    function getMappingFields() {
        return array_keys($this->mapping);
    }

    /**
     * Return the fields ready for a SQL query
     * @param  array|null fields to show
     * @return array|null
     */
    function getSQLSelectFields($fields=null) {
        if(!$this->use_mapping || !count($this->mapping)) {
            if(null === $fields) $fields = $this->getFields();
            return implode(',',$fields);
        }
        else {
            $ret = '';
            foreach ($this->mapping as $field=>$fieldMapped) {
                if(null != $fields && !in_array($fieldMapped,$fields)) continue;
                if($ret) $ret.=',';
                $ret .= "{$field} AS {$fieldMapped}";
            }
            return $ret;
        }
    }

    /**
     * Return the tuplas with the $keyWhere including $fields
     * @param $keysWhere
     * @param null $fields if null $fields = $this->getFields()
     */
    function fetchByKeys($keysWhere, $fields=null) {
        if($this->error) return;

        // Keys to find
        if(!is_array($keysWhere)) $keysWhere = [$keysWhere];

        // Where condition for the SELECT
        $where = ''; $params = [];
        foreach ($this->keys as $i=>$key) {

            if($where) $where.=' AND ';
            $where.=" {$key[0]} IN ( ";
            $values = '';
            foreach ($keysWhere as $keyWhere) {
                if(!is_array($keyWhere)) $keyWhere = [$keyWhere];
                if($values) $values.=', ';
                if($key[1]=='int') $values.='%s';
                else $values.="'%s'";
                $params[] = $keyWhere[$i];
            }

            $where.= "{$values} )";
        }

        // Fields to returned
        if(!$fields) $sqlFields = $this->getSQLSelectFields();
        else {
            if(is_string($fields)) $fields = explode(',',$fields);
            $sqlFields = $this->getSQLSelectFields($fields);
        }

        // Query
        $SQL = "SELECT {$sqlFields} FROM {$this->entity_name} WHERE {$where}";
        if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));

        return $this->core->model->dbQuery($this->entity_name.' fetch by querys: '.json_encode($keysWhere),$SQL,$params);

    }


    /**
     * Active or deactive mapping of fields
     * @param bool $use
     */
    public function useMapping($use=true) {
        $this->use_mapping = $use;
    }

    /**
     * Add an error in the class
     */
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
        $this->core->errors->add(['DataSQL'=>$value]);
    }

}
