function addClassElementEvent(element,className,event){

    let elements = document.querySelectorAll(element);

    for(var i = 0; i<elements.length; i++) {
        elements[i].addEventListener(event, function(event) {
            [].forEach.call(elements, function(el) {
                el.classList.remove(className);
            });
            this.classList.add(className);
        });
    }
}

addClassElementEvent('.list-active','active','click');
addClassElementEvent('.list-group-item-action','active','click');


document.querySelector('.contact-listbox').addEventListener('click', () => {
	var contactStared = document.querySelector(".contact-stared");
	contactStared.classList.toggle("stared");
});