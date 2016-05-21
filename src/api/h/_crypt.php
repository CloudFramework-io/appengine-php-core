<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('GET');
        $this->checkMandatoryFormParam('s');
        if(!$this->error) {
            $data = ['info'=>'the {{crypt}} output is what you store in your config files avoinding to show the real info in plain text'];
            if(!isset($this->formParams['c'])) $this->formParams['c'] = $this->core->system->crypt($this->formParams['s']);
                $data['crypt'] = ['source'=>$this->formParams['s']
                    ,'crypt'=>$this->formParams['c']
                    ,'$this->core->system->checkPassword(source,crypt)'=>$this->core->system->checkPassword($this->formParams['s'],$this->formParams['c'])];

        }
        $this->addReturnData($data);
    }
}