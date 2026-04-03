//---- code copy js---- //


$(document).on('click',".box-shadow-box",function (e){
    const classNameToCopy = $(this).attr('class');
    copyTextToClipboard(classNameToCopy)
    Toastify({
        text: "Class name copied to the clipboard successfully",
        duration: 3000,
        close: true,
        gravity: "top",
        position: "right",
        stopOnFocus: true,
        style: {background: "rgba(var(--success),1)"},
        onClick: function () {
        }
    }).showToast();
});

function copyTextToClipboard(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}
