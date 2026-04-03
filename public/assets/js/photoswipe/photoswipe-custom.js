/*=====================
    photoswipe custom js
  ==========================*/
const lightbox = new PhotoSwipeLightbox({
  gallery: '#my-gallery',
  children: 'a',
  pswpModule: () => import('https://unpkg.com/photoswipe'),
});
lightbox.init();