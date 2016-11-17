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
            } else {
                require_once $this->core->system->root_path . '/vendor/autoload.php';
                $this->client = new Google_Client();
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }
    }
}
