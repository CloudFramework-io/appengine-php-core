<?php

// Based on https://github.com/Wixel/GUMP
// DataValidationClass
if (!defined ("_DATAVALIDATION_CLASS_") ) {
    define("_DATAVALIDATION_CLASS_", TRUE);

    Class DataValidation {

        var $field=null;
        var $typeError = 'field';
        var $errorMsg='';
        var $error=false;
        var $errorFields = [];

        /**
         * Validate the content of $data based on $model
         * @param array $model
         * @param array $data
         * @param array $dictionaries
         * @param bool $all
         * @param string $extrakey
         * @return bool
         */
        public function validateModel (array &$model, array &$data, array &$dictionaries=[], $all=true, $extrakey='') {

            $error = '';
            foreach ($model as $key=>$value) {
                //  because $all==true  Ignore those fields that does not exist in $data and are optional or internal
                if($all && !key_exists($key,$data) && isset($value['validation']) && (strpos($value['validation'], 'optional') !== false || strpos($value['validation'], 'internal') !== false)) continue;
                // because $all==false Ignore those fields that does not exist in $data and they are not mandatory
                if(!$all && !key_exists($key,$data)) continue;

                // Does type field exist?.. If not return false and break the loop
                if(!isset($value['type'])) {
                    $this->setError('Missing type attribute in model for ' . $extrakey . $key);
                    return false;
                }

                //region: excludeifexist:
                // $excludeif controls the exintence of the field depends on others fields
                $excludeif = [];
                if (isset($value['validation']) && strpos($value['validation'], 'excludeifexist:') !== false) {
                    $excludeif = explode(',',$this->extractOptionValue('excludeifexist:',$value['validation']));
                    foreach ($excludeif as $excludefield) if(strlen($excludefield = trim($excludefield))) {
                        if(!isset($model[$excludefield])) {
                            $this->setError('Wrong \'excludeifexist:\' tag in '.$extrakey . $key.'. Missing field attribute in model for \'' . $excludefield.'\'' );
                            $this->typeError = 'model';
                        } else {
                            // If it exist and also the exludes then error
                            if(key_exists($key,$data)) {
                                if (key_exists($excludefield,$data)) {
                                    $this->setError('This field is not allowed because other field exists: \'' . $excludefield . '\'');
                                    break;
                                }
                            } else {
                                if (!key_exists($excludefield,$data) && stripos($value['validation'],'allownull')===false) {
                                    $this->setError('This field is mandatory because is missing other field in \'excludeifexist:' . $excludefield . '\'');
                                    break;
                                }
                            }
                        }
                    }
                    // If the field does not exist but there are exclude fields and there is not error.. continue to next field
                    if(!$this->error && strlen(trim($excludeif[0])) && !key_exists($key,$data)) continue;
                }
                //endregion

                // Transform values and check if we have an empty value
                if(!$this->error && isset($value['validation'])) {

                    // Transform values based on defaultvalue, forcevalue, tolowercase, touppercase,trim
                    if(!array_key_exists($key,$data)) $data[$key] = null;
                    $data[$key] = $this->transformValue($data[$key],$value['validation']);

                    if( null===$data[$key] || (is_string($data[$key]) && !strlen($data[$key])) ||  (is_array($data[$key]) && !count($data[$key]))) {
                        // OPTIONAL: -- Allow empty values if we have optional in options
                        if(stripos($value['validation'],'allownull')!==false) {
                            continue;  // OK.. next
                        }else {
                            if(!key_exists($key,$data))
                                $this->setError('Missing '.$extrakey.$key);
                            else
                                $this->setError('Empty value for '.$extrakey.$key);
                        }
                    }

                }


                // Let's valid types and recursive contents..
                if(!$this->error) {
                    if(!$this->validType($extrakey.$key,$value['type'],$data[$key])) {
                        $this->setError(((is_string($data[$key]) && !strlen($data[$key]))?'Empty':'Wrong').' data received for field {'.$extrakey.$key.'} with type {'.$value['type'].'} value=['.json_encode($data[$key]).']');
                    }
                    elseif($value['type']=='model') {
                        // Recursive CALL
                        $this->validateModel($value['fields'],$data[$key],$dictionaries,$all,$extrakey.$key.'-');
                    }
                    elseif(isset($value['validation']) && !$this->validContent($extrakey.$key,$value['validation'],$data[$key]))
                        $this->setError('Wrong content in field {'.$extrakey.$key.'} with validation {'.$value['validation'].'}');
                }

                if($this->error) {
                    if(!strlen($this->field ))
                        $this->field  = $extrakey.$key.': ['.$value['type'].']('.(isset($value['validation'])?:'').')';
                    return false;
                }
            }
            return !$this->error;
        }

        function setError($msg) {
            $this->error=true;
            $this->errorMsg = $msg;
        }

        /**
         * Transform data based on obtions: forcevalue, defaultvalue, trim, tolowercase, touppercase
         * @param $data
         * @param $options
         */
        public function transformValue($data, $options) {

            if(strpos($options,'forcevalue:')!==false) {
                $data = $this->extractOptionValue('forcevalue:',$options);
                //if deault is "null"
                if($data=="null") $data=null;
            } elseif(strpos($options,'defaultvalue:')!==false && !strlen($data) && !is_bool($data) ) {
                $data = $this->extractOptionValue('defaultvalue:',$options);
                //if deault is "null"
                if($data=="null") $data=null;
            }

            if( strpos($options,'tolowercase')!==false && strlen($data)) (is_array($data))?$data = array_map('strtolower',$data):$data = strtolower($data);
            if( strpos($options,'touppercase')!==false && strlen($data)) (is_array($data))?$data = array_map('strtoupper',$data):$data = strtoupper($data);
            if( strpos($options,'trim')!==false && strlen($data)) (is_array($data))?$data = array_map('trim',$data):$data = trim($data);
            if( strpos($options,'regex_delete:')!==false) {
                $regex = $this->extractOptionValue("regex_delete:",$options);
                if(strlen($regex)) {
                    if(is_array($data)) foreach ($data as &$item) $item = preg_replace("/$regex/",'',$item);
                    else $data = preg_replace("/$regex/",'',$data);
                }
            }

            //Convert a string into an array
            if( strpos($options,'toarray:')!==false && !is_array($data) && is_string($data)) {
                $sep = $this->extractOptionValue('toarray:',$options);
                if(strlen($data))
                    $data = explode($sep,$data);
                else $data = [];
            }

            //Convert an array into string
            if( strpos($options,'tostring:')!==false && is_array($data) ) {
                $sep = $this->extractOptionValue('tostring:',$options);
                if(!$sep) $sep=',';
                if(count($data))
                    $data = implode($sep,$data);
                else $data = "";
            }

            return $data;
        }

        /**
         * Validate no empty data based in the type
         * @param $key
         * @param $type
         * @param $data
         * @return bool
         */
        public function validType($key, $type,  &$data) {


            if(!is_bool($data) && !is_array($data) && is_string($data) && !strlen($data)) return false;

            // database conversion types
            $type = preg_replace('/\(.*/','',$type);

            switch (strtolower($type)) {
                case "varbinary": case "varchar": case "char": case "string": return is_string($data);
                case "text": case "txt": return is_string($data);
                case "integer": if(strval(intval($data))===strval($data)) $data=intval($data);return is_integer($data);
                case "decimal": case "float": if(floatval($data)!=0 || $data==="0" || $data === 0) $data=floatval($data);return is_float($data);
                case "bit": if(strval(intval($data))===strval($data)) $data=intval($data);return ($data==0 || $data==1);
                case "model": return is_array($data) && !empty($data);
                case "json": if(is_array($data)) $data = json_encode($data);return is_string($data) && is_array(json_decode($data,true));
                case "name": return $this->validateName($key,$data);
                case "ip": return filter_var($data,FILTER_VALIDATE_IP);
                case "url": return filter_var($data,FILTER_VALIDATE_URL);
                case "email": return is_string($data) && $this->validateEmail($key,"email",$data);
                case "emails": return is_array($data) && $this->validateEmail($key,"email",$data);
                case "phone": return is_string($data);
                case "zip": return is_string($data);
                case "keyname": return is_string($data);
                case "key": return is_string($data);
                case "date": return $this->validateDate($data);
                case "datetime": return $this->validateDateTime($data);
                case "datetimeiso": return $this->validateDateTimeISO($data);
                case "currency": return is_numeric($data);
                case "boolean": if(!is_bool($data) && ($data=='true' || $data=='false')) $data = ($data == 'true');return is_bool($data);
                case "array": return is_array($data);
                case "list": return is_array($data);
                case "array_to_string": if(is_array($data)) $data=implode(",",$data);return is_string($data);
                default: return false;
            }
        }

        public function validContent($key,$options,&$data, array &$dictionaries=[]) {

            if(!strlen(trim($options))) return true;
            if(strpos($options,'optional')===false && is_string($data) && !strlen($data)) return false;

            // Potential Validators
            if(!$this->validateMaxLength($key,$options,$data)) return false;
            if(!$this->validateMinLength($key,$options,$data)) return false;
            if(!$this->validateFixLength($key,$options,$data)) return false;
            if(!$this->validateEmail($key,$options,$data)) return false;
            if(!$this->validateRegexMatch($key,$options,$data)) return false;
            if(!$this->validateValues($key,$options,$data)) return false;
            if(!$this->validateRange($key,$options,$data)) return false;
            if(!$this->validateUnsigned($key,$options,$data)) return false;


            return true;
        }

        /**
         * Formats: Length bt. 8 to 10 depending of the year formar (YY or YYYY)
         * @param $data
         * @return bool
         */
        public function validateDate($data)
        {

            if($data =='now' || (strlen($data)>=8 && strlen($data)<=10)) {
                try {
                    $value_time = new DateTime($data);
                    return true;
                } catch (Exception $e) {
                    // Is not a valida Date
                }
            }
            return false;
        }

        /**
         * Formats: Length bt. 15 to 17 depending of the year formar (YY or YYYY)
         * @param $data
         * @return bool
         */
        public function validateDateTime($data)
        {
            if($data =='now' || (strlen($data)>=15)) {
                try {
                    $value_time = new DateTime($data);
                    return true;
                } catch (Exception $e) {
                    // Is not a valida Date
                    $this->errorFields[] = [$e.$this->errorMsg];
                }
            } else {
                $this->errorFields[] = 'DateTime field is not "now" o it does not have 15 characters';
            }
            return false;
        }

        /**
         * Formats: Length bt. 23 or 25  depending of the year formar (YY or YYYY)
         * @param $data
         * @return bool
         */
        public function validateDateTimeISO($data)
        {
            if($data =='now' || (strlen($data)>=23)) {
                try {
                    $value_time = new DateTime($data);
                    return true;
                } catch (Exception $e) {
                    $this->errorFields[] = [$e.$this->errorMsg];
                    // Is not a valida Date
                }
            }
            return false;
        }



        public function validateMaxLength($key,$options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'maxlength:')!==false)){
                if(!is_integer($options) ) $options = intval($this->extractOptionValue('maxlength:',$options));
                if(!is_array($data)) $data = [$data];
                foreach ($data as $item) {
                    if(strlen($item) > $options) {
                        $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                        return false;
                    }
                }
            }
            return true;
        }

        public function validateMinLength($key,$options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'minlength:')!==false)){
                if(!is_integer($options) ) $options = intval($this->extractOptionValue('minlength:',$options));
                if(!is_array($data)) $data = [$data];
                foreach ($data as $item) {
                    if(strlen($item) < $options) {
                        $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                        return false;
                    }
                }
            }
            return true;
        }

        public function validateFixLength($key, $options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'fixlength:')!==false)){
                if(!is_integer($options) ) $options = intval($this->extractOptionValue('fixlength:',$options));
                if(!is_array($data)) $data = [$data];
                foreach ($data as $item) {
                    if(strlen($item) != $options) {
                        $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                        return false;
                    }
                }
            }
            return true;
        }

        public function validateEmail($key,$options,$data) {
            if(strlen($options) && strpos($options,'email')!==false){
                if(!is_array($data)) $data = [$data];
                foreach ($data as $item) {
                    if(!filter_var($item,FILTER_VALIDATE_EMAIL)) {
                        $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'data'=>$data];
                        return false;
                    }
                }
            }
            return true;
        }

        public function validateRange($key,$options,$data) {
            if(strlen($options) && (strpos($options,'range:')!==false)){
                $options = explode(',',($this->extractOptionValue('range:',$options)));
                $ok=true;
                if(isset($options[0]) && strlen($options[0])) $ok = $data >= $options[0];
                if($ok && isset($options[1]) && strlen($options[1])) $ok = $data <= $options[1];
                if(!$ok) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        public function validateValues($key,$options,$data) {
            if(strlen($options) && (strpos($options,'values:')!==false)){
                $options = explode(',',($this->extractOptionValue('values:',$options)));
                $ok= in_array($data,$options);
                if(!$ok) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        public function validateUnsigned($key,$options,$data) {
            if(strlen($options) && (strpos($options,'unsigned')!==false)){
                if(intval($data) < 0) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        /**
         * Validate if the content match with a regex expresion
         * @param $key
         * @param $options
         * @param $data
         * @return bool|int
         */
        public function validateRegexMatch($key, $options, $data) {
            if(strlen($options) && strpos($options,'regex_match')!==false){
                $regex = $this->extractOptionValue('regex_match:',$options);
                if(strlen($regex)) {
                    if (is_string($data)) {
                        if (!preg_match('/'.$regex.'/', trim($data))) {
                            $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'regex'=>$regex];
                            return false;
                        }
                    } elseif(is_array($data)) foreach ($data as $item) {
                        if (!preg_match('/'.$regex.'/', trim($item))) {
                            $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'regex'=>$regex];
                            return false;
                        }
                    }
                }
            }
            return true;
        }

        public function validateName($key,$data) {
            if(strlen(trim($data)) < 2 || !preg_match("/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖßÙÚÛÜÝàáâãäåçèéêëìíîïñðòóôõöùúûüýÿ '-])+$/i", trim($data))) {
                $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'data'=>$data];
                return false;
            }
            return true;
        }

        private function extractOptionValue($tag,$options) {
            list($foo,$value) = explode($tag,$options,2);
            return(preg_replace('/( |\|).*/','',trim($value)));

        }


    }
}
