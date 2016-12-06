<?php
class API extends RESTful
{
    /** @var  Google */
    var $google;

    function main()
    {
        $this->checkMethod('GET');
        /** @var Google $this->google */
        $this->google = $this->core->loadClass('Google');
        if($this->google->error) {
            $this->setErrorFromCodelib('system-error');
            $this->core->errors->add($this->google->errorMsg);
        } else {
            // STEP 0.. Generate a token
            if($this->params[0]=='generate_token') {
                $this->google->client->setAccessType('offline');
                $this->google->client->setScopes(Google_Service_Drive::DRIVE);
                $this->addReturnData(['url_generate_token' => $this->google->client->createAuthUrl()]);
            }
            // STEP 1.. Generate an access_token and refresh_token
            elseif($this->params[0]=='generate_access_token') {
              if(!$this->checkMandatoryFormParam('token')) return;
                $this->addReturnData($this->google->client->fetchAccessTokenWithAuthCode($this->formParams['token']));
            }
            // STEP 2
            elseif($this->params[0]=='test') {
                return($this->test());
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
                    $this->google->client->setAccessToken($access_token);

                    // Refresh Expried tokens
                    if ($this->google->client->isAccessTokenExpired()) {
                        $this->google->client->fetchAccessTokenWithRefreshToken($this->google->client->getRefreshToken());
                        $this->core->cache->set('_cloudframework_access_token_array',$this->google->client->getAccessToken());
                    }

                    $service = new Google_Service_Drive($this->google->client);

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
                $this->addReturnData(['valid-end-points'=>[
                    '/generate_token'
                    ,'/generate_access_token?token={token}'
                    ,'/test'
                ]]);
            }
        }
    }

    function test() {
        // Message
        $msg[0] = 'You need to create a config var called Google_Client and put inside Google Credentials JSON';
        $msg[1] = 'Download your Google JSON credentials from: https://console.cloud.google.com/apis/credentials (section: OAuth 2.0 client IDs)';
        $msg[2] = 'Generate an access token using the end-points `/generate_token and `/generate_access_token?token={token}` and include inside "Google_Client":{"access_token":{  (your credentials) }}';

        $client_secret = $this->core->config->get('Google_Client');
        $data['config.json']=[ "Google_Client"=>(is_array($client_secret))?'found':$msg[0]];
        $data['config.json']['Google_Client:installed']=(is_array($client_secret['installed']))?'found':$msg[1];
        $data['config.json']['Google_Client:access_token']=(is_array($client_secret['access_token']))?'found':$msg[2];

        if(is_array($client_secret['access_token'])) {

            // Refresh token
            $access_token = $this->core->cache->get('_cloudframework_access_token_array');
            if(!$access_token) $access_token = $client_secret['access_token'];
            $this->google->client->setAccessToken($access_token);

            if($this->google->client->isAccessTokenExpired()) {
                $data['access_token'] = 'Expired';
                $data['refreh_token'] = ($client_secret['access_token']['refresh_token'])?'*******************':'missing';
                if($data['refreh_token']!='missing') {
                    $this->google->client->fetchAccessTokenWithRefreshToken($client_secret['access_token']['refresh_token']);
                    $data['new_access_token'] = $this->google->client->getAccessToken();
                    $this->core->cache->set('_cloudframework_access_token_array',$data['new_access_token']);
                    $data['access_token'] = ($data['new_access_token'])?'OK':'ERROR';
                }
            } else {
                $data['access_token'] = 'OK';
            }

            // Testing Google Drive
            if( $data['access_token']=='OK') {
                $data['Google_Service_Drive']=$this->testDrive();
            }


        }
        $this->addReturnData($data);
    }

    function testDrive() {
        $service = new Google_Service_Drive($this->google->client);

        // About the user
        try {
            // https://developers.google.com/drive/v3/reference/about#methods
            /** @var Google_Service_Drive_About $about */
            $about = ($service->about->get(['fields'=>'user,storageQuota']));
            $ret['about'] = $about->getUser();
            $ret['quota'] = $about->getStorageQuota();

        } catch (Exception $e) {
            return $this->setErrorFromCodelib('system-error',  'Excepción capturada: '.  $e->getMessage());
        };

        // Creating a FOLDER
        try {
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => '_GOOGLE_TEST',
                'mimeType' => 'application/vnd.google-apps.folder'));

            //Create parent folder
            $file = $service->files->create($fileMetadata, array(
                'fields' => implode(',',$this->getModelFields('files'))));

            $ret['test folder: _GOOGLE_TEST'] = $file;
        } catch (Exception $e) {
            return $this->setErrorFromCodelib('system-error',  'Excepción capturada: '.  $e->getMessage());
        };

        // DELETING A FOLDER
        try {
            $file = $service->files->delete($ret['test folder: _GOOGLE_TEST']['id']);
            $ret['delete folder: _GOOGLE_TEST'] = 'OK';
        } catch (Exception $e) {
            return $this->setErrorFromCodelib('system-error',  'Excepción capturada: '.  $e->getMessage());
        };

        // Show files
        // Print the names and IDs for up to 10 files.
        // Search https://developers.google.com/drive/v3/web/search-parameters
        try {
            $optParams = array(
                'pageSize' => 10,
                'orderBy'=> 'modifiedTime desc',
                'fields' => 'nextPageToken, files('.implode(',',$this->getModelFields('files')).')',
                'spaces' => 'drive',
                'q' =>"mimeType = 'application/vnd.google-apps.folder'"
            );
            $results = $service->files->listFiles($optParams);
            $ret['files'] = $results->getFiles();
        }catch (Exception $e) {
            return $this->setErrorFromCodelib('system-error',  'Excepción capturada: '.  $e->getMessage());
        };
        return  $ret;
    }

    function getModelFields($type) {
        if($type=='files') {
            $files = '{
            "appProperties": null,
            "createdTime": null,
            "description": null,
            "explicitlyTrashed": null,
            "fileExtension": null,
            "folderColorRgb": null,
            "fullFileExtension": null,
            "headRevisionId": null,
            "iconLink": null,
            "id": "0B39ymL7MeFYGaGhYcVJfVHladjA",
            "isAppAuthorized": null,
            "kind": "drive#file",
            "md5Checksum": null,
            "mimeType": "application/vnd.google-apps.folder",
            "modifiedByMeTime": null,
            "modifiedTime": null,
            "name": "_GOOGLE_TEST",
            "originalFilename": null,
            "ownedByMe": null,
            "parents": [
            "0AH9ymL7MeFYGUk9PVA"
            ],
            "properties": null,
            "quotaBytesUsed": null,
            "shared": null,
            "sharedWithMeTime": null,
            "size": null,
            "spaces": null,
            "starred": null,
            "thumbnailLink": null,
            "trashed": null,
            "version": null,
            "viewedByMe": null,
            "viewedByMeTime": null,
            "viewersCanCopyContent": null,
            "webContentLink": null,
            "webViewLink": null,
            "writersCanShare": null
            }';
             return array_keys(json_decode($files,true));
        }
    }

}