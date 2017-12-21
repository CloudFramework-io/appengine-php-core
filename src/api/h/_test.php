<?php
class API extends RESTful
{
	function main()
	{
		//$this->core->config->set('CloudServiceUrl','http://localhost:9080/h/api');

		$this->checkMethod('GET');
		// Checking by vars
		$showConfig = false;
		if( !$this->core->security->checkBasicAuthWithConfig()) {
		    if($this->core->is->production())
                return($this->setErrorFromCodelib('security-error','Required right Authorization header. Add in config {"authorizations":{"{{your user}}":{"password":"{{your password}}"}}'));
            else
                $this->core->logs->add('missing BasicAuth security');
        }

		$check = [];


        if (isset($_GET['all']) || isset($_GET['DataStore']))
            if ($this->core->config->get("DataStoreSpaceName")) {
                $this->core->__p->init('Test', 'DataStore connect');

                /* @var $ds DataStore */
                $schema = '{
                            "id": ["key","index"],
                            "title": ["string","index"],
                            "author": ["string","index"],
                            "published": ["date","index"],
                            "geolocation":["geo"],
                            "cat":["list","index"],
                            "description": ["string"]
                          }';
                $data ='[
                          [1,"title1","author1","2014-01-01","53.4723272,-2.2936314",["cat1","cat2"],"this is a description for 1"],
                          [2,"title2","author2","2014-01-02","53.4723272,-2.2936314",["cat1","cat2"],"this is a description for 2"],
                          [3,"title2","author3","2014-01-02","53.4723272,-2.2936314",["cat1","cat2"],"this is a description for 2"]
                          ]';
                $ds = $this->core->loadClass("DataStore",['CloudFrameWorkTest',$this->core->config->get("DataStoreSpaceName"),json_decode($schema, true)]);
                $arr = ['{spacename} = $this->core->config->get("DataStoreSpaceName")' => substr($this->core->config->get("DataStoreSpaceName"), 0, 2) . '***'];
                $arr['{schema} '] = preg_replace('/(\t|\n)/','' , $schema);
                $arr['$ds = $this->core->loadClass("DataStore",[\'CloudFrameWorkTest\',{spacename},{schema}]);'] = !$ds->error;
                $notes[] = $arr;

