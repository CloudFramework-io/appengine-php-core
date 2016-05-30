<?php

// CloudSQL Class v10
if (!defined("_RESTfull_CLASS_")) {
    define("_RESTfull_CLASS_", TRUE);

    class RESTful
    {

        var $formParams = array();
        var $rawData = array();
        var $params = array();
        var $error = 0;
        var $ok = 200;
        var $errorMsg = [];
        var $header = '';
        var $requestHeaders = array();
        var $method = '';
        var $contentTypeReturn = 'JSON';
        var $url = '';
        var $urlParams = '';
        var $returnData = null;
        var $auth = true;
        var $referer = null;

        var $service = '';
        var $serviceParam = '';
        var $org_id = '';
        var $rewrite = [];
        var $core = null;

        function RESTful(Core &$core, $apiUrl = '/h/api')
        {


            $this->core = $core;

            // FORCE Ask to the browser the basic Authetication
            if (isset($_REQUEST['_forceBasicAuth'])) {
                if (($_REQUEST['_forceBasicAuth'] !== '0' && !$this->core->security->existBasicAuth())
                    || ($_REQUEST['_forceBasicAuth'] === '0' && $this->core->security->existBasicAuth())

                ) {
                    header('WWW-Authenticate: Basic realm="Test Authentication System"');
                    header('HTTP/1.0 401 Unauthorized');
                    echo "You must enter a valid login ID and password to access this resource\n";
                    exit;

                }
            }
            $this->core->__p->add("RESTFull: ", __FILE__, 'note');

            // $this->requestHeaders = apache_request_headers();
            $this->method = (strlen($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : 'GET';
            if ($this->method == 'GET') {
                $this->formParams = &$_GET;
                if (isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_GET['_raw_input_'], true)) : json_decode($_GET['_raw_input_'], true);
            } else {
                if (count($_GET)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParam, $_GET) : $_GET;
                if (count($_POST)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $_POST) : $_POST;
                if (isset($_POST['_raw_input_']) && strlen($_POST['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_POST['_raw_input_'], true)) : json_decode($_POST['_raw_input_'], true);
                if (isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_GET['_raw_input_'], true)) : json_decode($_GET['_raw_input_'], true);

                // raw data.
                $input = file_get_contents("php://input");
                if (strlen($input)) {
                    $this->formParams['_raw_input_'] = $input;

                    if (is_object(json_decode($input))) {
                        $input_array = json_decode($input, true);
                    } else {
                        parse_str($input, $input_array);
                    }
                    if (is_array($input_array))
                        $this->formParams = array_merge($this->formParams, $input_array);
                    else {
                        $this->setError('Wrong JSON: ' . $input, 400);
                    }
                    unset($input_array);
                    /*
                   if(strpos($this->requestHeaders['Content-Type'], 'json')) {
                   }
                     *
                     */
                }
            }


            // URL splits
            $this->url = $_SERVER['REQUEST_URI'];
            $this->urlParams = '';
            if (strpos($_SERVER['REQUEST_URI'], '?') !== false)
                list($this->url, $this->urlParams) = explode('?', $_SERVER['REQUEST_URI'], 2);

            // API URL Split
            list($foo, $url) = explode($apiUrl . '/', $this->url, 2);
            $this->service = $url;
            $this->serviceParam = '';
            $this->params = [];

            if (strpos($url, '/') !== false) {
                list($this->service, $this->serviceParam) = explode('/', $url, 2);
                $this->service = strtolower($this->service);
                $this->params = explode('/', $this->serviceParam);
            }

        }

        function sendCorsHeaders($methods = 'GET,POST,PUT', $origin = '')
        {

            // Rules for Cross-Domain AJAX
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
            // $origin =((strlen($_SERVER['HTTP_ORIGIN']))?preg_replace('/\/$/', '', $_SERVER['HTTP_ORIGIN']):'*')
            if (!strlen($origin)) $origin = ((strlen($_SERVER['HTTP_ORIGIN'])) ? preg_replace('/\/$/', '', $_SERVER['HTTP_ORIGIN']) : '*');
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: $methods");
            header("Access-Control-Allow-Headers: Content-Type,Authorization,X-CloudFrameWork-AuthToken,X-CLOUDFRAMEWORK-SECURITY");
            header("Access-Control-Allow-Credentials: true");
            header('Access-Control-Max-Age: 1000');

            // To avoid angular Cross-Reference
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header("HTTP/1.1 200 OK");
                exit();
            }


        }

        function setAuth($val, $msg = '')
        {
            if (!$val) {
                $this->setError($msg, 401);
            }
        }


        function checkMethod($methods, $msg = '')
        {
            if (strpos(strtoupper($methods), $this->method) === false) {
                if (!strlen($msg)) $msg = 'Method ' . $this->method . ' is not supported';
                $this->setError($msg, 405);
            }
            return ($this->error === 0);
        }

        function checkMandatoryFormParam($keys, $msg = '', $min_length = 1)
        {
            if (!is_array($keys) && strlen($keys)) $keys = array($keys);
            if (is_array($keys))
                foreach ($keys as $i => $key) {
                    if(isset($this->formParams[$key]) && is_string($this->formParams[$key]))
                        $this->formParams[$key] = trim($this->formParams[$key]);
                    if (!isset($this->formParams[$key]) 
                        || (is_string($this->formParams[$key]) && strlen($this->formParams[$key]) < $min_length)
                        || (is_array($this->formParams[$key]) && !count($this->formParams[$key]))) {
                        if (!strlen($msg))
                            $msg = "{{$key}}" . ((!isset($this->formParams[$key]))?' form-param missing ':' form-params\' length is less than: '.$min_length);
                        $this->setError($msg);
                        break;
                    }
                }
            return ($this->error === 0);
        }

        function checkMandatoryParam($pos, $msg = '')
        {
            if (!isset($this->params[$pos]) || !strlen($this->params[$pos])) {
                $this->setError(($msg == '') ? 'param ' . $pos . ' is mandatory' : $msg);
            }
            return ($this->error === 0);
        }

        function setError($value, $key = 400)
        {
            $this->error = $key;
            $this->errorMsg[] = $value;
            $this->core->errors->add($value);
        }

        function addHeader($key, $value)
        {
            $this->header[$key] = $value;
        }

        function setReturnFormat($method)
        {
            switch (strtoupper($method)) {
                case 'JSON':
                case 'TEXT':
                case 'HTML':
                    $this->contentTypeReturn = strtoupper($method);
                    break;
                default:
                    $this->contentTypeReturn = 'JSON';
                    break;
            }
        }

        function getRequestHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        function getResponseHeaders()
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }


        function sendHeaders()
        {
            $header = $this->getResponseHeader();
            if (strlen($header)) header($header);
            switch ($this->contentTypeReturn) {
                case 'JSON':
                    header("Content-Type: application/json");

                    break;
                case 'TEXT':
                    header("Content-Type: text/plain");

                    break;
                case 'HTML':
                    header("Content-Type: text/html");

                    break;
                default:
                    header("Content-Type: text/html");
                    break;
            }


        }

        function setReturnResponse($response)
        {
            $this->returnData = $response;
        }

        function updateReturnResponse($response)
        {
            if (is_array($response))
                foreach ($response as $key => $value) {
                    $this->returnData[$key] = $value;
                }
        }

        function rewriteReturnResponse($response)
        {
            $this->rewrite = $response;
        }

        function setReturnData($data)
        {
            $this->returnData['data'] = $data;
        }

        function addReturnData($value)
        {
            if (!isset($this->returnData['data'])) $this->setReturnData($value);
            else {
                if (!is_array($value)) $value = array($value);
                if (!is_array($this->returnData['data'])) $this->returnData['data'] = array($this->returnData['data']);
                $this->returnData['data'] = array_merge($this->returnData['data'], $value);
            }
        }

        function getReturnCode()
        {
            return (($this->error) ? $this->error : $this->ok);
        }

        function setReturnCode($code)
        {
            $this->ok = $code;
        }

        function getResponseHeader()
        {
            switch ($this->getReturnCode()) {
                case 201:
                    $ret = ("HTTP/1.0 201 Created");
                    break;
                case 204:
                    $ret = ("HTTP/1.0 204 No Content");
                    break;
                case 405:
                    $ret = ("HTTP/1.0 405 Method Not Allowed");
                    break;
                case 400:
                    $ret = ("HTTP/1.0 400 Bad Request");
                    break;
                case 401:
                    $ret = ("HTTP/1.0 401 Unauthorized");
                    break;
                case 404:
                    $ret = ("HTTP/1.0 404 Not Found");
                    break;
                case 503:
                    $ret = ("HTTP/1.0 503 Service Unavailable");
                    break;
                default:
                    if ($this->error) $ret = ("HTTP/1.0 " . $this->error);
                    else $ret = ("HTTP/1.0 200 OK");
                    break;
            }
            return ($ret);
        }

        function checkCloudFrameWorkSecurity($time = 0, $id = '')
        {
            $ret = false;
            $info = $this->core->security->checkCloudFrameWorkSecurity($time); // Max. 10 min for the Security Token and return $this->getConf('CLOUDFRAMEWORK-ID-'.$id);
            if (false === $info) $this->setError($this->core->logs->get(), 401);
            else {
                $ret = true;
                $response['SECURITY-MODE'] = 'CloudFrameWork Security';
                $response['SECURITY-ID'] = $info['SECURITY-ID'];
                $response['SECURITY-EXPIRATION'] = ($info['SECURITY-EXPIRATION']) ? round($info['SECURITY-EXPIRATION']) . ' secs' : 'none';
                $this->setReturnResponse($response);
            }
            return $ret;
        }

        function existBasicAuth()
        {
            return ($this->core->security->existBasicAuth() && $this->core->security->existBasicAuthConfig());
        }

        function checkBasicAuthSecurity()
        {
            $ret = false;
            if (false === ($basic_info = $this->core->security->checkBasicAuthWithConfig())) {
                $this->setError($this->core->logs->get(), 401);
            } elseif (!isset($basic_info['id'])) {
                $this->setError('Missing "id" parameter in authorizations config file', 401);
            } elseif (!is_array($info = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $basic_info['id']))) {
                $this->setError('Missing config CLOUDFRAMEWORK-ID-{id} specified in "authorizations" config paramater', 503);
            } else {
                $ret = true;
                $response['SECURITY-MODE'] = 'Basic Authorization: ' . $basic_info['_BasicAuthUser_'];
                $response['SECURITY-ID'] = $basic_info['id'];
                $this->setReturnResponse($response);
            }
            return $ret;
        }

        function getCloudFrameWorkSecurityInfo()
        {
            if (isset($this->returnData['SECURITY-ID'])) {
                return $this->core->config->get('CLOUDFRAMEWORK-ID-' . $this->returnData['SECURITY-ID']);
            } else return [];
        }


        function send()
        {
            $ret = array();
            $ret['success'] = ($this->core->errors->lines) ? false : true;
            $ret['status'] = $this->getReturnCode();
            $ret['url'] = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $ret['method'] = $this->method;
            $ret['ip'] = $this->core->system->ip;

            // Debug params
            if (isset($this->formParams['debug'])) {
                $ret['header'] = $this->getResponseHeader();
                $ret['session'] = session_id();
                $ret['ip'] = $this->core->system->ip;
                $ret['user_agent'] = ($this->core->system->user_agent != null) ? $this->core->system->user_agent : $this->requestHeaders['User-Agent'];
                $ret['urlParams'] = $this->params;
                $ret['form-raw Params'] = $this->formParams;
            }

            if (is_array($this->returnData)) $ret = array_merge($ret, $this->returnData);

            if ($this->core->logs->lines) {
                $ret['logs'] = $this->core->logs->data;
            }

            // If I have been called from a queue the response has to be 200 to avoid…
            if (isset($this->formParams['cloudframework_queued'])) {
                if ($this->core->errors->lines) {
                    $ret['queued_return_error'] = $this->core->errors->data;
                    $this->error = 0;
                    $this->ok = 200;
                }
            } elseif ($this->core->errors->lines) {
                $ret['errors'] = $this->core->errors->data;
            }


            $this->sendHeaders();
            $this->core->__p->add("RESTFull: ", '', 'endnote');
            switch ($this->contentTypeReturn) {
                case 'JSON':
                    if (isset($this->formParams['__p']))
                        $ret['__p'] = $this->core->__p->data;

                    $ret['_totTime'] = $this->core->__p->getTotalTime(5) . ' secs';
                    $ret['_totMemory'] = $this->core->__p->getTotalMemory(5) . ' Mb';

                    // If the API->main does not want to send $ret standard it can send its own data
                    if (count($this->rewrite)) $ret = $this->rewrite;
                    die(json_encode($ret));

                    break;
                default:
                    if ($this->core->errors->lines) _printe($this->core->errors->data);
                    else die($this->returnData['data']);
                    break;
            }
        }

        function getHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        function getHeaders()
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }


    } // Class
}
?>