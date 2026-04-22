(function () {
    const root = document.querySelector('.csd-ai-gallery');
    if (!root) return;

    const pickBtn   = root.querySelector('#csd-pick-source');
    const clearBtn  = root.querySelector('#csd-clear-source');
    const idInput   = root.querySelector('#csd-source-id');
    const preview   = root.querySelector('.csd-source-preview');
    const generate  = root.querySelector('#csd-generate-btn');
    const status    = root.querySelector('.csd-status');
    const thumbs    = root.querySelector('.csd-thumbs');

    let frame = null;

    function setSelection(att) {
        idInput.value = att ? String(att.id) : '';
        preview.replaceChildren();
        if (att) {
            const img = document.createElement('img');
            img.src = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) || att.url;
            img.alt = '';
            img.className = 'csd-source-thumb';
            preview.appendChild(img);
            const name = document.createElement('span');
            name.className = 'csd-source-name';
            name.textContent = att.filename || '';
            preview.appendChild(name);
            clearBtn.hidden = false;
        } else {
            clearBtn.hidden = true;
        }
    }

    pickBtn.addEventListener('click', function () {
        if (!window.wp || !window.wp.media) {
            status.textContent = 'WordPress media library is not available.';
            return;
        }
        if (!frame) {
            frame = window.wp.media({
                title: window.CSD_AI.mediaTitle,
                button: { text: window.CSD_AI.mediaButton },
                library: { type: 'image/png' },
                multiple: false,
            });
            frame.on('select', function () {
                const att = frame.state().get('selection').first().toJSON();
                if (att.mime !== 'image/png') {
                    status.textContent = 'Only PNG files are supported.';
                    return;
                }
                status.textContent = '';
                setSelection(att);
            });
        }
        frame.open();
    });

    clearBtn.addEventListener('click', function () {
        setSelection(null);
        status.textContent = '';
    });

    generate.addEventListener('click', async function () {
        generate.disabled = true;
        status.textContent = 'Generating… this can take ~30 seconds.';
        thumbs.replaceChildren();

        const fd = new FormData();
        fd.append('action', window.CSD_AI.action);
        fd.append('product_id', root.dataset.productId);
        fd.append('nonce', root.dataset.nonce);
        if (idInput.value) {
            fd.append('source_attachment_id', idInput.value);
        }

        try {
            const res = await fetch(window.CSD_AI.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) {
                const msg = (json.data && json.data.message) ? json.data.message : 'unknown';
                status.textContent = 'Error: ' + msg;
                generate.disabled = false;
                return;
            }
            status.textContent = 'Done. ' + json.data.attachments.length + ' image(s) added to the gallery.';
            json.data.attachments.forEach(function (a) {
                const img = document.createElement('img');
                img.src = a.url;
                img.alt = '';
                img.className = 'csd-thumb';
                thumbs.appendChild(img);
            });
        } catch (err) {
            status.textContent = 'Network error: ' + err.message;
        } finally {
            generate.disabled = false;
        }
    });
})();
