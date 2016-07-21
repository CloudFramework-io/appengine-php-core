<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('POST');
        if(!$this->error) $this->checkMandatoryFormParam('id');
        if(!$this->error) $this->checkMandatoryFormParam('secret');
        if(!$this->error) {
            $this->addReturnData($this->core->security->generateCloudFrameWorkSecurityString($this->formParams['id'],'',$this->formParams['secret']));
        }
    }
}