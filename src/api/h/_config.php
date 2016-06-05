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
			$showConfig =  $key=='SystemPassword' && $this->core->system->checkPassword($value,$this->core->config->get($key));
		} else {
			$this->setError('Require Basic Authentication');
		}

		$check = [];
		if(!$this->error && !count($this->formParams)) {
			$check = ['Config' => [
				'Security' => [
					'SystemPassword' => [
						'Description' => "Encrypted password to allow seeing  system configuration in plain text. To generate a password you can use h/api/_crypt",
						"active" => ($this->core->config->get("SystemPassword")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("SystemPassword") : '**Require a right SystemPassword**'

					],
					'CloudServiceId' => [
						'Description' => "Id provided from your CloudService Provider",
						"active" => ($this->core->config->get("CloudServiceId")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("CloudServiceId") : '**Require a right SystemPassword**'


					],
					'CloudServiceSecret' => [
						'Description' => "Secret provided from your CloudService Provider",
						"active" => ($this->core->config->get("CloudServiceSecret")) ? true : false,
						"content" => ($showConfig) ? $this->core->config->get("CloudServiceSecret") : '**Require a right SystemPassword**'

					],
					'authorizations' => $this->core->config->get('authorizations'),
				],
				'CloudServices' => [
					'CloudServiceUrl' => ($this->core->config->get("CloudServiceUrl")) ? (($showConfig) ? $this->core->config->get("CloudServiceUrl") : '**Require a right SystemPassword**') : false,
					'CloudServiceId' => ($this->core->config->get("CloudServiceId")) ? (($showConfig) ? $this->core->config->get("CloudServiceId") : '**Require a right SystemPassword**') : false,
					'CloudServiceSecret' => ($this->core->config->get("CloudServiceSecret")) ? (($showConfig) ? $this->core->config->get("CloudServiceSecret") : '**Require a right SystemPassword**') : false,
					'CloudServiceLocalization' => ($this->core->config->get("CloudServiceLocalization")) ? (($showConfig) ? $this->core->config->get("CloudServiceLocalization") : '**Require a right SystemPassword**') : false,
					'CloudServiceLog' => ($this->core->config->get("CloudServiceLog")) ? (($showConfig) ? $this->core->config->get("CloudServiceLog") : '**Require a right SystemPassword**') : false,
				],
				'CloudSQL' => [
					'dbServer' => ($this->core->config->get("dbServer")) ? (($showConfig) ? $this->core->config->get("dbServer") : '**Require a right SystemPassword**') : false,
					'dbSocket' => ($this->core->config->get("dbSocket")) ? (($showConfig) ? $this->core->config->get("dbSocket") : '**Require a right SystemPassword**') : false,
					'dbName' => ($this->core->config->get("dbName")) ? (($showConfig) ? $this->core->config->get("dbName") : '**Require a right SystemPassword**') : false,
					'dbUser' => ($this->core->config->get("dbUser")) ? (($showConfig) ? $this->core->config->get("dbUser") : '**Require a right SystemPassword**') : false,
					'dbPassword' => ($this->core->config->get("dbPassword")) ? "**Require explore the Code**" : false,
				],
				'DataStore' => [
					'DataStoreSpaceName' => ($this->core->config->get("DataStoreSpaceName")) ? (($showConfig) ? $this->core->config->get("DataStoreSpaceName") : '**Require a right SystemPassword**') : false
				],
				'Localization' => [
					'WAPPLOCA' => ($this->core->config->get("WAPPLOCA")) ?  (($showConfig) ? $this->core->config->get("WAPPLOCA") : '**Require a right SystemPassword**') : false,
					'LocalizePath' => ($this->core->config->get("LocalizePath")) ? (($showConfig) ? $this->core->config->get("LocalizePath") : '**Require a right SystemPassword**') : false,
					'LocalizatonDefaultLang' => ($this->core->config->get("LocalizatonDefaultLang")) ? $this->core->config->get("LocalizatonDefaultLang") : 'does not exist. $core->config->lang will be \'en\'',
					'LocalizatonAllowedLangs' => ($this->core->config->get("LocalizatonAllowedLangs")) ? $this->core->config->get("LocalizatonAllowedLangs") : 'does not exist. You can put the languages you want separated by , (en,es)',

				]
			]
			];
			$this->addReturnData($check);
		}

		if (isset($this->core->__p->data['init']))
			$this->addReturnData(['Tests'=>[$this->core->__p->data['init']['Test']]]);
	}
}

