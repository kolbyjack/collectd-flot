$(document).ready(get_params);

function populate_lists(data)
{
  // FIXME: Populate listboxes with returned data
  console.log(data);
  fetch_data();
}

function get_params()
{
  $.ajax({
    url: "rrd.php",
    data: { op: 'get_params' },
    type: 'GET',
    dataType: "json",
    success: populate_lists
  });
}

function metricTicks(tick, axis) {

  var decimalUnits = [
    [ 'm', 0.001 ],
    [ '',  1 ],
    [ 'K', 1000 ],
    [ 'M', 1000 * 1000 ],
    [ 'G', 1000 * 1000 * 1000 ],
    [ 'T', 1000 * 1000 * 1000 * 1000 ],
    [ 'P', 1000 * 1000 * 1000 * 1000 * 1000 ]
  ];

  var binaryUnits = [
    [ 'm', 1.0 / 1024.0 ],
    [ '',  1 ],
    [ 'Ki', 1024 ],
    [ 'Mi', 1024 * 1024 ],
    [ 'Gi', 1024 * 1024 * 1024 ],
    [ 'Ti', 1024 * 1024 * 1024 * 1024 ],
    [ 'Pi', 1024 * 1024 * 1024 * 1024 * 1024 ]
  ];

  var base = decimalUnits;
  var units = 0;

  if ('binary' == axis.options.metricBase) {
    base = binaryUnits;
  }

  while (axis.max >= base[units][1] * 990 && units + 1 < base.length) {
    ++units;
  }

  var scale = 10 / base[units][1];
  var value = (tick * scale + 0.5) << 0;
  var tenth = value % 10;

  value = (value * 0.1) << 0;
  if (tenth > 0) {
    tenth = '.' + tenth;
  } else {
    tenth = '';
  }

  return value + tenth + ' ' + base[units][0] + ' ';
}

function draw_graph(data)
{
  var series = new Array();
  var max = 0;

  for (var i in data.data) {
    series.push({
      label: data.data[i].legend,
      data: $.map(data.data[i].data, function(x, y) {
        if (x > max) { max = x; }
        return [ [ y * 1000, x ] ];
      })
    });
  }

  $.plot($('#chartdiv'), series, {
    series: {
      points: { show: false },
      lines: { show: true, fill: true },
      stack: data.settings.stack
    },
    xaxis: {
      mode: 'time',
      timezone: 'browser',
      panRange: [ data.start * 1000, data.end * 1000 ]
    },
    yaxis: {
      tickFormatter: metricTicks,
      metricBase: data.settings.metricBase,
      min: 0,
      max: data.settings.max,
      panRange: [ 0, max * 2 ]
    },
    legend: { position: 'nw' }//,
    //zoom: { interactive: true },
    //pan: { interactive: true }
  });
}

function fetch_data()
{
  var zorak_if = { op: 'xport', host: 'zorak', plugin: 'interface', type: 'if_octets', type_instance: 'eth0', start_time: 'week' };
  var zorak_load = { op: 'xport', host: 'zorak', plugin: 'load', start_time: 'week' };
  var zorak_memory = { op: 'xport', host: 'zorak', plugin: 'memory', start_time: 'week' };
  var zorak_swap = { op: 'xport', host: 'zorak', plugin: 'swap', start_time: 'week' };
  var zorak_cpu0 = { op: 'xport', host: 'zorak', plugin: 'cpu', plugin_instance: 0, start_time: 'week' };
  var zorak_df_boot = { op: 'xport', host: 'zorak', plugin: 'df', type_instance: 'boot', start_time: 'week' };
  var zorak_df_root = { op: 'xport', host: 'zorak', plugin: 'df', type_instance: 'root', start_time: 'week' };
  var zorak_disk = { op: 'xport', host: 'zorak', plugin: 'disk', plugin_instance: 'sda3', type: 'disk_octets', start_time: 'week' };
  var data = zorak_disk;

  $.ajax({
    url: "rrd.php",
    data: data,
    type: 'GET',
    dataType: "json",
    success: draw_graph
  });
}

// vim: set sw=2 ts=2 ft=jquery:
