<?php
class API extends RESTful
{
    function main()
    {
        $this->sendCorsHeaders('GET,POST,PUT,DELETE');

        $data['method'] = $this->method;
        $data['headers'] = $this->getHeaders();
        $data['params'] = $this->params;
        $data['formParams'] = $this->formParams;
        $this->addReturnData($data);
    }
}