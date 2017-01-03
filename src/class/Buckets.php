<?php
require_once 'google/appengine/api/cloud_storage/CloudStorageTools.php';
use google\appengine\api\cloud_storage\CloudStorageTools;

// CloudSQL Class v10
if (!defined ("_Buckets_CLASS_") ) {
    define ("_Buckets_CLASS_", TRUE);

    class Buckets {

        private $core;
        var $bucket = '';
        var $error = false;
        var $errorMsg = array();
        var $max = array();
        var $uploadedFiles = array();
        var $isUploaded = false;


        function Bucket(Core &$core,$bucket='') {
            $this->core = $core;

            if(strlen($bucket)) $this->bucket = $bucket;
            else $this->bucket = CloudStorageTools::getDefaultGoogleStorageBucketName();
            $this->vars['upload_max_filesize'] = ini_get('upload_max_filesize');
            $this->vars['max_file_uploads'] = ini_get('max_file_uploads');
            $this->vars['file_uploads'] = ini_get('file_uploads');
            $this->vars['default_bucket'] = $this->bucket;
            _printe($this->core->system->url);
            $this->vars['retUploadUrl'] = $adnbp->_url;


            if(count($_FILES)) {
                foreach ($_FILES as $key => $value) {
                    if(is_array($value['name'])) {
                        for($j=0,$tr2=count($value['name']);$j<$tr2;$j++) {
                            foreach ($value as $key2 => $value2) {
                                $this->uploadedFiles[$key][$j][$key2] = $value[$key2][$j];
                            }
                        }
                    } else {
                        $this->uploadedFiles[$key][0] = $value;
                    }
                    $this->isUploaded = true;
                }
            }
        }

        function deleteUploadFiles() {
            if(strlen($_FILES['uploaded_files']['tmp_name'])) unlink($_FILES['uploaded_files']['tmp_name']);
        }
        function manageUploadFiles($dest_bucket='',$public=true) {
            // $gs_name = $_FILES['uploaded_files']['tmp_name'];
            // move_uploaded_file($gs_name, 'gs://my_bucket/new_file.txt');
            if($this->isUploaded)  {
                foreach ($this->uploadedFiles as $key => $files) {
                    for($i=0,$tr=count($files);$i<$tr;$i++) {
                        $value = $files[$i];
                        if(!$value['error']) {
                            if(!strlen($dest_bucket)) $dest = 'gs://'.$this->bucket.'/'.$value['name'];
                            else $dest.'/'.$value['name'];
                            try {
                                if($public) {
                                    stream_context_set_default(array('gs'=>array('acl'=>'public-read')));
                                }
                                if(copy($value['tmp_name'],$dest)) {
                                    $this->uploadedFiles[$key][$i]['movedTo'] = $dest;
                                    if($public)
                                        $this->uploadedFiles[$key][$i]['publicUrl'] = $this->getPublicUrl($dest);
                                } else {
                                    $this->addError(error_get_last());
                                    $this->uploadedFiles[$key][$i]['error'] = $this->errorMsg;
                                }


                            }catch(Exception $e) {
                                $this->addError($e->getMessage());
                                $this->addError(error_get_last());
                                $this->uploadedFiles[$key][$i]['error'] = $this->errorMsg;
                            }
                        }
                    }

                }

            }
            return($this->uploadedFiles);
        }

        function getPublicUrl($file) {
            global $adnbp;
            $ret = 'bucket missing';
            if(strlen($this->bucket)) {
                if(strpos($file,'gs://')!==0 ) {
                    $ret  = $adnbp->url['host_url_full'].str_replace($_SERVER['DOCUMENT_ROOT'], '',$file);
                } else
                    $ret =  CloudStorageTools::getPublicUrl($file,false);
            } return $ret;
        }
        function scan($path='') {
            $ret = array();
            $tmp = scandir('gs://'.$this->bucket.$path);
            foreach ($tmp as $key => $value) {
                $ret[$value] = array('type'=>(is_file('gs://'.$this->bucket.$path.'/'.$value))?'file':'dir');
                if(isset($_REQUEST['__p'])) __p('is_dir: '.'gs://'.$this->bucket.$path.'/'.$value);
            }
            return($ret);
        }
        function fastScan($path='') {
            return(scandir('gs://'.$this->bucket.$path));
        }

        function deleAllFiles($path='') { $this->deleteFiles($path,'*');}
        function deleteFiles($path='',$file) {
            if(is_array($file)) $files=$file;
            else if($file == '*') $files = $this->fastScan($path);
            else $file[] = file;
            foreach ($files as $key => $value) {
                $value = 'gs://'.$this->bucket.$path.'/'.$value;
                $ret[$value] = 'ignored';
                if(is_file($value)) {
                    $ret[$value] = 'deleting: '.unlink($value);
                }
            }
            return($ret);
        }

        function rmdir($path='')  {
            $value = 'gs://'.$this->bucket.$path;
            $ret = false;
            try {
                $ret = rmdir($value);
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return $ret;
        }

        function mkdir($path='')  {
            $value = 'gs://'.$this->bucket.$path;
            $ret = false;
            try {
                $ret = @mkdir($value);
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return $ret;
        }

        function isDir($path='')  {
            $value = 'gs://'.$this->bucket.$path;
            return(is_dir($value));
        }

        function isFile($file='')  {
            $value = 'gs://'.$this->bucket.$file;
            return(is_file($value));
        }

        function isMkdir($path='')  {
            $value = 'gs://'.$this->bucket.$path;
            $ret = is_dir($value);
            if(!$ret) try {
                $ret = @mkdir($value);
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return $ret;
        }


        function putContents($file, $data, $path='',$ctype = 'text/plain' ) {

            $options = array('gs' => array('Content-Type' => $ctype));
            $ctx = stream_context_create($options);

            $ret = false;
            try{
                if(@file_put_contents('gs://'.$this->bucket.$path.'/'.$file, $data,0,$ctx) === false) {
                    $this->addError(error_get_last());
                }
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
        }

        function getContents($file,$path='') {
            $ret = '';
            try{
                $ret = @file_get_contents('gs://'.$this->bucket.$path.'/'.$file);
                if($ret=== false) {
                    $this->addError(error_get_last());
                }
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return($ret);
        }

        function getUploadUrl() {
            $options = array( 'gs_bucket_name' => $this->bucket );
            $upload_url = CloudStorageTools::createUploadUrl($this->vars['retUploadUrl'], $options);
            return($upload_url);
        }


        function setError($msg) {
            $this->errorMsg = array();
            $this->addError($msg);
        }
        function addError($msg) {
            $this->error = true;
            $this->errorMsg[] = $msg;
        }
    }
}