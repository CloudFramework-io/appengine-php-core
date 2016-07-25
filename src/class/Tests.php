<?php
include_once __DIR__.'/RESTful.php';
// Tests class.. v1.0
if (!defined("_Tests_CLASS_")) {
    define("_Tests_CLASS_", TRUE);

    class Tests extends RESTful
    {
        var $tests;

        function sendTerminal($info) {
            if(is_string($info)) echo $info;
            else print_r($info);
            echo "\n";
        }
        
    }
}