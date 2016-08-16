<?php
use google\appengine\datastore\v4\BeginTransactionRequest;
use google\appengine\datastore\v4\BeginTransactionResponse;
use google\appengine\datastore\v4\CommitRequest;
use google\appengine\datastore\v4\CommitRequest\Mode;
use google\appengine\datastore\v4\CommitResponse;
use google\appengine\datastore\v4\Key;
use google\appengine\datastore\v4\LookupRequest;
use google\appengine\datastore\v4\LookupResponse;
use google\appengine\datastore\v4\PropertyFilter;
use google\appengine\datastore\v4\QueryResultBatch;
use google\appengine\datastore\v4\RunQueryRequest;
use google\appengine\datastore\v4\RunQueryResponse;
use google\appengine\datastore\v4\Value;
use google\appengine\runtime\ApiProxy;
use google\appengine\runtime\ApplicationError;
use google\net\ProtocolMessage;
use google\appengine\datastore\v4\PropertyFilter\Operator;
use google\appengine\datastore\v4\PropertyOrder\Direction;
use google\appengine\datastore\v4\EntityResult;

// CloudSQL Class v10
if (!defined ("_DATASTORE_CLASS_") ) {
    define("_DATASTORE_CLASS_", TRUE);

    class DataStoreTypes {
        const key = 'key';
        const keyname = 'keyname';
        const boolean = 'boolean';
        const integer =  'integer';
        const date =  'date';
        const datetime =  'datetime';
        const float =  'float';
        const list_elements =  'list';
        const geo =  'bool';
    }

    class DataStore
    {
        var $error = false;
        var $errorMsg = '';
        var $store = null;
        var $entity_schema = null;
        var $entity_name = null;
        var $entity_gw = null;
        var $schema = [];
        var $lastQuery = '';
        var $core = null;
        var $types = null;
        var $limit = 0;
        var $page = 0;
        var $cursor = '';
        var $last_cursor;
        var $time_zone = 'UTC';
        var $cache = null;
        var $namespace = 'default';

        function __construct(Core &$core, $params)
        {
            
            $this->core = $core;
            $this->cache = new CoreCache();
            $this->cache->setSpaceName('CF_DATASTORE');

            $entity = $params[0];
            $namespace = (isset($params[1]))?$params[1]:null;
            $schema = (isset($params[2]))?$params[2]:null;

            $this->core->__p->add('DataStore new instance ', '', 'note');
            $this->entity_name = $entity;
            $this->entity_schema = $this->getEntitySchema($entity, $schema);
            if(!$this->error) {
                if (null !== $namespace && strlen($namespace)) {
                    $this->namespace = $namespace;
                    $this->entity_gw = new ProtoBuf(null, $namespace);
                }
                $this->store = new Store($this->entity_schema, $this->entity_gw);
            }
            $this->core->__p->add('DataStore new instance ', '', 'endnote');


        }

        /**
         * Creating  entities based in the schema
         * @param $data
         * @return array|bool
         */
        function createEntities($data)
        {
            if ($this->error) return false;
            $ret = [];
            $entities = [];

            if (!is_array($data)) $this->setError('No data received');
            else {
                $this->core->__p->add('createEntities: ', $this->entity_name, 'note');

                // converting $data into n,n dimmensions
                if (!is_array($data[0])) $data = [$data];

                // loop the array into $row
                foreach ($data as $i => $row) {
                    $record = [];
                    $schema_key = null;
                    $schema_keyname = null;

                    // Loading info from Data. $i can be numbers 0..n or indexes.
                    foreach ($row as $i => $value) {

                        //$i = strtolower($i);  // Let's work with lowercase

                        // If the key or keyname is passed instead the schema key|keyname let's create
                        if(strtolower($i)=='key' || strtolower($i)=='keyid' || strtolower($i)=='keyname')  {
                            $this->schema['props'][$i][0] = $i;
                            $this->schema['props'][$i][1] = (strtolower($i)=='keyname')?strtolower($i):'key';
                        }

                        // Only use those variables that appears in the schema except key && keyname
                        if(!isset($this->schema['props'][$i])) continue;

                        // if the field is key or keyname feed $schema_key or $schema_keyname
                        if ($this->schema['props'][$i][1] == 'key') {
                            $schema_key =  preg_replace('/[^0-9]/','' ,$value );
                            if(!strlen($schema_key)) $this->setError('wrong Key value');

                        } elseif ($this->schema['props'][$i][1] == 'keyname') {
                            $schema_keyname = $value;

                        // else explore the data.
                        } else {
                            if (is_string($value)) {
                                // date & datetime values
                                if ($this->schema['props'][$i][1] == 'date' || $this->schema['props'][$i][1] == 'datetime' || $this->schema['props'][$i][1] == 'datetimeiso') {
                                    if(strlen($value)) {
                                        try {
                                            $value_time = new DateTime($value);
                                            $value = $value_time;
                                        } catch (Exception $e) {
                                            $ret[] = ['error' => 'field {' . $this->schema['props'][$i][0] . '} has a wrong date format: ' . $value];
                                            $record = [];
                                            break;
                                        }
                                    }
                                // geo values
                                } elseif($this->schema['props'][$i][1] == 'geo') {
                                    if(!strlen($value)) $value='0.00,0.00';
                                    list($lat,$long) = explode(',',$value,2);
                                    $value = new Geopoint($lat,$long);
                                } elseif($this->schema['props'][$i][1] == 'json') {
                                    if(!strlen($value)) {
                                        $value='{}';
                                    } else {
                                        json_decode($value); // Let's see if we receive a valid JSON
                                        if (json_last_error() !== JSON_ERROR_NONE) $value = json_encode($value, JSON_PRETTY_PRINT);
                                    }
                                }
                            } else {
                                if($this->schema['props'][$i][1] == 'json') {
                                    if (is_array($value) || is_object($value)) {
                                        $value = json_encode($value, JSON_PRETTY_PRINT);
                                    } elseif (!strlen($value)) {
                                        $value = '{}';
                                    } 
                                }
                            }

                            $record[$this->schema['props'][$i][0]] = $value;
                        }
                    }


                    //Complete info in the rest of inf
                    if(!$this->error)
                    if (count($record)) {
                        try {
                            $entity = $this->store->createEntity($record);
                            if (null !== $schema_key) {
                                $entity->setKeyId($schema_key);
                            } elseif(null !== $schema_keyname) {
                                $entity->setKeyName($schema_keyname);
                            }
                            // Add this entity to insert
                            $entities[] = $entity;
                        } catch (Exception $e) {
                            $this->setError($e->getMessage());
                            $ret = false;
                        }

                    } else {
                        $this->setError('Structure of the data does not match with schema');
                    }
                }
            }

            // Bulk insertion
            if(!$this->setError && count($entities)) try {

                $this->deleteCache(); // Delete Cache for next queries..
                // The limit for bulk inserting is 500 records.
                $entities = (array_chunk($entities,500));
                foreach ($entities as &$entity) {
                    $this->store->upsert($entity);
                }
                $ret = [];
                /** @var Entity $entity */
                foreach ($entities as &$entities_chunck) {
                    foreach ($entities_chunck as &$entity) {
                        $row = $entity->getData();

                        foreach ($row as $key=>$value) {

                            // Update Types: Geppoint, JSON, Datetime
                            if ($value instanceof Geopoint)
                                $row[$key] = $value->getLatitude() . ',' . $value->getLongitude();
                            elseif ($key == 'JSON')
                                $row[$key] = json_decode($value, true);
                            elseif($value instanceof DateTime) {
                                if($this->schema['props'][$key][1]=='date')
                                    $row[$key] = $value->format('Y:m:d');
                                elseif($this->schema['props'][$key][1]=='datetime')
                                    $row[$key] = $value->format('Y:m:d H:i:s e');
                                elseif($this->schema['props'][$key][1]=='datetimeiso')
                                    $row[$key] = $value->format('c');
                            }


                        }
                        // Return the Keys
                        if(null !== $entity->getKeyId())
                            $row['KeyId'] = $entity->getKeyId();
                        else
                            $row['KeyName'] = $entity->getKeyName();
                        $ret[] = $row;
                    }
                }

            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $ret = false;
            }

            $this->core->__p->add('createEntities: ', '', 'endnote');
            return ($ret);
        }

        // Return string or GDS/Schema if we receive an array of schema
        // format:
        // { "field1":["type"(,"index|..other validations")]
        // { "field2":["type"(,"index|..other validations")]
        function getEntitySchema($entity, $schema)
        {
            $ret = $entity;
            $this->schema['data'] = (is_array($schema)) ? $schema : [];
            $this->schema['props'] = ['__fields'=>[]];

            if (is_array($schema)) {
                $ret = (new Schema($entity));
                $i = 0;
                if(isset($this->schema['data']['model']) && is_array($this->schema['data']['model'])) $data = $this->schema['data']['model'];
                else $data = $this->schema['data'];
                foreach ($data as $key => $props) {
                    if(!strlen($key)) {
                        $this->setError('Schema of '.$entity.' with empty key');
                        return false;
                    } elseif($key=='__model') {
                        $this->setError('Schema of '.$entity.' with not allowd key: __model');
                        return false;
                    }
                    if (!is_array($props)) $props = ['string', ''];
                    else $props[0] = strtolower($props[0]);
                    // true / false index
                    if(isset($props[1]))
                        $index = (stripos($props[1],'index')!== false);
                    else $index = false;
                    switch ($props[0]) {
                        case "integer":
                            $ret->addInteger($key, $index);
                            break;
                        case "key":
                        case "keyname":
                            break;
                        case "date":
                        case "datetime":
                        case "datetimeiso":
                        case "datetimeiso":
                            $ret->addDatetime($key, $index);
                            break;
                        case "float":
                            $ret->addFloat($key, $index);
                            break;
                        case "boolean":
                        case "bool":
                            $ret->addBoolean($key, $index);
                            break;
                        case "list":
                        case "emails":
                            $ret->addStringList($key, $index);
                            break;
                        case "geo":
                            $ret->addGeopoint($key, $index);
                            break;
                        case "json":
                            $ret->addString($key, false);
                            break;
                        case "zip":
                            $ret->addString($key, false);
                            break;
                        default:
                            $ret->addString($key, $index);
                            break;
                    }
                    $this->schema['props'][$i++] = [$key, $props[0], $props[1]];
                    $this->schema['props'][$key] = [$key, $props[0], $props[1]];
                    $this->schema['props']['__model'][$key] = ['type'=> $props[0], 'validation'=>$props[1]];

                }
            }
            return $ret;
        }

        /**
         * Fill an array based in the model structure and  mapped data
         * @param $data
         * @param array $dictionaries
         * @return array
         */
        function getCheckedRecordWithMapData($data, $all=true, &$dictionaries=[]) {
            $entity = array_flip(array_keys($this->schema['props']['__model']));

            // If there is not mapdata.. Use the model fields to mapdata
            if(!isset($this->schema['data']['mapData']) || !count($this->schema['data']['mapData'])) {
                foreach ($entity as $key=>$foo) {
                    $this->schema['data']['mapData'][$key] = $key;
                }
            }

            // Explore the entity
            foreach ($entity as $key=>$foo) {
                $key_exist = true;
                if(isset($this->schema['data']['mapData'][$key])) {
                    $array_index = explode('.',$this->schema['data']['mapData'][$key]); // Find potential . array separators
                    if(isset($data[$array_index[0]])) {
                        $value = $data[$array_index[0]];
                    } else {
                        $value = null;
                        $key_exist = false;
                    }
                    $value = (isset($data[$array_index[0]]))?$data[$array_index[0]]:'';
                    // Explore potential subarrays
                    for($i=1,$tr=count($array_index);$i<$tr;$i++) {
                        if(isset($value[$array_index[$i]]))
                            $value = $value[$array_index[$i]];
                        else {
                            $key_exist = false;
                            $value = null;
                            break;
                        }
                    }
                    // Assign Value
                    $entity[$key] = $value;
                } else {
                    $key_exist = false;
                    $entity[$key] = null;
                }
                if(!$key_exist && !$all ) unset($entity[$key]);

            }

            /* @var $dv DataValidation */
            $dv = $this->core->loadClass('DataValidation');
            if(!$dv->validateModel($this->schema['props']['__model'],$entity,$dictionaries,$all)) {
                $this->setError('Error validating Data in Model.: {'.$dv->field.'}. '.$dv->errorMsg);
            }

            return ($entity);
        }

        function getFormModelWithMapData() {
            $entity = $this->schema['props']['__model'];
            foreach ($entity as $key=>$attr) {
                if(!isset($attr['validation']))
                    unset($entity[$key]['validation']);
                elseif(strpos($attr['validation'],'hidden')!==false)
                    unset($entity[$key]);
            }
            return ($entity);
        }

        function transformEntityInMapData($entity) {
            $map = $this->schema['data']['mapData'];
            $transform = [];


            if(!is_array($map)) $transform = $entity;
            else foreach ($map as $key=>$item) {
                $array_index = explode('.',$item); // Find potental . array separators
                if(count($array_index) == 1) $transform[$array_index[0]] = (isset($entity[$key]))?$entity[$key]:'';
                elseif(!isset($transform[$array_index[0]])) {
                    $transform[$array_index[0]] = [];
                }

                for($i=1,$tr=count($array_index);$i<$tr;$i++) {
                    $transform[$array_index[0]][$array_index[$i]] = (isset($entity[$key]))?$entity[$key]:'';
                }

            }
            return $transform;
        }
        
        

        function fetchOne($fields = '*', $where = null, $order = null)
        {
            return $this->fetch('one', $fields, $where, $order);
        }

        function fetchAll($fields = '*', $where = null, $order = null)
        {
            return $this->fetch('all', $fields, $where, $order, null);
        }

        function fetchLimit($fields = '*', $where = null, $order = null, $limit = null)
        {
            if (!strlen($limit)) $limit = 100;
            else $limit = intval($limit);
            return $this->fetch('all', $fields, $where, $order, $limit);
        }

        function fetch($type = 'one', $fields = '*', $where = null, $order = null, $limit = null)
        {
            if ($this->error) return false;
            $this->core->__p->add('fetch: ', $type . ' fields:' . $fields . ' where:' . $where . ' order:' . $order . ' limit:' . $limit, 'note');
            $ret = [];
            if (!strlen($fields)) $fields = '*';
            if (!strlen($limit)) $limit = $this->limit;

            // FIX when you work on a local environment
            if($this->core->is->development() && $fields!='*') $fields='*';


            $_q = 'SELECT ' . $fields . ' FROM ' . $this->entity_name;

            // Where construction
            if (is_array($where)) {
                $i = 0;
                foreach ($where as $key => $value) {
                    if ($i == 0) $_q .= " WHERE $key = @{$key}";
                    else $_q .= " AND $key = @{$key}";
                    $i++;
                }
            } elseif (strlen($where)) {
                $_q .= " WHERE $where";
                $where = null;
            }
            if (strlen($order)) $_q .= " ORDER BY $order";

            $this->lastQuery = $_q . ((is_array($where)) ? ' ' . json_encode($where) : '') . ' limit=' . $limit.' page='.$this->page;


            try {
                if ($type == 'one') {
                    $data = [$this->store->fetchOne($_q, $where)];
                    if (is_array($data))
                        foreach ($data as $record) if(is_object($record)) {
                            // GeoData Transformation
                            foreach ($record->getData() as $key=>$value)
                                if($value instanceof Geopoint)
                                    $record->{$key} = $value->getLatitude().','.$value->getLongitude();
                                elseif($key=='JSON')
                                    $record->{$key} = json_decode($value,true);
                                elseif ($this->schema['props'][$key][1] == 'date') $record->{$key} = $value->format('Y-m-d');
                                elseif ($this->schema['props'][$key][1] == 'datetime') $record->{$key} = $value->format('Y-m-d H:i:s e');
                                elseif ($this->schema['props'][$key][1] == 'datetimeiso') $record->{$key} = $value->format('c');cd -

                            $subret = (null !== $record->getKeyId())?['KeyId' => $record->getKeyId()]:['KeyName' => $record->getKeyName()];
                            $ret[] = array_merge($subret, $record->getData());
                        }
                } else {
                    $this->store->query($_q, $where);
                    // page size
                    $blocksOfEntities = 300;  // Maxium group of records per datastore call
                    $init = false;

                    if ($limit > 0 && $limit < $blocksOfEntities) $blocksOfEntities = $limit;
                    $tr = 0;
                    do {
                        if(!$init) {
                            if(strlen($this->cursor))
                                $data = $this->store->fetchPage($blocksOfEntities, base64_decode($this->cursor));
                            else
                                $data = $this->store->fetchPage($blocksOfEntities, $this->page * $limit);
                            $init = true;
                        } else {
                            $data = $this->store->fetchPage($blocksOfEntities);
                        }
                        $this->last_cursor = base64_encode($this->store->str_last_cursor);

                        if (is_array($data))
                            foreach ($data as $record) {
                                // GeoData Transformation
                                foreach ($record->getData() as $key=>$value)
                                    if($value instanceof Geopoint)
                                        $record->{$key} = $value->getLatitude().','.$value->getLongitude();
                                    elseif($key=='JSON')
                                        $record->{$key} = json_decode($value,true);
                                    elseif ($this->schema['props'][$key][1] == 'date') $record->{$key} = $value->format('Y-m-d');
                                    elseif ($this->schema['props'][$key][1] == 'datetime') $record->{$key} = $value->format('Y-m-d H:i:s e');
                                    elseif ($this->schema['props'][$key][1] == 'datetimeiso') $record->{$key} = $value->format('c');

                                $subret = (null !== $record->getKeyId())?['KeyId' => $record->getKeyId()]:['KeyName' => $record->getKeyName()];
                                $ret[] = array_merge($subret, $record->getData());
                                $tr++;
                                if ($limit > 0 && $tr == $limit) break;
                            }
                        if ($limit > 0) {
                            if ($tr >= $limit) $data = null;
                            elseif (($tr + $blocksOfEntities) >= $limit) $blocksOfEntities = $limit - $tr;
                        }

                    } while ($data);
                }
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('fetch');

            }
            $this->core->__p->add('fetch: ', '', 'endnote');
            return $ret;
        }


        function fetchKeys($keys) {
            return $this->fetchByKeys($keys);
        }
        function fetchByKeys($keys)
        {
            $keyType = 'key';
            if(!is_array($keys)) $keys= explode(',',$keys);

            if((is_array($this->schema) && strpos(json_encode($this->schema),'keyname' )!==false) || preg_match('/[^0-9]/',$keys[0] )) {
                $keyType='keyname';
            }
            // Are keys or names
            $ret = [];
            try {
                if($keyType=='key') {
                    $data = $this->store->fetchByIds($keys);
                } else {
                    // DOES NOT SUPPORT keys with ',' as values.
                    foreach ($keys as &$key) $key = preg_replace('/(\'|")/','',$key);
                    $data = $this->store->fetchByNames($keys);
                }
                $ret = $this->transformEntities($data);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('query');

            }

            $this->lastQuery = $this->store->str_last_query;
            return $ret;
        }

        function fetchCount($where = null,$distinct='__key__')
        {
            $hash = sha1(json_encode($where).$distinct);
            $totals = $this->cache->get($this->entity_name.'_'.$this->namespace.'_totals');
            if(is_array($totals) && isset($totals[$hash])) {
                $this->core->logs->add('Returning from cache: count from '.$this->entity_name.' where '.json_encode($where));
                return($totals[$hash]);
            } else {
                $data = $this->fetchAll($distinct,$where);
                $totals[$hash] = count($data);
                $this->cache->set($this->entity_name.'_'.$this->namespace.'_totals',$totals);
                return($totals[$hash]);
            }
        }

        function deleteCache() {
            $this->cache->delete($this->entity_name.'_'.$this->namespace.'_totals');
        }

        function delete($where){
            $_q = 'SELECT __key__ FROM ' . $this->entity_name;

            // Where construction
            if (is_array($where)) {
                $i = 0;
                foreach ($where as $key => $value) {
                    if ($i == 0) $_q .= " WHERE $key = @{$key}";
                    else $_q .= " AND $key = @{$key}";
                    $i++;
                }
            } elseif (strlen($where)) {
                $_q .= " WHERE $where";
                $where = null;
            }
            $this->store->query($_q, $where);
            $this->lastQuery = $this->store->str_last_query;
            
            $data = $this->store->fetchAll($_q, $where);
            if(!count($data)) return [];
            if($this->store->delete($data)) {
                $this->deleteCache();
                return($this->transformEntities($data));
            } else {
                return false;
            }
        }

        function deleteByKeys($keys) {

        }

        function query($q, $data)
        {
            $ret = [];
            try {
                $this->store->query($q, $data);
                $data = $this->store->fetchAll($q, $data);
                $ret = $this->transformEntities($data);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('query');

            }
            return $ret;
        }
        private function transformEntities(&$data) {
            $ret = [];
            foreach ($data as $record) {
                // GeoData Transformation
                foreach ($record->getData() as $key=>$value)
                    if($value instanceof Geopoint)
                        $record->{$key} = $value->getLatitude().','.$value->getLongitude();
                    elseif($key=='JSON')
                        $record->{$key} = json_decode($value,true);
                    elseif ($this->schema['props'][$key][1] == 'date') $record->{$key} = $value->format('Y-m-d');
                    elseif ($this->schema['props'][$key][1] == 'datetime') $record->{$key} = $value->format('Y-m-d H:i:s e');
                    elseif ($this->schema['props'][$key][1] == 'datetimeiso') $record->{$key} = $value->format('c');

                $subret = (null !== $record->getKeyId())?['KeyId' => $record->getKeyId()]:['KeyName' => $record->getKeyName()];
                $ret[] = array_merge($subret, $record->getData());
            }
            return $ret;

        }

        function setError($value)
        {
            $this->errorMsg = [];
            $this->addError($value);
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
            $this->core->errors->add(['DataStore'=>$value]);
        }

    }


    class Store
    {

        /**
         * The GDS Gateway we're going to use
         *
         * @var Gateway
         */
        private $obj_gateway = null;

        /**
         * The GDS Schema defining the Entity we're operating with
         *
         * @var Schema
         */
        private $obj_schema = null;

        /**
         * The last GQL query
         *
         * @var string|null
         */
        var $str_last_query = null;

        /**
         * Named parameters for the last query
         *
         * @var array|null
         */
        private $arr_last_params = null;

        /**
         * The last result cursor
         *
         * @var string|null
         */
        var $str_last_cursor = null;

        /**
         * Transaction ID
         *
         * @var null|string
         */
        private $str_transaction_id = null;

        /**
         * Gateway and Schema/Kind can be supplied on construction
         *
         * @param Schema|string|null $kind_schema
         * @param Gateway $obj_gateway
         * @throws \Exception
         */
        public function __construct($kind_schema = null, Gateway $obj_gateway = null)
        {
            $this->obj_schema = $this->determineSchema($kind_schema);
            $this->obj_gateway = (null === $obj_gateway) ? new ProtoBuf() : $obj_gateway;
            $this->str_last_query = 'SELECT * FROM `' . $this->obj_schema->getKind() . '` ORDER BY __key__ ASC';
        }

        /**
         * Set up the Schema for the current data model, based on the provided Kind/Schema/buildSchema
         *
         * @param Schema|string|null $mix_schema
         * @return Schema
         * @throws \Exception
         */
        private function determineSchema($mix_schema)
        {
            if (null === $mix_schema) {
                $mix_schema = $this->buildSchema();
            }
            if ($mix_schema instanceof Schema) {
                return $mix_schema;
            }
            if (is_string($mix_schema)) {
                return new Schema($mix_schema);
            }
            throw new \Exception('You must provide a Schema or Kind. Alternatively, you can extend Store and implement the buildSchema() method.');
        }

        /**
         * Write one or more new/changed Entity objects to the Datastore
         *
         * @todo Consider returning the input
         *
         * @param Entity|Entity[]
         */
        public function upsert($entities)
        {
            if ($entities instanceof Entity) {
                $entities = [$entities];
            }
            $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->consumeTransaction())
                ->putMulti($entities);
        }

        /**
         * Delete one or more Model objects from the Datastore
         *
         * @param mixed
         * @return bool
         */
        public function delete($entities)
        {
            if ($entities instanceof Entity) {
                $entities = [$entities];
            }
            return $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->consumeTransaction())
                ->deleteMulti($entities);
        }

        /**
         * Fetch a single Entity from the Datastore, by it's Key ID
         *
         * Only works for root Entities (i.e. those without parent Entities)
         *
         * @param $str_id
         * @return Entity|null
         */
        public function fetchById($str_id)
        {
            return $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->fetchById($str_id);
        }

        /**
         * Fetch multiple entities by Key ID
         *
         * @param $arr_ids
         * @return Entity[]
         */
        public function fetchByIds(array $arr_ids)
        {
            return $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->fetchByIds($arr_ids);
        }

        /**
         * Fetch a single Entity from the Datastore, by it's Key Name
         *
         * Only works for root Entities (i.e. those without parent Entities)
         *
         * @param $str_name
         * @return Entity|null
         */
        public function fetchByName($str_name)
        {
            return $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->fetchByName($str_name);
        }

        /**
         * Fetch one or more Entities from the Datastore, by their Key Name
         *
         * Only works for root Entities (i.e. those without parent Entities)
         *
         * @param $arr_names
         * @return Entity|null
         */
        public function fetchByNames(array $arr_names)
        {
            return $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->fetchByNames($arr_names);
        }

        /**
         * Fetch Entities based on a GQL query
         *
         * Supported parameter types: String, Integer, DateTime, Entity
         *
         * @param $str_query
         * @param array|null $arr_params
         * @return $this
         */
        public function query($str_query, $arr_params = null)
        {
            $this->str_last_query = $str_query;
            $this->arr_last_params = $arr_params;
            $this->str_last_cursor = null;
            return $this;
        }

        /**
         * Fetch ONE Entity based on a GQL query
         *
         * @param $str_query
         * @param array|null $arr_params
         * @return Entity
         */
        public function fetchOne($str_query = null, $arr_params = null)
        {
            if (null !== $str_query) {
                $this->query($str_query, $arr_params);
            }
            $arr_results = $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->gql($this->str_last_query . ' LIMIT 1', $this->arr_last_params);
            return count($arr_results) > 0 ? $arr_results[0] : null;
        }

        /**
         * Fetch Entities (optionally based on a GQL query)
         *
         * @param $str_query
         * @param array|null $arr_params
         * @return Entity[]
         */
        public function fetchAll($str_query = null, $arr_params = null)
        {
            if (null !== $str_query) {
                $this->query($str_query, $arr_params);
            }
            $arr_results = $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->gql($this->str_last_query, $this->arr_last_params);
            return $arr_results;
        }

        /**
         * Fetch (a page of) Entities (optionally based on a GQL query)
         *
         * @param $int_page_size
         * @param null $mix_offset
         * @return Entity[]
         */
        public function fetchPage($int_page_size, $mix_offset = null)
        {
            $str_offset = '';
            $arr_params = (array)$this->arr_last_params;
            $arr_params['intPageSize'] = $int_page_size;
            if (null !== $mix_offset) {
                if (is_int($mix_offset)) {
                    $str_offset = 'OFFSET @intOffset';
                    $arr_params['intOffset'] = $mix_offset;
                } else {
                    $str_offset = 'OFFSET @startCursor';
                    $arr_params['startCursor'] = $mix_offset;
                }
            } else if (strlen($this->str_last_cursor) > 1) {
                $str_offset = 'OFFSET @startCursor';
                $arr_params['startCursor'] = $this->str_last_cursor;
            }
            $arr_results = $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->gql($this->str_last_query . " LIMIT @intPageSize {$str_offset}", $arr_params);
            $this->str_last_cursor = $this->obj_gateway->getEndCursor();
            return $arr_results;
        }

        /**
         * Fetch all of the entities in a particular group
         *
         * @param Entity $obj_entity
         * @return Entity[]
         */
        public function fetchEntityGroup(Entity $obj_entity)
        {
            $arr_results = $this->obj_gateway
                ->withSchema($this->obj_schema)
                ->withTransaction($this->str_transaction_id)
                ->gql("SELECT * FROM `" . $this->obj_schema->getKind() . "` WHERE __key__ HAS ANCESTOR @ancestorKey", [
                    'ancestorKey' => $obj_entity
                ]);
            $this->str_last_cursor = $this->obj_gateway->getEndCursor();
            return $arr_results;
        }

        /**
         * Get the last result cursor
         *
         * @return null|string
         */
        public function getCursor()
        {
            return $this->str_last_cursor;
        }

        /**
         * Set the query cursor
         *
         * Usually before continuing through a paged result set
         *
         * @param $str_cursor
         * @return $this
         */
        public function setCursor($str_cursor)
        {
            $this->str_last_cursor = $str_cursor;
            return $this;
        }

        /**
         * Create a new instance of this GDS Entity class
         *
         * @param array|null $arr_data
         * @return Entity
         */
        public final function createEntity($arr_data = null)
        {
            $obj_entity = $this->obj_schema->createEntity();
            if (null !== $arr_data) {
                foreach ($arr_data as $str_property => $mix_value) {
                    $obj_entity->__set($str_property, $mix_value);
                }
            }
            return $obj_entity;
        }

        /**
         * Set the class to use when instantiating new Entity objects
         *
         * Must be Entity, or a sub-class of it
         *
         * This method is here to maintain backwards compatibility. The Schema is responsible in 2.0+
         *
         * @param $str_class
         * @return $this
         * @throws \Exception
         */
        public function setEntityClass($str_class)
        {
            $this->obj_schema->setEntityClass($str_class);
            return $this;
        }

        /**
         * Begin a transaction
         *
         * @param bool $bol_cross_group
         * @return $this
         */
        public function beginTransaction($bol_cross_group = FALSE)
        {
            $this->str_transaction_id = $this->obj_gateway->beginTransaction($bol_cross_group);
            return $this;
        }

        /**
         * Clear and return the current transaction ID
         *
         * @return string|null
         */
        private function consumeTransaction()
        {
            $str_transaction_id = $this->str_transaction_id;
            $this->str_transaction_id = null;
            return $str_transaction_id;
        }

        /**
         * Optionally build and return a Schema object describing the data model
         *
         * This method is intended to be overridden in any extended Store classes
         *
         * @return Schema|null
         */
        protected function buildSchema()
        {
            return null;
        }

    }
    class Schema
    {
        /**
         * Field data types
         */
        const PROPERTY_STRING = 1;
        const PROPERTY_INTEGER = 2;
        const PROPERTY_DATETIME = 3;
        const PROPERTY_DOUBLE = 4;
        const PROPERTY_FLOAT = 4; // FLOAT === DOUBLE
        const PROPERTY_BLOB = 5;
        const PROPERTY_GEOPOINT = 6;
        const PROPERTY_BOOLEAN = 10; // 10 types of people...
        const PROPERTY_STRING_LIST = 20;
        const PROPERTY_INTEGER_LIST = 21;
        const PROPERTY_ENTITY = 30;
        const PROPERTY_KEY = 40;
        const PROPERTY_DETECT = 99; // used for auto-detection
        /**
         * Kind (like database 'Table')
         *
         * @var string|null
         */
        private $str_kind = null;
        /**
         * Known fields
         *
         * @var array
         */
        private $arr_defined_properties = [];
        /**
         * The class to use when instantiating new Entity objects
         *
         * @var string
         */
        private $str_entity_class = 'Entity';
        /**
         * Kind is required
         *
         * @param $str_kind
         */
        public function __construct($str_kind)
        {
            $this->str_kind = $str_kind;
        }
        /**
         * Add a field to the known field array
         *
         * @param $str_name
         * @param $int_type
         * @param bool $bol_index
         * @return $this
         */
        public function addProperty($str_name, $int_type = self::PROPERTY_STRING, $bol_index = TRUE)
        {
            $this->arr_defined_properties[$str_name] = [
                'type' => $int_type,
                'index' => $bol_index
            ];
            return $this;
        }
        /**
         * Add a string field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addString($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_STRING, $bol_index);
        }
        /**
         * Add an integer field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addInteger($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_INTEGER, $bol_index);
        }
        /**
         * Add a datetime field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addDatetime($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_DATETIME, $bol_index);
        }
        /**
         * Add a float|double field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addFloat($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_FLOAT, $bol_index);
        }
        /**
         * Add a boolean field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addBoolean($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_BOOLEAN, $bol_index);
        }
        /**
         * Add a geopoint field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addGeopoint($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_GEOPOINT, $bol_index);
        }
        /**
         * Add a string-list (array of strings) field to the schema
         *
         * @param $str_name
         * @param bool $bol_index
         * @return Schema
         */
        public function addStringList($str_name, $bol_index = TRUE)
        {
            return $this->addProperty($str_name, self::PROPERTY_STRING_LIST, $bol_index);
        }
        /**
         * Get the Kind
         *
         * @return string
         */
        public function getKind()
        {
            return $this->str_kind;
        }
        /**
         * Get the configured fields
         *
         * @return array
         */
        public function getProperties()
        {
            return $this->arr_defined_properties;
        }
        /**
         * Set the class to use when instantiating new Entity objects
         *
         * Must be Entity, or a sub-class of it
         *
         * @param $str_class
         * @return $this
         * @throws \InvalidArgumentException
         */
        public final function setEntityClass($str_class)
        {
            if(class_exists($str_class)) {
                if(is_a($str_class, 'Entity', TRUE)) {
                    $this->str_entity_class = $str_class;
                } else {
                    throw new \InvalidArgumentException('Cannot set an Entity class that does not extend "Entity": ' . $str_class);
                }
            } else {
                throw new \InvalidArgumentException('Cannot set missing Entity class: ' . $str_class);
            }
            return $this;
        }
        /**
         * Create a new instance of this GDS Entity class
         *
         * @return Entity
         */
        public final function createEntity()
        {
            return (new $this->str_entity_class())->setSchema($this);
        }
    }

    class Entity
    {
        /**
         * Datastore entity Kind
         *
         * @var string|null
         */
        private $str_kind = null;
        /**
         * GDS record Key ID
         *
         * @var string
         */
        private $str_key_id = null;
        /**
         * GDS record Key Name
         *
         * @var string
         */
        private $str_key_name = null;
        /**
         * Entity ancestors
         *
         * @var null|array|Entity
         */
        private $mix_ancestry = null;
        /**
         * Field Data
         *
         * @var array
         */
        private $arr_data = [];
        /**
         * The Schema for the Entity, if known.
         *
         * @var Schema|null
         */
        private $obj_schema = null;
        /**
         * Get the Entity Kind
         *
         * @return null
         */
        public function getKind()
        {
            return $this->str_kind;
        }
        /**
         * Get the key ID
         *
         * @return string
         */
        public function getKeyId()
        {
            return $this->str_key_id;
        }
        /**
         * Get the key name
         *
         * @return string
         */
        public function getKeyName()
        {
            return $this->str_key_name;
        }
        /**
         * @param $str_kind
         * @return $this
         */
        public function setKind($str_kind)
        {
            $this->str_kind = $str_kind;
            return $this;
        }
        /**
         * Set the key ID
         *
         * @param $str_key_id
         * @return $this
         */
        public function setKeyId($str_key_id)
        {
            $this->str_key_id = $str_key_id;
            return $this;
        }
        /**
         * Set the key name
         *
         * @param $str_key_name
         * @return $this
         */
        public function setKeyName($str_key_name)
        {
            $this->str_key_name = $str_key_name;
            return $this;
        }
        /**
         * Magic setter.. sorry
         *
         * @param $str_key
         * @param $mix_value
         */
        public function __set($str_key, $mix_value)
        {
            $this->arr_data[$str_key] = $mix_value;
        }
        /**
         * Magic getter.. sorry
         *
         * @param $str_key
         * @return null
         */
        public function __get($str_key)
        {
            if(isset($this->arr_data[$str_key])) {
                return $this->arr_data[$str_key];
            }
            return null;
        }
        /**
         * Is a data value set?
         *
         * @param $str_key
         * @return bool
         */
        public function __isset($str_key)
        {
            return isset($this->arr_data[$str_key]);
        }
        /**
         * Get the entire data array
         *
         * @return array
         */
        public function getData()
        {
            return $this->arr_data;
        }
        /**
         * Set the Entity's ancestry. This either an array of paths OR another Entity
         *
         * @param $mix_path
         * @return $this
         */
        public function setAncestry($mix_path)
        {
            $this->mix_ancestry = $mix_path;
            return $this;
        }
        /**
         * Get the ancestry of the entity
         *
         * @return null|array
         */
        public function getAncestry()
        {
            return $this->mix_ancestry;
        }
        /**
         * The Schema for the Entity, if known.
         *
         * @return Schema|null
         */
        public function getSchema()
        {
            return $this->obj_schema;
        }
        /**
         * Set the Schema for the Entity
         *
         * @param Schema $obj_schema
         * @return $this
         */
        public function setSchema(Schema $obj_schema)
        {
            $this->obj_schema = $obj_schema;
            $this->setKind($obj_schema->getKind());
            return $this;
        }
    }
    abstract class Gateway
    {

        /**
         * The dataset ID
         *
         * @var string|null
         */
        protected $str_dataset_id = null;

        /**
         * Optional namespace (for multi-tenant applications)
         *
         * @var string|null
         */
        protected $str_namespace = null;

        /**
         * The last response - usually a Commit or Query response
         *
         * @var object|null
         */
        protected $obj_last_response = null;

        /**
         * The transaction ID to use on the next commit
         *
         * @var null|string
         */
        protected $str_next_transaction = null;

        /**
         * The current Schema
         *
         * @var Schema|null
         */
        protected $obj_schema = null;

        /**
         * An array of Mappers, keyed on Entity Kind
         *
         * @var Mapper[]
         */
        protected $arr_kind_mappers = [];

        /**
         * Set the Schema to be used next (once?)
         *
         * @param Schema $obj_schema
         * @return $this
         */
        public function withSchema(Schema $obj_schema)
        {
            $this->obj_schema = $obj_schema;
            return $this;
        }

        /**
         * Set the transaction ID to be used next (once)
         *
         * @param $str_transaction_id
         * @return $this
         */
        public function withTransaction($str_transaction_id)
        {
            $this->str_next_transaction = $str_transaction_id;
            return $this;
        }

        /**
         * Fetch one entity by Key ID
         *
         * @param $int_key_id
         * @return mixed
         */
        public function fetchById($int_key_id)
        {
            $arr_results = $this->fetchByIds([$int_key_id]);
            if(count($arr_results) > 0) {
                return $arr_results[0];
            }
            return null;
        }

        /**
         * Fetch entity data by Key Name
         *
         * @param $str_key_name
         * @return mixed
         */
        public function fetchByName($str_key_name)
        {
            $arr_results = $this->fetchByNames([$str_key_name]);
            if(count($arr_results) > 0) {
                return $arr_results[0];
            }
            return null;
        }

        /**
         * Delete an Entity
         *
         * @param Entity $obj_key
         * @return bool
         */
        public function delete(Entity $obj_key)
        {
            return $this->deleteMulti([$obj_key]);
        }

        /**
         * Put a single Entity into the Datastore
         *
         * @param Entity $obj_entity
         */
        public function put(Entity $obj_entity)
        {
            $this->putMulti([$obj_entity]);
        }

        /**
         * Put an array of Entities into the Datastore
         *
         * Consumes Schema
         *
         * @param Entity[] $arr_entities
         * @throws \Exception
         */
        public function putMulti(array $arr_entities)
        {
            // Ensure all the supplied are Entities and have a Kind & Schema
            $this->ensureSchema($arr_entities);

            // Record the Auto-generated Key IDs against the GDS Entities.
            $this->mapAutoIDs($this->upsert($arr_entities));

            // Consume schema, clear kind mapper-map(!)
            $this->obj_schema = null;
            $this->arr_kind_mappers = [];
        }

        /**
         * Fetch one or more entities by KeyID
         *
         * Consumes Schema (deferred)
         *
         * @param array $arr_key_ids
         * @return array
         */
        public function fetchByIds(array $arr_key_ids)
        {
            return $this->fetchByKeyPart($arr_key_ids, 'setId');
        }

        /**
         * Fetch one or more entities by KeyName
         *
         * Consume Schema (deferred)
         *
         * @param array $arr_key_names
         * @return array
         */
        public function fetchByNames(array $arr_key_names)
        {
            return $this->fetchByKeyPart($arr_key_names, 'setName');
        }

        /**
         * Default Kind & Schema support for "new" Entities
         *
         * @param Entity[] $arr_entities
         */
        protected function ensureSchema($arr_entities)
        {
            foreach($arr_entities as $obj_gds_entity) {
                if($obj_gds_entity instanceof Entity) {
                    if (null === $obj_gds_entity->getKind()) {
                        $obj_gds_entity->setSchema($this->obj_schema);
                    }
                } else {
                    throw new \InvalidArgumentException('You gave me something other than Entity objects.. not gonna fly!');
                }
            }
        }

        /**
         * Determine Mapper (early stage [draft] support for cross-entity upserts)
         *
         * @param Entity $obj_gds_entity
         * @return Mapper
         */
        protected function determineMapper(Entity $obj_gds_entity)
        {
            $str_this_kind = $obj_gds_entity->getKind();
            if(!isset($this->arr_kind_mappers[$str_this_kind])) {
                $this->arr_kind_mappers[$str_this_kind] = $this->createMapper();
                if($this->obj_schema->getKind() != $str_this_kind) {
                    $this->arr_kind_mappers[$str_this_kind]->setSchema($obj_gds_entity->getSchema());
                }
            }
            return $this->arr_kind_mappers[$str_this_kind];
        }

        /**
         * Record the Auto-generated Key IDs against the GDS Entities.
         *
         * @param Entity[] $arr_auto_id_requested
         * @throws \Exception
         */
        protected function mapAutoIDs(array $arr_auto_id_requested)
        {
            if (!empty($arr_auto_id_requested)) {
                $arr_auto_ids = $this->extractAutoIDs();
                if(count($arr_auto_id_requested) === count($arr_auto_ids)) {
                    foreach ($arr_auto_id_requested as $int_idx => $obj_gds_entity) {
                        $obj_gds_entity->setKeyId($arr_auto_ids[$int_idx]);
                    }
                } else {
                    throw new \Exception("Mismatch count of requested & returned Auto IDs");
                }
            }
        }

        /**
         * Part of our "add parameters to query" sequence.
         *
         * Shared between multiple Gateway implementations.
         *
         * @param $obj_val
         * @param $mix_value
         * @return $obj_val
         */
        protected function configureValueParamForQuery($obj_val, $mix_value)
        {
            $str_type = gettype($mix_value);
            switch($str_type) {
                case 'boolean':
                    $obj_val->setBooleanValue($mix_value);
                    break;

                case 'integer':
                    $obj_val->setIntegerValue($mix_value);
                    break;

                case 'double':
                    $obj_val->setDoubleValue($mix_value);
                    break;

                case 'string':
                    $obj_val->setStringValue($mix_value);
                    break;

                case 'array':
                    throw new \InvalidArgumentException('Unexpected array parameter');

                case 'object':
                    $this->configureObjectValueParamForQuery($obj_val, $mix_value);
                    break;

                case 'null':
                    $obj_val->setStringValue(null);
                    break;

                case 'resource':
                case 'unknown type':
                default:
                    throw new \InvalidArgumentException('Unsupported parameter type: ' . $str_type);
            }
            return $obj_val;
        }

        /**
         * Configure a Value parameter, based on the supplied object-type value
         *
         * @param object $obj_val
         * @param object $mix_value
         */
        abstract protected function configureObjectValueParamForQuery($obj_val, $mix_value);

        /**
         * Put an array of Entities into the Datastore. Return any that need AutoIDs
         *
         * @param Entity[] $arr_entities
         * @return Entity[]
         */
        abstract protected function upsert(array $arr_entities);

        /**
         * Extract Auto Insert IDs from the last response
         *
         * @return array
         */
        abstract protected function extractAutoIDs();

        /**
         * Fetch 1-many Entities, using the Key parts provided
         *
         * Consumes Schema
         *
         * @param array $arr_key_parts
         * @param $str_setter
         * @return mixed
         */
        abstract protected function fetchByKeyPart(array $arr_key_parts, $str_setter);

        /**
         * Delete 1-many entities
         *
         * @param array $arr_entities
         * @return mixed
         */
        abstract public function deleteMulti(array $arr_entities);

        /**
         * Fetch some Entities, based on the supplied GQL and, optionally, parameters
         *
         * @param string $str_gql
         * @param null|array $arr_params
         * @return mixed
         */
        abstract public function gql($str_gql, $arr_params = null);

        /**
         * Get the end cursor from the last response
         *
         * @return mixed
         */
        abstract public function getEndCursor();

        /**
         * Create a mapper that's right for this Gateway
         *
         * @return Mapper
         */
        abstract protected function createMapper();

        /**
         * Start a transaction
         *
         * @param bool $bol_cross_group
         * @return mixed
         */
        abstract public function beginTransaction($bol_cross_group = FALSE);

    }
    class ProtoBuf extends Gateway
    {

        /**
         * Batch size for un-limited queries
         */
        const BATCH_SIZE = 1000;

        /**
         * Set up the dataset and optional namespace
         *
         * @todo Review use of $_SERVER.
         * Google propose a 'better' way of auto detecting app id,
         * but it's not perfect (does not work) in the dev environment
         * \google\appengine\api\app_identity\AppIdentityService::getApplicationId();
         *
         * @param null|string $str_dataset
         * @param null|string $str_namespace
         * @throws \Exception
         */
        public function __construct($str_dataset = null, $str_namespace = null)
        {
            if(null === $str_dataset) {
                if(isset($_SERVER['APPLICATION_ID'])) {
                    $this->str_dataset_id = $_SERVER['APPLICATION_ID'];
                } else {
                    throw new \Exception('Could not determine DATASET, please pass to ' . get_class($this) . '::__construct()');
                }
            } else {
                $this->str_dataset_id = $str_dataset;
            }
            $this->str_namespace = $str_namespace;
        }

        /**
         * Put an array of Entities into the Datastore. Return any that need AutoIDs
         *
         * @todo Validate support for per-entity Schemas
         *
         * @param Entity[] $arr_entities
         * @return Entity[]
         */
        public function upsert(array $arr_entities)
        {
            $obj_request = $this->setupCommit();
            $obj_mutation = $obj_request->mutableDeprecatedMutation();
            $arr_auto_id_required = [];
            foreach($arr_entities as $obj_gds_entity) {
                if(null === $obj_gds_entity->getKeyId() && null === $obj_gds_entity->getKeyName()) {
                    $obj_entity = $obj_mutation->addInsertAutoId();
                    $arr_auto_id_required[] = $obj_gds_entity; // maintain reference to the array of requested auto-ids
                } else {
                    $obj_entity = $obj_mutation->addUpsert();
                }
                $this->applyNamespace($obj_entity->mutableKey());
                $this->determineMapper($obj_gds_entity)->mapToGoogle($obj_gds_entity, $obj_entity);
            }
            $this->execute('Commit', $obj_request, new CommitResponse());
            return $arr_auto_id_required;
        }

        /**
         * Extract Auto Insert IDs from the last response
         *
         * @return array
         */
        protected function extractAutoIDs()
        {
            $arr_ids = [];
            foreach($this->obj_last_response->getDeprecatedMutationResult()->getInsertAutoIdKeyList() as $obj_key) {
                $arr_key_path = $obj_key->getPathElementList();
                $obj_path_end = end($arr_key_path);
                $arr_ids[] = $obj_path_end->getId();
            }
            return $arr_ids;
        }

        /**
         * Apply dataset and namespace ("partition") to an object
         *
         * Usually a Key or RunQueryRequest
         *
         * @param object $obj_target
         * @return mixed
         */
        private function applyNamespace($obj_target)
        {
            $obj_partition = $obj_target->mutablePartitionId();
            $obj_partition->setDatasetId($this->str_dataset_id);
            if(null !== $this->str_namespace) {
                $obj_partition->setNamespace($this->str_namespace);
            }
            return $obj_target;
        }

        /**
         * Apply a transaction to an object
         *
         * @param $obj
         * @return mixed
         */
        private function applyTransaction($obj)
        {
            if(null !== $this->str_next_transaction) {
                $obj->setTransaction($this->str_next_transaction);
                $this->str_next_transaction = null;
            }
            return $obj;
        }

        /**
         * Set up a RunQueryRequest
         *
         * @todo setReadConsistency
         * @todo Be more intelligent about when we set the suggested batch size (e.g. on LIMITed queries)
         *
         * @return RunQueryRequest
         */
        private function setupRunQuery()
        {
            $obj_request = ($this->applyNamespace(new RunQueryRequest()));
            $obj_request->setSuggestedBatchSize(self::BATCH_SIZE); // Avoid having to run multiple batches
            $this->applyTransaction($obj_request->mutableReadOptions()); // ->setReadConsistency('some-val');
            return $obj_request;
        }

        /**
         * Set up a LookupRequest
         *
         * @todo setReadConsistency
         *
         * @return LookupRequest
         */
        private function setupLookup()
        {
            $obj_request = new LookupRequest();
            $this->applyTransaction($obj_request->mutableReadOptions()); // ->setReadConsistency('some-val');
            return $obj_request;
        }

        /**
         * Set up a commit request
         *
         * @return CommitRequest
         */
        private function setupCommit()
        {
            $obj_commit_request = new CommitRequest();
            if(null === $this->str_next_transaction) {
                $obj_commit_request->setMode(Mode::NON_TRANSACTIONAL);
            } else {
                $obj_commit_request->setMode(Mode::TRANSACTIONAL);
                $this->applyTransaction($obj_commit_request);
            }
            return $obj_commit_request;
        }

        /**
         * Execute a method against the Datastore
         *
         * Use Google's static ApiProxy method
         *
         * Will attempt to convert GQL queries in local development environments
         *
         * @param $str_method
         * @param ProtocolMessage $obj_request
         * @param ProtocolMessage $obj_response
         * @return mixed
         * @throws ApplicationError
         * @throws \google\appengine\runtime\CapabilityDisabledError
         * @throws \google\appengine\runtime\FeatureNotEnabledError
         * @throws Contention
         */
        private function execute($str_method, ProtocolMessage $obj_request, ProtocolMessage $obj_response)
        {
            try {
                ApiProxy::makeSyncCall('datastore_v4', $str_method, $obj_request, $obj_response, 60);
                $this->obj_last_response = $obj_response;
            } catch (ApplicationError $obj_exception) {
                $this->obj_last_response = NULL;
                if($obj_request instanceof RunQueryRequest && 'GQL not supported.' === $obj_exception->getMessage()) {
                    $this->executeGqlAsBasicQuery($obj_request); // recursive
                } elseif (FALSE !== strpos($obj_exception->getMessage(), 'too much contention') || FALSE !== strpos($obj_exception->getMessage(), 'Concurrency')) {
                    // LIVE: "too much contention on these datastore entities. please try again." LOCAL : "Concurrency exception."
                    throw new Contention('Datastore contention', 409, $obj_exception);
                } else {
                    throw $obj_exception;
                }
            }
            return $this->obj_last_response;
        }

        /**
         * Fetch 1-many Entities, using the Key parts provided
         *
         * @param array $arr_key_parts
         * @param $str_setter
         * @return Entity[]|null
         */
        protected function fetchByKeyPart(array $arr_key_parts, $str_setter)
        {
            $obj_request = $this->setupLookup();
            foreach($arr_key_parts as $mix_key_part) {
                $obj_key = $obj_request->addKey();
                $this->applyNamespace($obj_key);
                $obj_kpe = $obj_key->addPathElement();
                $obj_kpe->setKind($this->obj_schema->getKind());
                $obj_kpe->$str_setter($mix_key_part);
            }
            $this->execute('Lookup', $obj_request, new LookupResponse());
            $arr_mapped_results = $this->createMapper()->mapFromResults($this->obj_last_response->getFoundList());
            $this->obj_schema = null; // Consume Schema
            return $arr_mapped_results;
        }

        /**
         * Delete 1 or many entities, using their Keys
         *
         * Consumes Schema
         *
         * @todo Determine success. Not 100% how to do this from the response yet.
         *
         * @param array $arr_entities
         * @return bool
         */
        public function deleteMulti(array $arr_entities)
        {
            $obj_mapper = $this->createMapper();
            $obj_request = $this->setupCommit();
            $obj_mutation = $obj_request->mutableDeprecatedMutation();
            foreach($arr_entities as $obj_gds_entity) {
                $this->applyNamespace(
                    $obj_mapper->configureGoogleKey(
                        $obj_mutation->addDelete(), $obj_gds_entity
                    )
                );
            }
            $this->execute('Commit', $obj_request, new CommitResponse());
            $this->obj_schema = null;
            return TRUE; // really?
        }

        /**
         * Fetch some Entities, based on the supplied GQL and, optionally, parameters
         *
         * In local dev environments, we may convert the GQL query later.
         *
         * @todo Consider automatically handling multiple response batches?
         *
         * @param string $str_gql
         * @param array|null $arr_params
         * @return Entity[]|null
         * @throws \Exception
         */
        public function gql($str_gql, $arr_params = null)
        {
            $obj_query_request = $this->setupRunQuery();
            $obj_gql_query = $obj_query_request->mutableGqlQuery();
            $obj_gql_query->setQueryString($str_gql);
            $obj_gql_query->setAllowLiteral(TRUE);
            if(null !== $arr_params) {
                $this->addParamsToQuery($obj_gql_query, $arr_params);
            }
            $obj_gql_response = $this->execute('RunQuery', $obj_query_request, new RunQueryResponse());
            $arr_mapped_results = $this->createMapper()->mapFromResults($obj_gql_response->getBatch()->getEntityResultList());
            $this->obj_schema = null; // Consume Schema
            return $arr_mapped_results;
        }

        /**
         * Take a GQL RunQuery request and convert to a standard RunQuery request
         *
         * Always expected to be called in the stack ->gql()->execute()->runGqlAsBasicQuery()
         *
         * @param ProtocolMessage $obj_gql_request
         * @return null
         * @throws \GDS\Exception\GQL
         */
        private function executeGqlAsBasicQuery(ProtocolMessage $obj_gql_request)
        {
            // Set up the new request
            $obj_query_request = $this->setupRunQuery();
            $obj_query = $obj_query_request->mutableQuery();

            // Transfer any transaction data to the new request
            /** @var RunQueryRequest $obj_gql_request */
            if($obj_gql_request->mutableReadOptions()->hasTransaction()) {
                $obj_query_request->mutableReadOptions()->setTransaction($obj_gql_request->mutableReadOptions()->getTransaction());
            }

            // Parse the GQL string
            $obj_gql_query = $obj_gql_request->getGqlQuery();
            $obj_parser = new ProtoBufGQLParser();

            $obj_parser->parse($obj_gql_query->getQueryString(), $obj_gql_query->getNameArgList());

            // Start applying to the new RunQuery request
            $obj_query->addKind()->setName($obj_parser->getKind());
            foreach($obj_parser->getOrderBy() as $arr_order_by) {
                $obj_query->addOrder()->setDirection($arr_order_by['direction'])->mutableProperty()->setName($arr_order_by['property']);
            }

            // Limits, Offsets, Cursors
            $obj_parser->getLimit() && $obj_query->setLimit($obj_parser->getLimit());
            $obj_parser->getOffset() && $obj_query->setOffset($obj_parser->getOffset());
            $obj_parser->getStartCursor() && $obj_query->setStartCursor($obj_parser->getStartCursor());
            // @todo @ $obj_query->setEndCursor();

            // Filters
            $int_filters = count($obj_parser->getFilters());
            if(1 === $int_filters) {
                $this->configureFilterFromGql($obj_query->mutableFilter()->mutablePropertyFilter(), $obj_parser->getFilters()[0]);
            } else if (1 < $int_filters) {
                $obj_composite_filter = $obj_query->mutableFilter()->mutableCompositeFilter()->setOperator(\google\appengine\datastore\v4\CompositeFilter\Operator::AND_);
                foreach ($obj_parser->getFilters() as $arr_filter) {
                    $this->configureFilterFromGql($obj_composite_filter->addFilter()->mutablePropertyFilter(), $arr_filter);
                }
            }
            return $this->execute('RunQuery', $obj_query_request, new RunQueryResponse());
        }

        /**
         * @param PropertyFilter $obj_filter
         * @param $arr_filter
         */
        private function configureFilterFromGql(PropertyFilter $obj_filter, $arr_filter)
        {
            $obj_filter->mutableProperty()->setName($arr_filter['lhs']);
            $mix_value = $arr_filter['rhs'];
            if($mix_value instanceof Value) {
                $obj_filter->mutableValue()->mergeFrom($mix_value);
            } else {
                $obj_filter->mutableValue()->setStringValue($mix_value); // @todo Improve type detection using Schema
            }
            $obj_filter->setOperator($arr_filter['op']);
        }

        /**
         * Add Parameters to a GQL Query object
         *
         * @param \google\appengine\datastore\v4\GqlQuery $obj_query
         * @param array $arr_params
         */
        private function addParamsToQuery(\google\appengine\datastore\v4\GqlQuery $obj_query, array $arr_params)
        {
            if(count($arr_params) > 0) {
                foreach ($arr_params as $str_name => $mix_value) {
                    $obj_arg = $obj_query->addNameArg();
                    $obj_arg->setName($str_name);
                    if ('startCursor' == $str_name) {
                        $obj_arg->setCursor($mix_value);
                    } else {
                        $this->configureValueParamForQuery($obj_arg->mutableValue(), $mix_value);
                    }
                }
            }
        }

        /**
         * Configure a Value parameter, based on the supplied object-type value
         *
         * @todo Re-use one Mapper instance
         *
         * @param \google\appengine\datastore\v4\Value $obj_val
         * @param object $mix_value
         */
        protected function configureObjectValueParamForQuery($obj_val, $mix_value)
        {
            if($mix_value instanceof Entity) {
                $obj_key_value = $obj_val->mutableKeyValue();
                $this->createMapper()->configureGoogleKey($obj_key_value, $mix_value);
                $this->applyNamespace($obj_key_value);
            } elseif ($mix_value instanceof \DateTime) {
                $obj_val->setTimestampMicrosecondsValue($mix_value->format('Uu'));
            } elseif (method_exists($mix_value, '__toString')) {
                $obj_val->setStringValue($mix_value->__toString());
            } else {
                throw new \InvalidArgumentException('Unexpected, non-string-able object parameter: ' . get_class($mix_value));
            }
        }

        /**
         * Get the end cursor from the last response
         */
        public function getEndCursor()
        {
            return $this->obj_last_response->getBatch()->getEndCursor();
        }

        /**
         * Create a mapper that's right for this Gateway
         *
         * @return MapperProtoBuf
         */
        protected function createMapper()
        {
            return (new MapperProtoBuf())->setSchema($this->obj_schema);
        }

        /**
         * Begin a transaction
         *
         * @todo Evaluate cross-request transactions [setCrossRequest]
         *
         * @param bool $bol_cross_group
         * @return string|null
         */
        public function beginTransaction($bol_cross_group = FALSE)
        {
            $obj_request = new BeginTransactionRequest();
            if($bol_cross_group) {
                $obj_request->setCrossGroup(TRUE);
            }
            $obj_response = $this->execute('BeginTransaction', $obj_request, new BeginTransactionResponse());
            return isset($obj_response->transaction) ? $obj_response->transaction : null;
        }
    }
    abstract class Mapper
    {
        /**
         * Current Schema
         *
         * @var Schema
         */
        protected $obj_schema = null;
        /**
         * Set the schema
         *
         * @param Schema $obj_schema
         * @return $this
         */
        public function setSchema(Schema $obj_schema)
        {
            $this->obj_schema = $obj_schema;
            return $this;
        }
        /**
         * Dynamically determine type for a value
         *
         * @param $mix_value
         * @return array
         */
        protected function determineDynamicType($mix_value)
        {
            switch(gettype($mix_value)) {
                case 'boolean':
                    $int_dynamic_type = Schema::PROPERTY_BOOLEAN;
                    break;
                case 'integer':
                    $int_dynamic_type = Schema::PROPERTY_INTEGER;
                    break;
                case 'double':
                    $int_dynamic_type = Schema::PROPERTY_DOUBLE;
                    break;
                case 'string':
                    $int_dynamic_type = Schema::PROPERTY_STRING;
                    break;
                case 'array':
                    $int_dynamic_type = Schema::PROPERTY_STRING_LIST;
                    break;
                case 'object':
                    if($mix_value instanceof DateTime) {
                        $int_dynamic_type = Schema::PROPERTY_DATETIME;
                        break;
                    }
                    if($mix_value instanceof Geopoint) {
                        $int_dynamic_type = Schema::PROPERTY_GEOPOINT;

                        break;
                    }
                    $int_dynamic_type = Schema::PROPERTY_STRING;
                    if(method_exists($mix_value, '__toString')) {
                        $mix_value = $mix_value->__toString();
                    } else {
                        $mix_value = null;
                    }
                    break;
                case 'resource':
                case 'null':
                case 'unknown type':
                default:
                    $int_dynamic_type = Schema::PROPERTY_STRING;
                    $mix_value = null;
            }
            return [
                'type' => $int_dynamic_type,
                'value' => $mix_value
            ];
        }
        /**
         * Map 1-many results out of the Raw response data array
         *
         * @param array $arr_results
         * @return Entity[]|null
         */
        public function mapFromResults(array $arr_results)
        {
            $arr_entities = [];
            foreach ($arr_results as $obj_result) {
                $arr_entities[] = $this->mapOneFromResult($obj_result);
            }
            return $arr_entities;
        }
        /**
         * Extract a single property value from a Property object
         *
         * Defer any varying data type extractions to child classes
         *
         * @param $int_type
         * @param object $obj_property
         * @return array
         * @throws \Exception
         */
        protected function extractPropertyValue($int_type, $obj_property)
        {
            switch ($int_type) {
                case Schema::PROPERTY_STRING:
                    return $obj_property->getStringValue();
                case Schema::PROPERTY_INTEGER:
                    return $obj_property->getIntegerValue();
                case Schema::PROPERTY_DATETIME:
                    return $this->extractDatetimeValue($obj_property);
                case Schema::PROPERTY_DOUBLE:
                case Schema::PROPERTY_FLOAT:
                    return $obj_property->getDoubleValue();
                case Schema::PROPERTY_BOOLEAN:
                    return $obj_property->getBooleanValue();
                case Schema::PROPERTY_GEOPOINT:
                    return $this->extractGeopointValue($obj_property);
                case Schema::PROPERTY_STRING_LIST:
                    return $this->extractStringListValue($obj_property);
                case Schema::PROPERTY_DETECT:
                    return $this->extractAutoDetectValue($obj_property);
            }
            throw new \Exception('Unsupported field type: ' . $int_type);
        }
        /**
         * Auto detect & extract a value
         *
         * @param object $obj_property
         * @return mixed
         */
        abstract protected function extractAutoDetectValue($obj_property);
        /**
         * Extract a datetime value
         *
         * @param $obj_property
         * @return mixed
         */
        abstract protected function extractDatetimeValue($obj_property);
        /**
         * Extract a String List value
         *
         * @param $obj_property
         * @return mixed
         */
        abstract protected function extractStringListValue($obj_property);
        /**
         * Extract a Geopoint value
         *
         * @param $obj_property
         * @return Geopoint
         */
        abstract protected function extractGeopointValue($obj_property);
        /**
         * Map a single result out of the Raw response data array FROM Google TO a GDS Entity
         *
         * @param object $obj_result
         * @return Entity
         * @throws \Exception
         */
        abstract public function mapOneFromResult($obj_result);
    }
    class MapperProtoBuf extends Mapper
    {
        /**
         * Map from GDS to Google Protocol Buffer
         *
         * @param Entity $obj_gds_entity
         * @param \google\appengine\datastore\v4\Entity $obj_entity
         */
        public function mapToGoogle(Entity $obj_gds_entity, \google\appengine\datastore\v4\Entity $obj_entity)
        {
            // Key
            $this->configureGoogleKey($obj_entity->mutableKey(), $obj_gds_entity);
            // Properties
            $arr_field_defs = $this->obj_schema->getProperties();
            foreach($obj_gds_entity->getData() as $str_field_name => $mix_value) {
                $obj_prop = $obj_entity->addProperty();
                $obj_prop->setName($str_field_name);
                $obj_val = $obj_prop->mutableValue();
                if(isset($arr_field_defs[$str_field_name])) {
                    $this->configureGooglePropertyValue($obj_val, $arr_field_defs[$str_field_name], $mix_value);
                } else {
                    $arr_dynamic_data = $this->determineDynamicType($mix_value);
                    $this->configureGooglePropertyValue($obj_val, ['type' => $arr_dynamic_data['type'], 'index' => TRUE], $arr_dynamic_data['value']);
                }
            }
        }
        /**
         * Map a single result out of the Raw response data into a supplied Entity object
         *
         * @todo Validate dynamic schema mapping in multi-kind responses like fetchEntityGroup()
         *
         * @param EntityResult $obj_result
         * @return Entity
         */
        public function mapOneFromResult($obj_result)
        {
            // Key & Ancestry
            list($obj_gds_entity, $bol_schema_match) = $this->createEntityWithKey($obj_result);
            // Properties
            $arr_property_definitions = $this->obj_schema->getProperties();
            foreach($obj_result->getEntity()->getPropertyList() as $obj_property) {
                /* @var $obj_property \google\appengine\datastore\v4\Property */
                $str_field = $obj_property->getName();
                if ($bol_schema_match && isset($arr_property_definitions[$str_field])) {
                    $obj_gds_entity->__set($str_field, $this->extractPropertyValue($arr_property_definitions[$str_field]['type'], $obj_property->getValue()));
                } else {
                    $obj_gds_entity->__set($str_field, $this->extractPropertyValue(Schema::PROPERTY_DETECT, $obj_property->getValue()));
                }
            }
            return $obj_gds_entity;
        }
        /**
         * Create & populate a Entity with key data
         *
         * @todo Validate dynamic mapping
         *
         * @param EntityResult $obj_result
         * @return array
         */
        private function createEntityWithKey(EntityResult $obj_result)
        {
            // Get the full key path
            $arr_key_path = $obj_result->getEntity()->getKey()->getPathElementList();
            // Key for 'self' (the last part of the KEY PATH)
            /* @var $obj_path_end \google\appengine\datastore\v4\Key\PathElement */
            $obj_path_end = array_pop($arr_key_path);
            if($obj_path_end->getKind() == $this->obj_schema->getKind()) {
                $bol_schema_match = TRUE;
                $obj_gds_entity = $this->obj_schema->createEntity();
            } else {
                $bol_schema_match = FALSE;
                $obj_gds_entity = (new Entity())->setKind($obj_path_end->getKind());
            }
            // Set ID or Name (will always have one or the other)
            if($obj_path_end->hasId()) {
                $obj_gds_entity->setKeyId($obj_path_end->getId());
            } else {
                $obj_gds_entity->setKeyName($obj_path_end->getName());
            }
            // Ancestors?
            $int_ancestor_elements = count($arr_key_path);
            if($int_ancestor_elements > 0) {
                $arr_anc_path = [];
                foreach ($arr_key_path as $obj_kpe) {
                    $arr_anc_path[] = [
                        'kind' => $obj_kpe->getKind(),
                        'id' => $obj_kpe->hasId() ? $obj_kpe->getId() : null,
                        'name' => $obj_kpe->hasName() ? $obj_kpe->getName() : null
                    ];
                }
                $obj_gds_entity->setAncestry($arr_anc_path);
            }
            // Return whether or not the Schema matched
            return [$obj_gds_entity, $bol_schema_match];
        }
        /**
         * Populate a ProtoBuf Key from a GDS Entity
         *
         * @param Key $obj_key
         * @param Entity $obj_gds_entity
         * @return Key
         */
        public function configureGoogleKey(Key $obj_key, Entity $obj_gds_entity)
        {
            // Add any ancestors FIRST
            $mix_ancestry = $obj_gds_entity->getAncestry();
            if(is_array($mix_ancestry)) {
                // @todo Get direction right!
                foreach ($mix_ancestry as $arr_ancestor_element) {
                    $this->configureGoogleKeyPathElement($obj_key->addPathElement(), $arr_ancestor_element);
                }
            } elseif ($mix_ancestry instanceof Entity) {
                // Recursive
                $this->configureGoogleKey($obj_key, $mix_ancestry);
            }
            // Root Key (must be the last in the chain)
            $this->configureGoogleKeyPathElement($obj_key->addPathElement(), [
                'kind' => $obj_gds_entity->getKind(),
                'id' => $obj_gds_entity->getKeyId(),
                'name' => $obj_gds_entity->getKeyName()
            ]);
            return $obj_key;
        }
        /**
         * Configure a Google Key Path Element object
         *
         * @param Key\PathElement $obj_path_element
         * @param array $arr_kpe
         */
        private function configureGoogleKeyPathElement(Key\PathElement $obj_path_element, array $arr_kpe)
        {
            $obj_path_element->setKind($arr_kpe['kind']);
            isset($arr_kpe['id']) && $obj_path_element->setId($arr_kpe['id']);
            isset($arr_kpe['name']) && $obj_path_element->setName($arr_kpe['name']);
        }
        /**
         * Populate a ProtoBuf Property Value from a GDS Entity field definition & value
         *
         * @todo compare with Google API implementation
         *
         * @param Value $obj_val
         * @param array $arr_field_def
         * @param $mix_value
         */
        private function configureGooglePropertyValue(Value $obj_val, array $arr_field_def, $mix_value)
        {
            // Indexed?
            $bol_index = TRUE;
            if(isset($arr_field_def['index']) && FALSE === $arr_field_def['index']) {
                $bol_index = FALSE;
            }
            $obj_val->setIndexed($bol_index);
            // null checks
            if(null === $mix_value) {
                return;
            }
            // Value
            switch ($arr_field_def['type']) {
                case Schema::PROPERTY_STRING:
                    $obj_val->setStringValue((string)$mix_value);
                    break;
                case Schema::PROPERTY_INTEGER:
                    $obj_val->setIntegerValue((int)$mix_value);
                    break;
                case Schema::PROPERTY_DATETIME:
                    if($mix_value instanceof \DateTime) {
                        $obj_dtm = $mix_value;
                    } else {
                        $obj_dtm = new \DateTime($mix_value);
                    }
                    $obj_val->setTimestampMicrosecondsValue($obj_dtm->format('Uu'));
                    break;
                case Schema::PROPERTY_DOUBLE:
                case Schema::PROPERTY_FLOAT:
                    $obj_val->setDoubleValue(floatval($mix_value));
                    break;
                case Schema::PROPERTY_BOOLEAN:
                    $obj_val->setBooleanValue((bool)$mix_value);
                    break;
                case Schema::PROPERTY_GEOPOINT:
                    $obj_val->mutableGeoPointValue()->setLatitude($mix_value[0])->setLongitude($mix_value[1]);
                    break;
                case Schema::PROPERTY_STRING_LIST:
                    $obj_val->clearIndexed(); // Ensure we only index the values, not the list
                    foreach ((array)$mix_value as $str) {
                        $obj_val->addListValue()->setStringValue($str)->setIndexed($bol_index);
                    }
                    break;
                default:
                    throw new \RuntimeException('Unable to process field type: ' . $arr_field_def['type']);
            }
        }
        /**
         * Extract a datetime value
         *
         * @todo Validate 32bit compatibility. Consider substr() or use bc math
         *
         * @param object $obj_property
         * @return mixed
         */
        protected function extractDatetimeValue($obj_property)
        {
            $date =  new DateTime();
            $date->setTimestamp($obj_property->getTimestampMicrosecondsValue() / 1000000);
            return($date);
            // Changed
            // return date('Y-m-d H:i:s e', $obj_property->getTimestampMicrosecondsValue() / 1000000);
        }
        /**
         * Extract a String List value
         *
         * @param object $obj_property
         * @return mixed
         */
        protected function extractStringListValue($obj_property)
        {
            $arr_values = $obj_property->getListValueList();
            if(count($arr_values) > 0) {
                $arr = [];
                foreach ($arr_values as $obj_val) {
                    /** @var $obj_val Value */
                    $arr[] = $obj_val->getStringValue();
                }
                return $arr;
            }
            return null;
        }
        /**
         * Extract a Geopoint value (lat/lon pair)
         *
         * @param \google\appengine\datastore\v4\Value $obj_property
         * @return Geopoint
         */
        protected function extractGeopointValue($obj_property)
        {
            $obj_gp_value = $obj_property->getGeoPointValue();
            return new Geopoint($obj_gp_value->getLatitude(), $obj_gp_value->getLongitude());
        }
        /**
         * Auto detect & extract a value
         *
         * @todo expand auto detect types
         *
         * @param Value $obj_property
         * @return mixed
         */
        protected function extractAutoDetectValue($obj_property)
        {
            if($obj_property->hasStringValue()) {
                return $obj_property->getStringValue();
            }
            if($obj_property->hasIntegerValue()) {
                return $obj_property->getIntegerValue();
            }
            if($obj_property->hasTimestampMicrosecondsValue()) {
                return $this->extractDatetimeValue($obj_property);
            }
            if($obj_property->hasDoubleValue()) {
                return $obj_property->getDoubleValue();
            }
            if($obj_property->hasBooleanValue()) {
                return $obj_property->getBooleanValue();
            }
            if($obj_property->hasGeoPointValue()) {
                return $this->extractGeopointValue($obj_property);
            }
            if($obj_property->getListValueSize() > 0) {
                return $this->extractStringListValue($obj_property);
            }
            // $this->extractPropertyValue($int_field_type, $obj_property); // Recursive detection call
            return null;
        }
    }

    class ProtoBufGQLParser
    {
        /**
         * The schema is used to check if property's type is valid.
         * This is optional, because Google Datastore is schemaless.
         *
         * @var Schema|null
         */
        protected $obj_schema = null;
        /**
         * We swap out quoted strings for simple tokens early on to help parsing simplicity
         */
        const TOKEN_PREFIX = '__token__';
        /**
         * Tokens detected
         *
         * @var array
         */
        private $arr_tokens = [];
        /**
         * A count of the tokens detected
         *
         * @var int
         */
        private $int_token_count = 0;
        /**
         * Kind for the query
         *
         * @var string
         */
        private $str_kind = null;
        /**
         * Any integer offset
         *
         * @var int
         */
        private $int_offset = null;
        /**
         * Any integer limit
         *
         * @var int
         */
        private $int_limit = null;
        /**
         * A string cursor (start, like offset)
         *
         * @var string
         */
        private $str_start_cursor = null;
        /**
         * A string cursor (end, like limit)
         *
         * @var string
         */
        private $str_end_cursor = null;
        /**
         * Conditions (filters)
         *
         * @var array
         */
        private $arr_conditions = [];
        /**
         * Order bys
         *
         * @var array
         */
        private $arr_order_bys = [];
        /**
         * Any provided named parameters
         *
         * @var array
         */
        private $arr_named_params = [];
        /**
         * Sort Direction options
         *
         * @var array
         */
        private $arr_directions = [
            'ASC' => Direction::ASCENDING,
            'DESC' => Direction::DESCENDING
        ];
        /**
         * Supported comparison operators
         *
         * Not supported by v4 Proto files?
         * 'IN', 'CONTAINS', 'HAS DESCENDANT'
         *
         * @var array
         */
        private $arr_operators = [
            '=' => Operator::EQUAL,
            '<' => Operator::LESS_THAN,
            '<=' => Operator::LESS_THAN_OR_EQUAL,
            '>' => Operator::GREATER_THAN,
            '>=' => Operator::GREATER_THAN_OR_EQUAL,
            'HAS ANCESTOR' => Operator::HAS_ANCESTOR
        ];
        /**
         * Optionally, reference to the Entity schema to check type validity
         *
         * @param null|Schema $obj_schema
         */
        public function __construct(Schema $obj_schema = null)
        {
            $this->obj_schema = $obj_schema;
        }
        /**
         * Turn a GQL string and parameter array into a "lookup" query
         *
         * We use preg_replace_callback to "prune" down the GQL string so we are left with nothing
         *
         * @param $str_gql
         * @param \google\appengine\datastore\v4\GqlQueryArg[] $arr_named_params
         * @throws GQL
         */
        public function parse($str_gql, $arr_named_params = [])
        {
            // Record our input params
            foreach($arr_named_params as $obj_param) {
                if($obj_param->hasValue()) {
                    $this->arr_named_params[$obj_param->getName()] = $obj_param->getValue();
                } else if ($obj_param->hasCursor()) {
                    $this->arr_named_params[$obj_param->getName()] = $obj_param->getCursor();
                }
            }
            // Cleanup before we begin...
            $str_gql = trim($str_gql);
            // Ensure it's a 'SELECT *' query
            if(!preg_match('/^SELECT\s+(\*|__key__)\s+FROM\s+(.*)/i', $str_gql)) {
                throw new GQL("Sorry, only 'SELECT *' (full Entity) queries are currently supported by php-gds");
            }
            // Tokenize quoted items ** MUST BE FIRST **
            $str_gql = preg_replace_callback("/([`\"'])(?<quoted>.*?)(\\1)/", [$this, 'tokenizeQuoted'], $str_gql);
            // Kind
            $str_gql = preg_replace_callback('/^SELECT\s+(\*|__key__)\s+FROM\s+(?<kind>[^\s]*)/i', [$this, 'recordKind'], $str_gql, 1);
            // Offset
            $str_gql = preg_replace_callback('/OFFSET\s+(?<offset>[^\s]*)/i', [$this, 'recordOffset'], $str_gql, 1);
            // Limit
            $str_gql = preg_replace_callback('/LIMIT\s+(?<limit>[^\s]*)/i', [$this, 'recordLimit'], $str_gql, 1);
            // Order
            $str_gql = preg_replace_callback('/ORDER\s+BY\s+(?<order>.*)/i', [$this, 'recordOrder'], $str_gql, 1);
            // Where
            $str_gql = preg_replace_callback('/WHERE\s+(?<where>.*)/i', [$this, 'recordWhere'], $str_gql, 1);
            // Check we're done
            $str_gql = trim($str_gql);
            if(strlen($str_gql) > 0) {
                throw new GQL("Failed to parse entire query, remainder: [{$str_gql}]");
            }
        }
        /**
         * Record quoted strings, return simple tokens
         *
         * @param $arr
         * @return string
         */
        private function tokenizeQuoted($arr)
        {
            $str_token = self::TOKEN_PREFIX . ++$this->int_token_count;
            $this->arr_tokens[$str_token] = $arr['quoted'];
            return $str_token;
        }
        /**
         * Record the Kind
         *
         * @param $arr
         * @return string
         */
        private function recordKind($arr)
        {
            $this->str_kind = $this->lookupToken($arr['kind']);
            return '';
        }
        /**
         * Record the offset
         *
         * @param $arr
         * @return string
         */
        private function recordOffset($arr)
        {
            list($this->int_offset, $this->str_start_cursor) = $this->getIntStringFromValue($this->lookupToken($arr['offset']));
            return '';
        }
        /**
         * Record the limit
         *
         * @param $arr
         * @return string
         */
        private function recordLimit($arr)
        {
            list($this->int_limit, $this->str_end_cursor) = $this->getIntStringFromValue($this->lookupToken($arr['limit']));
            return '';
        }
        /**
         * Extract a string/int tuple from the value. Used for offsets and limits which can be string cursors or integers
         *
         * @param $mix_val
         * @return array
         */
        private function getIntStringFromValue($mix_val)
        {
            $int = null;
            $str = null;
            if($mix_val instanceof Value) {
                if($mix_val->hasIntegerValue()) {
                    $int = $mix_val->getIntegerValue();
                } else {
                    $str = $mix_val->getStringValue();
                }
            } else {
                if(is_numeric($mix_val)) {
                    $int = $mix_val;
                } else {
                    $str = $mix_val;
                }
            }
            return [$int, $str];
        }
        /**
         * Process the ORDER BY clause
         *
         * @param $arr
         * @return string
         * @throws GQL
         */
        private function recordOrder($arr)
        {
            $arr_order_bys = explode(',', $arr['order']);
            foreach($arr_order_bys as $str_order_by) {
                $arr_matches = [];
                preg_match('/\s?(?<field>[^\s]*)\s*(?<dir>ASC|DESC)?/i', $str_order_by, $arr_matches);
                if(isset($arr_matches['field'])) {
                    $str_direction = strtoupper(isset($arr_matches['dir']) ? $arr_matches['dir'] : 'ASC');
                    if(isset($this->arr_directions[$str_direction])) {
                        $int_direction = $this->arr_directions[$str_direction];
                    } else {
                        throw new GQL("Unsupported direction in ORDER BY: [{$arr_matches['dir']}] [{$str_order_by}]");
                    }
                    $this->arr_order_bys[] = [
                        'property' => $this->lookupToken($arr_matches['field']), // @todo @ lookup
                        'direction' => $int_direction
                    ];
                }
            }
            return '';
        }
        /**
         * Process the WHERE clause
         *
         * @param $arr
         * @return string
         * @throws GQL
         */
        private function recordWhere($arr)
        {
            $arr_conditions = explode('AND', $arr['where']);
            $str_regex = '/(?<lhs>[^\s<>=]*)\s*(?<comp>=|<|<=|>|>=|IN|CONTAINS|HAS ANCESTOR|HAS DESCENDANT)\s*(?<rhs>[^\s<>=]+)/i';
            foreach($arr_conditions as $str_condition) {
                $arr_matches = [];
                if(preg_match($str_regex, trim($str_condition), $arr_matches)) {
                    $str_comp = strtoupper($arr_matches['comp']);
                    if(isset($this->arr_operators[$str_comp])) {
                        $int_operator = $this->arr_operators[$str_comp];
                    } else {
                        throw new GQL("Unsupported operator in condition: [{$arr_matches['comp']}] [{$str_condition}]");
                    }
                    // If schema is set and its properties is not empty, then we use it to test the validity
                    if(isset($this->obj_schema) && !empty($this->obj_schema->getProperties())){
                        // Check left hand side's type
                        $arr_properties = $this->obj_schema->getProperties();
                        if(isset($arr_properties[$arr_matches['lhs']])){
                            $int_current_type = $arr_properties[$arr_matches['lhs']]['type'];
                            switch($int_current_type) {
                                case Schema::PROPERTY_STRING:
                                    if(substr($arr_matches['rhs'], 0, strlen(self::TOKEN_PREFIX))
                                        != self::TOKEN_PREFIX){
                                        // If the right hand side has not been tokenized
                                        throw new GQL("Invalid string representation in: [{$str_condition}]");
                                    }
                                    break;
                                // @todo Add support for other type's validity here
                            }
                        } else {
                            // We have a Schema, but it does not contain a definition for this property.
                            // So, skip validation checks (we must support onl-the-fly Schemas)
                        }
                    }
                    $this->arr_conditions[] = [
                        'lhs' => $arr_matches['lhs'],
                        'comp' => $str_comp,
                        'op' => $int_operator,
                        'rhs' => $this->lookupToken($arr_matches['rhs'])
                    ];
                } else {
                    throw new GQL("Failed to parse condition: [{$str_condition}]");
                }
            }
            return '';
        }
        /**
         * Lookup the field in our token & named parameter list
         *
         * Use array index string access for fast initial check
         *
         * @param $str_val
         * @return mixed
         */
        private function lookupToken($str_val)
        {
            if('__key__' === $str_val) {
                return $str_val;
            }
            if('_' === $str_val[0]) {
                if(isset($this->arr_tokens[$str_val])) {
                    return $this->arr_tokens[$str_val];
                }
            }
            if('@' === $str_val[0]) {
                $str_bind_name = substr($str_val, 1);
                if(isset($this->arr_named_params[$str_bind_name])) {
                    return $this->arr_named_params[$str_bind_name];
                }
            }
            return $str_val;
        }
        /**
         * Get the query Kind
         *
         * @return string
         */
        public function getKind()
        {
            return $this->str_kind;
        }
        /**
         * Get the query limit
         *
         * @return int
         */
        public function getLimit()
        {
            return $this->int_limit;
        }
        /**
         * Get the offset
         *
         * @return int
         */
        public function getOffset()
        {
            return $this->int_offset;
        }
        /**
         * Get any start cursor
         *
         * @return string
         */
        public function getStartCursor()
        {
            return $this->str_start_cursor;
        }
        /**
         * Get any end cursor
         *
         * @return string
         */
        public function getEndCursor()
        {
            return $this->str_end_cursor;
        }
        /**
         * Get any order bys
         *
         * @return array
         */
        public function getOrderBy()
        {
            return $this->arr_order_bys;
        }
        /**
         * Get any filters
         *
         * @return array
         */
        public function getFilters()
        {
            return $this->arr_conditions;
        }
    }
    
    class Geopoint implements \ArrayAccess
    {
        private $flt_lat = 0.0;
        private $flt_lon = 0.0;
        public function __construct($latitude = 0.0, $longitude = 0.0)
        {
            $this->flt_lat = (float)$latitude;
            $this->flt_lon = (float)$longitude;
        }
        public function getLatitude()
        {
            return $this->flt_lat;
        }
        public function getLongitude()
        {
            return $this->flt_lon;
        }
        public function setLatitude($latitude)
        {
            $this->flt_lat = (float)$latitude;
            return $this;
        }
        public function setLongitude($longitude)
        {
            $this->flt_lon = (float)$longitude;
            return $this;
        }
        /**
         * ArrayAccess
         *
         * @param mixed $offset
         * @return bool
         */
        public function offsetExists($offset)
        {
            return (0 === $offset || 1 === $offset);
        }
        /**
         * ArrayAccess
         *
         * @param mixed $offset
         * @return float
         */
        public function offsetGet($offset)
        {
            if(0 === $offset) {
                return $this->getLatitude();
            }
            if(1 === $offset) {
                return $this->getLongitude();
            }
            throw new \UnexpectedValueException("Cannot get Geopoint data with offset [{$offset}]");
        }
        /**
         * ArrayAccess
         *
         * @param mixed $offset
         * @param mixed $value
         * @return $this|Geopoint
         */
        public function offsetSet($offset, $value)
        {
            if(0 === $offset) {
                $this->setLatitude($value);
                return;
            }
            if(1 === $offset) {
                $this->setLongitude($value);
                return;
            }
            throw new \UnexpectedValueException("Cannot set Geopoint data with offset [{$offset}]");
        }
        /**
         * ArrayAccess
         *
         * @param mixed $offset
         */
        public function offsetUnset($offset)
        {
            if(0 === $offset) {
                $this->setLatitude(0.0);
                return;
            }
            if(1 === $offset) {
                $this->setLongitude(0.0);
                return;
            }
            throw new \UnexpectedValueException("Cannot unset Geopoint data with offset [{$offset}]");
        }
    }

    class GQL extends \Exception
    {

    }
}