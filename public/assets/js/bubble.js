//  **------Bubble_chart 1**
function generateData(baseval, count, yrange) {
    var i = 0;
    var series = [];
    while (i < count) {
      var y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
      var z = Math.floor(Math.random() * (75 - 15 + 1)) + 15;
  
      series.push([baseval, y, z]);
      baseval += 86400000;
      i++;
    }
    return series;
  }
var options = {
    series: [{
    name: 'Bubble1',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: 'Bubble2',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: 'Bubble3',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: 'Bubble4',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  }],
    chart: {
      height: 350,
      type: 'bubble',
  },
  dataLabels: {
      enabled: false
  },
  fill: {
      opacity: 0.8
  },
  title: {
      text: ''
  },
  xaxis: {
      tickAmount: 12,
      type: 'datetime',
  },

colors: [getLocalStorageItem('color-primary','#056464'),getLocalStorageItem('color-secondary','#74788D'),'#05b48d','#EA5659',],
  yaxis: {
      max: 70
  }
  };

  var chart = new ApexCharts(document.querySelector("#bubble1"), options);
  chart.render();


//  **------ Bubble_chart 2**


var options = {
    series: [{
    name: '3D Bubble 1',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: '3D Bubble 2',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: '3D Bubble 3',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  },
  {
    name: '3D Bubble 4',
    data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
      min: 10,
      max: 60
    })
  }],
    chart: {
    height: 350,
    type: 'bubble',
  },
  dataLabels: {
    enabled: false
  },
  fill: {
    type: 'gradient',
  },
  title: {
    text: ''
  },
  xaxis: {
    tickAmount: 12,
    type: 'datetime',
    labels: {
        rotate: 0,
    }
  },

colors: ['#74788D','#3C91F3','#ACB8C8','#231928'],
  yaxis: {
    max: 70
  },
  theme: {
    palette: 'palette2'
  }
  };

  var chart = new ApexCharts(document.querySelector("#bubble2"), options);
  chart.render();
