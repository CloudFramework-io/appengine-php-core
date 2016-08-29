<?php
class API extends RESTful
{
    /* @var $db CloudSQL */
    var $db;
    var $info = null;
    var $dbproxy = null;

    function __codes() {
        $this->addCodeLib('dbproxy-notfound','This dbproxy is not confgured',404);
    }
    function main()
    {
        if(!$this->checkSecurity()) return;

        if(!$this->params[0]) $this->addReturnData(array_keys($this->info['dbproxy']));
        else {
            if(!isset($this->info['dbproxy'][$this->params[0]])) return($this->setErrorFromCodelib('dbproxy-notfound'));
            else {
                $this->dbproxy = $this->info['dbproxy'][$this->params[0]];
                switch ($this->params[1]) {
                    case null;
                        $this->showTables();
                        break;
                    default:
                        $this->showTable($this->params[1]);
                        break;
                }
            }

        }
        $this->end();
    }

    function showTables() {

        $dataTables = null;
        if(!is_array($dataTables)) {
            if(!$this->init()) return;

            $tables = $this->db->getDataFromQuery('SHOW TABLES;');
            $dataTables = [];
            foreach ($tables as $table)
                $dataTables[] = array_values($table)[0];
        }
        $this->addReturnData([$this->core->config->get("dbName")=>$dataTables]);


    }

    function showTable($table) {
        $dataTables = null;
        if(!is_array($dataTables)) {
            if (!$this->init()) return;
            if(!isset($this->formParams['simple']))
                $dataTables = $this->db->getModelFromTable($table);
            else
                $dataTables = $this->db->getSimpleModelFromTable($table);

        }
        $this->addReturnData([$table=>$dataTables]);
    }

    function init() {

        // db proxy inclides: db_server, db_socket, db_user,db_password and db_name;
        $this->core->config->processConfigData($this->dbproxy);
        $this->core->config->set("dbServer", $this->core->config->get('db_server'));
        $this->core->config->set("dbSocket", $this->core->config->get('db_socket'));
        $this->core->config->set("dbUser", $this->core->config->get('db_user'));
        $this->core->config->set("dbPassword", $this->core->config->get('db_password'));
        $this->core->config->set("dbName", $this->core->config->get('db_name'));

        if(null === $this->db)
            $this->db = $this->core->loadClass('CloudSQL');

        if(!$this->db->connect()) {
            $this->setErrorFromCodelib('db-error-connection',$this->db->getError());
        }
        return !$this->error;
    }
    function end() {
        if(is_object($this->db))
            $this->db->close();
    }

    public function checkSecurity() {
        if($this->existBasicAuth()) {
            // if checkBasicAuthWithConfig has been passed we can get the array stored in setConf('CLOUDFRAMEWORK-ID-'.$id);
            if ($this->checkBasicAuthSecurity()) {
                $this->info = $this->getCloudFrameWorkSecurityInfo();
                $this->updateReturnResponse(['security'=>'Basic Authentication']);

            }
        } elseif ($this->checkCloudFrameWorkSecurity(600)) {
            // if checkCloudFrameWorkSecurity has been passed we can get the array stored in setConf('CLOUDFRAMEWORK-ID-'.$id);
            $this->info = $this->getCloudFrameWorkSecurityInfo();
            $this->updateReturnResponse(['security'=>'X-CLOUDFRAMEWORK-SECURITY']);
        } else {
            $this->setErrorFromCodelib('security-error','Requires authorization');
        }

        if(!is_array($this->info['dbproxy'])) $this->setErrorFromCodelib('dbproxy-notfound');
        return !$this->error;
    }


}