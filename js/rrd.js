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
      lines: { show: true, fill: true }
    },
    xaxis: { mode: 'time', panRange: [ data.start * 1000, data.end * 1000 ] },
    yaxis: { min: 0, panRange: [ 0, max * 2 ] },
    zoom: { interactive: true },
    pan: { interactive: true }
  });
}

function fetch_data()
{
  var orko_if = { op: 'xport', host: 'orko', plugin: 'interface', type: 'if_octets', type_instance: 'eth2', start_time: 'week' };
  var gir_if = { op: 'xport', host: 'gir', plugin: 'interface', type: 'if_octets', type_instance: 'eth0', start_time: 'week' };
  var zorak_if = { op: 'xport', host: 'zorak', plugin: 'interface', type: 'if_octets', type_instance: 'eth0', start_time: 'day' };
  var jem_load = { op: 'xport', host: 'jem', plugin: 'load', type: 'load', start_time: 'week' };
  var penfold_load = { op: 'xport', host: 'penfold', plugin: 'load', type: 'load', start_time: 'week' };

  $.ajax({
    url: "rrd.php",
    data: zorak_if,
    type: 'GET',
    dataType: "json",
    success: draw_graph
  });
}

// vim: set sw=2 ts=2 ft=jquery:
