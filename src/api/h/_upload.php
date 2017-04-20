<?php
class API extends RESTful
{
    /** @var  Buckets $buckets */
    private $buckets;
    function main()
    {
        if(!$this->checkMethod('GET,POST')) return;
        $this->buckets = $this->core->loadClass('Buckets','bloombees-public/upload');
        if($this->buckets->error) return($this->setErrorFromCodelib('system-error',$this->buckets->errorMsg));

        if(!$this->useFunction('ENDPOINT_'.$this->params[0]))  return($this->setErrorFromCodelib('params-error'));

    }

    /**
     * get uploadUrl to send files
     */
    public function ENDPOINT_uploadUrl() {

        // The return URL once the files have been sent to process those files
        $retUrl = $this->core->system->url['host_base_url'].'/h/api/_upload/manageFiles';


        // Gather uploadUrl
        $ret = array_merge(['uploadUrl' => $this->buckets->getUploadUrl($retUrl)],$this->buckets->vars);


        // return the data
        $this->addReturnData(['UploadInfo'=>$ret]);
    }


    public function ENDPOINT_manageFiles() {
        $this->addReturnData( $this->buckets->manageUploadFiles('gs://bloombees-public/upload2017',true,true));
    }
}