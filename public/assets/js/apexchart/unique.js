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
    height: 210,
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
        strokeWidth: 4,
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
      width: 10,
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
      breakpoint: 1799,
      options: {
        chart: {
          height: 225,
        },
      }
    },
    {
      breakpoint: 1699,
      options: {
        chart: {
          height: 230,
        },
      }
    },
  {
      breakpoint: 1400,
      options: {
        chart: {
          height: 185,
        },
      }
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 235,
        },
      }
    },
    {
      breakpoint: 1500,
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
          height: 170,
        },
      }
    },
    {
      breakpoint: 1388,
      options: {
        chart: {
          height: 200,
        },
      }
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 270,
        },
      }
    },
    {
      breakpoint: 1199,
      options: {
        chart: {
          height: 175,
        },
      }
    },
    {
      breakpoint: 1095,
      options: {
        chart: {
          height: 170,
        },
      }
    },
    {
      breakpoint: 991,
      options: {
        chart: {
          height: 180,
        },
      }
    },
    {
      breakpoint: 769,
      options: {
        chart: {
          height: 215,
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
        background: 'rgba(var(--primary),0.1)',
        strokeWidth: '90%',
        startAngle: -100,
        startAngle: 0,
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
      breakpoint: 1709,
      options: {
        chart: {
          height: 250,
        },
      },
    }, 
    { 
      breakpoint: 1659,
      options: {
        chart: {
          height: 240,
        },
      },
    }, 
    { 
      breakpoint: 1600,
      options: {
        chart: {
          height: 230,
        },
      },
    }, 
    { 
      breakpoint: 1540,
      options: {
        chart: {
          height: 220,
        },
      },
    }, 
    { 
      breakpoint: 1460,
      options: {
        chart: {
          height: 200,
        },
      },
    }, 
    { 
      breakpoint: 1499,
      options: {
        chart: {
          height: 214,
        },
      },
    }, 
    { 
      breakpoint: 1399,
      options: {
        chart: {
          height: 206,
        },
      },
    }, 
    { 
      breakpoint: 1226,
      options: {
        chart: {
          height: 190,
        },
      },
    }, 
    { 
      breakpoint: 1199,
      options: {
        chart: {
          height: 280,
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
      breakpoint: 1699,
      options: {
        chart: {
          height: 95,
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
      name: 'Desktops',
      data: [30, 50, 50, 50, 25, 25, 25, 2, 2, 2, 25, 25, 25, 62, 62, 62, 35, 35, 35, 66, 66],
    },
  ],
  chart: {
    height: 150,
    type: 'line',
    offsetY: -50,
    bottom: 0,
    zoom: {
      enabled: false,
    },
    toolbar: {
      show: false,
    },
    dropShadow: {
      enabled: true,
      top: 0,
      left: 0,
      bottom: 0,
      blur: 3,
      color: '#000',
      opacity: 0.1,
    },
  },
  colors: ['rgba(var(--tertiary),1)'],
  dataLabels: {
    enabled: false,
  },
  stroke: {
    curve: 'smooth',
  },
  tooltip: {
    enabled: false,
  },
  grid: {
    yaxis: {
      lines: {
        show: false,
      },
    },
    padding: {
      left: 0,
      right: 0,
      bottom: 0,
      top: 0,
    },
  },
  xaxis: {
    labels: {
      show: false,
    },
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    show: false,
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
  ],
};
const SubscripationchartEl = new ApexCharts(document.querySelector('#Subscripationchart'), option);
SubscripationchartEl.render();
/*=======/Statisticschart/=======*/
var options = {
  series: [{
    data: [120, 60, 100, 64]
  },
  {
    data: [0, 0, 0, 0]
  },
  {
    data: [108, 85, 40, 115]
  }],
  chart: {
    type: 'bar',
    height: 351,
    toolbar: {
      show: false,
    }
  },
  colors: ['rgba(var(--primary))', 'rgba(var(--secondary))', 'rgba(var(--secondary))'],
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 5,
      barHeight: '50%',
      dataLabels: {
        position: 'top',
      },
    }
  },
  dataLabels: {
    enabled: false,
    offsetX: -6,
    style: {
      fontSize: '12px',
      colors: ['#fff']
    }
  },
  tooltip: toolTipMini,
  stroke: {
    show: true,
    width: 1,
    colors: ['#fff']
  },
  // tooltip: {
  //   shared: true,
  //   intersect: false
  // },
  xaxis: {
    categories: ['Visitors', 'Subscriber', 'Contributor', 'Author'],
    labels: {
      low: 0,
      style: {
        colors: 'var(--light)',
        fontWeight: 500,
      },
    },
    axisBorder: {
      low: 0,
      offsetX: -10,
    },
    show: false,
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    min: 10,
    max: 120,
    labels: {
      show: true,
      style: {
        ...fontCommon,
        fontWeight: 600,
      },
    },
  },
  grid: {
    xaxis: {
      lines: {
        show: false
      },
    },
    yaxis: {
      title: {
        text: undefined,
      },
      lines: {
        show: false
      },
    },
  },
  legend: {
    show: false,
  },
  responsive: [
    {
      breakpoint: 1799,
      options: {
        chart: {
          height: 350,
        },
      },
    },
    {
      breakpoint: 1599,
      options: {
        chart: {
          height: 330,
        },
      },
    },
    {
      breakpoint: 1499,
      options: {
        chart: {
          height: 335,
        },
      },
    },
    {
      breakpoint: 1399,
      options: {
        chart: {
          height: 335,
        },
      },
    },
    {
      breakpoint: 1200,
      options: {
        chart: {
          height: 490,
        },
      },
    },
    {
      breakpoint: 1199,
      options: {
        chart: {
          height: 460,
        },
      },
    },
  ],
};
var chart = new ApexCharts(document.querySelector("#Statisticschart"), options);
chart.render();
/*=======/INCOME/=======*/
var IncomeOption = {
  series: [
    {
      name: 'series2',
      data: [60 , 45, 70, 41, 65, 30, 81, 75, 82, 50, 55 ],
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
  tooltip: toolTipMini,
  stroke: {
    curve: 'smooth',
    width: 2,
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
      breakpoint: 675,
      options: {
        chart: {
          height: 50,
          width: 200,
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
    width: 2,
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
      breakpoint: 675,
      options: {
        chart: {
          height: 50,
          width: 200,
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
var ExpensesEl = new ApexCharts(document.querySelector('#Expenses'), ExpensesOption);
ExpensesEl.render();
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
    width: 2,
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
      breakpoint: 675,
      options: {
        chart: {
          height: 50,
          width: 200,
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