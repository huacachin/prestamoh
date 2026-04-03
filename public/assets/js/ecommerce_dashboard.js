$(function () {
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

// Ecommerce_dashboard js
var options = {
  series: [
    {
      name: 'sales',
      type: 'bar',
      data: [44, 55, 41, 67, 22, 43, 53, 22, 12, 65],
      colors: getLocalStorageItem('color-primary', '#056464')
    }, {
      name: 'Earning',
      type: 'bar',
      data: [44, 55, 41, 67, 22, 43, 53, 22, 12, 30],
      colors: getLocalStorageItem('color-primary', '#056464')
    }, {
      name: 'order',
      type: 'bar',
      data: [-13, -23, -20, -8, -13, -27, -24, -15, -17, -25],
      colors: '#467ffb99'
    }
  ],

  chart: {
    height: 280,
    type: 'bar',
    stacked: true,
  },
  dataLabels: {
    enabled: false
  },
  colors: [
    hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 0.1),
    hexToRGB(getLocalStorageItem('color-primary', '#056464'), 1),
    'rgba(250, 193, 84,1)',
  ],

  grid: {
    borderColor: hexToRGB(getLocalStorageItem('color-primary', '#056464'), 0.2),
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false
      },
    },
    yaxis: {
      lines: {
        show: true,
      }
    },
  },

  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: "20%",
      borderRadius: [7, 7, 7],


      dataLabels: {
        total: {
          enabled: true,
          style: {
            fontSize: '13px',
            fontWeight: 900
          }
        }
      },
    },

  },
  legend: {
    show: false,
  },
  xaxis: {
    show: false,
    categories: ['2011', '2012', '2013', '2014', '2015', '2016', '2017', '2019', '2020', '2021'],
    axisBorder: {
      show: false,
    },
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 1),
        fontSize: '14px',
        fontWeight: 400,
      },
    }
  },
  yaxis: {
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 1),
        fontSize: '14px',
        fontWeight: 500,
      },
    }
  },
  fill: {
    opacity: 1,
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
    breakpoint: 1500,
    options: {
      xaxis: {
        labels: {
          show: true,
          rotate: -45,
          rotateAlways: true,
        }
      }
    }
  }, {
    breakpoint: 480,
    options: {
      chart: {
        height: 240,
      },
      plotOptions: {
        bar: {
          columnWidth: "35%",
        }
      },
      xaxis: {
        labels: {
          show: false,
        }
      },
      yaxis: {
        labels: {
          show: false,
        }
      }
    }
  }]
};

var chart = new ApexCharts(document.querySelector("#earningChart"), options);
chart.render();


// map js

$(function () {
  $('#e-markers').vectorMap({
    map: 'world_mill',
    normalizeFunction: 'polynomial',
    hoverOpacity: 0.7,
    hoverColor: false,
    backgroundColor: 'var(--white)',
    regionStyle: { initial: { fill: 'rgba(var(--primary))' } },
  });
});

//  **------Doughnut**

var options = {
  series: [14, 23, 21],
  chart: {
    height: 350,
    type: 'polarArea',
  },
  stroke: {
    colors: ['#fff']
  },
  fill: {
    opacity: 0.8
  },
  colors: ['rgba(var(--danger),1)', 'rgba(var(--primary),1)', 'rgba(var(--warning),1)'],

  legend: {
    show: false,
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
    breakpoint: 1500,
    options: {
      chart: {
        height: 300,
      }
    }
  }, {
    breakpoint: 1366,
    options: {
      chart: {
        height: 240,
      }
    }
  }]
};

var chart = new ApexCharts(document.querySelector("#userOverview"), options);
chart.render();




document.querySelector(".eyes-icon").addEventListener("click", () => {
  const content = document.querySelector(".card-pin").textContent;

  if (content === "**** ****") {
    document.querySelector(".card-pin").innerHTML = "1234 5678";
    document.querySelector(".eyes-icon").classList.add("ti-eye");
    document.querySelector(".eyes-icon").classList.remove("ti-eye-off");
  } else {
    document.querySelector(".card-pin").innerHTML = "**** ****";
    document.querySelector(".eyes-icon").classList.add("ti-eye-off");
    document.querySelector(".eyes-icon").classList.remove("ti-eye");
  }
})

document.querySelector(".eyes-icon1").addEventListener("click", () => {
  const content = document.querySelector(".card-pin1").textContent;

  if (content === "**** ****") {
    document.querySelector(".card-pin1").innerHTML = "8736 9872";
    document.querySelector(".eyes-icon1").classList.add("ti-eye");
    document.querySelector(".eyes-icon1").classList.remove("ti-eye-off");
  } else {
    document.querySelector(".card-pin1").innerHTML = "**** ****";
    document.querySelector(".eyes-icon1").classList.add("ti-eye-off");
    document.querySelector(".eyes-icon1").classList.remove("ti-eye");
  }
})

