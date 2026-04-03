$(function() {
  $('.counting').each(function () {
    var $this = $(this),
      countTo = $this.attr('data-count');
    $({ countNum: $this.text() }).animate({
      countNum: countTo
    },
      {
        duration: 3000,
        easing: 'linear',
        step: function () {
          $this.text(Math.floor(this.countNum));
        },
        complete: function () {
          $this.text(this.countNum);
        }
      });
  });
});

// LearningChart Start
var options = {

  series: [{
    name: 'Materials',
    data: [31, 35, 40, 20, 20, 56, 51, 78, 78, 29, 16, 100],
  }, {
    name: 'Exam',
    data: [11, 32, 15, 45, 82, 89, 34, 52, 41, 37, 45, 20],
  }],
  chart: {
    height: 300,
    type: 'line',
  },
  colors: [getLocalStorageItem('color-primary', '#056464'), '#fac10f'],
  legend: {
    position: 'top',
    fontSize: '14px',
    fontFamily: '"Poppins", sans-serif',
    labels: {
      colors: 'rgba(var(--secondary),1)',
      useSeriesColors: false
    },
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: ['smooth', 'smooth'],
    lineCap: 'butt',
    dashArray: [4, 0],
  },
  xaxis: {
    categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  yaxis: {
    show: true,
    offsetX: 0,
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  grid: {
    borderColor: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 0.5),
    strokeDashArray: 4,
    padding: {
      top: 10,
      bottom: 2,
      right: 0,
    },
  },
  tooltip: {
    x: {
      show: false,
    },
    style: {
      fontSize: '16px',
      fontFamily: '"Poppins", sans-serif',
    },
  },
  responsive: [{
    breakpoint: 1440,
    options: {
      yaxis: {
        show: false,
        offsetX: 0,
      },
    },
  }, {
    breakpoint: 480,
    options: {
      chart: {
        height: 250,
      },
    },
  }]
};

var chart = new ApexCharts(document.querySelector("#learningChart"), options);
chart.render();

//  targetChart

var options = {
  series: [80, 45, 67],
  chart: {
  height: 335,
  type: 'radialBar',
},
colors: ['rgba(var(--primary),1)','rgba(var(--danger),1)','rgba(var(--warning),1)'],
plotOptions: {
  radialBar: {
    dataLabels: {
      name: {
        fontSize: '18px',
      },
      value: {
        fontSize: '20px',
        fontFamily: 'Poppins, sans-serif',
        fontWeight: 500,
        color: 'rgba(var(--primary),1)',
      },
      total: {
        show: true,
        label: 'Total',
      }
    }
  }
},
labels: ['New Target', 'Resolve Target', 'Total'],
responsive: [{
  breakpoint: 1250,
  options: {
    chart:{
      height:300,
    }
  }
}]
};

var chart = new ApexCharts(document.querySelector("#targetChart"), options);
chart.render();


// Users chart
var options = {
  series: [{
    name: 'series1',
    data: [5,10,10,8,8,15,15,12,12,10,10,20,20,16,16,20,20]
  }],
  chart: {
    height: 150,
    type: 'area',
    parentHeightOffset: 0,
    sparkline: {
      enabled: true
    }
  },
  dataLabels: {
    enabled: false
  },
  fill: {
    type: "gradient",
    colors: [hexToRGB(getLocalStorageItem('color-primary','#056464'))],
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.8,
      stops: [0, 90, 100]
    }
  },
  stroke: {
    width: 2,
    curve: 'smooth'
  },
  yaxis: {
    show: false,
    labels: {
      show: false
    },
    axisBorder: {
      show: false
    },
    axisTicks: {
      show: false
    }
  },
  xaxis: {
    show: false,
    labels: {
      show: false
    },
    axisBorder: {
      show: false
    },
    axisTicks: {
      show: false
    }
  },
  grid: {
    show: false,
    padding: {
      top: 10,
      bottom: 2,
      left: -10,
      right: 0
    },
    xaxis: {
      lines: {
        show: false
      }
    },
    yaxis: {
      lines: {
        show: false
      }
    },
  },
  tooltip: {
    enabled: false,
  }
};

var chart = new ApexCharts(document.querySelector("#cardChart1"), options);
chart.render();

var options = {
  series: [{
    name: 'series1',
    // data: [30,22,25,18,20,10,12,10,8,5]
    data: [5,8,10,12,10,20,18,25,22,30]
  }],
  chart: {
    height: 160,
    type: 'area',
    parentHeightOffset: 0,
    sparkline: {
      enabled: true
    }
  },
  colors:['rgba(var(--success),1)'],
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 0,
      opacityFrom: 1,
      opacityTo: .1,
      stops: [0, 90, 100]
    }
  },
  yaxis: {
    axisBorder: {
      show: false
    },
  },
  xaxis: {
    categories: ["2014" ,"2015" ,"2016" ,"2017" , "2018", "2019", "2020", "2021", "2022", "2023"],
    axisBorder: {
      show: false
    },
  },
  grid: {
    show: false,
    xaxis: {
      lines: {
        show: false
      }
    },
    yaxis: {
      lines: {
        show: false
      }
    },
    padding: {
      top: 10,
      bottom: 0,
      left: -10,
      right: 0 
    },
  },
  tooltip: {
    enabled: false,
  },
};

var chart = new ApexCharts(document.querySelector("#collectionChart"), options);
chart.render();