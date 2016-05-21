<?php
class API extends RESTful
{
    function main()
    {
        $data['method'] = $this->method;
        $data['headers'] = $this->getHeaders();
        $data['formParams'] = $this->formParams;
        $this->addReturnData($data);
    }
}