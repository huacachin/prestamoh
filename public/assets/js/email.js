//email js

$(document).on('click','.tab-link',function () {
	var tabID = $(this).attr('data-tab');

	$(this).addClass('active').siblings().removeClass('active');

	$('#tab-' + tabID).addClass('active').siblings().removeClass('active');
});

document.querySelector('.toggle-btn').addEventListener('click', () => {
	var mailbox = document.querySelector(".mailbox");
	mailbox.classList.toggle("mailtoggle");
});

document.querySelector('.close-toggle').addEventListener('click', () => {
	var chatcontainer = document.querySelector(".mailbox");
	chatcontainer.classList.remove("mailtoggle");
});

document.querySelectorAll('.star-icon').forEach(function(icon) {
	icon.addEventListener('click', function() {
		this.classList.toggle('ti-star');
		this.classList.toggle('ti-star-filled'); 
	});
  });