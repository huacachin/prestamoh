//
$(function() {
  $('.counting').each(function () {
    var $this = $(this),
        countTo = $this.attr('data-count');
    $({ countNum: $this.text() }).animate({
          countNum: countTo
        },
        {
          duration: 4000,
          easing: 'linear',
          step: function () {
            $this.text(Math.floor(this.countNum));
          },
          complete: function () {
            $this.text(this.countNum + '+');
          }
        });
  });
});

// cursor js
const circleElement = document.querySelector('.circle-cursor');

const cursor = { x: 0, y: 0 },
      circle = { x: 0, y: 0 };

window.addEventListener('mousemove', e => {
  cursor.x = e.x;
  cursor.y = e.y;
});

// Speed factor
// Between 0 and 1 (0 = smoother, 1 = instant)
const speed = 0.10;

const tick = () => {
  circle.x += (cursor.x - circle.x) * speed;
  circle.y += (cursor.y - circle.y) * speed;

  circleElement.style.transform = `translate(${circle.x}px, ${circle.y}px)`;

  window.requestAnimationFrame(tick);
}


tick();



// >>-- tap on top --<<
let calcScrollValue = () => {
  let scrollProgress = document.getElementsByClassName("go-top");
  let progressValue = document.getElementsByClassName("progress-value");
  let pos = document.documentElement.scrollTop;
  let calcHeight =
    document.documentElement.scrollHeight -
    document.documentElement.clientHeight;
  let scrollValue = Math.round((pos * 100) / calcHeight);
  if (pos > 100) {
    scrollProgress[0].style.display = 'grid';
  } else {
    scrollProgress[0].style.display = 'none';
  }

  scrollProgress[0].addEventListener("click", () => {
    document.documentElement.scrollTop = 0;
  });

  scrollProgress[0].style.background = `conic-gradient( rgba(var(--primary),1) ${scrollValue}%, var(--light-gray) ${scrollValue}%)`;
};

window.onscroll = calcScrollValue;
/* Theme name prepend to localstorage key*/
const themeName = "La-Theme";

/* Get item in local store */
function setLocalStorageItem(key,value){
  localStorage.setItem(`${themeName}-${key}`, value);
}

$(document).on('click','#darkDemoBtn',function () {
  setLocalStorageItem("theme-mode", 'dark');
  window.location.href ='/index'
})

// Typing js
document.addEventListener('DOMContentLoaded', function() {
  let index = 0;
  let index1 = 0;
  const powerfullAndEasyText = "powerful";
  const easyfullAndEasyText = "easy";

  const powerfulContent = document.querySelector('.powerful');
  const easyContent = document.querySelector('.easy');

  function Easy() {
    easyContent.textContent += easyfullAndEasyText[index1];
    index1++;
    if (index1 < easyfullAndEasyText.length) {
      setTimeout(Easy, 100);
    }
  }

  function powerfulAndEasy() {
    powerfulContent.textContent += powerfullAndEasyText[index];
    index++;
    if (index < powerfullAndEasyText.length) {
      setTimeout(powerfulAndEasy, 200);
    } else{
      document.querySelector(".and").innerHTML += "&";
      Easy();
    }
  }
  powerfulAndEasy()
});

//
"use strict";
$(function() {
    var tooltip_init = {
        init: function () {
            $("i").tooltip();
            $("a").tooltip();
        }
    };
    tooltip_init.init()
});

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();

        const targetId = this.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        const offset = 80; // Adjust based on your header height

        window.scrollTo({
            top: targetElement.offsetTop - offset,
            behavior: 'smooth'
        });
    });
});
