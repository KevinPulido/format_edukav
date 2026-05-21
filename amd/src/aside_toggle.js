define([], function() {
    const init = () => {
        const button = document.querySelector('[data-toggle="aside"]');
        const aside = document.querySelector('.edukav-sidebar-shell');
        const icon = button ? button.querySelector('i') : null;

        if (!button || !aside || !icon) {
            return;
        }

        button.addEventListener('click', () => {

            aside.classList.toggle('closed');
            const isClosed = aside.classList.contains('closed');

            /* cambiar icono */
            icon.classList.remove('fa-chevron-left', 'fa-bars');
            icon.classList.add(isClosed ? 'fa-bars' : 'fa-chevron-left');

            button.setAttribute('aria-expanded', String(!isClosed));
            button.setAttribute(
                'aria-label',
                isClosed ? 'Mostrar barra lateral' : 'Ocultar barra lateral'
            );
        });
    };

    return {
        init: init
    };
});
