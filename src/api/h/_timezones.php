<?php
class API extends RESTful
{
	function main()
	{
		$this->checkMethod('GET');
		if (!$this->error) {
			$tz = timezone_identifiers_list();
			$ret = [];
			foreach ($tz as $item) {
				date_default_timezone_set($item);
				$ret[$item] = date('Y-m-d H:i');
			}
			$this->setReturnData($ret);
		}
	}
}