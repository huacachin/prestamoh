/*=====================
    Friendly Dashboard Chart 
  ==========================*/
/*=======/BTC-chart/=======*/
var BTCchartOption = {
  series: [
    {
      name: 'series1',
      data: [10, 35, 15, 78, 40, 60, 12, 60],
    },
  ],
  chart: {
    width: 150,
    type: 'area',
    sparkline: {
      enabled: true,
    },
  },
  colors: ['#008193'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.6,
      opacityTo: 0.4,
    },
  },
  stroke: {
    curve: 'straight',
    width: 2,
  },
  tooltip: toolTipMini,
  responsive: [
    {
      breakpoint: 1500,
      options: {
        chart: {
          width: 230,
          height: 80,
        },
      },
    },
    {
      breakpoint: 1400,
      options: {
        chart: {
          width: 180,
          height: 80,
        },
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          width: 150,
        },
        grid: {
          padding: {
            top: 0,
            right: 0,
            bottom: 0,
            left: 5,
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
var BTCchartEl = new ApexCharts(document.querySelector('#BTC-chart'), BTCchartOption);
BTCchartEl.render();
/*=======/totalLikesAreaChart/=======*/
var totalLikesOption = {
  series: [
    {
      name: 'series2',
      data: [25, 22, 48, 30, 78, 24, 40, 10, 50, 30, 70, 20],
    },
  ],
  chart: {
    width: 150,
    type: 'area',
    sparkline: {
      enabled: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  colors: ['#EAB01E'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.6,
      opacityTo: 0.4,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  tooltip: toolTipMini,
  responsive: [
    {
      breakpoint: 1500,
      options: {
        chart: {
          width: 230,
          height: 80,
        },
      },
    },
    {
      breakpoint: 1400,
      options: {
        chart: {
          width: 180,
          height: 80,
        },
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          width: 144,
        },
        grid: {
          padding: {
            top: 0,
            right: 0,
            bottom: 0,
            left: 5,
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
var totalLikesEl = new ApexCharts(document.querySelector('#totalLikesAreaChart'), totalLikesOption);
totalLikesEl.render();
/*=======/latestSales-chart/=======*/
var options = {
  series: [{
    name: 'TEAM A',
    type: 'area',
    data: ['3', '1.7', '2.9', '2.5', '4', '2', '1.7', '4', '3', '4'],
  },],
  colors: ['rgba(var(--primary))'],
  chart: {
    height: 160,
    type: 'line',
    toolbar: {
      show: false,
    }
  },
  grid: {
    show: true,
    borderColor: 'var(--border-light)',
    position: 'back',
  },
  stroke: {
    curve: 'smooth'
  },
  fill: {
    type: 'solid',
    opacity: [0.20, 1],
    type: 'pattern',
    pattern: {
      style: ['verticalLines', 'horizontalLines'],
      width: 3,
      height: 1,
      strokeWidth: 1,
    },
  },
  markers: {
    size: 5,
    hover: {
      size: 7,
    }
  },
  xaxis: {
    categories: ['Sun', '', 'Mon', '', 'Tue', '', 'Wed', '', 'Thu', '', 'Fri', '', 'Sat'],
    labels: {
      minHeight: undefined,
      maxHeight: 24,
      offsetX: 0,
      offsetY: 0,
      style: {
        colors: 'var(--light)',
        fontWeight: 400,
      },
      tooltip: {
        enabled: false,
      },
    },
  },
  yaxis: [{
    title: {
      min: -10,
      max: 20
    },
    labels: {
      show: true,
      align: 'right',
      minWidth: 0,
      maxWidth: 34,
      style: {
        ...fontCommon,
      },
    },
  }],
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: function (y) {
        if (typeof y !== "undefined") {
          return y.toFixed('$100') + " points";
        }
        return y;
      }
    }
  },
  responsive: [
    {
      breakpoint: 1599,
      options: {
        chart: {
          height: 210 ,
        },
      },
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 180,
        },
      },
    },
    {
      breakpoint: 1399,
      options: {
        chart: {
          height: 190,
        },
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 145,
        },
      },
    },
    {
      breakpoint: 1096,
      options: {
        chart: {
          height: 140,
        },
      },
    },
    {
      breakpoint: 1025,
      options: {
        chart: {
          height: 160,
        },
      },
    },
    {
      breakpoint: 991,
      options: {
        chart: {
          height: 170,
        },
      },
    },
  ],
};
var chart = new ApexCharts(document.querySelector("#latestSales-chart"), options);
chart.render();
/*=======/monthlyradial-chart/=======*/
var options = {
  series: [67, 62, 56],
  clockwise: false,
  legend: {
    show: true,
    position: "left",
    offsetX: -30,
    offsetY: 20,
    formatter: function (val, opts) {
      return opts.w.globals.series[opts.seriesIndex] + "%" + " <br/>" + val;
    },
    labels: {
      colors: 'var(--title)',
    },
  },
  labels: ['Oranges', 'Bananas', 'Berries'],
  chart: {
    type: 'radialBar',
    height: 220,
    offsetY: -10,
  },
  colors: ["#008193", "#EAB01E", "#615184"],
  plotOptions: {
    radialBar: {
      track: {
        show: true,
        strokeWidth: "120%",
        background: ["#eaecf2", "#f5f3ea", "#e2eff4"],
        opacity: 1,
        margin: 3,
      },
      strokeWidth: '75',
      startAngle: -95,
      endAngle: 270,
      dataLabels: {
        name: {
          fontSize: '16px',
        },
        value: {
          fontSize: '14px',
          color: 'var(--title)',
        },
        total: {
          show: true,
          label: 'Total',
          formatter: function (w) {
            return 249
          }
        }
      }
    },
  },
  responsive: [
    {
      breakpoint: 1799,
      options: {
        chart: {
          height: 260,
        },  
        legend: {
          show: false,
        }
      },
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 200,
        },
      },  
    },  
    {
      breakpoint: 1399,
      options: {
        chart: {
          height: 191,
        },
      },  
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 300,
        },  
        legend: {
          show: true,
          offsetX: 20,
          offsetY: 50,
        }
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 280,
        },  
        legend: {
          show: true,
          offsetX: 50,
          offsetY: 30,
        }
      },
    },
    {
      breakpoint: 769,
      options: {
        chart: {
          height: 400,
        },  
        legend: {
          show: true,
          position: "bottom",
          offsetX: 10,
          offsetY: 10,
        }
      },
    },
    {
      breakpoint: 579,
      options: {
        chart: {
          height: 300,
        },  
        legend: {
          show: false,
        }
      },
    },  
    {
      breakpoint: 576,
      options: {
        chart: {
          height: 400,
        },  
        legend: {
          show: true,
        }
      },
    },  
    {
      breakpoint: 482,
      options: {
        chart: {
          height: 400,
        },  
        legend: {
          show: true,
        }
      },
    },
  ]
};
var chart = new ApexCharts(document.querySelector("#monthlyradial-chart"), options);
chart.render();
/*=======/ Last Orders /=======*/
var lastOrdersOption = {
  series: [{
    type: 'candlestick',  
    data: [{
        x: 'Jun',
        y: [30, 10, 40, 53],
      },
      {
        x: 'Feb',
        y: [35, 10, 40, 57],
      },
      {
        x: 'Mar',
        y: [40, 10, 50, 65],
      },
      {
        x: 'Apr',
        y: [25, 10, 40, 45],
      },
      {
        x: 'May',
        y: [30, 10, 40, 55],
      },
      {
        x: 'Jun',
        y: [17, 10, 30, 35],
      },
      {
        x: 'Jul',
        y: [25, 10, 40, 55],
      },
      {
        x: 'Aug',
        y: [30, 10, 40, 50],
      },
      {
        x: 'Sep',
        y: [30, 10, 40, 50],
      },
      {
        x: 'Sep',
        y: [30, 10, 40, 50],
      },
    ]
  }],
    chart: {
    type: 'candlestick',
    height: 310,
    yaxis: -30,
    toolbar: {
      show: false,
    } 
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    show: true,
    curve: 'smooth',
    lineCap: 'butt',
    colors: '#D6D6D6',
    dashArray: 0, 
  },
  xaxis: {
    labels: {
      style: {
        colors: 'var(--light)',
        fontWeight: 400,
      },
    }
  },
  yaxis: {
    show: false,
    showAlways: false,
  },
  plotOptions: {
    bar: {
      columnWidth: '20%',
      endingShape: 'rounded',
      startingShape: 'rounded',
    },
    candlestick: {
      endingShape: 'rounded',
      startingShape: 'rounded',
      colors: {
        upward: '#008193',
        downward: '#EAB01E'
      },
      wick: {
        useFillColor: true,
        colors: {                           
          upward: '#008193',
          downward: '#EAB01E'
        },
      },
    },
  },
  responsive: [
    {
      breakpoint: 1599,
      options: {
        chart: {
          height: 290,
        }
      },
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 280,
        },
      },
    },
  ],
  colors: ['#008193', '#008193', '#008193', '#008193', '#EAB01E', '#008193','#008193', '#008193'],
};
var lastOrdersChartEl = new ApexCharts(document.querySelector("#lastOrdersChart"), lastOrdersOption);
lastOrdersChartEl.render();          