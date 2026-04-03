/*=======/recentstatisticschart/=======*/
var options = {
    series: [{
        type: 'column',
        data: [52, 67, 73, 38, 67, 80, 23]
    },],
    chart: {
        height: 350,
        type: 'line',
        toolbar: {
            show: false
        },
    },
    stroke: {
        curve: 'smooth',
        width: 0,
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            distributed: true,
            borderRadius: 5,
        }
    },
    colors: ['#008193', '#F5F6F9', '#008193', '#F5F6F9', '#008193', '#F5F6F9', '#F5F6F9'],
    tooltip: {
        x: {
            show: false,
        },
        z: {
            show: false
        }
    },
    grid: {
        show: true,
        borderColor: 'var(--border-light)',
        position: 'back',
    },
    xaxis: {
        labels: {
            minHeight: undefined,
            maxHeight: 24,
            offsetX: 0,
            offsetY: 0,
            style: {
                ...fontCommon,
                fontWeight: 400,
            },
        },
    },
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
    yaxis: {
        min: 10,
        max: 80,
        labels: {
            show: true,
            align: 'right',
            style: {
                ...fontCommon,
            },
            formatter: (value) => {
                return `${value}k`;
            },
        },
    },
};
var chart = new ApexCharts(document.querySelector("#recentstatisticschart"), options);
chart.render();