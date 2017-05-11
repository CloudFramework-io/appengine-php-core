<?php
/**
 * Pinterest strategy for Opauth
 * based on https://developers.pinterest.com/docs/api/overview/?
 *
 * More information on Opauth: http://opauth.org
 *
 * @copyright    Copyright ©2017 Adrian Martinez
 * @link         http://opauth.org
 * @package      Opauth.PinterestStrategy
 * @license      MIT License
 */

class PinterestStrategy extends OpauthStrategy {
    /**
     * Compulsory config keys, listed as unassociative arrays
     * eg. array('app_id', 'app_secret');
     */
    public $expects = array('client_id', 'client_secret');

    /**
     * Optional config keys with respective default values, listed as associative arrays
     * eg. array('scope' => 'email');
     */
    public $defaults = array(
        'redirect_uri' => '{complete_url_to_strategy}callback',
        'scope' => 'read_public,write_public,read_relationships,write_relationships'
    );

    /**
     * Auth request
     */
    public function request(){
        $url = 'https://api.pinterest.com/oauth/';

        $params = array(
            'response_type' =>'code',
            'redirect_uri'=> $this->strategy['redirect_uri'],
            'client_id' => $this->strategy['client_id'],
            'state' => substr(md5(rand()), 0, 7)
        );

        if (!empty($this->strategy['scope'])) $params['scope'] = $this->strategy['scope'];

        // redirect to generated url
        $this->clientGet($url, $params);
    }

    /**
     * Internal callback, after Pinterest's OAuth
     */
    public function callback(){
        if (array_key_exists('code', $_GET) && !empty($_GET['code'])){
            $url = 'https://api.pinterest.com/v1/oauth/token';

            $params = array(
                'grant_type' => 'authorization_code',
                'client_id' =>$this->strategy['client_id'],
                'client_secret' => $this->strategy['client_secret'],
                'code' => trim($_GET['code'])
            );
            $response = $this->serverPost($url, $params, null, $headers);
            $results = json_decode($response);

            if (!empty($results) && !empty($results->access_token)){
                $userinfo = $this->userinfo($results->user->id, $results->access_token);

                $this->auth = array(
                    'provider' => 'Pinterest',
                    'uid' => $userinfo->id,
                    'info' => array(
                        'name' => $userinfo->full_name,
                        'nickname' => $userinfo->username,
                        'image' => $userinfo->profile_picture
                    ),
                    'credentials' => array(
                        'token' => $results->access_token,
                        //'expires' => date('c', time() + $results->expires_in)
                    ),
                    'raw' => $userinfo
                );

                if (!empty($userinfo->website)) $this->auth['info']['urls']['website'] = $userinfo->website;
                if (!empty($userinfo->bio)) $this->auth['info']['description'] = $userinfo->bio;

                /**
                 * NOTE:
                 * Pinterest's access_token have no explicit expiry, however, please do not assume your
                 * access_token is valid forever.
                 *
                 * Missing optional info values
                 * - email
                 */

                $this->callback();
            }
            else{
                $error = array(
                    'provider' => 'Pinterest',
                    'code' => 'access_token_error',
                    'message' => 'Failed when attempting to obtain access token',
                    'raw' => array(
                        'url' => $url,
                        'response' => $results,
                        'headers' => $headers,
                        'params' =>$params
                    )
                );

                $this->errorCallback($error);
            }
        }
        else{
            $error = array(
                'provider' => 'Pinterest',
                'code' => $_GET['error'],
                'reason' => $_GET['error_reason'],
                'message' => $_GET['error_description'],
                'raw' => $_GET
            );

            $this->errorCallback($error);
        }
    }

    /**
     * Queries Pinterest API for user info
     *
     * @param	integer	$uid
     * @param	string	$access_token
     * @return	array	Parsed JSON results
     */
    private function userinfo($uid, $access_token){
        $userinfo = $this->serverGet('https://api.instagram.com/v1/users/'.$uid.'/', array('access_token' => $access_token), null, $headers);

        if (!empty($userinfo)){
            $results = json_decode($userinfo);

            return $results->data;
        }
        else{
            $error = array(
                'provider' => 'Pinterest',
                'code' => 'userinfo_error',
                'message' => 'Failed when attempting to query for user information',
                'raw' => array(
                    'response' => $userinfo,
                    'headers' => $headers
                )
            );

            $this->errorCallback($error);
        }
    }
}
?>