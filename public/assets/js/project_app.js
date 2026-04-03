// **------ tab js**
$(document).on('click','.tab-link',function () {
	var tabID = $(this).attr('data-tab');
	
	$(this).addClass('active').siblings().removeClass('active');
	
	$('#tab-'+tabID).addClass('active').siblings().removeClass('active');
});

"use strict";
$(function() {
    var tooltip_init = {
        init: function () {
            $("a").tooltip();
        }
    };
    tooltip_init.init()
});