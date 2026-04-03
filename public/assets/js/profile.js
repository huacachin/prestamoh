// **------ image uplode **

function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#imgPreview').css('background-image', 'url('+e.target.result +')');
            $('#imgPreview').hide();
            $('#imgPreview').fadeIn(650);
        }
        reader.readAsDataURL(input.files[0]);
    }
}
$("#imageUpload").change(function() {
    readURL(this);
});

//  **------tab js**
$(document).on('click','.tab-link',function () {
	var tabID = $(this).attr('data-tab');
	
	$(this).addClass('active').siblings().removeClass('active');
	
	$('#tab-'+tabID).addClass('active').siblings().removeClass('active');
});

//  **------image js**
GLightbox({
    touchNavigation: true,
    loop: true,
    width: "90vw",
    height: "90vh",
  });

//  **-----post js**

const pond= FilePond.create(
    document.querySelector('.filepond-file'),
    {
      labelIdle: `<i class="fa-solid fa-cloud-upload fa-fw fs-4"></i> <div class="filepond--label-action text-decoration-none">Upload Your Files</div>`,
    }
);

FilePond.registerPlugin(FilePondPluginFileValidateType);
FilePond.registerPlugin(FilePondPluginImagePreview);
FilePond.registerPlugin(FilePondPluginFileEncode);
FilePond.registerPlugin(FilePondPluginFileValidateSize);
FilePond.registerPlugin(FilePondPluginImageExifOrientation);

const pondInput = FilePond.create(
    document.querySelector('#id'),
    {
      labelIdle: `<i class="fa-solid fa-cloud-upload fa-fw fs-4"></i> <div class="filepond--label-action text-decoration-none">Upload Your Files</div>`,
    }
);

//  **------post gallery js**
GLightbox({
    touchNavigation: true,
    loop: true,
    width: "90vw",
    height: "90vh",
  });
  
  var lightboxDescription = GLightbox({
    selector: '.descriptionbox'
  });
  
  var lightboxVideo = GLightbox({
    selector: '.glightboxvideo'
  });
  
  var lightboxVideo = GLightbox({
    selector: '.gbox'
  });


 //  **------ slider**
 $('.story-container').slick({
  slidesToShow: 4,
  slidesToScroll: 1,
  autoplay: true,
  arrows: false,
  autoplaySpeed: 1000,
  responsive: [
    {
      breakpoint: 1366,
      settings: {
        slidesToShow: 2
      }
    },
    {
      breakpoint: 768,
      settings: {
        slidesToShow: 3
      }
    },
    {
      breakpoint: 480,
      settings: {
        slidesToShow: 1,
      }
    },
  ]
});