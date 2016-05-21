<?php
###########################################################
# Madrid Feb 3th 2014
# ADNBP Cloud Frameworl
# http://www.adnbp.com (support@adnbp.com)
###########################################################
/**
 * Paquete de clases de utilidad general
 * @version 1.0.0
 * @author Hector López <hlopez@adnbp.com>
 * @package com.adnbp.class.email
 */

require_once 'google/appengine/api/mail/Message.php';
use google\appengine\api\mail\Message;


if (!defined ("_GOOGLEAPPSEMAIL_CLASS_") ) {
    define ("_GOOGLEAPPSEMAIL_CLASS_", TRUE);

    /**
     * @class GoogleAppsEmail
     * Let's send emails
     *
     */
    class Email{

        var $data = array();
        var $errorMsg = '';
        var $error = false;
        var $_debug = false;
        var $_sengrid = null;
        var $_sengridmail = null;
        var $core = null;

        function Email (Core &$core,$params=[]) {
            global $adnbp;
            $from = (isset($params[0]))?$params[0]:'';
            $subject = (isset($params[1]))?$params[1]:'';
            $text = (isset($params[2]))?$params[2]:'';
            $thtml = (isset($params[3]))?$params[3]:'';

            $this->core = $core;
            if(is_array($from)) {
                $this->setFrom($from[0]);
                if(strlen($from[1]))
                    $this->setFromName($from[1]);
            } else
                if(!strlen($from) && strlen($this->core->config->get('defaultEmailSender')))
                    $this->setFrom($this->core->config->get("defaultEmailSender"));
                else
                    $this->setFrom($from);

            $this->setSubject($subject);
            $this->setTextBody($text);
            $this->setHtmlBody($thtml);

        }

        function getError() { return($this->errorMsg); }
        function isError() { return($this->error === true); }
        function setError($msg) { $this->errorMsg = $msg; $this->error = true;$this->core->errors->add($msg);}
        function setFrom($txt) { $this->data['sender'] = $txt; }
        function setFromName($txt) { $this->data['senderName'] = $txt; }
        function setSubject($txt) { $this->data['subject'] = $txt; }
        function setTextBody($txt) { $this->data['textBody'] = $txt; }
        function setText($txt) { $this->data['textBody'] = $txt; }
        function setCc($cc) { $this->data['cc'] = $cc; }
        function setBcc($bcc) { $this->data['bcc'] = $bcc; }

        function setTo($txt) { $this->data['to'] = $txt; }

        function setHtmlBody($txt) { $this->data['htmlBody']= $txt; }
        function setHtml($txt) { $this->data['htmlBody']= $txt; }

        function setHtmlTemplate($txt) {
            $this->data['htmlTemplate']= $txt;
            if(is_file("./templates/$txt")) {
                $this->setHtmlBody(file_get_contents("./templates/$txt"));
            } else {
                $this->setError("Template $txt no found");
            }
        }

        function useSendGridCredentials($user='',$passw='') {

            if(!strlen($user) && is_object($adnbp)) {
                $user = $this->core->config->get("sendGridUser");
                $passw = $this->core->config->get("sendGridPassword");
            }
            $_ret = true;
            if(!strlen($user) || !strlen($passw) ) {
                $_ret = false;
                $this->setError("No sendGridUser and/or sendGridPassword credentials");
            } else {
                $this->_sendgrid = new SendGrid($user, $passw);
                $this->_sendgridmail     = new SendGridMail();
            }
            return($_ret);
            // $this->_sendgridmail->addCc($email);
            // $this->_sendgridmail->addBcc($email);
        }

        function checkValidEmail($email) {

            $_ret = true;
            if(!is_array($email)) $email = array($email);
            for($i=0,$tr=count($email);$i<$tr;$i++) {
                if (! filter_var($email[$i], FILTER_VALIDATE_EMAIL) !== false) {
                    $_ret =  false;
                }
            }
            return $_ret;
        }


        function setDebug($boolean) { $this->_debug= $boolean; }
        function isDebug() { return($this->_debug); }

        function send($to) {

            if($this->isError()) return(false);

            // Passing $to into an array
            if(!is_array($to) and strlen($to)) {
                if(!strpos($to, ",")) $to = array($to);
                else $to = explode(",",$to);
            }

            if(!$this->checkValidEmail($to)){
                $this->setError("Invalid email/s in 'To' email: ".print_r($to,true));
                return(false);
            }

            if(!$this->checkValidEmail($this->data['sender'])){
                $this->setError("Error in 'From' email: ".$this->data['sender']);
                return(false);
            }


            // Checking if everything is OK
            if(!strlen($this->data['sender'])) {
                $this->setError("Sender missing. Use setFrom(email) method.");
                return(false);
            }

            if(!strlen($this->data['textBody']) && !strlen($this->data['htmlBody'])) {
                $this->setError("Text or HTML Body missing. Use setTextBody(txt) or setHtmlBody(html) methods.");
                return(false);
            }

            if(!strlen($this->data['subject'])) {
                $this->setError("Subjectmissing. Use setSubject(txt) method.");
                return(false);
            }

            if(is_object($this->_sendgrid)) {

                $this->core->__p->add('Email send with sendgrid ', $this->entity_name, 'note');
                $this->_sendgridmail->setFrom($this->data['sender']);
                if(strlen($this->data['senderName'])) $this->_sendgridmail->setFromName($this->data['senderName']);
                $this->_sendgridmail->setSubject($this->data['subject']);
                if(strlen($this->data['htmlBody'])) $this->_sendgridmail->setHtml($this->data['htmlBody']);
                if(strlen($this->data['textBody'])) $this->_sendgridmail->setText($this->data['textBody']);
                $this->_sendgridmail->setTos($to);
                if(strlen($this->data['cc'])) $this->_sendgridmail->setCc($this->data['cc']);
                if(strlen($this->data['bcc'])) $this->_sendgridmail->setBcc($this->data['bcc']);
                $res = $this->_sendgrid->send($this->_sendgridmail);

                $ret =false;
                if($res === FALSE) {
                    $this->setError("Probably wrong user and/or password");
                    return(false);
                } else {
                    $res = json_decode($res);
                    if($res->message == "success")
                        $ret = true;
                    else {
                        $this->setError("<pre>".print_r($res,true)."</pre>");
                    }
                }
                $this->core->__p->add('Email send with sendgrid ', '', 'endnote');
                return $ret;

            } else {

                $this->core->__p->add('Email send with appengine ', $this->entity_name, 'note');
                $message = new Message();
                $message->setSender($this->data['sender']);
                $message->setSubject($this->data['subject']);
                if(strlen($this->data['htmlBody'])) $message->setHtmlBody($this->data['htmlBody']);
                if(strlen($this->data['textBody'])) $message->setTextBody($this->data['textBody']);
                //if(strlen($this->_data['cc'])) $message->setCc($this->_data['cc']);
                //if(strlen($this->_data['bcc'])) $message->setBcc($this->_data['bcc']);

                $message->addTo($to);
                $ret = false;
                try {
                    if($this->isDebug()) echo "<li> Email Debug: Sending message: <pre>".htmlentities(print_r($message,true))."</pre>";
                    $message->send();
                    if($this->isDebug()) echo "<li> Email Debug: OK";
                    $ret = true;
                } catch (InvalidArgumentException $e) {
                    $this->setError($e);
                    if($this->isDebug()) echo "<li> Email Debug: Error $e";
                }
                $this->core->__p->add('Email send with appengine ', '', 'note');
                return $ret;
            }


        }
    }

    class SendGrid
    {
        const VERSION = "1.0.0";
        protected $namespace = "SendGrid";
        protected $domain = "https://sendgrid.com/";
        protected $endpoint = "api/mail.send.json";
        protected $username,
            $password;

        public function __construct($username, $password)
        {
            $this->username = $username;
            $this->password = $password;
        }

        /**
         * _prepMessageData
         * Takes the mail message and returns a url friendly querystring
         *
         * @param  Mail   $mail [description]
         * @return String - the data query string to be posted
         */
        protected function _prepMessageData(SendGridMail $mail)
        {

            /* the api expects a 'to' parameter, but this parameter will be ignored
             * since we're sending the recipients through the header. The from
             * address will be used as a placeholder.
             */
            $params =
                array(
                    'api_user'  => $this->username,
                    'api_key'   => $this->password,
                    'subject'   => $mail->getSubject(),
                    'from'      => $mail->getFrom(),
                    'to'        => $mail->getFrom(),
                    'x-smtpapi' => $mail->getHeadersJson()
                );

            if($mail->getHtml()) {
                $params['html'] = $mail->getHtml();
            }

            if($mail->getText()) {
                $params['text'] = $mail->getText();
            }

            if(($fromname = $mail->getFromName())) {
                $params['fromname'] = $fromname;
            }

            if(($replyto = $mail->getReplyTo())) {
                $params['replyto'] = $replyto;
            }

            // determine if we should send our recipients through our headers,
            // and set the properties accordingly
            if($mail->useHeaders())
            {
                // workaround for posting recipients through SendGrid headers
                $headers = $mail->getHeaders();
                $headers['to'] = $mail->getTos();
                $mail->setHeaders($headers);

                $params['x-smtpapi'] = $mail->getHeadersJson();
            }
            else
            {
                $params['to'] = $mail->getTos();
            }


            if($mail->getAttachments())
            {
                if (function_exists("curl_file_create")) {
                    foreach($mail->getAttachments() as $attachment)
                    {
                        $params['files['.$attachment['filename'].'.'.$attachment['extension'].']'] = new \CURLFile($attachment['file']);
                    }
                }else{
                    foreach($mail->getAttachments() as $attachment)
                    {
                        $params['files['.$attachment['filename'].'.'.$attachment['extension'].']'] = '@'.$attachment['file'];
                    }
                }
            }

            return $params;
        }

        /**
         * _arrayToUrlPart
         * Converts an array to a url friendly string
         *
         * @param  array  $array - the array to convert
         * @param  String $token - the name of parameter
         * @return String        - a url part that can be concatenated to a url request
         */
        protected function _arrayToUrlPart($array, $token)
        {
            $string = "";

            if ($array)
            {
                foreach ($array as $value)
                {
                    $string.= "&" . $token . "[]=" . urlencode($value);
                }
            }

            return $string;
        }

        /**
         * send
         * Send an email
         *
         * @param  Mail    $mail  The message to send
         * @return String         the json response
         */
        public function send(SendGridMail $mail)
        {
            $data = $this->_prepMessageData($mail);

            $request = $this->domain . $this->endpoint;

            // we'll append the Bcc and Cc recipients to the url endpoint (GET)
            // so that we can still post attachments (via cURL array).
            $request.= "?" .
                substr($this->_arrayToUrlPart($mail->getBccs(), "bcc"), 1) .
                $this->_arrayToUrlPart($mail->getCcs(), "cc");

            $context = array("http"=>
                array("method" => "POST",
                    "content" => http_build_query($data)));

            $context = stream_context_create($context);
            $response = @file_get_contents($request, false, $context);

            return $response;
        }
    }

    class SendGridMail
    {

        private $to_list,
            $from,
            $from_name,
            $reply_to,
            $cc_list,
            $bcc_list,
            $subject,
            $text,
            $html,
            $attachment_list,
            $header_list = array();

        protected $use_headers;

        public function __construct()
        {
            $this->from_name = false;
            $this->reply_to = false;
            $this->setCategory("google_sendgrid_php_lib");
        }

        /**
         * _removeFromList
         * Given a list of key/value pairs, removes the associated keys
         * where a value matches the given string ($item)
         *
         * @param Array  $list - the list of key/value pairs
         * @param String $item - the value to be removed
         */
        private function _removeFromList(&$list, $item, $key_field = null)
        {
            foreach ($list as $key => $val)
            {
                if($key_field)
                {
                    if($val[$key_field] == $item)
                    {
                        unset($list[$key]);
                    }
                }
                else
                {
                    if ($val == $item)
                    {
                        unset($list[$key]);
                    }
                }
            }
            // repack the indices
            $list = array_values($list);
        }

        /**
         * getTos
         * Return the list of recipients
         *
         * @return list of recipients
         */
        public function getTos()
        {
            return $this->to_list;
        }

        /**
         * setTos
         * Initialize an array for the recipient 'to' field
         * Destroy previous recipient 'to' data.
         *
         * @param  Array         $email_list   an array of email addresses
         * @return SendGridMail               the SendGrid\Mail object.
         */
        public function setTos(array $email_list)
        {
            $this->to_list = $email_list;

            return $this;
        }

        /**
         * setTo
         * Initialize a single email for the recipient 'to' field
         * Destroy previous recipient 'to' data.
         *
         * @param  String         $email  a list of email addresses
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setTo($email)
        {
            $this->to_list = array($email);

            return $this;
        }

        /**
         * addTo
         * append an email address to the existing list of addresses
         * Preserve previous recipient 'to' data.
         *
         * @param  String         $email  a single email address
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function addTo($email, $name=null)
        {
            $this->to_list[] = ($name ? $name . "<" . $email . ">" : $email);

            return $this;
        }

        /**
         * removeTo
         * remove an email address from the list of recipient addresses
         *
         * @param  String         $search_term  the regex value to be removed
         * @return SendGridMail                the SendGrid\Mail object.
         */
        public function removeTo($search_term)
        {
            $this->to_list = array_values(array_filter($this->to_list, function($item) use($search_term) {
                return !preg_match("/" . $search_term . "/", $item);
            }));

            return $this;
        }

        /**
         * getFrom
         * get the from email address
         *
         * @param   Boolean $as_array   return the from as an assocative array
         * @return                      the from email address
         */
        public function getFrom($as_array = false)
        {
            if ($as_array && ($name = $this->getFromName())) {
                return array("$this->from" => $name);
            } else {
                return $this->from;
            }
        }

        /**
         * setFrom
         * set the from email
         *
         * @param  String         $email  an email address
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setFrom($email)
        {
            $this->from = $email;

            return $this;
        }

        /**
         * getFromName
         * get the from name
         *
         * @return the from name
         */
        public function getFromName()
        {
            return $this->from_name;
        }

        /**
         * setFromName
         * set the name appended to the from email
         *
         * @param  String        $name  a name to append
         * @return SendGridMail        the SendGrid\Mail object.
         */
        public function setFromName($name)
        {
            $this->from_name = $name;

            return $this;
        }

        /**
         * getReplyTo
         * get the reply-to address
         *
         * @return the reply to address
         */
        public function getReplyTo()
        {
            return $this->reply_to;
        }

        /**
         * setReplyTo
         * set the reply-to address
         *
         * @param  String         $email  the email to reply to
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setReplyTo($email)
        {
            $this->reply_to = $email;

            return $this;
        }
        /**
         * getCc
         * get the Carbon Copy list of recipients
         *
         * @return Array the list of recipients
         */
        public function getCcs()
        {
            return $this->cc_list;
        }

        /**
         * setCcs
         * Set the list of Carbon Copy recipients
         *
         * @param  String         $email  a list of email addresses
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setCcs(array $email_list)
        {
            $this->cc_list = $email_list;

            return $this;
        }

        /**
         * setCc
         * Initialize the list of Carbon Copy recipients
         * destroy previous recipient data
         *
         * @param  String         $email  a list of email addresses
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setCc($email)
        {
            $this->cc_list = array($email);

            return $this;
        }

        /**
         * addCc
         * Append an address to the list of Carbon Copy recipients
         *
         * @param  String         $email  an email address
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function addCc($email)
        {
            $this->cc_list[] = $email;

            return $this;
        }

        /**
         * removeCc
         * remove an address from the list of Carbon Copy recipients
         *
         * @param  String        $email  an email address
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function removeCc($email)
        {
            $this->_removeFromList($this->cc_list, $email);

            return $this;
        }

        /**
         * getBccs
         * return the list of Blind Carbon Copy recipients
         *
         * @return Array - the list of Blind Carbon Copy recipients
         */
        public function getBccs()
        {
            return $this->bcc_list;
        }

        /**
         * setBccs
         * set the list of Blind Carbon Copy Recipients
         *
         * @param  Array         $email_list  the list of email recipients to
         * @return SendGridMail              the SendGrid\Mail object.
         */
        public function setBccs($email_list)
        {
            $this->bcc_list = $email_list;

            return $this;
        }

        /**
         * setBcc
         * Initialize the list of Carbon Copy recipients
         * destroy previous recipient Blind Carbon Copy data
         *
         * @param  String        $email  an email address
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function setBcc($email)
        {
            $this->bcc_list = array($email);

            return $this;
        }

        /**
         * addBcc
         * Append an email address to the list of Blind Carbon Copy recipients
         *
         * @param  String         $email  an email address
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function addBcc($email)
        {
            $this->bcc_list[] = $email;

            return $this;
        }

        /**
         * removeBcc
         * remove an email address from the list of Blind Carbon Copy addresses
         *
         * @param  String         $email  the email to remove
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function removeBcc($email)
        {
            $this->_removeFromList($this->bcc_list, $email);

            return $this;
        }

        /**
         * getSubject
         * get the email subject
         *
         * @return the email subject
         */
        public function getSubject()
        {
            return $this->subject;
        }

        /**
         * setSubject
         * set the email subject
         *
         * @param  String        $subject  the email subject
         * @return SendGridMail           the SendGrid\Mail object.
         */
        public function setSubject($subject)
        {
            $this->subject = $subject;

            return $this;
        }

        /**
         * getText
         * get the plain text part of the email
         *
         * @return the plain text part of the email
         */
        public function getText()
        {
            return $this->text;
        }

        /**
         * setText
         * Set the plain text part of the email
         *
         * @param  String        $text  the plain text of the email
         * @return SendGridMail        the SendGrid\Mail object.
         */
        public function setText($text)
        {
            $this->text = $text;

            return $this;
        }

        /**
         * getHtml
         * Get the HTML part of the email
         *
         * @param  String         $html  the HTML part of the email
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function getHtml()
        {
            return $this->html;
        }

        /**
         * setHTML
         * Set the HTML part of the email
         *
         * @param  String         $html  the HTML part of the email
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function setHtml($html)
        {
            $this->html = $html;

            return $this;
        }

        /**
         * getAttachments
         * Get the list of file attachments
         *
         * @return Array of indexed file attachments
         */
        public function getAttachments()
        {
            return $this->attachment_list;
        }

        /**
         * setAttachments
         * add multiple file attachments at once
         * destroys previous attachment data.
         *
         * @param  Array          $files  The list of files to attach
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function setAttachments(array $files)
        {
            $this->attachment_list = array();
            foreach($files as $file)
            {
                $this->addAttachment($file);
            }

            return $this;
        }

        /**
         * setAttachment
         * Initialize the list of attachments, and add the given file
         * destroys previous attachment data.
         *
         * @param  String         $file  the file to attach
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function setAttachment($file)
        {
            $this->attachment_list = array($this->_getAttachmentInfo($file));

            return $this;
        }

        /**
         * addAttachment
         * Add a new email attachment, given the file name.
         *
         * @param  String         $file  The file to attach.
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function addAttachment($file)
        {
            $this->attachment_list[] = $this->_getAttachmentInfo($file);

            return $this;
        }

        /**
         * removeAttachment
         * Remove a previously added file attachment, given the file name.
         *
         * @param  String         $file  the file attachment to remove.
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function removeAttachment($file)
        {
            $this->_removeFromList($this->attachment_list, $file, "file");

            return $this;
        }

        /**
         * get file details
         *
         * @return Array with file details
         */
        private function _getAttachmentInfo($file)
        {
            $info = pathinfo($file);
            $info['file'] = $file;

            return $info;
        }

        /**
         * setCategories
         * Set the list of category headers
         * destroys previous category header data
         *
         * @param  Array          $category_list  the list of category values
         * @return SendGridMail                  the SendGrid\Mail object.
         */
        public function setCategories($category_list)
        {
            $this->header_list['category'] = $category_list;
            $this->addCategory('google_sendgrid_php_lib');

            return $this;
        }

        /**
         * setCategory
         * Clears the category list and adds the given category
         *
         * @param  String        $category  the new category to append
         * @return SendGridMail            the SendGrid\Mail object.
         */
        public function setCategory($category)
        {
            $this->header_list['category'] = array($category);
            $this->addCategory('google_sendgrid_php_lib');

            return $this;
        }

        /**
         * addCategory
         * Append a category to the list of categories
         *
         * @param  String         $category  the new category to append
         * @return SendGridMail             the SendGrid\Mail object.
         */
        public function addCategory($category)
        {
            $this->header_list['category'][] = $category;

            return $this;
        }

        /**
         * removeCategory
         * Given a category name, remove that category from the list
         * of category headers
         *
         * @param  String        $category  the category to be removed
         * @return SendGridMail            the SendGrid\Mail object.
         */
        public function removeCategory($category)
        {
            $this->_removeFromList($this->header_list['category'], $category);

            return $this;
        }

        /**
         * SetSubstitutions
         *
         * Substitute a value for list of values, where each value corresponds
         * to the list emails in a one to one relationship. (IE, value[0] = email[0],
         * value[1] = email[1])
         *
         * @param  Array          $key_value_pairs  key/value pairs where the value is an array of values
         * @return SendGridMail                    the SendGrid\Mail object.
         */
        public function setSubstitutions($key_value_pairs)
        {
            $this->header_list['sub'] = $key_value_pairs;

            return $this;
        }

        /**
         * addSubstitution
         * Substitute a value for list of values, where each value corresponds
         * to the list emails in a one to one relationship. (IE, value[0] = email[0],
         * value[1] = email[1])
         *
         * @param  string         $from_key    the value to be replaced
         * @param  array          $to_values   an array of values to replace the $from_value
         * @return SendGridMail               the SendGrid\Mail object.
         */
        public function addSubstitution($from_value, array $to_values)
        {
            $this->header_list['sub'][$from_value] = $to_values;

            return $this;
        }

        /**
         * setSection
         * Set a list of section values
         *
         * @param  Array         $key_value_pairs
         * @return SendGridMail                  the SendGrid\Mail object.
         */
        public function setSections(array $key_value_pairs)
        {
            $this->header_list['section'] = $key_value_pairs;

            return $this;
        }

        /**
         * addSection
         * append a section value to the list of section values
         *
         * @param  String         $from_value  the value to be replaced
         * @param  String         $to_value    the value to replace
         * @return SendGridMail               the SendGrid\Mail object.
         */
        public function addSection($from_value, $to_value)
        {
            $this->header_list['section'][$from_value] = $to_value;

            return $this;
        }

        /**
         * setUniqueArguments
         * Set a list of unique arguments, to be used for tracking purposes
         *
         * @param  array         $key_value_pairs  list of unique arguments
         * @return SendGridMail                   the SendGrid\Mail object.
         */
        public function setUniqueArguments(array $key_value_pairs)
        {
            $this->header_list['unique_args'] = $key_value_pairs;

            return $this;
        }

        /**
         * addUniqueArgument
         * Set a key/value pair of unique arguments, to be used for tracking purposes
         *
         * @param  string        $key    key
         * @param  string        $value  value
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function addUniqueArgument($key, $value)
        {
            $this->header_list['unique_args'][$key] = $value;

            return $this;
        }

        /**
         * setFilterSettings
         * Set filter/app settings
         *
         * @param  array          $filter_settings  array of fiter settings
         * @return SendGridMail                    the SendGrid\Mail object.
         */
        public function setFilterSettings($filter_settings)
        {
            $this->header_list['filters'] = $filter_settings;

            return $this;
        }

        /**
         * addFilterSetting
         * Append a filter setting to the list of filter settings
         *
         * @param  string         $filter_name     - filter name
         * @param  string         $parameter_name  - parameter name
         * @param  string         $parameter_value - setting value
         * @return SendGridMail                    the SendGrid\Mail object.
         */
        public function addFilterSetting($filter_name, $parameter_name, $parameter_value)
        {
            $this->header_list['filters'][$filter_name]['settings'][$parameter_name] = $parameter_value;

            return $this;
        }

        /**
         * getHeaders
         * return the list of headers
         *
         * @return Array the list of headers
         */
        public function getHeaders()
        {
            return $this->header_list;
        }

        /**
         * getHeaders
         * return the list of headers
         *
         * @return Array the list of headers
         */
        public function getHeadersJson()
        {
            if (count($this->getHeaders()) <= 0)
            {
                return "{}";
            }

            return json_encode($this->getHeaders(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }

        /**
         * setHeaders
         * Sets the list headers
         * destroys previous header data
         *
         * @param  Array          $key_value_pairs - the list of header data
         * @return SendGridMail                     the SendGrid\Mail object.
         */
        public function setHeaders($key_value_pairs)
        {
            $this->header_list = $key_value_pairs;

            return $this;
        }

        /**
         * addHeaders
         * append the header to the list of headers
         *
         * @param String          $key    the header key
         * @param String          $value  the header value
         * @return SendGridMail          the SendGrid\Mail object.
         */
        public function addHeader($key, $value)
        {
            $this->header_list[$key] = $value;

            return $this;
        }

        /**
         * removeHeaders
         * remove a header key
         *
         * @param  String         $key - the key to remove
         * @return SendGridMail         the SendGrid\Mail object.
         */
        public function removeHeader($key)
        {
            unset($this->header_list[$key]);

            return $this;
        }

        /**
         * useHeaders
         * Checks to see whether or not we can or should you headers. In most cases,
         * we prefer to send our recipients through the headers, but in some cases,
         * we actually don't want to. However, there are certain circumstances in
         * which we have to.
         */
        public function useHeaders()
        {
            return !($this->_preferNotToUseHeaders() && !$this->_isHeadersRequired());
        }

        public function setRecipientsInHeader($preference)
        {
            $this->use_headers = $preference;

            return $this;
        }

        /**
         * isHeaderRequired
         * determines whether or not we need to force recipients through the smtpapi headers
         * @return boolean, if true headers are required
         *
         */
        protected function _isHeadersRequired()
        {
            if(count($this->getAttachments()) > 0 || $this->use_headers )
            {
                return true;
            }
            return false;
        }

        /**
         * _preferNotToUseHeaders
         * There are certain cases in which headers are not a preferred choice
         * to send email, as it limits some basic email functionality. Here, we
         * check for any of those rules, and add them in to decide whether or
         * not to use headers
         *
         * @return boolean, if true we don't
         */
        protected function _preferNotToUseHeaders()
        {
            if (count($this->getBccs()) > 0 || count($this->getCcs()) > 0)
            {
                return true;
            }
            if ($this->use_headers !== null && !$this->use_headers)
            {
                return true;
            }

            return false;
        }

    }
}