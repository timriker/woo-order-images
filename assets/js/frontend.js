(function () {
	const containers = document.querySelectorAll('.woi-product-note[data-woi-required-base]');
	if (!containers.length || typeof Cropper === 'undefined') {
		return;
	}

	containers.forEach((container) => {
		const base = Math.max(1, parseInt(container.getAttribute('data-woi-required-base') || '1', 10));
		const aspectRatio = parseFloat(container.getAttribute('data-woi-aspect-ratio') || '1');
		const visibleAspectRatio = parseFloat(container.getAttribute('data-woi-visible-aspect-ratio') || '1');
		const visibleWidthPercent = parseFloat(container.getAttribute('data-woi-visible-width-percent') || '100');
		const visibleHeightPercent = parseFloat(container.getAttribute('data-woi-visible-height-percent') || '100');
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
		const saveCropButton = modal ? modal.querySelector('[data-woi-save-crop]') : null;
		const closeButtons = modal ? modal.querySelectorAll('[data-woi-close]') : [];

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

		const getGeometry = (orientation) => {
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
		};

		const detectImageOrientation = (fileUrl) => new Promise((resolve) => {
			const image = new Image();
			image.onload = () => {
				resolve(image.naturalWidth >= image.naturalHeight ? 'landscape' : 'portrait');
			};
			image.onerror = () => resolve(defaultOrientation);
			image.src = fileUrl;
		});

		const cleanupSlotUrl = (slot) => {
			if (slot && slot.fileUrl && slot.fileUrl.startsWith('blob:')) {
				URL.revokeObjectURL(slot.fileUrl);
			}
		};

		const assignFileToSlot = async (slot, file) => {
			if (!slot || !file) {
				return;
			}

			cleanupSlotUrl(slot);
			slot.fileUrl = URL.createObjectURL(file);
			slot.croppedData = '';
			slot.orientation = isSquare ? 'square' : await detectImageOrientation(slot.fileUrl);
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
			const complete = state.slots.filter((slot) => !!slot.croppedData).length;
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
					croppedData: '',
					orientation: defaultOrientation,
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

			const geometry = getGeometry(slot.orientation || defaultOrientation);
			state.activeIndex = index;
			modal.hidden = false;
			modalImage.src = slot.fileUrl;
			applyModalGuides(geometry);
			orientationSwapButton.disabled = isSquare;
			orientationSwapButton.textContent = isSquare
				? 'Orientation fixed (square)'
				: `Swap to ${geometry.orientation === 'landscape' ? 'Portrait' : 'Landscape'}`;

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
				zoom(event) {
					const ratio = typeof event.detail === 'object' ? event.detail.ratio : null;
					if (ratio === null) { return; }
					if (state.cropMinZoom !== null && ratio < state.cropMinZoom) {
						event.preventDefault();
						return;
					}
					if (state.cropMaxZoom !== null && ratio > state.cropMaxZoom) {
						event.preventDefault();
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
					// Compute min/max zoom bounds
					const imgData = cropper.getImageData();
					const currentRatio = imgData.width / imgData.naturalWidth;
					// Min: image just covers visible area
					state.cropMinZoom = Math.max(
						cropW / imgData.naturalWidth,
						cropH / imgData.naturalHeight,
					);
					// Max: 4× the initial fitted ratio
					state.cropMaxZoom = currentRatio * 4;
					syncSliderFromCropper();
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
		};

		const renderSlots = () => {
			slotsWrap.innerHTML = '';

			state.slots.forEach((slot, index) => {
				const geometry = getGeometry(slot.orientation || defaultOrientation);
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
				hiddenInput.value = slot.croppedData || '';

				if (slot.croppedData) {
					preview.src = slot.croppedData;
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

					await assignFileToSlot(slot, file);
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

				if (slot.croppedData) {
					root.classList.add('woi-upload-slot--complete');
				}

				slotsWrap.appendChild(fragment);
			});
		};

		orientationSwapButton.addEventListener('click', () => {
			if (isSquare || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			const slot = state.slots[state.activeIndex];
			slot.orientation = slot.orientation === 'portrait' ? 'landscape' : 'portrait';
			openModalForSlot(state.activeIndex);
		});

		rotateButton.addEventListener('click', () => {
			if (!state.cropper || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			state.cropper.rotate(90);
		});

		saveCropButton.addEventListener('click', () => {
			if (!state.cropper || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			// Export visible area first
			const geo = state.cropGeometry;
			const visibleData = state.cropper.getData(true);
			const bleedFracX = (100 / geo.visibleWidthPercent - 1) / 2;
			const bleedFracY = (100 / geo.visibleHeightPercent - 1) / 2;
			const bW = Math.round(visibleData.width * bleedFracX);
			const bH = Math.round(visibleData.height * bleedFracY);

			state.cropper.setData({
				x: visibleData.x - bW,
				y: visibleData.y - bH,
				width: visibleData.width + 2 * bW,
				height: visibleData.height + 2 * bH,
				rotate: visibleData.rotate,
				scaleX: visibleData.scaleX,
				scaleY: visibleData.scaleY,
			});
			const canvas = state.cropper.getCroppedCanvas({
				fillColor: '#ffffff',
				imageSmoothingEnabled: true,
				imageSmoothingQuality: 'high',
			});

			if (!canvas) {
				return;
			}

			// Ensure exported canvas has the correct full bleed aspect ratio
			// to prevent uneven scaling on the print sheet
			const naturalAspect = canvas.width / canvas.height;
			const targetAspect = geo.cropAspect;

			let finalCanvas = canvas;
			if (Math.abs(naturalAspect - targetAspect) > 0.01) {
				// Resize canvas to match target aspect, keeping width and adjusting height
				const targetHeight = Math.round(canvas.width / targetAspect);
				finalCanvas = document.createElement('canvas');
				finalCanvas.width = canvas.width;
				finalCanvas.height = targetHeight;

				const ctx = finalCanvas.getContext('2d');
				ctx.fillStyle = '#ffffff';
				ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

				// Center the exported content vertically
				const yOffset = (targetHeight - canvas.height) / 2;
				ctx.drawImage(canvas, 0, yOffset);
			}

			state.slots[state.activeIndex].croppedData = finalCanvas.toDataURL('image/jpeg', 0.92);
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
					await assignFileToSlot(state.slots[targetIndex], file);
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
			const complete = state.slots.filter((slot) => !!slot.croppedData).length;
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
				const imageUrl = typeof existingImages[index] === 'string' ? existingImages[index].trim() : '';
				if (!imageUrl || !state.slots[index]) {
					continue;
				}

				state.slots[index].fileUrl = imageUrl;
				state.slots[index].croppedData = imageUrl;
				state.slots[index].orientation = isSquare ? 'square' : await detectImageOrientation(imageUrl);
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
