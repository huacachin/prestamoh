const iconWrapper = document.querySelector('.icon-event');
iconWrapper.addEventListener('click', function (event) {
  const iconBox = event.target.closest('.icon-box');
  if (!iconBox) return;
  const iconName = iconBox.querySelector('.icon-name').textContent;
  const featherPage = event.target.closest('.feather-event');
  const iconlyNotBulk = event.target.closest('.icon-not-bulk-event');
  const iconlyBulk = event.target.closest('.icon-bulk-event');
  const iconEl = iconBox.querySelector("i[class*='iconly-']");
  const iconBulkEl = iconBox.querySelector("span[class*='iconlyBulk-']");
  const iconBulkCode = iconBulkEl?.outerHTML;
  const preCodeBulk = iconBulkCode?.replaceAll('<', '&lt;').replaceAll('>', '&gt;');
  const iconClass = iconEl?.classList.contains('icli') ? 'icli' : iconEl?.classList.contains('icbo') ? 'icbo' : '';
  const featherHtml = `<div class="p-3 flex items-center justify-center rounded-5 border border-border-light"> <i class="w-7 h-7 stroke-title " data-feather=${iconName}></i></div>
              <div class="relative bg-white2-light p-[12px] copyParent  rounded-5 border border-border-light">
                 <button class="btn copyBtn border-border-light !top-2 !right-[10px]"> <i data-feather="clipboard"></i></button>
                  <pre class="language-html !whitespace-pre-wrap custom-scroll overflow-auto"> <code>&lt;i data-feather="${iconName}"&gt;&lt;/i&gt; </code></pre>
               </div>
            </div> `;
  const iconlyHtml = `
            <div class="p-3 flex items-center justify-center rounded-5 border border-border-light"> 
               <svg class="w-6 h-6 fill-title"> 
                    <use href="../assets/icons/svg/_sprite.svg#${iconName}"></use>
               </svg>
             </div>
              <div class="relative bg-white2-light p-[12px] copyParent w-full overflow-auto rounded-5 border border-border-light">
                 <button class="btn copyBtn border-border-light !top-2 !right-[10px]"> <i data-feather="clipboard"></i></button>
                  <pre class="language-html !whitespace-pre-wrap custom-scroll overflow-auto"><code>&lt;svg class="w-6 h-6 fill-title"&gt;
  &lt;use href="../assets/icons/svg/_sprite.svg#${iconName}" &gt;&lt;/use&gt;
&lt;/svg&gt;</code></pre>
               </div>
            </div> 
        `;
  const iconlyBulkHtml = `
            <div class="p-3 flex items-center justify-center rounded-5 border border-border-light"> ${iconBulkCode}</div>
              <div class="w-full overflow-auto relative bg-white2-light p-[12px] copyParent  rounded-5 border border-border-light">
                 <button class="btn copyBtn border-border-light !top-2 !right-[10px]"> <i data-feather="clipboard"></i></button>
                  <pre class="language-html !whitespace-pre-wrap custom-scroll overflow-auto"> <code>${preCodeBulk}</code></pre>
               </div>
            </div> 
        `;
  const html = `<div class="copy-box-wrapper z-10 fixed inset-0 flex items-center justify-center">
         <a class="fixed overlay-close inset-0 z-10 bg-black opacity-25" href="javascript:void(0)"></a>
          <div class="icon-modal z-20 relative group max-w-[50%] lg:max-w-[80%] sm:max-w-[90%]">
            <div class="shadow-2xl bg-white dark:bg-mode-300 flex flex-col rounded-5 items-center gap-3  p-5 relative">
            <span class="close-btn border-border-light absolute top-4 right-4 rtl:left-4 rtl:right-unset flex items-center justify-center p-1 border shadow-md  rounded-full bg-white dark:bg-mode-200 ">
               <i data-feather="x" class="stroke-title w-4 h-4"></i>
            </span>
            ${featherPage ? featherHtml : ''}
            ${iconlyNotBulk ? iconlyHtml : ''} 
            ${iconlyBulk ? iconlyBulkHtml : ''}  
          </div> 
        </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  feather.replace();
  const copyBtn = document.querySelector('.copyBtn');
  const overlay = document.querySelector('.overlay-close');
  const closeBtn = document.querySelector('.close-btn');
  copyBtn?.addEventListener(
    'click', // Copy Function
    function copyFunction() {
      const BtnParentEl = this.closest('.copyParent').querySelector('pre').textContent;
      navigator.clipboard.writeText(BtnParentEl);
      this.innerHTML = ` 
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check-circle">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>`;
      setTimeout(() => {
        this.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard">
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
    </svg>`;
      }, 1500);
    },
  );
  function closeModal(el) {
    el.addEventListener('click', function () {
      document.querySelector('.copy-box-wrapper').remove();
    });
  }
  closeModal(overlay);
  closeModal(closeBtn);
});