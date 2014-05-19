<form id="form1" name="form1" method="POST" enctype="multipart/form-data" >
    <input type="file" id="file1" name="file1">
    <input name="mySubmit" type="submit" value="Send" />
</form>

<?php
echo var_export($_POST,true);
echo var_export($_GET,true);

if($_POST && $_FILES['file1']){
    require_once './CEClient.php';

    date_default_timezone_set('Etc/UTC');

    $api = new \com\crowdemotion\API\client\CEClient(true, true);

    $username = "stefano@crowdemotion.co.uk";
    $password = "w1234567";
    $res = $api->login($username, $password);

    if(!$res) {
        echo 'AUTH ERROR';
        return;
    }

    //name type tmp_name error size

    //move_uploaded_file($_FILES['file1']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] );

    $fullpath_move = $_SERVER['DOCUMENT_ROOT'].'/'.'foo.mp4';
    $fullpath = '@'.$fullpath_move.';filename='.$fullpath_move;


    //$api->upload2();

    //$facevideo = $api->upload($_SERVER['DOCUMENT_ROOT'].'/'.'foo.mp4', filesize($fullpath_move));
    $dir = $_FILES['file1']['tmp_name'];
    $name = $_FILES['file1']['name'];
    $type = $_FILES['file1']['type'];
    $dim = $_FILES['file1']['size'];
    $facevideo = $api->upload($dir, $name, $type, $dim);

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
}