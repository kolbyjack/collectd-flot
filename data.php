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

    if (isset($query_args['chart'])) {
        $split = explode('/', abspath($query_args['chart']));

        $plugin_parts = explode('-', $split[0], 2);
        $type_parts = explode('-', $split[1], 2);

        $args['plugin'] = $plugin_parts[0];
        $args['plugin_instance'] = $plugin_parts[1];
        $args['type'] = $type_parts[0];
        $args['type_instance'] = $type_parts[1];
    } else {
        $args['plugin'] = abspath($query_args['plugin']);
        $args['plugin_instance'] = abspath($query_args['plugin_instance']);
        $args['type'] = abspath($query_args['type']);
        $args['type_instance'] = abspath($query_args['type_instance']);
    }

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

    function add_split_rrd(&$options, $components, $args, $plugin_instance, $type)
    {
        $args['plugin_instance'] = $plugin_instance;
        $args['type'] = $type;
        foreach ($components as $instance => $label) {
            $args['type_instance'] = is_numeric($instance) ? $label : $instance;
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
        $components = array('steal', 'interrupt', 'softirq',
            'system', 'user', 'nice', 'wait', 'idle');
        add_split_rrd($options, $components, $args, $args['plugin_instance'], 'cpu');

        $settings['stack'] = true;
        $settings['max'] = 100;
        break;

    case 'df':
        $args['plugin_instance'] = null;
        $args['type'] = 'df';
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:used=$rrd_path:used:AVERAGE",
            "DEF:free=$rrd_path:free:AVERAGE",
            "XPORT:used:used",
            "XPORT:free:free");

        $settings['stack'] = true;
        $settings['colors'] = array('red', 'green');
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

    case 'interface':
        $units = ('if_octets' === $args['type']) ? 'bits/s' : '/s';
        $args['plugin_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:rx=$rrd_path:rx:AVERAGE",
            "DEF:tx=$rrd_path:tx:AVERAGE",
            "CDEF:incoming=rx,8,*",
            "CDEF:outgoing=tx,8,*",
            "XPORT:incoming:Incoming ($units)",
            "XPORT:outgoing:Outgoing ($units)");
        break;

    case 'load':
        $args['plugin_instance'] = $args['type_instance'] = null;
        $args['type'] = 'load';
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
        $components = array('used', 'buffered', 'cached', 'free');
        add_split_rrd($options, $components, $args, null, 'memory');

        $settings['stack'] = true;
        $settings['metricBase'] = 'binary';
        break;

    case 'nginx':
        $components = array('active', 'reading', 'writing', 'waiting');
        add_split_rrd($options, $components, $args, null, 'nginx_connections');

        $args['type'] = 'nginx_requests';
        $args['type_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:value=$rrd_path:value:AVERAGE",
            "XPORT:value:requests");
        break;

    case 'nut':
        $components = array(
            array('type' => 'percent', 'type_instance' => 'charge', 'ds' => 'percent', 'label' => 'charge (%)'),
            array('type' => 'percent', 'type_instance' => 'load', 'ds' => 'percent', 'label' => 'load (%)'),
            array('type' => 'timeleft', 'type_instance' => 'battery', 'ds' => 'timeleft', 'label' => 'time (min)'),
            array('type' => 'voltage', 'type_instance' => 'battery', 'ds' => 'value', 'label' => 'batt (V)'),
            array('type' => 'voltage', 'type_instance' => 'input', 'ds' => 'value', 'label' => 'input (V)')
        );

        foreach ($components as &$component) {
            $args['type'] = $component['type'];
            $args['type_instance'] = $component['type_instance'];
            $instance = $component['type'].'_'.$component['type_instance'];
            $ds = $component['ds'];
            $label = $component['label'];

            $rrd_path = get_rrd_path($args);
            array_push($options,
                "DEF:$instance=$rrd_path:$ds:AVERAGE",
                "XPORT:$instance:$label");
        }
        break;

    case 'processes':
        $components = array('blocked', 'paging', 'running', 'sleeping', 'stopped', 'zombies');
        add_split_rrd($options, $components, $args, null, 'ps_state');

        $args['type'] = 'fork_rate';
        $args['type_instance'] = null;
        $rrd_path = get_rrd_path($args);
        array_push($options,
            "DEF:value=$rrd_path:value:AVERAGE",
            "XPORT:value:fork rate");

        $settings['stack'] = true;
        break;

    case 'swap':
        $components = array('used', 'cached', 'free');
        add_split_rrd($options, $components, $args, null, 'swap');

        $settings['stack'] = true;
        $settings['metricBase'] = 'binary';
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

            $plugin_name = explode('-', $entry);
            $plugin_name = $plugin_name[0];
            $custom_charts = array('load', 'memory', 'swap', 'nginx', 'processes', 'nut', 'cpu');
            if (in_array($plugin_name, $custom_charts)) {
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

