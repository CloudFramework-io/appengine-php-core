<?php

$rootPath = exec('pwd');
include_once __DIR__.'/src/Core.php';
$core = new Core($rootPath);

// Check test exist
if(true) {
    system('clear');

    if(count($argv)>1) {
        list($script, $params) = explode('?', str_replace('..', '', $argv[count($argv)-1]), 2);
        $script = explode('/', $script);
        $path = ($script[0][0] == '_') ? __DIR__ : $core->system->app_path;
    }

    echo "CloudFramwork Script v1.0\nroot_path: {$rootPath}\napp_path: {$path}\n";
    if(!strlen($script[0])) die ('Mising Script name: Use php vendor/cloudframework-io/appengine-php-core/runscript.php {script_name}'."\n\n");
    echo "Script: /scripts/{$script[0]}.php\n------------------------------\n";


    // Evaluate options
    $options = ['performance'=>false];
    if(count($argv)>2) for($i=1,$tr=count($argv)-1;$i<$tr;$i++) {
        switch ($argv[$i]) {
            case "-p":
                $options['performance'] = true;
                break;
            default:
                die('unknown option: '.$argv[$i]."\n");
                break;
        }

    }

    if(!is_file($path.'/scripts/'.$script[0].'.php')) die("Script not found. Create it with:\n-------\n<?php\nclass Script extends Scripts {\n\tfunction main() { }\n}\n-------\n\n");
    include_once $path.'/scripts/'.$script[0].'.php';
    if(!class_exists('Script')) die('The script does not include a "Class Script'."\nUse:\n-------\n<?php\nclass Script extends Scripts {\n\tfunction main() { }\n}\n-------\n\n");
    /** @var Script $script */

    $run = new Script($core);
    $run->params = $script;
    if(strlen($params))
        parse_str($params,$run->formParams);
    
    if(!method_exists($run,'main')) die('The class Script does not include the method "main()'."\n\n");
}
try {
    $run->main();
} catch (Exception $e) {
    $this->errors->add(error_get_last());
    $this->errors->add($e->getMessage());
}
echo "------------------------------\n";
if($core->errors->lines) {
    $run->sendTerminal(['errors'=>$core->errors->data]);
    $run->sendTerminal('Script: Error');
}
else $run->sendTerminal('Script: OK');
if($core->logs->lines) $run->sendTerminal(['logs'=>$core->logs->data]);
if($options['performance']) $run->sendTerminal($core->__p->data['info']);
