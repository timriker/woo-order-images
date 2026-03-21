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
		const orientationSwapButton = document.querySelector('[data-woi-swap-orientation]');
		const form = container.closest('form.cart');
		const multiFileInput = container.querySelector('[data-woi-multi-file]');
		const slotsWrap = container.querySelector('[data-woi-upload-slots]');
		const slotTemplate = container.querySelector('.woi-upload-slot-template');
		const quantityInput = form ? form.querySelector('input.qty') : null;
		const textNode = container.querySelector('.woi-requirement-text');
		const modal = document.querySelector('[data-woi-modal]');
		const modalImage = modal ? modal.querySelector('[data-woi-cropper-image]') : null;
		const visibleGuide = modal ? modal.querySelector('.woi-visible-area-guide') : null;
		const saveCropButton = modal ? modal.querySelector('[data-woi-save-crop]') : null;
		const closeButtons = modal ? modal.querySelectorAll('[data-woi-close]') : [];

		if (!form || !multiFileInput || !slotsWrap || !slotTemplate || !quantityInput || !textNode || !modal || !modalImage || !saveCropButton || !visibleGuide || !orientationSwapButton) {
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

		const state = {
			slots: [],
			activeIndex: -1,
			cropper: null,
		};

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
			const left = (100 - geometry.visibleWidthPercent) / 2;
			const top = (100 - geometry.visibleHeightPercent) / 2;

			visibleGuide.style.left = `${left}%`;
			visibleGuide.style.top = `${top}%`;
			visibleGuide.style.width = `${geometry.visibleWidthPercent}%`;
			visibleGuide.style.height = `${geometry.visibleHeightPercent}%`;
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

			state.cropper = new Cropper(modalImage, {
				aspectRatio: geometry.cropAspect > 0 ? geometry.cropAspect : 1,
				viewMode: 1,
				autoCropArea: 1,
				responsive: true,
				background: false,
				dragMode: 'move',
				cropBoxMovable: false,
				cropBoxResizable: false,
				toggleDragModeOnDblclick: false,
				ready() {
					const cropper = this.cropper;
					if (!cropper) {
						return;
					}
					cropper.setCropBoxData(cropper.getContainerData());
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

		saveCropButton.addEventListener('click', () => {
			if (!state.cropper || state.activeIndex < 0 || !state.slots[state.activeIndex]) {
				return;
			}

			const canvas = state.cropper.getCroppedCanvas({
				imageSmoothingEnabled: true,
				imageSmoothingQuality: 'high',
			});

			if (!canvas) {
				return;
			}

			state.slots[state.activeIndex].croppedData = canvas.toDataURL('image/jpeg', 0.92);
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
		ensureSlots();
	});
})();
