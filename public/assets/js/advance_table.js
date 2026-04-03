// dragebal tabel 
let shadow
function dragit(e){
  shadow=e.target;
}
function dragover(e){
  let children=Array.from(e.target.parentNode.parentNode.children);
if(children.indexOf(e.target.parentNode)>children.indexOf(shadow))
  e.target.parentNode.after(shadow);
  else e.target.parentNode.before(shadow);
}

// checkbox js
document.getElementById('projectcheck').onclick = function() {
  var checkboxes = document.querySelectorAll('input[type="checkbox"]');
  for (var checkbox of checkboxes) {
      checkbox.checked = this.checked;
  }
}

// project table js
$(function() {
  $('#projectTable').DataTable();
});

document.addEventListener('DOMContentLoaded', (event) => {
  // Function to handle delete action
  const handleDelete = (event) => {
      const deleteButton = event.target;
      if (deleteButton.classList.contains('delete-btn')) {
          const row = deleteButton.closest('tr');
          row.remove();
      }
  };

  // Add event listener to all delete buttons
  const deleteButtons = document.querySelectorAll('.delete-btn');
  deleteButtons.forEach(button => {
      button.addEventListener('click', handleDelete);
  });
});