<?php

// Info: https://cloud.google.com/vision/reference/rest/v1

class API extends RESTful
{
    /** @var  Google */
    var $google;

    function main()
    {
        $this->checkMethod('GET');
        /** @var Google $this->google */
        $this->google = $this->core->loadClass('Google','developer');
        if($this->google->error) {
            $this->setErrorFromCodelib('system-error');
            $this->core->errors->add($this->google->errorMsg);
        } else {

            $optParams = [];
            $gcsurl = 'gs://cloudframework-public/api-vision/face.jpg';
            $this->google->client->setScopes([Google_Service_Vision::CLOUD_PLATFORM]);

            $service = new Google_Service_Vision($this->google->client);
            $body = new Google_Service_Vision_BatchAnnotateImagesRequest();

            $features = [];

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('FACE_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LANDMARK_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LOGO_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LABEL_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('TEXT_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('SAFE_SEARCH_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;

            $feature = new Google_Service_Vision_Feature();
            $feature->setType('IMAGE_PROPERTIES');
            $feature->setMaxResults(100);
            $features[] = $feature;


            $src = new Google_Service_Vision_ImageSource();
            $src->setGcsImageUri($gcsurl);
            $image = new Google_Service_Vision_Image();
            $image->setSource($src);


            $payload = new Google_Service_Vision_AnnotateImageRequest();
            $payload->setFeatures($features);
            $payload->setImage($image);

            $body->setRequests([$payload]);

            /** @var $res \Google_Service_Vision_BatchAnnotateImagesResponse */
            try {
                $res = $service->images->annotate($body, $optParams);
            } catch (Exception $e) {
                echo 'Excepción capturada: ',  $e->getMessage(), "\n";
            }

            /** @var Google_Service_Vision_AnnotateImageResponse $item */
            foreach ($res->getResponses() as $item) {

                //_printe($item->toSimpleObject());
                /** @var Google_Service_Vision_FaceAnnotation $faceAnnotation */
                foreach ($item->getFaceAnnotations() as $faceAnnotation) {
                    $ret['faceAnnotations'][] = ['confidence'=>$faceAnnotation->detectionConfidence
                        ,'joy'=>$faceAnnotation->joyLikelihood
                        ,'sorrow'=>$faceAnnotation->sorrowLikelihood
                        ,'anger'=>$faceAnnotation->angerLikelihood
                        ,'surpise'=>$faceAnnotation->surpriseLikelihood
                        ,'exposed'=>$faceAnnotation->underExposedLikelihood
                        ,'blurred'=>$faceAnnotation->blurredLikelihood
                        ,'headWear'=>$faceAnnotation->headwearLikelihood
                        ,'rollAngle'=>$faceAnnotation->rollAngle
                        ,'tiltAngle'=>$faceAnnotation->tiltAngle
                        ,'panAngle'=>$faceAnnotation->panAngle

                    ];
                }

                /** @var Google_Service_Vision_EntityAnnotation $landmarkAnnotation */
                foreach ($item->getLandmarkAnnotations() as $landmarkAnnotation) {
                    $ret['landmarkAnnotations'][] = ['description'=>$landmarkAnnotation->description,'score'=>$landmarkAnnotation->score];
                }

                /** @var Google_Service_Vision_EntityAnnotation $logoAnnotation */
                foreach ($item->getLogoAnnotations() as $logoAnnotation) {
                    $ret['logoAnnotations'][] = ['description'=>$logoAnnotation->description,'score'=>$logoAnnotation->score];
                }

                /** @var Google_Service_Vision_EntityAnnotation $labelAnnotation */
                foreach ($item->getLabelAnnotations() as $labelAnnotation) {
                    $ret['labelAnnotations'][] = ['description'=>$labelAnnotation->description,'score'=>$labelAnnotation->score];
                }

                /** @var Google_Service_Vision_EntityAnnotation $text */
                foreach ($item->getTextAnnotations() as $text) {
                    $ret['textAnnotations'][] = ['description'=>$text->description,'score'=>$text->score];
                }

                /** @var Google_Service_Vision_SafeSearchAnnotation $safe */
                $safe = $item->getSafeSearchAnnotation();
                if($safe) $ret['safeSearchAnnotation'][] = ['adult'=>$safe->getAdult(),'spoof'=>$safe->getSpoof(),'medical'=>$safe->getMedical(),'violence'=>$safe->getViolence()];

                /** @var Google_Service_Vision_ImageProperties $image */
                $image = $item->getImagePropertiesAnnotation();
                if($image) {
                    /** @var  Google_Service_Vision_ColorInfo $dominantColor */
                    foreach ($image->getDominantColors() as $dominantColor) {
                        /** @var  Google_Service_Vision_Color $color */
                        $color = $dominantColor->getColor();
                        $colors[] = ['color'=>[$color->getRed(),$color->getGreen(),$color->getBlue(),$color->getAlpha()],'score'=>$dominantColor->score];
                    }
                    if($safe) $ret['imageProperties'][] = ['colors'=>$colors];

                }

            }

            $this->addReturnData($ret);
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