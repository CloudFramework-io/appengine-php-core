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
    private $limit = 0;
    private $order = '';
    private $joins = [];
    private $queryFields = '';
    private $queryWhere = [];
    private $view = null;

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
     * Return the mapped field namesdefined in the schema mapping
     * @return array
     */
    function getMappingFields() {
        return array_values($this->mapping);
    }

    /**
     * Return the fields ready for a SQL query
     * @param  array|null fields to show
     * @return array|null
     */
    function getSQLSelectFields($fields=null) {
        if(null === $fields || empty($fields)) {
            if($this->use_mapping)
                $fields = $this->getMappingFields();
            else
                $fields = $this->getFields();
        }
        if(!$this->use_mapping || !count($this->mapping)) {
            return $this->entity_name.'.'.implode(','.$this->entity_name.'.',$fields);
        }
        else {
            $ret = '';
            foreach ($this->mapping as $field=>$fieldMapped) {
                if(null != $fields && !in_array($fieldMapped,$fields)) continue;

                if($this->view && (!isset($this->entity_schema['mapping'][$fieldMapped]['views']) || !in_array($this->view,$this->entity_schema['mapping'][$fieldMapped]['views']))) continue;
                if($ret) $ret.=',';
                $ret .= "{$this->entity_name}.{$field} AS {$fieldMapped}";
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
            $where.=" {$this->entity_name}.{$key[0]} IN ( ";
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
        $sqlFields = $this->getQuerySQLFields($fields);
        $from = $this->getQuerySQLFroms();


        // Query
        $SQL = "SELECT {$sqlFields} FROM {$from} WHERE {$where}";
        if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));

        return $this->core->model->dbQuery($this->entity_name.' fetch by querys: '.json_encode($keysWhere),$SQL,$params);

    }

    /**
     * Set a limit in the select query or fetch method.
     * @param int $limit
     */
    function setLimit($limit) {
        $this->limit = intval($limit);
    }

    /**
     * Defines the fields to return in a query. If empty it will return all of them
     * @param $fields
     */
    function setQueryFields($fields) {
        $this->queryFields = $fields;
    }

    /**
     * Array with key=>value
     * Especial values:
     *              '__null__'
     *              '__notnull__'
     *              '__empty__'
     *              '__notempty__'
     * @param Array $keysWhere
     */
    function setQueryWhere($keysWhere) {
        if(empty($keysWhere) ) return($this->addError('setQueryWhere($keysWhere) $keyWhere can not be empty'));
        $this->queryWhere = $keysWhere;
    }

    /**
     * Return records from the db object
     * @param array $keysWhere
     * @param null $fields
     * @return array|void
     */
    function fetch($keysWhere=[], $fields=null) {

        if($this->error) return false;
        //--- WHERE
        // Array with key=>value or empty
        if(is_array($keysWhere) ) {
            list($where, $params) = $this->getQuerySQLWhereAndParams($keysWhere);
        }
        // String
        elseif(is_string($keysWhere) && !empty($keysWhere)) {
            $where =$keysWhere;
            $params = [];
        } else {
            return($this->addError('fetch($keysWhere,$fields=null) $keyWhere has a wrong value'));
        }

        // --- FIELDS
        $sqlFields = $this->getQuerySQLFields($fields);


        // --- QUERY
        $from = $this->getQuerySQLFroms();
        $SQL = "SELECT {$sqlFields} FROM {$from}";
        if($where) $SQL.=" WHERE {$where}";


        // --- ORDER BY
        if($this->order) $SQL.= " ORDER BY {$this->order}";
        if($this->limit) $SQL.= " limit {$this->limit}";

        if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));

        $ret= $this->core->model->dbQuery($this->entity_name.' fetch by querys: '.json_encode($keysWhere),$SQL,$params);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);
    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function update(&$data) {
        if(!is_array($data) ) return($this->addError('update($data) $data has to be an array with key->value'));

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('update($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbUpdate($this->entity_name.' update record: '.json_encode($data),$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function upsert(&$data) {
        if(!is_array($data) ) return($this->addError('upsert($data) $data has to be an array with key->value'));

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('upsert($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbUpSert($this->entity_name.' update record: '.json_encode($data),$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function insert(&$data) {
        if(!is_array($data) ) return($this->addError('insert($data) $data has to be an array with key->value'));

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('upsert($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbInsert($this->entity_name.' insert record: '.json_encode($data),$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }


    /** About Order */
    function unsetOrder() {$this->order='';}

    /**
     * Add Order into a query
     * @param $field
     * @param $type
     */
    function addOrder($field, $type) {

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            if(isset($this->entity_schema['mapping'][$field]['field'])) $field = $this->entity_schema['mapping'][$field]['field'];
        }

        if(isset($this->fields[$field]))  {
            if(strlen($this->order)) $this->order.=', ';
            $this->order.= $this->entity_name.'.'.$field.((strtoupper(trim($type))=='DESC')?' DESC':' ASC');
        } else {
            $this->addError($field.' does not exist to order by');
        }
    }


    function getQuerySQLWhereAndParams($keysWhere=[]) {
        if(!is_array($keysWhere) ) return($this->addError('getQuerySQLWhereAndParams($keysWhere) $keyWhere has to be an array with key->value'));

        // Where condition for the SELECT
        $where = ''; $params = [];
        if(!count($keysWhere)) $keysWhere = $this->queryWhere;

        foreach ($keysWhere as $key=>$value) {

            if($this->use_mapping) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('fetch($keysWhere, $fields=null) $keyWhere contains a wrong mapped key: '.$key));
                $key = $this->entity_schema['mapping'][$key]['field'];
            } else {
                if(!isset($this->fields[$key])) return($this->addError('fetch($keysWhere, $fields=null) $keyWhere contains a wrong key: '.$key));
            }
            if($where) $where.=' AND ';
            switch ($value) {
                case "__null__":
                    $where.="{$this->entity_name}.{$key} IS NULL";
                    break;
                case "__notnull__":
                    $where.="{$this->entity_name}.{$key} IS NOT NULL";
                    break;
                default:
                    // IN
                    if(is_array($value)) {
                        if($this->fields[$key]=='int') {
                            $where.="{$this->entity_name}.{$key} IN (%s)";
                            $params[] = implode(',',$value);
                        }
                        else {
                            $where.="{$this->entity_name}.{$key} IN ('%s')";
                            $params[] = implode("','",$value);

                        }
                    }
                    // =
                    else {
                        $op = '=';

                        // Add operators
                        if(strpos($value,'>=')===0) {
                            $op='>=';
                            $value = str_replace('>=','',$value);
                        }elseif(strpos($value,'<=')===0) {
                            $op='<=';
                            $value = str_replace('<=','',$value);
                        }elseif(strpos($value,'>')===0) {
                            $op='>';
                            $value = str_replace('>','',$value);
                        }elseif(strpos($value,'<')===0) {
                            $op='<';
                            $value = str_replace('<','',$value);
                        }

                        if($this->fields[$key]=='int') $where.="{$this->entity_name}.{$key} {$op} %s";
                        else $where.="{$this->entity_name}.{$key} {$op} '%s'";
                        $params[] = $value;
                    }

                    break;

            }

        }

        // Search into Joins quieries
        foreach ($this->joins as $join) {
            /** @var DataSQL $object */
            $object = $join[1];
            list($joinWhere,$joinParams) = $object->getQuerySQLWhereAndParams();
            if($joinWhere) {

                if($where) $where.=' AND ';
                $where.=$joinWhere;

                $params=array_merge($params,$joinParams);

            }
        }

        return [$where,$params];
    }

    function getQuerySQLFields($fields=null) {
        if(!$fields) $fields=$this->queryFields;
        if($fields && is_string($fields)) $fields = explode(',',$fields);

        $ret =  $this->getSQLSelectFields($fields);

        foreach ($this->joins as $join) {

            /** @var DataSQL $object */
            $object = $join[1];
            $ret.=','.$object->getQuerySQLFields();

        }

        return $ret;
    }

    function getQuerySQLFroms() {
        $from = $this->entity_name;
        foreach ($this->joins as $join) {
            /** @var DataSQL $object */
            $object = $join[1];
            $from.=" {$join[0]} JOIN {$object->entity_name} ON ($join[2])";
        }
        return $from;
    }

    /**
     * Active or deactive mapping of fields
     * @param bool $use
     */
    public function useMapping($use=true) {
        $this->use_mapping = $use;
    }

    public function setView($view) {
        if(!is_string($view) && null !==$view) return($this->addError('setView($view), Wrong value'));

        $this->view = $view;
    }

    /**
     * @param $type Could be inner or left
     * @param DataSQL $object
     * @param $on
     */
    function join ($type, DataSQL &$object, $on) {
        $this->joins[] = [$type,$object,$on];
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

    function getDBQuery() {
        if(!is_object($this->core->model->db)) return null;
        
        return($this->core->model->db->getQuery());
    }



}