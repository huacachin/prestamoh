document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialDate: '2020-09-12',
    initialView: 'timeGridWeek',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
    height: '370px',
    width: '490px',
    navLinks: false, // can click day/week names to navigate views
    editable: true,
    selectable: true,
    selectMirror: true,
    nowIndicator: true,
    events: [
      {
        id: 1,
        subTitle: 'Project Admin \ 12:15 am',
        task: 'Mon 4 Nov, 2023',
        classNames: ['common-style', 'bg-gray-light'],
        start: '2020-09-05T24:00:00',
      },
      {
        id: 2,
        subTitle: 'NFt Website \ 10:10 am',
        task: 'Tue 4 Nov, 2023',
        classNames: ['common-style', 'bg-gray-light'],
        start: '2020-09-07T01:00:00',
      },
      {
        id: 3,
        subTitle: 'App Landing page\ 12:15 am',
        task: 'Sat 12 Nov, 2023',
        classNames: ['common-style', 'bg-gray-light'],
        start: '2020-09-08T02:00:00',
      },
    ],
  });
  calendar.render();
});