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

            if(!is_array($this->client_secret['api_id']))
                return($this->addError('Missing app_id config var inside Facebook_Client.'));


            if(!is_array($this->client_secret['app_secret']))
                return($this->addError('Missing app_secret config var inside Facebook_Client.'));


            require_once $this->core->system->root_path . '/vendor/autoload.php';

            $this->client = new Facebook\Facebook([
                'app_id' => $this->client_secret['api_id'],
                'app_secret' => $this->client_secret['app_secret'],
                'default_graph_version' => 'v2.5',
            ]);

        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

    }
}
