/*=====================
    Basic Slider
  ==========================*/
  var basicSlider = new Swiper('.basicSlider', {
    slidesPerView: 4,
    spaceBetween: 5,
    centeredSlider: false,
    loop: true,
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
    autoplay: {
      delay: 1000,
      disableOnInteraction: false,
    },
    breakpoints: {
      1700: {
        slidesPerView: 4,
      },
      1400: {
        slidesPerView: 3,
      },
      1100: {
        slidesPerView: 4,
      },
      320: {
        slidesPerView: 3,
      }
    },
  });
/*=====================
    Default Slider
  ==========================*/
var defaultSlider = new Swiper('.defaultSlider', {});
/*=====================
  Navigation Btn Slider
 ==========================*/
var navigationBtnSlider = new Swiper('.navigationBtnSlider', {
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
});
/*=====================
  Slider with Pagination
 ==========================*/
var sliderPagination = new Swiper('.sliderPagination', {
  pagination: {
    el: '.swiper-pagination',
    dynamicBullets: true,
  },
});
/*===========================
  Slider with Progress Pagination
 ==================================*/
var sliderProgressPagination = new Swiper('.sliderProgressPagination', {
  pagination: {
    el: '.swiper-pagination',
    type: 'progressbar',
  },
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
});
/*===========================
  Slider with Pagination fraction
 ==================================*/
var sliderPaginationFraction = new Swiper('.sliderPaginationFraction', {
  pagination: {
    el: '.swiper-pagination',
    type: 'fraction',
  },
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
});
/*===========================
  Slider with Pagination custom
 ==================================*/
var sliderPaginationCustom = new Swiper('.sliderPaginationCustom', {
  pagination: {
    el: '.swiper-pagination',
    clickable: true,
    renderBullet: function (index, className) {
      return '<span class="' + className + '">' + (index + 1) + '</span>';
    },
  },
});
/*================
  Slides per view
 ===================*/
var slidesPerView = new Swiper('.slidesPerView', {
  slidesPerView: 2,
  spaceBetween: 20,
  pagination: {
    el: '.swiper-pagination',
    clickable: true,
  },
  breakpoints: {
    767: {
      spaceBetween: 20,
      slidesPerView: 3,
    },
    992: {
      spaceBetween: 20,
      slidesPerView: 2,
    },
    1599: {
      spaceBetween: 30,
      slidesPerView: 3,
    },
  },
});
/*===================
  Centered Slides 
 ======================*/
var centeredSlider = new Swiper('.centeredSlider', {
  slidesPerView: 1.5,
  spaceBetween: 20,
  centeredSlides: true,
  loop: true,
  pagination: {
    el: '.swiper-pagination',
    clickable: true,
  },
  breakpoints: {
    767: {
      spaceBetween: 20,
      slidesPerView: 2.5,
    },
    992: {
      spaceBetween: 20,
      slidesPerView: 1.8,
    },
    1599: {
      spaceBetween: 30,
      slidesPerView: 2.5,
    },
  },
});
/*=======================
  Animation Fade Slides 
 ==========================*/
var slideFadeSlider = new Swiper('.slideFadeSlider', {
  spaceBetween: 30,
  effect: 'fade',
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
  pagination: {
    el: '.swiper-pagination',
    clickable: true,
  },
});
/*==================
  Effect Cube Slides 
 ========================*/
var sliderCubEffect = new Swiper('.sliderCubEffect', {
  effect: 'cube',
  grabCursor: true,
  cubeEffect: {
    shadow: true,
    slideShadows: true,
    shadowOffset: 20,
    shadowScale: 0.94,
  },
  pagination: {
    el: '.swiper-pagination',
  },
});
/*=================
  Effect Cover Flow 
 =======================*/
var sliderCoverFlow = new Swiper('.sliderCoverFlow', {
  effect: 'coverflow',
  grabCursor: true,
  centeredSlides: true,
  slidesPerView: 3,
  loop: true,
  coverflowEffect: {
    rotate: 50,
    stretch: 0,
    depth: 100,
    modifier: 1,
    slideShadows: true,
  },
  pagination: {
    el: '.swiper-pagination',
  },
});
/*===================
  Flip Effect Slider
 =======================*/
var sliderFlipEffect = new Swiper('.sliderFlipEffect', {
  effect: 'flip',
  grabCursor: true,
  pagination: {
    el: '.swiper-pagination',
  },
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
});
/*===================
  Slider With Thumbnails
 ==========================*/
var sliderThumbnail = new Swiper('.sliderThumbnail', {
  spaceBetween: 10,
  slidesPerView: 4,
  freeMode: true,
  watchSlidesProgress: true,
});
var sliderMain = new Swiper('.sliderMain', {
  spaceBetween: 10,
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
  thumbs: {
    swiper: sliderThumbnail,
  },
});
/*===================
  Slider With Thumbnails
 ==========================*/
var sliderLazyLoading = new Swiper('.sliderLazyLoading', {
  lazy: true,
  pagination: {
    el: '.swiper-pagination',
    clickable: true,
  },
});
