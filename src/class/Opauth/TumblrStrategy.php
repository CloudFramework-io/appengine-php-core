<?php
/**
 * Tumblr strategy for Opauth
 * based on http://www.tumblr.com/docs/en/api/v2#auth
 * Author: Benjamin Bjurstrom
 *
 * tumblroauth library from: https://groups.google.com/forum/#!msg/tumblr-api/g6SeIBWvsnE/gnWqT9jFSlEJ
 *
 * More information on Opauth: http://opauth.org
 *
 * @copyright    Copyright © 2012 U-Zyn Chua (http://uzyn.com)
 * @link         http://opauth.org
 * @package      Opauth.TumblrStrategy
 * @license      MIT License
 */
class TumblrStrategy extends OpauthStrategy
{
    public function __construct($strategy, $env)
    {
        parent::__construct($strategy, $env);
        require dirname(__FILE__) . '/tumblroauth/tumblroauth.php';
        if (!isset($_SESSION)) session_start();
        //check if oauth_token is present in the session
        if(empty($_SESSION['_opauth_tumblr']['oauth_token'])){
            $this->tum_oauth = new TumblrOAuth\TumblrOAuth($this->strategy['consumer_key'], $this->strategy['consumer_secret']);
        }else{
            //On the callback oauth tokens are present in the session and need to be included when instantiating the TumblrOAuth object.
            $this->tum_oauth = new TumblrOAuth\TumblrOAuth($this->strategy['consumer_key'], $this->strategy['consumer_secret'], $_SESSION['_opauth_tumblr']['oauth_token'], $_SESSION['_opauth_tumblr']['oauth_token_secret']);
            //reset the _opauth_tumblr session data.
            unset($_SESSION['_opauth_tumblr']);
        }
    }
    /**
     * Compulsory config keys, listed as unassociative arrays
     * eg. array('app_id', 'app_secret');
     */
    public $expects = array('consumer_key', 'consumer_secret');
    /**
     * Optional config keys with respective default values, listed as associative arrays
     * eg. array('scope' => 'email');
     */
    public $defaults = array(
        'redirect_uri' => '{complete_url_to_strategy}oauth_callback'
    );
    /**
     * Oauth request
     *
     * Generates a request token and redirects the user to tumblr's site for
     * authentication.
     */
    public function request()
    {
        $callback_url = NULL;
        //generate request token
        $request_token = $this->tum_oauth->getRequestToken($callback_url);
        if(!empty($request_token['oauth_token'])){
            //save the request token in a session as it will be needed again after the callback.
            $_SESSION['_opauth_tumblr'] = $request_token;
            $url = 'http://www.tumblr.com/oauth/authorize?oauth_token=' . $request_token['oauth_token'];
            $this->redirect($url);
        }else{
            $this->send_error('Failed when attempting to obtain request token', $this->tum_oauth->http_header, $request_token, $this->tum_oauth->http_info);
        }
    }
    /**
     * Oauth Callback
     *
     * After the user authenticates at http://www.tumblr.com/oauth/authorize
     * tumblr redirects them to the default callback URL. Opauth then
     * calls this method.
     */
    public function oauth_callback()
    {
        $access_token = $this->get_access_token($_REQUEST['oauth_verifier']);
        $user_raw = $this->get_user_info();
        $user = $user_raw->response->user;
        $this->send_success($user, $access_token, $user_raw);
    }
    /**
     * Get Access Token
     *
     * Request a tumblr access token for the authenticated user.
     */
    private function get_access_token($oauth_verifier){
        $access_token = $this->tum_oauth->getAccessToken($oauth_verifier);
        if(!empty($access_token['oauth_token'])){
            return $access_token;
        } else {
            $this->send_error('Failed when attempting to obtain access token', $this->tum_oauth->http_header, $access_token, $this->tum_oauth->http_info);
        }
    }
    /**
     * Get User Info
     *
     * Using the access token, request the user's tumblr account information.
     */
    private function get_user_info()
    {
        $response = $this->tum_oauth->get('http://api.tumblr.com/v2/user/info');
        if (!empty($response->meta) && $response->meta->status == 200) {
            return $response;
        } else {
            $this->send_error('Failed when attempting to lookup user info', $this->tum_oauth->http_header, $response, $this->tum_oauth->http_info);
        }
    }
    private function send_success($user, $access_token, $raw){
        $this->auth = array(
            'provider' => 'Tumblr',
            'uid' => $user->name,
            'info' => array(
                'name' => $user->name,
                'likes' => $user->likes,
                'following' => $user->following,
                'blogs' => $user->blogs,
                // etc...
            ),
            'credentials' => array(
                'token' => $access_token['oauth_token'],
                'secret' => $access_token['oauth_token_secret']
            ),
            'raw' => $raw
        );
        //opauth success callback
        $this->callback();
    }
    private function send_error($message, $headers, $response, $http_info){
        $error = array(
            'provider' => 'Tumblr',
            'code' => 'oauth_token_error',
            'message' => $message,
            'raw' => array(
                'headers' => $headers,
                'response' => $response,
                'http_info' => $http_info
            )
        );
        //opauth error callback
        $this->errorCallback($error);
    }
}