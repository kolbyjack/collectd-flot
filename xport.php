<?php

// curl http://stats.b0g.us/rrd/xport.php -d 'host=orko&plugin=interface&plugin_instance=&type=if_octets&type_instance=eth2&start_time=week'

$data_dir = '/opt/collectd/data';
$host = basename($_POST['host']);
$plugin = basename($_POST['plugin']);
$plugin_instance = basename($_POST['plugin_instance']);
$type = basename($_POST['type']);
$type_instance = basename($_POST['type_instance']);
$start_time = basename($_POST['start_time']);
$end_time = basename($_POST['end_time']);

if (strlen($plugin_instance)) {
    $plugin .= "-$plugin_instance";
}

if (strlen($type_instance)) {
    $type .= "-$type_instance";
}

if (0 == strlen($end_time)) {
    $end_time = 'now';
}

if (0 == strlen($start_time)) {
    $start_time = '1w';
}
else {
    switch ($start_time) {
    case 'day':   $start_time = "$end_time-1d"; break;
    case 'week':  $start_time = "$end_time-1w"; break;
    case 'month': $start_time = "$end_time-1m"; break;
    case 'year':  $start_time = "$end_time-1y"; break;
    }
}

$rrd_path = "$data_dir/$host/$plugin/$type.rrd";

$options = array(
    '--start', "$start_time",
    '--end', "$end_time",
    "DEF:rx=$rrd_path:rx:AVERAGE",
    "DEF:tx=$rrd_path:tx:AVERAGE",
    "CDEF:rxb=rx,8,*",
    "CDEF:txb=tx,8,*",
    "XPORT:rxb:rx bits",
    "XPORT:txb:tx bits"
);

$data = rrd_xport($options);

if (true) {
    header('Content-Type: application/x-json');
    #echo json_encode($_POST);
    echo json_encode($data);
}
else {
    header('Content-Type: text/plain');
    var_dump($options);
    var_dump($data);
}

