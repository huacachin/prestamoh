//  **------radialBar 1**

var options = {
    series: [70],
    chart: {
    height: 350,
    type: 'radialBar',
  },
  plotOptions: {
    radialBar: {
      hollow: {
        size: '70%',
      }
    },
  },
  labels: ['Cricket'],
  colors: [getLocalStorageItem('color-primary','#056464')],
  responsive: [{
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar1"), options);
  chart.render();

  //  **------radialBar 2**


var options = {
    series: [44, 55, 67, 83],
    chart: {
    height: 350,
    type: 'radialBar',
  },
  colors: [getLocalStorageItem('color-secondary','#74788D'),'#0FB450','#EA5659','#FAC10F'],
  plotOptions: {
    radialBar: {
      dataLabels: {
        name: {
          fontSize: '22px',
        },
        value: {
          fontSize: '16px',
        },
       
        total: {
          show: true,
          label: 'Total',
          formatter: function (w) {
            // By default this function returns the average of all series. The below is just an example to show the use of custom formatter function
            return 249
          }
        }
      }
    }
  },
  labels: ['Apples', 'Oranges', 'Bananas', 'Berries'],
  responsive: [{
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar2"), options);
  chart.render();

//  **------radialBar 3**


var options = {
    series: [76, 67, 61, 90],
    chart: {
    height: 390,
    type: 'radialBar',
  },
  plotOptions: {
    radialBar: {
      offsetY: 0,
      startAngle: 0,
      endAngle: 270,
      hollow: {
        margin: 5,
        size: '30%',
        background: 'transparent',
        image: undefined,
      },
      dataLabels: {
        name: {
          show: false,
        },
        value: {
          show: false,
        }
      }
    }
  },
  colors: [getLocalStorageItem('color-primary','#056464'), '#29b0f2',getLocalStorageItem('color-secondary','#74788D'), '#283055'],
  labels: ['Vimeo', 'Messenger', 'Facebook', 'LinkedIn'],
  legend: {
    show: true,
    floating: true,
    fontSize: '16px',
    position: 'left',
    offsetX: 120,
    offsetY: 15,
    labels: {
      useSeriesColors: true,
    },
    markers: {
      size: 0
    },
    formatter: function(seriesName, opts) {
      return seriesName + ":  " + opts.w.globals.series[opts.seriesIndex]
    },
    itemMargin: {
      vertical: 3
    }
  },
  responsive: [
    {
      breakpoint: 1550,
      options: {
        legend: {
          offsetX: 25,
          offsetY: 15,
        }
      }
    },
    {
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  },
  {
    breakpoint: 480,
    options: {
      legend: {
    fontSize: '14px',
    offsetX: -35,
    offsetY: -10,
      }
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar3"), options);
  chart.render();

//  **------radialBar 4**


var options = {
    series: [75],
    chart: {
    height: 390,
    type: 'radialBar',
    toolbar: {
      show: true
    }
  },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 225,
       hollow: {
        margin: 0,
        size: '70%',
        background: '#fff',
        image: undefined,
        imageOffsetX: 0,
        imageOffsetY: 0,
        position: 'front',
      },
      track: {
        background: '#fff',
        strokeWidth: '67%',
        margin: 0, // margin is in pixels
        dropShadow: {
          enabled: true,
          top: -3,
          left: 0,
          blur: 4,
          opacity: 0.35
        }
      },
  
      dataLabels: {
        show: true,
        name: {
          offsetY: -10,
          show: true,
          color: '#888',
          fontSize: '17px'
        },
        value: {
          formatter: function(val) {
            return parseInt(val);
          },
          color: '#111',
          fontSize: '36px',
          show: true,
        }
      }
    }
  },
  fill: {
    // type: 'gradient',
    colors: ['#ea5759'],
  },
  stroke: {
    lineCap: 'round'
  },
  labels: ['Percent'],
  responsive: [{
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar4"), options);
  chart.render();

//  **------radialBar 5**


var options = {
    series: [67],
    chart: {
    height: 350,
    type: 'radialBar',
    offsetY: -10
  },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      dataLabels: {
        name: {
          fontSize: '16px',
          color: undefined,
          offsetY: 120
        },
        value: {
          offsetY: 76,
          fontSize: '22px',
          color: undefined,
          formatter: function (val) {
            return val + "%";
          }
        }
      }
    }
  },
  fill: {
    colors: ['#283055'],
  },
  stroke: {
    dashArray: 4
  },
  labels: ['Median Ratio'],
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar5"), options);
  chart.render();

//  **------radialBar 6**


var options = {
    series: [76],
    chart: {
    height: 640,
    type: 'radialBar',
    offsetY: -20,
    sparkline: {
      enabled: true
    }
  },
  plotOptions: {
    radialBar: {
      startAngle: -90,
      endAngle: 90,
      track: {
        background: "#e7e7e7",
        strokeWidth: '97%',
        margin: 5, // margin is in pixels
      },
      dataLabels: {
        name: {
          show: false
        },
        value: {
          offsetY: -2,
          fontSize: '22px'
        }
      }
    }
  },
  grid: {
    padding: {
      top: -10
    }
  },
  fill: {
    // type: 'gradient',
    colors: [getLocalStorageItem('color-primary','#056464')],
  },
  labels: ['Average Results'],
  responsive: [{
    breakpoint: 1366,
    options: {
      chart: {
        height:500,
      },
    }
  },{
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar6"), options);
  chart.render();

// **------ radialBar 7**


var options = {
    series: [67],
    chart: {
    height: 350,
    type: 'radialBar',
  },
  plotOptions: {
    radialBar: {
      hollow: {
        margin: 15,
        size: '70%',
        image: '../../assets/images/icons/clock.png',
        imageWidth: 64,
        imageHeight: 64,
        imageClipped: false
      },
      dataLabels: {
        name: {
          show: false,
          
        },
        value: {
          show: true,
          color: '#333',
          offsetY: 70,
          fontSize: '22px'
        }
      }
    }
  },
  fill: {
    type: 'image',
    image: {
      src: ['../../assets/images/slick/11.jpg'],
    }
  },
  stroke: {
    lineCap: 'round'
  },
  labels: ['Volatility'],
  responsive: [{
    breakpoint: 567,
    options: {
      chart: {
        height:250,
      },
    }
  }]
  };

  var chart = new ApexCharts(document.querySelector("#radlaibar7"), options);
  chart.render();


//  **------radialBar 2**

var options = {
  series: [0],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
labels: ['0%'], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress2"), options);
chart.render();
//  **------radial-progress3**

var options = {
  series: [21],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
labels: ['21%'], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress3"), options);
chart.render();

//  **------radial-progress 4**

var options = {
  series: [65],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
labels: ['65%'], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress4"), options);
chart.render();
//  **------radial-progress 5**

var options = {
  series: [87],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
labels: ['87%'], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress5"), options);
chart.render();
//  **------radial-progress 6**

var options = {
  series: [100],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
labels: ['100%'], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress6"), options);
chart.render();


// **------ radial-progress 13**

var options = {
  series: [5],
  chart: {
  height:150,
  width:100,
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
    }
  }
},
colors: [hexToRGB(getLocalStorageItem('color-primary','#056464'),1)],
labels: [''],
};

var chart = new ApexCharts(document.querySelector("#radial-progress13"), options);
chart.render();

//  **------radial-progress 14**

var options = {
  series: [25],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
colors: [hexToRGB(getLocalStorageItem('color-secondary','#74788D'),1)],
labels: [''], 
};

var chart = new ApexCharts(document.querySelector("#radial-progress14"), options);
chart.render();
// **------ radial-progress 15**

var options = {
  series: [57],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
colors: ['rgba(var(--success),1)'],
labels: [''],
 
};

var chart = new ApexCharts(document.querySelector("#radial-progress15"), options);
chart.render();
//  **------radial-progress 16**

var options = {
  series: [78],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
colors: ['rgba(var(--danger),1)'],
labels: [''],
 
};

var chart = new ApexCharts(document.querySelector("#radial-progress16"), options);
chart.render();
// **------ radial-progress 17**

var options = {
  series: [88],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
colors: ['rgba(var(--warning),1)'],
labels: [''],
 
};

var chart = new ApexCharts(document.querySelector("#radial-progress17"), options);
chart.render();

var options = {
  series: [88],
  chart: {
  height:150,
  width:100,
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
      }
      }
  }
},
colors: ['rgba(var(--info),1)'],
labels: [''],
 
};

var chart = new ApexCharts(document.querySelector("#radial-progress12"), options);
chart.render();


//  **------radial-progress 18**
var options = {
  chart: {
    height: 150,
    width: 110,
    type: "radialBar"
  },
  
  series: [5],
  
  plotOptions: {
    radialBar: {
      hollow: {
        margin: 15,
        size: "70%"
      },
     
      dataLabels: {
        showOn: "always",
        name: {
          offsetY: -10,
          show: false,
          color: hexToRGB(getLocalStorageItem('color-primary','#056464'),1),
          fontSize: "15px",
        },
        style: {
          fontSize: '14px',
      },
        value: {
          color:hexToRGB(getLocalStorageItem('color-primary','#056464'),1),
          fontSize: "30px",
          show: true
        }
      }
    }
  },

  stroke: {
    lineCap: "round",
  },
colors: [hexToRGB(getLocalStorageItem('color-primary','#74788D'),1)],
  labels: ["Primary"]
};

var chart = new ApexCharts(document.querySelector("#radial-progress18"), options);

chart.render();

       
//   **------ radial-progress 19**

  var options = {
    chart: {
      height: 172,
       width: 200,
      type: "radialBar"
    },
    
    series: [25],
    
    plotOptions: {
      radialBar: {
        hollow: {
          margin: 15,
          size: "70%"
        },
       
        dataLabels: {
          showOn: "always",
          name: {
            offsetY: -10,
            show: false,
            color: hexToRGB(getLocalStorageItem('color-secondary','#74788D'),1),
            fontSize: "13px"
          },
          value: {
            color: hexToRGB(getLocalStorageItem('color-secondary','#74788D'),1),
            fontSize: "30px",
            show: true
          }
        }
      }
    },
  
    stroke: {
      lineCap: "round",
    },
    colors: [hexToRGB(getLocalStorageItem('color-secondary','#74788D'),1)],
    labels: ["Secondary"]
  };
  
  var chart = new ApexCharts(document.querySelector("#radial-progress19"), options);
  
  chart.render();
//  **------radial-progress 20**
  
  var options = {
    chart: {
      height: 190,
       width: 200,
      type: "radialBar"
    },
    
    series: [57],
    
    plotOptions: {
      radialBar: {
        hollow: {
          margin: 15,
          size: "70%"
        },
       
        dataLabels: {
          showOn: "always",
          name: {
            offsetY: -10,
            show: false,
            color: "rgba(var(--success),1)",
            fontSize: "13px"
          },
          value: {
            color: "rgba(var(--success),1)",
            fontSize: "30px",
            show: true
          }
        }
      }
    },
  
    stroke: {
      lineCap: "round",
    },
    colors: ['rgba(var(--success),1)'],
    labels: ["Success"]
  };
  
  var chart = new ApexCharts(document.querySelector("#radial-progress20"), options);
  
  chart.render();
//  **------radial-progress 21**

  var options = {
    chart: {
      height: 210,
       width: 200,
      type: "radialBar"
    },
    
    series: [78],
    
    plotOptions: {
      radialBar: {
        hollow: {
          margin: 15,
          size: "65%"
        },
       
        dataLabels: {
          showOn: "always",
          name: {
            offsetY: -10,
            show: false,
            color: "rgba(var(--danger),1)",
            fontSize: "13px"
          },
          value: {
            color: "rgba(var(--danger),1)",
            fontSize: "30px",
            show: true
          }
        }
      }
    },
  
    stroke: {
      lineCap: "round",
    },
    colors: ['rgba(var(--danger),1)'],
    labels: ["Danger"]
  };
  
  var chart = new ApexCharts(document.querySelector("#radial-progress21"), options);
  
  chart.render();
//  **------radial-progress 22**
var options = {
  chart: {
    height: 230,
     width: 200,
    type: "radialBar"
  },
  
  series: [88],
  
  plotOptions: {
    radialBar: {
      hollow: {
        margin: 15,
        size: "60%"
      },
     
      dataLabels: {
        showOn: "always",
        name: {
          offsetY: -10,
          show: false,
          color: "rgba(var(--warning),1)",
          fontSize: "13px"
        },
        value: {
          color: "rgba(var(--warning),1)",
          fontSize: "30px",
          show: true
        }
      }
    }
  },

  stroke: {
    lineCap: "round",
  },
  colors: ['rgba(var(--warning),1)'],
  labels: ["Warning"]
};

var chart = new ApexCharts(document.querySelector("#radial-progress22"), options);

chart.render();


var options = {
  chart: {
    height: 250,
     width: 200,
    type: "radialBar"
  },
  
  series: [95],
  
  plotOptions: {
    radialBar: {
      hollow: {
        margin: 15,
        size: "55%"
      },
     
      dataLabels: {
        showOn: "always",
        name: {
          offsetY: -10,
          show: false,
          color: "rgba(var(--info),1)",
          fontSize: "13px"
        },
        value: {
          color: "rgba(var(--info),1)",
          fontSize: "30px",
          show: true
        }
      }
    }
  },

  stroke: {
    lineCap: "round",
  },
  colors: ['rgba(var(--info),1)'],
  labels: ["Info"]
};

var chart = new ApexCharts(document.querySelector("#radial-progress23"), options);

chart.render();


var options = {
  chart: {
    height: 280,
     width: 200,
    type: "radialBar"
  },
  
  series: [100],
  
  plotOptions: {
    radialBar: {
      hollow: {
        margin: 15,
        size: "55%"
      },
     
      dataLabels: {
        showOn: "always",
        name: {
          offsetY: -10,
          show: false,
          color: "rgba(var(--dark),1)",
          fontSize: "13px"
        },
        value: {
          color: "rgba(var(--dark),1)",
          fontSize: "30px",
          show: true
        }
      }
    }
  },

  stroke: {
    lineCap: "round",
  },
  colors: ['rgba(var(--dark),1)'],
  labels: ["dark"]
};

var chart = new ApexCharts(document.querySelector("#radial-progress24"), options);

chart.render();

