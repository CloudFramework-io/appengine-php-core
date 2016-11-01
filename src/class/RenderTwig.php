<?php
// Render Twig Class v1
if (!defined ("_RenderTwig_CLASS_") ) {
    define("_RenderTwig_CLASS_", TRUE);

    require_once dirname(__FILE__).'/../lib/Twig/CacheInterface.php';

    /**
     * Adaptation of cache for Cloudframework compatible with
     *
     * @author Adrian Martinez <amartinez@adnbp.com>
     */

    class CloudFrameWork_Twig_Cache implements Twig_CacheInterface
    {
        private $directory;
        private $cache = null;
        private $templates = [];

        /**
         * @param $directory string The root cache directory
         */
        public function __construct($directory)
        {
            $this->directory = rtrim($directory, '\/').'/';
            $this->cache = new CoreCache('TWIG_CLOUDFRAMEWORK_'.$directory);
            // $this->cache->debug=false;

        }

        /**
         * {@inheritdoc}
         */
        public function generateKey($name, $className)
        {
            //$hash = hash('sha256', $className);
            return $this->directory.$name.'.php';
        }

        /**
         * {@inheritdoc}
         */
        public function load($key)
        {
            if(!isset($this->templates[$key])) $this->templates[$key] = $this->cache->get($key);
            if(is_array($this->templates[$key])) {
                return eval(str_replace('<?php','',$this->templates[$key]['code']));
            }

        }

        /**
         * {@inheritdoc}
         */
        public function write($key, $content)
        {
            $templates = $this->cache->get('templates');
            if(!isset($templates[$key])) {
                $templates[$key] = true;
                $this->cache->set('templates',$templates);
            }
            $template = ['timestamp'=>time(),'code'=>$content];
            return( $this->cache->set($key,$template));
            return;

        }

        /**
         * {@inheritdoc}
         */
        public function getTimestamp($key)
        {
            if(!isset($this->templates[$key])) $this->templates[$key] = $this->cache->get($key);
            if(is_array($this->templates[$key])) return $this->templates[$key]['timestamp'];
            else return 0;
        }

        function getTemplates() {
            return($this->cache->get('templates'));
        }

        function cleanTemplates() {
            $templates = $this->cache->get('templates');
            if(is_array($templates)) {
                foreach ($templates as $key=>$true) {
                    $this->cache->delete($key);
                }
                $this->templates = [];
                $this->cache->delete('templates');
            }
        }
    }

    /**
     * Cloudframework Rendertwig for Cloudframework
     *
     * @author Adrian Martinez <amartinez@adnbp.com>
     */
    class RenderTwig
    {
        private $core;
        var $config;
        var $error = false;
        var $errorMsg = [];
        var $templates = [];
        /* @var $twig Twig_Environment */
        var $twig = null;
        private  $index = '';
        var $load_from_cache = false;


        function __construct(Core &$core, $config)
        {
            $this->core = $core;
            spl_autoload_register(array(__CLASS__, 'autoload'), true, false);

            if(!isset($config['twigCachePath']) && $this->core->config->get('twigCachePath')) $config['twigCachePath'] = $this->core->config->get('twigCachePath');
            if(isset($config['twigCachePath'])) {
                if($this->core->is->development()) {
                    if(!is_dir($config['twigCachePath'])) {
                        @mkdir($config['twigCachePath']);
                        if(!is_dir($config['twigCachePath'])) {
                            $this->addError('twigCachePath is not writtable: '.$config['twigCachePath']);
                        }
                    }
                }
            }

            $this->config = $config;
        }

        function addFileTemplate($index,$path) {
            $this->templates[$index] = ['type'=>'file','template'=>$path];
        }

        function addURLTemplate($index,$path,$reload=false) {
            $this->templates[$index] = ['type'=>'url','url'=>$path,'reload'=>$reload==true];
        }

        function addStringTemplate($index,$template) {
            $this->templates[$index] = ['type'=>'string','template'=>$template];
        }
        function getTiwg($index) {
            $this->setTwig($index);
            return $this->twig;
        }

        /**
         * @param $index
         * @return bool
         */
        function setTwig($index) {
            if(!isset($this->templates[$index])) {
                $this->addError($index.' does not exist. Use addFileTemplate or addStringTemplate');
                return false;
            }

            $loader = null;
            $this->core->__p->add('RenderTwig->setTwig: ', $index, 'note');
            switch ($this->templates[$index]['type']) {
                case "file":
                    // Convert the path into relative path to generate the same keys
                    $path = dirname($this->templates[$index]['template']);
                    $loader = new \Twig_Loader_Filesystem($path);
                    break;
                case "url":
                    $template = '';  // Raw content of the HTML

                    // Trying to load from cache if reload is reload is false
                    if(!$this->templates[$index]['reload']) {
                        $template = $this->core->cache->get('RenderTwig_Url_Content_'.$this->templates[$index]['url']);
                        if(!empty($template)) $this->load_from_cache = true;
                    }

                    // If I don't have the template trying to load from URL
                    if(!strlen($template)) {
                        $template = file_get_contents($this->templates[$index]['url']);
                        if(strlen($template)) {
                            $this->core->cache->set('RenderTwig_Url_Content_'.$this->templates[$index]['template'],$template);
                        }
                    }

                    if(strlen($template)) {
                        $this->templates[$index]['template'] = $template;
                        $loader = new \Twig_Loader_Array(array(
                            $index => $this->templates[$index]['template'],
                        ));
                    } else {
                        $this->addError($this->templates[$index]['template'].' has not content');
                    }
                    break;
                default:
                    $loader = new \Twig_Loader_Array(array(
                        $index => $this->templates[$index]['template'],
                    ));
                    break;
            }
            if(is_object($loader)) {

                // Twig paramters
                $params = array("debug" => (bool)$this->core->is->development());
                if($this->core->config->get('twigCacheInMemory')) {
                    $spacename = ($this->config['twigCachePath'])?$this->config['twigCachePath']:$this->core->system->app_path;
                    $cache = new CloudFrameWork_Twig_Cache($spacename);
                    $params['cache'] = $cache;
                    if(isset($_GET['_cleanTwig'])) $cache->cleanTemplates();

                } else if(isset($this->config['twigCachePath'])) {
                    $params['cache'] = $this->config['twigCachePath'];
                }

                $this->twig = new Twig_Environment($loader, $params);

                $function = new \Twig_SimpleFunction('getConf', function ($key) {
                    return $this->core->config->get($key);
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('setConf', function ($key, $value) {
                    return $this->core->config->set($key, $value);
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('session', function ($key) {
                    return $this->core->session->get($key);
                });
                $this->twig->addFunction($function);


                $function = new \Twig_SimpleFunction('isAuth', function ($namespace = null) {
                    return $this->core->user->isAuth();
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('l', function ($dic, $key, $config = []) {
                    return $this->core->localization->get($dic, $key, $config);
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('w', function ($dic, $key, $config = []) {
                    return $this->core->localization->get($dic, $key, $config);
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('getLang', function () {
                    return $this->core->config->getLang();
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('setLang', function ($lang) {
                    return $this->core->config->setLang($lang);
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('system', function ($var) {
                    if(property_exists($this->core->system,$var)) return $this->core->system->{$var};
                });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('getData', function () { return $this->core->getData(); });
                $this->twig->addFunction($function);

                $function = new \Twig_SimpleFunction('getDataKey', function ($key) { return $this->core->getDataKey($key); });
                $this->twig->addFunction($function);

                $this->index = $index;
            }
            $this->core->__p->add('RenderTwig->setTwig: ', '', 'endnote');
        }

        function render($data=[]) {
            if(!strlen($this->index) || !is_object($this->twig)) return false;
            else {
                $this->core->__p->add('RenderTwig->render: ', $this->index, 'note');
                if($this->templates[$this->index]['type']=='file') {
                    if(!is_array($data)) $data = [$data];
                    $ret = $this->twig->render(basename($this->index.'.htm.twig'),$data);
                } else {
                    $ret = $this->twig->render($this->index,$data);
                }

                $this->core->__p->add('RenderTwig->render: ', '', 'endnote');
                return $ret;
            }
        }

        function getTemplate() {
            if(!strlen($this->index) || !is_object($this->twig)) return false;
            else {
                if($this->templates[$this->index]['type']=='file') {
                    return file_get_contents($this->templates[$this->index]['template']);
                } else {
                    return $this->templates[$this->index]['template'];
                }
            }
            return false;
        }

        function test() {
            $loader = new Twig_Loader_Array(array(
                'index' => 'Hello {{ name }}!',
            ));
            $twig = new Twig_Environment($loader);
            echo $twig->render('index', array('name' => 'Fabien'));
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
            $this->core->errors->add('Missing TwigBucket config var');
        }

        public static function autoload($class)
        {
            if (0 !== strpos($class, 'Twig')) { return; }
            if (is_file($file = dirname(__FILE__).'/../lib/'.str_replace(array('_', "\0"), array('/', ''), $class).'.php')) {
                require $file;
            }
        }
    }
}