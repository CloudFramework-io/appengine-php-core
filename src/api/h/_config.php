<?php
class API extends RESTful
{
	function main()
	{
		$this->checkMethod('GET');
		// Checking by vars
		$showConfig = false;
		if($this->existBasicAuth()) {
			list($key,$value) = $this->core->security->getBasicAuth();
			$showConfig =  $key=='core.system.password' && $this->core->system->checkPassword($value,$this->core->config->get($key));
		} else {
			$this->setErrorFromCodelib('security-error','Require Basic Authentication');
		}

		$check = [];
		if(!$this->error && !count($this->formParams)) {
			$check = ['Config' => [
				'Security' => [
					'core.system.password' => [
						'Description' => "Encrypted password to allow seeing  system configuration in plain text. To generate a password you can use h/api/_crypt",
						"active" => ($this->core->config->get("core.system.password")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("core.system.password") : '**Require a right core.system.password**'

					],
					'CloudServiceId' => [
						'Description' => "Id provided from your CloudService Provider",
						"active" => ($this->core->config->get("CloudServiceId")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("CloudServiceId") : '**Require a right core.system.password**'


					],
					'CloudServiceSecret' => [
						'Description' => "Secret provided from your CloudService Provider",
						"active" => ($this->core->config->get("CloudServiceSecret")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("CloudServiceSecret") : '**Require a right core.system.password**'

					],
					'authorizations' => $this->core->config->get('authorizations'),
				],
				'Cache' => [
					'cachePath' => ($this->core->config->get("cachePath")) ? (($showConfig) ? $this->core->config->get("cachePath") : '**Require a right core.system.password**') : false,
					'core.localization.cache_path' => ($this->core->config->get("core.localization.cache_path")) ? (($showConfig) ? $this->core->config->get("core.localization.cache_path") : '**Require a right core.system.password**') : false,
					'twigCachePath' => ($this->core->config->get("twigCachePath")) ? (($showConfig) ? $this->core->config->get("twigCachePath") : '**Require a right core.system.password**') : false,

				],
				'CloudServices' => [
					'CloudServiceUrl' => ($this->core->config->get("CloudServiceUrl")) ? (($showConfig) ? $this->core->config->get("CloudServiceUrl") : '**Require a right core.system.password**') : false,
					'CloudServiceId' => ($this->core->config->get("CloudServiceId")) ? (($showConfig) ? $this->core->config->get("CloudServiceId") : '**Require a right core.system.password**') : false,
					'CloudServiceSecret' => ($this->core->config->get("CloudServiceSecret")) ? (($showConfig) ? $this->core->config->get("CloudServiceSecret") : '**Require a right core.system.password**') : false,
					'CloudServiceLocalization' => ($this->core->config->get("CloudServiceLocalization")) ? (($showConfig) ? $this->core->config->get("CloudServiceLocalization") : '**Require a right core.system.password**') : false,
					'CloudServiceLog' => ($this->core->config->get("CloudServiceLog")) ? (($showConfig) ? $this->core->config->get("CloudServiceLog") : '**Require a right core.system.password**') : false,
				],
				'CloudSQL' => [
					'dbServer' => ($this->core->config->get("dbServer")) ? (($showConfig) ? $this->core->config->get("dbServer") : '**Require a right core.system.password**') : false,
					'dbSocket' => ($this->core->config->get("dbSocket")) ? (($showConfig) ? $this->core->config->get("dbSocket") : '**Require a right core.system.password**') : false,
					'dbName' => ($this->core->config->get("dbName")) ? (($showConfig) ? $this->core->config->get("dbName") : '**Require a right core.system.password**') : false,
					'dbUser' => ($this->core->config->get("dbUser")) ? (($showConfig) ? $this->core->config->get("dbUser") : '**Require a right core.system.password**') : false,
					'dbPassword' => ($this->core->config->get("dbPassword")) ? "**Require explore the Code**" : false,
				],
				'DataStore' => [
					'DataStoreSpaceName' => ($this->core->config->get("DataStoreSpaceName")) ? (($showConfig) ? $this->core->config->get("DataStoreSpaceName") : '**Require a right core.system.password**') : false
				],
				'Localization' => [
					'WAPPLOCA' => ($this->core->config->get("WAPPLOCA")) ?  (($showConfig) ? $this->core->config->get("WAPPLOCA") : '**Require a right core.system.password**') : false,
					'core.localization.cache_path' => ($this->core->config->get("core.localization.cache_path")) ? (($showConfig) ? $this->core->config->get("core.localization.cache_path") : '**Require a right core.system.password**') : false,
					'core.localization.default_lang' => ($this->core->config->get("core.localization.default_lang")) ? $this->core->config->get("core.localization.default_lang") : 'does not exist. $core->config->lang will be \'en\'',
					'core.localization.allowed_langs' => ($this->core->config->get("core.localization.allowed_langs")) ? $this->core->config->get("core.localization.allowed_langs") : 'does not exist. You can put the languages you want separated by , (en,es)',

				]
			]
			];
			$this->addReturnData($check);
		}

		if (isset($this->core->__p->data['init']))
			$this->addReturnData(['Tests'=>[$this->core->__p->data['init']['Test']]]);
	}
}

