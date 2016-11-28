<?php
// Models Class v1
if (!defined ("_Models_CLASS_") ) {
    define("_Models_CLASS_", TRUE);

    class Models
    {
        private $core;
        var $models;
        var $error = false;
        var $errorMsg = [];
        var $apidoc=[];
        function __construct (Core &$core,$models=null)
        {
            $this->core = $core;
            if(is_array($models)) $this->loadModels($models);
        }

        /**
         * Process array of models based on a specify structure. The array has to have the following structure:
         * [{"table_name_1}:{"model":{"field1":[properties],"field2":[properties],..},"mapping":{"field1":{properties},..}},{}]
         *
         * @param $models
         */
        public function loadModels(&$models) {

            // Verifiy we receive an array
            if(!is_array($models)) return($this->addError("loadModels requires an array"));

            // Start saving the models
            foreach ($models as $table=>$info) {
                if(!is_array($info['model'])) return($this->addError("({$table}) does not have 'model' attribute"));
                $this->models[$table] = $info;

                // Processing mapping and creating sqlalias
                if(is_array($info['mapping'])) {
                    foreach ($info['mapping'] as $field_map=>$map)  {
                        if(!empty($field = $map['field'])) {
                            if (!isset($info['model'][$field])) return ($this->addError("({$field_map}) is mapped with {$field} and it does no exist in the model"));
                            $this->models[$table]['sqlalias'][$field_map] = "{$table}.{$field} AS {$field_map}";
                        } else {
                            $this->models[$table]['sqlalias'][$field_map] = "null AS {$field_map}";
                        }
                    }
                } else foreach ($info['model'] as $field_map=>$map) {
                    $this->models[$table]['sqlalias'][$field_map] = $table.'.'.$field_map;
                }
            }
        }

        /**
         * Return a string of SQL fields with the names Mapped
         * @param $model
         * @param null $fields
         * @return null|string
         */
        public function getSQLMappedFields($model, $fields=null) {
            if(!isset($this->models[$model]) || !is_array($this->models[$model]['model'])) return($this->addError("getSQLMappedFields. model ({$model}) does not exist os has a wrong structure."));
            if(!is_array($fields)) $fields = array_keys($this->models[$model]['sqlalias']);
            $map=[];
            foreach ($fields as $field) {
                if (!isset($this->models[$model]['sqlalias'][$field])) return ($this->addError("getSQLMappedFields. ({$field}) is not mapped for sql query"));
                $map[] = $this->models[$model]['sqlalias'][$field];
            }
            if(!count($map)) return($this->addError('getSQLMappedFields. No fields has been passed'));

            // Return fields separated by ','
            return implode(',',$map);
        }

        /**
         * Return the array with the mapping
         * @param $model
         * @return Array
         */
        public function getModelMapping($model) {
            if(!isset($this->models[$model]) || !is_array($this->models[$model]['mapping'])) return($this->addError("getSQLMappedFields. model ({$model}) does not exist os has a wrong structure."));
            return $this->models[$model]['mapping'];
        }

        /**
         * Return the array with the mapping
         * @param $model
         * @return Array
         */
        public function getSQLRecordToUpdate($model,&$data) {
            if(!isset($this->models[$model]) || !is_array($this->models[$model]['mapping'])) return($this->addError("getSQLMappedFields. model ({$model}) does not exist os has a wrong structure."));
            $record = [];
            foreach ($data as $key=>$value) {
                if (isset($this->models[$model]['mapping'][$key])
                    && $this->models[$model]['mapping'][$key]['field']
                    && (strpos($this->models[$model]['mapping'][$key]['validation'], 'internal') === false || strpos($this->models[$model]['mapping'][$key]['validation'], 'hidden') === false )
                ) {

                    $record[$this->models[$model]['mapping'][$key]['field']] = $value;
                    // If the record is an array (it is not supported in DB), let's convert it in JSON
                    if(is_array($record[$this->models[$model]['mapping'][$key]['field']])) $record[$this->models[$model]['mapping'][$key]['field']]  = json_encode($record[$this->models[$model]['mapping'][$key]['field']] );
                }


            }
            return $record;
        }
        /**
         * Return the array with the mapping
         * @param $model
         * @return Array
         */
        public function getSQLRecordToInsert($model,&$data) {
            if(!isset($this->models[$model]) || !is_array($this->models[$model]['mapping'])) return($this->addError("getSQLRecordToInsert. model ({$model}) does not exist os has a wrong structure."));
            $record = [];
            foreach ($this->models[$model]['mapping'] as $key=>$value) if($value['field']){
                if(preg_match('/(\||^)trigger(\||$)/',$value['validation'])) continue;

                if(preg_match('/(\||^)key(\||$)/',$value['validation'])) continue;
                if (!isset($data[$key]) && preg_match('/(\||^)optional(\||$)/',$value['validation']) ) continue;

                $record[$value['field']] = (preg_match('/(\||^)internal(\||$)/',$value['validation']))?null:$data[$key];

                // If the record is an array (it is not supported in DB), let's convert it in JSON
                if(is_array($record[$value['field']] )) $record[$value['field']]  = json_encode($record[$value['field']] );
            }
            return $record;
        }
        /**
         * Output mapped field in the output with APIDOC format
         * @param $model
         * @return null|array
         */
        public function getApiDocFields($model,$field_name='',$type='Success') {
            if(isset($this->apidoc[$model])) return;
            $this->apidoc[$model] = true;
            $apidocs =[];

            if(!isset($this->models[$model]) || !is_array($this->models[$model]['model'])) return($this->addError("getModifiableFields. model ({$model}) does not exist os has a wrong structure."));
            $map="    *\n";
            if(!$field_name) $field_name = 'Fields';
            foreach ($this->models[$model]['mapping'] as $field=>$value) {
                if($type!='Success' && isset($value['validation']) && strpos($value['validation'],'internal')!==false ) continue;
                unset($value['field']);

                $field_type = ucfirst($value['type']);
                $description = $value['validation'];

                if($type=='Success' && isset($value['relatioship']) && isset($value['relatioship']['model']) && isset($this->models[$value['relatioship']['model']]['mapping'])) {
                    $description = 'See: ('.$field_name.'/'.$field.'). '.$description;
                    $apidocs[$value['relatioship']['model']]=$field_name.'/'.$field;
                }
                $map.="    * @api{$type} ({$field_name}) {{$field_type}} {$field} {$description}\n";

            }

            // Show other relations
            foreach ($apidocs as $key=>$apidoc) {
                $map.= $this->getApiDocFields($key,$apidoc);
            }
            $map.="    *\n";

            return($map);
        }




        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }
    }
}