$(document).ready(fetch_data);

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
  $.ajax({
    url: "rrd.php",
    data: {
      op: 'xport'
      ,host: 'orko'
      ,plugin: 'interface'
      //,plugin_instance: ''
      ,type: 'if_octets'
      ,type_instance: 'eth2'
      ,start_time: 'week'
      //,end_time: 'now'
    },
    type: 'POST',
    dataType: "json",
    success: draw_graph
  });
}

// vim: set sw=2 ts=2 ft=jquery:
