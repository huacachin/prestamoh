//analytics_dashboard

var options = {
  series: [{
    name: 'series1',
    data: [5,10,8,15,12,10,20,16,20]
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

// cardChart 2
var options = {
  series: [{
    name: 'series1',
    data:[20,25,15,12,30,25,16,28,20]
  }],
  chart: {
    height: 150,
    type: 'area',
    parentHeightOffset: 0,
    sparkline: {
      enabled: true
    }
  },
  colors: ['rgba(var(--success),1)'],
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  fill: {
    type: "gradient",
    colors: ['#0fb450'],
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.4,
      stops: [0, 90, 100]
    }
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
      bottom: 2,
      left: -10,
      right: 0
    },
  },
  tooltip: {
    enabled: false,
  }
};

var chart = new ApexCharts(document.querySelector("#cardChart2"), options);
chart.render();

// collectionChart

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

// Visitors Countries chart

var options = {
  series: [{
    name: "data1",
    data: [45, 52, 38, 24, 33, 26, 21, 20, 6, 8, 15, 10]
  },
  {
    name: 'data2',
    data: [87, 57, 74, 99, 75, 38, 62, 47, 82, 56, 45, 47]
  }
],
  chart: {
  height: 90,
  type: 'line',
},
dataLabels: {
  enabled: false
},
colors:['rgba(var(--danger),1)','rgba(var(--primary),1)'],
stroke: {
  width: [3, 3],
  curve: 'smooth',
  dashArray: [0, 5]
},
markers: {
  size: 0,
  hover: {
    sizeOffset: 6
  }
},
legend: {
  show: false,
},
xaxis: {
  categories: ['01 Jan', '02 Jan', '03 Jan', '04 Jan', '05 Jan', '06 Jan', '07 Jan', '08 Jan', '09 Jan',
    '10 Jan', '11 Jan', '12 Jan'
  ],
  labels: {
      show: false,
  },
  axisBorder: {
      show: false,
  },
  axisTicks: {
      show: false,
  },
  tooltip: {
      enabled: false,
  },
},
yaxis: {
  labels: {
      show: false,
  },
},
grid: {
  show: false,
  padding: {
      top: -10,
      right: 0,
      bottom: -18,
      left: 0,
  },
},
tooltip: {
  enabled: false
},
};
$(function() {
var chart = new ApexCharts(document.querySelector("#sharesChart"), options);
chart.render();
});
// checkbox js
document.getElementById('select-all').onclick = function () {
  var checkboxes = document.querySelectorAll('input[type="checkbox"]');
  for (var checkbox of checkboxes) {
    checkbox.checked = this.checked;
  }
}

//  Modal js

$(function () {
  $('#welcomeCard').modal('show');
});