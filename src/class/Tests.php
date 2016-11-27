<?php
include_once __DIR__.'/RESTful.php';
// Tests class.. v1.0
if (!defined("_Tests_CLASS_")) {
    define("_Tests_CLASS_", TRUE);

    class Tests extends RESTful
    {
        var $tests;
        var $server=null;
        var $response = null;
        var $response_headers = null;

        function sendTerminal($info) {
            if(is_string($info)) echo $info;
            else print_r($info);
            echo "\n";
        }

        function wants($wish) {
            $this->sendTerminal('** This test wants '.$wish);
        }

        function prompts($title,$default=null) {
            echo ('   please, this test needs '.$title.(($default)?" [default {$default}]":'').': ');
            $handle = fopen ("php://stdin","r");
            $line = trim(fgets($handle));
            fclose($handle);
            if(empty($line)) $line = $default;
            return $line;
        }

        function connects($server) {
            if(empty($server)) $this->addError('You can not connect into empty url');
            if(!($headers = $this->core->request->getUrlHeaders($server))) $this->addError('You have provided a wrong url: '.$server);
            echo "   ".$headers[0]."\n";
            $this->server = $server;
        }

        function gets($url,$data=null,$raw=false)
        {
            if(!$this->server) $this->addError('Missing server. User $this->connects($server) first.');
            echo "   ** Test gets info from ".$this->server.$url."\n";
            $this->response = $this->core->request->get($this->server.$url,$data,null,$raw);
            if(!$this->response )
                if($this->core->errors->lines) $this->addError("Error connecting  [{$this->core->errors->data[0]}]");

            $this->response_headers = $this->core->request->responseHeaders;

        }

        function checksIfResponseCodeIs($code) {
            if(!$this->response) $this->addError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if response code is $code: ";

            if(strpos($this->response_headers[0]," {$code} ")===false) $this->addError('Failing checksIfResponseCodeIs. Response is: '.$this->response_headers[0]);
            echo "\n";


        }

        function checksIfResponseContainsJSON($json) {
            if(!$this->response) $this->addError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if json returned match with JSON pattern: ";
            if(!($response = json_decode($this->response,true))) $this->addError('returned data is not a JSON');
            if(is_string($json)) $json = json_decode($json,true);
            if(!is_array($json)) $this->addError('pattern is not array nor json');
            echo json_encode($json);

            function recursiveCheck(&$data,&$pattern)
            {
                foreach ($data as $key=>$info) {
                    if(isset($pattern[$key]) && $pattern[$key]!= $info) return false;
                    elseif(is_array($info)) return(recursiveCheck($info,$pattern));
                }
                return true;
            }
            if(recursiveCheck($response,$json)) echo " [OK]";
            else $this->addError('Failing checksIfResponseContainsJSON.');
            echo "\n";

        }


        function checksIfResponseContains($text) {
            if(!is_array($text)) $text=[$text];
            if(!$this->server) $this->addError('Missing server. User $this->connects($server) first.');
            if(!$this->response) $this->addError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if response contains: ".json_encode($text);
            foreach ($text as $item) {
                if(strpos($this->response,$item)===false) $this->addError('Failing check.');
            }
            echo " [OK]\n";
        }

        function addError($error) {
            echo("\n\n   the test failed: {$error}\n\n");
            if($this->core->errors->lines) {
                _printe($this->core->errors->data);
            }
            die();
        }
    }
}