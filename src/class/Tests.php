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
        var $headers = [];

        function sendTerminal($info) {
            if(is_string($info)) echo $info;
            else print_r($info);
            echo "\n";
        }

        function wants($wish) {
            $this->sendTerminal("\n".'** This test wants '.$wish);
        }

        function says($something) {
            if(is_array($something)) $something = json_encode($something,JSON_PRETTY_PRINT);
            $this->sendTerminal("\n".'   '.$something);
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
            if(empty($server)) $this->addsError('You can not connect into empty url');
            if(!($headers = $this->core->request->getUrlHeaders($server))) $this->addsError('You have provided a wrong url: '.$server);
            echo "   ".$headers[0]."\n";
            $this->server = $server;
        }

        function gets($url,$data=null,$raw=false)
        {
            if(!$this->server) $this->addsError('Missing server. User $this->connects($server) first.');
            echo "   ** Test gets info from ".$this->server.$url."\n";
            $this->response = $this->core->request->get($this->server.$url,$data,$this->headers,$raw);
            if(!$this->response )
                if($this->core->errors->lines) $this->addsError("Error connecting  [{$this->core->errors->data[0]}]");

            $this->response_headers = $this->core->request->responseHeaders;

        }

        function posts($url,$data=null,$raw=false)
        {
            if(!$this->server) $this->addsError('Missing server. User $this->connects($server) first.');
            echo "   ** Test posts info into ".$this->server.$url."\n";
            $this->response = $this->core->request->post($this->server.$url,$data,$this->headers,$raw);
            if(!$this->response )
                if($this->core->errors->lines) $this->addsError("Error connecting  [{$this->core->errors->data[0]}]");

            $this->response_headers = $this->core->request->responseHeaders;

        }

        function checksIfResponseCodeIs($code) {
            if(!$this->response) $this->addsError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if response code is $code: ";

            if(strpos($this->response_headers[0]," {$code} ")===false) $this->addsError('Failing checksIfResponseCodeIs. Response is: '.$this->response_headers[0]);
            echo "[OK]\n";
            return(!$this->error);
        }

        function checksIfResponseContainsJSON($json) {
            if(!$this->response) $this->addsError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if json returned match with JSON pattern: ";
            if(!($response = json_decode($this->response,true))) $this->addsError('returned data is not a JSON');
            if(is_string($json)) $json = json_decode($json,true);
            if(!is_array($json)) $this->addsError('pattern is not array nor json');
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
            else $this->addsError('Failing checksIfResponseContainsJSON.');
            echo "\n";
            return(!$this->error);
        }


        function checksIfResponseContains($text) {
            if(!is_array($text)) $text=[$text];
            if(!$this->server) $this->addsError('Missing server. User $this->connects($server) first.');
            if(!$this->response) $this->addsError('Missing response. User $this->(gets/posts/puts/deletes) first.');
            echo "      cheks if response contains: ".json_encode($text);
            foreach ($text as $item) {
                if(strpos($this->response,$item)===false) $this->addsError('Failing check.');
            }
            echo " [OK]\n";
            return(!$this->error);
        }

        function addsError($error) {
            if(is_array($error)) $error = json_encode($error,JSON_PRETTY_PRINT);
            echo("\n\n   the test failed: {$error}\n\n");
            if($this->core->errors->lines) {
                _printe($this->core->errors->data);
            }
            die();
        }

        function addsHeaders($headers) {
            $i=0;
            foreach ($headers as $key=>$header) {
                    echo ($i++)?", {$key}":"   [Adding header {$key} and value {$header}]";
                    $this->headers[$key] = $header;
            }
            echo "\n";

        }
    }
}
