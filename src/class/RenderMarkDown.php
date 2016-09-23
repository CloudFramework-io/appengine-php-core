<?php
// Render Twig Class v1
if (!defined ("_RenderTwig_CLASS_") ) {
    define("_RenderTwig_CLASS_", TRUE);

    class RenderMarkDown
    {
        private $core;
        var $objMarkDown = null;

        function __construct(Core &$core, $config)
        {
            $this->core = $core;
            include_once __DIR__.'/../lib/Parsedown.php';
            $this->objMarkDown = new Parsedown();
        }

        public function parse($txt) {
            return $this->objMarkDown->parse($txt);
        }
    }
}