var kanban1 = new jKanban({
    element: '.kanab-1',
    boards: [
       {
          id: '_todo',
          title: 'Todo Taks',
          gutter: '15px',
          widthBoard: '250px',
          responsive: '700',
          item: [
             {
                title: `user
             <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-primary"></div>
                         <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Final Test For The Design Issues </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-primary text-primary rounded">design</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">7</span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                      </div>
              </div>
                `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-warning"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-secondary text-secondary rounded">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/3.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-danger"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Not Proper Responsive ro Some Screen</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-danger title-modules rounded">Issues</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">34</span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_doing',
          title: 'In Progress',
          item: [
             {
                title: `
                <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class=" flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-info"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Testing dark and rtl  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">35</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
                  <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-info"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working on demo 3 design </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_done',
          title: 'Done',
          item: [
             {
                title: `
                 <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-warning"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">working demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-warning text-warning rounded">warning</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-success"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Make Responsive demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/7.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/3.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 shadow-md bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-success"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working On Dark and Rtl of demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/8.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
    ],
 });
 var kanban2 = new jKanban({
    element: '.kanab-2',
    gutter: '15px',
    click: function (el) {
       alert(el.innerHTML);
    },
    boards: [
       {
          id: '_todo',
          title: 'To Do (Item only in Working)',
          class: 'bg-info',
          dragTo: ['_working'],
          item: [
             {
                title: `
             <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-primary"></div>
                         <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Final Test For The Design Issues </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">design</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">7   </span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                      </div>
              </div>
                `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-warning"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-secondary text-secondary rounded">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/8.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-danger"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Not Proper Responsive ro Some Screen</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-warning text-warning rounded">Issues</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">34</span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_working',
          title: 'Working',
          class: 'bg-warning',
          item: [
             {
                title: `
                <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-info"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Testing dark and rtl  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">35</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
                  <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-info"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working on demo 3 design </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_done',
          title: 'Done (Item only in Working)',
          class: 'bg-success',
          dragTo: ['_working'],
          item: [
             {
                title: `
                 <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-warning"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">working demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-warning text-warning rounded">warning</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-success"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Make Responsive demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <div class="min-w-3 w-3 min-h-3 h-3 rounded-full bg-success"></div>
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working On Dark and Rtl of demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
    ],
 });
 var kanban3 = new jKanban({
    element: '.kanab-3',
    gutter: '15px',
    click: function (el) {
       alert(el.innerHTML);
    },
    boards: [
       {
          id: '_todo',
          title: 'To Do',
          class: 'info',
          item: [
             {
                title: `
             <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-success border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                         <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Final Test For The Design Issues </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-primary text-primary rounded">design</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">7   </span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                      </div>
              </div>
                `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-warning  border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-secondary text-secondary rounded">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/3.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
           <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-danger  border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Not Proper Responsive ro Some Screen</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-warning text-warning rounded">Issues</div><span class="text-gray-500">48%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">34</span></div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_working',
          title: 'Working',
          class: 'warning',
          item: [
             {
                title: `
                <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-info border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Testing dark and rtl  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">35</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
                  <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-info border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working on demo 3 design </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-info text-info rounded">info</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/7.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/8.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
       {
          id: '_done',
          title: 'Done',
          class: 'success',
          item: [
             {
                title: `
                 <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-warning border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">working demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-warning text-warning rounded">warning</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-success border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Make Responsive demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/3.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4 border-l-4 border-l-success border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Working On Dark and Rtl of demo 2  </h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge border border-success text-success rounded">success</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/7.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
    ],
 });
 var toDoButton = document.getElementById('addToDo');
 toDoButton.addEventListener('click', function () {
    kanban3.addElement('_todo', {
       title: `
      <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4  border border-border-light bg-white dark:bg-mode-300 rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge badge-warning badge-outline rounded-5">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/9.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/8.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/10.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/11.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
    });
 });
 var addBoardDefault = document.getElementById('addDefault');
 addBoardDefault.addEventListener('click', function () {
    kanban3.addBoards([
       {
          id: '_default',
          title: 'Kanban Default',
          item: [
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4  border border-border-light bg-white rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge badge-warning badge-outline rounded-5">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/1.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/2.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/3.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/4.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
             {
                title: `
            <div class="kanban-box">
                   <div class="kanab-item-1 p-5 2xl:p-4  border border-border-light bg-white rounded-5 flex flex-col gap-5 2xl:gap-3">
                         <div class="flex items-center gap-2">
                            <h5 class="w-[calc(100%_-_12px)] 2xl:text-xs text-sm text-dark">Don't use unwanted extra div because is make dome bigger</h5>
                         </div>
                         <div class="2xl:flex-col 2xl:items-start 2xl:gap-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                            <div class="badge badge-warning badge-outline rounded-5">code</div><span class="text-gray-500">88%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-1"> <i class="w-5 h-5 stroke-gray-500" data-feather="message-circle"></i><span class="text-gray-500">15</span></div>
                          <div class="avatar-group -space-x-6">
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/5.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/6.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/7.jpg" alt="avatar"></div>
                            </div>
                            <div class="avatar dark:border-mode-200">
                               <div class="rounded-full w-7 h-7"><img src="../../assets/images/user-card/8.jpg" alt="avatar"></div>
                            </div>
                            </div>
                         </div>
                         </div>
                      </div>
                   </div>
              </div>
                             `,
             },
          ],
       },
    ]);
 });
 feather.replace();
 var removeBoard = document.getElementById('removeBoard');
 removeBoard.addEventListener('click', function () {
    kanban3.removeBoard('_done');
 });
