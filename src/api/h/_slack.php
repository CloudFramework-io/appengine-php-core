<?php
/*
 * Example about how to implment a Slack API to interact with users.
 * You have to define in https://api.slack.com/apps/XXXXXX/slash-commands a command and aim that command to this endpoint
 */
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
     * Send a 'message' to Slack channel defoned by 'slack_webhook' formParams
     * Optionally it can receive 'delay' formParams with a value between 1 to 20 to delay the message some seconds.
     */
    function ENDPOINT_messages() {
        if(!$this->checkMethod('POST')) return;
        if(!$this->checkMandatoryFormParam('message')) return($this->setErrorFromCodelib('params-error'));

        if(isset($this->formParams['response'])) {
            if(!is_array($this->formParams['response']) || !isset($this->formParams['response']['response_url'])) return($this->setErrorFromCodelib('params-error','wrong structure in "response" form params'));
            $url = $this->formParams['response']['response_url'];
            $data = $this->formParams['response'];
            $data['text'] = $this->formParams['message'];
        } else{
            if(!$this->checkMandatoryFormParam('slack_webhook')) return($this->setErrorFromCodelib('params-error'));
            $data = ['text'=>$this->formParams['message']];
            $url = $this->formParams['slack_webhook'];
        }


        //allow to delay a message max 20 seconds
        if(isset($this->formParams['delay']) && intval($this->formParams['delay']) && intval($this->formParams['delay']) < 20) sleep(intval($this->formParams['delay']));

        $ret = $this->core->request->post_json_decode($url,$data,['Content-type'=> 'application/json'],true);
        if($this->core->request->error) {
            if($ret) return($this->addReturnData([$url,$data,$ret]));
            else return($this->setErrorFromCodelib('slack-error',$this->core->request->errorMsg));
        }

        $this->addReturnData([$ret,$url,$data]);

    }

    /**
     * Basic commands to receive in slack. /cfbot hello
     */
    function ENDPOINT_commands() {

        if(!$this->checkMandatoryFormParams(['token','team_id','team_domain','channel_id','channel_name','user_id','user_name','command','response_url','trigger_id']))return;
        switch ($this->formParams['text']) {
            case "hello":
                $this->setReturnResponse(['text'=>'Hello body','attachments'=>[['text'=>'How are you?']]]);
                break;
            case "in_channel":
                $this->setReturnResponse(['response_type'=> 'in_channel','text'=>'Hello body','attachments'=>[['text'=>'How are you?']]]);
                break;
            case "ephemeral":
                $this->setReturnResponse(['response_type'=>'ephemeral','text'=>'This live is ephemeral']);
                break;
            case "delay":
                $id =uniqid('slack');
                $this->setReturnResponse(['text'=>'the response '.$id.' has been delayed']);
                $url = preg_replace('/slack\/commands.*/','queue/slack/messages',$this->core->system->url['host_url']);
                $data = ['slack_webhook'=>$this->formParams['response_url'],'message'=>'The delayed message '.$id.' has arrived','delay'=>5];
                $this->core->request->post_json_decode($url,$data);
                break;
            default:
                $this->setReturnResponse(['text'=>'Sorry. I am still learning how to interact with you.']);
                break;
        }
        $this->core->logs->add($this->formParams);
    }
}