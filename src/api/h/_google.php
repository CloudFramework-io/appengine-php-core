<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('GET');
        $google = $this->core->loadClass('Google');
        if($google->error) {
            $this->setErrorFromCodelib('system-error');
            $this->core->errors->add($google->errorMsg);
        }
    }
}