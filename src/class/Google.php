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

        function __construct(Core &$core)
        {
            $this->core = $core;
            if(!is_dir($this->core->system->root_path.'/vendor/google')) {
                $this->addError('Missing Google Client libreries. Execute from your document root: php composer.phar require google/apiclient:^2.0');
                $this->addError('You can find composer.phar from: https://getcomposer.org/composer.phar');
                $this->addError('You can reduce extra files and docs with: find vendor -type d -name tests  -exec rm -rf {} \;');
                $this->addError('You can reduce extra files and docs with: find vendor -type d -name examples  -exec rm -rf {} \;');
                $this->addError('You can reduce extra files and docs with: find vendor -type d -name doc  -exec rm -rf {} \;');
                $this->addError('You can reduce extra files and docs with: find vendor -type f -name "*.md"  -exec rm -rf {} \;');
            } else {
                $client_secret = $this->core->config->get('Google_Client');

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
    }
}
