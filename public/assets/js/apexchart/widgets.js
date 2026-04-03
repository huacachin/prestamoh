/*=======/revenueChart/=======*/
var optionsrevenue = {
  series: [
    {
      type: 'area',
      data: [35, 40, 30, 46, 36, 45]
    },
    {
      type: 'line',
      data: [27, 25, 48, 46, 44, 53],
    },
  ],
  chart: {
    type: 'line',
    height: 200,
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
  colors: ['#01A1B9', '#EAB01E'],
  dataLabels: {
    enabled: false
  },
  legend: {
    show: false,
  },
  stroke: {
    curve: 'smooth',
    width: [2, 3],
    dashArray: [0, 2]
  },
  grid: {
    show: true,
    borderColor: 'var(--border-light)',
    position: 'back',
  },
  tooltip: {
    x: {
      show: false,
    },
    z: {
      show: false
    }
  },
  plotOptions: {
    distributed: true,
    bar: {
      borderRadius: 6,
    }
  },
  fill: {
    opacity: [0.05, 1],
  },
  annotations: {
    points: [{
      x: "Apr",
      y: 45,
      marker: {
        size: 10,
        fillColor: '#008193',
        strokeColor: '#ffffff',
        strokeWidth: 2,
        radius: 2,
      },
    }]
  },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
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
    axisTicks: {
      show: false,
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
      minWidth: 0,
      maxWidth: 34,
      style: {
        ...fontCommon,
      },
      formatter: (value) => {
        return `$${value}0`;
      },
    },
  },
  responsive: [
    {
      breakpoint: 1899,
      options: {
        chart: {
          height: 190,
        },
      }
    },
    {
      breakpoint: 1199,
      options: {
        chart: {
          height: 190,
        },
      }
    },
    {
      breakpoint: 1095,
      options: {
        chart: {
          height: 190,
        },
      }
    },
    {
      breakpoint: 991,
      options: {
        chart: {
          height: 190,
        },
      }
    },
    {
      breakpoint: 875,
      options: {
        chart: {
          height: 240,
        },
      }
    },
    {
      breakpoint: 768,
      options: {
        chart: {
          height: 210,
        },
      }
    },
  ]
};
var revenueChart = new ApexCharts(document.querySelector("#revenueChart"), optionsrevenue);
revenueChart.render();
/*=======/Incomechrt/=======*/
var income = {
  series: [78],
  chart: {
    type: 'radialBar',
    offsetY: -40,
    height: 260,
    sparkline: {
      enabled: false,
    },
  },
  plotOptions: {
    radialBar: {
      startAngle: -90,
      endAngle: 90,
      hollow: {
        size: '60%',
      },
      track: {
        background: "#cae3e8",
        strokeWidth: '90%',
        startAngle: -100,
        endAngle: 100,
        opacity: 0.1,
        // strokeWidth: '97%',
        dropShadow: {
          enabled: true,
          top: 2,
          left: 0,
          bottom: 0,
          color: '#999',
          opacity: 1,
          blur: 2
        }
      },
      dataLabels: {
        name: {
          show: false,
          color: 'var(--title)',
          fontSize: '17px',
        },
        value: {
          offsetY: -2,
          fontSize: '22px',
          ...fontCommon,
        }
      }
    }
  },
  xaxis: {
  },
  stroke: {
    lineCap: 'round'
  },
  colors: ['#008193'],
  fill: {
    gradient: {
      inverseColors: false,
    },
  },
  labels: ['Average Results'],
  dataLabels: {
    colors: undefined,
  },
  responsive: [
    {
      breakpoint: 1300,
      options: {
        chart: {
          height: 220,
        },
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 260,
        },
      },
    },
  ]
};
var IncomechrtchartEl = new ApexCharts(document.querySelector("#Incomechrt"), income);
IncomechrtchartEl.render();
/*=======/expensesChart/=======*/
var expensesOption = {
  series: [
    {
      name: 'series2',
      data: [25, 20, 40, 60, 27, 20, 24, 15, 50, 30, 20, 25, 14, 30],
    },
  ],
  colors: ["#EAB01E", "#f9f1dc", "#f9f1dc", "#EAB01E", "#EAB01E", "#f9f1dc", "#f9f1dc", "#EAB01E", "#f9f1dc", "#EAB01E", "#EAB01E", "#f9f1dc", "#f9f1dc", "#EAB01E"],
  chart: {
    height: 95,
    type: 'bar',
    sparkline: {
      enabled: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    curve: 'smooth',
  },
  plotOptions: {
    bar: {
      vartical: true,
      borderRadius: 5,
      distributed: true,
      barHeight: '35%',
      dataLabels: {
        position: 'top',
      },
    }
  },
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
var expensesEl = new ApexCharts(document.querySelector('#expensesChart'), expensesOption);
expensesEl.render();
/*=======/Subscripationchart/=======*/
const option = {
  series: [
    {
      name: 'series2',
      data: [30, 50, 50, 50, 25, 25, 25, 2, 2, 2, 25, 25, 25, 62, 62, 62, 35, 35, 35, 66, 66],
    },
  ],
  chart: {
    height: 95,
    type: 'area',
    sparkline: {
      enabled: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  colors: ['#615184'],
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
  markers: {
    discrete: [{
      seriesIndex: 0,
      dataPointIndex: 1,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 6,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 9,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 12,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 13,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 16,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    {
      seriesIndex: 0,
      dataPointIndex: 19,
      fillColor: '#fff',
      strokeColor: '#615184',
      size: 3,
      shape: "circle"
    },
    ],
  },
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
const SubscripationchartEl = new ApexCharts(document.querySelector('#Subscripationchart'), option);
SubscripationchartEl.render();
/*=======/customerChart/=======*/
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
  yaxis: {
    labels: {
      show: false,
      align: 'right',
      style: {
        ...fontCommon,
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
/*=======/view chart/=======*/
// view chart
var optionsview = {
  series: [{
    type: 'line',
    data: [25.00, 40.00, 35.00, 52.00, 42.00, 50.00, 35.00],
  }],
  chart: {
    height: 290,
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
      y: 52,
      marker: {
        size: 10,
        fillColor: '#008193',
        strokeColor: '#ffffff',
        strokeWidth: 2,
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
      breakpoint: 1366,
      options: {
        chart: {
          height: 270,
        },
      }
    },
    {
      breakpoint: 1201,
      options: {
        chart: {
          height: 240,
        },
      }
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 270,
        },
      }
    },
    {
      breakpoint: 481,
      options: {
        annotations: {
          points: [{
            x: "Feb",
            y: 40,
            marker: {
              size: 10,
              fillColor: '#ffffff',
              strokeColor: '#008193',
              strokeWidth: 7,
              radius: 2,
              cssClass: 'apexcharts-custom-class'
            },
          }]
        },
      }
    }
  ]
};
var viewchart = new ApexCharts(document.querySelector("#view"), optionsview);
viewchart.render();
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
/*=======/Total Likes area Spark line/=======*/
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
/*=======/Radial Chart/=======*/
var totalCommentOption = {
  series: [60],
  chart: {
    type: 'radialBar',
    height: 120,
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
        colors: 'var(--light)',
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
    {
      breakpoint: 376,
      options: {
        chart: {
          height: 90,
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
    },
  },
  xaxis: {
    crosshairs: {
      width: 1,
    },
  },
  colors: ['rgba(var(--secondary),1)'],
  fill: {
    colors: 'rgba(var(--secondary), 0.3)',
    type: 'solid',
  },
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
/*=======/INCOME/=======*/
var IncomeOption = {
  series: [
    {
      name: 'series2',
      data: [50, 30, 70, 22, 78, 2, 80, 50, 85, 25, 65],
    },
  ],
  chart: {
    width: 144,
    height: 48,
    type: 'area',
    sparkline: {
      enabled: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  colors: ['#008193'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 0,
      opacityFrom: 0.0,
      opacityTo: 0.0,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  responsive: [
    {
      breakpoint: 1800,
      options: {
        chart: {
          height: 50,
          width: 100,
        },
      },
    },
    {
      breakpoint: 575,
      options: {
        chart: {
          height: 50,
          width: 150,
        },
      },
    },
  ],
};
var IncomeEl = new ApexCharts(document.querySelector('#Income'), IncomeOption);
IncomeEl.render();
/*=======/Expenses/=======*/
var ExpensesOption = {
  series: [
    {
      name: 'series2',
      data: [70, 5, 50, 10, 52, 40, 52, 2, 40, 25, 57],
    },
  ],
  chart: {
    // width: 144,
    height: 48,
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
      shadeIntensity: 0,
      opacityFrom: 0.0,
      opacityTo: 0.0,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
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
            top: 0,
            right: 0,
            bottom: 0,
            left: 5,
          },
        },
      },
    },
    {
      breakpoint: 576,
      options: {
        chart: {
          height: 50,
          width: 150,
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
var ExpensesEl = new ApexCharts(document.querySelector('#Expenses'), ExpensesOption);
ExpensesEl.render();
/*=======/investment/=======*/
var investmentOption = {
  series: [
    {
      name: 'series2',
      data: [10, 12, 50, 30, 75, 15, 24, 2, 40, 15, 57, 10],
    },
  ],
  chart: {
    width: 144,
    height: 48,
    type: 'area',
    sparkline: {
      enabled: true,
    },
  },
  dataLabels: {
    enabled: false,
  },
  colors: ['#615184'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 0,
      opacityFrom: 0.0,
      opacityTo: 0.0,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  tooltip: toolTipMini,
  responsive: [
    {
      breakpoint: 1800,
      options: {
        chart: {
          height: 50,
          width: 110,
        },
      },
    },
    {
      breakpoint: 1900,
      options: {
        chart: {
          height: 50,
          width: 120,
        },
      },
    },
    {
      breakpoint: 1460,
      options: {
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
      breakpoint: 575,
      options: {
        chart: {
          height: 50,
          width: 150,
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
var investmentEl = new ApexCharts(document.querySelector('#investment'), investmentOption);
investmentEl.render();