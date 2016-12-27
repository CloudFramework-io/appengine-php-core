<?php
// https://developers.google.com/drive/v3/web/quickstart/php
// php composer.phar require google/apiclient:^2.0

// Instagram Class v1
if (!defined ("_Facebook_CLASS_") ) {
    define("_Facebook_CLASS_", TRUE);

    class Facebook
    {
        private $core;
        var $error = false;
        var $errorMsg = [];
        var $client;
        var $client_secret=[];
        var $scope;
        var $access_token=null;

        function __construct(Core &$core)
        {
            $this->core = $core;
            if(!is_dir($this->core->system->root_path.'/vendor/google')) {
                $this->addError('Missing Google Client libreries. Execute from your document root: php composer.phar require facebook/graph-sdk:~5.0');
                $this->addError('You can find composer.phar from: curl https://getcomposer.org/composer.phar');
                return;
            }

            // Read id and secret based on installed credentials
            $this->client_secret = $this->core->config->get('Facebook_Client');
            if(!is_array($this->client_secret))
                return($this->addError('Missing Facebook_Client config var with the credentials from Facebook.'));

            if(!isset($this->client_secret['app_id']))
                return($this->addError('Missing app_id config var inside Facebook_Client.'));


            if(!isset($this->client_secret['app_secret']))
                return($this->addError('Missing app_secret config var inside Facebook_Client.'));


            require_once $this->core->system->root_path . '/vendor/autoload.php';

            $this->client = new Facebook\Facebook([
                'app_id' => $this->client_secret['app_id'],
                'app_secret' => $this->client_secret['app_secret'],
                'default_graph_version' => 'v2.8',
            ]);


        }

        /**
         * Returns info about an user
         * About the fields and the end-point: https://developers.facebook.com/docs/graph-api/reference/user/
         * @param $id                   id of the user
         * @param string $access_token  optional access token
         * @param string $fields        optional fields. By default: id,name,first_name,middle_name,last_name,email,cover,locale,website,link,picture,is_verified
         * @return array|void
         */
        public function getProfile($id, $access_token=null, $fields='id,name,first_name,middle_name,last_name,email,cover,locale,website,link,picture,is_verified') {
            
            if($this->error) return;
            if(!$access_token) $access_token = $this->access_token;
            if(!$access_token) return($this->addError('getProfile($id,$access_token=null). Missing access token. Use setAccessToken method or pass the variable.'));


            try {
                $response = $this->client->get("/".$id."?fields={$fields}", $access_token);

                // Let's extract the total friends
                $friends = $this->client->get("/{$id}/friends?limit=1", $access_token);

            } catch(Exception $e) {
                return($this->addError('Error getting user profile: ' . $e->getMessage()));
            }
            
            /** @var  $graphUser */
            $graphUser = $response->getGraphUser();
            return(array_merge($graphUser->asArray(),['total_friends'=>$friends->getDecodedBody()['summary']['total_count']]));
        }

        /**
         * Returns the pages that the user associated to the access token can admin
         * @param null $access_token
         * @return array|void
         */
        public function getPages($access_token=null) {

            if($this->error) return;
            if(!$access_token) $access_token = $this->access_token;
            if(!$access_token) return($this->addError('getPages($id,$access_token=null). Missing access token. Use setAccessToken method or pass the variable.'));


            try {
                $response = $this->client->get("/me/accounts", $access_token);
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                return($this->addError('Error getting user profile: ' . $e->getMessage()));
            }

            $pages = [];
            $pagesEdge = $response->getGraphEdge();
            foreach ($pagesEdge as $page) {
                $pages[] = $page->asArray();
            }

            return $pages;
        }

        /**
         * Returns the pages that the user associated to the access token can admin
         * @param string $access_token    optional access token
         * @param string $fields          optional fields to show.. See https://developers.facebook.com/docs/graph-api/reference/page/
         * @return array|void
         */
        public function getPage($id,$access_token=null,$fields='access_token,category,name,id,link,fan_count,is_verified,engagement,emails,general_info') {

            if($this->error) return;
            if(!$access_token) $access_token = $this->access_token;
            if(!$access_token) return($this->addError('getPages($id,$access_token=null). Missing access token. Use setAccessToken method or pass the variable.'));


            try {
                $response = $this->client->get("/".$id."?fields={$fields}", $access_token);
                $node = $response->getGraphNode()->asArray();
                return $node;
                $response = $this->client->get("/".$id."/tabs", $node["access_token"]);
                $tabsEdge = $response->getGraphEdge();

                foreach ($tabsEdge as $tab) {
                    $node['tabs'][] = $tab->asArray();
                }
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                return($this->addError('Error getting page: ' . $e->getMessage()));
            }



            return $node;
        }

        /**
         * Service that creates a tab in a page
         * @param $id       page id
         * @param $parameters
         *      "app_id"               =>      ID of the app that contains a Page Tab platform (required)
         *      "custom_name"          =>      Custom name for the tab (required)
         *      "custom_image_url"     =>      url of the image file (optional). You can upload a JPG, GIF or PNG file.
         *                                     The size of the image must be 111 x 74 pixels. File size limit 1 MB.
         *      "position"             =>      position among the other tabs (optional)
         * @param $access_token                optional access token if it is not set
         * @return array
         */
        public function createPageTab($id,$parameters,$access_token=null) {

            if($this->error) return;
            if(!$access_token) $access_token = $this->access_token;
            if(!$access_token) return($this->addError('getPages($id,$access_token=null). Missing access token. Use setAccessToken method or pass the variable.'));

            try {
                $response = $this->client->get("/".$id."?fields=access_token,category,name,id", $access_token);
                $node = $response->getGraphNode()->asArray();

                // Create tab
                /** @var \Facebook\FacebookResponse $ret */
                $response = $this->client->post("/".$id."/tabs", $parameters, $node["access_token"]);
                $tab = $response->getGraphNode()->asArray();
                if(!$tab['success']) return($this->addError('Error creating tab. Missing success result '));

                $response = $this->client->get("/".$id."/tabs", $node["access_token"]);
                $tabsEdge = $response->getGraphEdge();

                foreach ($tabsEdge as $tab) if(strpos($tab->asArray()['id'],'app_'.$this->client_secret['app_id'])) {
                    $node['tab'] = $tab->asArray();
                }
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                return($this->addError('Error creating tab: ' . $e->getMessage()));
            }



            return $node;
        }

        /**
         * Service that delete a tab in a page
         * @param $id                   page id
         * @param $tabId                tab id
         * @param $access_token         optional access token if it is not set
         * @return array|null                Return the tabs in the page or null if error
         */
        public function deletePageTab($id,$tabId,$access_token=null) {

            if($this->error) return;
            if(!$access_token) $access_token = $this->access_token;
            if(!$access_token) return($this->addError('getPages($id,$access_token=null). Missing access token. Use setAccessToken method or pass the variable.'));

            try {
                $response = $this->client->get("/".$id."?fields=access_token,category,name,id", $access_token);
                $node = $response->getGraphNode()->asArray();

                // Create tab
                /** @var \Facebook\FacebookResponse $ret */
                $response = $this->client->delete("/".$id."/tabs", ['tab'=>$tabId], $node["access_token"]);
                $tab = $response->getGraphNode()->asArray();
                if(!$tab['success']) return($this->addError('Error creating tab. Missing success result '));

                $response = $this->client->get("/".$id."/tabs", $node["access_token"]);
                $tabsEdge = $response->getGraphEdge();

                foreach ($tabsEdge as $tab) {
                    $node['tabs'][] = $tab->asArray();
                }
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                return($this->addError('Error creating tab: ' . $e->getMessage()));
            }



            return $node;
        }

        /**
         * Set an access token for all calls avoiding to send it in every call
         * @param $token
         */
        public function setAccessToken($token) {
            if(!token) return($this->addError('setAccessToken($token). Empty $token'));
            if($this->error) return;

            $this->setAccessToken($token);
        }

        /**
         * Analyze $signed_request
         * https://developers.facebook.com/docs/pages/tabs
         * https://developers.facebook.com/docs/reference/login/signed-request
         *
         * The method decodeSignedRequest in case of success will return an array with user values:
         * https://developers.facebook.com/docs/reference/login/signed-request
         * @param $signed_request
         * @return array|null|void
         */
        public function decodeSignedRequest($signed_request) {

            try {
                $fbApp = new Facebook\FacebookApp($this->client_secret['app_id'], $this->client_secret['app_secret']);
                $signed_request = new \Facebook\SignedRequest($fbApp, $signed_request);
                return($signed_request->getPayload());
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
                $this->addError(['signature'=>$signed_request]);
                return($this->addError('Error creating tab: ' . $e->getMessage()));
            }


            // PHP native decoding
            // https://developers.facebook.com/docs/games/gamesonfacebook/login#parsingsr
            /*
            list($encoded_sig, $payload) = explode('.', $signed_request, 2);

            $secret = $this->client_secret['app_secret']; // Use your app secret here

            // decode the data
            $sig = base64_url_decode($encoded_sig);
            $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

            // confirm the signature
            $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
            if ($sig !== $expected_sig) {
                error_log('Bad Signed JSON signature!');
                return null;
            }

            return $data;
            */

        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

    }
}
