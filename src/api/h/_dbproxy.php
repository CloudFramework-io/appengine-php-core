<?php
class API extends RESTful
{
    /* @var $db CloudSQL */
    var $db;
    function main()
    {
        ini_set('memory_limit', '512M');
        $this->checkMethod('POST');
        if(!$this->error) $this->checkSecurity();
        if(!$this->error) $this->checkMandatoryFormParams('q');
        if(!$this->error) {
            // Trying the connection
            $this->db->connect();
            if($this->db->error()) $this->setError('Error connecting db',503);
            else {
                $ret = $this->db->getDataFromQuery('SELECT '.$this->formParams['q']);
                if($this->db->error()) $this->setError('Error in query: '.$this->db->getQuery());
                else {
                    $this->db->close();
                    if(isset($this->formParams['plain']))
                        $this->addReturnData($ret);
                    else
                        $this->addReturnData(utf8_encode(gzcompress(json_encode($ret))));
                    unset($ret);
                }
            }
        }

    }

    function checkSecurity() {
        if($this->existBasicAuth()) {
            // if checkBasicAuthWithConfig has been passed we can get the array stored in setConf('CLOUDFRAMEWORK-ID-'.$id);
            if ($this->checkBasicAuthSecurity()) {
                $info = $this->getCloudFrameWorkSecurityInfo();
                $org = $info['org_id'];
                $org_name = $info['org_name'];
                if (!$this->error && !strlen($org)) $this->setError('Missing org_id in conf-var array: CLOUDFRAMEWORK-ID-XX', 503);
                if (!$this->error && !strlen($org_name)) $this->setError('Missing org_name in conf-var array: CLOUDFRAMEWORK-ID-XX', 503);
                $this->updateReturnResponse(['security'=>'Basic Authentication']);

            }
        } elseif ($this->checkCloudFrameWorkSecurity(600)) {
            // if checkCloudFrameWorkSecurity has been passed we can get the array stored in setConf('CLOUDFRAMEWORK-ID-'.$id);
            $info = $this->getCloudFrameWorkSecurityInfo();
            $org = $info['org_id'];
            $org_name = $info['org_name'];
            if (!$this->error && !strlen($org)) $this->setError('Missing org_id in conf-var array: CLOUDFRAMEWORK-ID-XX', 503);
            if (!$this->error && !strlen($org_name)) $this->setError('Missing org_name in conf-var array: CLOUDFRAMEWORK-ID-XX', 503);
            $this->updateReturnResponse(['security'=>'X-CLOUDFRAMEWORK-SECURITY']);
        }
        if(!$this->error) $this->checkMandatoryFormParams('dbproxy_id');
        if(!$this->error) $this->checkMandatoryFormParams('dbproxy_passw');

        if(!$this->error && (!is_array($info['dbproxy']) || !is_array($info['dbproxy'][$this->formParams['dbproxy_id']]) ||!strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['passw']))) $this->setError('No dbproxy not configured for that id');
        if(!$this->error && !$this->core->system->checkPassword($this->formParams['dbproxy_passw'],$info['dbproxy'][$this->formParams['dbproxy_id']]['passw']))  $this->setError('Wrong dbproxy credentials',401);


        // Copy values for the diffeerent environments
        if(!$this->error) {
            if ($this->core->is->development()) {
                if (isset($info['dbproxy'][$this->formParams['dbproxy_id']]['development:'])) {
                    foreach ($info['dbproxy'][$this->formParams['dbproxy_id']]['development:'] as $key => $value) {
                        $info['dbproxy'][$this->formParams['dbproxy_id']][$key] = $value;
                    }
                }
            } else {
                if (isset($info['dbproxy'][$this->formParams['dbproxy_id']]['production:'])) {
                    foreach ($info['dbproxy'][$this->formParams['dbproxy_id']]['production:'] as $key => $value) {
                        $info['dbproxy'][$this->formParams['dbproxy_id']][$key] = $value;
                    }
                }
            }
        }

        if(!$this->error && !strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_server']) && !strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_socket'])) $this->setError('Missing db_server or db_socket field in dbproxy connection: '.$this->formParams['dbproxy_id'],503);
        if(!$this->error && !strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_name'])) $this->setError('Missing db_server or db_socket field in dbproxy connection: '.$this->formParams['dbproxy_id'],503);
        if(!$this->error && !strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_user'])) $this->setError('Missing db_user  field in dbproxy connection: '.$this->formParams['dbproxy_id'],503);
        if(!$this->error && !isset($info['dbproxy'][$this->formParams['dbproxy_id']]['db_password'])) $this->setError('Missing db_password  field in dbproxy connection: '.$this->formParams['dbproxy_id'],503);
        if(!$this->error) {
            if(strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_socket']))
                $this->core->config->set('dbSocket', $info['dbproxy'][$this->formParams['dbproxy_id']]['db_socket']);

            if(strlen($info['dbproxy'][$this->formParams['dbproxy_id']]['db_server']))
                $this->core->config->set('dbServer',$info['dbproxy'][$this->formParams['dbproxy_id']]['db_server']);
            $this->core->config->set('dbUser', $info['dbproxy'][$this->formParams['dbproxy_id']]['db_user']);
            $this->core->config->set('dbPassword', $info['dbproxy'][$this->formParams['dbproxy_id']]['db_password']);
            $this->core->config->set('dbName', $info['dbproxy'][$this->formParams['dbproxy_id']]['db_name']);

            $this->db = $this->core->loadClass('CloudSQL');
        }

    }
}