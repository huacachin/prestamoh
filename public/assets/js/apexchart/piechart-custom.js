var projectChart = {
  series: [45, 20, 25],
  labels: ["Complets", "Running", "Canceled"],
  chart: {
    type: 'donut',
    height: 305,
    toolbar: {
      show: true,
      offsetY: '330',
      offsetX: '30%',
    }
  },
  dataLabels: {
    enabled: false
  },
  legend: {
    position: 'bottom',
    offsetY: 0,
    height: 20,
    labels: {
      colors: 'var(--light)',
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '70%',
        labels: {
          show: true,
          name: {
            offsetY: 4,
          },
          total: {
            show: true,
            fontSize: '26px',
            color: 'var(--title)',
            fontWeight: 600,
            label: '6780',
            formatter: () => 'Total Projects'
          }
        },
      }
    }
  },
  labels: ['Complets', 'Running', 'Canceled'],
  yaxis: {
    labels: {
      formatter: function (val) {
        return val / 100 + "$";
      },
    },
  },
  colors: ['#615184', '#EAB01E', '#008193'],
  responsive: [
    {
      breakpoint: 1600,
      options: {
        chart: {
          height: 280,
        },
      },
    },
    {
      breakpoint: 1439,
      options: {
        chart: {
          height: 290,
        },
      },
    },
    {
      breakpoint: 1398,
      options: {
        chart: {
          height: 220,
        },
        legend: {
          show: false,
        }
      },
    },
    {
      breakpoint: 1280,
      options: {
        chart: {
          height: 220,
        },
        legend: {
          show: false,
        }
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 270,
        },
        legend: {
          show: true,
        }
      },
    },
    {
      breakpoint: 875,
      options: {
        chart: {
          height: 280,
        },
        legend: {
          show: true,
        }
      },
    },
  ]
};
var chart1 = new ApexCharts(document.querySelector("#projectChart"), projectChart);
chart1.render();
/*=======/Radial Chart/=======*/
var totalCommentOption = {
  series: [60],
  chart: {
    type: 'radialBar',
    height: 130,
    offsetY: 0,
    sparkline: {
      enabled: true,
    },
  },
  plotOptions: {
    radialBar: {
      startAngle: 0,
      endAngle: 360,
      offsetY: 0,
      hollow: {
        size: '50%',
      },
      track: {
        background: 'rgba(var(--primary),0.1)',
        strokeWidth: '90%',
        startAngle: 0,
        endAngle: 360,
      },
      dataLabels: {
        enabled: true,
        textAnchor: 'middle',
        name: {
          show: false,
        },
        value: {
          colors: 'var(--title)',
          fontSize: '16px',
          fontFamily: "'Mukta Sans', sans-serif",
          fontWeight: 600,
          offsetY: 5,
        },
      },
    },
  },
  xaxis: {
    labels: {
      style: {
        colors: 'var(--title)',
        fontWeight: 400,
      },
    }
  },
  colors: ['rgba(var(--primary),1)'],
  grid: {
    padding: {
      top: 0,
      bottom: 0,
      left: 0,
      right: 0,
    },
  },
  stroke: {
    lineCap: 'round',
  },
  responsive: [
    {
      breakpoint: 1700,
      options: {
        chart: {
          height: 115,
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '48%',
            },
            dataLabels: {
              value: {
                fontSize: '14px',
              },
            },
          },
        },
      },
    },
    {
      breakpoint: 1600,
      options: {
        chart: {
          height: 110,
        },
      },
    },
    {
      breakpoint: 1460,
      options: {
        chart: {
          height: 100,
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '42%',
            },
            dataLabels: {
              value: {
                fontSize: '13px',
              },
            },
          },
        },
      },
    },
    {
      breakpoint: 1400,
      options: {
        chart: {
          height: 120,
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '50%',
            },
            dataLabels: {
              value: {
                fontSize: '18px',
              },
            },
          },
        },
      },
    },
    {
      breakpoint: 876,
      options: {
        chart: {
          height: 110,
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '45%',
            },
            dataLabels: {
              value: {
                fontSize: '18px',
              },
            },
          },
        },
      },
    },
    {
      breakpoint: 376,
      options: {
        chart: {
          height: 90,
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '40%',
            },
            dataLabels: {
              value: {
                fontSize: '14px',
              },
            },
          },
        },
      },
    },
  ],
};
var totalCommentEl = new ApexCharts(document.querySelector('#totalCommentRadialChart'), totalCommentOption);
totalCommentEl.render();
/*=======/Total Follower Bar Spark line/=======*/
var totalFollowersBarChartOption = {
  series: [
    {
      data: [10, 55, 45, 89, 63, 52, 70],
    },
  ],
  chart: {
    type: 'bar',
    width: '100%',
    height: 110,
    sparkline: {
      enabled: true,
    },
  },
  grid: {
    padding: {
      top: 0,
      right: 0,
      bottom: 0,
      left: -14,
    },
  },
  plotOptions: {
    bar: {
      columnWidth: '70%',
      distributed: true,
    },
  },
  xaxis: {
    crosshairs: {
      width: 1,
    },
  },
  colors: ['#f9e7c1', '#f9e7c1', '#f9e7c1', '#f9e7c1', '#f9e7c1', '#f9e7c1', '#eab01f'],
  tooltip: toolTipMini,
  responsive: [
    {
      breakpoint: 1700,
      options: {
        chart: {
          height: 86,
        },
      },
    },
    {
      breakpoint: 1460,
      options: {
        grid: {
          padding: {
            left: 0,
          },
        },
      },
    },
    {
      breakpoint: 376,
      options: {
        chart: {
          height: 50,
        },
      },
    },
  ],
};
var totalFollowersBarChartEl = new ApexCharts(document.querySelector('#totalFollowersBarChart'), totalFollowersBarChartOption);
totalFollowersBarChartEl.render();
// customer bar chart js
var customerChartOptions = {
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
    height: 400,
    offsetY: -115,
    stacked: true,
    toolbar: {
      show: false,
    },
    dropShadow: {
      enabled: true,
      top: 8,
      left: 0,
      blur: 3,
      color: "#008193",
      opacity: 0.1
    }
  },
  colors: ['#cce6e9', '#99cdd4', '#66b3be', '#339aa9', '#008193', '#EAB01E'],
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
    },
  },
  tooltip: toolTipMini,
  grid: {
    show: false,
    borderColor: 'var(--border-light)',
    padding: {
      left: 0,
      top: -50,
    },
  },
  xaxis: {
    type: 'datetime',
    labels: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
    axisBorder: {
      show: false,
    },
  },
  annotations: {
    points: [{
      x: 400,
      y: 136,
      marker: {
        size: 10,
        fillColor: '#008193',
        strokeColor: '#ffffff',
        strokeWidth: 2,
        radius: 2,
      },
    }]
  },
  yaxis: {
    labels: {
      show: false,
      align: 'right',
      style: {
        ...fontCommon,
      },
      formatter: (value) => {
        return `${value}`;
      },
    },
    axisTicks: {
      show: false,
    },
    axisBorder: {
      show: false,
    },
  },
  legend: {
    show: false,
  },
  xaxis: {
    categories: ['100k', '200k', '300k', '400k', '500k', '600k', '700k'],
    labels: {
      minHeight: undefined,
      maxHeight: 24,
      offsetX: 0,
      offsetY: 0,
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
  responsive: [
    {
      breakpoint: 1799,
      options: {
        chart: {
          height: 420,
        },
      }
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 370,
        },
      }
    },
    {
      breakpoint: 1199,
      options: {
        chart: {
          annotations: {
            points: [{
              x: 0,
              y: 0,
              marker: {
                size: 0,
                strokeWidth: 0,
                radius: 0,
              },
            }]
          },
        }

      }
    },
    {
      breakpoint: 992,
      options: {
        chart: {
          height: 410,
        },
      }
    },
  ] 
};
var customerChartEl = document.querySelector('#customerChart');
if (customerChartEl) {
  var customerChart = new ApexCharts(customerChartEl, customerChartOptions);
  customerChart.render();
};
// view chart
var optionsview = {
  series: [{
    type: 'line',
    data: [25.00, 40.00, 35.00, 48.00, 38.00, 46.00, 42.00],
  }],
  chart: {
    height: 270,
    type: 'line',
    zoom: {
      enabled: false
    },
    toolbar: {
      show: false
    },
    dropShadow: {
      enabled: true,
      top: 8,
      left: 0,
      blur: 3,
      color: "#008193",
      opacity: 0.2
    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  tooltip: {
    x: {
      show: false,
    },
    z: {
      show: false
    }
  },
  grid: {
    borderColor: 'var(--border-light)',
  },
  colors: ["#008193"],
  fill: {
    opacity: 1,
    type: 'gradient',
    gradient: {
      shade: 'light',
      type: "horizontal",
      shadeIntensity: 1,
      opacityFrom: 0.95,
      opacityTo: 1,
      colorStops: [
        {
          offset: 0,
          color: "#008193",
          opacity: 0.05
        },
        {
          offset: 30,
          color: "#008193",
          opacity: 1
        },
        {
          offset: 80,
          color: "#008193",
          opacity: 1
        },
        {
          offset: 100,
          color: "#008193",
          opacity: 0.1
        },
      ]
    },
  },
  annotations: {
    points: [{
      x: "Apr",
      y: 48,
      marker: {
        size: 10,
        fillColor: '#008193',
        strokeColor: '#ffffff',
        strokeWidth: 3,
        radius: 2,
        cssClass: 'apexcharts-custom-class'
      },
    }]
  },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', ''],
    labels: {
      minHeight: undefined,
      maxHeight: 24,
      offsetX: 0,
      offsetY: 0,
      style: {
        colors: 'var(--light)',
        fontWeight: 400,
      },
      title: {
        text: undefined,
      }
    },
    crosshairs: {
      show: true,
      width: 30,
      position: "back",
      opacity: 0.2,
      stroke: {
        color: '#008193',
        width: 0,
        dashArray: 0,
      },
      fill: {
        type: "solid",
        color: '#008193',
      },
    },
  },
  yaxis: {
    logBase: 10,
    tickAmount: 5,
    min: 10.00,
    max: 60.00,
    labels: {
      show: true,
      align: 'right',
      style: {
        ...fontCommon,
      },
      formatter: (value) => {
        return `${value}`;
      },
    },
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        chart: {
          height: 280,
        },
      }
    },
    {
      breakpoint: 481,
      options: {
        annotations: {
          points: [{
            x: "Feb",
            y: 44,
            marker: {
              size: 10,
              fillColor: '#7366FF',
              strokeColor: '#cfcdfc',
              strokeWidth: 7,
              radius: 2,
              cssClass: 'apexcharts-custom-class'
            },
          }]
        },
      }
    },
  ]
};
var viewchart = new ApexCharts(document.querySelector("#view"), optionsview);
viewchart.render();
