<?php
class API extends RESTful
{
	function main()
	{
		//$this->core->config->set('CloudServiceUrl','http://localhost:9080/h/api');

		$this->checkMethod('GET');
		// Checking by vars
		$showConfig = false;
		if(!$this->core->security->checkBasicAuthWithConfig()) {
			$this->setError('Required right Authorization header. Add in config {"authorizations":{"{{your user}}":{"password":"{{your password}}"}}');
		}

		$check = [];
		if(!$this->error ) {

			if (isset($_GET['all']) || isset($_GET['CloudSQL']))
				if ($this->core->config->get("dbName")) {
					$this->core->__p->init('Test', 'CloudSQL connect');
					$db = $this->core->loadClass("CloudSQL");
					$db->connect();
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

			if (isset($_GET['all']) || isset($_GET['Localization'])) {
				$this->core->__p->init('Test', 'Localization');
				$errMsg = [];
				$notes = [];
				$ret = null;
				$ok = true;

				$notes[] = '{{LocalizatonDefaultLang}}='.$this->core->config->get("LocalizatonDefaultLang");
				$notes[] = '{{LocalizatonAllowedLangs}}='.$this->core->config->get("LocalizatonAllowedLangs");
				$notes[] = '$this->core->config->getLang()='.$this->core->config->getLang();
				$notes[] = '{{CloudServiceLocalization}}='. $this->core->config->get("CloudServiceLocalization");

				// Testing Localize var
				if (!strlen($this->core->config->get("LocalizePath"))) {
					$errMsg[] = 'Missing {{LocalizePath}} config var.';
				} else {
					if(!$this->core->is->dirWritable($this->core->config->get("LocalizePath")))
						$errMsg[] = 'Error in dir {{LocalizePath}}: '  . json_encode(error_get_last(), JSON_PRETTY_PRINT);
					else $notes[] = "{{LocalizePath}} is writable";
				}

				// Test set a dictionary
				if(!count($errMsg)) {
					$notes[] = ['test'=>['$this->core->localization->get(\'Test\',\'universe;earth;salutations;hello\')'=>$this->core->localization->get("Test","universe;earth;salutations;hello")]];
				}
				// Testing $core->localization->set
				// if($this->core->localization->set('Test','test;hello'));


				$ok = !count($errMsg);
				$this->core->__p->end('Test', 'Localization', $ok,  array_merge($notes,$errMsg));
			}

			// Cloud Service Connections
			if (isset($_GET['all']) || $_GET['Localization'] == 'cloud')
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
				} else {
					$this->addReturnData(array('Cloud Service Url' => 'no CloudServiceUrl configured'));
				}

			if (is_file($this->core->system->app_path . '/logic/_test.php')) include($this->core->system->app_path . '/logic/_test.php');
		}
		if (isset($this->core->__p->data['init']))
			$this->addReturnData(['Tests'=>[$this->core->__p->data['init']['Test']]]);
	}
}