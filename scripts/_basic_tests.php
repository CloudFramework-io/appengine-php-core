<?php
class Script extends Scripts
{
    function main()
    {
        $this->sendTerminal('Calling: ' . $this->core->request->getServiceUrl('/_version'));
        $ret = $this->core->request->get('_version');
        if($ret) $this->sendTerminal($ret);
    }
}