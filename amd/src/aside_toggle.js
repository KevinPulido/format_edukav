define([], function() {

    const init = () => {
        const button = document.querySelector('[data-toggle="aside"]');
        const aside = document.querySelector('.edukav-sidebar');

        if (!button || !aside) {
            return;
        }

        button.addEventListener('click', () => {
            aside.classList.toggle('closed');
        });
    };

    return {
        init: init
    };
});