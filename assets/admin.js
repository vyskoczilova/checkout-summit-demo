(function () {
    const root = document.querySelector('.csd-ai-gallery');
    if (!root) return;

    const fileInput = root.querySelector('#csd-source-image');
    const button    = root.querySelector('#csd-generate-btn');
    const status    = root.querySelector('.csd-status');
    const thumbs    = root.querySelector('.csd-thumbs');

    button.addEventListener('click', async function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            status.textContent = 'Pick a PNG first.';
            return;
        }
        if (file.type !== 'image/png') {
            status.textContent = 'Only PNG files are supported.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Generating… this can take ~30 seconds.';
        thumbs.replaceChildren();

        const fd = new FormData();
        fd.append('action', window.CSD_AI.action);
        fd.append('product_id', root.dataset.productId);
        fd.append('nonce', root.dataset.nonce);
        fd.append('source_image', file);

        try {
            const res = await fetch(window.CSD_AI.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) {
                const msg = (json.data && json.data.message) ? json.data.message : 'unknown';
                status.textContent = 'Error: ' + msg;
                button.disabled = false;
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
            button.disabled = false;
        }
    });
})();
