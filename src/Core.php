<?php
###########################################################
# Madrid  nov de 2012
# ADNBP Business & IT Perfomrnance S.L.
# http://www.adnbp.com (info@adnbp.coom)
# Last update: Apr 2015
# Project ADNBP Framework
#
#####
# Equipo de trabajo:
#   Héctor López
###########################################################

/**
 * Core module
 */
if (!defined("_ADNBP_CORE_CLASSES_"))
{
    define("_ADNBP_CORE_CLASSES_", TRUE);

    // Global functions
    function __print($args)
    {
        if(key_exists('PWD',$_SERVER)) echo "\n";
        else echo "<pre>";
        for ($i = 0, $tr = count($args); $i < $tr; $i++) {
            if ($args[$i] === "exit")
                exit;
            if(key_exists('PWD',$_SERVER)) echo "\n[$i]: ";
            else echo "\n<li>[$i]: ";

            if (is_array($args[$i]))
                echo print_r($args[$i], TRUE);
            else if (is_object($args[$i]))
                echo var_dump($args[$i]);
            else if (is_bool($args[$i]))
                echo ($args[$i]) ? 'true' : 'false';
            else if (is_null($args[$i]))
                echo 'NULL';
            else
                echo $args[$i];
            if(key_exists('PWD',$_SERVER)) echo "\n";
            else echo "</li>";
        }
        if(key_exists('PWD',$_SERVER)) echo "\n";
        else echo "</pre>";
    }
    function _print()
    {
        __print(func_get_args());
    }
    function _printe()
    {
        __print(array_merge(func_get_args(), array('exit')));
    }

    // Independend classes
    class Performance
    {
        var $data = [];
        function __construct()
        {
            // Performance Vars
            $this->data['initMicrotime'] = microtime(true);
            $this->data['lastMicrotime'] = $this->data['initMicrotime'];
            $this->data['initMemory'] = memory_get_usage() / (1024 * 1024);
            $this->data['lastMemory'] = $this->data['initMemory'];
            $this->data['lastIndex'] = 1;
            $this->data['info'][] = 'File: ' . str_replace($_SERVER['DOCUMENT_ROOT'], '', __FILE__);
            $this->data['info'][] = 'Init Memory Usage: ' . number_format(round($this->data['initMemory'], 4), 4) . 'Mb';

        }
        function add($title, $file = '', $type = 'all')
        {
            // Hidding full path (security)
            $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);


            if ($type == 'note') $line = "[$type";
            else $line = $this->data['lastIndex'] . ' [';

            if (strlen($file)) $file = " ($file)";

            $_mem = memory_get_usage() / (1024 * 1024) - $this->data['lastMemory'];
            if ($type == 'all' || $type == 'endnote' || $type == 'memory' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                $line .= number_format(round($_mem, 3), 3) . ' Mb';
                $this->data['lastMemory'] = memory_get_usage() / (1024 * 1024);
            }

            $_time = microtime(TRUE) - $this->data['lastMicrotime'];
            if ($type == 'all' || $type == 'endnote' || $type == 'time' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                $line .= (($line == '[') ? '' : ', ') . (round($_time, 3)) . ' secs';
                $this->data['lastMicrotime'] = microtime(TRUE);
            }
            $line .= '] ' . $title;
            $line = (($type != 'note') ? '[' . number_format(round(memory_get_usage() / (1024 * 1024), 3), 3) . ' Mb, '
                    . (round(microtime(TRUE) - $this->data['initMicrotime'], 3))
                    . ' secs] / ' : '') . $line . $file;
            if ($type == 'endnote') $line = "[$type] " . $line;
            $this->data['info'][] = $line;

            if ($title) {
                if(!isset($this->data['titles'][$title])) $this->data['titles'][$title] = ['mem'=>'','time'=>0,'lastIndex'=>''];
                $this->data['titles'][$title]['mem'] = $_mem;
                $this->data['titles'][$title]['time'] += $_time;
                $this->data['titles'][$title]['lastIndex'] = $this->data['lastIndex'];

            }

            if (isset($_GET['__p']) && $_GET['__p'] == $this->data['lastIndex']) {
                __sp();
                exit;
            }

            $this->data['lastIndex']++;

        }
        function getTotalTime($prec=3) {
            return (round(microtime(TRUE) - $this->data['initMicrotime'], $prec));
        }
        function getTotalMemory($prec=3) {
            return number_format(round(memory_get_usage() / (1024 * 1024), $prec), $prec);
        }
        function init($spacename, $key)
        {
            $this->data['init'][$spacename][$key]['mem'] = memory_get_usage();
            $this->data['init'][$spacename][$key]['time'] = microtime(TRUE);
            $this->data['init'][$spacename][$key]['ok'] = TRUE;
        }

        function end($spacename, $key, $ok = TRUE, $msg = FALSE)
        {
            $this->data['init'][$spacename][$key]['mem'] = round((memory_get_usage() - $this->data['init'][$spacename][$key]['mem']) / (1024 * 1024), 3) . ' Mb';
            $this->data['init'][$spacename][$key]['time'] = round(microtime(TRUE) - $this->data['init'][$spacename][$key]['time'], 3) . ' secs';
            $this->data['init'][$spacename][$key]['ok'] = $ok;
            if ($msg !== FALSE) $this->data['init'][$spacename][$key]['notes'] = $msg;
        }
    }
    class Session
    {
        var $start = false;
        var $id = '';

        function __construct()
        {
        }

        function init($id='') {
            // I will only start session if someone call me..
            $this->id = $id;  // Someone create a session based in one ID and no in a PHPSESSION


            if (strlen($this->id))
                session_id($this->id);
            session_start();
            $this->start = true;
        }

        function get($var) {
            if(!$this->start) $this->init();
            if(key_exists('CloudSessionVar_' . $var, $_SESSION)) {
                    try {
                        $ret = unserialize(gzuncompress($_SESSION['CloudSessionVar_' . $var]));
                    } catch (Exception $e) {
                        return null;
                    }
                    return $ret;
            }
            return null;
        }
        function set($var,$value) {
            if(!$this->start) $this->init();
            $_SESSION['CloudSessionVar_' . $var] = gzcompress(serialize($value));
        }
        function delete($var) {
            if(!$this->start) $this->init();
            unset($_SESSION['CloudSessionVar_' . $var]);
        }
    }
    class System
    {
        var $url,$root_path,$app_path,$app_url;
        var $config=[];
        var $ip, $user_agent,$format,$time_zone;
        function __construct($root_path='')
        {
            if(!strlen($root_path)) $root_path = (strlen($_SERVER['DOCUMENT_ROOT']))?$_SERVER['DOCUMENT_ROOT']: $_SERVER['PWD'];

            $this->url['https'] = $_SERVER['HTTPS'];
            $this->url['protocol'] = ($_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
            $this->url['host'] = $_SERVER['HTTP_HOST'];
            $this->url['url_uri'] = $_SERVER['REQUEST_URI'];

            $this->url['url'] = $_SERVER['REQUEST_URI'];
            $this->url['params'] = '';
            if(strpos($_SERVER['REQUEST_URI'],'?')!==false)
                list($this->url['url'],$this->url['params']) = explode('?', $_SERVER['REQUEST_URI'], 2);

            $this->url['host_url'] = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'].$this->url['url'];
            $this->url['host_url_uri'] = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $this->url['script_name'] = $_SERVER['SCRIPT_NAME'];
            $this->url['parts'] = explode('/', substr($this->url['url'], 1));

            // paths
            $this->root_path = $root_path;
            $this->app_path = $this->root_path;

            // Remote user:
            $this->ip = ($_SERVER['REMOTE_ADDR']=='::1')?'localhost':$_SERVER['REMOTE_ADDR'];
            $this->user_agent = $_SERVER['HTTP_USER_AGENT'];

            // About timeZone, Date & Number format
            if(isset($_SERVER['PWD']) && strlen($_SERVER['PWD'])) date_default_timezone_set('UTC'); // necessary for shell run
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d h:i:s'), date("P"), time());
            //date_default_timezone_set(($this->core->config->get('timeZone')) ? $this->core->config->get('timeZone') : 'Europe/Madrid');
            //$this->_timeZone = array(date_default_timezone_get(), date('Y-m-d h:i:s'), date("P"), time());
            $this->format['formatDate'] = "Y-m-d";
            $this->format['formatDateTime'] = "Y-m-d h:i:s";
            $this->format['formatDBDate'] = "Y-m-d";
            $this->format['formatDBDateTime'] = "Y-m-d h:i:s";
            $this->format['formatDecimalPoint'] = ",";
            $this->format['formatThousandSep'] = ".";

            // General conf
            // TODO default formats, currencies, timezones, etc..
            $this->config['setLanguageByPath']= false;

        }

        /**
         * @param $url path for destination ($dest is empty) or for source ($dest if not empty)
         * @param string $dest Optional destination. If empty, destination will be $url
         */
        function urlRedirect($url, $dest = '') {
            if (!strlen($dest)) {
                if ($url != $this->url['url']) {
                    Header("Location: $url");
                    exit;
                }
            } else if ($url == $this->url['url'] && $url != $dest) {
                if (strlen($this->url['params'])) {
                    if (strpos($dest, '?') === false)
                        $dest .= "?" . $this->url['params'];
                    else
                        $dest .= "&" . $this->url['params'];
                }
                Header("Location: $dest");
                exit;
            }
        }

        function getRequestFingerPrint($extra = '')
        {
            $ret['ip'] =  $_SERVER['REMOTE_ADDR'];
            $ret['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $ret['http_referer'] = $_SERVER['HTTP_REFERER'];
            $ret['host'] = $_SERVER['HTTP_HOST'];
            $ret['software'] = $_SERVER['SERVER_SOFTWARE'];
            if ($extra == 'geodata') {
                $ret['geoData'] = $this->core->getGeoData();
                unset($ret['geoData']['source_ip']);
                unset($ret['geoData']['credit']);
            }
            $ret['hash'] = sha1(implode(",", $ret));
            $ret['time'] = date('Ymdhis');
            $ret['uri'] = $_SERVER['REQUEST_URI'];
            return ($ret);
        }

        function crypt($input, $rounds = 7)
        {
            $salt = "";
            $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
            for ($i = 0; $i < 22; $i++) {
                $salt .= $salt_chars[array_rand($salt_chars)];
            }
            return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
        }

        // Compare Password
        function checkPassword($passw, $compare)
        {
            return (crypt($passw, $compare) == $compare);
        }

    }
    class Loggin
    {
        var $lines = 0;
        var $data = [];

        function set($data)
        {
            $this->lines = 0;
            $this->data = [];
            $this->add($data);
        }
        function add($data)
        {
            $this->data[] = $data;
            $this->lines++;
        }
        function get()
        {
            return $this->data;
        }

    }
    class Is
    {
        function development()
        {
            return (stripos($_SERVER['SERVER_SOFTWARE'], 'Development') !== false || isset($_SERVER['PWD']));
        }
        function production()
        {
            return (stripos($_SERVER['SERVER_SOFTWARE'], 'Development') === false && !isset($_SERVER['PWD']));
        }
        function dirReadble($dir)
        {
            if (strlen($dir)) return (is_dir($dir));
        }
        function terminal() {
            return isset($_SERVER['PWD']);
        }
        function dirWritable($dir)
        {
            if (strlen($dir)) {
                if(!$this->dirReadble($dir)) return false;
                try {
                    if (@mkdir($dir . '/__tmp__')) {
                        rmdir($dir . '/__tmp__');
                        return (true);
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
        }
        function validEmail($email) {
            return (filter_var($email, FILTER_VALIDATE_EMAIL));
        }
        function validURL($url) {
            return (filter_var($url, FILTER_VALIDATE_URL));
        }
    }

    class CacheFile  {

        var $dir='';

        function __construct($dir='')
        {
            if(strlen($dir)) $this->dir=$dir.'/';
        }

        function set($path,$data)
        {
            return @file_put_contents($this->dir.$path,gzcompress(serialize($data)));
        }

        function delete($path)
        {
            return @unlink($this->dir.$path);
        }

        function get($path)
        {
            $ret =  @file_get_contents($this->dir.$path);
            if(false === $ret) return null;
            else return unserialize(gzuncompress($ret));
        }
    }

    class Cache {
        var $cache = null;
        var $spacename = 'CloudFrameWork';
        var $type = 'memory';
        var $dir = '';
        var $error = false;
        var $errorMsg = [];

        function __construct($spacename='',$type='memory') {
            if(!strlen(trim($spacename)))  $spacename = (isset($_SERVER['HTTP_HOST']))?$_SERVER['HTTP_HOST']:$_SERVER['PWD'];
            $this->setSpaceName($spacename);
            if($type=='memory') $this->type = 'memory';

        }

        function activeDirPath($path) {

            if(is_dir($path) || !@mkdir($path)) {
                $this->type = 'CacheInDirectory';
                $this->dir = $path;
                $this->setSpaceName(basename($path));
                return true;
            } else {
                $this->addError($path.' does not exist and can not be created');
                return false;
            }

        }

        function init() {
            if($this->type=='memory') {
                if(class_exists('MemCache'))
                    $this->cache = new Memcache;
            } else
                $this->cache = new CacheFile($this->dir);
        }

        function setSpaceName($name) {
            if(strlen($name)) $name = '_'.trim($name);
            $this->spacename = preg_replace('/[^A-z_-]/','','CloudFrameWork_'.$this->type.$name);
        }

        function set($str,$data) {
            if(null === $this->cache) $this->init();
            if(null === $this->cache) return false;

            if(!strlen(trim($str))) return false;
            $info['_microtime_']=microtime(true);
            $info['_data_']=gzcompress(serialize($data));
            $this -> cache ->set($this->spacename.'-'.$str,serialize($info));
            return true;
        }

        function delete($str) {
            if(null === $this->cache) $this->init();
            if(null === $this->cache) return false;

            if(!strlen(trim($str))) return false;
            $this -> cache ->delete($this->spacename.'-'.$str);
            return true;
        }

        function get($str,$expireTime=-1) {
            if(null === $this->cache) $this->init();
            if(null === $this->cache) return false;

            if(!strlen(trim($str))) return false;
            $info = $this -> cache ->get($this->spacename.'-'.$str);
            if(strlen($info) && $info!==null) {
                $info = unserialize($info);
                // Expire Caché
                if($expireTime >=0 && microtime(true)-$info['_microtime_'] >= $expireTime) {
                    $this -> cache ->delete($this->spacename.'-'.$str);
                    return null;
                } else {
                    return(unserialize(gzuncompress($info['_data_'])));
                }
            } else {
                return null;
            }
        }

        function getTime($str,$expireTime=-1) {
            if(null === $this->cache) $this->init();
            if(!strlen(trim($str))) return false;
            $info = $this -> cache ->get($this->spacename.'-'.$str);
            if(strlen($info) && $info!==null) {
                $info = unserialize($info);
                return(microtime(true)-$info['_microtime_']);
            } else {
                return null;
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

    }

    // Core dependent classes
    class Core
    {
        public $obj = [];
        public $__p,$session,$system,$logs,$errors,$is,$cache,$security,$user,$config,$localization;
        var $_version = '20160522';
        function __construct($root_path='') {
            $this->__p  = new Performance();
            $this->session  = new Session();
            $this->system  = new System($root_path);
            $this->logs  = new Loggin();
            $this->errors= new Loggin();
            $this->is = new Is();
            $this->cache = new Cache();
            $this->__p->add('Construct Class with objects (__p,session[started='.(($this->session->start)?'true':'false').'],system,logs,errors,is,cache):' . __CLASS__, __FILE__);
            $this->security = new Security($this);
            $this->user = new User($this);
            $this->config = new Config($this, __DIR__ . '/config.json');
            $this->request = new Request($this);
            $this->localization = new Localization($this);

            // Local configuration
            if($this->is->development() && is_file($this->system->root_path.'/local_config.json'))
                $this->config->readConfigJSONFile($this->system->root_path.'/local_config.json');
            $this->__p->add('Loaded security,user,config,request objects with __session[started='.(($this->session->start)?'true':'false').']: ,' , __METHOD__);

        }
        function setAppPath($dir) {
            if(is_dir($this->system->root_path.$dir)) {
                $this->system->app_path = $this->system->root_path.$dir;
                $this->system->app_url = $dir;
            } else {
                $this->errors->add($this->system->root_path.$dir . " doesn't exist. The path has to begin with /");
            }
        }

        function loadClass($class,$params=null) {

            $hash = hash('md5', $class . json_encode($params));
            if(key_exists($hash,$this->obj)) return $this->obj[$hash];

            if (is_file(__DIR__ . "/class/{$class}.php"))
                include_once(__DIR__ .  "/class/{$class}.php");
            elseif (is_file($this->system->app_path . "/class/" . $class . ".php"))
                include_once($this->system->app_path . "/class/" . $class . ".php");
            else {
                $this->errors->add("Class $class not found");
                return null;
            }
            $this->obj[$hash] = new $class($this,$params);
            return $this->obj[$hash];
            
        }

        function dispatch() {
            // API end points
            if(strpos($this->system->url['url'],'/h/api')===0 ) {
                if(!strlen($this->system->url['parts'][2])) $this->errors->add('missing api end point');
                else {
                    $apifile = $this->system->url['parts'][2];

                    // path to file
                    if($apifile[0]=='_' || $apifile=='queue')
                        $pathfile = __DIR__ . "/api/h/{$apifile}.php";
                    else {
                        // Every End-point inside the app has priority over the apiPaths
                        $pathfile = $this->system->app_path . "/api/{$apifile}.php";
                        if (!file_exists($pathfile)) {
                            $pathfile = '';
                            // pathAPI is deprecated..
                            if(strlen($this->config->get('apiPath')))
                                $pathfile = $this->config->get('apiPath') . "/{$apifile}.php";
                            elseif(strlen($this->config->get('pathAPI')))
                                $pathfile = $this->config->get('pathAPI') . "/{$apifile}.php";
                        }
                    }

                    // IF NOT EXIST
                    include_once __DIR__ . '/class/RESTful.php';
                    if(!$this->errors->lines){
                        try {
                            if(strlen($pathfile))
                                include_once $pathfile;
                            if (class_exists('API')) {
                                $api = new API($this);
                                $api->main();
                                $this->__p->add("Executed RESTfull->main()", "/api/{$apifile}.php");
                                $api->send();

                            } else {
                                $api = new RESTful($this);
                                $api->setError("api $apifile does not include a API class extended from RESTFul with method ->main()",404);
                                $api->send();
                            }
                        } catch (Exception $e) {
                            $this->errors->add(error_get_last());
                            $this->errors->add($e->getMessage());
                        }
                        $this->__p->add("API including RESTfull.php and {$apifile}.php: ",'There are ERRORS');
                    }
                    return false;
                }
            }
        }
        function jsonDecode($string,$as_array=false) {
            $ret = json_decode($string,$as_array);
            if(json_last_error() != JSON_ERROR_NONE) {
                $this->errors->add('Error decoding JSON: '.$string);
                $this->errors->add(json_last_error_msg());
            }
            return $ret;
        }
    }

    class User
    {
        private $core;
        var $isAuth = null;
        var $namespace;
        var $data = [];

        function __construct(Core &$core,$namespace='Default')
        {
            $this->core = $core;
            $this->namespace = (is_string($namespace) && strlen($namespace))?$namespace:'Default';

            // Check if the Auth comes from X-CloudFrameWork-AuthToken and there is a hacking.
            if (strlen($this->core->security->getHeader('X-CloudFrameWork-AuthToken'))) {
                list($sessionId, $hash) = explode("_", $this->core->security->getHeader('X-CloudFrameWork-AuthToken'), 2);

                // Checking security
                $_security = '';
                if(!strlen(trim($hash)) || $hash != $this->core->system->getRequestFingerPrint()[hash]) {
                    $_security = 'Wrong token.';
                    if(strlen($hash)) $_security.=' Violating token integrity, ';
                } else {

                    $this->core->session->init($sessionId);
                    if (!$this->isAuth() || $this->getVar('token') != $this->core->security->getHeader('X-CloudFrameWork-AuthToken')) {
                        $_security = 'Violating session token integrity';
                        session_destroy();
                        $_SESSION = array();
                        session_regenerate_id();
                        $this->data = [];
                    }
                }
                // Informing security issue
                // This is top level risk
                if(strlen($_security)) {
                    // TODO: Report the log
                    //$this->sendLog('access', 'Hacking', 'X-CloudFrameWork-AuthToken', 'Ilegal token ' . $this->getHeader('X-CloudFrameWork-AuthToken')
                    //    , 'Error comparing with internal token: ' . $this->getAuthUserData('token') . ' for user: ' . $this->getAuthUserData('email'), $this->getConf('CloudServiceLogEmail'));
                    die($_security);
                }
            }
        }

        function init($namespace='') {
            if(strlen($namespace)) $this->namespace = $namespace;

            $this->data[$this->namespace]= $this->core->session->get("_User_".$this->namespace);
            if(null === $this->data[$this->namespace]) $this->data[$this->namespace] =['__auth'=>false];
        }

        function setVar($var,$data='') {
            if(null === $this->data[$this->namespace]) $this->init();
            if(is_array($var)) {
                foreach ($var as $key => $value)
                    $this->data[$this->namespace][$key] = $value;
            } else {
                $this->data[$this->namespace][$var] = $data;
            }
            $this->data[$this->namespace]['__auth'] = true;
            $this->core->session->set("_User_".$this->namespace,$this->data[$this->namespace]);
        }

        function getVar($var='') {
            if(null === $this->data[$this->namespace]) $this->init();
            if(!strlen($var))
                return $this->data[$this->namespace];
            else
                return (key_exists($var,$this->data[$this->namespace]))?$this->data[$this->namespace][$var]:null;
        }

        function isAuth() {
            if(null === $this->isAuth) $this->init();
            return(true === $this->data[$this->namespace]['__auth']);
        }
        function setAuth($bool) {
            if($bool) $this->setData('__auth',true);
            else {
                $this->data[$this->namespace] = ['__auth'=>false];
                $this->core->session->set("_User_".$this->namespace,$this->data[$this->namespace]);
            }
        }

        /*
        * Manage User Organizations
        */

        function addOrganization($orgId, $orgData,$group='')
        {
            $_userOrganizations = $this->getVar("userOrganizations");
            if (empty($_userOrganizations))
                $_userOrganizations = array();


            // Default Organization
            if(count($_userOrganizations)==0)
                $_userOrganizations['__org__'] = $orgId;

            $_userOrganizations['__orgs__'][$orgId]= $orgData;
            if(!strlen($group)) $group = '__OTHER__';
            $_userOrganizations['__groups__'][$group][$orgId]= true;

            $this->setVar("userOrganizations", $_userOrganizations);
        }

        function setOrganizationDefault($orgId) {
            if(strlen($orgId)) {
                $_userOrganizations = $this->getVar("userOrganizations");
                if (is_array($_userOrganizations) && isset($_userOrganizations['__orgs__'][$orgId])) {
                    $_userOrganizations['__org__'] = $orgId;
                    $this->setVar("userOrganizations", $_userOrganizations);
                }
            }
        }

        function getOrganizationDefault() {
            $_userOrganizations = $this->getVar("userOrganizations");
            if(is_array($_userOrganizations) && isset($_userOrganizations['__org__']))
                return $_userOrganizations['__org__'];
            else return '__orgNotNefined__';
        }

        function getOrganizations()
        {
            $_userOrganizations = $this->getVar("userOrganizations");

            if (empty($_userOrganizations)
                || (!isset($_userOrganizations['__orgs__']))
            )
                return array();

            return $_userOrganizations['__orgs__'];
        }
        function getOrganizationsGroups()
        {
            $_userOrganizations = $this->getVar("userOrganizations");

            if (empty($_userOrganizations)
                || (!isset($_userOrganizations['__groups__']))
            )
                return array();

            return $_userOrganizations['__groups__'];
        }
        function getOrganization($id='')
        {
            if(!strlen($id)) $id = $this->getOrganizationDefault();
            $orgs = $this->getOrganizations();
            if(isset($orgs[$id])) return($orgs[$id]);
            else return null;
        }

        function resetOrganizations()
        {
            $this->setVar("userOrganizations", array());
        }

        /*
         * Manage User Roles
         */

        function setRole($rolId, $rolName = '', $org = '')
        {
            if (!strlen($org)) $org = $this->getOrganizationDefault();
            if (!strlen($rolName)) $rolName = $rolId;

            $_userRoles = $this->getVar("UserRoles");
            if (empty($_userRoles))
                $_userRoles = array();

            $_userRoles[$org]['byId'][$rolId] = $rolName;
            $_userRoles[$org]['byName'][$rolName] = $rolId;
            $this->setVar("UserRoles", $_userRoles);
        }

        function hasRoleId($rolId, $org = '')
        {
            if (!strlen($org)) $org = $this->getOrganizationDefault();
            $_userRoles = $this->getVar("UserRoles");
            if (empty($_userRoles))
                $_userRoles = array();

            if (!is_array($rolId))
                $rolId = array($rolId);
            $ret = false;
            foreach ($rolId as $key => $value) {
                if (strlen($value) && !empty($_userRoles[$org]['byId'][$value]) && strlen($_userRoles[$org]['byId'][$value]))
                    $ret = true;
            }
            return ($ret);

        }

        function hasRoleName($roleName, $org = '')
        {
            if (!strlen($org))
                $org = $this->getOrganizationDefault();
            $_userRoles = $this->getVar("UserRoles");
            if (empty($_userRoles))
                $_userRoles = array();

            if (!is_array($roleName))
                $roleName = array($roleName);
            $ret = false;
            foreach ($roleName as $key => $value) {
                if (strlen($value) && !empty($_userRoles[$org]['byName'][$value]) && strlen($_userRoles[$org]['byName'][$value]))
                    $ret = true;
            }
            return ($ret);
        }

        function resetRoles()
        {
            $this->setVar("UserRoles", array());
        }

        function getRoles($org='')
        {
            if (!strlen($org))
                $org = $this->getOrganizationDefault();

            $ret = $this->getVar("UserRoles");
            if($org=='*') return $ret;
            else return (isset($ret[$org]))?$ret[$org]:[];
        }


        /*
         * Manage User Privileges by App
         */

        function setPrivilege($appId, $privileges = array(), $org = '')
        {
            if (!strlen($org)) $org = $this->getOrganizationDefault();

            $_userPrivileges = $this->getVar("UserPrivileges");
            if (empty($_userPrivileges))
                $_userPrivileges = array();

            $_userPrivileges[$org][$appId] = $privileges;
            $this->setVar("UserPrivileges", $_userPrivileges);
        }

        function getPrivileges($appId='',$privilege='' , $org = '')
        {
            if (!strlen($org)) $org = $this->getOrganizationDefault();
            $_userPrivileges = $this->getVar("UserPrivileges");

            if (empty($_userPrivileges)
                || (strlen($appId) && !isset($_userPrivileges[$org][$appId]))
                || (strlen($privilege) && !isset($_userPrivileges[$org][$appId][$privilege])))
                return null;

            if(!strlen($appId)) return $_userPrivileges[$org];
            elseif(!strlen($privilege)) return $_userPrivileges[$org][$appId];
            else return $_userPrivileges[$org][$appId][$privilege];
        }

        function resetPrivileges()
        {
            $this->setVar("UserPrivileges", array());
        }
    }
    class Config
    {
        private $core;
        private $_configPaths = [];
        var $data = [];
        var $menu = [];
        protected $lang = 'en';
        function __construct(Core &$core,$path)
        {
            $this->core = $core;
            $this->readConfigJSONFile($path);

            if(strlen($this->get('LocalizatonDefaultLang'))) $this->setLang($this->get('LocalizatonDefaultLang'));

            // Session value for lang
            if(!empty($_GET['_lang'])) $this->core->session->set('_CloudFrameWorkLang_',$_GET['_lang']);
            $lang = $this->core->session->get('_CloudFrameWorkLang_');
            if(strlen($lang))
                if(!$this->setLang($lang)) {
                    $this->core->session->delete('_CloudFrameWorkLang_');
                }
        }

        /*
         * ABOUT CONFIG LANGUAGE
         */
        function getLang() {
            return($this->lang);
        }
        function setLang($lang) {
            $lang = preg_replace('/[^a-z]/','',strtolower($lang));
            // Control Lang
            if(strlen($lang=trim($lang))<2) {
                $this->core->logs->add('Warning config->setLang. Trying to pass an incorrect Lang: '.$lang);
                return false;
            }
            if(strlen($this->get('LocalizatonAllowedLangs'))
            && !preg_match('/(^|,)'.$lang.'(,|$)/',preg_replace('/[^A-z,]/','',$this->get('LocalizatonAllowedLangs')))) {
                $this->core->logs->add('Warning in config->setLang. '.$lang.' is not included in {{LocalizatonAllowedLangs}}');
                return false;
            }

            $this->lang = $lang;
            return true;
        }
        /*
         * ABOUT ASSIGN VARS
         */
        function get($var)
        {
            return (key_exists($var,$this->data))?$this->data[$var]:null;
        }
        function set($var,$data) {
            $this->data[$var] = $data;
        }
        function pushMenu($var) {
            $this->menu[] = $var;
        }



        function processConfigData($data)
        {
            // Tags convertion
            $convertTags = function ($data) {
                $_array = is_array($data);

                // Convert into string if we received an array
                if($_array) $data = json_encode($data);
                // Tags Conversions
                $data = str_replace('{{rootPath}}', $this->core->system->root_path, $data);
                $data = str_replace('{{appPath}}', $this->core->system->app_path, $data);
                while(strpos($data,'{{confVar:')!==false) {
                    list($foo,$var) = explode("{{confVar:",$data,2);
                    list($var,$foo) = explode("}}",$var,2);
                    $data = str_replace('{{confVar:'.$var.'}}',$this->get(trim($var)),$data);
                }
                // Convert into array if we received an array
                if($_array) $data = json_decode($data,true);
                return $data;

            };
            // going through $data
            foreach ($data as $cond => $vars) {
                if ($cond == '--') continue; // comment
                $tagcode = '';
                if(strpos($cond,':')!== false) {
                    list($tagcode, $tagvalue) = explode(":", $cond, 2);
                    $include = false;
                } else {
                    $include = true;
                    $vars = [$cond=>$vars];
                }

                // Substitute tags for strings
                $vars = $convertTags($vars);
                // If there is a condition tag
                if(!$include) {
                    switch (trim(strtolower($tagcode))) {

                        case "include":
                            // Recursive Call
                            $this->readConfigJSONFile($vars);
                            break;

                        case "webapp":
                            $this->core->setAppPath($vars);
                            break;

                        case "uservar":
                        case "authvar":
                            if(strpos($tagvalue,'=')!==false) {
                                list($authvar, $authvalue) = explode("=", $tagvalue);
                                if ($this->core->user->isAuth() && $this->core->user->getVar($authvar) == $authvalue)
                                    $include = true;
                            }
                            break;
                        case "confvar":
                            if(strpos($tagvalue,'=')!==false) {
                                list($confvar, $confvalue) = explode("=", $tagvalue);
                                if ($this->get($confvar) == $confvalue)
                                    $include = true;
                            }
                            break;
                        case "sessionvar":
                            if(strpos($tagvalue,'=')!==false) {
                                list($sessionvar, $sessionvalue) = explode("=", $tagvalue);
                                if ($this->core->session->get($sessionvar) == $sessionvalue)
                                    $include = true;
                            }
                            break;
                        case "servervar":
                            if(strpos($tagvalue,'=')!==false) {
                                list($servervar, $servervalue) = explode("=", $tagvalue);
                                if ($_SERVER($servervar) == $servervalue)
                                    $include = true;
                            }
                            break;
                        case "redirect":
                            // Array of redirections
                            if(!$this->core->is->terminal()) {
                                if (is_array($vars)) {
                                    foreach ($vars as $ind => $urls)
                                        if (!is_array($urls)) {
                                            $this->core->errors->add('Wrong redirect format. It has to be an array of redirect elements: [{orig:dest},{..}..]');
                                        } else {
                                            foreach ($urls as $urlOrig => $urlDest) {
                                                if ($urlOrig == '*' || !strlen($urlOrig))
                                                    $this->core->system->urlRedirect($urlDest);
                                                else
                                                    $this->core->system->urlRedirect($urlOrig, $urlDest);
                                            }
                                        }

                                } else {
                                    $this->core->system->urlRedirect($vars);
                                }
                            }
                            break;
                        case "true":
                            $include = true;
                            break;
                        case "auth":
                        case "noauth":
                            if (trim(strtolower($tagcode)) == 'auth')
                                $include = $this->core->user->isAuth();
                            else
                                $include = !$this->core->user->isAuth();
                            break;
                        case "development":
                            $include = $this->core->is->development();
                            break;
                        case "production":
                            $include = $this->core->is->production();
                            break;
                        case "indomain":
                        case "domain":
                            $domains = explode(",", $tagvalue);
                            foreach ($domains as $ind => $inddomain) if (strlen(trim($inddomain))) {
                                if (trim(strtolower($tagcode)) == "domain") {
                                    if (strtolower($_SERVER['HTTP_HOST']) == strtolower(trim($inddomain)))
                                        $include = true;
                                } else {
                                    if (stripos($_SERVER['HTTP_HOST'], trim($inddomain)) !== false)
                                        $include = true;
                                }
                            }
                            break;
                        case "interminal":
                                $include = $this->core->is->terminal();
                            break;
                        case "inurl":
                        case "notinurl":
                            $urls = explode(",", $tagvalue);

                            // If notinurl the condition is upsidedown
                            if (trim(strtolower($tagcode)) == "notinurl") $include = true;
                            foreach ($urls as $ind => $inurl) if (strlen(trim($inurl))) {
                                if (trim(strtolower($tagcode)) == "inurl") {
                                    if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                        $include = true;
                                } else {
                                    if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                        $include = false;
                                }
                            }
                            break;

                        case "menu":
                            if (is_array($vars)) {
                                foreach ($vars as $key => $value) {
                                    $this->pushMenu($value);
                                }
                            } else {
                                $this->core->errors->add("menu: tag does not contain an array");
                            }
                            break;
                        case "isversion":
                            if (trim(strtolower($tagvalue)) == 'core')
                                $include = true;
                            break;
                        case "false":
                            break;
                        default:
                            $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                            break;
                    }
                }
                // Include config vars.
                if($include) {
                    if(is_array($vars)) {
                        foreach ($vars as $key => $value) {
                            if ($key == '--') continue; // comment
                            // Recursive call to analyze subelements
                            if (strpos($key, ':')) {

                                $this->processConfigData([$key => $value]);
                            }
                            else {
                                // Assign conf var values converting {} tags
                                $this->set($key, $convertTags($value));
                            }
                        }
                    }
                }
            }

        }
        function readConfigJSONFile($path) {
            // Avoid recursive load JSON files
            if(isset($this->_configPaths[$path])) {
                $this->core->errors->add("Recursive config file: ".$path);
                return false;
            }
            $this->_configPaths[$path] = 1; // Control witch config paths are beeing loaded.
            try {
                $data = json_decode(@file_get_contents($path),true);
                if(!is_array($data)) {
                    if(json_last_error())
                        $this->core->errors->add("Wrong format of json: ".$path);
                    else
                        $this->core->errors->add(error_get_last());
                    return false;
                } else {
                    $this->processConfigData($data);
                    return true;
                }
            } catch(Exception $e) {
                $this->core->errors->add(error_get_last());
                $this->core->errors->add($e->getMessage());
                return false;
            }
        }
    }
    class Security
    {
        private $core;
        function __construct(Core &$core)
        {
            $this->core = $core;

        }
        /*
         * BASIC AUTH
         */
        function existBasicAuth() {
            return (isset($_SERVER['PHP_AUTH_USER']) && strlen($_SERVER['PHP_AUTH_USER'])
               || (isset($_SERVER['HTTP_AUTHORIZATION']) && strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0)
            );
        }
        function getBasicAuth() {
            $username = null;
            $password = null;
            // mod_php
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $username = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];
                // most other servers
            } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0)
                    list($username,$password) = explode(':',base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
            return([$username,$password]);
        }
        function checkBasicAuth($user, $passw)
        {
            list($username,$password) = $this->getBasicAuth();
            return (!is_null($username) && $user==$username && $passw==$password);
        }
        function existBasicAuthConfig() {
            return is_array($this->core->config->get('authorizations'));
        }
        function checkBasicAuthWithConfig()
        {
            $ret = false;
            list($user,$passw) = $this->getBasicAuth();
            if($user === null) {
                $this->core->logs->add('checkBasicAuthWithConfig: No Authorization in headers ');
            }elseif(!is_array($auth = $this->core->config->get('authorizations'))) {
                $this->core->logs->add('checkBasicAuthWithConfig: no "authorizations" array in config. ');
            }elseif(!isset($auth[$user])) {
                $this->core->logs->add('checkBasicAuthWithConfig: key "'.$user.'" does not match in "authorizations"');
            }elseif(!$this->core->system->checkPassword($passw, ((isset($auth[$user]['password'])?$auth[$user]['password']:'')))) {
                $this->core->logs->add('checkBasicAuthWithConfig: password does not match in "authorizations"');

           // User and password match!!!
            } else {
                $ret = true;
                // IPs Security
                if(isset($auth[$user]['ips']) && strlen($auth[$user]['ips'])) {
                    if(!($ret = $this->checkIPs($auth[$user]['ips']))) {
                        $this->core->logs->add('checkBasicAuthWithConfig: IP "'.$this->core->system->ip.'" not allowed');
                    }
                }
            }

            // Return the array of elements it passed
            if($ret) {
                $auth[$user]['_BasicAuthUser_'] = $user;
                $ret = $auth[$user];
            }
            return $ret;
        }

        /*
         * API KEY
         */
        function existWebKey() {
            return (isset($_GET['web_key']) || isset($_POST['web_key']));
        }
        function getWebKey() {
            if(isset($_GET['web_key'])) return $_GET['web_key'];
            else if(isset($_POST['web_key'])) return $_POST['web_key'];
            else return '';
        }

        function checkWebKey($keys) {
            if(!is_array($keys)) $keys = [[$keys,'*']];
            else if(!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getWebKey();

            if(strlen($web_key))
                foreach ($keys as $key) {
                    if($key[0] == $web_key) {
                        if(!isset($key[1])) $key[1]="*";
                        if($key[1]=='*') return true;
                        elseif(!strlen($_SERVER['HTTP_ORIGIN'])) return false;
                        else {
                            $allows = explode(',',$key[1]);
                            foreach ($allows as $host) {
                                if(preg_match('/^.*'.trim($host).'.*$/',$_SERVER['HTTP_ORIGIN'])>0) return true;
                            }
                            return false;
                        }
                    }
                }
            return false;
        }

        function existServerKey() {
            return (strlen($this->getHeader('X-CLOUDFRAMEWORK-SERVER-KEY'))>0);
        }
        function getServerKey() {
            return $this->getHeader('X-CLOUDFRAMEWORK-SERVER-KEY');
        }

        function checkServerKey($keys) {
            if(!is_array($keys)) $keys = [[$keys,'*']];
            else if(!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getServerKey();

            if(strlen($web_key))
                foreach ($keys as $key) {
                    if($key[0] == $web_key) {
                        if(!isset($key[1])) $key[1]="*";
                        if($key[1]=='*') return true;
                        elseif(!strlen($_SERVER['HTTP_ORIGIN'])) return false;
                        else return $this->checkIPs($key[1]);
                    }
                }
            return false;
        }


        /**
         * @param array|string $allows string to compre with the current IP
         * @return bool
         */
        private function checkIPs($allows) {
            if(is_string($allows)) $allows = explode(',',$allows);
            foreach ($allows as $host) {
                $host = trim($host);
                if($host=='*' || preg_match('/^.*'.$host.'.*$/',$this->core->system->ip)>0) return true;
            }
            return false;
        }
        function getHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        // Check checkCloudFrameWorkSecurity
        function checkCloudFrameWorkSecurity($maxSeconds = 0, $id = '', $secret = '')
        {
            if (!strlen($this->getHeader('X-CLOUDFRAMEWORK-SECURITY')))
                $this->core->logs->add('X-CLOUDFRAMEWORK-SECURITY missing.');
            else {
                list($_id, $_zone, $_time, $_token) = explode('__', $this->getHeader('X-CLOUDFRAMEWORK-SECURITY'), 4);
                if (!strlen($_id)
                    || !strlen($_zone)
                    || !strlen($_time)
                    || !strlen($_token)
                ) {
                    $this->core->logs->add('_wrong format in X-CLOUDFRAMEWORK-SECURITY.');
                } else {
                    $date = new DateTime(null, new DateTimeZone($_zone));
                    $secs = microtime(true) + $date->getOffset() - $_time;

                    if (!strlen($secret)) {
                        $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $_id);
                        if (isset($secArr['secret'])) $secret = $secArr['secret'];
                    }

                    if (!strlen($secret)) {
                        $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $_id . ' missing or it is not a righ CLOUDFRAMEWORK array.');
                    } elseif (!strlen($_time) || !strlen($_token)) {
                        $this->core->logs->add('wrong X-CLOUDFRAMEWORK-SECURITY format.');
                        // We allow an error of 2 min
                    } elseif (false && $secs < -120) {
                        $this->core->logs->add('Bad microtime format. Negative value got: ' . $secs . '. Check the clock of the client side.');
                    } elseif (strlen($id) && $id != $_id) {
                        $this->core->logs->add($_id . ' ID is not allowed');
                    } elseif ($this->getHeader('X-CLOUDFRAMEWORK-SECURITY') != $this->generateCloudFrameWorkSecurityString($_id, $_time, $secret)) {
                        $this->core->logs->add('X-CLOUDFRAMEWORK-SECURITY does not match.');
                    } elseif ($maxSeconds > 0 && $maxSeconds <= $secs) {
                        $this->core->logs->add('Security String has reached maxtime: ' . $maxSeconds . ' seconds');
                    } else {
                        $secArr['SECURITY-ID'] = $_id;
                        $secArr['SECURITY-EXPIRATION'] = ($maxSeconds) ? $maxSeconds - $secs : $maxSeconds;
                        return ($secArr);
                    }
                }
            }
            return false;
        }

        function getCloudFrameWorkSecurityInfo($maxSeconds = 0, $id = '', $secret = '') {
            $info = $this->checkCloudFrameWorkSecurity($maxSeconds,$id,$secret);
            if(false === $info) return [];
            else {
                return $this->core->config->get('CLOUDFRAMEWORK-ID-'.$info['SECURITY-ID']);
            }

        }

        // time, has to to be microtime().
        function generateCloudFrameWorkSecurityString($id, $time = '', $secret = '')
        {
            $ret = null;
            if (!strlen($secret)) {
                $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                if (isset($secArr['secret'])) $secret = $secArr['secret'];
            }
            if (!strlen($secret)) {
                $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $id . ' missing.');
            } else {
                if (!strlen($time)) $time = microtime(true);
                $date = new \DateTime(null, new \DateTimeZone('UTC'));
                $time += $date->getOffset();
                $ret = $id . '__UTC__' . $time;
                $ret .= '__' . hash_hmac('sha1', $ret, $secret);
            }
            return $ret;
        }
    }
    Class Localization
    {
        protected $core;
        var $data = [];
        var $wapploca = [];

        function __construct(Core &$core)
        {
            $this->core = $core;
            // Read from Cache last Dics
            if(!isset($_GET['_reloadDics']) && !isset($_GET['_nocacheDics'])) {
                $this->data = $this->core->cache->get('Core:Localization:Data');
                if (!is_array($this->data)) $this->data = [];

                $this->wapploca = $this->core->cache->get('Core:Localization:WAPPLOCA');
                if (!is_array($this->wapploca)) $this->wapploca = [];
            }
        }

        /**
         * Get a Localization code from a localization file
         * @param $locFile
         * @param $code
         * @param array $config
         * @return mixed|string
         */
        function get($locFile, $code, $config=[]) {
            if(!$this->checkLocFileAndCode($locFile,$code)) return 'Err in: ['.$locFile."{{$code}}".']';

            // The $locFile does not exist
            if(!isset($_GET['_debugDics'])) {

                if (!isset($this->data[$locFile][$this->core->config->getLang()]) && !isset($_GET['_reloadDics'])) $this->readFromFile($locFile);
                if (!isset($this->data[$locFile][$this->core->config->getLang()])) {
                    if($this->readFromCloudService($locFile, $code) &&  isset($this->data[$locFile][$this->core->config->getLang()][$code])) {
                        $this->writeLocalization($locFile);
                    }
                }
            }

            if(!isset($_GET['_debugDics']) && isset($this->data[$locFile][$this->core->config->getLang()][$code]))
                $ret = $this->data[$locFile][$this->core->config->getLang()][$code];
            else
                $ret = $this->core->config->getLang().'_'.$locFile."{{$code}}";
            return $ret;
        }

        /**
         * Read from a file the localizations
         * @param $locFile
         * @return bool
         */
        private function readFromFile($locFile) {
            if(!strlen($this->core->config->get('LocalizePath'))) return false;
            $ok = true;
            $this->core->__p->add('Localization->readFromFile: ', $locFile, 'note');
            // First read from local directory if {{LocalizePath}} is defined.
            if(strlen($this->core->config->get('LocalizePath'))) {
                $filename = $this->core->config->get('LocalizePath').'/'.$this->core->config->getLang().'_Core_'.$locFile.'.json';
                try {
                    $ret = @file_get_contents($filename);
                    if ($ret !== false) {
                        $this->data[$locFile][$this->core->config->getLang()] = json_decode($ret,true);
                        $this->core->cache->set('Core:Localization:Data',$this->data);
                    } else {
                        $this->core->logs->add('Error reading ' . $filename);
                        $this->core->logs->add(error_get_last());
                        $ok = false;
                    }
                } catch (Exception $e) {
                    $ok = false;
                    $this->core->logs->add('Error reading ' . $filename . ': ');
                    $this->core->logs->add( $e->getMessage() . ' ' . error_get_last());
                }
            }
            $this->core->__p->add('Localization->readFromFile: ', '', 'endnote');
            return $ok;
        }

        private function  writeLocalization($locFile) {
            if(!strlen($this->core->config->get('LocalizePath'))) return false;
            if(!isset($this->data[$locFile])) return false;
            $ok = true;
            $this->core->__p->add('Localization->writeLocalization: ', $this->core->config->getLang().'_Core_'.$locFile.'.json', 'note');

            $filename = $this->core->config->get('LocalizePath').'/'.$this->core->config->getLang().'_Core_'.$locFile.'.json';
            try {
                $ret = @file_put_contents($filename,json_encode($this->data[$locFile][$this->core->config->getLang()],JSON_PRETTY_PRINT));
                if ($ret === false) {
                    $ok = false;
                    $this->core->logs->add('Error writting ' . $filename);
                    $this->core->logs->add(error_get_last());
                }
            } catch (Exception $e) {
                $ok = false;
                $this->core->logs->add('Error reading ' . $filename . ': ');
                $this->core->logs->add( $e->getMessage() . ' ' . error_get_last());
            }
            $this->core->__p->add('Localization->writeLocalization: ', '', 'endnote');
            return $ok;
        }


        /**
         * Read from a file the localizations
         * @param $locFile
         * @param $code
         * @return bool
         */
        private function readFromCloudService($locFile,$code) {
            if(empty($this->core->config->get('CloudServiceLocalization'))) return false;

            list($org,$app,$cat,$loc_code) = explode(';',$code,4);
            if(!strlen($loc_code) || preg_match('/[^a-z0-9_-]/',$loc_code)) {
                $this->core->logs->add('Localization->readFromCloudService: has a wrong value for $code: '.$code);
                return false;
            }
            $ok = true;

            $key = "$org/$app/$cat/".$this->core->config->getLang();
            // Read From CloudService the info and put the cats into $this->wapploca
            if(!isset($this->wapploca[$key])) {
                $ret = $this->core->request->get('wapploca/dics/' . $key);
                if (!$this->core->request->error) {
                    $ret = json_decode($ret, true);
                    if (!$ret['success']) {
                        $this->core->logs->add($ret);
                        $ok = false;
                    } else {
                        $this->wapploca[$key] = $ret['data'];
                        $this->core->cache->set('Core:Localization:WAPPLOCA',$this->wapploca);
                    }
                }
                else $ok = false;
            }

            // Return the code required
            if(isset($this->wapploca[$key][$code]))
                $this->data[$locFile][$this->core->config->getLang()][$code] =  $this->wapploca[$key][$code];
            else
                $this->data[$locFile][$this->core->config->getLang()][$code] = $code;
            return $ok;
        }

        /**
         * Set manually a code into a Localization file
         * @param $locFile
         * @param $code
         * @param $value
         * @param string $lang
         * @return bool
         */
        function set($locFile, $code, $value, $lang='') {

            // Controling $lang value.
            if(strlen($lang)) $lang = preg_replace('/[^a-z]/','',strtolower($lang));
            if(strlen($lang) < 2) $lang = $this->core->config->getLang();

        }

        /**
         * Check the formats for locaLizationFile and codes
         * @param $locFile
         * @param $code
         * @return bool
         */
        private function checkLocFileAndCode(&$locFile, &$code) {
            $locFile = preg_replace('/[^A-z_\-]/','',$locFile);
            if(!strlen($locFile)) {
                $this->core->errors->set('Localization->set has received a wrong spacename: '.$dic);
                return false;
            }
            $code = preg_replace('/[^a-z_\-;]/','',strtolower($code));
            if(!strlen($code)) {
                $this->core->errors->set('Localization->set has received a wrong code: '.$code);
                return false;
            }
            return true;
        }
    }

    Class Request
    {
        protected $core;
        protected $http;
        public $responseHeaders;
        public $error = false;
        public $errorMsg = [];
        private $curl =[];

        function __construct(Core &$core)
        {
            $this->core = $core;
            if (!$this->core->config->get("CloudServiceUrl"))
                $this->core->config->set("CloudServiceUrl", 'https://cloud.adnbp.com/h/api');

        }

        /**
         * @param string $path Path to complete URL. if it does no start with http.. $path will be aggregated to: $this->core->config->get("CloudServiceUrl")
         * @return string
         */
        function getServiceUrl($path = '')
        {
            if (strpos($path, 'http') === 0) return $path;
            else {
                if (!$this->core->config->get("CloudServiceUrl"))
                    $this->core->config->set("CloudServiceUrl", 'https://cloud.adnbp.com/h/api');

                $this->http = $this->core->config->get("CloudServiceUrl");

                if (strlen($path) && $path[0]!='/')
                    $path = '/' . $path;
                return ($this->http . $path);
            }
        }

        /**
         * Call External Cloud Service Caching the result
         */
        function getCache($rute, $data = null, $verb = 'GET', $extraheaders = null, $raw = false)
        {
            $_qHash = hash('md5', $rute . json_encode($data) . $verb);
            $ret = $this->core->cache->get($_qHash);
            if (isset($_GET['refreshCache']) ||  $ret === false || $ret === null) {
                $ret = $this->get($rute, $data, $verb, $extraheaders, $raw);
                // Only cache successful responses.
                if(is_array($this->responseHeaders) && isset($headers[0]) && strpos($headers[0],'OK')) {
                    $this->core->cache->set($_qHash, $ret);
                }
            }
            return ($ret);
        }
        function getCurl($rute, $data = null, $verb = 'GET', $extra_headers = null, $raw = false) {
            $this->core->__p->add('Request->getCurl: ', "$rute " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $rute = $this->getServiceUrl($rute);
            $this->responseHeaders = null;
            $options['http']['header'] = ['Connection: close','Expect:','ACCEPT:'] ; // improve perfomance and avoid 100 HTTP Header


            // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
            if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                $options['http']['header'][] = 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret"));

            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    $options['http']['header'][] .= $key . ': ' . $value ;
                }
            }

            # Content-type for something different than get.
            if ($verb != 'GET') {
                if (stripos(json_encode($options['http']['header']), 'Content-type') === false) {
                    if ($raw) {
                        $options['http']['header'][] = 'Content-type: application/json' ;
                    } else {
                        $options['http']['header'][] = 'Content-type: application/x-www-form-urlencoded' ;
                    }
                }
            }
            // Build contents received in $data as an array
            if (is_array($data)) {
                if ($verb == 'GET') {
                    if (is_array($data)) {
                        if (strpos($rute, '?') === false) $rute .= '?';
                        else $rute .= '&';
                        foreach ($data as $key => $value) $rute .= $key . '=' . rawurlencode($value) . '&';
                    }
                } else {
                    if ($raw) {
                        if (stripos(json_encode($options['http']['header']), '/json') !== false) {
                            $build_data = json_encode($data);
                        } else
                            $build_data = $data;
                    } else {
                        $build_data = http_build_query($data);
                    }
                    $options['http']['content'] = $build_data;

                    // You have to calculate the Content-Length to run as script
                    $options['http']['header'][] = sprintf('Content-Length: %d', strlen($build_data));
                }
            }

            $curl_options = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,            // return headers
                CURLOPT_FOLLOWLOCATION=> false,
                CURLOPT_HTTPHEADER=>$options['http']['header'],
                CURLOPT_CUSTOMREQUEST =>$verb

            ];
            // Appengine  workaround
            // $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
            // $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
            // Download https://pki.google.com/GIAG2.crt
            // openssl x509 -in GIAG2.crt -inform DER -out google.pem -outform PEM
            // $curl_options[CURLOPT_CAINFO] =__DIR__.'/google.pem';

            if(isset($options['http']['content'])) {
                $curl_options[CURLOPT_POSTFIELDS]=$options['http']['content'];
            }

            // Cache
            $ch = curl_init($rute);
            curl_setopt_array($ch, $curl_options);
            $ret = curl_exec($ch);

            if(!curl_errno($ch)) {
                $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $this->responseHeaders = substr($ret, 0, $header_len);
                $ret = substr($ret, $header_len);
            } else {
                $this->addError(error_get_last());
                $this->addError([('Curl error '.curl_errno($ch))=>curl_error($ch)]);
                $this->addError(['Curl url'=>$rute]);
                $ret = false;
            }
            curl_close($ch);

            $this->core->__p->add('Request->getCurl: ', '', 'endnote');
            return $ret;


        }

        function get($rute, $data = null, $verb = 'GET', $extra_headers = null, $raw = false)
        {
            $rute = $this->getServiceUrl($rute);
            $this->responseHeaders = null;

            $this->core->__p->add('Request->get: ', "$rute " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            // Performance for connections
            $options = array('ssl'=>array('verify_peer' => false));
            $options['http']['ignore_errors'] ='1';
            $options['http']['header'] = 'Connection: close' . "\r\n";


            // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
            if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                $options['http']['header'] .= 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret")) . "\r\n";

            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    $options['http']['header'] .= $key . ': ' . $value . "\r\n";
                }
            }

            // Method
            $options['http']['method'] = $verb;

            // Content-type
            if ($verb != 'GET')
                if (stripos($options['http']['header'], 'Content-type') === false) {
                    if ($raw) {
                        $options['http']['header'] .= 'Content-type: application/json' . "\r\n";
                    } else {
                        $options['http']['header'] .= 'Content-type: application/x-www-form-urlencoded' . "\r\n";
                    }
                }


            // Build contents received in $data as an array
            if (is_array($data))
                if ($verb == 'GET') {
                    if (is_array($data)) {
                        if (strpos($rute, '?') === false) $rute .= '?';
                        else $rute .= '&';
                        foreach ($data as $key => $value) $rute .= $key . '=' . rawurlencode($value) . '&';
                    }
                } else {
                    if ($raw) {
                        if (stripos($options['http']['header'], 'application/json') !== false)
                            $build_data = json_encode($data);
                        else
                            $build_data = $data;
                    } else {
                        $build_data = http_build_query($data);
                    }
                    $options['http']['content'] = $build_data;

                    // You have to calculate the Content-Length to run as script
                    $options['http']['header'] .= sprintf('Content-Length: %d', strlen($build_data)) . "\r\n";
                }

            // Context creation
            $context = stream_context_create($options);


            try {
                $ret = @file_get_contents($rute, false, $context);
                $this->responseHeaders = $http_response_header;
                if ($ret === false) $this->addError(error_get_last());
                else {
                    $code = $this->getLastResponseCode();
                    if($code === null) {
                        $this->addError('Return header not found');
                        $this->addError($this->responseHeaders);
                        $this->addError($ret);
                    } else {
                        if($code >= 400) {
                            $this->addError('Error code returned: '.$code);
                            $this->addError($this->responseHeaders);
                            $this->addError($ret);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->addError(error_get_last());
                $this->addError($e->getMessage());
            }


            $this->core->__p->add('Request->get: ', '', 'endnote');
            return ($ret);
        }
        
        function getLastResponseCode() {
            $code = null;
            if(isset($this->responseHeaders[0])) {
                list($foo,$code,$foo) = explode(' ',$this->responseHeaders[0],3);
            }
            return $code;

        }

        // time, has to to be microtime().
        function generateCloudFrameWorkSecurityString($id, $time = '', $secret = '')
        {
            $ret = null;
            if (!strlen($secret)) {
                $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                if (isset($secArr['secret'])) $secret = $secArr['secret'];
            }
            if (!strlen($secret)) {
                $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $id . ' missing.');
            } else {
                if (!strlen($time)) $time = microtime(true);
                $date = new \DateTime(null, new \DateTimeZone('UTC'));
                $time += $date->getOffset();
                $ret = $id . '__UTC__' . $time;
                $ret .= '__' . hash_hmac('sha1', $ret, $secret);
            }
            return $ret;
        }

        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
        }

        function sendLog($type, $cat, $subcat, $title, $text = '', $email = '', $app = '', $interactive = false)
        {

            if (!strlen($app)) $app = $this->core->system->url['host'];

            $this->core->logs->add(['sending cloud service logs:'=>[$this->getServiceUrl('queue/cf_logs/'.$app),$type, $cat, $subcat, $title]]);
            if (!$this->core->config->get('CloudServiceLog') && !$this->core->config->get('LogPath')) return false;
            $app = str_replace(' ', '_', $app);
            $params['id'] = $this->core->config->get('CloudServiceId');
            $params['cat'] = $cat;
            $params['subcat'] = $subcat;
            $params['title'] = $title;
            if (!is_string($text)) $text = json_encode($text);
            $params['text'] = $text . ((strlen($text)) ? "\n\n" : '');
            if ($this->core->errors->lines) $params['text'] .= "Errors: " . json_encode($this->core->errors->data,JSON_PRETTY_PRINT) . "\n\n";
            if (count($this->core->logs->lines)) $params['text'] .= "Logs: " . json_encode($this->core->logs->data,JSON_PRETTY_PRINT);

            // IP gathered from queue
            if(isset($_REQUEST['cloudframework_queued_ip']))
                $params['ip'] = $_REQUEST['cloudframework_queued_ip'];
            else
                $params['ip'] = $this->core->system->ip;

            // IP gathered from queue
            if(isset($_REQUEST['cloudframework_queued_fingerprint']))
                $params['fingerprint'] = $_REQUEST['cloudframework_queued_fingerprint'];
            else
                $params['fingerprint'] = json_encode($this->core->system->getRequestFingerPrint(),JSON_PRETTY_PRINT);

            // Tell the service to send email of the report.
            if (strlen($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
                $params['email'] = $email;
            if ($this->core->config->get('CloudServiceLog')) {
                $ret = $this->core->jsonDecode($this->get('queue/cf_logs/' . urlencode($app) . '/' . urlencode($type), $params, 'POST'),true);
                if (is_array($ret) && !$ret['success']) $this->addError($ret);
            } else {
                $ret = 'Sending to LogPath not yet implemented';
            }
            return $ret;
        }
    }
}