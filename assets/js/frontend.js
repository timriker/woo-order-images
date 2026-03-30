(function () {
	const containers = document.querySelectorAll('.woi-product-note[data-woi-required-base]');
	if (!containers.length || typeof Cropper === 'undefined') {
		return;
	}

	containers.forEach((container) => {
		const ajaxConfig = (typeof window.woiFrontend === 'object' && window.woiFrontend)
			? window.woiFrontend
			: null;
		const base = Math.max(1, parseInt(container.getAttribute('data-woi-required-base') || '1', 10));
		const aspectRatio = parseFloat(container.getAttribute('data-woi-aspect-ratio') || '1');
		const visibleAspectRatio = parseFloat(container.getAttribute('data-woi-visible-aspect-ratio') || '1');
		const visibleWidthPercent = parseFloat(container.getAttribute('data-woi-visible-width-percent') || '100');
		const visibleHeightPercent = parseFloat(container.getAttribute('data-woi-visible-height-percent') || '100');
		const isPuzzle = container.getAttribute('data-woi-is-puzzle') === '1';
		const puzzleCols = Math.max(1, parseInt(container.getAttribute('data-woi-puzzle-cols') || '1', 10));
		const puzzleRows = Math.max(1, parseInt(container.getAttribute('data-woi-puzzle-rows') || '1', 10));
		const puzzleAspectRatio = parseFloat(container.getAttribute('data-woi-puzzle-aspect-ratio') || '1');
		const existingImagesRaw = container.getAttribute('data-woi-existing-images') || '[]';
		const existingQty = Math.max(0, parseInt(container.getAttribute('data-woi-existing-qty') || '0', 10));
		const orientationSwapButton = document.querySelector('[data-woi-swap-orientation]');
		const rotateButton = document.querySelector('[data-woi-rotate]');
		const form = container.closest('form.cart');
		const multiFileInput = container.querySelector('[data-woi-multi-file]');
		const slotsWrap = container.querySelector('[data-woi-upload-slots]');
		const slotTemplate = container.querySelector('.woi-upload-slot-template');
		const quantityInput = form ? form.querySelector('input.qty') : null;
		const textNode = container.querySelector('.woi-requirement-text');
		const modal = document.querySelector('[data-woi-modal]');
		const modalImage = modal ? modal.querySelector('[data-woi-cropper-image]') : null;
		const zoomSlider = modal ? modal.querySelector('[data-woi-zoom-slider]') : null;
		const zoomValue = modal ? modal.querySelector('[data-woi-zoom-value]') : null;
		const applyCountRow = modal ? modal.querySelector('[data-woi-apply-count-row]') : null;
		const applyCountSelect = modal ? modal.querySelector('[data-woi-apply-count]') : null;
		const saveCropButton = modal ? modal.querySelector('[data-woi-save-crop]') : null;
		const closeButtons = modal ? modal.querySelectorAll('[data-woi-close]') : [];
		const puzzleGrid = modal ? modal.querySelector('[data-woi-puzzle-grid]') : null;

		if (!form || !multiFileInput || !slotsWrap || !slotTemplate || !quantityInput || !textNode || !modal || !modalImage || !saveCropButton || !orientationSwapButton || !rotateButton) {
			return;
		}

		const isSquare = Math.abs(aspectRatio - 1) < 0.0001;
		const landscapeAspect = aspectRatio >= 1 ? aspectRatio : 1 / Math.max(aspectRatio, 0.0001);
		const portraitAspect = 1 / landscapeAspect;
		const landscapeVisibleAspect = visibleAspectRatio >= 1 ? visibleAspectRatio : 1 / Math.max(visibleAspectRatio, 0.0001);
		const portraitVisibleAspect = 1 / landscapeVisibleAspect;
		const landscapeVisibleWidthPercent = visibleAspectRatio >= 1 ? visibleWidthPercent : visibleHeightPercent;
		const landscapeVisibleHeightPercent = visibleAspectRatio >= 1 ? visibleHeightPercent : visibleWidthPercent;
		const portraitVisibleWidthPercent = landscapeVisibleHeightPercent;
		const portraitVisibleHeightPercent = landscapeVisibleWidthPercent;
		const defaultOrientation = visibleAspectRatio >= 1 ? 'landscape' : 'portrait';
		const defaultPuzzleOrientation = puzzleAspectRatio >= 1 ? 'landscape' : 'portrait';
		let existingImages = [];
		try {
			existingImages = JSON.parse(existingImagesRaw);
			if (!Array.isArray(existingImages)) {
				existingImages = [];
			}
		} catch (error) {
			existingImages = [];
		}

		const state = {
			slots: [],
			activeIndex: -1,
			cropper: null,
			cropGeometry: null,
			cropMinZoom: null,
			cropMaxZoom: null,
			assignableIndexes: [],
		};

		const getPuzzleGridForOrientation = (orientation) => {
			if (orientation && orientation !== defaultPuzzleOrientation) {
				return {
					cols: puzzleRows,
					rows: puzzleCols,
				};
			}

			return {
				cols: puzzleCols,
				rows: puzzleRows,
			};
		};

		const normalizeRotation = (rotation) => {
			const normalized = rotation % 360;
			return normalized < 0 ? normalized + 360 : normalized;
		};

		const syncSliderFromCropper = () => {
			if (!state.cropper || !zoomSlider || !zoomValue) { return; }
			const imgData = state.cropper.getImageData();
			const ratio = imgData.width / imgData.naturalWidth;
			const minR = state.cropMinZoom || ratio;
			const maxR = state.cropMaxZoom || (ratio * 4);
			const pct = Math.max(0, Math.min(100, ((ratio - minR) / (maxR - minR)) * 100));
			zoomSlider.value = String(Math.round(pct));
			zoomValue.textContent = `${Math.round(ratio * 100)}%`;
		};

		if (zoomSlider) {
			zoomSlider.addEventListener('input', () => {
				if (!state.cropper || state.cropMinZoom === null || state.cropMaxZoom === null) { return; }
				const pct = parseInt(zoomSlider.value, 10) / 100;
				const targetRatio = state.cropMinZoom + pct * (state.cropMaxZoom - state.cropMinZoom);
				state.cropper.zoomTo(targetRatio);
				if (zoomValue) {
					zoomValue.textContent = `${Math.round(targetRatio * 100)}%`;
				}
			});
		}

		const getGeometry = (orientation, slot = null) => {
			if (isPuzzle) {
				const grid = slot && slot.puzzleCols && slot.puzzleRows
					? {
						cols: Math.max(1, parseInt(slot.puzzleCols, 10)),
						rows: Math.max(1, parseInt(slot.puzzleRows, 10)),
					}
					: getPuzzleGridForOrientation(orientation || defaultPuzzleOrientation);
				const gridAspect = grid.rows > 0 ? grid.cols / grid.rows : 1;
				return {
					cropAspect: gridAspect > 0 ? gridAspect : 1,
					visibleAspect: gridAspect > 0 ? gridAspect : 1,
					visibleWidthPercent,
					visibleHeightPercent,
					orientation: orientation || defaultPuzzleOrientation,
					puzzleCols: grid.cols,
					puzzleRows: grid.rows,
				};
			}

			if (isSquare) {
				return {
					cropAspect: 1,
					visibleAspect: 1,
					visibleWidthPercent,
					visibleHeightPercent,
					orientation: 'square',
				};
			}

			if (orientation === 'portrait') {
				return {
					cropAspect: portraitAspect,
					visibleAspect: portraitVisibleAspect,
					visibleWidthPercent: portraitVisibleWidthPercent,
					visibleHeightPercent: portraitVisibleHeightPercent,
					orientation: 'portrait',
				};
			}

			return {
				cropAspect: landscapeAspect,
				visibleAspect: landscapeVisibleAspect,
				visibleWidthPercent: landscapeVisibleWidthPercent,
				visibleHeightPercent: landscapeVisibleHeightPercent,
				orientation: 'landscape',
			};
		};

		const applyModalGuides = (geometry) => {
			// Size the crop container to the full-bleed aspect ratio for this orientation
			const cropperWrap = modal.querySelector('.woi-cropper-wrap');
			if (cropperWrap) {
				cropperWrap.style.aspectRatio = `${geometry.cropAspect}`;
			}

			if (puzzleGrid) {
				if (isPuzzle) {
					puzzleGrid.hidden = false;
					puzzleGrid.style.setProperty('--woi-puzzle-cols', `${geometry.puzzleCols}`);
					puzzleGrid.style.setProperty('--woi-puzzle-rows', `${geometry.puzzleRows}`);
					const label = puzzleGrid.querySelector('[data-woi-puzzle-grid-label]');
					if (label) {
						label.textContent = `${geometry.puzzleCols}×${geometry.puzzleRows} puzzle grid`;
					}
				} else {
					puzzleGrid.hidden = true;
					puzzleGrid.style.left = '';
					puzzleGrid.style.top = '';
					puzzleGrid.style.width = '';
					puzzleGrid.style.height = '';
				}
			}
		};

		const syncPuzzleGridToCropBox = () => {
			if (!isPuzzle || !puzzleGrid || !state.cropper) {
				return;
			}

			const cropBox = state.cropper.getCropBoxData();
			if (!cropBox || cropBox.width <= 0 || cropBox.height <= 0) {
				return;
			}

			puzzleGrid.style.left = `${cropBox.left}px`;
			puzzleGrid.style.top = `${cropBox.top}px`;
			puzzleGrid.style.width = `${cropBox.width}px`;
			puzzleGrid.style.height = `${cropBox.height}px`;
		};

		const applySavedCropViewport = (cropper, slot) => {
			if (!cropper || !slot || !slot.crop || slot.crop.width <= 0 || slot.crop.height <= 0) {
				return false;
			}

			const cropBox = cropper.getCropBoxData();
			if (!cropBox || cropBox.width <= 0 || cropBox.height <= 0) {
				return false;
			}

			const cropWidth = parseFloat(slot.crop.width || 0);
			const cropHeight = parseFloat(slot.crop.height || 0);
			if (cropWidth <= 0 || cropHeight <= 0) {
				return false;
			}

			const ratioX = cropBox.width / cropWidth;
			const ratioY = cropBox.height / cropHeight;
			const targetRatio = Math.min(ratioX, ratioY);

			if (!(targetRatio > 0)) {
				return false;
			}

			cropper.zoomTo(targetRatio);

			const targetLeft = cropBox.left - (parseFloat(slot.crop.x || 0) * targetRatio);
			const targetTop = cropBox.top - (parseFloat(slot.crop.y || 0) * targetRatio);
			cropper.moveTo(targetLeft, targetTop);

			return true;
		};

		const detectImageOrientation = (fileUrl) => new Promise((resolve) => {
			const image = new Image();
			image.onload = () => {
				resolve(image.naturalWidth >= image.naturalHeight ? 'landscape' : 'portrait');
			};
			image.onerror = () => resolve(defaultOrientation);
			image.src = fileUrl;
		});

		const getOrientationForRotation = (orientation, rotation) => {
			if (isSquare || orientation === 'square') {
				return 'square';
			}

			const normalizedRotation = normalizeRotation(rotation || 0);
			if (normalizedRotation === 90 || normalizedRotation === 270) {
				return orientation === 'portrait' ? 'landscape' : 'portrait';
			}

			return orientation;
		};

		const detectRenderedOrientation = async (fileUrl, rotation = 0) => {
			if (isSquare) {
				return 'square';
			}

			const baseOrientation = await detectImageOrientation(fileUrl);
			return getOrientationForRotation(baseOrientation, rotation);
		};

		/**
		 * Revoke blob URLs to prevent memory leaks.
		 * @param {Object} slot - Upload slot object containing fileUrl
		 */
		const cleanupSlotUrl = (slot) => {
			if (slot && slot.fileUrl && slot.fileUrl.startsWith('blob:')) {
				const sharedByOtherSlot = state.slots.some((candidate) => candidate !== slot && candidate.fileUrl === slot.fileUrl);
				if (!sharedByOtherSlot) {
					URL.revokeObjectURL(slot.fileUrl);
				}
			}
		};

		const getAssignableIndexes = (activeIndex) => {
			if (activeIndex < 0 || !state.slots[activeIndex]) {
				return [];
			}

			const indexes = [activeIndex];
			const activeSlot = state.slots[activeIndex];
			const activeIsOpen = !activeSlot.crop || !activeSlot.sourceUrl;

			if (!activeIsOpen) {
				return indexes;
			}

			state.slots.forEach((slot, index) => {
				if (index === activeIndex) {
					return;
				}

				if (!slot.crop || !slot.sourceUrl) {
					indexes.push(index);
				}
			});

			return indexes;
		};

		const syncApplyCountControl = (activeIndex) => {
			state.assignableIndexes = getAssignableIndexes(activeIndex);

			if (!applyCountRow || !applyCountSelect) {
				return;
			}

			const maxAssignable = state.assignableIndexes.length;
			applyCountSelect.innerHTML = '';

			for (let count = 1; count <= maxAssignable; count += 1) {
				const option = document.createElement('option');
				option.value = String(count);
				option.textContent = String(count);
				applyCountSelect.appendChild(option);
			}

			applyCountSelect.value = '1';
			applyCountRow.hidden = maxAssignable <= 1;
		};

		const assignFileToSlot = async (slot, file) => {
			if (!slot || !file) {
				return;
			}

			cleanupSlotUrl(slot);
			slot.fileUrl = URL.createObjectURL(file);
			const dataUrl = await new Promise((resolve, reject) => {
				const reader = new FileReader();
				reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
				reader.onerror = () => reject(new Error('Unable to read image file.'));
				reader.readAsDataURL(file);
			});

			if (!ajaxConfig || !ajaxConfig.ajaxUrl || !ajaxConfig.nonce) {
				throw new Error('Image upload endpoint is unavailable.');
			}

			const formData = new FormData();
			formData.append('action', 'woi_stage_image_upload');
			formData.append('nonce', ajaxConfig.nonce);
			formData.append('source', dataUrl);

			const response = await fetch(ajaxConfig.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			});

			const result = await response.json().catch(() => ({}));
			if (!response.ok || !result || result.success !== true || !result.data || !result.data.url) {
				throw new Error('Unable to stage image upload.');
			}

			slot.sourceData = '';
			slot.sourceUrl = result.data.url;
			slot.previewData = '';
			slot.crop = null;
			slot.orientation = await detectRenderedOrientation(slot.fileUrl, 0);
			if (isPuzzle) {
				const grid = getPuzzleGridForOrientation(slot.orientation);
				slot.puzzleCols = grid.cols;
				slot.puzzleRows = grid.rows;
			}
			slot.rotation = 0;
		};

		const getTargetIndexes = (count) => {
			const indexes = [];

			state.slots.forEach((slot, index) => {
				if (!slot.fileUrl && indexes.length < count) {
					indexes.push(index);
				}
			});

			state.slots.forEach((slot, index) => {
				if (indexes.length >= count) {
					return;
				}

				if (!indexes.includes(index)) {
					indexes.push(index);
				}
			});

			return indexes.slice(0, count);
		};

		const getRequiredTotal = () => {
			const qty = Math.max(1, parseInt(quantityInput.value || '1', 10));
			return qty * base;
		};

		const updateRequirementText = () => {
			const required = getRequiredTotal();
			const complete = state.slots.filter((slot) => !!slot.crop && !!slot.sourceUrl).length;
			if (isPuzzle) {
				textNode.textContent = `Puzzle mode requires ${required} full-image upload(s). Completed: ${complete}/${required}.`;
				return;
			}
			textNode.textContent = `This order requires ${required} image(s). Completed: ${complete}/${required}.`;
		};

		const ensureSlots = () => {
			const required = getRequiredTotal();

			while (state.slots.length > required) {
				cleanupSlotUrl(state.slots[state.slots.length - 1]);
				state.slots.pop();
			}

			while (state.slots.length < required) {
				state.slots.push({
					fileUrl: '',
					sourceData: '',
					sourceUrl: '',
						previewData: '',
						crop: null,
						orientation: defaultOrientation,
						puzzleCols: puzzleCols,
						puzzleRows: puzzleRows,
						rotation: 0,
					});
			}

			renderSlots();
			updateRequirementText();
		};

		const openModalForSlot = (index) => {
			const slot = state.slots[index];
			if (!slot || !slot.fileUrl) {
				return;
			}

			const geometry = getGeometry(slot.orientation || defaultOrientation, slot);
			state.activeIndex = index;
			syncApplyCountControl(index);
			modal.hidden = false;
			modalImage.src = slot.fileUrl;
			applyModalGuides(geometry);
			orientationSwapButton.disabled = !isPuzzle && isSquare;
			if (isPuzzle) {
				const nextGrid = getPuzzleGridForOrientation(geometry.orientation === 'landscape' ? 'portrait' : 'landscape');
				orientationSwapButton.textContent = `Swap to ${nextGrid.cols}×${nextGrid.rows}`;
			} else {
				orientationSwapButton.textContent = isSquare
					? 'Orientation fixed (square)'
					: `Swap to ${geometry.orientation === 'landscape' ? 'Portrait' : 'Landscape'}`;
			}

			if (state.cropper) {
				state.cropper.destroy();
				state.cropper = null;
			}

			state.cropGeometry = geometry;

			state.cropMinZoom = null;
			state.cropMaxZoom = null;

			state.cropper = new Cropper(modalImage, {
				// Crop box = visible area; bleed region shows as Cropper's natural grey
				aspectRatio: geometry.visibleAspect > 0 ? geometry.visibleAspect : 1,
				viewMode: 1,
				autoCropArea: 1,
				responsive: true,
				background: false,
				dragMode: 'move',
				cropBoxMovable: false,
				cropBoxResizable: false,
				toggleDragModeOnDblclick: false,
				wheelZoomRatio: 0.06,
				crop() {
					syncPuzzleGridToCropBox();
				},
				zoom(event) {
					const ratio = typeof event.detail === 'object' ? event.detail.ratio : null;
					if (ratio === null) { return; }
					if (state.cropMinZoom !== null && ratio < state.cropMinZoom) {
						event.preventDefault();
						requestAnimationFrame(() => {
							if (state.cropper && state.cropMinZoom !== null) {
								state.cropper.zoomTo(state.cropMinZoom);
								syncSliderFromCropper();
							}
						});
						return;
					}
					if (state.cropMaxZoom !== null && ratio > state.cropMaxZoom) {
						event.preventDefault();
						requestAnimationFrame(() => {
							if (state.cropper && state.cropMaxZoom !== null) {
								state.cropper.zoomTo(state.cropMaxZoom);
								syncSliderFromCropper();
							}
						});
						return;
					}
					// Sync slider after Cropper has applied the zoom
					requestAnimationFrame(() => syncSliderFromCropper());
				},
				ready() {
					const cropper = this.cropper;
					if (!cropper) {
						return;
					}

					const effectiveRotation = normalizeRotation(slot.rotation || 0);
					if (effectiveRotation !== 0) {
						cropper.rotateTo(effectiveRotation);
					}
					// Place crop box precisely over the visible area (centred within bleed container)
					const containerData = cropper.getContainerData();
					const geo = state.cropGeometry;
					const cropW = containerData.width * (geo.visibleWidthPercent / 100);
					const cropH = containerData.height * (geo.visibleHeightPercent / 100);
					cropper.setCropBoxData({
						left: (containerData.width - cropW) / 2,
						top: (containerData.height - cropH) / 2,
						width: cropW,
						height: cropH,
					});

					const applyZoomBounds = () => {
						if (!state.cropper) {
							return;
						}
						const hasSavedCrop = applySavedCropViewport(cropper, slot);
						const settledCropBox = cropper.getCropBoxData();
						const effectiveCropW = settledCropBox && settledCropBox.width > 0 ? settledCropBox.width : cropW;
						const effectiveCropH = settledCropBox && settledCropBox.height > 0 ? settledCropBox.height : cropH;
						const imgData = cropper.getImageData();
						const currentRatio = imgData.width / imgData.naturalWidth;

						state.cropMinZoom = Math.max(
							effectiveCropW / imgData.naturalWidth,
							effectiveCropH / imgData.naturalHeight,
						);
						state.cropMaxZoom = currentRatio * 4;

						if (!hasSavedCrop && state.cropMinZoom !== null && currentRatio > state.cropMinZoom) {
							cropper.zoomTo(state.cropMinZoom);
						}

						syncSliderFromCropper();
						syncPuzzleGridToCropBox();
					};

					requestAnimationFrame(() => requestAnimationFrame(applyZoomBounds));
				},
			});
		};

		const closeModal = () => {
			if (state.cropper) {
				state.cropper.destroy();
				state.cropper = null;
			}
			modal.hidden = true;
			modalImage.removeAttribute('src');
			state.activeIndex = -1;
			state.assignableIndexes = [];
			if (applyCountRow) {
				applyCountRow.hidden = true;
			}
			if (applyCountSelect) {
				applyCountSelect.innerHTML = '';
			}
			if (puzzleGrid) {
				puzzleGrid.hidden = true;
				puzzleGrid.style.left = '';
				puzzleGrid.style.top = '';
				puzzleGrid.style.width = '';
				puzzleGrid.style.height = '';
			}
		};

		const renderSlots = () => {
			slotsWrap.innerHTML = '';

			state.slots.forEach((slot, index) => {
				const geometry = getGeometry(slot.orientation || defaultOrientation, slot);
				const fragment = slotTemplate.content.cloneNode(true);
				const root = fragment.querySelector('[data-woi-slot]');
				const title = fragment.querySelector('.woi-upload-title');
				const fileInput = fragment.querySelector('[data-woi-file]');
				const preview = fragment.querySelector('[data-woi-preview]');
				const previewWrap = fragment.querySelector('.woi-preview-wrap');
				const replaceButton = fragment.querySelector('[data-woi-replace]');
				const editButton = fragment.querySelector('[data-woi-edit]');
				const hiddenInput = fragment.querySelector('[data-woi-hidden]');

				title.textContent = `Image ${index + 1}`;
				previewWrap.style.aspectRatio = `${geometry.visibleAspect}`;
					const payload = (slot.crop && slot.sourceUrl)
						? {
							source: slot.sourceUrl,
							crop: slot.crop,
							orientation: slot.orientation || defaultOrientation,
							rotation: slot.rotation || 0,
							...(isPuzzle ? {
								puzzle_cols: Math.max(1, parseInt(slot.puzzleCols || puzzleCols, 10)),
								puzzle_rows: Math.max(1, parseInt(slot.puzzleRows || puzzleRows, 10)),
							} : {}),
						}
						: null;
				hiddenInput.value = payload ? JSON.stringify(payload) : '';

				if (slot.previewData) {
					preview.src = slot.previewData;
					preview.classList.add('woi-preview--visible');
				} else if (slot.fileUrl) {
					preview.src = slot.fileUrl;
					preview.classList.add('woi-preview--visible');
				} else {
					preview.removeAttribute('src');
					preview.classList.remove('woi-preview--visible');
				}

				editButton.disabled = !slot.fileUrl;

				fileInput.addEventListener('change', async (event) => {
					const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
					if (!file) {
						return;
					}

					if (!file.type || !file.type.startsWith('image/')) {
						window.alert('Please choose an image file.');
						event.target.value = '';
						return;
					}

					try {
						await assignFileToSlot(slot, file);
					} catch (error) {
						window.alert('Unable to upload this image right now. Please try again.');
						event.target.value = '';
						return;
					}
					renderSlots();
					updateRequirementText();
					openModalForSlot(index);
					event.target.value = '';
				});

				replaceButton.addEventListener('click', () => {
					fileInput.click();
				});

				editButton.addEventListener('click', () => {
					openModalForSlot(index);
				});

				if (slot.crop && slot.sourceUrl) {
					root.classList.add('woi-upload-slot--complete');
				}

				slotsWrap.appendChild(fragment);
			});
		};

		orientationSwapButton.addEventListener('click', () => {
			if ((!isPuzzle && isSquare) || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			const slot = state.slots[state.activeIndex];
			slot.orientation = slot.orientation === 'portrait' ? 'landscape' : 'portrait';
			if (isPuzzle) {
				const grid = getPuzzleGridForOrientation(slot.orientation);
				slot.puzzleCols = grid.cols;
				slot.puzzleRows = grid.rows;
			}
			openModalForSlot(state.activeIndex);
		});

		rotateButton.addEventListener('click', async () => {
			if (!state.cropper || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			const slot = state.slots[state.activeIndex];
			slot.rotation = normalizeRotation((slot.rotation || 0) + 90);
			slot.previewData = '';
			slot.crop = null;
			slot.orientation = await detectRenderedOrientation(slot.fileUrl, slot.rotation);
			if (isPuzzle) {
				const grid = getPuzzleGridForOrientation(slot.orientation);
				slot.puzzleCols = grid.cols;
				slot.puzzleRows = grid.rows;
			}
			openModalForSlot(state.activeIndex);
		});

		saveCropButton.addEventListener('click', async () => {
			if (!state.cropper || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			const visibleData = state.cropper.getData(true);

			const slot = state.slots[state.activeIndex];
			if (!slot.fileUrl || (!slot.sourceData && !slot.sourceUrl)) {
				return;
			}

			let assignCount = 1;
			if (applyCountSelect && state.assignableIndexes.length > 1) {
				assignCount = Math.max(1, parseInt(applyCountSelect.value || '1', 10));
			}

			const targetIndexes = state.assignableIndexes.slice(0, Math.min(assignCount, state.assignableIndexes.length));
			if (!targetIndexes.length) {
				targetIndexes.push(state.activeIndex);
			}

			slot.crop = {
				x: visibleData.x,
				y: visibleData.y,
				width: visibleData.width,
				height: visibleData.height,
			};

			try {
				const previewCanvas = state.cropper.getCroppedCanvas();
				slot.previewData = previewCanvas ? previewCanvas.toDataURL('image/jpeg', 0.85) : '';
			} catch (error) {
				slot.previewData = '';
			}

			for (let targetOffset = 1; targetOffset < targetIndexes.length; targetOffset += 1) {
				const targetIndex = targetIndexes[targetOffset];
				const targetSlot = state.slots[targetIndex];
				if (!targetSlot) {
					continue;
				}

				cleanupSlotUrl(targetSlot);
				targetSlot.fileUrl = slot.fileUrl;
				targetSlot.sourceData = '';
				targetSlot.sourceUrl = slot.sourceUrl;
				targetSlot.previewData = slot.previewData;
				targetSlot.crop = {
					x: slot.crop.x,
					y: slot.crop.y,
					width: slot.crop.width,
					height: slot.crop.height,
				};
				targetSlot.orientation = slot.orientation;
				targetSlot.rotation = slot.rotation;
				if (isPuzzle) {
					targetSlot.puzzleCols = slot.puzzleCols;
					targetSlot.puzzleRows = slot.puzzleRows;
				}
			}

			renderSlots();
			updateRequirementText();
			closeModal();
		});

		closeButtons.forEach((button) => {
			button.addEventListener('click', closeModal);
		});

		multiFileInput.addEventListener('change', async (event) => {
			const files = Array.from(event.target.files || []);
			if (!files.length) {
				return;
			}

			const allowedFiles = files.filter((file) => file.type && file.type.startsWith('image/'));
			if (!allowedFiles.length) {
				window.alert('Please choose one or more image files.');
				event.target.value = '';
				return;
			}

			const required = getRequiredTotal();
			if (allowedFiles.length > required) {
				window.alert(`Only ${required} image(s) are required. Extra files will be ignored.`);
			}

			const filesToUse = allowedFiles.slice(0, required);
			const indexes = getTargetIndexes(filesToUse.length);

			for (let fileIndex = 0; fileIndex < filesToUse.length; fileIndex += 1) {
				const file = filesToUse[fileIndex];
				const targetIndex = indexes[fileIndex];
				if (typeof targetIndex === 'number' && state.slots[targetIndex]) {
					try {
						await assignFileToSlot(state.slots[targetIndex], file);
					} catch (error) {
						window.alert('One or more images could not be uploaded. Please try again.');
						event.target.value = '';
						return;
					}
				}
			}

			renderSlots();
			updateRequirementText();

			if (indexes.length) {
				openModalForSlot(indexes[0]);
			}

			event.target.value = '';
		});

		form.addEventListener('submit', (event) => {
			const required = getRequiredTotal();
			const complete = state.slots.filter((slot) => !!slot.crop && !!slot.sourceUrl).length;
			if (complete !== required) {
				event.preventDefault();
				window.alert(`Please upload and crop all ${required} required images before adding to cart.`);
			}
		});

		quantityInput.addEventListener('change', ensureSlots);
		quantityInput.addEventListener('input', ensureSlots);

		container.style.setProperty('--woi-visible-aspect-ratio', `${visibleAspectRatio}`);
		container.style.setProperty('--woi-visible-width-percent', `${visibleWidthPercent}%`);
		container.style.setProperty('--woi-visible-height-percent', `${visibleHeightPercent}%`);

		const hydrateExistingImages = async () => {
			if (!existingImages.length) {
				return;
			}

			for (let index = 0; index < existingImages.length && index < state.slots.length; index += 1) {
				const entry = existingImages[index];
				const imageUrl = entry && typeof entry === 'object' && typeof entry.url === 'string'
					? entry.url.trim()
					: (typeof entry === 'string' ? entry.trim() : '');
				if (!imageUrl || !state.slots[index]) {
					continue;
				}

				state.slots[index].fileUrl = imageUrl;
				state.slots[index].sourceUrl = imageUrl;
				state.slots[index].sourceData = '';
				state.slots[index].previewData = imageUrl;
				state.slots[index].crop = entry && typeof entry === 'object' && entry.crop && typeof entry.crop === 'object'
					? entry.crop
					: null;

				const savedRotation = entry && typeof entry === 'object' && typeof entry.rotation === 'number'
					? entry.rotation
					: 0;
				state.slots[index].rotation = savedRotation;

				// Restore saved orientation if available, otherwise detect against the rendered orientation.
				const detectedOrientation = await detectRenderedOrientation(imageUrl, savedRotation);
				const savedOrientation = entry && typeof entry === 'object' && typeof entry.orientation === 'string'
					? entry.orientation
					: null;
				state.slots[index].orientation = savedOrientation || detectedOrientation;

				if (isPuzzle) {
					const entryCols = entry && typeof entry === 'object' ? parseInt(entry.puzzle_cols || '0', 10) : 0;
					const entryRows = entry && typeof entry === 'object' ? parseInt(entry.puzzle_rows || '0', 10) : 0;
					if (entryCols > 0 && entryRows > 0) {
						state.slots[index].puzzleCols = entryCols;
						state.slots[index].puzzleRows = entryRows;
					} else {
						const grid = getPuzzleGridForOrientation(state.slots[index].orientation);
						state.slots[index].puzzleCols = grid.cols;
						state.slots[index].puzzleRows = grid.rows;
					}
				}
			}

			renderSlots();
			updateRequirementText();
		};

		if (existingQty > 0 && existingQty !== parseInt(quantityInput.value || '1', 10)) {
			quantityInput.value = String(existingQty);
		}

		ensureSlots();
		hydrateExistingImages();
	});
})();
