<?php

// curl http://stats.b0g.us/rrd/rrd.php -d 'op=xport&host=orko&plugin=interface&type=if_octets&type_instance=eth2&start_time=week'

define(DATA_DIR, '/opt/collectd/data');

function abspath($path)
{
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');

    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) {
            continue;
        } elseif ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }

    return implode(DIRECTORY_SEPARATOR, $absolutes);
}


function parse_args($query_args)
{
    $args['op'] = $query_args['op'];
    $args['host'] = abspath($query_args['host']);
    $args['plugin'] = abspath($query_args['plugin']);
    $args['plugin_instance'] = $pinstance = abspath($query_args['plugin_instance']);
    $args['type'] = abspath($query_args['type']);
    $args['type_instance'] = $tinstance = abspath($query_args['type_instance']);
    $args['start_time'] = $query_args['start_time'];
    $args['end_time'] = $query_args['end_time'];

    $args['rrd_path'] = sprintf("%s/%s/%s%s/%s%s.rrd", DATA_DIR, $args['host'],
        $args['plugin'], (strlen($pinstance) > 0) ? "-$pinstance" : '',
        $args['type'], (strlen($tinstance) > 0) ? "-$tinstance" : '');

    if (0 == strlen($args['end_time'])) {
        $args['end_time'] = 'now';
    }

    switch ($args['start_time']) {
    case '': case null: $args['start_time'] = "{$args['end_time']}-1w"; break;
    case 'day':   $args['start_time'] = "{$args['end_time']}-1d"; break;
    case 'week':  $args['start_time'] = "{$args['end_time']}-1w"; break;
    case 'month': $args['start_time'] = "{$args['end_time']}-1m"; break;
    case 'year':  $args['start_time'] = "{$args['end_time']}-1y"; break;
    }

    return $args;
}


function xport($args)
{
    header('Content-Type: application/x-json');

    $options = array(
        '--start', $args['start_time'],
        '--end', $args['end_time'],
        "DEF:rx={$args['rrd_path']}:rx:AVERAGE",
        "DEF:tx={$args['rrd_path']}:tx:AVERAGE",
        "CDEF:rxb=rx,8,*",
        "CDEF:txb=tx,8,*",
        "XPORT:rxb:rx bits",
        "XPORT:txb:tx bits"
    );

    $data = rrd_xport($options);
    foreach ($data['data'] as &$series) {
        foreach ($series['data'] as $time => &$value) {
            if (is_nan($value)) {
                $value = null;
            }
        }
    }

    echo json_encode($data);
}


$args = parse_args($_POST);

switch ($args['op']) {
case 'xport': xport($args); break;
default: echo json_encode($args); break;
}

