<?php
class API extends RESTful
{
    /* @var $ds DataStore */
    var $ds;

    function main()
    {
        if(!$this->checkMethod('POST')) return;
        if(isset($this->params[0]) && $this->params[0]=='test') {
            $model = json_decode('{
                      "entity":"dsProxy",
                      "model": {
                        "DateInsertion": ["datetime","index|defaultvalue:now"],
                        "Title": ["string"]
                      }}',true);

            /** @var DataStore $ds */
            $ds = $this->core->loadClass('DataStore',['dsProxy','test',$model]);
            $ds_serialize = serialize($ds);

            $ret = $this->core->request->post_json_decode('http://localhost:9999/_dsproxy/fetchAll',['dsobject'=>$ds_serialize]);
            if($ret) $ret = unserialize(gzuncompress(utf8_decode($ret['data'])));
            if(!$this->core->request->error)  $this->addReturnData($ret);
            else $this->setErrorFromCodelib('system-error');
            return;

            $entity = ["DateInsertion"=>"now","Title"=>"Test at ".date("Y-m-d H:i:s")];
            $ds->createEntities([$entity]);
            $this->addReturnData(['serialize'=>$ds_serialize,'local_data'=>$ds->fetchAll()]);

        } else {
            if(!$this->checkMandatoryFormParams('dsobject')) return;
            if(!$this->checkMandatoryParam(0,'missing method. User /_dsproxy/(fetchAll|fetchOne)')) return;
            if(!$this->unserialize($this->formParams['dsobject'])) return;
            switch ($this->params[0]) {
                case "fetchAll":
                    $ret = $this->ds->fetchAll();
                    $this->addReturnData(utf8_encode(gzcompress((serialize($ret)))));
                    break;
                default:
                    return($this->setErrorFromCodelib('params-error','method not found'));
                    break;
            }
        }

    }


    function ENDPOINT_fetch() {

    }

    function ENDPOINT_test() {

    }

    function unserialize($object) {
        include_once(__DIR__ . "/../../class/DataStore.php");
        try {
            $this->ds  = unserialize($this->formParams['dsobject']);
            if($this->ds === false) return($this->setErrorFromCodelib('system-error',"wrong serializable object"));
        } catch (Exception $e) {
            return($this->setErrorFromCodelib('system-error',$e->getMessage()));
        }
        return true;
    }
}
