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
    $args['plugin_instance'] = abspath($query_args['plugin_instance']);
    $args['type'] = abspath($query_args['type']);
    $args['type_instance'] = abspath($query_args['type_instance']);
    $args['start_time'] = $query_args['start_time'];
    $args['end_time'] = $query_args['end_time'];

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
    function get_rrd_path($args)
    {
        $pi = $args['plugin_instance'];
        $ti = $args['type_instance'];

        return sprintf("%s/%s/%s%s/%s%s.rrd", DATA_DIR, $args['host'],
            $args['plugin'], (strlen($pi) > 0) ? "-$pi" : '',
            $args['type'], (strlen($ti) > 0) ? "-$ti" : '');
    }

    function add_split_rrd(&$options, $components, $args)
    {
        foreach ($components as $instance => $label) {
            $args['type_instance'] = $instance;
            $rrd_path = get_rrd_path($args);
            array_push($options,
                "DEF:$instance=$rrd_path:value:AVERAGE",
                "XPORT:$instance:$label");
        }
    }

    $options = array(
        '--start', $args['start_time'],
        '--end', $args['end_time']);

    $settings = array();

    switch ($args['plugin']) {

    case 'cpu':
        $args['type'] = $args['plugin'];
        $args['type_instance'] = null;
        $components = array('steal' => 'steal', 'interrupt' => 'interrupt',
            'softirq' => 'softirq', 'system' => 'system', 'user' => 'user',
            'nice' => 'nice', 'wait' => 'wait', 'idle' => 'idle');
        $settings['stack'] = true;
        $settings['max'] = 100;

        add_split_rrd($options, $components, $args);
        break;

    case 'interface':
        $args['plugin_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:rx=$rrd_path:rx:AVERAGE",
            "DEF:tx=$rrd_path:tx:AVERAGE",
            "CDEF:incoming=rx,8,*",
            "CDEF:outgoing=tx,8,*",
            "XPORT:incoming:Incoming (bits/s)",
            "XPORT:outgoing:Outgoing (bits/s)");
        break;

    case 'load':
        $args['plugin_instance'] = null;
        $args['type'] = $args['plugin'];
        $args['type_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:shortterm=$rrd_path:shortterm:AVERAGE",
            "DEF:midterm=$rrd_path:midterm:AVERAGE",
            "DEF:longterm=$rrd_path:longterm:AVERAGE",
            "XPORT:shortterm:1 min",
            "XPORT:midterm:5 min",
            "XPORT:longterm:15 min");
        break;

    case 'memory':
        $args['plugin_instance'] = null;
        $args['type'] = $args['plugin'];
        $components = array('used' => 'used', 'buffered' => 'buffered',
            'cached' => 'cached', 'free' => 'free');
        $settings['stack'] = true;
        $settings['metricBase'] = 'binary';

        add_split_rrd($options, $components, $args);
        break;

    case 'swap':
        $args['plugin_instance'] = null;
        $args['type'] = $args['plugin'];
        $components = array('used' => 'used',
            'cached' => 'cached', 'free' => 'free');
        $settings['stack'] = true;
        $settings['metricBase'] = 'binary';

        add_split_rrd($options, $components, $args);
        break;

    case 'df':
        $args['plugin_instance'] = null;
        $args['type'] = $args['plugin'];
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:used=$rrd_path:used:AVERAGE",
            "DEF:free=$rrd_path:free:AVERAGE",
            "XPORT:used:used",
            "XPORT:free:free");

        $settings['stack'] = true;
        break;

    case 'disk':
        $args['type_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:write=$rrd_path:write:AVERAGE",
            "DEF:read=$rrd_path:read:AVERAGE",
            "XPORT:write:Written",
            "XPORT:read:Read");
        break;

    default:
        $rrd_path = get_rrd_path($args);
        $info = rrd_info($rrd_path);
        foreach ($info as $key => $val) {
            if (0 == strncmp($key, 'ds[', 3) && 0 == substr_compare($key, '.index', -6)) {
                $ds = split('[][]', $key);
                $ds = $ds[1];
                array_push($options,
                    "DEF:$ds=$rrd_path:$ds:AVERAGE",
                    "XPORT:$ds:$ds");
            }
        }
        break;
    }

    $data = rrd_xport($options);
    $data['settings'] = $settings;
    foreach ($data['data'] as &$series) {
        foreach ($series['data'] as $time => &$value) {
            if (is_nan($value)) {
                $value = null;
            }
        }
    }

    return $data;
}


function get_params($args)
{
    function read_rrdfiles($dirname)
    {
        if (!($dp = opendir($dirname))) {
            return null;
        }

        $rrd_files= array();

        while (($entry = readdir($dp)) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            if (0 != substr_compare($entry, '.rrd', -4)) {
                continue;
            }

            $rrd_files[] = substr($entry, 0, -4);
        }

        closedir($dp);

        return $rrd_files;
    }

    function read_plugins($dirname)
    {
        if (!($dp = opendir($dirname))) {
            return null;
        }

        $plugins = array();

        while (($entry = readdir($dp)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (0 === strncmp($entry, 'cpu-', 4) || 'load' === $entry ||
                'memory' === $entry || 'swap' === $entry) {
                $plugins[] = $entry;
            } elseif (($rrd_files = read_rrdfiles("$dirname/$entry"))) {
                $host = basename($dirname);
                foreach ($rrd_files as $rrd) {
                    $plugins[] = "$entry/$rrd";
                }
            }
        }

        closedir($dp);

        sort($plugins);
        return $plugins;
    }

    function read_hosts($dirname)
    {
        if (!($dp = opendir($dirname))) {
            return null;
        }

        $hosts = array();

        while (($entry = readdir($dp)) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            if (($plugins = read_plugins("$dirname/$entry"))) {
                $hosts[$entry] = $plugins;
            }
        }

        closedir($dp);

        return $hosts;
    }

    $hosts = read_hosts(DATA_DIR);
    ksort($hosts);

    return $hosts;
}


$args = parse_args($_GET);

switch ($args['op']) {
case 'xport':       $data = xport($args); break;
case 'get_params':  $data = get_params($args); break;
default:            $data = $args; break;
}

header('Content-Type: application/x-json');
echo json_encode($data);

