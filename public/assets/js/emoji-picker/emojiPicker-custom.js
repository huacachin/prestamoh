new EmojiPicker({
  trigger: [
    {
      selector: '.first-btn',
      insertInto: ['.one', '.two'], // '.selector' can be used without array
    },
    {
      selector: '.second-btn',
      insertInto: '.two',
    },
  ],
  closeButton: true,
  //specialButtons: green
});
