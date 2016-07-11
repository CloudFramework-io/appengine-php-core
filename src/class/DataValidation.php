<?php

// Based on https://github.com/Wixel/GUMP
// DataValidationClass
if (!defined ("_DATAVALIDATION_CLASS_") ) {
    define("_DATAVALIDATION_CLASS_", TRUE);

    Class DataValidation {

        var $field=null;
        var $errorMsg='';
        var $error=false;
        var $errorFields = [];

        public function validateModel (array &$model,array &$data,array &$dictionaries=[],$extrakey='') {

            $error = '';
            foreach ($model as $key=>$value) {

                // Does type field exist?.. If not return false and break the loop
                if(!isset($value['type'])) {
                    $this->setError('Missing type attribute in model for ' . $extrakey . $key);
                    return false;
                }

                // Transform values and check if we have an empty value
                if(isset($value['validation'])) {
                    // Transform values based on defaultvalue, forcevalue, tolowercase, touppercase,trim
                    $data[$key] = $this->transformValue($data[$key],$value['validation']);

                    if(empty($data[$key])) {

                        // Allow empty values if we have optional in options
                        if(strpos($value['validation'],'optional')!==false)
                            continue;  // OK.. next
                        else
                            $this->setError('Missing '.$extrakey.$key);
                    }
                }

                // Let's valid types and recursive contents..
                if(!$this->error) {
                    if(!$this->validType($extrakey.$key,$value['type'],$data[$key]))
                        $this->setError(((empty($data[$key]))?'Empty':'Wrong').' data received for field {'.$extrakey.$key.'} with type {'.$value['type'].'}');
                    elseif($value['type']=='model') {
                        // Recursive CALL
                        $this->validateModel($value['fields'],$data[$key],$dictionaries,$extrakey.$key.'-');
                    }
                    elseif(isset($value['validation']) && !$this->validContent($extrakey.$key,$value['validation'],$data[$key]))
                        $this->setError('Wrong content in '.$extrakey.$key);
                }

                if($this->error) {
                    if(!strlen($this->field ))
                        $this->field  = $extrakey.$key;
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
            }elseif(strpos($options,'defaultvalue:')!==false && !strlen($data)) {
                $data = $this->extractOptionValue('forcevalue:',$options);
            }

            if( strpos($options,'tolowercase')!==false) $data = strtolower($data);
            if( strpos($options,'touppercase')!==false) $data = strtoupper($data);
            if( strpos($options,'trim')!==false) $data = trim($data);

            return $data;
        }

        /**
         * Validate no empty data based in the type
         * @param $key
         * @param $type
         * @param $data
         * @return bool
         */
        public function validType($key, $type, &$data) {

            if(!is_bool($data) && empty($data)) return false;

            switch (strtolower($type)) {
                case "string": return is_string($data);
                case "integer": return is_integer($data);
                case "model": return is_array($data) && !empty($data);
                case "json": return is_string($data) && is_object(json_encode($data));
                case "name": return $this->validateName($key,$data);
                case "ip": return filter_var($data,FILTER_VALIDATE_IP);
                case "url": return filter_var($data,FILTER_VALIDATE_URL);
                case "email": return $this->validateEmail($key,$data);
                case "phone": return is_string($data);
                case "zip": return is_string($data);
                case "keyname": return is_string($data);
                case "date": return $this->validateDate($data);
                case "datetime": return $this->validateDateTime($data);
                case "currency": return is_float($data);
                case "boolean": return is_bool($data);
                case "array": return is_array($data);
                case "float": return is_float($data);

                default: return false;
            }
        }

        public function validContent($key,$options,&$data, array &$dictionaries=[]) {

            if(!strlen(trim($options))) return true;
            if(strpos($options,'optional')===false && empty($data)) return false;


            // converters
            if(strpos($options,'trim')!== false && is_string($data)) $data = trim($data);
            if(strpos($options,'lowercase')!== false && is_string($data)) $data = strtolower($data);
            if(strpos($options,'uppercase')!== false && is_string($data)) $data = strtoupper($data);

            // Potential Validators
            if(!$this->validateMaxLength($key,$options,$data)) return false;
            if(!$this->validateMinLength($key,$options,$data)) return false;
            if(!$this->validateFixLength($key,$options,$data)) return false;


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
            if($data =='now' || (strlen($data)>=15 && strlen($data)<=17)) {
                try {
                    $value_time = new DateTime($data);
                    return true;
                } catch (Exception $e) {
                    // Is not a valida Date
                }
            }
            return false;
        }



        public function validateMaxLength($key,$options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'maxlength:')!==false)){
                if(!is_integer($options)) $options = intval($this->extractOptionValue('maxlength:',$options));

                if(strlen($data) > $options) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        public function validateMinLength($key,$options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'minlength:')!==false)){
                if(!is_integer($options) ) $options = intval($this->extractOptionValue('minlength:',$options));

                if(strlen($data) < $options) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        public function validateFixLength($key, $options,$data) {
            if(strlen($options) && (is_integer($options) || strpos($options,'fixlength:')!==false)){
                if(!is_integer($options) ) $options = intval($this->extractOptionValue('fixlength:',$options));

                if(strlen($data) != $options) {
                    $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'options'=>$options,'data'=>$data];
                    return false;
                }
            }
            return true;
        }

        public function validateEmail($key,$data) {
            if(!filter_var($data,FILTER_VALIDATE_EMAIL)) {
                $this->errorFields[] = ['key'=>$key,'method'=>__FUNCTION__,'data'=>$data];
                return false;
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