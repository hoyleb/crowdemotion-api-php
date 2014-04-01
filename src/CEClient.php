<?php

namespace com\crowdemotion\API\client;

/**
 * CrowdEmotion REST API PHP client.
 *
 * @author diego
 */
class CEClient {
    
    private $base_url = 'https://api.crowdemotion.co.uk/';
    
    private $debug;
    private $user = null;

    public function __construct($debug=false) {
        $this->debug = $debug;
    }
    
    public function login($username, $password) {
        
        $function = 'user/login';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url . $function);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"username\": \"$username\", \"password\": \"$password\"}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        $response = curl_exec($ch);

        if($this->debug) {
            var_dump($function, $response);
        }
                
        if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            $response = false;
        }
        
        if($response) {
            $this->user = json_decode($response);
        }
        
        curl_close($ch);

        return $response ? true : false;
    }

    public function uploadLink($mediaURL) {

        $function = "facevideo";
        $url = "$function";
        
        $postFields =  "{\"link\": \"$mediaURL\"}";
        $response = $this->makeCall($function, 'POST', $url, $postFields);
                
        if($response) {
            $response = json_decode($response);
        }
        
        return $response;
    }
    
    
    public function writeTimeseries($params) {

        $function = "timeseries";
        $url = "$function?response_id={$params['responseId']}&metric_id={$params['metric']}";
        $postFields =  "{\"data\": [". implode(',',$params['data']) ."]}";

        $response = $this->makeCall($function, 'POST', $url, $postFields);
                
        return $response ? true : false;
    }
    
    public function readTimeseries($params) {

        $function = "timeseries";
        $url = "$function?response_id={$params['responseId']}&metric_id={$params['metric']}";

        $response = $this->makeCall($function, 'GET', $url);
                
        if($response) {
            $response = json_decode($response);
        }
                
        return $response;
    }
     
    private function makeCall($function, $httpMethod, $url, $postFields=null) {

        // TODO better error management
        if(!$this->user || !$this->user->token || !$this->user->userId) {
            return false;
        }
        
        $time = date('c');
        $nonce = substr(str_shuffle(MD5(microtime())), 0, 21);   // clever! http://derek.io/blog/2009/php-a-better-random-string-function/
        $string_to_hash = $this->user->token .':'. $function .','. $httpMethod .','. $time .','. $nonce;
        $authorization = $this->user->userId .':'. base64_encode(hash("sha256", $string_to_hash, true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        if($httpMethod == 'POST') {
            $isPost = TRUE;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }
        curl_setopt($ch, CURLOPT_POST, $isPost);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: $authorization",
            "x-ce-rest-date: $time",
            "nonce: $nonce"));
        $response = curl_exec($ch);
        
        if($this->debug) {
            var_dump($function, $response);
        }

        if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            $response = false;
        }
        
        curl_close($ch);
        
        return $response === '' ? true : $response;
    }
    
    
}
