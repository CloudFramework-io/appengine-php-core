<?php
class API extends RESTful
{

    public function __codes() {

        $this->addCodeLib('slack-params-error', 'Missing slack params',400);

    }

    function main()
    {

        if(!$this->useFunction('ENDPOINT_'.$this->params[0])) {
            // ENDPOINT_'.$this->params[1] DOES NOT EXIST IN THIS CODE OR PARENT's CODE
            return($this->setErrorFromCodelib('params-error',"/{ $this->platform}/{$this->params[1]} is not implemented"));
        } else {
            // DO SOMETHING IF YOU WANT TO CREATE ANY WORKFLOW WHEN ERROR
            if($this->error) {
                // save in log register data if there is an error signup
            }
        }


    }

    /**
     * End points without params, shows the different calls allowed.
     */
    function ENDPOINT_() {
        if(!$this->checkMethod('GET')) return;

        $_endpoints = [
            '[POST] _slack/messages'=>'Send a message.'
        ];

        $this->addReturnData($_endpoints);

    }

    /**
     * End points without params
     */
    function ENDPOINT_messages() {
        if(!$this->checkMethod('POST')) return;
        if(!$this->checkMandatoryFormParam('message')) return($this->setErrorFromCodelib('params-error'));
        if(!$this->checkMandatoryFormParam('slack_webhook')) return($this->setErrorFromCodelib('params-error'));

        $data = ['text'=>$this->formParams['message']];
        $ret = $this->core->request->post_json_decode($this->formParams['slack_webhook'],$data,['Content-type'=> 'application/json'],true);
        if($this->core->request->error) {
            if($ret) return($this->addReturnData([$this->formParams['slack_webhook'],$data,$ret]));
            else return($this->setErrorFromCodelib('slack-error',$this->core->request->errorMsg));
        }

        $this->addReturnData($ret);

    }
}