                if (!$ds->error) {
                    $ds->createEntities(json_decode($data, true));
                    $arr = ['{data}' => preg_replace('/(\t|\n)/','' , $data)];
                    $arr['$ds->createEntities({data})'] = !$ds->error;
                    if(!$ds->error) {
                        $ds->fetchAll();
                        $arr['$ds->fetchAll()'] = $ds->fetchAll();
                    }
                    $notes[] = $arr;
                } else {
                    $notes = array($ds->errorMsg);
                }
                $this->core->__p->end('Test', 'DataStore connect', !$ds->error, $notes);
            } else {
                $this->addReturnData(array('DataStore connect' => 'no DataStoreSpaceName config-var is configuredconfigured'));
            }

        if (isset($_GET['all']) || isset($_GET['CloudSQL']))
            if ($this->core->config->get("dbName")) {
                $this->core->__p->init('Test', 'CloudSQL connect');
                $db = $this->core->loadClass("CloudSQL");
                $db->connect();
                $notes = [];
                if (!$db->error()) {
                    $db->close();
                    $notes[] = ['dbServer' => (strlen($this->core->config->get("dbServer"))) ? substr($this->core->config->get("dbServer"), 0, 4) . '***' : 'None'];
                    $notes[] = ['dbSocket' => (strlen($this->core->config->get("dbSocket"))) ? '***' : 'None'];
                } else {
                    $notes = array($db->getError());
                }

                /*
                $notes[] = ['dbServer'=>(strlen($this->core->config->get("dbServer")))?substr($this->core->config->get("dbServer"),0,4).'***':'None'];
                $notes[] = ['dbSocket'=>(strlen($this->core->config->get("dbSocket")))?'***':'None'];
                $notes[] = ['dbUser'=>(strlen($this->core->config->get("dbUser")))?'***':'None'];
                $notes[] = ['dbPassword'=>(strlen($this->core->config->get("dbPassword")))?'***':'None'];
                $notes[] = ['dbName'=>(strlen($this->core->config->get("dbName")))?'***':'None'];
                $notes[] = ['dbPort'=>(strlen($this->core->config->get("dbPort")))?'***':'None'];
                */
                $this->core->__p->end('Test', 'CloudSQL connect', !$db->error(), $notes);
            } else {
                $this->addReturnData(array('CloudSQL connect' => 'no DB configured'));
            }

        if (isset($_GET['all']) || isset($_GET['CacheFile'])) {
            $this->core->__p->init('Test', 'CacheFile');
            $errMsg = [];
            $notes = [];
            $ret = null;
            $ok = true;

            $notes[] = '{{cachePath}}='. (strlen($this->core->config->get("cachePath"))?'****'.substr($this->core->config->get("cachePath"),-6):'not defined');

            // Testing Localize var
            if (!strlen($this->core->config->get("cachePath"))) {
                $errMsg[] = 'Missing {{cachePath}} config var.';
            } else {
                if(!$this->core->is->dirWritable($this->core->config->get("cachePath")))
                    $errMsg[] = 'Error in dir {{cachePath}}: '  . json_encode(error_get_last(), JSON_PRETTY_PRINT);
                else $notes[] = "{{cachePath}} is writable";
            }

            // Test set a dictionary
            if(!count($errMsg)) {
                $notes[] = ['test'=>['$this->core->activateCacheFile()'=>$this->core->activateCacheFile()]];
                if($this->core->cache->error) $errMsg[] = $this->core->cache->errorMsg;
            }
            // Testing $core->localization->set
            // if($this->core->localization->set('Test','test;hello'));


            $ok = !count($errMsg);
            $this->core->__p->end('Test', 'CacheFile', $ok,  array_merge($notes,$errMsg));
        }

        if (isset($_GET['all']) || isset($_GET['Localization'])) {
            $this->core->__p->init('Test', 'Localization');
            $errMsg = [];
            $notes = [];
            $ret = null;
            $ok = true;



            // Testing Localize var
            if (!strlen($this->core->config->get("core.localization.cache_path"))) {
                $errMsg[] = 'Missing {{core.localization.cache_path}} config var.';
            } else {
                if(!$this->core->is->dirWritable($this->core->config->get("core.localization.cache_path")))
                    $errMsg[] = 'Error in dir {{core.localization.cache_path}}: '  . json_encode(error_get_last(), JSON_PRETTY_PRINT);
                else {

                    $notes[] = "Info about Localization: https://github.com/CloudFramework-io/appengine-php-core/wiki/Localization";
                    $notes[] = "{{core.localization.cache_path}} is writable";
                    $notes[] = '$this->core->config->getLang() is ['.$this->core->config->getLang().']';

                    $loc = $this->core->localization->get('_test','test-example',['lang'=>'en']);
                    $loc_es = $this->core->localization->get('_test','test-example',['lang'=>'es']);
                    if(!$loc || !$loc_es) {
                        $notes[] = '$this->core->localization->set(\'_test\',\'test-example\',\'This is an example of tag: test-example\',[\'lang\'=>\'en\'])';
                        $notes[] = '$this->core->localization->set(\'_test\',\'test-example\',\'Este es un ejemplo tag: test-example\',[\'lang\'=>\'es\'])';
                        $this->core->localization->set('_test','test-example','This is an example of tag: test-example',['lang'=>'en']);
                        $this->core->localization->set('_test','test-example','Este es un ejemoplo de tag: test-example',['lang'=>'es']);
                        $loc = $this->core->localization->get('_test','test-example',['lang'=>'en']);
                        $loc_es = $this->core->localization->get('_test','test-example',['lang'=>'es']);

                    }
                    $notes[] = '$this->core->localization->get(\'_test\',\'test-example\',[\'lang\'=>\'en\']) => '.$loc;
                    $notes[] = '$this->core->localization->get(\'_test\',\'test-example\',[\'lang\'=>\'es\']) => '.$loc_es;



                    $notes[] = '{{core.localization.param_name}}='.(($this->core->config->get("core.localization.param_name"))?$this->core->config->get("core.localization.param_name"):'empty');
                    $notes[] = '{{core.localization.default_lang}}='.(($this->core->config->get("core.localization.default_lang"))?$this->core->config->get("core.localization.default_lang"):'empty');
                    $notes[] = '{{core.localization.allowed_langs}}='.(($this->core->config->get("core.localization.allowed_langs"))?$this->core->config->get("core.localization.allowed_langs"):'empty');
                    // $notes[] = '$this->core->config->getLang()='.$this->core->config->getLang();
                    // $notes[] = '{{WAPPLOCA}}='. (strlen($this->core->config->get("WAPPLOCA"))?'****':false);

                }
            }

            // Test set a dictionary
            if(!count($errMsg)) {
                // $notes[] = ['test'=>['$this->core->localization->get(\'Test\',\'universe;earth;salutations;hello\')'=>$this->core->localization->get("Test","universe;earth;salutations;hello")]];
                // $notes[] = ['test'=>['$this->core->localization->get(\'Test\',\'universe;earth;salutations;good_bye\')'=>$this->core->localization->get("Test","universe;earth;salutations;good_bye")]];
            }
            // Testing $core->localization->set
            // if($this->core->localization->set('Test','test;hello'));


            $ok = !count($errMsg);
            $this->core->__p->end('Test', 'Localization', $ok,  array_merge($notes,$errMsg));
        }

        if (isset($_GET['all']) || isset($_GET['Twig'])) {
            $this->core->__p->init('Test', 'Twig');
            $errMsg = [];
            $notes = [];
            $ret = null;
            $ok = true;

            $notes[] = '{{twigCachePath}}='. (strlen($this->core->config->get("twigCachePath"))?'****'.substr($this->core->config->get("twigCachePath"),-6):'not defined');

            // Testing Localize var
            if (!strlen($this->core->config->get("twigCachePath"))) {
                $errMsg[] = 'Missing {{twigCachePath}} config var.';
            } else {
                if(!$this->core->is->dirWritable($this->core->config->get("twigCachePath")))
                    $errMsg[] = 'Error in dir {{twigCachePath}}: '  . json_encode(error_get_last(), JSON_PRETTY_PRINT);
                else $notes[] = "{{twigCachePath}} is writable";
            }

            // Test set a dictionary
            if(!count($errMsg)) {
                $renderTwig = $this->core->loadClass('RenderTwig');
                $notes[] = ['load'=>['$this->renderTwig = $this->core->loadClass(\'RenderTwig\')'=>!$renderTwig->error]];
                if($renderTwig->error) $errMsg[] = $renderTwig->errorMsg;
                else {
                    $renderTwig->addStringTemplate('test','Hello {{ name }}!');
                    $renderTwig->setTwig('test');

                    $notes[] = ['template'=>['$renderTwig->addStringTemplate(\'test\',\'Hello {{ name }}!\');'=>true]];
                    $notes[] = ['twig'=>['$renderTwig->setTwig(\'test\')'=>true]];
                    $notes[] = ['twig'=>['$renderTwig->render([\'name\'=>\'Lola\'])'=>$renderTwig->render(['name'=>'Lola'])]];

                }
            }
            // Testing $core->localization->set
            // if($this->core->localization->set('Test','test;hello'));


            $ok = !count($errMsg);
            $this->core->__p->end('Test', 'CacheFile', $ok,  array_merge($notes,$errMsg));
        }



        // Cloud Service Connections
        if (isset($_GET['all']) || isset($_GET['CloudService']))
            if ($this->core->request->getServiceUrl()) {
                $url = '/_version';
                $retErr = '';
                $this->core->__p->init('Test', 'Cloud Service Url request->get');
                $ret = $this->core->request->get($url);
                if (!$this->core->request->error) {
                    $ret = json_decode($ret);
                    $retOk = $ret->success;
                    if (!$retOk) $retErr = json_encode($ret);

                } else {
                    $retOk = false;
                }
                $this->core->__p->end('Test', 'Cloud Service Url request->get', $retOk, $this->core->request->getServiceUrl('/_version') . ' ' . $retErr);
                /*
                $this->core->__p->init('Test', 'Cloud Service Url request->getCurl');
                $ret = $this->core->request->getCurl($url);
                if (!$this->core->request->error) {
                    $ret = json_decode($ret);
                    $retOk = $ret->success;
                    if (!$retOk) $retErr = json_encode($ret);
                } else {
                    $retOk = false;
                }
                $this->core->__p->end('Test', 'Cloud Service Url request->getCurl', $retOk, $this->core->request->getServiceUrl('/_version') . ' ' . $retErr);
                */
            } else {
                $this->addReturnData(array('Cloud Service Url' => 'no CloudServiceUrl configured'));
            }

        if (is_file($this->core->system->app_path . '/logic/_test.php')) include($this->core->system->app_path . '/logic/_test.php');

		if (isset($this->core->__p->data['init']))
			$this->addReturnData(['Tests'=>[$this->core->__p->data['init']['Test']]]);
	}
}