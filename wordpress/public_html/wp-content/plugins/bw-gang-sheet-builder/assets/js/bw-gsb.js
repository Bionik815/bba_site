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
        var dpiGood = Number(config.dpiGood || 250);
        var dpiOk = Number(config.dpiOk || 150);
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
        var duplicateButton = form.querySelector('[data-bw-gsb-duplicate]');
        var itemPanel = form.querySelector('[data-bw-gsb-item-panel]');
        var itemPanelName = form.querySelector('[data-bw-gsb-item-name]');
        var itemPanelDpi = form.querySelector('[data-bw-gsb-item-dpi]');
        var itemPanelWidth = form.querySelector('[data-bw-gsb-item-width]');
        var itemPanelHeight = form.querySelector('[data-bw-gsb-item-height]');
        var itemPanelRotation = form.querySelector('[data-bw-gsb-item-rotation]');
        var itemPanelWarnings = form.querySelector('[data-bw-gsb-item-warnings]');

        var MIN_ITEM_INCHES = 0.5;

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

        function effectiveDpi(item) {
            if (!item || !item.naturalWidth || !item.naturalHeight || item.width <= 0 || item.height <= 0) {
                return 0;
            }

            return Math.round(Math.min(item.naturalWidth / item.width, item.naturalHeight / item.height));
        }

        function dpiLevel(dpi) {
            if (dpi <= 0) {
                return 'unknown';
            }

            if (dpi >= dpiGood) {
                return 'good';
            }

            if (dpi >= dpiOk) {
                return 'ok';
            }

            return 'low';
        }

        function dpiLabel(dpi) {
            var level = dpiLevel(dpi);
            if (level === 'good') {
                return dpi + ' DPI - ' + (config.messages.dpiGood || 'Print ready');
            }

            if (level === 'ok') {
                return dpi + ' DPI - ' + (config.messages.dpiOk || 'Acceptable');
            }

            if (level === 'low') {
                return dpi + ' DPI - ' + (config.messages.dpiLow || 'Too low, will print blurry');
            }

            return config.messages.dpiUnknown || 'DPI unknown';
        }

        // Half-extents of the axis-aligned box that encloses the item after rotation.
        function rotatedHalfExtents(item) {
            var radians = (item.rotation || 0) * Math.PI / 180;
            var cos = Math.abs(Math.cos(radians));
            var sin = Math.abs(Math.sin(radians));
            return {
                x: (item.width * cos + item.height * sin) / 2,
                y: (item.width * sin + item.height * cos) / 2
            };
        }

        function itemBounds(item) {
            var half = rotatedHalfExtents(item);
            var centerX = item.x + item.width / 2;
            var centerY = item.y + item.height / 2;
            return {
                left: centerX - half.x,
                right: centerX + half.x,
                top: centerY - half.y,
                bottom: centerY + half.y
            };
        }

        function isOffSheet(item) {
            if (!state.sheet) {
                return false;
            }

            var bounds = itemBounds(item);
            var epsilon = 0.01;
            return bounds.left < -epsilon || bounds.top < -epsilon || bounds.right > state.sheet.width + epsilon || bounds.bottom > state.sheet.height + epsilon;
        }

        function boundsOverlap(a, b) {
            return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
        }

        function computeWarnings() {
            var boundsById = {};
            state.items.forEach(function (item) {
                boundsById[item.id] = itemBounds(item);
                item.offSheet = isOffSheet(item);
                item.overlapping = false;
            });

            for (var i = 0; i < state.items.length; i += 1) {
                for (var j = i + 1; j < state.items.length; j += 1) {
                    if (boundsOverlap(boundsById[state.items[i].id], boundsById[state.items[j].id])) {
                        state.items[i].overlapping = true;
                        state.items[j].overlapping = true;
                    }
                }
            }
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
                        z_index: item.zIndex,
                        source_width_px: item.naturalWidth || 0,
                        source_height_px: item.naturalHeight || 0,
                        effective_dpi: effectiveDpi(item),
                        off_sheet: !!item.offSheet,
                        overlapping: !!item.overlapping
                    };
                }),
                builder_version: config.version || '0.3.0'
            });
        }

        function updateCanvasEmpty() {
            if (!canvasEmpty) {
                return;
            }

            canvasEmpty.classList.toggle('is-hidden', state.items.length > 0);
        }

        function canvasScale() {
            if (!canvas || !state.sheet || !state.sheet.width) {
                return 1;
            }

            return (canvas.clientWidth || 1) / state.sheet.width;
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

            computeWarnings();

            Array.prototype.slice.call(canvas.querySelectorAll('.bw-gsb-canvas-item')).forEach(function (node) {
                node.remove();
            });

            var scale = canvasScale();

            state.items.forEach(function (item) {
                var dpi = effectiveDpi(item);
                var level = dpiLevel(dpi);
                var classes = 'bw-gsb-canvas-item';
                if (item.id === state.activeId) {
                    classes += ' is-selected';
                }
                if (level === 'low') {
                    classes += ' is-low-dpi';
                }
                if (item.overlapping || item.offSheet) {
                    classes += ' is-warning';
                }

                var node = document.createElement('div');
                node.className = classes;
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
                image.draggable = false;

                var measurement = document.createElement('div');
                measurement.className = 'bw-gsb-canvas-item-measurement';
                measurement.textContent = formatInches(item.width) + ' x ' + formatInches(item.height) + (dpi > 0 ? ' | ' + dpi + ' DPI' : '');

                node.appendChild(image);
                node.appendChild(measurement);

                if (item.id === state.activeId) {
                    var handle = document.createElement('div');
                    handle.className = 'bw-gsb-resize-handle';
                    handle.dataset.resizeHandle = '1';
                    node.appendChild(handle);

                    var rotateHandle = document.createElement('div');
                    rotateHandle.className = 'bw-gsb-rotate-handle';
                    rotateHandle.dataset.rotateHandle = '1';
                    rotateHandle.title = 'Drag to rotate (hold Shift to snap to 15°)';
                    node.appendChild(rotateHandle);
                }

                canvas.appendChild(node);
            });

            updateCanvasEmpty();
            updateItemPanel();
            serializeState();
        }

        function panelInputActive() {
            var active = document.activeElement;
            return active && (active === itemPanelWidth || active === itemPanelHeight || active === itemPanelRotation);
        }

        function updateItemPanel() {
            if (!itemPanel) {
                return;
            }

            var item = activeItem();
            if (!item) {
                itemPanel.hidden = true;
                return;
            }

            itemPanel.hidden = false;

            if (itemPanelName) {
                itemPanelName.textContent = item.label;
            }

            var dpi = effectiveDpi(item);
            if (itemPanelDpi) {
                itemPanelDpi.textContent = dpiLabel(dpi);
                itemPanelDpi.className = 'bw-gsb-dpi-badge is-' + dpiLevel(dpi);
            }

            if (!panelInputActive()) {
                if (itemPanelWidth) {
                    itemPanelWidth.value = item.width.toFixed(2);
                }
                if (itemPanelHeight) {
                    itemPanelHeight.value = item.height.toFixed(2);
                }
                if (itemPanelRotation) {
                    itemPanelRotation.value = Math.round(item.rotation);
                }
            }

            if (itemPanelWarnings) {
                var warnings = [];
                if (item.offSheet) {
                    warnings.push(config.messages.warnOffSheet || 'This artwork extends past the sheet edge and would be cut off.');
                }
                if (item.overlapping) {
                    warnings.push(config.messages.warnOverlap || 'This artwork overlaps another design on the sheet.');
                }
                itemPanelWarnings.hidden = warnings.length === 0;
                itemPanelWarnings.textContent = warnings.join(' ');
            }
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

                var pixels = document.createElement('p');
                pixels.className = 'bw-gsb-upload-pixels';
                pixels.textContent = upload.width + ' x ' + upload.height + ' px';

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'bw-gsb-tool-button';
                button.textContent = 'Add To Sheet';
                button.addEventListener('click', function () {
                    addUploadToCanvas(upload.id);
                });

                card.appendChild(preview);
                card.appendChild(meta);
                card.appendChild(pixels);
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

            // Default placement: natural print size at 300 DPI, capped to fit the sheet.
            var width = upload.width > 0 ? upload.width / 300 : state.sheet.width * 0.3;
            width = clamp(width, MIN_ITEM_INCHES, state.sheet.width * 0.9);
            var height = width * ratio;
            if (height > state.sheet.height * 0.9) {
                height = state.sheet.height * 0.9;
                width = height / Math.max(ratio, 0.01);
            }

            var item = {
                id: nextId('item'),
                uploadId: upload.id,
                fileName: upload.name,
                label: upload.name,
                url: upload.url,
                naturalWidth: upload.width,
                naturalHeight: upload.height,
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

        function duplicateSelected() {
            var item = activeItem();
            if (!item || !state.sheet) {
                return;
            }

            var copy = {};
            Object.keys(item).forEach(function (key) {
                copy[key] = item[key];
            });
            copy.id = nextId('item');
            copy.zIndex = state.items.length + 1;
            copy.x = clamp(item.x + 0.5, 0, Math.max(0, state.sheet.width - item.width));
            copy.y = clamp(item.y + 0.5, 0, Math.max(0, state.sheet.height - item.height));

            state.items.push(copy);
            state.activeId = copy.id;
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

        function itemCenterOnScreen(item) {
            var scale = canvasScale();
            var rect = canvas.getBoundingClientRect();
            return {
                x: rect.left + (item.x + item.width / 2) * scale,
                y: rect.top + (item.y + item.height / 2) * scale
            };
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

            var mode = 'move';
            if (event.target.dataset.resizeHandle) {
                mode = 'resize';
            } else if (event.target.dataset.rotateHandle) {
                mode = 'rotate';
            }

            var start = pointerPoint(event);
            var startItem = {
                x: item.x,
                y: item.y,
                width: item.width,
                height: item.height,
                rotation: item.rotation
            };
            var scale = canvasScale();
            var center = itemCenterOnScreen(item);

            function moveHandler(moveEvent) {
                var point = pointerPoint(moveEvent);

                if (mode === 'rotate') {
                    var angle = Math.atan2(point.y - center.y, point.x - center.x) * 180 / Math.PI + 90;
                    if (moveEvent.shiftKey) {
                        angle = Math.round(angle / 15) * 15;
                    }
                    item.rotation = ((Math.round(angle) % 360) + 360) % 360;
                } else if (mode === 'move') {
                    var deltaX = (point.x - start.x) / scale;
                    var deltaY = (point.y - start.y) / scale;
                    item.x = clamp(startItem.x + deltaX, 0, Math.max(0, state.sheet.width - item.width));
                    item.y = clamp(startItem.y + deltaY, 0, Math.max(0, state.sheet.height - item.height));
                } else {
                    var resizeDeltaX = (point.x - start.x) / scale;
                    var nextWidth = clamp(startItem.width + resizeDeltaX, MIN_ITEM_INCHES, state.sheet.width);
                    var ratio = startItem.height / Math.max(startItem.width, 0.01);
                    item.width = nextWidth;
                    item.height = clamp(nextWidth * ratio, MIN_ITEM_INCHES, state.sheet.height);
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

        function setSelectedSize(width, height) {
            var item = activeItem();
            if (!item || !state.sheet) {
                return;
            }

            var ratio = item.naturalWidth > 0 && item.naturalHeight > 0
                ? item.naturalHeight / item.naturalWidth
                : item.height / Math.max(item.width, 0.01);

            var nextWidth;
            var nextHeight;

            if (width !== null) {
                nextWidth = clamp(Number(width), MIN_ITEM_INCHES, state.sheet.width);
                nextHeight = nextWidth * ratio;
            } else {
                nextHeight = clamp(Number(height), MIN_ITEM_INCHES, state.sheet.height);
                nextWidth = nextHeight / Math.max(ratio, 0.01);
            }

            if (nextHeight > state.sheet.height) {
                nextHeight = state.sheet.height;
                nextWidth = nextHeight / Math.max(ratio, 0.01);
            }
            if (nextWidth > state.sheet.width) {
                nextWidth = state.sheet.width;
                nextHeight = nextWidth * ratio;
            }

            item.width = Number(nextWidth.toFixed(2));
            item.height = Number(nextHeight.toFixed(2));
            item.x = clamp(item.x, 0, Math.max(0, state.sheet.width - item.width));
            item.y = clamp(item.y, 0, Math.max(0, state.sheet.height - item.height));
            renderItems();
        }

        function applyWidthPreset(width) {
            setSelectedSize(width, null);
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

        function isTypingTarget(target) {
            if (!target) {
                return false;
            }

            var tag = (target.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
        }

        function handleKeydown(event) {
            var item = activeItem();
            if (!item || isTypingTarget(event.target)) {
                return;
            }

            var step = event.shiftKey ? 0.5 : 0.1;
            var handled = true;

            switch (event.key) {
                case 'ArrowLeft':
                    item.x = clamp(item.x - step, 0, Math.max(0, state.sheet.width - item.width));
                    break;
                case 'ArrowRight':
                    item.x = clamp(item.x + step, 0, Math.max(0, state.sheet.width - item.width));
                    break;
                case 'ArrowUp':
                    item.y = clamp(item.y - step, 0, Math.max(0, state.sheet.height - item.height));
                    break;
                case 'ArrowDown':
                    item.y = clamp(item.y + step, 0, Math.max(0, state.sheet.height - item.height));
                    break;
                case 'Delete':
                case 'Backspace':
                    removeSelected();
                    return;
                default:
                    handled = false;
            }

            if (handled) {
                event.preventDefault();
                renderItems();
            }
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

        if (duplicateButton) {
            duplicateButton.addEventListener('click', duplicateSelected);
        }

        if (itemPanelWidth) {
            itemPanelWidth.addEventListener('input', function () {
                if (itemPanelWidth.value !== '' && Number(itemPanelWidth.value) > 0) {
                    setSelectedSize(Number(itemPanelWidth.value), null);
                }
            });
        }

        if (itemPanelHeight) {
            itemPanelHeight.addEventListener('input', function () {
                if (itemPanelHeight.value !== '' && Number(itemPanelHeight.value) > 0) {
                    setSelectedSize(null, Number(itemPanelHeight.value));
                }
            });
        }

        if (itemPanelRotation) {
            itemPanelRotation.addEventListener('input', function () {
                var item = activeItem();
                if (!item || itemPanelRotation.value === '') {
                    return;
                }

                item.rotation = ((Math.round(Number(itemPanelRotation.value)) % 360) + 360) % 360;
                renderItems();
            });
        }

        // Enter inside builder inputs should not submit the whole form.
        [itemPanelWidth, itemPanelHeight, itemPanelRotation].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    input.blur();
                }
            });
        });

        document.addEventListener('keydown', handleKeydown);

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
