<?php
class API extends RESTful
{
    const DEFAULT_URL = 'https://cloudframework-io.firebaseio.com/';
    const DEFAULT_TOKEN = 'AIzaSyBf7CS96KDztQPWCpUhNcs1wvhpn0tKtds';
    const DEFAULT_PATH = '/firebase/example';


    function main()
    {

        /** @var Firebase $firebase */
        $firebase = $this->core->loadClass('Firebase',['https://cloudframework-io.firebaseio.com/','AAAACyO8RKY:APA91bH_1CIIf22FZLiDdnQqJBZtwt-hCrCc89uKwsqoK9KD7MIvc4Yxg2QzJb9FuWjUjt4q6pIpXqFxUkEgj3nhDBIuwf8vXjtT69KuYgNpTbTl19xKS3rybcMGqsRUHU17cBoPdJQC']);
        if($firebase->error) return($this->setErrorFromCodelib('system-error',$firebase->errorMsg));

        // --- storing an array ---
        $test = array(
            "foo" => "bar",
            "i_love" => "lamp",
            "id" => 42
        );
        $dateTime = new DateTime();
        if(!$firebase->set(DEFAULT_PATH . '/' . $dateTime->format('c'), $test)) return($this->setErrorFromCodelib('system-error',$firebase->errorMsg));

        // --- storing a string ---
        if(!$firebase->set(DEFAULT_PATH . '/name/contact001', "John Doe")) return($this->setErrorFromCodelib('system-error',$firebase->errorMsg));


        // --- reading the stored string ---
        if(!($name = $firebase->get(DEFAULT_PATH . '/name/contact001'))) return($this->setErrorFromCodelib('system-error',$firebase->errorMsg));

        $this->addReturnData($name);




    }
}