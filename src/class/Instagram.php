<?php
// Instagram Class v1
if (!defined ("_Instagram_CLASS_") ) {
    define("_Instagram_CLASS_", TRUE);

    class Instagram
    {
        private $core;
        var $config;
        var $access_token=null;
        var $user_id=null;
        var $error = false;
        var $errorMsg = [];
        function __construct (Core &$core,$config)
        {
            $this->core = $core;
            $this->config = $config;
            if(isset($config['access_token'])) $this->access_token = $config['access_token'];
            if(isset($config['user_id'])) $this->user_id = $config['user_id'];
        }

        public function getUserRecent($user_id='', $maxId = '', $minId = '', $maxTimestamp = '', $minTimestamp = '') {
            if(!strlen($user_id)) $user_id=$this->user_id;
            if(strlen($user_id) && strlen($this->access_token)) {
                $s = 'https://api.instagram.com/v1/users/%s/media/recent/';
                $params['access_token'] = $this->access_token;
                $params['max_id'] = $maxId;
                $params['min_id'] = $minId;
                $params['max_timestamp'] = $maxTimestamp;
                $params['min_timestamp'] = $minTimestamp;

                $url = sprintf($s,$user_id);
                $ret = $this->core->request->get($url,$params);
                if(strlen($ret) && !$this->core->request->error) {
                    return json_decode($ret,true);
                } else {
                    $this->addError($this->core->request->errorMsg);
                    return null;
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