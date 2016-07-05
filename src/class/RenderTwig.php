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
        var $twig = null;


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

        function addStringTemplate($index,$template) {
            $this->templates[$index] = ['type'=>'string','template'=>$template];
        }

        function getTiwg($index) {
            if(!isset($this->templates[$index])) {
                $this->addError($index.' does not exist. Use addFileTemplate or addStringTemplate');
                return false;
            }

            switch ($this->templates[$index]['type']) {
                case "file":

                    break;
                default:
                    $loader = new \Twig_Loader_Array(array(
                        $index => $this->templates[$index]['template'],
                    ));
                    break;
            }
            $twig = new Twig_Environment($loader, array(
                "cache"       => $this->config['twigCachePath'],
                "debug"       => (bool)$this->core->is->development(),
                "auto_reload" => true,
            ));

            $function = new \Twig_SimpleFunction('getConf', function($key) {
                return $this->core->config->get($key);
            });
            $twig->addFunction($function);

            $function = new \Twig_SimpleFunction('setConf', function($key,$value) {
                return $this->core->config->set($key,$value);
            });
            $twig->addFunction($function);


            $function =  new \Twig_SimpleFunction('isAuth', function($namespace = null) {
                return $this->core->user->isAuth();
            });
            $twig->addFunction($function);

            $function = new \Twig_SimpleFunction('l', function($dic, $key, $config=[]) {
                return $this->core->localization->get($dic, $key, $config);
            });
            $twig->addFunction($function);

            $function = new \Twig_SimpleFunction('w', function($dic, $key, $config=[]) {
                return $this->core->localization->get($dic, $key, $config);
            });
            $twig->addFunction($function);

            return $twig;
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