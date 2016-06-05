<?php
class API extends RESTful
{
    var $schema = [];
    var $dschema = [];

    /* @var $ds DataStore */
    var $ds = null;

    function main() {
        $this->checkMethod('POST');
        if(!$this->error && !strlen($this->core->config->get('DataStoreSpaceName'))) $this->setError('Missing DataStoreSpaceName config-var',503);
        else {
            $this->updateReturnResponse(['spacename'=>$this->core->config->get('DataStoreSpaceName')]);
        }
        if(!$this->error) $this->checkSchema();
        if(!$this->error) $this->checkParams();

        if(!$this->error) switch ($this->params[0]) {
            case "in":
            case "up":
            case "upin":
                // Check if we have info in the database
                $ret = $this->ds->fetchByKeys($this->formParams['user']);
                if(!$this->ds->error) {
                    // Check potential errors
                    switch ($this->params[0]) {
                        case 'in':
                            if(!count($ret)) $this->setError('User does no exist',404);
                            else {
                                if(!$this->core->system->checkPassword($this->formParams['password'],$ret[0]['Password']))
                                    $this->setError('Wrong user/password',401);
                            }
                            break;
                        case 'up':
                            if(count($ret)) $this->setError('User already exist');
                            break;
                    }



                    // Executing the logic
                    if(!$this->error) switch ($this->params[0]) {
                        case 'in':
                            $ret[0]['Password'] = '*****';
                            $this->addReturnData($ret[0]);
                            break;
                        case 'up':
                        case 'upin':
                            $record =array($this->schema['user']['field']=>$this->formParams['user']);
                            $record[$this->schema['password']['field']] = $this->core->system->crypt($this->formParams['password']);
                            $record[$this->schema['fingerprint']['field']] = json_encode($this->core->system->getRequestFingerPrint(),JSON_PRETTY_PRINT);
                            $record[$this->schema['dateinsertion']['field']] = 'now';
                            // $this->core->system->checkPassword($passw
                            if (is_array($this->schema['signup']))
                                foreach ($this->schema['signup'] as $key => $item) {
                                    if(isset($item['oninsertvalue'])) $this->formParams[$key] = $item['oninsertvalue'];
                                    $record[$item['field']] = $this->formParams[$key];
                                }
                            $ret = $this->ds->createEntities($record);
                            $ret[0]['Password'] = '*****';
                            if(!$this->ds->error) {
                                $this->addReturnData($ret[0]);
                            }
                            break;
                    }

                } else {
                    $this->setError($this->ds->errorMsg,503);
                }
                break;
        }
    }

    function checkSchema()
    {
        $schema = '{ "entity":"CloudFrameWorkUsers",
                     "user": {"field":"User","validateEmail":true},
                     "password": {"field":"Password","minlength":8},
                     "fingerprint": {"field":"Fingerprint"},
                     "dateinsertion": {"field":"DateInsertion"},
                     "signup": {
                         "name": {"field":"FullName","index":true},
                         "active": {"field":"Active","type":"boolean","oninsertvalue":true,"optional":true},
                         "json": {"field":"JSON"}
                     }
            }';

        $this->core->config->set('SignInUpSchema',json_decode($schema,true));
        if(!$this->error &&  !is_array($this->core->config->get('SignInUpSchema')))
            $this->setError('Missing SignInUpSchema config-var',503);
        else {
            $schema = $this->core->config->get('SignInUpSchema');
            if(!$this->error && !strlen($schema['entity'])) $this->setError('Missing entity in SignInUpSchema ',503);
            if(!$this->error && !strlen($schema['user']['field'])) $this->setError('Missing user.field in SignInUpSchema ',503);
            if(!$this->error && !strlen($schema['password']['field'])) $this->setError('Missing password.field in SignInUpSchema ',503);
            if(!$this->error && is_array($schema['signup'])) {
                foreach ($schema['signup'] as $key => $item) {
                    if(empty($item['field'])) {
                        $this->setError('Missing signup.'.$key.'.field');
                    }
                }
            }
        }

        // Asign the shema
        if(!$this->error ) {
            /* @var $ds DataStore */
            $dschema = [$schema['entity']=>[$schema['user']['field']=>['keyname','index']]];
            $dschema[$schema['entity']][$schema['password']['field']] = ['string'];
            $dschema[$schema['entity']][$schema['fingerprint']['field']] = ['string'];
            $dschema[$schema['entity']][$schema['dateinsertion']['field']] = ['datetime','index'];
            if(is_array($schema['signup'])) foreach ($schema['signup'] as $key => $item) {
                $dschema[$schema['entity']][$item['field']]=[(strlen($item['type']))?$item['type']:'string'];
                if(isset($item['index'])) $dschema[$schema['entity']][$item['field']][]='index';
            }
            $this->ds = $this->core->loadClass('DataStore',[$schema['entity'],$this->core->config->get('DataStoreSpaceName'),$dschema[$schema['entity']]]);
            if ($ds->error) $this->setError($this->ds->errorMsg);

        }

        if(!$this->error ) {
            $this->schema = $schema;
            $this->dschema = $dschema;
        }


    }
    function checkParams() {
        if(!$this->error) $this->checkMandatoryParam(0,'use signupin/{up|in|upin}',['up','in','upin']);
        if(!$this->error) $this->checkMandatoryFormParams(['user','password']);
        if(!$this->error && in_array($this->params[0],['up','upin'])) {
            if (is_array($this->schema['signup']))
                foreach ($this->schema['signup'] as $key => $item) {
                    if(!$item['optional']) $this->checkMandatoryFormParam($key);
                }
        }

    }
}
