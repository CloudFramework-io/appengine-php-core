<?php
include_once __DIR__.'/../src/Core.php';
$core = new Core();

// Check CloudService
$ret = $core->request->get('/_version');
$ret = $core->request->getCurl('/_version');

_print($core->__p->data);
