<?php
class API extends RESTful
{
    function main()
    {
// CORS Control from a potential Cross-Reference.
        $db_keys = [$this->formParams['test_assign_web_key'], $this->formParams['test_assign_web_referers_allowed']];
        if ($this->core->security->checkWebKey($db_keys)) {
            $this->sendCorsHeaders('GET');
        }

        $this->checkMethod('GET');
        $data = ['vars.for.testing' => [
            'test_assign_basic_user' => 'User to send in a basic Auth',
            'test_assign_basic_password' => 'password to send in a basic Auth. Can be empty',
            'test_assign_web_client_key' => 'api_key to require a X-CLOUDFRAMEWORK-API-KEY header',
            'test_assign_client_referrers_allowed' => 'which domains will be allowed: *,localhost*, etc..',
            'test_assign_server_key_id' => 'key_id to require a X-CLOUDFRAMEWORK-SECURITY header'

        ]];
        if ($this->params[0] == 'test') {

            if (strlen($this->formParams['test_assign_basic_user'])) {
                $data['Basic.Auth'] = ['method' => '$passed =  $this->core->security->checkBasicAuth(\'' . $this->formParams['test_assign_basic_user'] . '\',\'' . $this->formParams['test_assign_basic_password'] . '\')'];
                $data['Basic.Auth']['notes'] = 'you have also: (bool)$this->core->security->existBasicAuth() and (array)$this->core->security->getBasicAuth()';
                $data['Basic.Auth']['passed'] = $this->core->security->checkBasicAuth($this->formParams['test_assign_basic_user'], $this->formParams['test_assign_basic_password']);
                if (!$data['Basic.Auth']['passed']) $data['Basic.Auth']['message'] = "Error. Send Basic Auth with the following info: user={$this->formParams['test_assign_basic_user']} and password={$this->formParams['test_assign_basic_password']}";
            }

            if (strlen($this->formParams['test_assign_basic_user'])) {
                $data['Basic.AuthWithConfig'] = ['method' => '$passed = (false !==  $this->core->security->checkBasicAuthWithConfig())'];
                $data['Basic.AuthWithConfig']['passed'] = false !== $this->core->security->checkBasicAuthWithConfig($this->formParams['test_assign_basic_user'], $this->formParams['test_assign_basic_password']);
                if (!$data['Basic.AuthWithConfig']['passed']) $data['Basic.AuthWithConfig']['message'] = 'Error. It requires "authorization" config params: "athorizations": { "user": {"password":"a password","ips":"valid ips or *","extravars":"extravalues"},... }';
            }

            if (strlen($this->formParams['test_assign_web_key'])) {
                $data['Web.Client.Auth'] = ['method' => '$passed = $this->core->security->checkWebKey([\'' . $this->formParams["test_assign_web_key"] . '\',\'' . $this->formParams["test_assign_web_referers_allowed"] . '\')]'];
                $data['Web.Client.Auth']['notes'] = 'you have also: (bool)$this->core->security->existWebKey() and (array)$this->core->security->getWebKey()';
                $data['Web.Client.Auth']['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
                $data['Web.Client.Auth']['passed'] = $this->core->security->checkWebKey($db_keys);
                if (!$data['Web.Client.Auth']['passed']) {
                    if (!strlen($_SERVER['HTTP_REFERER']))
                        $data['Web.Client.Auth']['message'][] = "Missing HTTP_REFERER: only *.rule referers_allowed";
                    $data['Web.Client.Auth']['message'][] = "Error. Send X-CLOUDFRAMEWORK-WEB-KEY header or _GET['web_key']  with the following info: "
                        . $this->formParams['test_assign_web_key'];
                }

            }

            if (strlen($this->formParams['test_assign_server_key'])) {
                $data['Web.Server.Auth'] = ['method' => '$passed = $this->core->security->checkServerKey([\'' . $this->formParams["test_assign_server_key"] . '\',\'' . $this->formParams["test_assign_ips_allowed"] . '\')]'];
                $data['Web.Server.Auth']['notes'] = 'you have also: (bool)$this->core->security->existServerKey() and (array)$this->core->security->getServerKey()';
                $data['Web.Server.Auth']['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
                $data['Web.Server.Auth']['passed'] = $this->core->security->checkServerKey([$this->formParams["test_assign_server_key"], $this->formParams["test_assign_ips_allowed"]]);
                if (!$data['Web.Server.Auth']['passed']) {
                    if(strlen($_SERVER['REMOTE_ADDR']) < 10)
                        $data['Web.Server.Auth']['message'][] = "REMOTE_ADD short. Maybe only *.rule ips_allowed will work";
                    $data['Web.Server.Auth']['message'][] = "Error. Send X-CLOUDFRAMEWORK-SERVER-KEY header]  with the following info: "
                        . $this->formParams['test_assign_server_key'];
                }

            }


            if (strlen($this->formParams['test_assign_security_token_id'])) {
                $secret = 'WerErty';
                $data['SecurityToken.Auth'] = ['method' => '$passed = $this->core->security->checkCloudFrameWorkSecurity(3600,\'' . $this->formParams["test_assign_security_token_id"] . '\',$secret)]'];
                $data['SecurityToken.Auth']['notes'] = 'The key can be generated using: $this->core->security->generateCloudFrameWorkSecurityString($this->formParams[\'test_assign_security_token_id\'],[\'\'|{current_time}],$secret)';
                $data['SecurityToken.Auth']['passed'] = $this->core->security->checkCloudFrameWorkSecurity(3600, $this->formParams['test_assign_security_token_id'], $secret);
                if (!$data['SecurityToken.Auth']['passed'])
                    $data['SecurityToken.Auth']['message'] = "Error. Send X-CLOUDFRAMEWORK-SECURITY header with the following info: "
                        . $this->core->security->generateCloudFrameWorkSecurityString($this->formParams["test_assign_security_token_id"], '', $secret);

            }

        }
        $this->addReturnData($data);
    }
}
