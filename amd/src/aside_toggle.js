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

            /* cambiar icono */
            if (aside.classList.contains('closed')) {

                icon.classList.remove(
                    'fa-sharp-duotone',
                    'fa-solid',
                    'fa-circle-left'
                );

                icon.classList.add(
                    'fa-sharp-duotone',
                    'fa-solid',
                    'fa-circle-right'
                );

            } else {

                icon.classList.remove(
                    'fa-sharp-duotone',
                    'fa-solid',
                    'fa-circle-right'
                );

                icon.classList.add(
                    'fa-sharp-duotone',
                    'fa-solid',
                    'fa-circle-left'
                );
            }
        });
    };

    return {
        init: init
    };
});