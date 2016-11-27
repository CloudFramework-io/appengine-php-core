<?php
$rootPath = exec('pwd');
include_once __DIR__.'/src/Core.php';

include_once __DIR__.'/src/class/Tests.php';
$core = new Core($rootPath);

// Check test exist
if(true) {
    system('clear');
    list($script,$params) = explode('?',str_replace('..','',$argv[1]),2);
    $script = explode('/',$script);
    $path = ($script[0][0]=='_')?__DIR__:$core->system->app_path;

    echo "CloudFramwork Test Script v1.0\napp_path: {$path}\n------------------------------\n\n";
    if(!strlen($script[0])) die ('Mising Test name: Use php vendor/cloudframework-io/appengine-php-core/runtest.php {test_name}'."\n\n");

    // $script[0] = string

    if(!is_file($path.'/tests/'.$script[0].'.php')) die('Test not found: '.$path.'/tests/'.$script[0]."\n\n Create it with:\n-------\n<?php\nclass Test extends Tests {\n\tfunction main() { }\n}\n-------\n\n");

    include_once $path.'/tests/'.$script[0].'.php';
    if(!class_exists('Test')) die($path.'/tests/'.$script[0].' does not include a "Class Test'."\n\n");

    /** @var Tests $test */
    $test = new Test($core);
    $test->params = $script;
    if(strlen($params))
        parse_str($params,$test->formParams);
    if(!method_exists($test,'main')) die('The class Test does not include the method "main()'."\n\n");
}

$test->main();
//system("clear");
//echo $test->send(true,true,$argv);
echo "\n\n";