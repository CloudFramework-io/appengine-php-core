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
        } else {
            // STEP 0.. Generate a token
            if($this->params[0]=='generate_token') {
                $google->client->setAccessType('offline');
                $google->client->setScopes(Google_Service_Drive::DRIVE);
                $this->addReturnData(['url_generate_token' => $google->client->createAuthUrl()]);
            }
            // STEP 1.. Generate an access_token and refresh_token
            elseif($this->params[0]=='generate_access_token') {
              if(!$this->checkMandatoryFormParam('token')) return;
                $this->addReturnData($google->client->fetchAccessTokenWithAuthCode($this->formParams['token']));
            }
            // STEP 2
            elseif($this->params[0]=='test_access_token') {
                $client_secret = $this->core->config->get('Google_Client');
                if(!is_array($client_secret) || !isset($client_secret['access_token'])) {
                    return($this->setErrorFromCodelib('system-error','Missing access_token array inside Google_Client config var. Please use: /generate_token and /generate_access_token'));
                }
                // STEP 3
                else {
                    $access_token = $this->core->cache->get('_cloudframework_access_token_array');
                    if(!$access_token) {
                        $access_token = $client_secret['access_token'];
                        $this->core->cache->set('_cloudframework_access_token_array',$access_token);
                    }
                    $google->client->setAccessToken($access_token);

                    // Refresh Expried tokens
                    if ($google->client->isAccessTokenExpired()) {
                        $google->client->fetchAccessTokenWithRefreshToken($google->client->getRefreshToken());
                        $this->core->cache->set('_cloudframework_access_token_array',$google->client->getAccessToken());
                    }

                    $service = new Google_Service_Drive($google->client);

                    // Print the names and IDs for up to 10 files.
                    $optParams = array(
                        'pageSize' => 10,
                        'fields' => 'nextPageToken, files(id, name)'
                    );
                    $results = $service->files->listFiles($optParams);

                    if (count($results->getFiles()) == 0) {
                        $this->addReturnData( "No files found");
                    } else {
                        $this->addReturnData( $results->getFiles());

                    }
                }
            } else {
                $this->setErrorFromCodelib('params-error');
            }
        }
    }


}