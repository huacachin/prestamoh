document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');
  var calendar = new FullCalendar.Calendar(calendarEl, {
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
    },
    initialDate: '2020-09-12',
    navLinks: true, // can click day/week names to navigate views
    businessHours: true, // display business hours
    editable: true,
    selectable: true,
    events: [
      {
        title: 'Business Lunch',
        start: '2020-09-03T13:00:00',
        constraint: 'businessHours'
      },
      {
        title: 'Meeting',
        start: '2020-09-13T11:00:00',
        constraint: 'availableForMeeting', // defined below
        color: '#257e4a'
      },
      {
        title: 'Conference',
        start: '2020-09-18',
        end: '2020-09-20'
      },
      {
        title: 'Party',
        start: '2020-09-29T20:00:00'
      },
      // areas where "Meeting" must be dropped
      {
        groupId: 'availableForMeeting',
        start: '2020-09-11T10:00:00',
        end: '2020-09-11T16:00:00',
        display: 'background'
      },
      {
        groupId: 'availableForMeeting',
        start: '2020-09-13T10:00:00',
        end: '2020-09-13T16:00:00',
        display: 'background'
      },
      // red areas where no events can be dropped
      {
        start: '2020-09-24',
        end: '2020-09-28',
        overlap: false,
        display: 'background',
        color: '#ff9f89'
      },
      {
        start: '2020-09-06',
        end: '2020-09-08',
        overlap: false,
        display: 'background',
        color: '#ff9f89'
      }
    ]
  });
  calendar.render();
});
// ----- index calender  
document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar-index');
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialDate: '2020-09-12',
    initialView: 'timeGridWeek',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
    height: 'auto',
    navLinks: false, // can click day/week names to navigate views
    editable: true,
    selectable: true,
    selectMirror: true,
    nowIndicator: true,
    events: [
      {
        id: 1,
        title: 'Carla Packard ',
        subTitle: 'Ux Designer',
        image: '../assets/images/avatar/2.jpg',
        hours: 06,
        task: 02,
        classNames: ['common-style', 'bg-primary-light'],
        // start: '2020-09-09 T12:30:00',
        start: '2020-09-08T02:00:00',
      },
      {
        id: 2,
        title: 'Wade Warren',
        subTitle: 'Ux Designer',
        image: '../assets/images/avatar/11.jpg',
        hours: 06,
        task: 02,
        classNames: ['common-style', 'bg-secondary-light'],
        start: '2020-09-06T01:00:00',
      },
      {
        id: 3,
        title: 'Andrew Black',
        subTitle: 'Web designer',
        image: '../assets/images/avatar/17.jpg',
        hours: 06,
        task: 02,
        classNames: ['common-style', 'success-light'],
        start: '2020-09-08T02:00:00',
      },
    ],
  });
  calendar.render();
});