<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('GET');
        /** @var Google $google */
        $google = $this->core->loadClass('Google');
        if($google->error) {
            $this->setErrorFromCodelib('system-error');
            $this->core->errors->add($google->errorMsg);

        }
        $this->addReturnData(['url_generate_token'=>$google->client->createAuthUrl()]);
    }


}