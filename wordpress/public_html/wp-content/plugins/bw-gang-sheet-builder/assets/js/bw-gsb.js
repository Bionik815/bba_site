(function () {
    function money(value, symbol) {
        return (symbol || '$') + Number(value || 0).toFixed(2);
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }

        fn();
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    onReady(function () {
        var adminPreviewOpen = document.querySelector('[data-bw-gsb-preview-open]');
        var adminPreviewModal = document.querySelector('[data-bw-gsb-preview-modal]');
        var adminPreviewClose = document.querySelectorAll('[data-bw-gsb-preview-close]');

        if (adminPreviewOpen && adminPreviewModal) {
            adminPreviewOpen.addEventListener('click', function () {
                adminPreviewModal.hidden = false;
                document.body.classList.add('bw-gsb-modal-open');
            });

            adminPreviewClose.forEach(function (button) {
                button.addEventListener('click', function () {
                    adminPreviewModal.hidden = true;
                    document.body.classList.remove('bw-gsb-modal-open');
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !adminPreviewModal.hidden) {
                    adminPreviewModal.hidden = true;
                    document.body.classList.remove('bw-gsb-modal-open');
                }
            });
        }

        var form = document.querySelector('.bw-gsb-form');
        if (!form || typeof window.BWGangSheetBuilder !== 'object') {
            return;
        }

        var config = window.BWGangSheetBuilder;
        var sheetSelect = form.querySelector('[data-bw-gsb-sheet-select]');
        var priceEl = form.querySelector('[data-bw-gsb-price]');
        var labelEl = form.querySelector('[data-bw-gsb-sheet-label]');
        var dimensionsEl = form.querySelector('[data-bw-gsb-sheet-dimensions]');
        var fileInput = form.querySelector('[data-bw-gsb-files]');
        var fileSummary = form.querySelector('[data-bw-gsb-file-summary]');
        var layoutInput = form.querySelector('input[name="layout_json"]');
        var canvas = form.querySelector('[data-bw-gsb-canvas]');
        var canvasEmpty = form.querySelector('.bw-gsb-canvas-empty');
        var uploadList = form.querySelector('[data-bw-gsb-upload-list]');
        var rotateButtons = form.querySelectorAll('[data-bw-gsb-rotate]');
        var widthPresetButtons = form.querySelectorAll('[data-bw-gsb-width-preset]');
        var removeButton = form.querySelector('[data-bw-gsb-remove]');

        var state = {
            sheet: null,
            uploads: [],
            items: [],
            activeId: null
        };

        function activeItem() {
            for (var i = 0; i < state.items.length; i += 1) {
                if (state.items[i].id === state.activeId) {
                    return state.items[i];
                }
            }

            return null;
        }

        function serializeState() {
            if (!layoutInput) {
                return;
            }

            layoutInput.value = JSON.stringify({
                sheet: state.sheet,
                items: state.items.map(function (item) {
                    return {
                        id: item.id,
                        upload_id: item.uploadId,
                        file_name: item.fileName,
                        label: item.label,
                        x: Number(item.x.toFixed(2)),
                        y: Number(item.y.toFixed(2)),
                        width: Number(item.width.toFixed(2)),
                        height: Number(item.height.toFixed(2)),
                        rotation: Number(item.rotation.toFixed(2)),
                        z_index: item.zIndex
                    };
                }),
                builder_version: '0.1.0'
            });
        }

        function updateCanvasEmpty() {
            if (!canvasEmpty) {
                return;
            }

            canvasEmpty.classList.toggle('is-hidden', state.items.length > 0);
        }

        function canvasScale() {
            if (!canvas || !state.sheet) {
                return 1;
            }

            return canvas.clientWidth / state.sheet.width;
        }

        function sizeCanvas() {
            if (!canvas || !state.sheet) {
                return;
            }

            var width = canvas.clientWidth || 1;
            var height = width * (state.sheet.height / state.sheet.width);
            canvas.style.height = height + 'px';
        }

        function renderItems() {
            if (!canvas || !state.sheet) {
                return;
            }

            Array.prototype.slice.call(canvas.querySelectorAll('.bw-gsb-canvas-item')).forEach(function (node) {
                node.remove();
            });

            var scale = canvasScale();

            state.items.forEach(function (item) {
                var node = document.createElement('div');
                node.className = 'bw-gsb-canvas-item' + (item.id === state.activeId ? ' is-selected' : '');
                node.dataset.itemId = item.id;
                node.style.left = (item.x * scale) + 'px';
                node.style.top = (item.y * scale) + 'px';
                node.style.width = (item.width * scale) + 'px';
                node.style.height = (item.height * scale) + 'px';
                node.style.transform = 'rotate(' + item.rotation + 'deg)';
                node.style.zIndex = item.zIndex;

                var image = document.createElement('img');
                image.src = item.url;
                image.alt = item.label;

                var measurement = document.createElement('div');
                measurement.className = 'bw-gsb-canvas-item-measurement';
                measurement.textContent = formatInches(item.width) + ' x ' + formatInches(item.height);

                var handle = document.createElement('div');
                handle.className = 'bw-gsb-resize-handle';
                handle.dataset.resizeHandle = '1';

                node.appendChild(image);
                node.appendChild(measurement);
                node.appendChild(handle);
                canvas.appendChild(node);
            });

            updateCanvasEmpty();
            serializeState();
        }

        function updateSheetMeta() {
            if (!sheetSelect) {
                return;
            }

            var option = sheetSelect.options[sheetSelect.selectedIndex];
            if (!option) {
                return;
            }

            state.sheet = {
                code: sheetSelect.value,
                label: option.getAttribute('data-label') || option.textContent,
                width: Number(option.getAttribute('data-width') || 0),
                height: Number(option.getAttribute('data-height') || 0),
                price: Number(option.getAttribute('data-price') || 0)
            };

            if (labelEl) {
                labelEl.textContent = state.sheet.label;
            }

            if (priceEl) {
                priceEl.textContent = money(state.sheet.price, config.currencySymbol);
            }

            if (dimensionsEl) {
                dimensionsEl.textContent = state.sheet.width + '" x ' + state.sheet.height + '"';
            }

            sizeCanvas();
            renderItems();
        }

        function updateFileSummary() {
            if (!fileInput || !fileSummary) {
                return;
            }

            var count = state.uploads.length;
            if (!count) {
                fileSummary.textContent = 'No files selected yet.';
                return;
            }

            if (count === 1) {
                fileSummary.textContent = config.messages.uploadCountSingle;
                return;
            }

            fileSummary.textContent = count + ' ' + config.messages.uploadCountPlural;
        }

        function nextId(prefix) {
            return prefix + '_' + Math.random().toString(36).slice(2, 10);
        }

        function formatInches(value) {
            var amount = Number(value || 0);
            var rounded = Math.round(amount * 100) / 100;
            return rounded.toFixed(2).replace(/\.00$/, '') + 'in';
        }

        function setActive(id) {
            state.activeId = id;
            renderItems();
        }

        function renderUploadCards() {
            if (!uploadList) {
                return;
            }

            uploadList.innerHTML = '';

            state.uploads.forEach(function (upload) {
                var card = document.createElement('div');
                card.className = 'bw-gsb-upload-card';

                var preview = document.createElement('div');
                preview.className = 'bw-gsb-upload-preview';
                var img = document.createElement('img');
                img.src = upload.url;
                img.alt = upload.name;
                preview.appendChild(img);

                var meta = document.createElement('p');
                meta.textContent = upload.name;

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'bw-gsb-tool-button';
                button.textContent = 'Add To Sheet';
                button.addEventListener('click', function () {
                    addUploadToCanvas(upload.id);
                });

                card.appendChild(preview);
                card.appendChild(meta);
                card.appendChild(button);
                uploadList.appendChild(card);
            });
        }

        function syncInputFiles() {
            if (!fileInput || typeof DataTransfer === 'undefined') {
                return;
            }

            var transfer = new DataTransfer();
            state.uploads.forEach(function (upload) {
                if (upload.file) {
                    transfer.items.add(upload.file);
                }
            });
            fileInput.files = transfer.files;
        }

        function addUploadToCanvas(uploadId) {
            if (!state.sheet) {
                return;
            }

            var upload = state.uploads.find(function (entry) {
                return entry.id === uploadId;
            });
            if (!upload) {
                return;
            }

            var ratio = upload.width > 0 && upload.height > 0 ? (upload.height / upload.width) : 1;
            var width = Math.min(state.sheet.width * 0.3, Math.max(2, upload.width / 100));
            var height = width * ratio;
            if (height > state.sheet.height * 0.5) {
                height = state.sheet.height * 0.5;
                width = height / Math.max(ratio, 0.01);
            }

            var item = {
                id: nextId('item'),
                uploadId: upload.id,
                fileName: upload.name,
                label: upload.name,
                url: upload.url,
                x: 1,
                y: 1,
                width: Number(width.toFixed(2)),
                height: Number(height.toFixed(2)),
                rotation: 0,
                zIndex: state.items.length + 1
            };

            state.items.push(item);
            state.activeId = item.id;
            renderItems();
        }

        function readUploads() {
            if (!fileInput || !fileInput.files) {
                updateFileSummary();
                renderUploadCards();
                return;
            }

            Array.prototype.forEach.call(fileInput.files, function (file, index) {
                if (!file.type || file.type !== 'image/png') {
                    return;
                }

                var duplicate = state.uploads.some(function (entry) {
                    return entry.name === file.name && entry.file && entry.file.size === file.size && entry.file.lastModified === file.lastModified;
                });
                if (duplicate) {
                    return;
                }

                var url = URL.createObjectURL(file);
                var image = new Image();
                image.onload = function () {
                    state.uploads.push({
                        id: nextId('upload_' + index),
                        name: file.name,
                        file: file,
                        url: url,
                        width: image.naturalWidth || 0,
                        height: image.naturalHeight || 0
                    });
                    syncInputFiles();
                    updateFileSummary();
                    renderUploadCards();
                };
                image.src = url;
            });

            fileInput.value = '';
        }

        function pointerPoint(event) {
            if (event.touches && event.touches[0]) {
                return { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }

            return { x: event.clientX, y: event.clientY };
        }

        function handleCanvasPointerDown(event) {
            var itemNode = event.target.closest('.bw-gsb-canvas-item');
            if (!itemNode) {
                setActive(null);
                return;
            }

            var item = state.items.find(function (entry) {
                return entry.id === itemNode.dataset.itemId;
            });
            if (!item) {
                return;
            }

            setActive(item.id);

            var mode = event.target.dataset.resizeHandle ? 'resize' : 'move';
            var start = pointerPoint(event);
            var startItem = {
                x: item.x,
                y: item.y,
                width: item.width,
                height: item.height
            };
            var scale = canvasScale();

            function moveHandler(moveEvent) {
                var point = pointerPoint(moveEvent);
                var deltaX = (point.x - start.x) / scale;
                var deltaY = (point.y - start.y) / scale;

                if (mode === 'move') {
                    item.x = clamp(startItem.x + deltaX, 0, Math.max(0, state.sheet.width - item.width));
                    item.y = clamp(startItem.y + deltaY, 0, Math.max(0, state.sheet.height - item.height));
                } else {
                    var nextWidth = clamp(startItem.width + deltaX, 0.75, state.sheet.width);
                    var ratio = startItem.height / Math.max(startItem.width, 0.01);
                    item.width = nextWidth;
                    item.height = clamp(nextWidth * ratio, 0.75, state.sheet.height);
                    item.x = clamp(item.x, 0, Math.max(0, state.sheet.width - item.width));
                    item.y = clamp(item.y, 0, Math.max(0, state.sheet.height - item.height));
                }

                renderItems();
                moveEvent.preventDefault();
            }

            function upHandler() {
                window.removeEventListener('mousemove', moveHandler);
                window.removeEventListener('mouseup', upHandler);
                window.removeEventListener('touchmove', moveHandler);
                window.removeEventListener('touchend', upHandler);
            }

            window.addEventListener('mousemove', moveHandler);
            window.addEventListener('mouseup', upHandler);
            window.addEventListener('touchmove', moveHandler, { passive: false });
            window.addEventListener('touchend', upHandler);
            event.preventDefault();
        }

        function rotateSelected(delta) {
            var item = activeItem();
            if (!item) {
                return;
            }

            item.rotation = (item.rotation + delta + 360) % 360;
            renderItems();
        }

        function applyWidthPreset(width) {
            var item = activeItem();
            if (!item || !state.sheet) {
                return;
            }

            var targetWidth = Number(width || 0);
            if (targetWidth <= 0) {
                return;
            }

            var ratio = item.height / Math.max(item.width, 0.01);
            var nextWidth = clamp(targetWidth, 0.75, state.sheet.width);
            var nextHeight = Math.max(0.75, nextWidth * ratio);

            if (nextHeight > state.sheet.height) {
                nextHeight = state.sheet.height;
                nextWidth = nextHeight / Math.max(ratio, 0.01);
            }

            item.width = Number(nextWidth.toFixed(2));
            item.height = Number(nextHeight.toFixed(2));
            item.x = clamp(item.x, 0, Math.max(0, state.sheet.width - item.width));
            item.y = clamp(item.y, 0, Math.max(0, state.sheet.height - item.height));
            renderItems();
        }

        function removeSelected() {
            if (!state.activeId) {
                return;
            }

            state.items = state.items.filter(function (item) {
                return item.id !== state.activeId;
            });
            state.activeId = null;
            renderItems();
        }

        if (sheetSelect) {
            sheetSelect.addEventListener('change', updateSheetMeta);
        }

        if (fileInput) {
            fileInput.addEventListener('change', readUploads);
        }

        if (canvas) {
            canvas.addEventListener('mousedown', handleCanvasPointerDown);
            canvas.addEventListener('touchstart', handleCanvasPointerDown, { passive: true });
        }

        rotateButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                rotateSelected(Number(button.getAttribute('data-bw-gsb-rotate') || 0));
            });
        });

        widthPresetButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyWidthPreset(Number(button.getAttribute('data-bw-gsb-width-preset') || 0));
            });
        });

        if (removeButton) {
            removeButton.addEventListener('click', removeSelected);
        }

        window.addEventListener('resize', function () {
            sizeCanvas();
            renderItems();
        });

        updateSheetMeta();
        updateFileSummary();
        renderUploadCards();
        serializeState();
    });
}());
