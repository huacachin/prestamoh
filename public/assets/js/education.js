// radialChart 1 Start
var options = {
  series: [80],
  chart: {
    height: 150,
    width: 100,
    type: 'radialBar'
  },
  plotOptions: {
    radialBar: {
      endAngle: 360,
      dataLabels: {
        name: {
          show: true,
        },
        value: {
          show: false,
        },
        hollow: {
          size: '70%',
          image: '../assets/images/slick/7.jpg',
          imageWidth: 64,
          imageHeight: 64,
          imageClipped: false
        }
      }
    }
  },
  colors: ['rgba(var(--primary),1)'],
  labels: [''],
  responsive: [{
    breakpoint: 1366,
    options: {
      chart: {
        height: 120,
        width: 70,
      },
    },
  }
    , {
    breakpoint: 360,
    options: {
      chart: {
        height: 115,
        width: 60,
      },
    },
  }],
};

var chart = new ApexCharts(document.querySelector("#card1-progress"), options);


// radialChart 1 End

// radialChart 2 Start
var options = {
  series: [80],
  chart: {
    height: 150,
    width: 100,
    type: 'radialBar'
  },
  plotOptions: {
    radialBar: {
      endAngle: 360,
      dataLabels: {
        name: {
          show: true,
        },
        value: {
          show: false,
        },
        hollow: {
          size: '70%',
          image: '../assets/images/slick/7.jpg',
          imageWidth: 64,
          imageHeight: 64,
          imageClipped: false
        }
      }
    }
  },
  colors: ['rgba(var(--secondary),1)'],
  labels: [''],
  responsive: [{
    breakpoint: 1366,
    options: {
      chart: {
        height: 120,
        width: 70,
      },
    },
  }, {
    breakpoint: 360,
    options: {
      chart: {
        height: 115,
        width: 60,
      },
    },
  }],
};

var chart = new ApexCharts(document.querySelector("#card2-progress"), options);

// radialChart 2 End

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
    height: 380,
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

// LearningChart End
var options = {
  series: [{
    name: 'session1',
    data: [44, 55, 41, 67, 22, 43, 21]
  }, {
    name: 'session2',
    data: [13, 23, 20, 8, 13, 27, 33]
  }, {
    name: 'session3',
    data: [11, 17, 15, 15, 21, 14, 15]
  }],
  chart: {
    type: 'bar',
    height: 405,
    stacked: true,
    stackType: '100%'
  },
  colors: ['rgba(var(--primary),1)', 'rgba(var(--secondary),1)', 'rgba(var(--danger),1)'],
  xaxis: {
    categories: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat',],
    show: true,
    labels: {
      show: true,
      style: {
        colors: hexToRGB(getLocalStorageItem('color-secondary', '#74788d'), 1),
        fontSize: '16px',
        fontWeight: 500,
      },
    }
  },
  yaxis: {
    show: false,
  },
  fill: {
    opacity: 1
  },
  legend: {
    position: 'bottom',
    fontSize: '14px',
    fontFamily: '"Poppins", sans-serif',
    labels: {
      colors: 'rgba(var(--secondary),1)',
      useSeriesColors: false
    },
    markers: {
      width: 15,
      height: 15,
      radius: 5,
      offsetX: -4,
    },
  },
  grid: {
    borderColor: 'rgba(var(--secondary),.4)',
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
    breakpoint: 576,
    options: {
      chart: {
        height: 360,
      },
      dataLabels: {
        enabled: false
      },
      xaxis: {
        labels: {
          rotate: -45,
          rotateAlways: true,
        },
      }
    }
  }],
};

var chart = new ApexCharts(document.querySelector("#scheduleChart"), options);
chart.render();
// ScheduleChart End


document.addEventListener('DOMContentLoaded', (event) => {
  const taskImages = document.querySelectorAll('.task-image');

  taskImages.forEach(taskImage => {
    taskImage.addEventListener('click', () => {
      const taskList = taskImage.closest('.task-list');
      const heading = taskList.querySelector('h6');
      heading.classList.toggle('text-decoration-line-through');
    });
  });
});