'use strict';
const sidebarLinksList = document.querySelector('.sidebar-links');
const subMenus = document.querySelector('.sidebar-submenu');
const url = window.location.href;
const urlLink = url.includes('#') ? url.split('#')[0] : url;
const submenuLinks = document.querySelectorAll('.sidebar-list a');
const allList = document.querySelectorAll('.sidebar-list');
const shoppingPlaceSidebar = document.querySelector('.shopping-place-sidebar');
const reportSidebar = document.querySelector('.report-sidebar');
const projectSidebar = document.querySelector('.project-sidebar');
// active menu js
let slideUp = (target, duration = 500) => {
  if (target) {
    target.style.transitionProperty = 'height, padding';
    target.style.transitionDuration = duration + 'ms';
    target.style.boxSizing = 'border-box';
    target.style.height = target.offsetHeight + 'px';
    target.offsetHeight;
    target.style.overflow = 'hidden';
    target.style.height = 0;
    target.style.paddingTop = 0;
    window.setTimeout(() => {
      target.style.display = 'none';
      target.style.removeProperty('height');
      target.style.removeProperty('padding-top');
      target.style.removeProperty('overflow');
      target.style.removeProperty('transition-duration');
      target.style.removeProperty('transition-property');
    }, duration);
  }
};
let slideDown = (target, duration = 500) => {
  if (target) {
    target.style.removeProperty('display');
    let display = window.getComputedStyle(target).display;
    if (display === 'none') display = 'flex';
    target.style.display = display;
    let height = target.offsetHeight;
    target.style.overflow = 'hidden';
    target.style.height = 0;
    target.style.paddingTop = 0;
    target.offsetHeight;
    target.style.boxSizing = 'border-box';
    target.style.transitionProperty = 'height, padding';
    target.style.transitionDuration = duration + 'ms';
    target.style.height = height + 'px';
    target.style.removeProperty('padding-top');
    window.setTimeout(() => {
      target.style.removeProperty('height');
      target.style.removeProperty('overflow');
      target.style.removeProperty('transition-duration');
      target.style.removeProperty('transition-property');
    }, duration);
  }
};
submenuLinks.forEach((el) => {
  var linkHref = el.href;
  if (urlLink === linkHref && !reportSidebar) {
    el.classList.add('active');
    el.closest('.sidebar-list').classList.add('active');
  }
});
let elOpen;
allList.forEach((el) => {
  el.addEventListener('click', function (e) {
    // const eventSubMenu = e.path[1].closest('.submenu-wrapper');
    if (shoppingPlaceSidebar && !reportSidebar) {
      allList.forEach((item) => {
        if (item.classList.contains('active')) {
          slideUp(item.querySelector('.sidebar-submenu'));
        }
        item.classList.remove('active');
      });
    } else { 
      allList.forEach((item) => {
        if (item !== el) {
          if (item.classList.contains('active')) {
            slideUp(item.querySelector('.sidebar-submenu'));
          }
          item.classList.remove('active');
        }
      });
    }
    el.classList.toggle('active');
    elOpen = el;
    if (el.classList.contains('active')) {
      slideDown(el.querySelector('.sidebar-submenu'));
    } else {
      slideUp(el.querySelector('.sidebar-submenu'));
    }
  });
});
const sidebarHeaderToggle = document.querySelector('.sidebar-toggle');
const sidebarBtn = document.querySelector('.sidebar-btn');
const sidebar = document.querySelector('.default-sidebar');
const pageBodyWrapper = document.querySelector('.page-body-wrapper');
const headerLogoShowOptions = document.querySelector('.sidebar-hide-logo-show');
sidebarBtn?.addEventListener('click', function (e) {
  sidebar.classList.toggle('hide-show-sidebar');
  sidebarHeaderToggle.classList.toggle('hide');
  headerLogoShowOptions?.classList.toggle('hide');
  pageBodyWrapper.classList.toggle('!w-full');
});
sidebarHeaderToggle?.addEventListener('click', function (e) {
  sidebar.classList.toggle('hide-show-sidebar');
  sidebarHeaderToggle.classList.toggle('hide');
  this.closest('.sidebar-hide-logo-show')?.classList.toggle('hide');
  pageBodyWrapper.classList.toggle('!w-full');
});
