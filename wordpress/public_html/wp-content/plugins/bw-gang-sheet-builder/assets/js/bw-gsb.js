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
        var autoArrangeButton = form.querySelector('[data-bw-gsb-auto-arrange]');
        var marginInput = form.querySelector('[data-bw-gsb-margin]');
        var addAllButton = form.querySelector('[data-bw-gsb-add-all]');
        var addTextButton = form.querySelector('[data-bw-gsb-add-text]');
        var textPanel = form.querySelector('[data-bw-gsb-text-panel]');
        var textInput = form.querySelector('[data-bw-gsb-text-input]');
        var textFont = form.querySelector('[data-bw-gsb-text-font]');
        var textSize = form.querySelector('[data-bw-gsb-text-size]');
        var textColor = form.querySelector('[data-bw-gsb-text-color]');
        var textOutline = form.querySelector('[data-bw-gsb-text-outline]');
        var textOutlineColor = form.querySelector('[data-bw-gsb-text-outline-color]');
        var textApply = form.querySelector('[data-bw-gsb-text-apply]');
        var textCancel = form.querySelector('[data-bw-gsb-text-cancel]');
        var saveDesignButton = form.querySelector('[data-bw-gsb-save-design]');
        var myDesignsButton = form.querySelector('[data-bw-gsb-my-designs]');
        var designsPanel = form.querySelector('[data-bw-gsb-designs-panel]');
        var designsList = form.querySelector('[data-bw-gsb-designs-list]');
        var designsClose = form.querySelector('[data-bw-gsb-designs-close]');
        var designSourceInput = form.querySelector('[data-bw-gsb-design-source]');
        var itemPanel = form.querySelector('[data-bw-gsb-item-panel]');
        var itemPanelName = form.querySelector('[data-bw-gsb-item-name]');
        var itemPanelDpi = form.querySelector('[data-bw-gsb-item-dpi]');
        var itemPanelWidth = form.querySelector('[data-bw-gsb-item-width]');
        var itemPanelHeight = form.querySelector('[data-bw-gsb-item-height]');
        var itemPanelRotation = form.querySelector('[data-bw-gsb-item-rotation]');
        var itemPanelWarnings = form.querySelector('[data-bw-gsb-item-warnings]');
        var buttonPrice = form.querySelector('[data-bw-gsb-button-price]');

        var MIN_ITEM_INCHES = 0.5;

        var state = {
            sheet: null,
            uploads: [],
            items: [],
            activeId: null,
            designId: 0,        // saved-design row being edited (0 = none)
            editingTextId: null // upload id while the text panel edits an existing text design
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

            if (buttonPrice) {
                buttonPrice.textContent = '- ' + money(state.sheet.price, config.currencySymbol);
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
                if (upload.noAlpha) {
                    var flag = document.createElement('p');
                    flag.className = 'bw-gsb-upload-flag';
                    flag.textContent = config.messages.jpegFlag || 'JPG: background prints white';
                    card.appendChild(flag);
                }
                if (upload.isText) {
                    var textFlag = document.createElement('p');
                    textFlag.className = 'bw-gsb-upload-flag is-text';
                    textFlag.textContent = config.messages.textFlag || 'Text design';
                    card.appendChild(textFlag);
                }
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

        function packMargin() {
            var value = marginInput ? Number(marginInput.value) : 0.125;
            if (!isFinite(value) || value < 0) {
                value = 0.125;
            }
            return Math.min(value, 2);
        }

        /**
         * Shelf-pack every item on the sheet: tallest first, rows left to
         * right, even spacing. Items that cannot fit stay where they were and
         * get reported.
         */
        function autoArrange() {
            if (!state.sheet || !state.items.length) {
                return;
            }

            var margin = packMargin();
            var sorted = state.items.slice().sort(function (a, b) {
                return rotatedHalfExtents(b).y - rotatedHalfExtents(a).y;
            });

            var cursorX = margin;
            var shelfY = margin;
            var shelfH = 0;
            var overflow = 0;
            var epsilon = 0.001;

            sorted.forEach(function (item) {
                var half = rotatedHalfExtents(item);
                var w = half.x * 2;
                var h = half.y * 2;

                if (cursorX + w > state.sheet.width - margin + epsilon) {
                    cursorX = margin;
                    shelfY += shelfH + margin;
                    shelfH = 0;
                }

                if (shelfY + h > state.sheet.height + epsilon || w > state.sheet.width - margin * 2 + epsilon) {
                    overflow += 1;
                    return;
                }

                // Place so the rotated bounding box's top-left sits at the cursor.
                item.x = cursorX + half.x - item.width / 2;
                item.y = shelfY + half.y - item.height / 2;

                cursorX += w + margin;
                shelfH = Math.max(shelfH, h);
            });

            renderItems();

            if (overflow > 0) {
                window.alert(overflow + ' ' + (config.messages.autoArrangeOverflow || 'design(s) did not fit on this sheet size. Pick a bigger sheet or shrink them.'));
            }
        }

        function addAllToSheet() {
            if (!state.uploads.length) {
                window.alert(config.messages.needUploads || 'Upload artwork files first.');
                return;
            }

            state.uploads.forEach(function (upload) {
                var placed = state.items.some(function (item) {
                    return item.uploadId === upload.id;
                });
                if (!placed) {
                    addUploadToCanvas(upload.id);
                }
            });

            autoArrange();
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

        var CONVERTIBLE_TYPES = {
            'image/png': 'png',
            'image/jpeg': 'jpeg',
            'image/webp': 'webp',
            'image/svg+xml': 'svg'
        };

        function pngName(name) {
            return String(name || 'artwork').replace(/\.(png|jpe?g|webp|svg)$/i, '') + '.png';
        }

        function registerUpload(entry) {
            state.uploads.push(entry);
            syncInputFiles();
            updateFileSummary();
            renderUploadCards();
        }

        /**
         * Everything becomes PNG in the browser so the server pipeline
         * (upload validation, print-file generation) stays PNG-only.
         */
        function ingestFile(file) {
            var kind = CONVERTIBLE_TYPES[file.type];
            if (!kind) {
                return;
            }

            var duplicate = state.uploads.some(function (entry) {
                return entry.name === pngName(file.name) && entry.file && entry.file.size === file.size;
            });
            if (duplicate) {
                return;
            }

            var url = URL.createObjectURL(file);
            var image = new Image();

            image.onload = function () {
                if (kind === 'png') {
                    registerUpload({
                        id: nextId('upload'),
                        name: file.name,
                        file: file,
                        url: url,
                        width: image.naturalWidth || 0,
                        height: image.naturalHeight || 0
                    });
                    return;
                }

                // Rasterize / re-encode to PNG. SVGs get a high-res raster so
                // they stay sharp at large print sizes.
                var width = image.naturalWidth || 0;
                var height = image.naturalHeight || 0;
                if (kind === 'svg') {
                    var maxSide = Math.max(width, height, 1);
                    var target = 3000; // ~10in at 300 DPI
                    var factor = width > 0 ? Math.min(4, Math.max(1, target / maxSide)) : 1;
                    if (width === 0 || height === 0) {
                        width = 2048;
                        height = 2048;
                        factor = 1;
                    }
                    width = Math.round(width * factor);
                    height = Math.round(height * factor);
                }

                var raster = document.createElement('canvas');
                raster.width = Math.max(1, width);
                raster.height = Math.max(1, height);
                var ctx = raster.getContext('2d');
                ctx.drawImage(image, 0, 0, raster.width, raster.height);

                raster.toBlob(function (blob) {
                    if (!blob) {
                        return;
                    }
                    var converted = new File([blob], pngName(file.name), { type: 'image/png' });
                    var convertedUrl = URL.createObjectURL(blob);
                    URL.revokeObjectURL(url);
                    registerUpload({
                        id: nextId('upload'),
                        name: converted.name,
                        file: converted,
                        url: convertedUrl,
                        width: raster.width,
                        height: raster.height,
                        noAlpha: kind === 'jpeg'
                    });
                }, 'image/png');
            };

            image.onerror = function () {
                URL.revokeObjectURL(url);
                window.alert((config.messages.badFile || 'Could not read this file:') + ' ' + file.name);
            };

            image.src = url;
        }

        function readUploads() {
            if (!fileInput || !fileInput.files) {
                updateFileSummary();
                renderUploadCards();
                return;
            }

            Array.prototype.forEach.call(fileInput.files, ingestFile);
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

        /* ---------- Text designs (rendered to PNG client-side) ---------- */

        var TEXT_DPI = 300;

        function openTextPanel(uploadId) {
            if (!textPanel) {
                return;
            }

            state.editingTextId = uploadId || null;
            var upload = uploadId ? state.uploads.find(function (u) { return u.id === uploadId; }) : null;
            var meta = upload && upload.textMeta ? upload.textMeta : null;

            if (textInput) textInput.value = meta ? meta.text : '';
            if (textFont && meta) textFont.value = meta.font;
            if (textSize) textSize.value = meta ? meta.heightIn : 2;
            if (textColor) textColor.value = meta ? meta.color : '#000000';
            if (textOutline) textOutline.value = meta ? String(meta.outline) : '0';
            if (textOutlineColor) textOutlineColor.value = meta ? meta.outlineColor : '#ffffff';

            if (textApply) {
                textApply.textContent = meta ? (config.messages.textUpdate || 'Update Text') : (config.messages.textAdd || 'Add To Sheet');
            }

            textPanel.hidden = false;
            if (textInput) textInput.focus();
        }

        function closeTextPanel() {
            if (textPanel) {
                textPanel.hidden = true;
            }
            state.editingTextId = null;
        }

        function renderTextPng(meta, done) {
            var fontPx = Math.round(meta.heightIn * TEXT_DPI);
            var probe = document.createElement('canvas').getContext('2d');
            probe.font = '900 ' + fontPx + 'px ' + meta.font;
            var textWidth = Math.ceil(probe.measureText(meta.text).width);

            var outlinePx = Math.round(meta.outline * fontPx);
            var pad = Math.max(outlinePx * 2, Math.round(fontPx * 0.12));

            var canvasEl = document.createElement('canvas');
            canvasEl.width = Math.max(1, textWidth + pad * 2);
            canvasEl.height = Math.max(1, Math.round(fontPx * 1.3) + pad * 2);
            var ctx = canvasEl.getContext('2d');
            ctx.font = '900 ' + fontPx + 'px ' + meta.font;
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            ctx.lineJoin = 'round';

            var cx = canvasEl.width / 2;
            var cy = canvasEl.height / 2;

            if (outlinePx > 0) {
                ctx.strokeStyle = meta.outlineColor;
                ctx.lineWidth = outlinePx * 2;
                ctx.strokeText(meta.text, cx, cy);
            }
            ctx.fillStyle = meta.color;
            ctx.fillText(meta.text, cx, cy);

            canvasEl.toBlob(function (blob) {
                if (!blob) {
                    return;
                }
                var slug = meta.text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'text';
                var name = 'text-' + slug + '-' + Math.random().toString(36).slice(2, 6) + '.png';
                done({
                    file: new File([blob], name, { type: 'image/png' }),
                    url: URL.createObjectURL(blob),
                    width: canvasEl.width,
                    height: canvasEl.height
                });
            }, 'image/png');
        }

        function applyTextPanel() {
            var text = textInput ? textInput.value.trim() : '';
            if (!text) {
                window.alert(config.messages.textEmpty || 'Type some text first.');
                return;
            }

            var meta = {
                text: text,
                font: textFont ? textFont.value : 'Impact, sans-serif',
                heightIn: clamp(Number(textSize && textSize.value || 2), 0.5, 12),
                color: textColor ? textColor.value : '#000000',
                outline: Number(textOutline && textOutline.value || 0),
                outlineColor: textOutlineColor ? textOutlineColor.value : '#ffffff'
            };

            renderTextPng(meta, function (result) {
                if (state.editingTextId) {
                    var upload = state.uploads.find(function (u) { return u.id === state.editingTextId; });
                    if (upload) {
                        if (upload.url && upload.file) {
                            URL.revokeObjectURL(upload.url);
                        }
                        upload.name = result.file.name;
                        upload.file = result.file;
                        upload.url = result.url;
                        upload.width = result.width;
                        upload.height = result.height;
                        upload.textMeta = meta;
                        upload.existingAssetId = 0; // regenerated: needs re-upload

                        state.items.forEach(function (item) {
                            if (item.uploadId === upload.id) {
                                item.url = result.url;
                                item.fileName = result.file.name;
                                item.label = 'Text: ' + meta.text;
                                item.naturalWidth = result.width;
                                item.naturalHeight = result.height;
                                item.height = meta.heightIn * (result.height / (meta.heightIn * TEXT_DPI));
                                item.width = item.height * (result.width / result.height);
                            }
                        });
                    }
                } else {
                    var entry = {
                        id: nextId('upload'),
                        name: result.file.name,
                        file: result.file,
                        url: result.url,
                        width: result.width,
                        height: result.height,
                        isText: true,
                        textMeta: meta
                    };
                    registerUpload(entry);
                    addUploadToCanvas(entry.id);
                    var item = activeItem();
                    if (item) {
                        // Print size honors the requested letter height.
                        item.height = Number((result.height / TEXT_DPI).toFixed(2));
                        item.width = Number((result.width / TEXT_DPI).toFixed(2));
                        item.label = 'Text: ' + meta.text;
                    }
                }

                syncInputFiles();
                renderUploadCards();
                renderItems();
                closeTextPanel();
            });
        }

        /* ---------- Saved designs ---------- */

        function designRequest(action, fields, done) {
            var data = new FormData();
            data.append('action', action);
            data.append('nonce', config.designNonce || '');
            Object.keys(fields || {}).forEach(function (key) {
                data.append(key, fields[key]);
            });

            fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        var message = payload && payload.data && payload.data.message ? payload.data.message : 'Request failed.';
                        window.alert(message);
                        return;
                    }
                    done(payload.data || {});
                })
                .catch(function () {
                    window.alert(config.messages.networkError || 'Network error — please try again.');
                });
        }

        function saveDesign() {
            if (!state.items.length) {
                window.alert(config.messages.needArtwork || 'Add artwork to the sheet first.');
                return;
            }

            var suggested = state.designName || '';
            var name = window.prompt(config.messages.designNamePrompt || 'Name this design:', suggested);
            if (name === null) {
                return;
            }

            serializeState();

            var data = new FormData();
            data.append('action', 'bw_gsb_save_design');
            data.append('nonce', config.designNonce || '');
            data.append('design_id', state.designId || 0);
            data.append('design_name', name);
            data.append('sheet_code', state.sheet ? state.sheet.code : '');
            data.append('layout_json', layoutInput ? layoutInput.value : '');

            var existingIds = [];
            state.uploads.forEach(function (upload) {
                if (upload.file) {
                    data.append('artwork_files[]', upload.file, upload.name);
                } else if (upload.existingAssetId) {
                    existingIds.push(upload.existingAssetId);
                }
            });
            data.append('existing_asset_ids', JSON.stringify(existingIds));

            fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        window.alert(payload && payload.data && payload.data.message ? payload.data.message : 'Could not save the design.');
                        return;
                    }
                    state.designId = payload.data.design_id;
                    state.designName = payload.data.design_name;
                    window.alert(config.messages.designSaved || 'Design saved. Find it under My Designs any time.');
                })
                .catch(function () {
                    window.alert(config.messages.networkError || 'Network error — please try again.');
                });
        }

        function renderDesignsList(designs) {
            if (!designsList) {
                return;
            }

            designsList.innerHTML = '';
            if (!designs.length) {
                var empty = document.createElement('p');
                empty.className = 'bw-gsb-help';
                empty.textContent = config.messages.designsEmpty || 'No saved designs yet. Build a sheet and hit Save Design.';
                designsList.appendChild(empty);
                return;
            }

            designs.forEach(function (design) {
                var row = document.createElement('div');
                row.className = 'bw-gsb-design-row';

                var label = document.createElement('div');
                var title = document.createElement('strong');
                title.textContent = design.design_name || 'Untitled design';
                var meta = document.createElement('span');
                meta.textContent = design.sheet_code + ' · ' + design.asset_count + ' file(s) · ' + String(design.updated_at || '').slice(0, 10);
                label.appendChild(title);
                label.appendChild(meta);

                var loadButton = document.createElement('button');
                loadButton.type = 'button';
                loadButton.className = 'bw-gsb-tool-button';
                loadButton.textContent = config.messages.designLoad || 'Load';
                loadButton.addEventListener('click', function () {
                    loadDesign(design.id);
                });

                var deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'bw-gsb-tool-button is-danger';
                deleteButton.textContent = config.messages.designDelete || 'Delete';
                deleteButton.addEventListener('click', function () {
                    if (!window.confirm(config.messages.designDeleteConfirm || 'Delete this saved design?')) {
                        return;
                    }
                    designRequest('bw_gsb_delete_design', { design_id: design.id }, function () {
                        openDesigns();
                    });
                });

                row.appendChild(label);
                row.appendChild(loadButton);
                row.appendChild(deleteButton);
                designsList.appendChild(row);
            });
        }

        function openDesigns() {
            if (!designsPanel) {
                return;
            }

            designsPanel.hidden = false;
            designRequest('bw_gsb_list_designs', {}, function (data) {
                renderDesignsList(data.designs || []);
            });
        }

        function loadDesign(designId) {
            designRequest('bw_gsb_load_design', { design_id: designId }, function (data) {
                state.uploads = [];
                state.items = [];
                state.activeId = null;
                state.designId = data.design_id;
                state.designName = data.design_name;

                if (designSourceInput) {
                    designSourceInput.value = data.design_id;
                }

                if (sheetSelect && data.sheet_code) {
                    sheetSelect.value = data.sheet_code;
                }
                updateSheetMeta();

                var uploadsByFilename = {};
                (data.assets || []).forEach(function (asset) {
                    var entry = {
                        id: nextId('upload'),
                        name: asset.filename,
                        file: null,
                        url: asset.url,
                        width: asset.width,
                        height: asset.height,
                        existingAssetId: asset.asset_id,
                        isText: /^text-/.test(asset.filename)
                    };
                    state.uploads.push(entry);
                    uploadsByFilename[String(asset.filename).toLowerCase()] = entry;
                });

                var layoutItems = (data.layout && data.layout.items) || [];
                layoutItems.forEach(function (raw) {
                    var upload = uploadsByFilename[String(raw.file_name || '').toLowerCase()];
                    if (!upload) {
                        return;
                    }
                    state.items.push({
                        id: nextId('item'),
                        uploadId: upload.id,
                        fileName: upload.name,
                        label: raw.label || upload.name,
                        url: upload.url,
                        naturalWidth: upload.width,
                        naturalHeight: upload.height,
                        x: Number(raw.x || 0),
                        y: Number(raw.y || 0),
                        width: Number(raw.width || 1),
                        height: Number(raw.height || 1),
                        rotation: Number(raw.rotation || 0),
                        zIndex: Number(raw.z_index || state.items.length + 1)
                    });
                });

                syncInputFiles();
                updateFileSummary();
                renderUploadCards();
                renderItems();

                if (designsPanel) {
                    designsPanel.hidden = true;
                }
            });
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

        if (autoArrangeButton) {
            autoArrangeButton.addEventListener('click', autoArrange);
        }

        if (addAllButton) {
            addAllButton.addEventListener('click', addAllToSheet);
        }

        if (addTextButton) {
            addTextButton.addEventListener('click', function () {
                openTextPanel(null);
            });
        }

        if (textApply) {
            textApply.addEventListener('click', applyTextPanel);
        }

        if (textCancel) {
            textCancel.addEventListener('click', closeTextPanel);
        }

        if (textInput) {
            textInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyTextPanel();
                }
            });
        }

        if (canvas) {
            canvas.addEventListener('dblclick', function (event) {
                var itemNode = event.target.closest('.bw-gsb-canvas-item');
                if (!itemNode) {
                    return;
                }
                var item = state.items.find(function (entry) {
                    return entry.id === itemNode.dataset.itemId;
                });
                if (!item) {
                    return;
                }
                var upload = state.uploads.find(function (entry) {
                    return entry.id === item.uploadId;
                });
                if (upload && upload.textMeta) {
                    openTextPanel(upload.id);
                }
            });
        }

        if (saveDesignButton) {
            saveDesignButton.addEventListener('click', saveDesign);
        }

        if (myDesignsButton) {
            myDesignsButton.addEventListener('click', openDesigns);
        }

        if (designsClose) {
            designsClose.addEventListener('click', function () {
                designsPanel.hidden = true;
            });
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

        form.addEventListener('submit', function (event) {
            if (!state.items.length) {
                event.preventDefault();
                window.alert(config.messages.needArtwork || 'Add at least one artwork to the sheet first.');
                return;
            }

            computeWarnings();

            var hasOffSheet = state.items.some(function (item) {
                return item.offSheet;
            });
            if (hasOffSheet) {
                event.preventDefault();
                window.alert(config.messages.blockOffSheet || 'Some artwork extends past the sheet edge. Fix it before checking out.');
                return;
            }

            var hasSoftIssues = state.items.some(function (item) {
                return item.overlapping || dpiLevel(effectiveDpi(item)) === 'low';
            });
            if (hasSoftIssues && !window.confirm(config.messages.confirmIssues || 'Some artwork has quality warnings. Continue?')) {
                event.preventDefault();
            }
        });

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
