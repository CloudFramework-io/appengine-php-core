<?php
// https://developers.google.com/drive/v3/web/quickstart/php
// php composer.phar require google/apiclient:^2.0

// Instagram Class v1
if (!defined ("_Google_CLASS_") ) {
    define("_Google_CLASS_", TRUE);

    class Google
    {
        private $core;
        var $error = false;
        var $errorMsg = [];
        var $client;
        var $client_id;
        var $client_secret;
        var $scope;

        function __construct(Core &$core)
        {
            $this->core = $core;
            if(!is_dir($this->core->system->root_path.'/vendor/google')) {
                $this->addError('Missing Google Client libreries. Execute from your document root: php composer.phar require google/apiclient:^2.0');
                $this->addError('You can find composer.phar from: curl https://getcomposer.org/composer.phar');
            } else {
                $client_secret = $this->core->config->get('Google_Client');

                // Read id and secret based on installed credentials
                $client_secret = $this->core->config->get('Google_Client');
                if(isset($client_secret['installed'])) {
                    $this->client_id = $client_secret['installed']['client_id'];
                    $this->client_secret = $client_secret['installed']['client_secret'];
                }

                // Read scope
                if(isset($client_secret['scope'])) {
                    $this->scope = $client_secret['scope'];
                }

                if(!is_array($client_secret)) {
                    $this->addError('Missing Google_Client config var with the credentials from Google. Get JSON OAUTH 2.0 credentials file from: https://console.developers.google.com/apis/credentials');
                } else {
                    require_once $this->core->system->root_path . '/vendor/autoload.php';
                    $this->client = new Google_Client();
                    $this->client->setApplicationName('GoogleCloudFrameWork');
                    $this->client->setAuthConfig($client_secret);
                }
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

        function verifyToken($token) {
            $ret =$this->core->request->get_json_decode('https://www.googleapis.com/oauth2/v1/tokeninfo',['access_token'=>$token]);
            if(isset($ret['error'])) return($this->addError($ret));
            if($this->client->getClientId() != $ret['issued_to']) return($this->addError('This token has not been generated with system client_id'));
            return true;
        }
    }
}
