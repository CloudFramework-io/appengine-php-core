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

        function __construct(Core &$core, $config)
        {
            $this->core = $core;
            $this->config = $config;
        }
    }
    
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
    }
}