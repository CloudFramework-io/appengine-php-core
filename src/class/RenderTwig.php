<?php
// Render Twig Class v1
if (!defined ("_RenderTwig_CLASS_") ) {
    define("_RenderTwig_CLASS_", TRUE);

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
            if(!isset($config['twigCachePath']) && !strlen($this->core->config->get('twigCachePath'))) {
                $this->addError('Missing twigCachePath config var');
            } else {
                if(!isset($config['twigCachePath'])) $config['twigCachePath'] = $this->core->config->get('twigCachePath');
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
                    $loader = new \Twig_Loader_Filesystem(dirname($this->templates[$index]['template']));
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

                $this->twig = new Twig_Environment($loader, array(
                    "cache" => $this->config['twigCachePath'],
                    "debug" => (bool)$this->core->is->development(),
                    "_auto_reload" => true,
                ));

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
                    $template = $this->twig->loadTemplate(basename($this->index.'.htm.twig'));
                    if(!is_array($data)) $data = [$data];
                    $ret = $template->render($data);
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