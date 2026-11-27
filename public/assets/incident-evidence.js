/* Incident evidence selection preview and limits. Server-side validation remains authoritative. */
(function () {
    'use strict';
    const MB = 1024 * 1024;
    const max = { images: 10, imageBytes: 10 * MB, videos: 3, videoBytes: 15 * MB };

    document.querySelectorAll('[data-incident-evidence]').forEach(function (container) {
        const photos = container.querySelector('input[name="images[]"]');
        const videos = container.querySelector('input[name="videos[]"]');
        const feedback = container.querySelector('[data-evidence-feedback]');
        const preview = container.querySelector('[data-evidence-previews]');
        const form = container.closest('form');
        if (!photos || !videos || !feedback || !preview || !form) return;

        const existing = {
            images: Number(container.dataset.existingImages || 0),
            imageBytes: Number(container.dataset.existingImageBytes || 0),
            videos: Number(container.dataset.existingVideos || 0),
            videoBytes: Number(container.dataset.existingVideoBytes || 0)
        };

        let objectUrls = [];
        function formatBytes(size) {
            return (size / MB).toFixed(2).replace(/\.00$/, '') + ' MB';
        }
        function releasePreviews() {
            objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
            objectUrls = [];
            preview.replaceChildren();
        }
        function selectedFiles(input) {
            return Array.from(input.files || []);
        }
        function refresh() {
            releasePreviews();
            const photoFiles = selectedFiles(photos);
            const videoFiles = selectedFiles(videos);
            const imageBytes = photoFiles.reduce(function (n, f) { return n + f.size; }, existing.imageBytes);
            const videoBytes = videoFiles.reduce(function (n, f) { return n + f.size; }, existing.videoBytes);
            const imageCount = photoFiles.length + existing.images;
            const videoCount = videoFiles.length + existing.videos;
            const errors = [];
            if (imageCount > max.images) errors.push('Maximum of 10 photos per incident.');
            if (imageBytes > max.imageBytes) errors.push('All photos together must not exceed 10 MB.');
            if (videoCount > max.videos) errors.push('Maximum of 3 video clips per incident.');
            if (videoBytes > max.videoBytes) errors.push('All videos together must not exceed 15 MB.');

            feedback.classList.toggle('is-error', errors.length > 0);
            feedback.textContent = errors.length
                ? errors.join(' ')
                : photoFiles.length || videoFiles.length
                ? imageCount + '/10 photos (' + formatBytes(imageBytes) + ' / 10 MB) · '
                  + videoCount + '/3 videos (' + formatBytes(videoBytes) + ' / 15 MB)'
                : 'Maximum: 10 photos (10 MB total) and 3 videos (15 MB total).';

            photoFiles.forEach(function (file) {
                const tile = document.createElement('div');
                tile.className = 'evidence-preview-item';
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    const url = URL.createObjectURL(file);
                    objectUrls.push(url);
                    img.src = url;
                    img.alt = 'Selected photo preview';
                    tile.appendChild(img);
                }
                const name = document.createElement('span');
                name.textContent = file.name + ' · ' + formatBytes(file.size);
                tile.appendChild(name);
                preview.appendChild(tile);
            });
            videoFiles.forEach(function (file) {
                const tile = document.createElement('div');
                tile.className = 'evidence-preview-item evidence-video-item';
                const icon = document.createElement('span');
                icon.textContent = 'Video';
                icon.className = 'evidence-video-label';
                const name = document.createElement('span');
                name.textContent = file.name + ' · ' + formatBytes(file.size);
                tile.append(icon, name);
                preview.appendChild(tile);
            });
            return errors.length === 0;
        }

        photos.addEventListener('change', refresh);
        videos.addEventListener('change', refresh);
        form.addEventListener('submit', function (event) {
            if (!refresh()) {
                event.preventDefault();
                feedback.focus();
                feedback.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });
        feedback.setAttribute('tabindex', '-1');
        refresh();
        window.addEventListener('pagehide', releasePreviews, { once: true });
    });
}());
