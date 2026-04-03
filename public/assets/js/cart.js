$(function() {
  $('#example').DataTable();
  $('#example1').DataTable();
});

$(document).on('click', '.decrement, .increment', function (e) {
  let input = $(this).parent().find('.count');
  let count = input.val();
  const min = 1;
  const max = 7;

  if (count === '') {
    count = 0;
  }
  $(this).hasClass('decrement') ? count-- : count++;
  if (count >= min && count <= max)
    input.val(count)
});


