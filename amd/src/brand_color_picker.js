define([], function() {
    const normalize = (value) => {
        value = (value || '').trim();
        if (!value) {
            return '#ffffff';
        }
        if (value.charAt(0) !== '#') {
            value = '#' + value;
        }
        return value;
    };

    const init = (selector) => {
        const text = document.querySelector(selector);
        if (!text) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'edukav-brand-color-picker';
        wrap.innerHTML = `
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="color" id="id_brand_color_picker" value="#ffffff" 
                class="form-control form-control-color" 
                style="width: 4rem; height: 3rem; padding: 0.2rem;">
            </div>
        `;

        text.insertAdjacentElement('afterend', wrap);

        const picker = wrap.querySelector('#id_brand_color_picker');
        const syncPicker = () => {
            const value = normalize(text.value);
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                picker.value = value;
            } else {
                picker.value = '#ffffff';
            }
        };

        syncPicker();
        picker.addEventListener('input', () => {
            text.value = picker.value;
            text.dispatchEvent(new Event('input', { bubbles: true }));
            text.dispatchEvent(new Event('change', { bubbles: true }));
        });
        text.addEventListener('change', syncPicker);
        text.addEventListener('input', syncPicker);
    };

    return {
        init: init
    };
});
