<?php
require_once __DIR__.'/Google.php';
class GoogleVision extends Google
{
    function analyze($gcsurl,$options=[]) {

        if($this->error) return;

        $optParams = [];
        $this->client->setScopes([Google_Service_Vision::CLOUD_PLATFORM]);

        $service = new Google_Service_Vision($this->client);
        $body = new Google_Service_Vision_BatchAnnotateImagesRequest();

        $features = [];

        if(!$options || in_array('FACE_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('FACE_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LANDMARK_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LANDMARK_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LOGO_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LOGO_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LABEL_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LABEL_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('TEXT_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('TEXT_DETECTION');
            $feature->setMaxResults(200);
            $features[] = $feature;
        }

        if(!$options || in_array('SAFE_SEARCH_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('SAFE_SEARCH_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('IMAGE_PROPERTIES',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('IMAGE_PROPERTIES');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }


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
            return($this->addError('Excepción capturada: ',  $e->getMessage()));
        }

        /** @var Google_Service_Vision_AnnotateImageResponse $item */
        foreach ($res->getResponses() as $item) {
            if(null !== $item->getError()) {
                return($this->addError($item->getError()->message));
            }
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
                    if(is_object($color))
                        $colors[] = ['color'=>[$color->getRed(),$color->getGreen(),$color->getBlue(),$color->getAlpha()],'score'=>$dominantColor->score];
                }
                if($safe) $ret['imageProperties'][] = ['colors'=>$colors];

            }

        }

        return $ret;

    }

    function check($image='gs://cloudframework-public/api-vision/face.jpg') {

        return($this->analyze($image));

    }
}