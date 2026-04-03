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

//  **------Bar Chart Border Radius**

   
var options = {
  series: [{
  name: 'Website Blog',
  type: 'column',
  data: [20, 15, 20, 25, 30, 40, 55]
}, {
  name: 'Social Media',
  type: 'line',
  data: [30, 25, 40, 35, 50, 45, 60]
}],
  chart: {
  height: 170,
  type: 'line',
},
colors:['rgba(var(--primary),1)','rgba(var(--success),1)'],
stroke: {
  curve: 'smooth',
  width: [0, 3]
},
markers: {
  size: 4,
},
plotOptions: {
  bar: {
    columnWidth: '45px',
    distributed: true,
    borderRadius: 8,
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
    top: 0,
    bottom: -20,
    left: -10,
    right: 0
  },
},
tooltip: {
  enabled: false,
},
legend: {
  show: false
},
};

var chart = new ApexCharts(document.querySelector("#myChart"), options);
chart.render();

//  **------Bar Chart Border Radius**
var options = {
  series: [{
  type: 'column',
  data: [35, 13, 25, 9, 19, 5, 12],
}, {
  type: 'line',
  data: [40, 25, 30, 20, 25, 10, 15],
}],
  chart: {
  height: 170,
  type: 'line',
  offsetX: 0,
  offsetY: 0,
},
colors:['rgba(var(--danger),1)','rgba(var(--success),1)'],
stroke: {
  curve: 'smooth',
  width: [0, 2]
},
markers: {
  size: 4,
},
plotOptions: {
  bar: {
    columnWidth: '45px',
    borderRadius: 8,
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
    show: false,
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
    top: 0,
    bottom: -20,
    left: -10,
    right: 0
  },
},
tooltip: {
  enabled: false,
},
legend: {
  show: false
},
};

var chart = new ApexCharts(document.querySelector("#myChart1"), options);
chart.render();

// Project data table js
$(function() {
  $('#projectTable').DataTable();
});

// weekearningChart
var options = {
  series: [{
    name: 'series1',
    data: [31, 40, 28, 80, 30, 60, 25]
  }],
  annotations: {
    xaxis: [{
      x: 'W',
      borderWidth: 2,
      borderColor: 'rgba(116, 120, 141,0.2)',
      strokeDashArray: 2,
    },
    ],
    points: [{
      x: 'W',
      y: 80,
      marker: {
        size: 5,
        colors: '#fff',
        strokeColor: getLocalStorageItem('color-primary','#056464'),
        strokeWidth: 4,
      },
    }],
  },
  chart: {
    height: 400,
    type: 'line'
  },
  stroke: {
    width: 2
  },
  colors: [hexToRGB(getLocalStorageItem('color-primary','#056464'))],
  markers: {
    size: 0,
    colors: '#fff',
    strokeColors: hexToRGB(getLocalStorageItem('color-primary','#056464')),
    strokeWidth: 4,
    hover: {
      size: 6,

    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {

    borderColor: 'rgba(var(--dark),.4)',
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false
      },
    },
  },
  xaxis: {
    categories: ["S", "M", "T", "W", "T", "F", "S"],
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 400,
      },
    }
  },
  yaxis: {
    show: true,
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  tooltip:{
    x: {
      show: false,
  },
  style: {
    fontSize: '14px',
    fontFamily: "'Poppins', sans-serif",
  },
  },
  responsive: [{
    breakpoint: 992,
    options: {
      chart: {
        height: 320
      },
    }
  }]
};

var chart = new ApexCharts(document.querySelector("#weekearningChart"), options);
chart.render();

// monthearningChart

var options = {
  series: [{
    name: 'series1',
    data: [10, 35, 25, 60, 25, 30, 18]
  }],
  annotations: {
    xaxis: [{
      x: 'Apr',
      borderWidth: 2,
      borderColor: 'rgba(116, 120, 141,0.2)',
      strokeDashArray: 2,
    },
    ],
    points: [{
      x: 'Apr',
      y: 60,
      marker: {
        size: 5,
        colors: '#fff',
        strokeColor: getLocalStorageItem('color-secondary','#74788d'),
        strokeWidth: 4,
      }
    }],
  },
  chart: {
    height: 380,
    type: 'line'
  },
  stroke: {
    width: 2
  },
  colors: ["rgb(116, 120, 141)"],
  markers: {
    size: 0,
    colors: '#fff',
    strokeColors: 'rgb(116, 120, 141)',
    strokeWidth: 4,
    hover: {
      size: 6,

    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    borderColor: 'rgba(var(--dark),.4)',
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false
      },
    },
  },
  xaxis: {
    categories: ["Jan", "Fab", "Mar", "Apr", "May", "Jun", "Jul"],
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  yaxis: {
    show: false,
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  tooltip:{
    x: {
      show: false,
  },
  style: {
    fontSize: '14px',
    fontFamily: "'Poppins', sans-serif",
  },
  },
  responsive: [{
    breakpoint: 992,
    options: {
      chart: {
        height: 320
      },
    }
  }]
};

var chart = new ApexCharts(document.querySelector("#monthearningChart"), options);
chart.render();

//  yearearningChart

var options = {
  series: [{
    name: 'series1',
    data: [20, 15, 25, 15, 30, 15]
  }],
  chart: {
    height: 380,
    type: 'line'
  },
  stroke: {
    width: 2
  },
  colors: ["rgba(var(--danger),1)"],
  markers: {
    size: 0,
    colors: '#fff',
    strokeColors: 'rgba(15, 180, 80)',
    strokeWidth: 4,
    hover: {
      size: 6,

    }
  },
  annotations: {
    xaxis: [{
      x: '2021',
      borderWidth: 2,
      borderColor: 'rgba(116, 120, 141,0.2)',
      strokeDashArray: 2,
    },
    ],
    points: [{
      x: '2021',
      y: 30,
      marker: {
        size: 5,
        colors: '#fff',
        strokeColor: '#ea5659',
        strokeWidth: 4,
      }
    }],
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    borderColor: 'rgba(var(--dark),.4)',
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false
      },
    },
  },  
  xaxis: {
    categories: ["2017", "2018", "2019", "2020", "2021", "2022", "2023"],
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  yaxis: {
    show: false,
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary','#74788d'),1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  tooltip:{
    x: {
      show: false,
  },
  style: {
    fontSize: '14px',
    fontFamily: "'Poppins', sans-serif",
  },
  },
  responsive: [{
    breakpoint: 992,
    options: {
      chart: {
        height: 320
      },
    }
  }]
};

var chart = new ApexCharts(document.querySelector("#yearearningChart"), options);
chart.render();

// calender js

$(function () {
  $('.calendar').pignoseCalendar();
});

// checkbox js
document.getElementById('select-all').onclick = function () {
  var checkboxes = document.querySelectorAll('input[type="checkbox"]');
  for (var checkbox of checkboxes) {
    checkbox.checked = this.checked;
  }
}