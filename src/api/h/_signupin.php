<?php
class API extends RESTful
{
    var $schema = [];
    var $dschema = [];

    /* @var $ds DataStore */
    var $dsUsers = null;
    var $dsAuths = null;

    function main() {

        $schema = '[{ "entity":"CloudFrameWorkUsers",
                     "user": {"field":"User"},
                     "auths":{"field":"AuthTypes"},
                     "fingerprint": {"field":"Fingerprint"},
                     "signup": {
                         "date": {"field":"DateUpdating","oninsertvalue":"now","hidden":true},
                         "name": {"field":"FullName"},
                         "email": {"field":"Email"},
                         "active":{"field":"Active","oninsertvalue":true,"optional":true}
                     }
                    },
                    { "entity":"CloudFrameWorkUserAuths",
                      "type": {"field":"Type"},
                      "user": {"field":"User","minlength":8},
                      "token_id": {"field":"TokenId","minlength":8},
                      "token_secret": {"field":"TokenSecret"},
                      "fingerprint": {"field":"Fingerprint"},
                      "dateinsertion": {"field":"DateInsertion"},
                       "signup": {
                         "name": {"field":"FullName","index":true},
                         "active": {"field":"Active","type":"boolean","oninsertvalue":true,"optional":true},
                         "json": {"field":"JSON"}
                     }
                    }]';

        $this->core->config->set('SignInUpSchema',json_decode($schema,true));

        $this->checkSecurity();
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
                            elseif(!$this->core->system->checkPassword($this->formParams['password'],$ret[0]['Password']))
                                    $this->setError('Wrong user/password',401);
                            break;
                        case 'up':
                            if(count($ret)) $this->setError('User already exist');
                            break;
                        case 'upin':
                            if(count($ret)) {
                                if(!$this->core->system->checkPassword($this->formParams['password'],$ret[0]['Password']))
                                    $this->setError('Wrong user/password',401);
                            }
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

                            // User
                            $record =array($this->schema['user']['field']=>$this->formParams['user']);

                            // The password always encrypted..
                            $record[$this->schema['password']['field']] = $this->core->system->crypt($this->formParams['password']);

                            // Save web_key together with finger print for security reasons.
                            $record[$this->schema['fingerprint']['field']] = json_encode(array_merge(['web_key'=>$this->core->security->getWebKey()],$this->core->system->getRequestFingerPrint()),JSON_PRETTY_PRINT);

                            // Dato of insertion
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
    function checkSecurity() {

        // If the pass a WebKey.. they will need credentials.
        // Needed for XHR CORS cross-reference
        if($this->core->security->existWebKey()) {
            if($this->core->security->checkWebKey()) {
                $this->sendCorsHeaders('GET');
            }
        }

    }
    function checkSchema()
    {


        if(!$this->error &&  !is_array($this->core->config->get('SignInUpSchema')))
            $this->setError('Missing SignInUpSchema config-var',503);
        else {
            $schema = $this->core->config->get('SignInUpSchema');
            // Entity 0
            if (!$this->error && !strlen($schema[0]['entity'])) $this->setError('Missing entity in SignInUpSchema.Users ', 503);
            if (!$this->error && !strlen($schema[0]['user']['field'])) $this->setError('Missing user field in SignInUpSchema.Users ', 503);
            if (!$this->error && !strlen($schema[0]['auths']['field'])) $this->setError('Missing auths field in SignInUpSchema.Users ', 503);
            if (!$this->error && !strlen($schema[0]['fingerprint']['field'])) $this->setError('Missing fingerprint field in SignInUpSchema.Users ', 503);
            if (!$this->error && is_array($schema[0]['signup'])) {
                foreach ($schema[0]['signup'] as $key => $item) {
                    if (empty($item['field'])) {
                        $this->setError('Missing signup.' . $key . '.field  in SignInUpSchema.Users');
                    }
                }
            }

            // Entity 1
            if (!$this->error && !strlen($schema[1]['entity'])) $this->setError('Missing entity in SignInUpSchema.UserAuths', 503);
            if (!$this->error && !strlen($schema[1]['type']['field'])) $this->setError('Missing type field in SignInUpSchema.UserAuths', 503);
            if (!$this->error && !strlen($schema[1]['user']['field'])) $this->setError('Missing user field in SignInUpSchema.UserAuths', 503);
            if (!$this->error && !strlen($schema[1]['token_id']['field'])) $this->setError('Missing token_id field in SignInUpSchema.UserAuths', 503);
            if (!$this->error && !strlen($schema[1]['token_secret']['field'])) $this->setError('Missing token_secret field in SignInUpSchema.UserAuthss ', 503);
            if (!$this->error && !strlen($schema[1]['fingerprint']['field'])) $this->setError('Missing fingerprint field in SignInUpSchema.UserAuths', 503);
            if (!$this->error && !strlen($schema[1]['dateinsertion']['field'])) $this->setError('Missing dateinsertion field in SignInUpSchema.UserAuths', 503);
            if (!$this->error && is_array($schema[1]['signup'])) {
                foreach ($schema[1]['signup'] as $key => $item) {
                    if (empty($item['field'])) {
                        $this->setError('Missing signup.' . $key . '.field  in SignInUpSchema.UserAuths');
                    }
                }
            }
        }
        // Asign the schema
        if(!$this->error ) {
            /* @var $ds DataStore */
            $dschema = [$schema[0]['entity']=>[$schema[0]['user']['field']=>['keyname','index']]];
            $dschema[$schema[0]['entity']][$schema[0]['auths']['field']] = ['list'];
            $dschema[$schema[0]['entity']][$schema[0]['fingerprint']['field']] = ['string'];
            if(is_array($schema[0]['signup'])) foreach ($schema[0]['signup'] as $key => $item) {
                $dschema[$schema[0]['entity']][$item['field']]=[(strlen($item['type']))?$item['type']:'string'];
                if(isset($item['index'])) $dschema[$schema[0]['entity']][$item['field']][]='index';
            }

            $dschema[$schema[1]['entity']][$schema[1]['user']['field']] = ['string','index'];
            $dschema[$schema[1]['entity']][$schema[1]['type']['field']] = ['string','index'];
            $dschema[$schema[1]['entity']][$schema[1]['token_id']['field']] = ['string'];
            $dschema[$schema[1]['entity']][$schema[1]['token_secret']['field']] = ['string'];
            $dschema[$schema[1]['entity']][$schema[1]['fingerprint']['field']] = ['string'];
            $dschema[$schema[1]['entity']][$schema[1]['dateinsertion']['field']] = ['datetime'];

            if(is_array($schema[1]['signup'])) foreach ($schema[1]['signup'] as $key => $item) {
                $dschema[$schema[1]['entity']][$item['field']]=[(strlen($item['type']))?$item['type']:'string'];
                if(isset($item['index'])) $dschema[$schema[1]['entity']][$item['field']][]='index';
            }

            $this->dsUsers = $this->core->loadClass('DataStore',[$schema[0]['entity'],$this->core->config->get('DataStoreSpaceName'),$dschema[$schema[0]['entity']]]);
            $this->dsAuths = $this->core->loadClass('DataStore',[$schema[1]['entity'],$this->core->config->get('DataStoreSpaceName'),$dschema[$schema[1]['entity']]]);
            if ($ds->error) $this->setError($this->ds->errorMsg);



        }

        if(!$this->error ) {
            $this->schema = $schema;
            $this->dschema = $dschema;
            $this->updateReturnResponse(['entity'=>$schema['entity']]);
        }
    }

    function checkParams() {

        if(!$this->error) $this->checkMandatoryParam(0,'use signupin/{up|in|upin|social}',['up','in','upin','social']);
        if(!$this->error)
            if($this->params[0]=='social') {
                $this->checkMandatoryFormParams(['user', 'socialnetwork']);
            } else
                $this->checkMandatoryFormParams(['user','password']);

        if(!$this->error && in_array($this->params[0],['up','upin','social'])) {
            if (is_array($this->schema[0]['signup']))
                foreach ($this->schema[0]['signup'] as $key => $item) {
                    if(!$item['optional'] && !$item['hidden'] ) $this->checkMandatoryFormParam($key);
                }
            if (is_array($this->schema[1]['signup']))
                foreach ($this->schema[1]['signup'] as $key => $item) {
                    if(!$item['optional'] && !$item['hidden'] ) $this->checkMandatoryFormParam($key);
                }
        }

    }
}
