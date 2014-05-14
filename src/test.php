<?php

require_once './CEClient.php';

date_default_timezone_set('Etc/UTC');

$api = new \com\crowdemotion\API\client\CEClient(true, true);

$username = "user";
$password = "password";
$res = $api->login($username, $password);

if(!$res) {
    echo 'AUTH ERROR';
    return;
}


$facevideo = $api->uploadLink("test-" . date('c'));

if(!$facevideo) {
    echo 'uploadLink error';
    return;
}

// should wait a few minutes
if($facevideo->status != 1) {
    echo 'timeseries not ready';
    return;
}


// *** POST VIDEO RESULTS
$res = $api->writeTimeseries(array('responseId' => $facevideo->responseId, 'metric' => '2',
        'data' => array(0.43333, 0.877223, 0.222244, 0.1)));
$res = $res && $api->writeTimeseries(array('responseId' => $facevideo->responseId, 'metric' => '3',
        'data' => array(0.1, 0.2, 0.3, 0.4)));
    
if(!$res) {
    echo 'writeTimeseries error';
    return;
}


// *** GET VIDEO RESULTS
$timeseries_res = $api->readTimeseries(array('responseId' => $facevideo->responseId, 'metric' => array(2,3)));

foreach ($timeseries_res as $timeseries) {
    if($timeseries) {
        var_dump(count($timeseries->data) == (($timeseries->endIndex-$timeseries->startIndex+1)/$timeseries->stepSize));
    }
}
