define(['core/log'], function(log) {
    const ASSET_ROOT = (window.M && window.M.cfg && window.M.cfg.wwwroot)
        ? window.M.cfg.wwwroot + '/course/format/edukav/assets'
        : '/course/format/edukav/assets';

    const CSS_URL = ASSET_ROOT + '/css/plyr.css';
    const JS_URL = ASSET_ROOT + '/js/plyr.js';

    const OPTIONS = {
        youtube: { noCookie: true },
        i18n: {
            quality: 'Calidad',
            speed: 'Velocidad',
            captions: 'Subtítulos',
            disabled: 'Desactivar',
            enabled: 'Activar',
        },
    };

    const ensureCss = () => {
        if (document.querySelector('link[data-edukav-plyr="css"]')) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = CSS_URL;
        link.setAttribute('data-edukav-plyr', 'css');
        document.head.appendChild(link);
    };

    const loadPlyr = () => {
        if (typeof window.Plyr === 'function') {
            return Promise.resolve(window.Plyr);
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = JS_URL;
            script.async = true;
            script.onload = () => {
                if (typeof window.Plyr === 'function') {
                    resolve(window.Plyr);
                    return;
                }

                if (typeof window.require === 'function') {
                    window.require(['Plyr'], resolve, reject);
                    return;
                }

                reject(new Error('Plyr no quedó disponible.'));
            };
            script.onerror = () => reject(new Error('No se pudo cargar Plyr.'));
            document.head.appendChild(script);
        });
    };

    const initPlayers = (PlyrCtor) => {
        document.querySelectorAll('.plyr').forEach((player) => {
            if (player.plyr || player.hasAttribute('data-plyr-initialized')) {
                return;
            }

            new PlyrCtor(player, OPTIONS);
            player.setAttribute('data-plyr-initialized', 'true');
        });
    };

    const init = () => {
        ensureCss();

        const run = () => {
            loadPlyr()
                .then(initPlayers)
                .catch((error) => log.error('Plyr: error al inicializar', error));
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, { once: true });
            return;
        }

        run();
    };

    return { init };
});
