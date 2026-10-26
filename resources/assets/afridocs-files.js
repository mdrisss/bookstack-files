/**
 * Afridocs Files - gestion asynchrone des fichiers liés aux entités BookStack.
 *
 * Chaque bloc [data-afd-files] charge sa liste via l'API du module, permet
 * l'ajout (clic ou glisser-déposer), la suppression, et l'aperçu en popup.
 */
(function () {
    'use strict';

    const translations = readJson('afd-translations');
    const csrfToken = document.querySelector('meta[name="token"]')?.getAttribute('content') || '';
    let dialog = null;

    function readJson(id) {
        try {
            return JSON.parse(document.getElementById(id)?.textContent || '{}');
        } catch (e) {
            return {};
        }
    }

    function t(key, params = {}) {
        let text = translations[key] || key;
        for (const [name, value] of Object.entries(params)) {
            text = text.replace(':' + name, value);
        }
        return text;
    }

    function icon(name) {
        const template = document.getElementById('afd-icons');
        const svg = template?.content.querySelector(`[data-icon="${name}"] svg`);
        return svg ? svg.cloneNode(true) : document.createTextNode('');
    }

    function el(tag, attrs = {}, children = []) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'text') {
                node.textContent = value;
            } else if (key.startsWith('on')) {
                node.addEventListener(key.substring(2), value);
            } else {
                node.setAttribute(key, value);
            }
        }
        for (const child of children) {
            node.append(child);
        }
        return node;
    }

    function notify(type, message) {
        if (window.$events && typeof window.$events[type] === 'function') {
            window.$events[type](message);
        } else if (type === 'error') {
            window.alert(message);
        }
    }

    function formatSize(bytes) {
        if (!bytes) return '';
        const units = ['o', 'Ko', 'Mo', 'Go'];
        let size = bytes;
        let unit = 0;
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }
        return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
    }

    function formatDate(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleDateString(document.documentElement.lang || undefined);
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
        });

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }

        if (!response.ok) {
            throw new Error(extractError(data) || response.statusText);
        }

        return data;
    }

    function extractError(data) {
        if (!data) return '';
        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }
        if (data.error && data.error.message) {
            return data.error.message;
        }
        return data.message || '';
    }

    /* ---------- Popup d'aperçu ---------- */

    function getDialog() {
        if (dialog) return dialog;

        const title = el('div', {class: 'afd-dialog-title'});
        const openLink = el('a', {class: 'afd-dialog-action', target: '_blank', rel: 'noopener', title: t('open_in_tab'), 'aria-label': t('open_in_tab')}, [icon('export')]);
        const downloadLink = el('a', {class: 'afd-dialog-action', title: t('download'), 'aria-label': t('download')}, [icon('download')]);
        const closeButton = el('button', {type: 'button', class: 'afd-dialog-action', title: t('close'), 'aria-label': t('close')}, [icon('close')]);
        const frame = el('iframe', {class: 'afd-dialog-frame', title: t('preview')});

        dialog = el('dialog', {class: 'afd-dialog'}, [
            el('div', {class: 'afd-dialog-header'}, [title, openLink, downloadLink, closeButton]),
            el('div', {class: 'afd-dialog-body'}, [frame]),
        ]);

        closeButton.addEventListener('click', () => dialog.close());
        // Fermeture au clic sur le fond
        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
            frame.removeAttribute('src');
            document.body.classList.remove('afd-dialog-open');
        });

        dialog.afd = {title, openLink, downloadLink, frame};
        document.body.append(dialog);
        return dialog;
    }

    function openPreview(file) {
        const dlg = getDialog();
        dlg.afd.title.textContent = file.name;
        dlg.afd.openLink.href = file.view_url;
        dlg.afd.downloadLink.href = file.download_url;
        dlg.afd.frame.src = file.view_url;
        document.body.classList.add('afd-dialog-open');
        dlg.showModal();
    }

    /* ---------- Bloc de fichiers ---------- */

    class FilesBlock {
        constructor(root) {
            this.root = root;
            this.listUrl = root.dataset.listUrl;
            this.maxSizeMb = parseInt(root.dataset.maxSize || '0', 10);
            this.list = root.querySelector('[data-afd-list]');
            this.upload = root.querySelector('[data-afd-upload]');
            this.input = root.querySelector('[data-afd-input]');
            this.progress = root.querySelector('[data-afd-progress]');
            this.canUpload = false;
            this.files = [];

            this.bindUpload();
            this.load();
        }

        async load() {
            try {
                const data = await request(this.listUrl);
                this.canUpload = Boolean(data.can_upload);
                this.files = data.files || [];
                this.render();
            } catch (error) {
                this.root.hidden = false;
                this.list.replaceChildren(el('p', {class: 'text-muted text-small', text: t('load_error')}));
            }
        }

        render() {
            // Bloc masqué pour un lecteur quand il n'y a rien à afficher
            this.root.hidden = !this.canUpload && this.files.length === 0;
            this.upload.hidden = !this.canUpload;

            if (this.files.length === 0) {
                this.list.replaceChildren(el('p', {class: 'text-muted text-small', text: t('empty')}));
                return;
            }

            this.list.replaceChildren(...this.files.map(file => this.renderFile(file)));
        }

        renderFile(file) {
            const isImage = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp'].includes(file.extension);
            const meta = [formatSize(file.size), file.extension.toUpperCase()].filter(Boolean).join(' · ');
            const tooltip = file.created_by ? t('added_by', {user: file.created_by, date: formatDate(file.created_at)}) : '';

            const main = el(file.previewable ? 'button' : 'a', {
                class: 'afd-file-main',
                title: tooltip,
                ...(file.previewable ? {type: 'button'} : {href: file.download_url}),
            }, [
                el('span', {class: 'afd-file-icon'}, [icon(isImage ? 'image' : 'file')]),
                el('span', {class: 'afd-file-text'}, [
                    el('span', {class: 'afd-file-name', text: file.name}),
                    el('span', {class: 'afd-file-meta', text: meta}),
                ]),
            ]);

            if (file.previewable) {
                main.addEventListener('click', () => openPreview(file));
            }

            const actions = [
                el('a', {class: 'afd-file-action', href: file.download_url, title: t('download'), 'aria-label': t('download')}, [icon('download')]),
            ];

            if (file.can_delete) {
                actions.push(el('button', {
                    type: 'button',
                    class: 'afd-file-action afd-file-delete',
                    title: t('delete'),
                    'aria-label': t('delete'),
                    onclick: () => this.deleteFile(file),
                }, [icon('delete')]));
            }

            return el('div', {class: 'afd-file'}, [main, el('div', {class: 'afd-file-actions'}, actions)]);
        }

        bindUpload() {
            if (!this.input) return;

            this.input.addEventListener('change', () => {
                this.uploadFiles(Array.from(this.input.files || []));
                this.input.value = '';
            });

            const zone = this.upload.querySelector('.afd-dropzone');
            zone.addEventListener('dragover', event => {
                event.preventDefault();
                zone.classList.add('afd-dropzone-active');
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('afd-dropzone-active'));
            zone.addEventListener('drop', event => {
                event.preventDefault();
                zone.classList.remove('afd-dropzone-active');
                this.uploadFiles(Array.from(event.dataTransfer?.files || []));
            });
        }

        async uploadFiles(files) {
            for (const file of files) {
                if (this.maxSizeMb && file.size > this.maxSizeMb * 1024 * 1024) {
                    notify('error', t('too_large', {name: file.name, size: this.maxSizeMb}));
                    continue;
                }

                this.progress.textContent = t('uploading', {name: file.name});
                const body = new FormData();
                body.append('file', file);

                try {
                    const created = await request(this.listUrl, {method: 'POST', body});
                    this.files.push(created);
                    this.render();
                    notify('success', t('uploaded', {name: file.name}));
                } catch (error) {
                    notify('error', t('upload_error', {name: file.name, error: error.message}));
                }
            }

            this.progress.textContent = '';
        }

        async deleteFile(file) {
            if (!window.confirm(t('delete_confirm', {name: file.name}))) {
                return;
            }

            try {
                const data = await request(file.download_url, {method: 'DELETE'});
                this.files = this.files.filter(f => f.id !== file.id);
                this.render();
                notify('success', data?.message || t('deleted'));
            } catch (error) {
                notify('error', error.message);
            }
        }
    }

    function init() {
        document.querySelectorAll('[data-afd-files]').forEach(root => {
            if (!root.afdBlock) {
                root.afdBlock = new FilesBlock(root);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
