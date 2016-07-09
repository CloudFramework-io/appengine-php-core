<?php

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

                // Avoid optional vars
                if(!isset($value['type']))
                    $this->setError('Missing type attribute in model for '.$extrakey.$key);
                elseif(isset($value['validation']) && strpos($value['validation'],'optional')!==false && !isset($data[$key]))
                    continue;
                elseif(isset($value['validation']) && strpos($value['validation'],'optional')===false && !isset($data[$key]))
                    $this->setError('Missing '.$extrakey.$key);
                elseif(!$this->validType($extrakey.$key,$value['type'],$data[$key]))
                    $this->setError('Wrong type or empty. '.$extrakey.$key.' does not match with '.$value['type']);
                elseif($value['type']=='model') {
                    // Recursive CALL
                    $this->validateModel($value['fields'],$data[$key],$dictionaries,$extrakey.$key.'-');
                }
                elseif(isset($value['validation']) && !$this->validContent($extrakey.$key,$value['validation'],$data[$key]))
                    $this->setError('Wrong content in '.$extrakey.$key);

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

        public function validType($key,$type,&$data) {
            if(empty($data)) return false;

            switch ($type) {
                case "string": return is_string($data);
                case "integer": return is_integer($data);
                case "model": return is_array($data) && !empty($data);
                case "json": return is_string($data) && is_object(json_encode($data));
                case "name": return $this->validateName($key,$data);
                case "ip": return filter_var($data,FILTER_VALIDATE_IP);
                case "url": return filter_var($data,FILTER_VALIDATE_URL);
                case "email": return $this->validateEmail($key,$data);
                case "phone": return is_string($data);

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