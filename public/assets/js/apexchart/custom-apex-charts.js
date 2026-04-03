/*=====================
    Custom Apex Chart 
  ==========================*/
/*=======/ Basic Area Chart /=======*/
var areachart = {
    series: [{
        name: 'series1',
        data: [310, 280, 350, 310, 570, 590, 650, 550],
    },],
    chart: {
        height: 300,
        width: '100%',
        type: 'area',
        offsetY: 0,
        toolbar: {
            show: false,
        },
    },
    dataLabels: {
        enabled: false,
    },
    colors: ['rgba(var(--primary),1'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.8,
            opacityTo: 0.4,
            colorStops: [{
                offset: 0,
                color: 'rgba(var(--primary),0.5)',
                opacity: 1,
            },
            {
                offset: 20,
                color: 'rgba(var(--primary),0.3)',
                opacity: 1,
            },
            {
                offset: 60,
                color: 'rgba(var(--primary),0.1)',
                opacity: 1,
            },
            {
                offset: 100,
                color: 'rgba(var(--primary), 0)',
                opacity: 1,
            },
            ],
        },
    },
    markers: {
        size: 0,
        strokeColors: 'rgba(var(--primary), 0.2)',
        strokeWidth: 30,
        discrete: [],
        shape: 'circle',
        radius: 2,
        offsetX: 0,
        offsetY: 0,
        onClick: undefined,
        onDblClick: undefined,
        showNullDataPoints: true,
        hover: {
            size: 10,
            strokeWidth: 20,
        },
    },
    stroke: {
        curve: 'smooth',
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        strokeDashArray: 0,
        position: 'back',
        padding: {
            top: -20,
            right: -13,
            bottom: 0,
            left: 12,
        },
    },
    yaxis: {
        logBase: 100,
        tickAmount: 6,
        min: 100,
        max: 700,
        labels: {
            show: true,
            align: 'right',
            minWidth: 0,
            maxWidth: 34,
            style: {
                ...fontCommon,
            },
            formatter: (value) => {
                return `${value}k`;
            },
        },
    },
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
        labels: {
            minHeight: undefined,
            maxHeight: 24,
            offsetX: -5,
            offsetY: 0,
            style: {
                ...fontCommon,
            },
            tooltip: {
                enabled: false,
            },
        },
    },
    tooltip: {
        custom: function ({
            series,
            seriesIndex,
            dataPointIndex,
            w
        }) {
            return '<div class="apex-tooltip">' + '<span>' + '<span class="bg-primary">' + '</span>' + 'Selling' + ': ' + series[seriesIndex][dataPointIndex] + 'K' + '</span>' + '</div>';
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var areachartEl = new ApexCharts(document.querySelector('#areachart'), areachart);
areachartEl.render();
/*=======/Column Chart/=======*/
var columnchart = {
    series: [{
        data: [45, 25, 55, 47, 89, 47, 70, 38, 92, 44, 38, 70],
    },],
    chart: {
        type: 'bar',
        width: '100%',
        height: 300,
        sparkline: {
            enabled: true,
        },
    },
    plotOptions: {
        bar: {
            columnWidth: '70%',
            borderRadius: 5,
        },
    },
    labels: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    xaxis: {
        crosshairs: {
            width: 1,
        },
    },
    colors: ['rgba(var(--secondary))'],
    fill: {
        colors: 'rgba(var(--secondary), 0.4)',
        type: 'solid',
    },
    tooltip: {
        fixed: {
            enabled: false,
        },
        x: {
            show: false,
        },
        y: {
            title: {
                formatter: function (seriesName) {
                    return '';
                },
            },
        },
        marker: {
            show: false,
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            plotOptions: {
                bar: {
                    columnWidth: '55%',
                    borderRadius: 5,
                },
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
            plotOptions: {
                bar: {
                    columnWidth: '70%',
                    borderRadius: 5,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
            plotOptions: {
                bar: {
                    columnWidth: '70%',
                    borderRadius: 5,
                },
            },
        },
    },
    ],
};
var columnchartEl = new ApexCharts(document.querySelector('#columnchart'), columnchart);
columnchartEl.render();
/*=======/ Advanced Area Chart /=======*/
var advancedareachart = {
    series: [{
        name: 'series1',
        data: [200, 570, 200, 300, 280, 530, 440, 440, 630, 400, 450, 560, 350, 200],
    },],
    chart: {
        height: 300,
        width: '100%',
        type: 'area',
        offsetY: 14,
        toolbar: {
            show: false,
        },
    },
    dataLabels: {
        enabled: false,
    },
    colors: ['rgba(var(--secondary),1'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.8,
            opacityTo: 0.4,
            colorStops: [{
                offset: 0,
                color: 'rgba(var(--secondary),0.5)',
                opacity: 1,
            },
            {
                offset: 20,
                color: 'rgba(var(--secondary),0.3)',
                opacity: 1,
            },
            {
                offset: 60,
                color: 'rgba(var(--secondary),0.1)',
                opacity: 1,
            },
            {
                offset: 100,
                color: 'rgba(var(--secondary), 0)',
                opacity: 1,
            },
            ],
        },
    },
    markers: {
        size: 7,
        colors: '#fff',
        strokeColors: 'rgba(var(--secondary), 1)',
        strokeWidth: 3,
        hover: {
            size: 9,
            strokeWidth: 20,
        },
    },
    stroke: {
        curve: 'straight',
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        strokeDashArray: 4,
        position: 'back',
        padding: {
            top: -22,
        },
        xaxis: {
            lines: {
                show: true,
            },
        },
        yaxis: {
            lines: {
                show: false,
            },
        },
    },
    yaxis: {
        show: false,
        logBase: 100,
        tickAmount: 6,
        min: 100,
        max: 700,
        labels: {
            show: false,
        },
    },
    xaxis: {
        categories: [' ', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'sep', 'Oct', 'Nov', 'Dec', ' '],
        labels: {
            minHeight: undefined,
            maxHeight: 24,
            offsetX: 0,
            offsetY: 0,
            style: {
                ...fontCommon,
            },
            tooltip: {
                enabled: false,
            },
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
    tooltip: {
        style: {
            ...fontCommon,
            fontSize: '12px',
        },
        onDatasetHover: {
            highlightDataSeries: false,
        },
        x: {
            show: false,
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
            markers: {
                size: 5,
                strokeWidth: 2,
                hover: {
                    size: 7,
                },
            },
            xaxis: {
                labels: {
                    show: true,
                    rotate: -45,
                    rotateAlways: true,
                    hideOverlappingLabels: true,
                    showDuplicates: false,
                    maxHeight: 30,
                    offsetX: 3,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var advancedareachartEl = new ApexCharts(document.querySelector('#advancedareachart'), advancedareachart);
advancedareachartEl.render();
/*=======/ Rounded Column chart /=======*/
var roundedcolumnchart = {
    series: [{
        data: [{
            x: 'Jan',
            y: [210, 400],
        },
        {
            x: 'Feb',
            y: [300, 490],
        },
        {
            x: 'Mar',
            y: [350, 500],
        },
        {
            x: 'Apr',
            y: [210, 390],
        },
        {
            x: 'May',
            y: [280, 400],
        },
        {
            x: 'Jun',
            y: [110, 250],
        },
        {
            x: 'Jul',
            y: [210, 400],
        },
        {
            x: 'Aug',
            y: [290, 390],
        },
        {
            x: 'Sep',
            y: [250, 490],
        },
        {
            x: 'Oct',
            y: [210, 390],
        },
        {
            x: 'Nov',
            y: [190, 310],
        },
        {
            x: 'Dec',
            y: [250, 450],
        },
        ],
    },],
    chart: {
        type: 'rangeBar',
        height: 300,
        offsetY: 13,
        toolbar: {
            show: false,
        },
    },
    legend: {
        show: false,
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        position: 'back',
        xaxis: {
            lines: {
                show: true,
            },
        },
        yaxis: {
            lines: {
                show: false,
            },
        },
    },
    tooltip: {
        enabled: false,
    },
    colors: ['rgba(var(--primary),1)'],
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '30%',
        },
    },
    dataLabels: {
        enabled: false,
    },
    yaxis: {
        logBase: 100,
        tickAmount: 4,
        min: 100,
        max: 500,
        labels: {
            show: true,
            align: 'right',
            minWidth: 0,
            maxWidth: 34,
            style: {
                ...fontCommon,
            },
            formatter: (value) => {
                return `${value}k`;
            },
        },
    },
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
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
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
            xaxis: {
                labels: {
                    maxHeight: 30,
                    offsetX: 0,
                    offsetY: 0,
                    rotate: -45,
                    rotateAlways: true,
                    style: {
                        fontSize: '14px',
                    },
                },
            },
            yaxis: {
                labels: {
                    show: true,
                    align: 'right',
                    minWidth: 0,
                    maxWidth: 34,
                    style: {
                        ...fontCommon,
                        fontSize: '14px',
                    },
                    formatter: (value) => {
                        return `${value}k`;
                    },
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
            xaxis: {
                labels: {
                    maxHeight: 34,
                    rotate: -70,
                },
            },
            yaxis: {
                labels: {
                    show: true,
                    align: 'right',
                    minWidth: 0,
                    maxWidth: 31,
                    style: {
                        ...fontCommon,
                        fontSize: '13px',
                    },
                    formatter: (value) => {
                        return `${value}k`;
                    },
                },
            },
        },
    },
    ],
};
var roundedcolumnchartEl = new ApexCharts(document.querySelector('#roundedcolumnchart'), roundedcolumnchart);
roundedcolumnchartEl.render();
/*=======/ Spline Area Chart /=======*/
var splineAreaChart1 = {
    series: [{
        name: 'series1',
        data: [0, 20, 70, 25, 100, 45, 25],
    },],
    colors: ['rgba(var(--secondary),1'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.8,
            opacityTo: 0.4,
            colorStops: [{
                offset: 0,
                color: 'rgba(var(--secondary),0.5)',
                opacity: 1,
            },
            {
                offset: 20,
                color: 'rgba(var(--secondary),0.3)',
                opacity: 1,
            },
            {
                offset: 60,
                color: 'rgba(var(--secondary),0.1)',
                opacity: 1,
            },
            {
                offset: 100,
                color: 'rgba(var(--secondary), 0)',
                opacity: 1,
            },
            ],
        },
    },
    markers: {
        size: 5,
        colors: '#fff',
        strokeColors: 'rgba(var(--secondary), 1)',
        strokeWidth: 2,
    },
};
var splineAreaChart2 = {
    series: [{
        name: 'series1',
        data: [0, 50, 40, 90, 60, 120, 150],
    },],
    colors: ['rgba(var(--primary),1'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.8,
            opacityTo: 0.4,
            colorStops: [{
                offset: 0,
                color: 'rgba(var(--primary),0.5)',
                opacity: 1,
            },
            {
                offset: 20,
                color: 'rgba(var(--primary),0.3)',
                opacity: 1,
            },
            {
                offset: 60,
                color: 'rgba(var(--primary),0.1)',
                opacity: 1,
            },
            {
                offset: 100,
                color: 'rgba(var(--primary), 0)',
                opacity: 1,
            },
            ],
        },
    },
    markers: {
        size: 5,
        colors: '#fff',
        strokeColors: 'rgba(var(--primary), 1)',
        strokeWidth: 2,
    },
};
var recentStatisticsOption = (data) => {
    return {
        series: data.series,
        chart: {
            height: 300,
            width: '100%',
            type: 'area',
            stacked: true,
            offsetY: 18,
            toolbar: {
                show: false,
            },
        },
        dataLabels: {
            enabled: false,
        },
        colors: data.colors,
        fill: data.fill,
        markers: data.markers,
        stroke: {
            curve: 'straight',
            width: 2,
        },
        grid: {
            show: true,
            borderColor: 'var(--border-light)',
            strokeDashArray: 0,
            position: 'back',
            padding: {
                top: 0,
                right: -2,
                left: 15,
            },
            xaxis: {
                lines: {
                    show: true,
                },
            },
            yaxis: {
                lines: {
                    show: false,
                },
            },
        },
        yaxis: {
            show: false,
            logBase: 100,
            tickAmount: 6,
            min: 0,
            max: 150,
            labels: {
                show: false,
            },
        },
        xaxis: {
            categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
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
        tooltip: {
            style: {
                ...fontCommon,
                fontSize: '12px',
            },
            onDatasetHover: {
                highlightDataSeries: false,
            },
            x: {
                show: false,
            },
        },
        responsive: [{
            breakpoint: 675,
            options: {
                chart: {
                    height: 280,
                },
            },
        },
        {
            breakpoint: 425,
            options: {
                chart: {
                    height: 260,
                },
            },
        },
        {
            breakpoint: 376,
            options: {
                chart: {
                    height: 220,
                },
            },
        },
        ],
    };
};
var splineAreaChartEl = new ApexCharts(document.querySelector('#splineAreaChart1'), recentStatisticsOption(splineAreaChart1));
var splineAreaChartE2 = new ApexCharts(document.querySelector('#splineAreaChart2'), recentStatisticsOption(splineAreaChart2));
splineAreaChartEl.render();
splineAreaChartE2.render();
/*=======/ Line Chart /=======*/
var linechart = {
    series: [{
        name: 'series1',
        data: [42.5, 40, 36, 32, 28, 25, 22, 20, 18, 16.5, 15.5, 15, 15, 16, 18, 20, 23, 26, 30, 34, 38, 41.5, 45, 48, 51, 54, 56, 58, 59, 59, 58, 56.5, 54.5, 51.5, 48, 44],
    },
    {
        name: 'series2',
        data: [57, 58, 58, 57.5, 56.5, 55, 52.5, 50, 47, 43.5, 40, 36, 32, 28, 24, 20, 17, 15, 14.0, 14, 15, 17, 20, 24, 28, 32, 36, 40, 44, 48, 51.5, 54.5, 56.6, 58, 58.5, 58],
    },
    ],
    chart: {
        height: 300,
        width: '100%',
        type: 'line',
        offsetY: 12,
        toolbar: {
            show: false,
        },
    },
    dataLabels: {
        enabled: false,
    },
    colors: ['rgba(var(--secondary),1', 'rgba(var(--primary),1'],
    markers: {
        size: 5,
        strokeColors: '#fff',
        strokeWidth: 2,
        discrete: [],
        shape: 'circle',
        radius: 2,
        offsetX: 0,
        offsetY: 0,
        onClick: undefined,
        onDblClick: undefined,
        showNullDataPoints: true,
        hover: {
            size: 10,
            strokeWidth: 20,
        },
    },
    stroke: {
        curve: 'straight',
        lineCap: 'butt',
        width: 3,
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        strokeDashArray: 0,
        position: 'back',
        padding: {
            top: -20,
            right: 10,
            bottom: 0,
            left: 12,
        },
    },
    yaxis: {
        logBase: 100,
        tickAmount: 6,
        min: 0,
        max: 60,
        labels: {
            show: true,
            align: 'right',
            minWidth: 0,
            maxWidth: 25,
            style: {
                ...fontCommon,
            },
            formatter: (value) => {
                return `${value}k`;
            },
        },
    },
    xaxis: {
        type: 'category',
        categories: [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ],
        tickAmount: 12,
        labels: {
            minHeight: undefined,
            maxHeight: 28,
            offsetX: 10,
            offsetY: 0,
            style: {
                ...fontCommon,
            },
            tooltip: {
                enabled: false,
            },
        },
    },
    tooltip: {
        custom: function ({
            series,
            seriesIndex,
            dataPointIndex,
            w
        }) {
            return `<div class="apex-tooltip"> 
                <span>
                     <span class="bg-secondary"> </span>
                      Selling : ${series[0][dataPointIndex]} K
                </span> 
                <span class="mt-2">
                     <span class="bg-primary"> </span>
                      Selling : ${series[1][dataPointIndex]} K
                </span> 
              </div>`;
        },
    },
    legend: {
        show: false,
    },
    responsive: [
    {
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 240,
            },
            markers: {
                size: 4,
                strokeWidth: 1,
                radius: 1,
                hover: {
                    size: 5,
                    strokeWidth: 1,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
            markers: {
                size: 3,
                strokeWidth: 1,
                radius: 1,
                hover: {
                    size: 4,
                    strokeWidth: 1,
                },
            },
        },
    },
    ],
};
var linechartEl = new ApexCharts(document.querySelector('#linechart'), linechart);
linechartEl.render();
/*=======/ Bubble Chart /=======*/
function generateData(baseval, count, yrange) {
    var i = 0;
    var series = [];
    while (i < count) {
        //var x =Math.floor(Math.random() * (750 - 1 + 1)) + 1;;
        var y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
        var z = Math.floor(Math.random() * (75 - 15 + 1)) + 15;
        series.push([baseval, y, z]);
        baseval += 86400000;
        i++;
    }
    return series;
}
var bubblechart = {
    chart: {
        height: 300,
        type: 'bubble',
        toolbar: {
            show: false,
        },
    },
    dataLabels: {
        enabled: false,
    },
    series: [{
        name: 'Product1',
        data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
            min: 10,
            max: 60,
        }),
    },
    {
        name: 'Product2',
        data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
            min: 10,
            max: 60,
        }),
    },
    {
        name: 'Product3',
        data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
            min: 10,
            max: 60,
        }),
    },
    {
        name: 'Product4',
        data: generateData(new Date('11 Feb 2017 GMT').getTime(), 20, {
            min: 10,
            max: 60,
        }),
    },
    ],
    fill: {
        type: 'gradient',
    },
    xaxis: {
        tickAmount: 12,
        type: 'datetime',
        labels: {
            rotate: 0,
            style: {
                ...fontCommon,
            },
        },
    },
    grid: {
        borderColor: 'var(--border-light)',
    },
    yaxis: {
        max: 70,
        labels: {
            style: {
                ...fontCommon,
            },
        },
    },
    theme: {
        palette: 'palette2',
    },
    stroke: {
        width: 0,
    },
    colors: ['#008193', '#EAB01E', '#615184', '#DE3F44'],
    legend: {
        labels: {
            style: {
                ...fontCommon,
            },
            useSeriesColors: false,
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 250,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var bubblechartEl = new ApexCharts(document.querySelector('#bubblechart'), bubblechart);
bubblechartEl.render();
/*=======/ CandleStick Chart /=======*/
var candlestickchart = {
    chart: {
        height: 300,
        type: 'candlestick',
        toolbar: {
            show: false,
        },
    },
    plotOptions: {
        candlestick: {
            colors: {
                upward: 'rgba(var(--primary),1',
                downward: 'rgba(var(--secondary),1',
            },
        },
    },
    series: [{
        data: [{
            x: new Date(1538778600000),
            y: [6629.81, 6650.5, 6623.04, 6633.33],
        },
        {
            x: new Date(1538780400000),
            y: [6632.01, 6643.59, 6620, 6630.11],
        },
        {
            x: new Date(1538782200000),
            y: [6630.71, 6648.95, 6623.34, 6635.65],
        },
        {
            x: new Date(1538784000000),
            y: [6635.65, 6651, 6629.67, 6638.24],
        },
        {
            x: new Date(1538785800000),
            y: [6638.24, 6640, 6620, 6624.47],
        },
        {
            x: new Date(1538787600000),
            y: [6624.53, 6636.03, 6621.68, 6624.31],
        },
        {
            x: new Date(1538789400000),
            y: [6624.61, 6632.2, 6617, 6626.02],
        },
        {
            x: new Date(1538791200000),
            y: [6627, 6627.62, 6584.22, 6603.02],
        },
        {
            x: new Date(1538793000000),
            y: [6605, 6608.03, 6598.95, 6604.01],
        },
        {
            x: new Date(1538794800000),
            y: [6604.5, 6614.4, 6602.26, 6608.02],
        },
        {
            x: new Date(1538796600000),
            y: [6608.02, 6610.68, 6601.99, 6608.91],
        },
        {
            x: new Date(1538798400000),
            y: [6608.91, 6618.99, 6608.01, 6612],
        },
        {
            x: new Date(1538800200000),
            y: [6612, 6615.13, 6605.09, 6612],
        },
        {
            x: new Date(1538802000000),
            y: [6612, 6624.12, 6608.43, 6622.95],
        },
        {
            x: new Date(1538803800000),
            y: [6623.91, 6623.91, 6615, 6615.67],
        },
        {
            x: new Date(1538805600000),
            y: [6618.69, 6618.74, 6610, 6610.4],
        },
        {
            x: new Date(1538807400000),
            y: [6611, 6622.78, 6610.4, 6614.9],
        },
        {
            x: new Date(1538809200000),
            y: [6614.9, 6626.2, 6613.33, 6623.45],
        },
        {
            x: new Date(1538811000000),
            y: [6623.48, 6627, 6618.38, 6620.35],
        },
        {
            x: new Date(1538812800000),
            y: [6619.43, 6620.35, 6610.05, 6615.53],
        },
        {
            x: new Date(1538814600000),
            y: [6615.53, 6617.93, 6610, 6615.19],
        },
        {
            x: new Date(1538816400000),
            y: [6615.19, 6621.6, 6608.2, 6620],
        },
        {
            x: new Date(1538818200000),
            y: [6619.54, 6625.17, 6614.15, 6620],
        },
        {
            x: new Date(1538820000000),
            y: [6620.33, 6634.15, 6617.24, 6624.61],
        },
        {
            x: new Date(1538821800000),
            y: [6625.95, 6626, 6611.66, 6617.58],
        },
        {
            x: new Date(1538823600000),
            y: [6619, 6625.97, 6595.27, 6598.86],
        },
        {
            x: new Date(1538825400000),
            y: [6598.86, 6598.88, 6570, 6587.16],
        },
        {
            x: new Date(1538827200000),
            y: [6588.86, 6600, 6580, 6593.4],
        },
        {
            x: new Date(1538829000000),
            y: [6593.99, 6598.89, 6585, 6587.81],
        },
        {
            x: new Date(1538830800000),
            y: [6587.81, 6592.73, 6567.14, 6578],
        },
        {
            x: new Date(1538832600000),
            y: [6578.35, 6581.72, 6567.39, 6579],
        },
        {
            x: new Date(1538834400000),
            y: [6579.38, 6580.92, 6566.77, 6575.96],
        },
        {
            x: new Date(1538836200000),
            y: [6575.96, 6589, 6571.77, 6588.92],
        },
        {
            x: new Date(1538838000000),
            y: [6588.92, 6594, 6577.55, 6589.22],
        },
        {
            x: new Date(1538839800000),
            y: [6589.3, 6598.89, 6589.1, 6596.08],
        },
        {
            x: new Date(1538841600000),
            y: [6597.5, 6600, 6588.39, 6596.25],
        },
        {
            x: new Date(1538843400000),
            y: [6598.03, 6600, 6588.73, 6595.97],
        },
        {
            x: new Date(1538845200000),
            y: [6595.97, 6602.01, 6588.17, 6602],
        },
        {
            x: new Date(1538847000000),
            y: [6602, 6607, 6596.51, 6599.95],
        },
        {
            x: new Date(1538848800000),
            y: [6600.63, 6601.21, 6590.39, 6591.02],
        },
        {
            x: new Date(1538850600000),
            y: [6591.02, 6603.08, 6591, 6591],
        },
        {
            x: new Date(1538852400000),
            y: [6591, 6601.32, 6585, 6592],
        },
        {
            x: new Date(1538854200000),
            y: [6593.13, 6596.01, 6590, 6593.34],
        },
        {
            x: new Date(1538856000000),
            y: [6593.34, 6604.76, 6582.63, 6593.86],
        },
        {
            x: new Date(1538857800000),
            y: [6593.86, 6604.28, 6586.57, 6600.01],
        },
        {
            x: new Date(1538859600000),
            y: [6601.81, 6603.21, 6592.78, 6596.25],
        },
        {
            x: new Date(1538861400000),
            y: [6596.25, 6604.2, 6590, 6602.99],
        },
        {
            x: new Date(1538863200000),
            y: [6602.99, 6606, 6584.99, 6587.81],
        },
        {
            x: new Date(1538865000000),
            y: [6587.81, 6595, 6583.27, 6591.96],
        },
        {
            x: new Date(1538866800000),
            y: [6591.97, 6596.07, 6585, 6588.39],
        },
        {
            x: new Date(1538868600000),
            y: [6587.6, 6598.21, 6587.6, 6594.27],
        },
        {
            x: new Date(1538870400000),
            y: [6596.44, 6601, 6590, 6596.55],
        },
        {
            x: new Date(1538872200000),
            y: [6598.91, 6605, 6596.61, 6600.02],
        },
        {
            x: new Date(1538874000000),
            y: [6600.55, 6605, 6589.14, 6593.01],
        },
        {
            x: new Date(1538875800000),
            y: [6593.15, 6605, 6592, 6603.06],
        },
        {
            x: new Date(1538877600000),
            y: [6603.07, 6604.5, 6599.09, 6603.89],
        },
        {
            x: new Date(1538879400000),
            y: [6604.44, 6604.44, 6600, 6603.5],
        },
        {
            x: new Date(1538881200000),
            y: [6603.5, 6603.99, 6597.5, 6603.86],
        },
        {
            x: new Date(1538883000000),
            y: [6603.85, 6605, 6600, 6604.07],
        },
        {
            x: new Date(1538884800000),
            y: [6604.98, 6606, 6604.07, 6606],
        },
        ],
    },],
    xaxis: {
        type: 'datetime',
        labels: {
            style: {
                ...fontCommon,
                fontSize: '12px',
            },
        },
    },
    yaxis: {
        tooltip: {
            enabled: true,
        },
        labels: {
            style: {
                ...fontCommon,
            },
        },
    },
    grid: {
        borderColor: 'var(--border-light)',
    },
    colors: ['#000000'],
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var candlestickchartEl = new ApexCharts(document.querySelector('#candlestickchart'), candlestickchart);
candlestickchartEl.render();
/*=======/ Group Bar Chart /=======*/
var groupbarchart = {
    series: [{
        name: 'Good',
        data: [170, 250, 350, 150, 230, 120, 330, 350, 280, 300, 250, 110],
    },
    {
        name: 'Very Good',
        data: [290, 180, 120, 290, 370, 250, 230, 200, 140, 220, 220, 330],
    },
    ],
    colors: ['rgba(var(--primary),1)', 'rgba(var(--secondary),1)'],
    chart: {
        type: 'bar',
        height: 300,
        width: '100%',
        offsetY: 10,
        offsetX: 0,
        toolbar: {
            show: false,
        },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            dataLabels: {
                position: 'top',
            },
        },
    },
    grid: {
        show: false,
        padding: {
            left: 8,
            right: 0,
        },
    },
    dataLabels: {
        enabled: false,
    },
    plotOptions: {
        bar: {
            horizontal: false,
            borderRadius: 8,
            columnWidth: '45%',
            barHeight: '100%',
            s̶t̶a̶r̶t̶i̶n̶g̶S̶h̶a̶p̶e̶: 'rounded',
            e̶n̶d̶i̶n̶g̶S̶h̶a̶p̶e̶: 'rounded',
        },
    },
    stroke: {
        show: true,
        width: 1,
        colors: ['#fff'],
    },
    tooltip: {
        shared: true,
        intersect: false,
        x: {
            show: true,
            format: 'dd MMM',
            formatter: undefined,
        },
        y: {
            show: false,
        },
    },
    yaxis: {
        show: true,
        min: 0,
        max: 400,
        logBase: 100,
        tickAmount: 4,
        labels: {
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        },
    },
    xaxis: {
        show: true,
        labels: {
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        },
        axisBorder: {
            show: true,
        },
        axisTicks: {
            show: true,
        },
        categories: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
    },
    legend: {
        show: false,
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
            plotOptions: {
                bar: {
                    borderRadius: 8,
                    columnWidth: '55%',
                },
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
            plotOptions: {
                bar: {
                    borderRadius: 5,
                    columnWidth: '65%',
                },
            },
        },
    },
    ],
};
var groupbarchartEl = new ApexCharts(document.querySelector('#groupbarchart'), groupbarchart);
groupbarchartEl.render();
/*=======/Radial Chart/=======*/
var radialchart = {
    chart: {
        height: 300,
        type: 'radialBar',
    },
    plotOptions: {
        radialBar: {
            dataLabels: {
                name: {
                    ...fontCommon,
                },
                value: {
                    ...fontCommon,
                },
                total: {
                    show: true,
                    label: 'Total',
                    color: 'var(--content)',
                    formatter: function (w) {
                        return 249;
                    },
                },
            },
        },
    },
    series: [44, 55, 67, 83],
    labels: ['Apples', 'Oranges', 'Bananas', 'Berries'],
    colors: ['rgba(var(--primary),1)', 'rgba(var(--secondary), 1)', 'var(--success)', 'var(--warning)'],
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
            plotOptions: {
                radar: {
                    size: 50,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var radialchartEl = new ApexCharts(document.querySelector('#radialchart'), radialchart);
radialchartEl.render();
/*=======/BoxPlot Chart/=======*/
var boxplotchart = {
    series: [{
        name: 'box',
        type: 'boxPlot',
        data: [{
            x: 'Jan 2015',
            y: [23, 30, 33, 36, 43],
        },
        {
            x: 'Jan 2016',
            y: [13, 24, 29, 33, 37],
        },
        {
            x: 'Jan 2017',
            y: [28, 33, 37, 42, 46],
        },
        {
            x: 'Jan 2018',
            y: [12, 15, 20, 25, 30],
        },
        {
            x: 'Jan 2019',
            y: [24, 32, 38, 42, 48],
        },
        {
            x: 'Jan 2020',
            y: [13, 17, 22, 25, 31],
        },
        {
            x: 'Jan 2021',
            y: [35, 37, 40, 42, 45],
        },
        {
            x: 'Jan 2023',
            y: [19, 24, 28, 34, 38],
        },
        ],
    },],
    chart: {
        type: 'boxPlot',
        height: 300,
        width: '100%',
        offsetY: 0,
        offsetX: 0,
        toolbar: {
            show: false,
        },
    },
    stroke: {
        colors: ['rgba(var(--primary), 1)'],
    },
    plotOptions: {
        boxPlot: {
            colors: {
                upper: 'rgba(var(--primary), 1)',
                lower: 'rgba(var(--secondary), 1)'
            },
        },
        bar: {
            columnWidth: '50%',
        },
    },
    tooltip: {
        custom: function ({
            series,
            seriesIndex,
            dataPointIndex,
            w
        }) {
            var dataY = series[0][dataPointIndex];
            var dataX = w.globals.initialSeries[seriesIndex].data[dataPointIndex].x;
            return '<ul class="boxChartTooltip">' + '<li><b>Price</b>: ' + dataY + '</li>' + '<li><b>Date</b>: ' + dataX + '</li>' + '</ul>';
        },
    },
    grid: {
        borderColor: 'var(--border-light)',
        padding: {
            top: -20,
            right: -20,
            bottom: -10,
        },
    },
    xaxis: {
        labels: {
            style: {
                ...fontCommon,
            },
            minHeight: 0,
            maxHeight: 30,
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
        logBase: 10,
        tickAmount: 4,
        min: 10,
        max: 50,
        labels: {
            style: {
                ...fontCommon,
                fontSize: 12,
            },
            minWidth: 0,
            maxWidth: 22,
            formatter: (value) => {
                return `${value}K`;
            },
        },
    },
    responsive: [{
        breakpoint: 1400,
        options: {
            chart: {
                offsetY: 0,
                offsetX: 0,
            },
            grid: {
                padding: {
                    top: -20,
                    right: 0,
                    left: 0,
                    bottom: -10,
                },
            },
            xaxis: {
                labels: {
                    show: true,
                    rotate: -30,
                    rotateAlways: true,
                    minHeight: 48,
                },
            },
        },
    },
    {
        breakpoint: 768,
        options: {
            chart: {
                offsetY: 0,
                offsetX: 0,
            },
            grid: {
                padding: {
                    top: -20,
                    right: 0,
                    left: 0,
                    bottom: -10,
                },
            },
            xaxis: {
                labels: {
                    show: true,
                    rotate: 0,
                    rotateAlways: false,
                    minHeight: 48,
                },
            },
        },
    },
    {
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 174,
            },
            xaxis: {
                labels: {
                    show: true,
                    rotate: -25,
                    rotateAlways: true,
                    minHeight: 45,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var boxplotchartEl = document.querySelector('#boxplotchart');
if (boxplotchartEl) {
    var BoxPlotChart = new ApexCharts(boxplotchartEl, boxplotchart);
    BoxPlotChart.render();
}
/*=======/Polar Area Chart/=======*/
var polarareachart = {
    series: [20, 28, 21, 17, 15, 18, 24],
    chart: {
        type: 'polarArea',
        height: 300,
    },
    stroke: {
        colors: ['#fff']
    },
    colors: ['#33BFBF', '#FF6150', '#b52af6', '#63d5be', '#feb858', '#f1523d', '#d8ecff'],
    fill: {
        opacity: 0.8
    },
    legend: {
        labels: {
            colors: 'var(--title)',
        },
    },
    responsive: [
        {
            breakpoint: 1880,
            options: {
                chart: {
                    height: 300,
                },
            }
        },
        {
            breakpoint: 675,
            options: {
                chart: {
                    height: 280,
                },
            }
        },
        {
            breakpoint: 480,
            options: {
                chart: {
                    height: 260,
                },
                legend: {
                    position: 'bottom'
                }
            }
        },
        {
            breakpoint: 1880,
            options: {
                chart: {
                    height: 220,
                },
            }
        },
    ],
};
var polarareachartEl = new ApexCharts(document.querySelector("#polarareachart"), polarareachart);
polarareachartEl.render();
/*=======/ Bar Chart /=======*/
var barchart = {
    series: [{
        data: [70, 30, 40, 90, 60, 50],
    },],
    chart: {
        type: 'bar',
        height: 300,
        width: '100%',
        toolbar: {
            show: false,
        },
    },
    colors: ['rgba(var(--primary),0.4)', 'rgba(var(--secondary),0.4)', '#072448', '#feb858'],
    fill: {
        opacity: 0.4,
    },
    plotOptions: {
        bar: {
            borderRadius: 0,
            horizontal: true,
            distributed: true,
            barHeight: '30%',
            dataLabels: {
                position: 'top',
            },
        },
    },
    dataLabels: {
        enabled: true,
        formatter: function (val) {
            return val;
        },
        background: {
            enabled: true,
            foreColor: '#fff',
            borderRadius: 5,
            padding: 4,
            opacity: 0.9,
            borderWidth: 1,
            borderColor: '#fff',
        },
        offsetX: 21,
        offsetY: -6,
        style: {
            fontSize: '12px',
            colors: ['#304758'],
        },
    },
    legend: {
        show: false,
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        strokeDashArray: 0,
        position: 'back',
        padding: {
            left: 50,
        },
        xaxis: {
            lines: {
                show: true,
            },
        },
        yaxis: {
            lines: {
                show: false,
            },
        },
    },
    yaxis: {
        labels: {
            show: false,
        },
    },
    xaxis: {
        categories: ['South Korea', 'Canada', 'United Kingdom', 'Netherlands', 'Italy', 'France'],
        logBase: 100,
        tickAmount: 10,
        min: 0,
        max: 100,
        labels: {
            minHeight: undefined,
            maxHeight: 18,
            offsetX: -5,
            offsetY: 0,
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
            tooltip: {
                enabled: false,
            },
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
                offsetY: 15,
            },
            xaxis: {
                title: {
                    offsetY: 0,
                },
            },
            grid: {
                padding: {
                    left: -13,
                    bottom: 25,
                },
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var barchartEl = new ApexCharts(document.querySelector('#barchart'), barchart);
barchartEl.render();
window.onload = function () {
    if (screen.width <= 675) {
        barchartEl.updateOptions({
            grid: {
                padding: {
                    left: -13,
                    bottom: 25,
                },
            },
        });
    }
};
/*=======/ Radal Chart /=======*/
var radalchart = {
    chart: {
        height: 300,
        type: 'radar',
        toolbar: {
            show: false,
        },
    },
    series: [{
        name: 'Series 1',
        data: [20, 100, 40, 30, 50, 80, 33],
    },],
    labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    plotOptions: {
        radar: {
            size: 140,
            polygons: {
                strokeColor: '#e9e9e9',
                fill: {
                    colors: ['#f8f8f8', '#fff'],
                },
            },
        },
    },
    colors: ['#FF6150'],
    markers: {
        size: 4,
        colors: ['#fff'],
        strokeColor: '#FF6150',
        strokeWidth: 2,
    },
    tooltip: {
        y: {
            formatter: function (val) {
                return val;
            },
        },
    },
    grid: {
        borderColor: 'var(--light)',
    },
    yaxis: {
        tickAmount: 7,
        labels: {
            formatter: function (val, i) {
                if (i % 2 === 0) {
                    return val;
                } else {
                    return '';
                }
            },
            style: {
                ...fontCommon
            },
        },
    },
    xaxis: {
        tickAmount: 7,
        labels: {
            style: {
                ...fontCommon
            },
        },
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
            plotOptions: {
                radar: {
                    size: 100,
                },
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
            plotOptions: {
                radar: {
                    size: 70,
                },
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
            plotOptions: {
                radar: {
                    size: 50,
                },
            },
        },
    },
    ],
};
var radalchartEl = new ApexCharts(document.querySelector('#radalchart'), radalchart);
radalchartEl.render();
/*=======/ Line Data Label Chart /=======*/
var linedatalabelchart = {
    series: [{
        name: "High - 2013",
        data: [28, 29, 33, 36, 32, 32, 33]
    },
    {
        name: "Low - 2013",
        data: [12, 11, 14, 18, 17, 13, 13]
    }
    ],
    chart: {
        height: 300,
        type: 'line',
        dropShadow: {
            enabled: true,
            color: '#000',
            top: 18,
            left: 7,
            blur: 10,
            opacity: 0.2
        },
        toolbar: {
            show: false
        }
    },
    colors: ['#33BFBF', '#FF6150'],
    dataLabels: {
        enabled: true,
    },
    stroke: {
        curve: 'smooth'
    },
    grid: {
        borderColor: 'var(--border-light)',
        padding: {
            left: 30,
        },
        row: {
            colors: ['#f5f6f9', 'transparent'],
            opacity: 0.5
        },
    },
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        labels: {
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        }
    },
    yaxis: {
        min: 5,
        max: 40,
        labels: {
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        floating: true,
        offsetY: -25,
        offsetX: -5
    },
    responsive: [{
        breakpoint: 675,
        options: {
            chart: {
                height: 280,
            },
        },
    },
    {
        breakpoint: 425,
        options: {
            chart: {
                height: 260,
            },
        },
    },
    {
        breakpoint: 375,
        options: {
            chart: {
                height: 220,
            },
        },
    },
    ],
};
var linedatalabelchartEl = new ApexCharts(document.querySelector("#linedatalabelchart"), linedatalabelchart);
linedatalabelchartEl.render();
/*=======/ Bar with Images /=======*/
var options = {
    series: [{
        name: 'coins',
        data: [2, 4, 3, 4, 3, 5, 5, 6.5, 6, 5, 4, 5, 8, 7, 7, 8, 8, 10, 9, 9, 12, 12,
            11, 12, 13, 14, 16, 14, 15, 17, 19, 21
        ]
    }],
    chart: {
        type: 'bar',
        height: 310,
        animations: {
            enabled: true
        },
        toolbar: {
            show: false,
        },
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '100%',
        },
    },
    legend: {
        show: false,
        labels: {
            colors: 'var(--title)',
        },
    },
    dataLabels: {
        enabled: false,
    },
    stroke: {
        colors: ["#fff"],
        width: 0.2
    },
    labels: Array.apply(null, { length: 39 }).map(function (el, index) {
        return index + 1;
    }),
    yaxis: {
        show: false,
        axisBorder: {
            show: false
        },
        axisTicks: {
            show: false
        },
        labels: {
            show: false
        },
    },
    xaxis: {
        labels: {
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        },
    },
    grid: {
        show: true,
        position: 'back',
        borderColor: 'var(--border-light)',
    },
    fill: {
        type: 'image',
        opacity: 0.87,
        image: {
            src: ['../../assets/images/apexchart/1.jpg'],
            width: 466,
            height: 406
        }
    },
};
var chart = new ApexCharts(document.querySelector("#barimg"), options);
chart.render();