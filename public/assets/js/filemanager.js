function changeLanguage(language) {
  var element = document.getElementById("url");
  element.value = language;
  element.innerHTML = language;
}

function showDropdown() {
  document.getElementById("myDropdown").classList.toggle("show");
}

// Close the dropdown if the user clicks outside of it
window.onclick = function(event) {
  if (!event.target.matches(".dropbtn")) {
    var dropdowns = document.getElementsByClassName("dropdown-content");
    var i;
    for (i = 0; i < dropdowns.length; i++) {
      var openDropdown = dropdowns[i];
      if (openDropdown.classList.contains("show")) {
        openDropdown.classList.remove("show");
      }
    }
  }
};


//  **------tab link js**
$(document).on('click','.tab-link',function () {
	var tabID = $(this).attr('data-tab');

	$(this).addClass('active').siblings().removeClass('active');

	$('#tab-' + tabID).addClass('active').siblings().removeClass('active');
});

//  **------chart js**

var options = {
  series: [42, 47, 52, 58],
  chart: {
    height: 340,
    type: 'donut'
  },
  labels: ['Data A', 'Data B', 'Data C', 'Data D'],
  dataLabels: {
    enabled: false
  },
  colors: [getLocalStorageItem('color-primary','#056464'), getLocalStorageItem('color-secondary','#74788d'), '#ea5659', '#fac10f'],
  yaxis: {
    show: false
  },
  legend: {
    show:true,
    position: 'top',
  },
};

var chart = new ApexCharts(document.querySelector("#polar2"), options);
chart.render();