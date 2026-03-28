(function () {
	const menuTriggers = document.querySelectorAll('[data-woi-admin-image-menu-trigger]');
	const modal = document.querySelector('[data-woi-admin-crop-modal]');

	if (!menuTriggers.length || !modal || typeof Cropper === 'undefined' || typeof window.woiAdminCrop !== 'object') {
		return;
	}

	const modalImage = modal.querySelector('[data-woi-admin-cropper-image]');
	const modalTitle = modal.querySelector('[data-woi-admin-modal-title]');
	const closeButtons = modal.querySelectorAll('[data-woi-admin-close]');
	const saveButton = modal.querySelector('[data-woi-admin-save]');
	const swapButton = modal.querySelector('[data-woi-admin-swap]');
	const zoomSlider = modal.querySelector('[data-woi-admin-zoom-slider]');
	const zoomValue = modal.querySelector('[data-woi-admin-zoom-value]');
	const puzzleGrid = modal.querySelector('[data-woi-admin-puzzle-grid]');

	if (!modalImage || !saveButton || !swapButton || !zoomSlider || !zoomValue || !puzzleGrid) {
		return;
	}

	const config = window.woiAdminCrop;
	const state = {
		cropper: null,
		activeButton: null,
		itemId: 0,
		imageIndex: -1,
		imageUrl: '',
		imageLabel: '',
		baseVisibleWidth: 1,
		baseVisibleHeight: 1,
		baseVisibleWidthPercent: 100,
		baseVisibleHeightPercent: 100,
		baseVisibleAspectRatio: 1,
		isPuzzle: false,
		defaultPuzzleCols: 1,
		defaultPuzzleRows: 1,
		currentPuzzleCols: 1,
		currentPuzzleRows: 1,
		currentOrientation: 'landscape',
		currentCrop: null,
		cropMinZoom: null,
		cropMaxZoom: null,
	};

	let activeMenu = null; // Track which menu is currently open

	const parseJSON = (value, fallback) => {
		try {
			return JSON.parse(value);
		} catch (error) {
			return fallback;
		}
	};

	const normalizeOrientation = (orientation) => orientation === 'portrait' ? 'portrait' : 'landscape';

	const inferOrientationFromCrop = (crop, fallback) => {
		if (crop && typeof crop === 'object') {
			const cropWidth = parseFloat(crop.width || 0);
			const cropHeight = parseFloat(crop.height || 0);
			if (cropWidth > 0 && cropHeight > 0) {
				return cropWidth >= cropHeight ? 'landscape' : 'portrait';
			}
		}

		return normalizeOrientation(fallback);
	};

	const getDefaultPuzzleOrientation = () => (
		state.defaultPuzzleCols >= state.defaultPuzzleRows ? 'landscape' : 'portrait'
	);

	const getPuzzleGridForOrientation = (orientation) => {
		if (normalizeOrientation(orientation) !== getDefaultPuzzleOrientation()) {
			return {
				cols: state.defaultPuzzleRows,
				rows: state.defaultPuzzleCols,
			};
		}

		return {
			cols: state.defaultPuzzleCols,
			rows: state.defaultPuzzleRows,
		};
	};

	const getGeometry = () => {
		if (state.isPuzzle) {
			const grid = {
				cols: Math.max(1, parseInt(state.currentPuzzleCols || state.defaultPuzzleCols, 10)),
				rows: Math.max(1, parseInt(state.currentPuzzleRows || state.defaultPuzzleRows, 10)),
			};
			const aspect = grid.rows > 0 ? grid.cols / grid.rows : 1;
			return {
				cropAspect: aspect,
				visibleAspect: aspect,
				visibleWidthPercent: state.baseVisibleWidthPercent,
				visibleHeightPercent: state.baseVisibleHeightPercent,
				puzzleCols: grid.cols,
				puzzleRows: grid.rows,
			};
		}

		if (Math.abs(state.baseVisibleAspectRatio - 1) < 0.0001) {
			return {
				cropAspect: 1,
				visibleAspect: 1,
				visibleWidthPercent: state.baseVisibleWidthPercent,
				visibleHeightPercent: state.baseVisibleHeightPercent,
			};
		}

		const landscapeAspect = state.baseVisibleAspectRatio >= 1
			? state.baseVisibleAspectRatio
			: 1 / Math.max(state.baseVisibleAspectRatio, 0.0001);
		const portraitAspect = 1 / Math.max(landscapeAspect, 0.0001);
		const landscapeVisibleWidthPercent = state.baseVisibleAspectRatio >= 1
			? state.baseVisibleWidthPercent
			: state.baseVisibleHeightPercent;
		const landscapeVisibleHeightPercent = state.baseVisibleAspectRatio >= 1
			? state.baseVisibleHeightPercent
			: state.baseVisibleWidthPercent;
		const portraitVisibleWidthPercent = landscapeVisibleHeightPercent;
		const portraitVisibleHeightPercent = landscapeVisibleWidthPercent;

		if (state.currentOrientation === 'portrait') {
			return {
				cropAspect: portraitAspect,
				visibleAspect: portraitAspect,
				visibleWidthPercent: portraitVisibleWidthPercent,
				visibleHeightPercent: portraitVisibleHeightPercent,
			};
		}

		return {
			cropAspect: landscapeAspect,
			visibleAspect: landscapeAspect,
			visibleWidthPercent: landscapeVisibleWidthPercent,
			visibleHeightPercent: landscapeVisibleHeightPercent,
		};
	};

	const applyModalGuides = (geometry) => {
		const cropperWrap = modal.querySelector('.woi-admin-cropper-wrap');
		if (cropperWrap) {
			cropperWrap.style.aspectRatio = `${geometry.cropAspect}`;
		}

		if (state.isPuzzle) {
			puzzleGrid.hidden = false;
			puzzleGrid.style.setProperty('--woi-puzzle-cols', `${geometry.puzzleCols}`);
			puzzleGrid.style.setProperty('--woi-puzzle-rows', `${geometry.puzzleRows}`);
			const label = puzzleGrid.querySelector('[data-woi-admin-puzzle-grid-label]');
			if (label) {
				label.textContent = `${geometry.puzzleCols}×${geometry.puzzleRows} ${config.puzzleGridLabel}`;
			}
		} else {
			puzzleGrid.hidden = true;
			puzzleGrid.style.left = '';
			puzzleGrid.style.top = '';
			puzzleGrid.style.width = '';
			puzzleGrid.style.height = '';
		}
	};

	const syncPuzzleGridToCropBox = () => {
		if (!state.isPuzzle || !state.cropper) {
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

	const syncSliderFromCropper = () => {
		if (!state.cropper) {
			return;
		}

		const imgData = state.cropper.getImageData();
		const ratio = imgData.width / imgData.naturalWidth;
		const minR = state.cropMinZoom || ratio;
		const maxR = state.cropMaxZoom || (ratio * 4);
		const pct = Math.max(0, Math.min(100, ((ratio - minR) / Math.max(0.0001, maxR - minR)) * 100));
		zoomSlider.value = String(Math.round(pct));
		zoomValue.textContent = `${Math.round(ratio * 100)}%`;
	};

	const updateSwapButton = () => {
		if (state.isPuzzle) {
			const nextGrid = getPuzzleGridForOrientation(state.currentOrientation === 'landscape' ? 'portrait' : 'landscape');
			swapButton.disabled = false;
			swapButton.textContent = config.swapToGridLabel
				.replace('%1$d', `${nextGrid.cols}`)
				.replace('%2$d', `${nextGrid.rows}`);
			return;
		}

		if (Math.abs(state.baseVisibleAspectRatio - 1) < 0.0001) {
			swapButton.disabled = true;
			swapButton.textContent = config.swapLandscapeLabel;
			return;
		}

		swapButton.disabled = false;
		swapButton.textContent = state.currentOrientation === 'landscape'
			? config.swapPortraitLabel
			: config.swapLandscapeLabel;
	};

	const closeModal = () => {
		if (state.cropper) {
			state.cropper.destroy();
			state.cropper = null;
		}
		modal.hidden = true;
		modalImage.removeAttribute('src');
		state.activeButton = null;
		state.imageIndex = -1;
		state.cropMinZoom = null;
		state.cropMaxZoom = null;
		puzzleGrid.hidden = true;
		puzzleGrid.style.left = '';
		puzzleGrid.style.top = '';
		puzzleGrid.style.width = '';
		puzzleGrid.style.height = '';
	};

	const openModal = () => {
		if (!state.imageUrl) {
			return;
		}

		const geometry = getGeometry();
		modal.hidden = false;
		modalImage.src = state.imageUrl;
		modalTitle.textContent = state.imageLabel || config.editLabel;
		applyModalGuides(geometry);
		updateSwapButton();

		if (state.cropper) {
			state.cropper.destroy();
			state.cropper = null;
		}

		state.cropMinZoom = null;
		state.cropMaxZoom = null;

		state.cropper = new Cropper(modalImage, {
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
				if (ratio === null) {
					return;
				}
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
				requestAnimationFrame(() => syncSliderFromCropper());
			},
			ready() {
				const cropper = this.cropper;
				if (!cropper) {
					return;
				}

				const containerData = cropper.getContainerData();
				const cropW = containerData.width * (geometry.visibleWidthPercent / 100);
				const cropH = containerData.height * (geometry.visibleHeightPercent / 100);
				cropper.setCropBoxData({
					left: (containerData.width - cropW) / 2,
					top: (containerData.height - cropH) / 2,
					width: cropW,
					height: cropH,
				});

				if (state.currentCrop && state.currentCrop.width > 0 && state.currentCrop.height > 0) {
					cropper.setData({
						x: parseFloat(state.currentCrop.x || 0),
						y: parseFloat(state.currentCrop.y || 0),
						width: parseFloat(state.currentCrop.width || 0),
						height: parseFloat(state.currentCrop.height || 0),
					});
				}

				const applyZoomBounds = () => {
					if (!state.cropper) {
						return;
					}
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

					if (state.cropMinZoom !== null && currentRatio > state.cropMinZoom) {
						cropper.zoomTo(state.cropMinZoom);
					}

					syncSliderFromCropper();
					syncPuzzleGridToCropBox();
				};

				requestAnimationFrame(() => requestAnimationFrame(applyZoomBounds));
			},
		});
	};

	const hydrateStateFromButton = (button) => {
		const crop = parseJSON(button.getAttribute('data-image-crop') || '{}', {});
		const storedOrientation = button.getAttribute('data-current-orientation') || '';
		state.activeButton = button;
		state.itemId = parseInt(button.getAttribute('data-item-id') || '0', 10);
		state.imageIndex = parseInt(button.getAttribute('data-image-index') || '-1', 10);
		state.imageUrl = button.getAttribute('data-image-url') || '';
		state.imageLabel = button.getAttribute('data-image-label') || config.editLabel;
		state.baseVisibleWidth = parseFloat(button.getAttribute('data-visible-width') || '1');
		state.baseVisibleHeight = parseFloat(button.getAttribute('data-visible-height') || '1');
		state.baseVisibleWidthPercent = parseFloat(button.getAttribute('data-visible-width-percent') || '100');
		state.baseVisibleHeightPercent = parseFloat(button.getAttribute('data-visible-height-percent') || '100');
		state.baseVisibleAspectRatio = parseFloat(button.getAttribute('data-visible-aspect-ratio') || '1');
		state.isPuzzle = button.getAttribute('data-is-puzzle') === '1';
		state.defaultPuzzleCols = Math.max(1, parseInt(button.getAttribute('data-puzzle-cols') || '1', 10));
		state.defaultPuzzleRows = Math.max(1, parseInt(button.getAttribute('data-puzzle-rows') || '1', 10));
		state.currentPuzzleCols = Math.max(1, parseInt(button.getAttribute('data-current-puzzle-cols') || `${state.defaultPuzzleCols}`, 10));
		state.currentPuzzleRows = Math.max(1, parseInt(button.getAttribute('data-current-puzzle-rows') || `${state.defaultPuzzleRows}`, 10));
		state.currentCrop = crop && typeof crop === 'object' ? crop : null;
		if (state.isPuzzle) {
			state.currentOrientation = normalizeOrientation(storedOrientation || (state.currentPuzzleCols >= state.currentPuzzleRows ? 'landscape' : 'portrait'));
		} else if (storedOrientation) {
			state.currentOrientation = normalizeOrientation(storedOrientation);
		} else if (state.currentCrop && state.currentCrop.width > 0 && state.currentCrop.height > 0) {
			state.currentOrientation = inferOrientationFromCrop(state.currentCrop, 'landscape');
		} else {
			state.currentOrientation = state.baseVisibleAspectRatio >= 1 ? 'landscape' : 'portrait';
		}
	};

	const applySaveResponse = (result) => {
		if (!state.activeButton) {
			return;
		}

		const frame = state.activeButton.parentElement ? state.activeButton.parentElement.querySelector('[data-woi-admin-thumb-frame]') : null;
		const image = state.activeButton.parentElement ? state.activeButton.parentElement.querySelector('[data-woi-admin-thumb-image]') : null;
		if (frame && result.visibleAspectRatio) {
			frame.style.aspectRatio = `${result.visibleAspectRatio}`;
		}
		if (image && result.thumbSrc) {
			image.src = result.thumbSrc;
		}

		state.activeButton.setAttribute('data-image-crop', JSON.stringify(result.crop || {}));
		if (result.currentOrientation) {
			state.activeButton.setAttribute('data-current-orientation', result.currentOrientation);
		}
		if (typeof result.puzzleCols !== 'undefined') {
			state.activeButton.setAttribute('data-current-puzzle-cols', `${result.puzzleCols}`);
		}
		if (typeof result.puzzleRows !== 'undefined') {
			state.activeButton.setAttribute('data-current-puzzle-rows', `${result.puzzleRows}`);
		}
	};

	const createMenuElement = () => {
		const menu = document.createElement('div');
		menu.className = 'woi-admin-image-menu';
		menu.setAttribute('role', 'menu');
		menu.setAttribute('aria-label', config.menuLabel || 'Image options');

		const viewButton = document.createElement('button');
		viewButton.type = 'button';
		viewButton.className = 'woi-admin-image-menu-item';
		viewButton.setAttribute('role', 'menuitem');
		viewButton.textContent = config.viewLabel || 'View Image';

		const editButton = document.createElement('button');
		editButton.type = 'button';
		editButton.className = 'woi-admin-image-menu-item';
		editButton.setAttribute('role', 'menuitem');
		editButton.textContent = config.editLabel || 'Adjust Crop';

		menu.appendChild(viewButton);
		menu.appendChild(editButton);

		return { menu, viewButton, editButton };
	};

	const closeAllMenus = () => {
		if (activeMenu) {
			activeMenu.menu.remove();
			activeMenu.trigger.setAttribute('aria-expanded', 'false');
			activeMenu = null;
		}
	};

	const openMenu = (trigger) => {
		closeAllMenus();

		const { menu, viewButton, editButton } = createMenuElement();
		const imageUrl = trigger.getAttribute('data-image-url');

		viewButton.addEventListener('click', () => {
			window.open(imageUrl, '_blank', 'noopener,noreferrer');
			closeAllMenus();
		});

		editButton.addEventListener('click', () => {
			hydrateStateFromButton(trigger);
			openModal();
			closeAllMenus();
		});

		document.body.appendChild(menu);

		const triggerRect = trigger.getBoundingClientRect();
		const menuWidth = 160;
		const menuHeight = menu.offsetHeight;
		let left = triggerRect.right + 8;
		let top = triggerRect.top;

		// Adjust if menu goes off-screen to the right
		if (left + menuWidth > window.innerWidth - 8) {
			left = triggerRect.left - menuWidth - 8;
		}

		// Adjust if menu goes off-screen below
		if (top + menuHeight > window.innerHeight - 8) {
			top = window.innerHeight - menuHeight - 8;
		}

		menu.style.left = `${left}px`;
		menu.style.top = `${top}px`;
		trigger.setAttribute('aria-expanded', 'true');

		activeMenu = { menu, trigger };

		// Close menu when clicking outside
		const closeOnClickOutside = (event) => {
			if (!menu.contains(event.target) && event.target !== trigger) {
				closeAllMenus();
				document.removeEventListener('click', closeOnClickOutside);
			}
		};

		document.addEventListener('click', closeOnClickOutside);

		// Close menu on Escape
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') {
				closeAllMenus();
				document.removeEventListener('keydown', closeOnEscape);
				trigger.focus();
			}
		};

		document.addEventListener('keydown', closeOnEscape);
	};

	// Set initial aria-expanded on all triggers
	menuTriggers.forEach((trigger) => {
		trigger.setAttribute('aria-expanded', 'false');
	});

	// Use event delegation to handle clicks on menu triggers
	document.addEventListener('click', (event) => {
		const trigger = event.target.closest('[data-woi-admin-image-menu-trigger]');
		if (trigger) {
			event.preventDefault();
			openMenu(trigger);
		}
	});

	swapButton.addEventListener('click', () => {
		if (state.isPuzzle) {
			state.currentOrientation = state.currentOrientation === 'landscape' ? 'portrait' : 'landscape';
			const grid = getPuzzleGridForOrientation(state.currentOrientation);
			state.currentPuzzleCols = grid.cols;
			state.currentPuzzleRows = grid.rows;
		} else if (Math.abs(state.baseVisibleAspectRatio - 1) >= 0.0001) {
			state.currentOrientation = state.currentOrientation === 'landscape' ? 'portrait' : 'landscape';
		}

		openModal();
	});

	zoomSlider.addEventListener('input', () => {
		if (!state.cropper || state.cropMinZoom === null || state.cropMaxZoom === null) {
			return;
		}
		const pct = parseInt(zoomSlider.value, 10) / 100;
		const targetRatio = state.cropMinZoom + pct * (state.cropMaxZoom - state.cropMinZoom);
		state.cropper.zoomTo(targetRatio);
		zoomValue.textContent = `${Math.round(targetRatio * 100)}%`;
	});

	saveButton.addEventListener('click', () => {
		if (!state.cropper || !state.itemId || state.imageIndex < 0) {
			return;
		}

		const cropData = state.cropper.getData(true);
		const formData = new FormData();
		formData.append('action', 'woi_admin_update_order_crop');
		formData.append('nonce', config.nonce);
		formData.append('item_id', `${state.itemId}`);
		formData.append('image_index', `${state.imageIndex}`);
		formData.append('crop[x]', `${cropData.x}`);
		formData.append('crop[y]', `${cropData.y}`);
		formData.append('crop[width]', `${cropData.width}`);
		formData.append('crop[height]', `${cropData.height}`);
		if (state.isPuzzle) {
			formData.append('puzzle_cols', `${state.currentPuzzleCols}`);
			formData.append('puzzle_rows', `${state.currentPuzzleRows}`);
		}

		saveButton.disabled = true;

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then((response) => response.json().catch(() => ({})))
			.then((result) => {
				if (!result || result.success !== true || !result.data) {
					throw new Error('save_failed');
				}
				applySaveResponse(result.data);
				closeModal();
			})
			.catch(() => {
				window.alert(config.updateFailed);
			})
			.finally(() => {
				saveButton.disabled = false;
			});
	});

	closeButtons.forEach((button) => {
		button.addEventListener('click', closeModal);
	});
})();
