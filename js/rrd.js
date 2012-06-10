var plot;
var legends;
var available_data;

function prettyPrint(value, baseType)
{
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

  if ('binary' == baseType) {
    base = binaryUnits;
  }

  while (value >= base[units][1] * 990 && units + 1 < base.length) {
    ++units;
  }

  var scale = 10 / base[units][1];
  value = (value * scale + 0.5) << 0;
  var tenth = value % 10;

  value = (value * 0.1) << 0;
  if (value < 100) {
    if (tenth > 0) {
        value += '.' + tenth;
    } else if (0 == value) {
      return '0 ';
    }
  }

  return value + ' ' + base[units][0] + ' ';
}

function updateLegend(event, pos, item)
{
  updateLegendTimeout = null;

  //var pos = latestPosition;

  var axes = plot.getAxes();
  if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max ||
    pos.y < axes.yaxis.min || pos.y > axes.yaxis.max) {
    return;
  }

  var i, j, dataset = plot.getData();
  for (i = 0; i < dataset.length; ++i) {
    var series = dataset[i];
    var data = series.data;

    var min = 0;
    var max = data.length - 1;

    while (min <= max) {
      var mid = (min + max) >> 1;

      if (data[mid][0] < pos.x) {
        if (pos.x <= data[mid + 1][0]) {
          break;
        }
        min = mid + 1;
      } else if (data[mid][0] > pos.x) {
        max = mid - 1;
      } else {
        break;
      }
    }

    // now interpolate
    var y, p1 = data[mid], p2 = data[mid + 1];
    if (p1 == null)
      y = p2[1];
    else if (p2 == null)
      y = p1[1];
    else
      y = p1[1] + (p2[1] - p1[1]) * (pos.x - p1[0]) / (p2[0] - p1[0]);

    legends.eq(i).text(series.label + ': ' + prettyPrint(y));
  }
}

function populateHosts(data)
{
  available_data = data;

  var hosts = $("#rrd_hosts");
  hosts.find('option').remove();
  hosts.append($("<option />").val('').text('Choose host'));
  $.each(data, function(host) {
    hosts.append($("<option />").val(host).text(host));
  });
}

function updateChartList()
{
  var hosts = $("#rrd_hosts");

  if (null == hosts.val() || 0 == hosts.val().length) {
    return;
  }

  var charts = $("#host_charts");
  charts.find('option').remove();
  charts.append($("<option />").val('').text('Choose chart'));
  $.each(available_data[hosts.val()], function(idx, chart) {
    charts.append($("<option />").val(chart).text(chart));
  });
}

function getParams()
{
  $.ajax({
    url: "rrd.php",
    data: { op: 'get_params' },
    type: 'GET',
    dataType: "json",
    success: populateHosts
  });
}

function metricTicks(tick, axis)
{
  return prettyPrint(tick, axis.options.metricBase);
}

function drawGraph(data)
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

  plot = $.plot($('#chartdiv'), series, {
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
    grid: { hoverable: true },
    legend: { position: 'nw' }//,
    //zoom: { interactive: true },
    //pan: { interactive: true }
  });

  legends = $("#chartdiv .legendLabel");
  legends.each(function () {
    // fix the widths so they don't jump around
    $(this).css('width', $(this).width());
  });
}

function fetchData()
{
  var hosts = $("#rrd_hosts");
  var charts = $("#host_charts");

  if (null == hosts.val() || 0 == hosts.val().length ||
      null == charts.val() || 0 == charts.val().length) {
    return;
  }

  var data = {
    op: 'xport',
    host: hosts.val(),
    chart: charts.val(),
    start_time: 'week'
  };

  $.ajax({
    url: "rrd.php",
    data: data,
    type: 'GET',
    dataType: "json",
    success: drawGraph
  });
}

$(document).ready(getParams);
$('#chartdiv').bind('plothover', updateLegend);
$('#rrd_hosts').change(updateChartList);
$('#host_charts').change(fetchData);

// vim: set sw=2 ts=2 ft=jquery:
