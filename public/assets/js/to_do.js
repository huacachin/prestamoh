function deleteTodoItem(){
    document.querySelectorAll(".delete").forEach(button => {
        button.addEventListener("click", () => {
            button.parentNode.remove();
        });
    });
}

deleteTodoItem();
// todo js
document.querySelector('#push').onclick = function () {
    if (document.querySelector('#newtask input').value.length == 0) {
        alert("Enter Task Name!!!!")
    } else {
        document.querySelector('#tasks').innerHTML += `
            <div>
                <div class="task">
                    <span>
                        ${document.querySelector('#newtask input').value}
                    </span>
                    <button class="btn btn-sm p-1 border-0  delete">
                            <i class="ti ti-trash title-modules f-s-18"></i>
                    </button>
                </div>
            </div>
        `;
        deleteTodoItem();
    }
}

// todo table
var options = {
    valueNames: ['id', 'employee', 'email', 'contact', 'date', 'status',],
};

// Init list
var contactList = new List('myTodo', options);

var idField = $('#id-field'),
    employeeField = $('#employee-field'),
    emailField = $('#email-field'),
    contactField = $('#contact-field'),
    dateField = $('#date-field'),
    statusField = $('#status-field'),
    addBtn = $('#add-btn'),
    editBtn = $('#edit-btn').hide(),
    removeBtns = $('.remove-item-btn'),
    editBtns = $('.edit-item-btn');

// Sets callbacks to the buttons in the list
refreshCallbacks();

$(document).on("submit", "#add_employee_todo", function (e) {
    e.preventDefault();
    $(this).parent().modal("hide");
    let newItem = {
        id: Math.floor(Math.random() * 110000),
        employee: employeeField.val(),
        email: emailField.val(),
        contact: contactField.val(),
        date: dateField.val(),
        status: `<span class="badge text-uppercase bg-${statusField.val()}-subtle text-${statusField.val()}">${$("#status-field option:selected").text()}</span>`,
    }
    contactList.add(newItem)
    clearFields();
    refreshCallbacks();
})

$(document).on('click', '#edit-btn', function () {
    var item = contactList.get('id', idField.val())[0];
    item.values({
        id: idField.val(),
        employee: employeeField.val(),
        email: emailField.val(),
        contact: contactField.val(),
        date: dateField.val(),
        status: `<span class="badge text-uppercase bg-${statusField.val()}-subtle text-${statusField.val()}">${$("#status-field option:selected").text()}</span>`,
    });
    clearFields();
    editBtn.hide();
    addBtn.show();
});

function refreshCallbacks() {
    // Needed to add new buttons to jQuery-extended object
    removeBtns = $(removeBtns.selector);
    editBtns = $(editBtns.selector);

    $(document).on('click', '.remove-item-btn', function () {
        var itemId = $(this).closest('tr').find('.id').text();
        contactList.remove('id', itemId);
    });

    $(document).on('click', '.edit-item-btn', function () {
        var itemId = $(this).closest('tr').find('.id').text();
        var itemValues = contactList.get('id', itemId)[0].values();
        idField.val(itemValues.id);
        employeeField.val(itemValues.employee);
        emailField.val(itemValues.email);
        contactField.val(itemValues.contact);
        dateField.val(itemValues.date);
        statusField.val(itemValues.status);

        editBtn.show();
        addBtn.hide();
    });
}

function clearFields() {
    employeeField.val('');
    emailField.val('');
    contactField.val('');
    dateField.val('');
    statusField.val('');
}
