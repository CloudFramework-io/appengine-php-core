<?php
// Copy this file in the upper folder of ADNBP framework folder
// v1 Apr. 2016
include_once (__DIR__ . "/Core.php"); //
$core = new Core();
$core->dispatch();

if(isset($_GET['__p']))
    _print($core->__p->data['info']);

if($core->errors->lines) {
    _print($core->errors->data);
}

?>