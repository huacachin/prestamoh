//  **------calender **

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');


    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      navLinks: true,
      editable: true,
      dayMaxEvents: true,
      headerToolbar: {
        left: 'prev,next,addEventButton',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
      },
      customButtons: {
        addEventButton: {
          text: 'Add event...',
          click: function() {
            var dateStr = prompt('Enter a date in YYYY-MM-DD format');
            var date = new Date(dateStr + 'T00:00:00');

            if (!isNaN(date.valueOf())) {
              calendar.addEvent({
                title: 'dynamic event',
                start: date,
                allDay: true
              });
              alert('Great. Now, update your database...');
            } else {
              alert('Invalid date.');
            }
          }
        }
      },
      events: [
        {
          title: 'Events',
          start: '2024-08-01',
          end: '2024-08-02',
        },
        {
          title: 'Events',
          start: '2024-08-08',
          end: '2024-08-12'
        },
        {
          title: 'Events',
          start: '2024-08-15',
        },
        {
          title: 'Events',
          start: '2024-08-30',
          end: '2024-08-31',
        },
        {
          title: 'Meeting',
          start: '2024-08-12T10:30:00',
          end: '2024-08-12T12:30:00'
        },
        {
          title: 'Lunch',
          start: '2024-08-12T12:00:00'
        },
        {
          title: 'Meeting',
          start: '2024-08-12T14:30:00'
        },
        {
          title: 'Happy Hour',
          start: '2024-08-12T17:30:00'
        },
        {
          title: 'Dinner',
          start: '2024-08-12T20:00:00'
        },
        {
          groupId: 'availableForMeeting',
          start: '2024-08-11T10:00:00',
          end: '2024-08-11T16:00:00',
          display: 'background'
        },
        {
          groupId: 'availableForMeeting',
          start: '2024-08-13T10:00:00',
          end: '2024-08-13T16:00:00',
          display: 'background'
        },
      ],
      eventClick:function(){
        $('#exampleModal').modal('show');
      },
      selectable: true,
      selectMirror: true,
      select: function (arg) {
        var title = prompt('Event Title:');
        if (title) {
          calendar.addEvent({
            title: title,
            start: arg.start,
            end: arg.end,
            allDay: arg.allDay
          })
        }
        calendar.unselect()
      },

      droppable: true,
      drop: function (arg) {
        if (document.getElementById('drop-remove').checked) {

          arg.draggedEl.parentNode.removeChild(arg.draggedEl);
        }
      }
    })

    var containerEl = document.getElementById('events-list');
    new FullCalendar.Draggable(containerEl, {
      itemSelector: '.list-event',
      eventData: function (eventEl) {
        return {
          title: eventEl.innerText.trim(),
          start: new Date,
          className: eventEl.getAttribute("data-class")
        }
      }
    });


    calendar.render();
  });

// **------ slider js**

$('.slider-event').slick({
    dots: false,
    speed: 1000,
    slidesToShow: 1,
    centerMode: true,
    arrows: false,
    vertical: true,
    verticalSwiping: true,
    focusOnSelect: true,
    autoplay: true,
    autoplaySpeed: 1000,
});
