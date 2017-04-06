<?php
// https://developers.google.com/drive/v3/web/quickstart/php
// php composer.phar require google/apiclient:^2.0

// Instagram Class v1
if (!defined ("_Google_CLASS_") ) {
    define("_Google_CLASS_", TRUE);

    class PusherClient
    {
        private $_token;
        private $core;
        var $error = false;
        var $errorMsg = [];
        /** @var Pusher $object */
        var $object = null;
        /**
         * Constructor
         *
         * @param string $baseURI
         * @param string $token
         */
        function __construct(Core &$core, $config = [])
        {
            $this->core = $core;
            $app_id = ($config[0])?:'';
            if (!$app_id) return($this->addError('Missing $app_id in config param. use: [$app_id,$key,$secret]'));

            $key = ($config[1])?:'';
            if (!$app_id) return($this->addError('Missing $key in config param. use: [$app_id,$key,$secret]'));

            $secret = ($config[2])?:'';
            if (!$app_id) return($this->addError('Missing $secret in config param. use: [$app_id,$key,$secret]'));

            require_once $this->core->system->root_path . '/vendor/autoload.php';

            $options = array(
                'cluster' => 'eu',
                'encrypted' => true
            );

            $this->object = new Pusher(
                $key,
                $secret,
                $app_id,
                $options
            );


        }

        /**
         * Allow authorize a token into a channel
         * https://pusher.com/docs/authenticating_users
         * @param $channel_name
         * @param $socket_id
         * @return string|void
         */
        public function socket_auth($channel_name, $socket_id) {
            try {
                $ret = $this->object->socket_auth($channel_name,$socket_id);
                return $ret;
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
        }

        public function trigger ($channel_name,$event_name,$message) {
            try {
                $data['message'] = $this->formParams['message'];
                $ret = $this->object->trigger($channel_name,$event_name,['message'=>$message]);
                return $ret;
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
        }

        private function addError($msg) {
            $this->error = true;
            $this->errorMsg[] = $msg;
        }
    }
}