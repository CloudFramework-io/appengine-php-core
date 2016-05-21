<?php
include_once __DIR__.'/../src/Core.php';
$core = new Core();

// Check CloudService
$core->request->setServiceUrl('https://adnbp-first-web-site.appspot.com/h/api');
#$core->request->setServiceUrl('https://cloud.adnbp.com/h/api');

$ok = true;
$ret = json_decode($core->request->get('_api',['a'=>1,'b'=>2]),true);
$check = json_encode($ret['data']);
$ret = json_decode($core->request->getCurl('_api',['a'=>1,'b'=>2]),true);
if($check != json_encode($ret['data'])) {
    _print('Error', $check, json_encode($ret['data']));
    $ok = false;
}

$ret = json_decode($core->request->get('_api',['a'=>1,'b'=>2],'POST'),true);
$check = json_encode($ret['data']);
$ret = json_decode($core->request->getCurl('_api',['a'=>1,'b'=>2],'POST'),true);
if($check != json_encode($ret['data'])) {
    _print('Error', $check, json_encode($ret['data']));
    $ok = false;

}

$ret = json_decode($core->request->get('_api',['a'=>1,'b'=>2],'POST',null,true),true);
$check = json_encode($ret['data']);
$ret = json_decode($core->request->getCurl('_api',['a'=>1,'b'=>2],'POST',null,true),true);
if($check != json_encode($ret['data'])) {
    _print('Error', $check, json_encode($ret['data']));
    $ok = false;
}
if(!$ok) _print('test failed :(');
else _print(';) OK.. man... you did it.');
