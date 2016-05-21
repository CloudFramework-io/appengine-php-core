<?php
class API extends RESTful
{
    function main() {
        $this->checkMethod('GET');
        if(!$this->error) {
            $this->setReturnData(array('_version'=>$this->core->_version));
            $this->addReturnData(array('time_zone'=>$this->core->system->time_zone));
            $this->addReturnData(array('output_format'=>$this->core->system->format));

            if(isset($_GET['fingerprint']))
                $this->addReturnData(array('fingerprint'=>$this->core->system->getRequestFingerPrint()));

            if(isset($_GET['headers']))
                $this->addReturnData(array('headers'=>$this->getHeaders()));
            if(isset($_GET['vars']))
                $this->addReturnData(array('vars'=>$_SERVER));

        }
    }
}
