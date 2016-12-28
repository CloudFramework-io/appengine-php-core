<?php

/**
 * @author Héctor López <hlopez@cloudframework.io>
 * @version 2016
 */

if (!defined("_ADNBP_CORE_CLASSES_")) {
    define("_ADNBP_CORE_CLASSES_", TRUE);

    // Global functions
    /**
     * Echo in output a group of vars passed as args
     * @param mixed $args Element to print.
     */
    function __print($args)
    {
        $ret = "";
        if (key_exists('PWD', $_SERVER)) echo "\n";
        else echo "<pre>";
        for ($i = 0, $tr = count($args); $i < $tr; $i++) {
            if ($args[$i] === "exit")
                exit;
            if (key_exists('PWD', $_SERVER)) echo "\n[$i]: ";
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
            if (key_exists('PWD', $_SERVER)) echo "\n";
            else echo "</li>";
        }
        if (key_exists('PWD', $_SERVER)) echo "\n";
        else echo "</pre>";
    }

    /**
     * Print a group of mixed vars passed as arguments
     */
    function _print()
    {
        __print(func_get_args());
    }

    /**
     * _print() with an exit
     */
    function _printe()
    {
        __print(array_merge(func_get_args(), array('exit')));
    }

    /**
     * Core Class to build cloudframework applications
     * @package Core
     */
    final class Core
    {

        /** @var CorePerformance $__p Object to control de performance */
        public $__p;
        /** @var CoreSession $session Object to control de Session */
        public $session;
        /** @var CoreSystem $system Object to control system interaction */
        public $system;
        /** @var CoreLog $logs Object to control Logs */
        public $logs;
        /** @var CoreLog $errors Object to control Errors */
        public $errors;
        /** @var CoreIs $is Object to help with certain conditions */
        public $is;
        /** @var CoreCache $cache Object to control cache info */
        public $cache;
        /** @var CoreSecurity $security Object to control security */
        public $security;
        /** @var CoreUser $user Object to control user information */
        public $user;
        /** @var CoreConfig $config Object to control configuration */
        public $config;
        /** @var CoreLocalization $localization Object to control manage Localizations */
        public $localization;
        /** @var CoreModel $model Object to control DataModels */
        public $model;

        var $_version = '20161202b';
        var $data = null;


        /**
         * @var array $loadedClasses control the classes loaded
         * @link Core::loadClass()
         */
        private $loadedClasses = [];

        function __construct($root_path = '')
        {
            $this->__p = new CorePerformance();
            $this->session = new CoreSession();
            $this->system = new CoreSystem($root_path);
            $this->logs = new CoreLog();
            $this->errors = new CoreLog();
            $this->is = new CoreIs();
            $this->cache = new CoreCache();


            $this->__p->add('Construct Class with objects (__p,session[started=' . (($this->session->start) ? 'true' : 'false') . '],system,logs,errors,is,cache):' . __CLASS__, __FILE__);
            $this->security = new CoreSecurity($this);

            $this->user = new CoreUser($this);
            $this->config = new CoreConfig($this, __DIR__ . '/config.json');
            $this->request = new CoreRequest($this);
            $this->localization = new CoreLocalization($this);
            $this->model = new CoreModel($this);



            // Local configuration
            if ($this->is->development() && is_file($this->system->root_path . '/local_config.json'))
                $this->config->readConfigJSONFile($this->system->root_path . '/local_config.json');
            $this->__p->add('Loaded security,user,config,request objects with __session[started=' . (($this->session->start) ? 'true' : 'false') . ']: ,', __METHOD__);

            // Config objects based in config
            $this->cache->setSpaceName($this->config->get('cacheSpacename'));

        }

        function setAppPath($dir)
        {
            if (is_dir($this->system->root_path . $dir)) {
                $this->system->app_path = $this->system->root_path . $dir;
                $this->system->app_url = $dir;
            } else {
                $this->errors->add($this->system->root_path . $dir . " doesn't exist. The path has to begin with /");
            }
        }

        function loadClass($class, $params = null)
        {

            $hash = hash('md5', $class . json_encode($params));
            if (key_exists($hash, $this->loadedClasses)) return $this->loadedClasses[$hash];

            if (is_file(__DIR__ . "/class/{$class}.php"))
                include_once(__DIR__ . "/class/{$class}.php");
            elseif (is_file($this->system->app_path . "/class/" . $class . ".php"))
                include_once($this->system->app_path . "/class/" . $class . ".php");
            else {
                $this->errors->add("Class $class not found");
                return null;
            }
            $this->loadedClasses[$hash] = new $class($this, $params);
            return $this->loadedClasses[$hash];

        }

        function dispatch()
        {

            // If the $this->system->app_path ends in / delete the char.
            $this->system->app_path = preg_replace('/\/$/','',$this->system->app_path);

            // API end points. By default $this->config->get('core_api_url') is '/h/api'
            if (strpos($this->system->url['url'], $this->config->get('core_api_url')) === 0) {
                if (!strlen($this->system->url['parts'][2])) $this->errors->add('missing api end point');
                else {


                    $apifile = $this->system->url['parts'][2];

                    // -----------------------
                    // Evaluating tests API cases

                    // path to file
                    if ($apifile[0] == '_' || $apifile == 'queue') {
                        $pathfile = __DIR__ . "/api/h/{$apifile}.php";
                        if (!file_exists($pathfile)) $pathfile = '';
                    } else {
                        // Every End-point inside the app has priority over the apiPaths
                        $pathfile = $this->system->app_path . "/api/{$apifile}.php";
                        if (!file_exists($pathfile)) {
                            $pathfile = '';
                            // pathAPI is deprecated..
                            if (strlen($this->config->get('ApiPath')))
                                $pathfile = $this->config->get('ApiPath') . "/{$apifile}.php";
                            elseif (strlen($this->config->get('pathAPI')))
                                $pathfile = $this->config->get('pathAPI') . "/{$apifile}.php";
                        }
                    }

                    // IF NOT EXIST
                    include_once __DIR__ . '/class/RESTful.php';

                    try {
                        if (strlen($pathfile)) {
                            include_once $pathfile;
                        }

                        // By default the ClassName will be called API.. if the include set $api_class var, we will use that class name
                        if(!isset($api_class)) $api_class = 'API';

                        if (class_exists($api_class)) {
                            $api = new $api_class($this);
                            if ($api->params[0] == '__codes') {
                                $__codes = $api->codeLib;
                                foreach ($__codes as $key => $value) {
                                    $__codes[$key] = $api->codeLibError[$key] . ', ' . $value;
                                }
                                $api->addReturnData($__codes);
                            } else {
                                $api->main();
                            }
                            $this->__p->add("Executed RESTfull->main()", "/api/{$apifile}.php");
                            $api->send();

                        } else {
                            $api = new RESTful($this);
                            $api->setError("api $apifile does not include a {$api_class} class extended from RESTFul with method ->main()", 404);
                            $api->send();
                        }
                    } catch (Exception $e) {
                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                    }
                    $this->__p->add("API including RESTfull.php and {$apifile}.php: ", 'There are ERRORS');
                }
                return false;
            } // Take a LOOK in the menu
            elseif ($this->config->inMenuPath()) {

                // Common logic
                if (!empty($this->config->get('commonLogic'))) {
                    try {
                        include_once $this->system->app_path . '/logic/' . $this->config->get('commonLogic');
                        if (class_exists('CommonLogic')) {
                            $commonLogic = new CommonLogic($this);
                            $commonLogic->main();
                            $this->__p->add("Executed CommonLogic->main()", "/logic/{$this->config->get('commonLogic')}");

                        } else {
                            die($this->config->get('commonLogic').' does not include CommonLogic class');
                        }
                    } catch (Exception $e) {
                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                        _print($this->errors->data);
                    }
                }

                // Specific logic
                if (!$this->errors->lines && !empty($this->config->get('logic'))) {
                    try {
                        include_once $this->system->app_path . '/logic/' . $this->config->get('logic');
                        if (class_exists('Logic')) {
                            $logic = new Logic($this);
                            $logic->main();
                            $this->__p->add("Executed Logic->main()", "/logic/{$this->config->get('logic')}");

                        } else {
                            $logic = new CoreLogic($this);
                            $logic->addError("api {$this->config->get('logic')} does not include a Logic class extended from CoreLogic with method ->main()", 404);
                        }

                    } catch (Exception $e) {
                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                    }
                } else {
                    $logic = new CoreLogic($this);
                }
                // Templates
                if (!empty($this->config->get('template'))) {
                    $logic->render($this->config->get('template'));
                }
                // No template assigned.
                else {
                    // If there is no logic and no template, then ERROR
                    if(empty($this->config->get('logic'))) {
                        $this->errors->add('No logic neither template assigned');
                        _print($this->errors->data);
                    }
                }
            }
            // URL not found in the menu.
            else {
                $this->errors->add('URL has not exist in config-menu');
                _printe($this->errors->data);
            }
        }

        /**
         * Use this method to run JSON and get the RROR message it it happens.
         * @param $string
         * @param bool $as_array
         * @return mixed
         */
        function jsonDecode($string, $as_array = false)
        {
            $ret = @json_decode($string, $as_array);
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->errors->add('Error decoding JSON: ' . $string);
                $this->errors->add(json_last_error_msg());
            }
            return $ret;
        }


        /**
         * Info to manage Data in general.
         * @param $data
         */
        function setData($data) { $this->data = $data; }
        function getData() { return($this->data); }
        function getDataKey($key) { return(isset($this->data[$key])?$this->data[$key]:null); }
        function setDataKey($key,$data) { $this->data[$key] = $data; }

        function activateCacheFile()
        {
            if (!strlen($this->config->get("cachePath"))) return false;
            $this->cache->activateCacheFile($this->config->get("cachePath"));
            if ($this->cache->error) {
                $this->errors->add($this->cache->errorMsg);
                return false;
            } else return true;
        }
    }

    /**
     * Class to track performance
     * @package Core
     */
    class CorePerformance
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
                if (!isset($this->data['titles'][$title])) $this->data['titles'][$title] = ['mem' => '', 'time' => 0, 'lastIndex' => ''];
                $this->data['titles'][$title]['mem'] = $_mem;
                $this->data['titles'][$title]['time'] += $_time;
                $this->data['titles'][$title]['lastIndex'] = $this->data['lastIndex'];

            }

            if (isset($_GET['__p']) && $_GET['__p'] == $this->data['lastIndex']) {
                _printe($this->data);
                exit;
            }

            $this->data['lastIndex']++;

        }

        function getTotalTime($prec = 3)
        {
            return (round(microtime(TRUE) - $this->data['initMicrotime'], $prec));
        }

        function getTotalMemory($prec = 3)
        {
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

    /**
     * Class to manage session
     * @package Core
     */
    class CoreSession
    {
        var $start = false;
        var $id = '';

        function __construct()
        {
        }

        function init($id = '')
        {

            // If they pass a session id I will use it.
            if (!empty($id)) session_id($id);

            // Session start
            session_start();

            // Let's keep the session id
            $this->id = session_id();

            // Initiated.
            $this->start = true;
        }

        function get($var)
        {
            if (!$this->start) $this->init();
            if (key_exists('CloudSessionVar_' . $var, $_SESSION)) {
                try {
                    $ret = unserialize(gzuncompress($_SESSION['CloudSessionVar_' . $var]));
                } catch (Exception $e) {
                    return null;
                }
                return $ret;
            }
            return null;
        }

        function set($var, $value)
        {
            if (!$this->start) $this->init();
            $_SESSION['CloudSessionVar_' . $var] = gzcompress(serialize($value));
        }

        function delete($var)
        {
            if (!$this->start) $this->init();
            unset($_SESSION['CloudSessionVar_' . $var]);
        }
    }

    /**
     * Clas to interacto with with the System variables
     * @package Core
     */
    class CoreSystem
    {
        var $url, $app,$root_path, $app_path, $app_url;
        var $config = [];
        var $ip, $user_agent, $format, $time_zone;

        function __construct($root_path = '')
        {
            if (!strlen($root_path)) $root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

            $this->url['https'] = $_SERVER['HTTPS'];
            $this->url['protocol'] = ($_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
            $this->url['host'] = $_SERVER['HTTP_HOST'];
            $this->url['url_uri'] = $_SERVER['REQUEST_URI'];

            $this->url['url'] = $_SERVER['REQUEST_URI'];
            $this->url['params'] = '';
            if (strpos($_SERVER['REQUEST_URI'], '?') !== false)
                list($this->url['url'], $this->url['params']) = explode('?', $_SERVER['REQUEST_URI'], 2);

            $this->url['host_base_url'] = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $this->url['host_url'] = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $this->url['url'];
            $this->url['host_url_uri'] = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $this->url['script_name'] = $_SERVER['SCRIPT_NAME'];
            $this->url['parts'] = explode('/', substr($this->url['url'], 1));

            // paths
            $this->root_path = $root_path;
            $this->app_path = $this->root_path;

            // Remote user:
            $this->ip = ($_SERVER['REMOTE_ADDR'] == '::1') ? 'localhost' : $_SERVER['REMOTE_ADDR'];
            $this->user_agent = $_SERVER['HTTP_USER_AGENT'];

            // About timeZone, Date & Number format
            if (isset($_SERVER['PWD']) && strlen($_SERVER['PWD'])) date_default_timezone_set('UTC'); // necessary for shell run
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
            $this->config['setLanguageByPath'] = false;

        }

        /**
         * @param $url path for destination ($dest is empty) or for source ($dest if not empty)
         * @param string $dest Optional destination. If empty, destination will be $url
         */
        function urlRedirect($url, $dest = '')
        {
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
            // Return the fingerprint coming from a queue
            if (isset($_REQUEST['cloudframework_queued_fingerprint'])) {
                return (json_decode($_REQUEST['cloudframework_queued_fingerprint'], true));
            }

            $ret['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $ret['host'] = $_SERVER['HTTP_HOST'];
            $ret['software'] = $_SERVER['SERVER_SOFTWARE'];
            if ($extra == 'geodata') {
                $ret['geoData'] = $this->core->getGeoData();
                unset($ret['geoData']['source_ip']);
                unset($ret['geoData']['credit']);
            }
            $ret['hash'] = sha1(implode(",", $ret));
            $ret['ip'] = $this->ip;
            $ret['http_referer'] = $_SERVER['HTTP_REFERER'];
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

    /**
     * Class to manage Logs & Errors
     * @package Core
     */
    class CoreLog
    {
        var $lines = 0;
        var $data = [];
        var $syslog_type = LOG_DEBUG;

        /**
         * Reset the log and add an entry in the log.. if syslog_title is passed, also insert a LOG_DEBUG
         * @param $data
         * @param string $syslog_title
         */
        function set($data,$syslog_title=null, $syslog_type=null)
        {
            $this->lines = 0;
            $this->data = [];
            $this->add($data,$syslog_title, $syslog_type);
        }

        /**
         * Add an entry in the log.. if syslog_title is passed, also insert a LOG_DEBUG
         * @param $data
         * @param string $syslog_title
         */
        function add($data, $syslog_title=null, $syslog_type=null)
        {
            // Evaluate to write in syslog
            if(null !==  $syslog_title) {

                if(null==$syslog_type) $syslog_type = $this->syslog_type;
                syslog($syslog_type, $syslog_title.': '. json_encode($data,JSON_FORCE_OBJECT));

                // Change the data sent to say that the info has been sent to syslog
                if(is_string($data))
                    $data = 'SYSLOG '.$syslog_title.': '.$data;
                else
                    $data = ['SYSLOG '.$syslog_title=>$data];
            }

            // Store in local var.
            $this->data[] = $data;
            $this->lines++;

        }

        /**
         * return the current data stored in the log
         * @return array
         */
        function get() { return $this->data; }

        /**
         * store all the data inside a syslog
         * @param $title
         */
        function sendToSysLog($title,$syslog_type=null) {
            if(null==$syslog_type=null) $syslog_type = $this->syslog_type;
            syslog($syslog_type, $title. json_encode($this->data,JSON_FORCE_OBJECT));
        }

        /**
         * Reset the log
         */
        function reset()
        {
            $this->lines = 0;
            $this->data = [];
        }

    }

    /**
     * Class to answer is? questions
     * @package Core
     */
    class CoreIs
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

        function terminal()
        {
            return isset($_SERVER['PWD']);
        }

        function dirWritable($dir)
        {
            if (strlen($dir)) {
                if (!$this->dirReadble($dir)) return false;
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

        function validEmail($email)
        {
            return (filter_var($email, FILTER_VALIDATE_EMAIL));
        }

        function validURL($url)
        {
            return (filter_var($url, FILTER_VALIDATE_URL));
        }
    }

    /**
     * Class to manage Cache
     * @package Core
     */
    class CoreCache
    {
        var $cache = null;
        var $spacename = 'CloudFrameWork';
        var $type = 'memory';
        var $dir = '';
        var $error = false;
        var $errorMsg = [];
        var $log = null;
        var $debug = false;
        var $lastHash = null;
        var $lastExpireTime = null;
        var $atom = null;

        /**
         * CoreCache constructor. If $type==CacheInDirectory a writable $path is required
         * @param string $spacename
         * @param string $path if != null the it assumes the cache will be store in files
         */
        function __construct($spacename = '',  $path=null, $debug = null)
        {
            // Asign a CoreLog Class to log
            $this->log = new CoreLog();

            // Initialize $this->spacename
            if (!strlen(trim($spacename))) $spacename = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : $_SERVER['PWD'];
            $this->setSpaceName($spacename);

            // Activate debug based on $debug or if I am in development
            if(null !== $debug)
                $this->debug = true === $debug;
            else
                if(stripos($_SERVER['SERVER_SOFTWARE'], 'Development') !== false) $this->debug = true;

            // Activate CacheInDirectory
            if (null !== $path) {
                $this->activateCacheFile($path);
            }



        }

        /**
         * Activate cache in files.. It requires that the path will be writtable
         * @param string $path dir path to keep files.
         * @param string $spacename
         * @return bool
         */
        function activateCacheFile($path, $spacename = '')
        {
            if ($_SESSION['Core_CacheFile_' . $path] || is_dir($path) || @mkdir($path)) {
                $this->type = 'CacheInDirectory';
                $this->dir = $path;
                if (strlen($spacename)) $spacename = '_' . $spacename;
                $this->setSpaceName(basename($path) . $spacename);
                $this->init();

                // Save in session to improve the performance for buckets because is_dir has a high cost.
                $_SESSION['Core_CacheFile_' . $path] = true;
                return true;
            } else {
                $this->addError($path . ' does not exist and can not be created');
                return false;
            }

        }

        /**
         * DEPRECATED
         */
        function activeDirPath($path, $spacename = '') { return $this->activateCacheFile(); }

        /**
         * Initialiated Cache Memory object.. If previously it has been called it just returns true.. if there is an error it returns false..
         * @return bool
         */
        function init()
        {
            if(null !== $this->cache) return(is_object($this->cache));

            if($this->debug)
                $this->log->add("init(). type: {$this->type} spacename: {$this->spacename}",'CoreCache');

            if ($this->type == 'memory') {
                if (class_exists('MemCache')) {
                    $this->cache = new Memcache;
                } else {
                    $this->cache = false;
                    $this->log->add("init(). Failed because MemCache does not exist",'CoreCache');
                }
            } else
                $this->cache = new CoreCacheFile($this->dir);

            return(is_object($this->cache));
        }

        /**
         * Set a $spacename to set/get $objects
         * @param $name
         */
        function setSpaceName($name)
        {
            if (strlen($name)) {
                $name = '_' . trim($name);
                $this->spacename = preg_replace('/[^A-z_-]/', '_', 'CloudFrameWork_' . $this->type . $name);
            }
        }

        /**
         * Set an object on cache based on $key
         * @param $key
         * @param mixed $object
         * @param string $hash Allow to set the info based in a hash to determine if it is valid when read it.
         * @return bool
         */
        function set($key, $object, $hash=null)
        {
            if(!$this->init() || !strlen(trim($key))) return null;

            $info['_microtime_'] = microtime(true);
            $info['_hash_'] = $hash;
            $info['_data_'] = gzcompress(serialize($object));
            $this->cache->set($this->spacename . '-' . $key, serialize($info));

            if($this->debug)
                $this->log->add("set({$key}). token: ".$this->spacename . '-' . $key.(($hash)?' with hash: '.$hash:''),'CoreCache');

            unset($info);
            return true;
        }

        /**
         * delete a $key from cache
         * @param $key
         * @return true|null
         */
        function delete($key)
        {
            if(!$this->init() || !strlen(trim($key))) return null;

            if (!strlen(trim($key))) return false;
            $this->cache->delete($this->spacename . '-' . $key);

            if($this->debug)
                $this->log->add("delete(). token: ".$this->spacename . '-' . $key,'CoreCache');

            return true;
        }

        /**
         * Return an object from Cache.
         * @param $key
         * @param int $expireTime The default value es -1. If you want to expire, you can use a value in seconds.
         * @param string $hash if != '' evaluate if the $hash match with hash stored in cache.. If not, delete the cache and return false;
         * @return bool|mixed|null
         */
        function get($key, $expireTime = -1, $hash = '')
        {
            if(!$this->init() || !strlen(trim($key))) return null;

            if (!strlen($expireTime)) $expireTime = -1;

            $info = $this->cache->get($this->spacename . '-' . $key);
            if (strlen($info) && $info !== null) {

                $info = unserialize($info);
                $this->lastExpireTime = microtime(true) - $info['_microtime_'];
                $this->lastHash = $info['_hash_'];

                // Expire Caché
                if ($expireTime >= 0 && microtime(true) - $info['_microtime_'] >= $expireTime) {
                    $this->cache->delete($this->spacename . '-' . $key);
                    if($this->debug)
                        $this->log->add("get('$key',$expireTime,'$hash') failed (beacause expiration) token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' secs.]','CoreCache');
                    return null;
                }
                // Hash Cache
                if ('' != $hash && $hash != $info['_hash_']) {
                    $this->cache->delete($this->spacename . '-' . $key);
                    if($this->debug)
                        $this->log->add("get('$key',$expireTime,'$hash') failed (beacause hash does not match) token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' secs.]','CoreCache');
                    return null;
                }
                // Normal return

                if($this->debug)
                    $this->log->add("get('$key',$expireTime,'$hash'). successful returned token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' secs.]','CoreCache');
                return (unserialize(gzuncompress($info['_data_'])));

            } else {
                if($this->debug) $this->log->add("get($key,$expireTime,$hash) failed (beacause it does not exist) token: ".$this->spacename . '-' . $key,'CoreCache');
                return null;
            }
        }

        /**
         * Return a cache based in a hash previously assigned in set
         * @param $str
         * @param $hash
         * @return bool|mixed|null
         */
        public function getByHash($str, $hash) { return $this->get($str,-1, $hash); }


        /**
         * Return a cache based in the Expiration time = TimeToSave + $seconds
         * @param string $str
         * @param int $seconds
         * @return bool|mixed|null
         */
        public function getByExpireTime($str, $seconds) { return $this->get($str,$seconds); }

        /**
         * Initialiated Atom Memory object.. If previously it has been called it just returns true.. if there is an error it returns false..
         * @return bool
         */
        public function initAtom() {
            if(null !== $this->atom) return(is_object($this->atom));

            if($this->debug)
                $this->log->add("initAtom(). spacename: {$this->spacename}_ATOM_Cache",'CoreCache');

            if (class_exists('MemCache')) {
                $this->atom = new Memcache;
            } else {
                $this->atom = false;
                $this->log->add("initAtom(). Failed because MemCache does not exist",'CoreCache');
            }

            return(is_object($this->atom));

        }

        /**
         * get Atom Id based in $key.. If not found return false
         * @param string $key
         * @param init $expireTime
         * @return null|false|string
         */
        public function getAtom($key,$expireTime) {
            // Check initAtom has been called at least once with no errors
            if(!$this->initAtom() || !strlen(trim($key))) return null;
            $spacename = $this->spacename.'_ATOM_Cache';
            $microtime = $this->atom->get($spacename . '-' . $key);

            if($microtime)  {
                if(microtime(true) - $microtime >= $expireTime) {
                    $microtime = null;
                    $this->atom->delete($spacename . '-' . $key);
                    if($this->debug)
                        $this->log->add("getAtom('{$key}',{$expireTime}). deleted because expiration. running since ".(round(microtime(true) - $microtime,2))." secs. ".$spacename . '-' . $key,'CoreCache');
                } else {
                    $this->log->add("getAtom('{$key}',{$expireTime}) running since ".(round(microtime(true) - $microtime,2)).' secs. '.$spacename . '-' . $key,'CoreCache');
                }
            } else {
                if($this->debug)
                    $this->log->add("getAtom('{$key}',{$expireTime}) not found. ".$spacename . '-' . $key,'CoreCache');
            }

            if(!$microtime) return false;
            else return($spacename . '-' . $key.': '.$microtime);
        }

        /**
         * Set an ATOM key content.. Return the String generated of null if $this->atom is not initiated.
         * @param $key
         * @return string|null
         */
        public function setAtom($key) {
            // Check initAtom has been called at least once with no errors
            if(!$this->initAtom() || !strlen(trim($key))) return null;
            $spacename = $this->spacename.'_ATOM_Cache';

            $microtime = microtime(true);
            $this->atom->set($spacename . '-' . $key, $microtime);

            if($this->debug)
                $this->log->add("setAtom('{$key}'). microtime: {$microtime}".$spacename . '-' . $key,'CoreCache');

            return($spacename . '-' . $key.': '.$microtime);
        }

        /**
         * delete an ATOM key. If the $key does not exist it returns true too.
         * @param $key
         * @return true|null
         */
        public function deleteAtom($key) {
            // Check initAtom has been called at least once with no errors
            if(!$this->initAtom() || !strlen(trim($key))) return null;
            $spacename = $this->spacename.'_ATOM_Cache';

            $this->atom->delete($spacename . '-' . $key);

            if($this->debug)
                $this->log->add("deleteAtom('{$key}'). ".$spacename . '-' . $key,'CoreCache');

            return true;

        }

        /**
         * Set error in the class
         * @param $value
         */
        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

    }

    /**
     * Class to manate Cache in Files
     * @package Core
     */
    class CoreCacheFile
    {

        var $dir = '';

        function __construct($dir = '')
        {
            if (strlen($dir)) $this->dir = $dir . '/';
        }

        function set($path, $data)
        {
            return @file_put_contents($this->dir . $path, gzcompress(serialize($data)));
        }

        function delete($path)
        {
            return @unlink($this->dir . $path);
        }

        function get($path)
        {
            $ret = false;
            if (is_file($this->dir . $path))
                $ret = file_get_contents($this->dir . $path);
            if (false === $ret) return null;
            else return unserialize(gzuncompress($ret));
        }
    }

    /**
     * Class to manage User information
     * @package Core
     */
    class CoreUser
    {
        private $core;
        var $isAuth = null;
        var $namespace;
        var $data = [];

        function __construct(Core &$core, $namespace = 'Default')
        {
            $this->core = $core;
            $this->namespace = (is_string($namespace) && strlen($namespace)) ? $namespace : 'Default';

            // Check if the Auth comes from X-CloudFrameWork-AuthToken and there is a hacking.
            if (strlen($this->core->security->getHeader('X-CloudFrameWork-AuthToken'))) {
                list($sessionId, $hash) = explode("_", $this->core->security->getHeader('X-CloudFrameWork-AuthToken'), 2);

                // Checking security
                $_security = '';
                if (!strlen(trim($hash)) || $hash != $this->core->system->getRequestFingerPrint()[hash]) {
                    $_security = 'Wrong token.';
                    if (strlen($hash)) $_security .= ' Violating token integrity, ';
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
                if (strlen($_security)) {
                    // TODO: Report the log
                    //$this->sendLog('access', 'Hacking', 'X-CloudFrameWork-AuthToken', 'Ilegal token ' . $this->getHeader('X-CloudFrameWork-AuthToken')
                    //    , 'Error comparing with internal token: ' . $this->getAuthUserData('token') . ' for user: ' . $this->getAuthUserData('email'), $this->getConf('CloudServiceLogEmail'));
                    die($_security);
                }
            }
        }

        function init($namespace = '')
        {
            if (strlen($namespace)) $this->namespace = $namespace;
            $this->core->session->init();
            // LOGOUT with $_REQUEST paramter

            if (isset($_GET['_logout']) || isset($_POST['_logout'])) $this->core->session->delete("_User_" . $this->namespace);
            $this->data[$this->namespace] = $this->core->session->get("_User_" . $this->namespace);

            if (null === $this->data[$this->namespace]) $this->data[$this->namespace] = ['__auth' => false];
        }

        function setVar($var, $data = '')
        {
            if (null === $this->data[$this->namespace]) $this->init();
            if (is_array($var)) {
                foreach ($var as $key => $value)
                    $this->data[$this->namespace][$key] = $value;
            } else {
                $this->data[$this->namespace][$var] = $data;
            }
            $this->data[$this->namespace]['__auth'] = true;
            $this->core->session->set("_User_" . $this->namespace, $this->data[$this->namespace]);
        }

        function getVar($var = '')
        {
            if (null === $this->data[$this->namespace]) $this->init();
            if (!strlen($var))
                return $this->data[$this->namespace];
            else
                return (key_exists($var, $this->data[$this->namespace])) ? $this->data[$this->namespace][$var] : null;
        }

        function isAuth()
        {
            if (null === $this->isAuth) $this->init();
            return (true === $this->data[$this->namespace]['__auth']);
        }

        function setAuth($bool)
        {
            if ($bool) $this->setData('__auth', true);
            else {
                $this->data[$this->namespace] = ['__auth' => false];
                $this->core->session->set("_User_" . $this->namespace, $this->data[$this->namespace]);
            }
        }

        /*
        * Manage User Organizations
        */

        function addOrganization($orgId, $orgData, $group = '')
        {
            $_userOrganizations = $this->getVar("userOrganizations");
            if (empty($_userOrganizations))
                $_userOrganizations = array();


            // Default Organization
            if (count($_userOrganizations) == 0)
                $_userOrganizations['__org__'] = $orgId;

            $_userOrganizations['__orgs__'][$orgId] = $orgData;
            if (!strlen($group)) $group = '__OTHER__';
            $_userOrganizations['__groups__'][$group][$orgId] = true;

            $this->setVar("userOrganizations", $_userOrganizations);
        }

        function setOrganizationDefault($orgId)
        {
            if (strlen($orgId)) {
                $_userOrganizations = $this->getVar("userOrganizations");
                if (is_array($_userOrganizations) && isset($_userOrganizations['__orgs__'][$orgId])) {
                    $_userOrganizations['__org__'] = $orgId;
                    $this->setVar("userOrganizations", $_userOrganizations);
                }
            }
        }

        function getOrganizationDefault()
        {
            $_userOrganizations = $this->getVar("userOrganizations");
            if (is_array($_userOrganizations) && isset($_userOrganizations['__org__']))
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

        function getOrganization($id = '')
        {
            if (!strlen($id)) $id = $this->getOrganizationDefault();
            $orgs = $this->getOrganizations();
            if (isset($orgs[$id])) return ($orgs[$id]);
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

        function getRoles($org = '')
        {
            if (!strlen($org))
                $org = $this->getOrganizationDefault();

            $ret = $this->getVar("UserRoles");
            if ($org == '*') return $ret;
            else return (isset($ret[$org])) ? $ret[$org] : [];
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

        function getPrivileges($appId = '', $privilege = '', $org = '')
        {
            if (!strlen($org)) $org = $this->getOrganizationDefault();
            $_userPrivileges = $this->getVar("UserPrivileges");

            if (empty($_userPrivileges)
                || (strlen($appId) && !isset($_userPrivileges[$org][$appId]))
                || (strlen($privilege) && !isset($_userPrivileges[$org][$appId][$privilege]))
            )
                return null;

            if (!strlen($appId)) return $_userPrivileges[$org];
            elseif (!strlen($privilege)) return $_userPrivileges[$org][$appId];
            else return $_userPrivileges[$org][$appId][$privilege];
        }

        function resetPrivileges()
        {
            $this->setVar("UserPrivileges", array());
        }


    }

    /**
     * Class to manage CloudFramework configuration.
     * @package Core
     */
    class CoreConfig
    {
        private $core;
        private $_configPaths = [];
        var $data = [];
        var $menu = [];
        protected $lang = 'en';

        function __construct(Core &$core, $path)
        {
            $this->core = $core;
            $this->readConfigJSONFile($path);

            // Set lang for the system
            if (strlen($this->get('localizatonDefaultLang'))) $this->setLang($this->get('localizatonDefaultLang'));

            // localizatonFieldParamName allow to change the lang by URL
            if (strlen($this->get('localizatonFieldParamName'))) {
                $field = $this->get('localizatonFieldParamName');
                if (!empty($_GET[$field])) $this->core->session->set('_CloudFrameWorkLang_', $_GET[$field]);
                $lang = $this->core->session->get('_CloudFrameWorkLang_');
                if (strlen($lang))
                    if (!$this->setLang($lang)) {
                        $this->core->session->delete('_CloudFrameWorkLang_');
                    }
            }

        }

        /**
         * Return an array of files readed for config.
         * @return array
         */
        function getConfigLoaded()
        {
            $ret = [];
            foreach ($this->_configPaths as $path => $foo) {
                $ret[] = str_replace($this->core->system->root_path, '', $path);
            }
            return $ret;
        }

        /**
         * Get the current lang
         * @return string
         */
        function getLang()
        {
            return ($this->lang);
        }

        /**
         * Assign the language
         * @param $lang
         * @return bool
         */
        function setLang($lang)
        {
            $lang = preg_replace('/[^a-z]/', '', strtolower($lang));
            // Control Lang
            if (strlen($lang = trim($lang)) < 2) {
                $this->core->logs->add('Warning config->setLang. Trying to pass an incorrect Lang: ' . $lang);
                return false;
            }
            if (strlen($this->get('localizatonAllowedLangs'))
                && !preg_match('/(^|,)' . $lang . '(,|$)/', preg_replace('/[^A-z,]/', '', $this->get('localizatonAllowedLangs')))
            ) {
                $this->core->logs->add('Warning in config->setLang. ' . $lang . ' is not included in {{localizatonAllowedLangs}}');
                return false;
            }

            $this->lang = $lang;
            return true;
        }

        /**
         * Get a config var value. $var is empty return the array with all values.
         * @param string $var  Config variable
         * @return mixed|null
         */
        public function get($var='')
        {
            if(strlen($var))
                return (key_exists($var, $this->data)) ? $this->data[$var] : null;
            else return $this->data;
        }

        /**
         * Set a config var
         * @param $var string
         * @param $data mixed
         */
        public function set($var, $data)
        {
            $this->data[$var] = $data;
        }

        /**
         * Set a config vars bases in an Array {"key":"value"}
         * @param $data Array
         */
        public function bulkSet(Array $data)
        {
            foreach ($data as $key=>$item) {
                $this->data[$key] = $item;
            }
        }
        /**
         * Add a menu line
         * @param $var
         */
        public function pushMenu($var)
        {
            if (!key_exists('menupath', $this->data)) {
                $this->menu[] = $var;
                if (!isset($var['path'])) {
                    $this->core->logs->add('Missing path in menu line');
                    $this->core->logs->add($var);
                } else {
                    // Trying to match the URLs
                    if (strpos($var['path'], "{*}"))
                        $_found = strpos($this->core->system->url['url'], str_replace("{*}", '', $var['path'])) === 0;
                    else
                        $_found = $this->core->system->url['url'] == $var['path'];

                    if ($_found) {
                        $this->set('menupath', $var['path']);
                        foreach ($var as $key => $value) {
                            $value = $this->convertTags($value);
                            $this->set($key, $value);
                        }
                    }
                }
            }
        }

        /**
         * Determine if the current URL is part of the menupath
         * @return bool
         */
        public function inMenuPath()
        {
            return key_exists('menupath', $this->data);
        }

        /**
         * Try to read a JOSN file to process it as a corfig file
         * @param $path string
         * @return bool
         */
        public function readConfigJSONFile($path)
        {
            // Avoid recursive load JSON files
            if (isset($this->_configPaths[$path])) {
                $this->core->errors->add("Recursive config file: " . $path);
                return false;
            }
            $this->_configPaths[$path] = 1; // Control witch config paths are beeing loaded.
            try {
                $data = json_decode(@file_get_contents($path), true);

                if (!is_array($data)) {
                    $this->core->errors->add('error reading ' . $path);
                    if (json_last_error())
                        $this->core->errors->add("Wrong format of json: " . $path);
                    elseif (!empty(error_get_last()))
                        $this->core->errors->add(error_get_last());
                    return false;
                } else {
                    $this->processConfigData($data);
                    return true;
                }
            } catch (Exception $e) {
                $this->core->errors->add(error_get_last());
                $this->core->errors->add($e->getMessage());
                return false;
            }
        }

        /**
         * Process a config array
         * @param $data array
         */
        public function processConfigData(array $data)
        {
            // going through $data
            foreach ($data as $cond => $vars) {

                // Just a comment
                if ($cond == '--') continue;

                // Convert potentials Tags
                if (is_string($vars)) $vars = $this->convertTags($vars);
                $include = false;

                $tagcode = '';
                if (strpos($cond, ':') !== false) {
                    // Substitute tags for strings
                    $cond = $this->convertTags(trim($cond));
                    list($tagcode, $tagvalue) = explode(":", $cond, 2);
                    $tagcode = trim($tagcode);
                    $tagvalue = trim($tagvalue);

                    if ($this->isConditionalTag($tagcode))
                        $include = $this->getConditionalTagResult($tagcode, $tagvalue);
                    elseif ($this->isAssignationTag($tagcode)) {
                        $this->setAssignationTag($tagcode, $tagvalue, $vars);
                        continue;
                    } else {
                        $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                        continue;
                    }

                } else {
                    $include = true;
                    $vars = [$cond => $vars];

                }

                // Include config vars.
                if ($include) {
                    if (is_array($vars)) {
                        foreach ($vars as $key => $value) {
                            if ($key == '--') continue; // comment
                            // Recursive call to analyze subelements
                            if (strpos($key, ':')) {
                                $this->processConfigData([$key => $value]);
                            } else {
                                // Assign conf var values converting {} tags
                                $this->set($key, $this->convertTags($value));
                            }
                        }
                    }
                }
            }

        }

        /**
         * Evalue if the tag is a condition
         * @param $tag
         * @return bool
         */
        private function isConditionalTag($tag)
        {
            $tags = ["uservar", "authvar", "confvar", "sessionvar", "servervar", "auth", "noauth", "development", "production"
                , "indomain", "domain", "interminal", "url", "noturl", "inurl", "notinurl", "beginurl", "notbeginurl"
                , "inmenupath", "notinmenupath", "isversion", "false", "true"];
            return in_array(strtolower($tag), $tags);
        }

        /**
         * Evalue conditional tags on config file
         * @param $tagcode string
         * @param $tagvalue string
         * @return bool
         */
        private function getConditionalTagResult($tagcode, $tagvalue)
        {
            $evaluateTags = [];
            while(strpos($tagvalue,'|')) {
                list($tagvalue,$tags) = explode('|',$tagvalue,2);
                $evaluateTags[] = [trim($tagcode),trim($tagvalue)];
                list($tagcode,$tagvalue) = explode(':',$tags,2);
            }
            $evaluateTags[] = [trim($tagcode),trim($tagvalue)];
            $ret = false;
            // Conditionals tags
            // -----------------
            foreach ($evaluateTags as $evaluateTag) {
                $tagcode = $evaluateTag[0];
                $tagvalue = $evaluateTag[1];
                switch (trim(strtolower($tagcode))) {
                    case "uservar":
                    case "authvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($authvar, $authvalue) = explode("=", $tagvalue);
                            if ($this->core->user->isAuth() && $this->core->user->getVar($authvar) == $authvalue)
                                $ret = true;
                        }
                        break;
                    case "confvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($confvar, $confvalue) = explode('=', $tagvalue,2);
                            if (strlen($confvar) && $this->get($confvar) == $confvalue)
                                $ret = true;
                        } elseif (strpos($tagvalue, '!=') !== false) {
                            list($confvar, $confvalue) = explode('!=', $tagvalue,2);
                            if (strlen($confvar) && $this->get($confvar) != $confvalue)
                                $ret = true;
                        }
                        break;
                    case "sessionvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($sessionvar, $sessionvalue) = explode("=", $tagvalue);
                            if (strlen($sessionvar) && $this->core->session->get($sessionvar) == $sessionvalue)
                                $ret = true;
                        }elseif (strpos($tagvalue, '!=') !== false) {
                            list($sessionvar, $sessionvalue) = explode("!=", $tagvalue);
                            if (strlen($sessionvar) && $this->core->session->get($sessionvar) != $sessionvalue)
                                $ret = true;
                        }
                        break;
                    case "servervar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($servervar, $servervalue) = explode("=", $tagvalue);
                            if (strlen($servervar) && $_SERVER[$servervar] == $servervalue)
                                $ret = true;
                        }elseif (strpos($tagvalue, '!=') !== false) {
                            list($servervar, $servervalue) = explode("!=", $tagvalue);
                            if (strlen($servervar) && $_SERVER[$servervar] != $servervalue)
                                $ret = true;
                        }
                        break;
                    case "auth":
                    case "noauth":
                        if (trim(strtolower($tagcode)) == 'auth')
                            $ret = $this->core->user->isAuth();
                        else
                            $ret = !$this->core->user->isAuth();
                        break;
                    case "development":
                        $ret = $this->core->is->development();
                        break;
                    case "production":
                        $ret = $this->core->is->production();
                        break;
                    case "indomain":
                    case "domain":
                        $domains = explode(",", $tagvalue);
                        foreach ($domains as $ind => $inddomain) if (strlen(trim($inddomain))) {
                            if (trim(strtolower($tagcode)) == "domain") {
                                if (strtolower($_SERVER['HTTP_HOST']) == strtolower(trim($inddomain)))
                                    $ret = true;
                            } else {
                                if (stripos($_SERVER['HTTP_HOST'], trim($inddomain)) !== false)
                                    $ret = true;
                            }
                        }
                        break;
                    case "interminal":
                        $ret = $this->core->is->terminal();
                        break;
                    case "url":
                    case "noturl":
                        $urls = explode(",", $tagvalue);

                        // If noturl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "noturl") $ret = true;
                        foreach ($urls as $ind => $url) if (strlen(trim($url))) {
                            if (trim(strtolower($tagcode)) == "url") {
                                if (($this->core->system->url['url'] == trim($url)))
                                    $ret = true;
                            } else {
                                if (($this->core->system->url['url'] == trim($url)))
                                    $ret = false;
                            }
                        }
                        break;
                    case "inurl":
                    case "notinurl":
                        $urls = explode(",", $tagvalue);

                        // If notinurl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "notinurl") $ret = true;
                        foreach ($urls as $ind => $inurl) if (strlen(trim($inurl))) {
                            if (trim(strtolower($tagcode)) == "inurl") {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                    $ret = true;
                            } else {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                    $ret = false;
                            }
                        }
                        break;
                    case "beginurl":
                    case "notbeginurl":
                        $urls = explode(",", $tagvalue);
                        // If notinurl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "notbeginurl") $ret = true;
                        foreach ($urls as $ind => $inurl) if (strlen(trim($inurl))) {
                            if (trim(strtolower($tagcode)) == "beginurl") {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) === 0))
                                    $ret = true;
                            } else {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) === 0))
                                    $ret = false;
                            }
                        }
                        break;
                    case "inmenupath":
                        $ret = $this->inMenuPath();
                        break;
                    case "notinmenupath":
                        $ret = !$this->inMenuPath();
                        break;
                    case "isversion":
                        if (trim(strtolower($tagvalue)) == 'core')
                            $ret = true;
                        break;
                    case "false":
                    case "true":
                        $ret = trim(strtolower($tagcode))=='true';
                        break;

                    default:
                        $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                        break;
                }
                // If I have found a true, break foreach
                if($ret) break;
            }
            return $ret;
        }

        /**
         * Evalue if the tag is a condition
         * @param $tag
         * @return bool
         */
        private function isAssignationTag($tag)
        {
            $tags = ["webapp", "set", "include", "redirect", "menu","coreversion"];
            return in_array(strtolower($tag), $tags);
        }

        /**
         * Execure an assigantion based on the tagcode
         * @param $tagcode string
         * @param $tagvalue string
         * @return bool
         */
        private function setAssignationTag($tagcode, $tagvalue, $vars)
        {
            // Asignation tags
            // -----------------
            switch (trim(strtolower($tagcode))) {
                case "webapp":
                    $this->set("webapp", $vars);
                    $this->core->setAppPath($vars);
                    break;
                case "set":
                    $this->set($tagvalue, $vars);
                    break;
                case "include":
                    // Recursive Call
                    $this->readConfigJSONFile($vars);
                    break;
                case "redirect":
                    // Array of redirections
                    if (!$this->core->is->terminal()) {
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

                case "menu":
                    if (is_array($vars)) {
                        $vars = $this->convertTags($vars);
                        foreach ($vars as $key => $value) {
                            if (!empty($value['path']))
                                $this->pushMenu($value);
                            else {
                                $this->core->logs->add('wrong menu format. Missing path element');
                                $this->core->logs->add($value);
                            }

                        }
                    } else {
                        $this->core->errors->add("menu: tag does not contain an array");
                    }
                    break;
                case "coreversion":
                    if($this->core->_version!= $vars) {
                        die("config var 'CoreVersion' is '{$vars}' and the current cloudframework version is {$this->core->_version}. Please update the framework");
                    }
                    break;
                default:
                    $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                    break;
            }
        }

        /**
         * Convert tags inside a string or object
         * @param $data mixed
         * @return mixed|string
         */
        private function convertTags($data)
        {
            $_array = is_array($data);

            // Convert into string if we received an array
            if ($_array) $data = json_encode($data);
            // Tags Conversions
            $data = str_replace('{{rootPath}}', $this->core->system->root_path, $data);
            $data = str_replace('{{appPath}}', $this->core->system->app_path, $data);
            $data = str_replace('{{lang}}', $this->lang, $data);
            while (strpos($data, '{{confVar:') !== false) {
                list($foo, $var) = explode("{{confVar:", $data, 2);
                list($var, $foo) = explode("}}", $var, 2);
                $data = str_replace('{{confVar:' . $var . '}}', $this->get(trim($var)), $data);
            }
            // Convert into array if we received an array
            if ($_array) $data = json_decode($data, true);
            return $data;
        }

    }

    /**
     * Class to manage the access security
     * @package Core
     */
    class CoreSecurity
    {
        private $core;
        /* @var $dsToken DataStore */
        private $dsToken = null;

        function __construct(Core &$core)
        {
            $this->core = $core;

        }

        /*
         * BASIC AUTH
         */
        function existBasicAuth()
        {
            return (isset($_SERVER['PHP_AUTH_USER']) && strlen($_SERVER['PHP_AUTH_USER'])
                || (isset($_SERVER['HTTP_AUTHORIZATION']) && strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic') === 0)
            );
        }

        function getBasicAuth()
        {
            $username = null;
            $password = null;
            // mod_php
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $username = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];
                // most other servers
            } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic') === 0)
                    list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
            return ([$username, $password]);
        }

        function checkBasicAuth($user, $passw)
        {
            list($username, $password) = $this->getBasicAuth();
            return (!is_null($username) && $user == $username && $passw == $password);
        }

        function existBasicAuthConfig()
        {
            return is_array($this->core->config->get('authorizations'));
        }

        function checkBasicAuthWithConfig()
        {
            $ret = false;
            list($user, $passw) = $this->getBasicAuth();

            if ($user === null) {
                $this->core->logs->add('checkBasicAuthWithConfig: No Authorization in headers ');
            } elseif (!is_array($auth = $this->core->config->get('authorizations'))) {
                $this->core->logs->add('checkBasicAuthWithConfig: no "authorizations" array in config. ');
            } elseif (!isset($auth[$user])) {
                $this->core->logs->add('checkBasicAuthWithConfig: key  does not match in "authorizations"');
            } elseif (!$this->core->system->checkPassword($passw, ((isset($auth[$user]['password']) ? $auth[$user]['password'] : '')))) {
                $this->core->logs->add('checkBasicAuthWithConfig: password does not match in "authorizations"');

                // User and password match!!!
            } else {
                $ret = true;
                // IPs Security
                if (isset($auth[$user]['ips']) && strlen($auth[$user]['ips'])) {
                    if (!($ret = $this->checkIPs($auth[$user]['ips']))) {
                        $this->core->logs->add('checkBasicAuthWithConfig: IP "' . $this->core->system->ip . '" not allowed');
                    }
                }
            }

            // Return the array of elements it passed
            if ($ret) {
                $auth[$user]['_BasicAuthUser_'] = $user;
                $ret = $auth[$user];
            }
            return $ret;
        }

        /*
         * API KEY
         */
        function existWebKey()
        {
            return (isset($_GET['web_key']) || isset($_POST['web_key']) || strlen($this->getHeader('X-WEB-KEY')));
        }

        function getWebKey()
        {
            if (isset($_GET['web_key'])) return $_GET['web_key'];
            else if (isset($_POST['web_key'])) return $_POST['web_key'];
            else if (strlen($this->getHeader('X-WEB-KEY'))) return $this->getHeader('X-WEB-KEY');
            else return '';
        }

        function checkWebKey($keys = null)
        {

            // If I don't have the credentials in keys I try to check if CLOUDFRAMEWORK-WEB-KEYS is defined.
            if (null === $keys) {
                $keys = $this->core->config->get('CLOUDFRAMEWORK-WEB-KEYS');
                if (!is_array($keys)) return false;
            }

            // Analyzing $keys
            if (!is_array($keys)) $keys = [[$keys, '*']];
            else if (!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getWebKey();

            if (strlen($web_key))
                foreach ($keys as $key) {
                    if ($key[0] == $web_key) {
                        if (!isset($key[1])) $key[1] = "*";
                        if ($key[1] == '*') return $key;
                        elseif (!strlen($_SERVER['HTTP_ORIGIN'])) return false;
                        else {
                            $allows = explode(',', $key[1]);
                            foreach ($allows as $host) {
                                if (preg_match('/^.*' . trim($host) . '.*$/', $_SERVER['HTTP_ORIGIN']) > 0) return $key;
                            }
                            return false;
                        }
                    }
                }
            return false;
        }

        function existServerKey()
        {
            return (strlen($this->getHeader('X-SERVER-KEY')) > 0);
        }

        function getServerKey()
        {
            return $this->getHeader('X-SERVER-KEY');
        }

        function checkServerKey($keys=null)
        {
            // If I don't have the credentials in keys I try to check if CLOUDFRAMEWORK-SERVER-KEYS is defined.
            if (null === $keys) {
                $keys = $this->core->config->get('CLOUDFRAMEWORK-SERVER-KEYS');
                if (!is_array($keys)) return false;
            }


            if (!is_array($keys)) $keys = [[$keys, '*']];
            else if (!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getServerKey();

            if (strlen($web_key))
                foreach ($keys as $key) {
                    if ($key[0] == $web_key) {

                        if (!isset($key[1])) $key[1] = "*";
                        if ($key[1] == '*') return $key;
                        else return $this->checkIPs($key[1]);
                    }
                }
            return false;
        }


        /**
         * @param array|string $allows string to compre with the current IP
         * @return bool
         */
        private function checkIPs($allows)
        {
            if (is_string($allows)) $allows = explode(',', $allows);
            foreach ($allows as $host) {
                $host = trim($host);
                if ($host == '*' || preg_match('/^.*' . $host . '.*$/', $this->core->system->ip) > 0) return true;
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

        function getCloudFrameWorkSecurityInfo($maxSeconds = 0, $id = '', $secret = '')
        {
            $info = $this->checkCloudFrameWorkSecurity($maxSeconds, $id, $secret);
            if (false === $info) return [];
            else {
                return $this->core->config->get('CLOUDFRAMEWORK-ID-' . $info['SECURITY-ID']);
            }

        }

        // time, has to to be microtime().
        function generateCloudFrameWorkSecurityString($id = '', $time = '', $secret = '')
        {
            if (!strlen($id)) {
                $id = $this->core->config->get('CloudServiceId');
                if (!strlen($id)) {
                    $this->core->errors->add('generateCloudFrameWorkSecurityString has not received $id and CloudServiceId config var does not exist');
                    return false;
                }
            }

            if (!strlen($secret)) {
                $secret = $this->core->config->get('CloudServiceSecret');
                if (!strlen($secret)) {
                    $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                    if (isset($secArr['secret'])) $secret = $secArr['secret'];
                    if (!strlen($secret)) {
                        $this->core->errors->add('generateCloudFrameWorkSecurityString has not received $secret and CloudServiceSecret and CLOUDFRAMEWORK-ID-XXX   config vars don\'t not exist');
                        return false;
                    }
                }
            }

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

        private function createDSToken()
        {
            $dschema = ['token' => ['keyname', 'index']];
            $dschema['dateInsert'] = ['datetime', 'index'];
            $dschema['JSONZIP'] = ['string'];
            $dschema['fingerprint'] = ['string'];
            $dschema['prefix'] = ['string', 'index'];
            $dschema['secondsToExpire'] = ['integer'];
            $dschema['status'] = ['integer','index'];
            $spacename = $this->core->config->get('DataStoreSpaceName');
            if (!strlen($spacename)) $spacename = "cloudframework";
            $this->dsToken = $this->core->loadClass('DataStore', ['CloudFrameWorkAuthTokens', $spacename, $dschema]);
            if ($this->dsToken->error) $this->core->errors->add(['setDSToken' => $this->dsToken->errorMsg]);
            return(!$this->dsToken->error);

        }

        /**
         * @param $token          Id generated with setDSToken
         * @param string $prefix Prefix to separate tokens Between apps
         * @param int $time MAX TIME to expire the token
         * @return array|mixed    The content contained in DS.JSONZIP
         */
        function getDSToken($token, $prefixStarts = '', $time = 0, $fingerprint_hash='')
        {
            $ret = null;

            // Check if token starts with $prefix
            if (strlen($prefixStarts) && strpos($token, $prefixStarts) !== 0) {
                $this->core->errors->add(['getDSToken' => 'incorrect prefix token']);
                return $ret;
            }
            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;


            $retToken = $this->dsToken->fetchByKeys($token);
            // Allow to rewrite the fingerprint it it is passed
            if (!$this->dsToken->error && !strlen($fingerprint_hash)) $fingerprint_hash = $this->core->system->getRequestFingerPrint()['hash'];

            if ($this->dsToken->error) {
                $this->core->errors->add(['getDSToken' => $this->dsToken->errorMsg]);
            } elseif (!count($retToken)) {
                $this->core->errors->add(['getDSToken' => 'Token not found.']);
            } elseif (!$retToken[0]['status']) {
                $this->core->errors->add(['getDSToken' => 'Token is no longer active.']);
            } elseif ($fingerprint_hash != $retToken[0]['fingerprint']) {
                $this->core->errors->add(['getDSToken' => 'Token fingerprint does not match. Security violation.']);
            } elseif ($time > 0 && ((new DateTime())->getTimestamp()) - (new DateTime($retToken[0]['dateInsert']))->getTimestamp() >= $time) {
                $this->core->errors->add(['getDSToken' => 'Token expired']);
            } elseif (isset($retToken[0]['JSONZIP'])) {
                $ret = json_decode(gzuncompress(utf8_decode($retToken[0]['JSONZIP'])), true);
            }

            return $ret;
        }

        /**
         * Delete the entity with KeyName=$token
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function deleteDSToken($token)
        {
            $ret = null;

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            // Return deleted records
            return($this->dsToken->deleteByKeys($token));
        }

        /**
         * Delete phically a token taking the $token key name.
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function updateDSToken($token,$data)
        {
            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            $retToken = $this->dsToken->fetchByKeys($token);
            if(count($retToken)) {
                $retToken[0]['JSONZIP'] = utf8_encode(gzcompress(json_encode($data)));
                $ret = $this->dsToken->createEntities($retToken[0]);
                if(count($ret)) {
                    return(json_decode(gzuncompress(utf8_decode($ret[0]['JSONZIP'])), true));
                }
            }
            return [];

        }

        /**
         * Change the status = 0
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function deactivateDSToken($token)
        {
            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            $retToken = $this->dsToken->fetchByKeys($token);
            if(count($retToken)) {
                $retToken[0]['status'] = 0;
                $ret = $this->dsToken->createEntities($retToken[0]);
                if(count($ret)) {
                    return(true);
                }
            }
            return false;

        }

        function setDSToken($data, $prefix = '', $fingerprint_hash = '',$time_expiration=0)
        {
            $ret = null;
            if (!strlen(trim($prefix))) $prefix = 'default';

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            // If not error continue
            if (!$this->core->errors->lines) {
                if (!strlen($fingerprint_hash)) $fingerprint_hash = $this->core->system->getRequestFingerPrint()['hash'];
                $record['dateInsert'] = "now";
                $record['fingerprint'] = $fingerprint_hash;
                $record['JSONZIP'] = utf8_encode(gzcompress(json_encode($data)));
                $record['prefix'] = $prefix;
                $record['secondsToExpire'] = $time_expiration;
                $record['status'] = 1;
                $record['token'] = $this->core->config->get('DataStoreSpaceName') . '__' . $prefix . '__' . sha1(json_encode($record) . date('Ymdhis'));

                $retEntity = $this->dsToken->createEntities($record);
                if ($this->dsToken->error) {
                    $this->core->errors->add(['setDSToken' => $this->dsToken->errorMsg]);
                } else {
                    $ret = $retEntity[0]['KeyName'];
                }
            }
            return $ret;

        }

        function encrypt($text) {
            $key = ($this->core->config->get('EncryptPassword'))?:'XWER$T;(6tg';
            $iv = mcrypt_create_iv(
                mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC),
                MCRYPT_DEV_URANDOM
            );

            return  base64_encode(
                $iv .
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_128,
                    hash('sha256', $key, true),
                    $text,
                    MCRYPT_MODE_CBC,
                    $iv
                )
            );
        }

        function decrypt($text) {

            if(strlen($text)<22) return null;

            $key = ($this->core->config->get('EncryptPassword'))?:'XWER$T;(6tg';
            $data = base64_decode($text);
            $iv = substr($data, 0, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC));

            return rtrim(
                mcrypt_decrypt(
                    MCRYPT_RIJNDAEL_128,
                    hash('sha256', $key, true),
                    substr($data, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC)),
                    MCRYPT_MODE_CBC,
                    $iv
                ),
                "\0"
            );
        }
    }

    /**
     * Class to manage localizations
     * @package Core
     */
    Class CoreLocalization
    {
        protected $core;
        var $data = [];
        var $wapploca = [];
        var $files_readed = [];
        private $init = false;

        function __construct(Core &$core)
        {
            $this->core = $core;

        }

        function init() {

            // Maintain compatibilty with old variable
            if (!strlen($this->core->config->get('localizeCachePath')) && strlen($this->core->config->get('LocalizePath')))
                $this->core->config->set('localizeCachePath', $this->core->config->get('LocalizePath'));

            // Read from Cache last Dics
            if (!isset($_GET['_reloadDics']) && !isset($_GET['_nocacheDics'])) {
                $this->data = $this->core->cache->get('Core:Localization:Data');
                if (!is_array($this->data)) $this->data = [];
            }

            // $this->core->config->get('wapploca_cache_expiration_time') default 3600
            if ($this->core->config->get('WAPPLOCA') && !isset($_GET['_nocacheDics'])) {
                $this->wapploca = $this->core->cache->get('Core:Localization:WAPPLOCA', $this->core->config->get('wapploca_cache_expiration_time'));
                if (!is_array($this->wapploca)) $this->wapploca = [];
            }

            $this->init = true;
        }

        /**
         * Get a Localization code from a localization file
         * @param $locFile
         * @param $code
         * @param array $config
         * @return mixed|string
         */
        function get($locFile, $code, $config = [])
        {
            if(!$this->init) $this->init();

            // Check syntax of $locFile & $code
            if (!$this->checkLocFileAndCode($locFile, $code)) return 'Err in: [' . $locFile . "{{$code}}" . ']';
            $lang = $this->core->config->getLang();
            if (isset($config['lang']) && strlen($config['lang']) == 2) $lang = $config['lang'];
            // The $locFile does not exist
            if (!isset($_GET['_debugDics'])) {

                // Trying read from file
                if (!isset($this->data[$locFile][$lang]) && !isset($this->files_readed[$locFile][$lang])  ) {
                    $this->readFromFile($locFile, $lang);
                }

                // Trying read from WAPPLOCA
                $wapploca_readed = false;
                if ($this->core->config->get('WAPPLOCA') && !isset($this->data[$locFile][$lang])) {
                    if ($this->readFromWAPPLOCA($locFile, $code, $lang) && isset($this->data[$locFile][$lang][$code])) {
                        // Writting Local file.
                        $this->writeLocalization($locFile, $lang);
                        $this->files_readed[$locFile][$lang] = true;

                    }
                }

                // If this localization file exists but the $code does not exist because the cache
                if (isset($this->data[$locFile][$lang]) && !isset($this->data[$locFile][$lang][$code]) && !isset($this->files_readed[$locFile][$lang]) ) {
                    // $this->readFromFile($locFile, $lang);
                }
            }

            if (isset($_GET['_debugDics'])) {
                $ret = $lang . '_' . $locFile . ":({$code})";
            } else if (isset($this->data[$locFile][$lang][$code])) {
                $ret = $this->data[$locFile][$lang][$code];
            } else {
                $this->core->logs->add("Missing configuration for Localizations: {$locFile}-{$lang}-{$code}");
                $this->core->logs->add('WAPPLOCA: ' . ((empty($this->core->config->get('WAPPLOCA'))) ? 'empty' : '***'));
                $this->core->logs->add('localizeCachePath: ' . ((empty($this->core->config->get('localizeCachePath'))) ? 'empty' : '***'));
            }

            return $ret;
        }

        /**
         * Set a Localization code
         * @param $locFile
         * @param $code
         * @param $content
         * @param array $config
         * @return mixed|string
         */
        function set($locFile, $code, $content,$config = [])
        {
            if(!$this->init) $this->init();

            // Check syntax of $locFile & $code
            if (!$this->checkLocFileAndCode($locFile, $code)) return 'Err in: [' . $locFile . "{{$code}}" . ']';
            if (isset($config['lang']) && strlen($config['lang']) == 2) $lang = $config['lang'];

            if (!isset($this->data[$locFile][$lang]) || !isset($this->data[$locFile][$lang][$code]) || $this->data[$locFile][$lang][$code]!=$content) {
                $this->data[$locFile][$lang][$code]=$content;
            }
        }


        /**
         * Read from a file the localizations and store the content into: $this->data[$locFile][$lang]
         * @param $locFile
         * @return bool
         */
        private function readFromFile($locFile, $lang = '')
        {
            if(!$this->init) $this->init();

            if (!strlen($this->core->config->get('localizeCachePath'))) return false;
            if (!strlen($lang)) $lang = $this->core->config->getLang();
            $ok = true;
            $this->core->__p->add('Localization->readFromFile: ', strtoupper($lang)."-{$locFile}", 'note');
            // First read from local directory if {{localizeCachePath}} is defined.
            $this->files_readed[$locFile][$lang] = true;
            if (strlen($this->core->config->get('localizeCachePath'))) {
                $filename = $this->core->config->get('localizeCachePath') . '/' . strtoupper($lang) . '_Core_' . $locFile . '.json';
                try {
                    $ret = @file_get_contents($filename);
                    if ($ret !== false) {
                        $this->data[$locFile][$lang] = json_decode($ret, true);
                        $this->core->cache->set('Core:Localization:Data', $this->data);
                        $this->core->__p->add('Success reading ' . strtoupper($lang) . '_Core_' . $locFile . '.json');

                    } else {
                        $this->core->__p->add('Error reading ' .  strtoupper($lang) . '_Core_' . $locFile . '.json');
                        $this->core->logs->add('Error reading ' . $filename);
                        $this->core->logs->add(error_get_last());
                        $ok = false;
                    }
                } catch (Exception $e) {
                    $ok = false;
                    $this->core->logs->add('Error reading ' . $filename . ': ');
                    $this->core->logs->add($e->getMessage() . ' ' . error_get_last());
                }
            }
            $this->core->__p->add('Localization->readFromFile: ', '', 'endnote');
            return $ok;
        }

        public function writeLocalization($locFile, $lang = '')
        {
            if(!$this->init) $this->init();


            if (!strlen($this->core->config->get('localizeCachePath'))) return false;
            if (!isset($this->data[$locFile])) return false;
            if (!strlen($lang)) $lang = $this->core->config->getLang();
            $ok = true;
            $this->core->__p->add('Localization->writeLocalization: ', strtoupper($lang) . '_Core_' . $locFile . '.json', 'note');

            $filename = $this->core->config->get('localizeCachePath') . '/' . strtoupper($lang) . '_Core_' . $locFile . '.json';
            try {
                $ret = @file_put_contents($filename, json_encode($this->data[$locFile][$lang], JSON_PRETTY_PRINT));
                if ($ret === false) {
                    $ok = false;
                    $this->core->__p->add('Error writting ' . strtoupper($lang) . '_Core_' . $locFile . '.json');
                    $this->core->logs->add('Error writting ' . $filename);
                    $this->core->logs->add(error_get_last());
                } else {
                    $this->core->__p->add('Success Writting ' . strtoupper($lang) . '_Core_' . $locFile . '.json');
                    $this->core->cache->set('Core:Localization:Data', $this->data);
                }
            } catch (Exception $e) {
                $ok = false;
                $this->core->logs->add('Error reading ' . $filename . ': ');
                $this->core->logs->add($e->getMessage() . ' ' . error_get_last());
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
        private function readFromWAPPLOCA($locFile, $code, $lang = '')
        {
            if(!$this->init) $this->init();

            if (empty($this->core->config->get('WAPPLOCA'))) return false;
            if (!strlen($lang)) $lang = $this->core->config->getLang();

            list($org, $app, $cat, $loc_code) = explode(';', $code, 4);
            if (!strlen($loc_code) || preg_match('/[^a-z0-9_-]/', $loc_code)) {
                $this->core->logs->add('Localization->readFromWAPPLOCA: has a wrong value for $code: ' . $code);
                return false;
            }
            $ok = true;
            $lang = strtoupper($lang);
            $key = "$org/$app/$cat?lang=" . $lang;
            // Read From CloudService the info and put the cats into $this->wapploca
            $url = $this->core->config->get('wapploca_api_url');
            if (!isset($this->wapploca[$key][$lang])) {
                $ret = $this->core->request->get($url . '/dics/' . $key);
                if (!$this->core->request->error) {
                    $ret = json_decode($ret, true);
                    if (!$ret['success']) {
                        $this->core->logs->add($ret);
                        $ok = false;
                    } else {
                        if (is_array($ret['data'])) {
                            $this->wapploca[$key] = $ret['data'];
                            $this->core->cache->set('Core:Localization:WAPPLOCA', $this->wapploca);
                        } else {
                            $this->core->logs->add('WAPPLOCA return data is not an array');
                            $this->core->logs->add($ret);
                        }
                    }
                } else $ok = false;
            }


            // Return the code required
            if (isset($this->wapploca[$key][$lang][$code]))
                $this->data[$locFile][$lang][$code] = $this->wapploca[$key][$lang][$code];
            else
                $this->data[$locFile][$lang][$code] = $code;
            return $ok;
        }


        /**
         * Check the formats for locaLizationFile and codes
         * @param $locFile
         * @param $code
         * @return bool
         */
        private function checkLocFileAndCode(&$locFile, &$code)
        {
            if(!$this->init) $this->init();

            $locFile = preg_replace('/[^A-z_\-]/', '', $locFile);
            if (!strlen($locFile)) {
                $this->core->errors->set('Localization has received a wrong spacename: ');
                return false;
            }
            $code = preg_replace('/[^a-z0-9_\-;]/', '', strtolower($code));
            if (!strlen($code)) {
                $this->core->errors->set('Localization has received a wrong code: ');
                return false;
            }
            return true;
        }
    }

    /**
     * Class to manage HTTP requests
     * @package Core
     */
    Class CoreRequest
    {
        protected $core;
        protected $http;
        public $responseHeaders;
        public $error = false;
        public $errorMsg = [];
        private $curl = [];
        var $rawResult = '';
        var $automaticHeaders = true; // Add automatically the following headers if exist on config: X-CLOUDFRAMEWORK-SECURITY, X-SERVER-KEY, X-SERVER-KEY, X-DS-TOKEN,X-EXTRA-INFO

        function __construct(Core &$core)
        {
            $this->core = $core;
            if (!$this->core->config->get("CloudServiceUrl"))
                $this->core->config->set("CloudServiceUrl", 'https://cloudframework.io/h/api');

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
                    $this->core->config->set("CloudServiceUrl", 'https://cloudframework.io/h/api');

                $this->http = $this->core->config->get("CloudServiceUrl");

                if (strlen($path) && $path[0] != '/')
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
            if (isset($_GET['refreshCache']) || $ret === false || $ret === null) {
                $ret = $this->get($rute, $data, $extraheaders, $raw);
                // Only cache successful responses.
                if (is_array($this->responseHeaders) && isset($headers[0]) && strpos($headers[0], 'OK')) {
                    $this->core->cache->set($_qHash, $ret);
                }
            }
            return ($ret);
        }

        function getCurl($rute, $data = null, $verb = 'GET', $extra_headers = null, $raw = false)
        {
            $this->core->__p->add('Request->getCurl: ', "$rute " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $rute = $this->getServiceUrl($rute);
            $this->responseHeaders = null;
            $options['http']['header'] = ['Connection: close', 'Expect:', 'ACCEPT:']; // improve perfomance and avoid 100 HTTP Header


            // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
            if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                $options['http']['header'][] = 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret"));

            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    $options['http']['header'][] .= $key . ': ' . $value;
                }
            }

            # Content-type for something different than get.
            if ($verb != 'GET') {
                if (stripos(json_encode($options['http']['header']), 'Content-type') === false) {
                    if ($raw) {
                        $options['http']['header'][] = 'Content-type: application/json';
                    } else {
                        $options['http']['header'][] = 'Content-type: application/x-www-form-urlencoded';
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
                    // $options['http']['header'][] = sprintf('Content-Length: %d', strlen($build_data));
                }
            }

            $curl_options = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,            // return headers
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $options['http']['header'],
                CURLOPT_CUSTOMREQUEST => $verb

            ];
            // Appengine  workaround
            // $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
            // $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
            // Download https://pki.google.com/GIAG2.crt
            // openssl x509 -in GIAG2.crt -inform DER -out google.pem -outform PEM
            // $curl_options[CURLOPT_CAINFO] =__DIR__.'/google.pem';

            if (isset($options['http']['content'])) {
                $curl_options[CURLOPT_POSTFIELDS] = $options['http']['content'];
            }

            // Cache
            $ch = curl_init($rute);
            curl_setopt_array($ch, $curl_options);
            $ret = curl_exec($ch);

            if (!curl_errno($ch)) {
                $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $this->responseHeaders = substr($ret, 0, $header_len);
                $ret = substr($ret, $header_len);
            } else {
                $this->addError(error_get_last());
                $this->addError([('Curl error ' . curl_errno($ch)) => curl_error($ch)]);
                $this->addError(['Curl url' => $rute]);
                $ret = false;
            }
            curl_close($ch);

            $this->core->__p->add('Request->getCurl: ', '', 'endnote');
            return $ret;


        }


        function get_json_decode($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->get($rute, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        function post_json_decode($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->post($rute, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        function put_json_decode($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->put($rute, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        function get($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($rute, $data, 'GET', $extra_headers, $send_in_json);
        }

        function post($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($rute, $data, 'POST', $extra_headers, $send_in_json);
        }

        function put($rute, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($rute, $data, 'PUT', $extra_headers, $send_in_json);
        }

        function delete($rute, $extra_headers = null)
        {
            return $this->call($rute, null, 'DELETE', $extra_headers);
        }

        function call($rute, $data = null, $verb = 'GET', $extra_headers = null, $raw = false)
        {
            $rute = $this->getServiceUrl($rute);
            $this->responseHeaders = null;

            syslog(LOG_INFO,"request {$verb} {$rute} ".(($data === null) ? '{no params}' : '{with params}'));

            $this->core->__p->add("Request->{$verb}: ", "$rute " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            // Performance for connections
            $options = array('ssl' => array('verify_peer' => false));
            $options['http']['ignore_errors'] = '1';
            $options['http']['header'] = 'Connection: close' . "\r\n";

            if($this->automaticHeaders) {
                // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
                if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                    $options['http']['header'] .= 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret")) . "\r\n";

                // Add Server Key if we have it.
                if (strlen($this->core->config->get("CloudServerKey")))
                    $options['http']['header'] .= 'X-SERVER-KEY: ' . $this->core->config->get("CloudServerKey") . "\r\n";

                // Add Server Key if we have it.
                if (strlen($this->core->config->get("X-DS-TOKEN")))
                    $options['http']['header'] .= 'X-DS-TOKEN: ' . $this->core->config->get("X-DS-TOKEN") . "\r\n";

                if (strlen($this->core->config->get("X-EXTRA-INFO")))
                    $options['http']['header'] .= 'X-EXTRA-INFO: ' . $this->core->config->get("X-EXTRA-INFO") . "\r\n";
            }
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
            if (is_array($data)) {
                if ($verb == 'GET') {
                    if (is_array($data)) {
                        if (strpos($rute, '?') === false) $rute .= '?';
                        else $rute .= '&';
                        foreach ($data as $key => $value) {
                            if (is_array($value)) {
                                foreach ($value as $item) {
                                    $rute .= $key . '=' . rawurlencode($item) . '&';
                                }
                            } else {
                                $rute .= $key . '=' . rawurlencode($value) . '&';
                            }
                        }
                    }
                } else {
                    if ($raw) {
                        if (stripos($options['http']['header'], 'application/json') !== false) {
                            $build_data = json_encode($data);
                        } else
                            $build_data = $data;
                    } else {
                        $build_data = http_build_query($data);
                    }
                    $options['http']['content'] = $build_data;

                    // You have to calculate the Content-Length to run as script
                    //$options['http']['header'] .= sprintf('Content-Length: %d', strlen($build_data)) . "\r\n";
                }
            }
            // Take data as a valid JSON
            elseif(is_string($data)) {
                if(is_array(json_decode($data,true))) $options['http']['content'] = $data;
            }

            // Context creation
            $context = stream_context_create($options);

            try {
                $ret = @file_get_contents($rute, false, $context);

                // Return response headers
                if(isset($http_response_header)) $this->responseHeaders = $http_response_header;
                else $this->responseHeaders = ['$http_response_header'=>'undefined'];

                // If we have an error
                if ($ret === false)
                    $this->addError(error_get_last());
                else {
                    $code = $this->getLastResponseCode();
                    if ($code === null) {
                        $this->addError('Return header not found');
                        $this->addError($this->responseHeaders);
                        $this->addError($ret);
                    } else {
                        if ($code >= 400) {
                            $this->addError('Error code returned: ' . $code);
                            $this->addError($this->responseHeaders);
                            $this->addError($ret);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->addError(error_get_last());
                $this->addError($e->getMessage());
            }

            syslog(($this->error)?LOG_DEBUG:LOG_INFO,"end request {$verb} {$rute} ".(($data === null) ? '{no params}' : '{with params}'));

            $this->core->__p->add("Request->{$verb}: ", '', 'endnote');
            return ($ret);
        }

        function getLastResponseCode()
        {
            $code = null;
            if (isset($this->responseHeaders[0])) {
                list($foo, $code, $foo) = explode(' ', $this->responseHeaders[0], 3);
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
        /**
         * @param string $url
         * @param int $format
         * @desc Fetches all the headers
         * @return array
         */
        function getUrlHeaders($url,$format=0)
        {
            $url_info=parse_url($url);
            if (isset($url_info['scheme']) && $url_info['scheme'] == 'https') {
                $port = 443;
                $fp= @fsockopen('ssl://'.$url_info['host'], $port, $errno, $errstr, 30);
            } else {
                $port = isset($url_info['port']) ? $url_info['port'] : 80;
                $fp = @fsockopen($url_info['host'], $port, $errno, $errstr, 30);

            }
            if($fp)
            {
                $head = "HEAD ".@$url_info['path']."?".@$url_info['query']." HTTP/1.0\r\nHost: ".@$url_info['host']."\r\n\r\n";
                fputs($fp, $head);
                while(!feof($fp))
                {
                    if($header=trim(fgets($fp, 1024)))
                    {
                        if($format == 1)
                        {
                            $key = array_shift(explode(':',$header));
                            // the first element is the http header type, such as HTTP 200 OK,
                            // it doesn't have a separate name, so we have to check for it.
                            if($key == $header)
                            {
                                $headers[] = $header;
                            }
                            else
                            {
                                $headers[$key]=substr($header,strlen($key)+2);
                            }
                            unset($key);
                        }
                        else
                        {
                            $headers[] = $header;
                        }
                    }
                }
                fclose($fp);
                return $headers;
            }
            else
            {
                $this->core->errors->add(error_get_last());
                return false;
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
        }
        public function getResponseHeader($key) {

            if(is_array($this->responseHeaders))
                foreach ($this->responseHeaders as $responseHeader)
                    if(strpos($responseHeader,$key)!==false) {
                        list($header_key,$content) = explode(':',$responseHeader,2);
                        $content = trim($content);
                        return $content;
                    }
            return null;
        }

        function sendLog($type, $cat, $subcat, $title, $text = '', $email = '', $app = '', $interactive = false)
        {

            if (!strlen($app)) $app = $this->core->system->url['host'];

            $this->core->logs->add(['sending cloud service logs:' => [$this->getServiceUrl('queue/cf_logs/' . $app), $type, $cat, $subcat, $title]]);
            if (!$this->core->config->get('CloudServiceLog') && !$this->core->config->get('LogPath')) return false;
            $app = str_replace(' ', '_', $app);
            $params['id'] = $this->core->config->get('CloudServiceId');
            $params['cat'] = $cat;
            $params['subcat'] = $subcat;
            $params['title'] = $title;
            if (!is_string($text)) $text = json_encode($text);
            $params['text'] = $text . ((strlen($text)) ? "\n\n" : '');
            if ($this->core->errors->lines) $params['text'] .= "Errors: " . json_encode($this->core->errors->data, JSON_PRETTY_PRINT) . "\n\n";
            if (count($this->core->logs->lines)) $params['text'] .= "Logs: " . json_encode($this->core->logs->data, JSON_PRETTY_PRINT);

            // IP gathered from queue
            if (isset($_REQUEST['cloudframework_queued_ip']))
                $params['ip'] = $_REQUEST['cloudframework_queued_ip'];
            else
                $params['ip'] = $this->core->system->ip;

            // IP gathered from queue
            if (isset($_REQUEST['cloudframework_queued_fingerprint']))
                $params['fingerprint'] = $_REQUEST['cloudframework_queued_fingerprint'];
            else
                $params['fingerprint'] = json_encode($this->core->system->getRequestFingerPrint(), JSON_PRETTY_PRINT);

            // Tell the service to send email of the report.
            if (strlen($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
                $params['email'] = $email;
            if ($this->core->config->get('CloudServiceLog')) {
                $ret = $this->core->jsonDecode($this->get('queue/cf_logs/' . urlencode($app) . '/' . urlencode($type), $params, 'POST'), true);
                if (is_array($ret) && !$ret['success']) $this->addError($ret);
            } else {
                $ret = 'Sending to LogPath not yet implemented';
            }
            return $ret;
        }
    }


    /**
     * Class to manage HTTP requests
     * @package Core
     */
    Class CoreModel
    {
        var $error = false;
        var $errorMsg = null;
        /** @var CloudSQL $db  */
        var $db = null;

        protected $core;
        var $models = null;

        function __construct(Core &$core)
        {
            $this->core = $core;
        }

        function readModels($path) {

            try {
                $data = json_decode(@file_get_contents($path), true);

                if (!is_array($data)) {
                    $this->addError('error reading ' . $path);
                    if (json_last_error())
                        $this->addError("Wrong format of json: " . $path);
                    elseif (!empty(error_get_last()))
                        $this->addError(error_get_last());
                    return false;
                } else {
                    $this->processModels($data);
                    return true;
                }
            } catch (Exception $e) {
                $this->addError(error_get_last());
                $this->addError($e->getMessage());
                return false;
            }

        }

        public function processModels($models) {
            if(is_array($models['DataBaseTables']))
                foreach ($models['DataBaseTables'] as $model=>$dataBaseTable) {
                    $this->models['db:'.$model] = ['type'=>'db','data'=>$dataBaseTable];
                }
            foreach ($models['DataStoreEntities'] as $model=>$dsEntity) {
                $this->models['ds:'.$model] = ['type'=>'ds','data'=>$dsEntity];
            }
        }

        /**
         * @param string $model         We expect a '(db|ds):model_name' or just 'model_name'
         * @return mixed|null|void
         */
        public function getModelObject($model) {

            // If the model does not include the '(ds|db):' we add it.
            if(!strpos($model,':')) {
                if(isset($this->models['db:'.$model])) $model = 'db:'.$model;
                else $type = 'ds:'.$model;
            }

            // Let's find it and return
            if(!isset($this->models[$model])) return($this->core->errors->add("Model $model does not exist"));
            switch ($this->models[$model]['type']) {
                case "db":
                    list($type,$table) = explode(':',$model,2);
                    if(!is_object($object = $this->core->loadClass('DataSQL',[$table,$this->models[$model]['data']]))) return;
                    return($object);
                    break;

                case "ds":
                    list($type,$entity) = explode(':',$model,2);
                    if(empty($this->core->config->get('DataStoreSpaceName'))) return($this->addError('Missing DataStoreSpaceName config var'));
                    if(!is_object($object = $this->core->loadClass('DataStore',[$entity,$this->core->config->get('DataStoreSpaceName'),$this->models[$model]['data']]))) return;
                    return($object);
                    break;
            }
            return null;
        }

        /**
         * Returns the array keys of the models
         * @return array
         */
        public function listmodels() {
            if(is_array($this->models)) return array_keys($this->models);
            else return [];
        }

        public function dbInit() {

            if(null === $this->db) {
                $this->core->model->db = $this->core->loadClass('CloudSQL');
                if(!$this->db->connect()) $this->addError($this->db->getError());
            }
            return !$this->db->error();
        }

        /**
         * Excute the query and return the result if there is no errors
         * @param $SQL
         * @param $params
         * @return array|void
         */
        public function dbQuery($title, $SQL, $params) {

            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query
            $this->core->logs->add($title,'dbQuery');
            $ret = $this->db->getDataFromQuery($SQL,$params);
            if($this->db->error()) return($this->addError($this->db->getError()));
            else return $ret;

        }

        /**
         * Update a record into the database
         * @param $title
         * @param $table
         * @param $data
         * @return bool|null|void
         */
        public function dbUpdate($title, $table, $data) {

            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query
            $this->core->logs->add($title,'dbUpdate');
            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            $this->db->cloudFrameWork('update',$data,$table);
            if($this->db->error()) return($this->addError($this->db->getError()));
            else return true;

        }

        public function dbClose() {
            if(is_object($this->db)) $this->db->close();
        }

        private function addError($msg) {
            $this->error = true;
            $this->errorMsg[] = $msg;
        }



    }

    /**
     * Class to be extended for the creation of a logic application.
     *
     * The sintax is: `Class Logic extends CoreLogic() {..}`
     *
     *
     * Normally your file has to be stored in the `logic/` directory and extend this class.
     * @package Core
     */
    Class CoreLogic
    {
        /** @var Core $core pointer to the Core class. `$this->core->...` */
        protected $core;

        /** @var string $method indicates the HTTP method used to access the script: GET, POST etc.. Default value is GET */
        var $method = 'GET';

        /** @var array $formParams Contains the variables passed in a GET,POST,PUT call intro an URL  */
        var $formParams = array();

        /** @var array $params contains the substrings paths of an URL script/param0/param1/..  */
        var $params = array();

        /**
         * @var boolean $error Indicates if an error has been produced
         */
        public $error = false;

        /** @var array $errorMsg Keep the error messages  */
        public $errorMsg = [];



        /**
         * CoreLogic constructor.
         * @param Core $core
         */
        function __construct(Core &$core)
        {
            // Singleton of core
            $this->core = $core;

            // Params
            $this->method = (strlen($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : 'GET';
            if ($this->method == 'GET') {
                $this->formParams = &$_GET;
                if (isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_GET['_raw_input_'], true)) : json_decode($_GET['_raw_input_'], true);
            } else {
                if (count($_GET)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParam, $_GET) : $_GET;
                if (count($_POST)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $_POST) : $_POST;
                // POST
                $raw = null;
                if(isset($_POST['_raw_input_']) && strlen($_POST['_raw_input_'])) $raw = json_decode($_POST['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;
                // GET
                $raw = null;
                if(isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $raw = json_decode($_GET['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;
                // RAW DATA
                $input = file_get_contents("php://input");
                if (strlen($input)) {
                    $this->formParams['_raw_input_'] = $input;

                    if (is_object(json_decode($input))) {
                        $input_array = json_decode($input, true);
                    } elseif(strpos($input,"\n") === false && strpos($input,"=")) {
                        parse_str($input, $input_array);
                    }

                    if (is_array($input_array)) {
                        $this->formParams = array_merge($this->formParams, $input_array);
                        unset($input_array);

                    }
                }
                // Trimming fields
                foreach ($this->formParams as $i=>$data) if(is_string($data)) $this->formParams[$i] = trim ($data);
            }

            $this->params = &$this->core->system->url['parts'];

        }

        /**
         * Try to render a template
         * @param string $template Path to the template
         */
        function render($template)
        {
            if(strpos($template,'.htm.twig')) {
                $template = str_replace('.htm.twig','',$template);
                /* @var $rtwig RenderTwig */
                $rtwig = $this->core->loadClass('RenderTwig');
                if(!$rtwig->error) {
                    $path = $this->core->system->app_path;
                    if($path[strlen($path)-1] != '/') $path.='/';
                    $rtwig->addFileTemplate($template,$path . 'templates/' . $template);
                    $rtwig->setTwig($template);
                    echo $rtwig->render();
                } else {
                    $this->addError($rtwig->errorMsg);
                }
            } else {
                try {
                    include $this->core->system->app_path . '/templates/' . $template;
                } catch (Exception $e) {
                    $this->addError(error_get_last());
                    $this->addError($e->getMessage());
                }
            }

        }

        /**
         * Add an error in the class
         * @param $value
         */
        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
        }
    }

    class Scripts extends CoreLogic
    {
        /** @var array $argv Keep the arguments passed to the logic if it runs as a script  */
        public $argv = null;
        var $tests;

        /**
         * Scripts constructor.
         * @param Core $core
         * @param null $argv
         */
        function __construct(Core $core, $argv=null)
        {
            parent::__construct($core);
            $this->argv = $argv;
        }

        function hasOption($option) {
            return(in_array('--'.$option, $this->argv));
        }

        function sendTerminal($info) {
            if(is_string($info)) echo $info."\n";
            else print_r($info);
        }

    }
}