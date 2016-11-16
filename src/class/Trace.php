<?php
// MaxMing service for Geolozalization
// Service Paid by CloudFrameWork
//
if (!defined("_Trace_CLASS_")) {
    define("_Trace_CLASS_", TRUE);

    class Trace
    {
        private $core;
        /* @var DataStore $ds */
        var $ds;
        var $error = false;
        var $errorMsg = [];

        function __construct(Core &$core,$spacename='cloudframework')
        {
            $this->core = $core;
            $this->ds = null;
            $schema = '{"Trace_id": ["keyName", "index"],
                        "Trace_app": ["string", "index"],
                        "Trace_type": ["string", "index"],
                        "Trace_cat": ["string", "index"],
                        "Trace_subcat": ["string", "index"],
                        "Trace_title": ["string", "index"],
                        "Trace_status": ["string", "index"],
                        "Trace_info": ["json"],
                        "Trace_kpi1": ["string", "index"],
                        "Trace_kpi2": ["string", "index"],
                        "Trace_kpi3": ["string", "index"],
                        "Trace_kpi4": ["string", "index"],
                        "Trace_ip": ["string", "index"],
                        "Trace_isoCountry": ["string", "index"],
                        "Trace_fingerPrint": ["json"],
                        "Trace_timeStamp": ["datetime", "Index"]
             }';
            $this->ds = $this->core->loadClass('DataStore',['CloudFrameWorkTraces', $spacename, json_decode($schema,true)]);
            if($this->ds->error) $this->addError($this->ds->errorMsg);

        }

        /**
         * @param array $data Array with fields to insert in logs. Require ['App'],['Cat']['Type']['Title']
         * @param string $email Optional parameter to send an email of this log.
         * @return array|bool
         */
        function createEntity($data) {
            if($this->error) return false;

            // Control field
            if(!isset($data['Trace_app']) || !strlen($data['Trace_app'])) $this->addError('Trace_app missing');
            if(!isset($data['Trace_cat']) || !strlen($data['Trace_cat'])) $this->addError('Trace_cat missing');
            if(!isset($data['Trace_type']) || !strlen($data['Trace_type'])) $this->addError('Trace_type missing');
            if(!isset($data['Trace_title']) || !strlen($data['Trace_title'])) $this->addError('Trace_title missing');
            if($this->error) return;

            // Feed optional fields
            if(!isset($data['Trace_timeStamp'])) $data['Trace_timeStamp'] = new DateTime('now');
            if(!isset($data['Trace_isoCountry'])) $data['Trace_isoCountry'] = '_';

            $ret = $this->ds->createEntities($data);
            if ($this->ds->error)  return($this->addError($this->ds->errorMsg));

            return $ret;
        }



        function addError($err) {
            $this->error = true;
            $this->errorMsg[] = $err;
            $this->core->errors->add(['Logs'=>$err]);
        }
    }


}