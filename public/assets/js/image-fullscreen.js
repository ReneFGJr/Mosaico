const imageTrigger = document.querySelector('[data-image-fullscreen-open]');
const imageDialog = document.querySelector('[data-image-fullscreen]');

if (imageTrigger && imageDialog && typeof imageDialog.showModal === 'function') {
    let ownsFullscreen = false;
    const viewport = imageDialog.querySelector('[data-image-zoom-viewport]');
    const stage = imageDialog.querySelector('[data-image-zoom-stage]');
    const image = stage.querySelector('img');
    const zoomIn = imageDialog.querySelector('[data-image-zoom-in]');
    const zoomOut = imageDialog.querySelector('[data-image-zoom-out]');
    const zoomLevel = imageDialog.querySelector('[data-image-zoom-level]');
    let zoom = 1;
    let imageWidth = 0;
    let imageHeight = 0;

    const setZoom = (value, anchorX = viewport.clientWidth / 2, anchorY = viewport.clientHeight / 2) => {
        if (!imageDialog.open || !image.naturalWidth || !viewport.clientWidth || !viewport.clientHeight) return;
        const oldWidth = Math.max(viewport.clientWidth, imageWidth);
        const oldHeight = Math.max(viewport.clientHeight, imageHeight);
        const imageX = imageWidth ? (viewport.scrollLeft + anchorX - (oldWidth - imageWidth) / 2) / imageWidth : .5;
        const imageY = imageHeight ? (viewport.scrollTop + anchorY - (oldHeight - imageHeight) / 2) / imageHeight : .5;
        zoom = Math.min(5, Math.max(1, value));
        const fit = Math.min(viewport.clientWidth / image.naturalWidth, viewport.clientHeight / image.naturalHeight);
        imageWidth = image.naturalWidth * fit * zoom;
        imageHeight = image.naturalHeight * fit * zoom;
        const width = Math.max(viewport.clientWidth, imageWidth);
        const height = Math.max(viewport.clientHeight, imageHeight);
        stage.style.width = `${width}px`;
        stage.style.height = `${height}px`;
        image.style.width = `${imageWidth}px`;
        image.style.height = `${imageHeight}px`;
        viewport.scrollLeft = (width - imageWidth) / 2 + imageX * imageWidth - anchorX;
        viewport.scrollTop = (height - imageHeight) / 2 + imageY * imageHeight - anchorY;
        zoomLevel.value = `${Math.round(zoom * 100)}%`;
        zoomOut.disabled = zoom <= 1;
        zoomIn.disabled = zoom >= 5;
    };

    zoomIn.addEventListener('click', () => setZoom(zoom + .25));
    zoomOut.addEventListener('click', () => setZoom(zoom - .25));
    imageDialog.querySelector('[data-image-zoom-fit]').addEventListener('click', () => setZoom(1));
    viewport.addEventListener('wheel', (event) => {
        if (event.ctrlKey || event.metaKey || event.deltaY === 0) return;
        event.preventDefault();
        const bounds = viewport.getBoundingClientRect();
        setZoom(zoom * (event.deltaY < 0 ? 1.1 : 1 / 1.1), event.clientX - bounds.left, event.clientY - bounds.top);
    }, { passive: false });
    viewport.addEventListener('dblclick', (event) => {
        event.preventDefault();
        imageDialog.close();
    });
    imageDialog.addEventListener('keydown', (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        if (['+', '=', '-', '0'].includes(event.key)) {
            event.preventDefault();
            setZoom(event.key === '0' ? 1 : zoom + (event.key === '-' ? -.25 : .25));
        }
    });
    image.addEventListener('load', () => setZoom(zoom));
    new ResizeObserver(() => setZoom(zoom)).observe(viewport);
    imageTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        if (imageDialog.open) return;
        imageDialog.showModal();
        zoom = 1;
        imageWidth = imageHeight = 0;
        setZoom(1);
        document.body.classList.add('image-fullscreen-open');
        if (typeof document.documentElement.requestFullscreen === 'function' && !document.fullscreenElement) {
            document.documentElement.requestFullscreen().then(() => {
                ownsFullscreen = true;
                if (!imageDialog.open) {
                    ownsFullscreen = false;
                    document.exitFullscreen().catch(() => {});
                }
            }).catch(() => {
                // The dialog already fills the viewport when fullscreen is unavailable.
            });
        }
    });

    imageDialog.addEventListener('close', () => {
        document.body.classList.remove('image-fullscreen-open');
        if (ownsFullscreen && document.fullscreenElement === document.documentElement) {
            ownsFullscreen = false;
            document.exitFullscreen().catch(() => {});
        }
        imageTrigger.focus({ preventScroll: true });
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && ownsFullscreen) {
            ownsFullscreen = false;
            if (imageDialog.open) imageDialog.close();
        }
    });

    window.addEventListener('pageshow', () => {
        if (imageDialog.open) imageDialog.close();
        document.body.classList.remove('image-fullscreen-open');
    });
}
