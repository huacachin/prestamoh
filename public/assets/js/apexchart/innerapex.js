var options = {
  series: [{
    name: 'PRODUCT A',
    type: 'bar',
    data: [35, 35, 35, 35, 35, 35, 35],
  },
  {
    name: 'PRODUCT B',
    type: 'bar',
    data: [35, 35, 35, 35, 35, 35, 35],
  },
  {
    name: 'PRODUCT C',
    type: 'bar',
    data: [35, 35, 35, 35, 35, 35, 35],
  },
  {
    name: 'PRODUCT D',
    type: 'bar',
    data: [35, 35, 35, 35, 35, 35, 35],
  },
  {
    name: 'PRODUCT E',
    type: 'bar',
    data: [35, 35, 35, 35, 35, 35, 35],
  },
  {
    name: 'PRODUCT F',
    type: 'line',
    data: [105, 65, 123, 57, 135, 52, 127]
  }],
  chart: {
    type: 'bar',
    height: 500,
    offsetY: -225,
    stacked: true,
    toolbar: {
      show: false,
    },
    dropShadow: {
      enabled: true,
      top: 4,
      left: 0,
      blur: 1,
      color: "#008193",
      opacity: 0.1
    }
  },
  legend: {
    show: false,
  },
  stroke: {
    width: [0, 0, 0, 0, 0, 5]
  },
  dataLabels: {
    enabled: false,
  },
  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: '40%',
      barHeight: '70%',
    },
  },
  annotations: {
    points: [{
      x: 452,
      y: 132,
      marker: {
        size: 10,
        fillColor: '#008193',
        strokeColor: '#ffffff',
        strokeWidth: 4,
        radius: 2,
      },
    }]
  },
  colors: ['#cce6e9', '#99cdd4', '#66b3be', '#339aa9', '#008193', '#EAB01E'],
  xaxis: {
    categories: ['100k', '200k', '300k', '400k', '500k', '600k', '700k'],
    labels: {
      minHeight: undefined,
      maxHeight: 24,
      style: {
        ...fontCommon,
        fontWeight: 400,
      },
      tooltip: {
        enabled: false,
      },
    },
    lines: {
      show: false
    },
    axisBorder: {
      show: false,
    },
  },
  labels: {
    show: false,
  },
  yaxis: {
    labels: {
      show: false,
      style: {
        ...fontCommon,
      },
    },
    lines: {
      show: false
    },
  },
  grid: {
    show: false,
  },
};
var chart = new ApexCharts(document.querySelector("#Project-created"), options);
chart.render();
