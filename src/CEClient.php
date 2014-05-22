<?php

namespace com\crowdemotion\API\client;

// HACK for compatibility with PHP < 5.5
if (!function_exists('curl_file_create')) {
    function curl_file_create($filename, $mimetype = '', $postname = '') {
        return "@$filename;filename="
            . ($postname ?: basename($filename))
            . ($mimetype ? ";type=$mimetype" : '');
    }
}


/**
 * CrowdEmotion REST API PHP client.
 *
 * @author diego
 */
class CEClient {
    
    private $domain = 'api.crowdemotion.co.uk';
    private $base_url;
    
    private $debug;
    private $user = null;

    public function __construct($debug=false, $http_fallback=false) {
        $this->debug = $debug;
        
        $protocol = 'https';
        
        // plain HTTP fallback only if requested
        if($http_fallback) {
            $connection = @fsockopen($this->domain, 443, $errno, $errstr, 10);
            if (is_resource($connection)) {
                fclose($connection);
            } else {
                $protocol = 'http';
            }
        }
        $this->base_url = $protocol .'://'. $this->domain .'/';        
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
    
    public function upload($dir,$name,$type, $dim) {

        $function = "facevideo/upload";
        $url = "$function";

        //$postFields = array('file' => '@'. $file_full_path);

        //$file =curl_file_create($file_full_path, 'application/octet-stream', basename($file_full_path));
        //$postFields = array('file_contents' => $file);
        $postFields = array(
            'file' =>
                '@'            . $dir
                . ';filename=' . $name
                . ';type='     . $type
            );

        $response = $this->makeCall($function, 'POST', $url, $postFields,
            array('header'=>
                array(
                    'Accept-Ranges: bytes',
                    "Content-Length: $dim",
                    "Content-Range: bytes 0-$dim",
                    'Content-Type: application/octet-stream',
                    'Content-Description: File Transfer',
                    'Content-Disposition: form-data; name="file"; filename="'.$name.'"'
                    ))
            );

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
        $url = "$function?response_id={$params['responseId']}";

        if(is_array($params['metric'])) {
            foreach($params['metric'] as $metric_id) {
                $url .= "&metric_id={$metric_id}";
            }
        } else {
            $url .= "&metric_id={$params['metric']}";
        }
        
        $response = $this->makeCall($function, 'GET', $url);
                
        if($response) {
            $response = json_decode($response);
        }
                
        return $response;
    }

    public function readMetrics($params=null) {

        $function = "metric";
        if($params==null){
            $url = "$function";
        }elseif(is_array($params)){
            $q_url = implode('&metric_id=', $params);
            $url = "$function?".substr($q_url,1);
        }elseif(is_numeric($params)){
            $url = "$function?metric_id=$params";
        }

        $response = $this->makeCall($function, 'GET', $url);

        if($response) {
            $response = json_decode($response);
        }

        return $response;
    }

    private function makeCall($function, $httpMethod, $url, $postFields=null, $opt=array()) {
        $debug_l2 = false;
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
        $isPost = FALSE;
        if($httpMethod == 'POST') {
            $isPost = TRUE;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }
        curl_setopt($ch, CURLOPT_POST, $isPost);
        
        $headers = array(
            "Authorization: $authorization",
            "x-ce-rest-date: $time",
            "nonce: $nonce",
        );
        if ($httpMethod != 'GET' && !isset($opt['header'])) {
            $headers[] = "Content-Type: application/json";
        }
        if (isset($opt['header'])) {
            foreach($opt['header'] as $h){
                $headers[] = $h;
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if($debug_l2 && $this->debug) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $logfh = fopen("my_log.log", 'w+');
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            error_log('postfield');
            error_log(var_export($postFields,true));

        }
        $response = curl_exec($ch);
        
        if($debug_l2 && $this->debug) {
            //curl_setopt($ch, CURLINFO_HEADER_OUT, true);

            $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
            error_log(var_dump( $headers ,true));

            curl_setopt($ch, CURLOPT_FILE, $logfh); // logs curl messages
            var_dump($url, $function, $response);
            $curlVersion = curl_version();
            extract(curl_getinfo($ch));
            //$metrics = <<<EOD

            error_log("URL....: $url                                                                                                               ");
            error_log("Code...: $http_code ($redirect_count redirect(s) in $redirect_time secs)                                                    ");
            error_log("Content: $content_type Size: $download_content_length (Own: $size_download) Filetime: $filetime                             ");
            error_log("Time...: $total_time Start @ $starttransfer_time (DNS: $namelookup_time Connect: $connect_time Request: $pretransfer_time)  ");
            error_log("Speed..: Down: $speed_download (avg.) Up: $speed_upload (avg.)                                                              ");
            error_log("Curl...: v{$curlVersion['version']}                                                                                         ");

            //EOD;
            error_log('=======RESPONSE');
            error_log(var_export($response,true));
            error_log('=======END RESPONSE');
        }

        if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            $response = false;
        }

        if($this->debug) {
            error_log('curl_getinfo($ch, CURLINFO_HTTP_CODE)');
            error_log(var_export(curl_getinfo($ch, CURLINFO_HTTP_CODE),true));
        }
        curl_close($ch);
        
        return $response === '' ? true : $response;
    }
    
    
}
