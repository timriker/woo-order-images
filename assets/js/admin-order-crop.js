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
	const rotateButton = modal.querySelector('[data-woi-admin-rotate]');
	const swapButton = modal.querySelector('[data-woi-admin-swap]');
	const zoomSlider = modal.querySelector('[data-woi-admin-zoom-slider]');
	const zoomValue = modal.querySelector('[data-woi-admin-zoom-value]');
	const puzzleGrid = modal.querySelector('[data-woi-admin-puzzle-grid]');

	if (!modalImage || !saveButton || !rotateButton || !swapButton || !zoomSlider || !zoomValue || !puzzleGrid) {
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
		currentRotation: 0,
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

	// Info modal references
	const infoModal = document.querySelector('[data-woi-admin-info-modal]');
	const infoTitle = infoModal ? infoModal.querySelector('[data-woi-admin-info-title]') : null;
	const infoLoadingEl = infoModal ? infoModal.querySelector('[data-woi-admin-info-loading]') : null;
	const infoTableEl = infoModal ? infoModal.querySelector('[data-woi-admin-info-table]') : null;
	const infoErrorEl = infoModal ? infoModal.querySelector('[data-woi-admin-info-error]') : null;

	const normalizeOrientation = (orientation) => orientation === 'portrait' ? 'portrait' : 'landscape';

	const normalizeRotation = (rotation) => {
		const normalized = rotation % 360;
		return normalized < 0 ? normalized + 360 : normalized;
	};

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

	const applySavedCropViewport = (cropper) => {
		if (!cropper || !state.currentCrop || state.currentCrop.width <= 0 || state.currentCrop.height <= 0) {
			return false;
		}

		const cropBox = cropper.getCropBoxData();
		if (!cropBox || cropBox.width <= 0 || cropBox.height <= 0) {
			return false;
		}

		const cropWidth = parseFloat(state.currentCrop.width || 0);
		const cropHeight = parseFloat(state.currentCrop.height || 0);
		if (cropWidth <= 0 || cropHeight <= 0) {
			return false;
		}

		// Rebuild the image viewport directly from the saved crop so reopening
		// lands on the authoritative stored crop rather than a centered default.
		const ratioX = cropBox.width / cropWidth;
		const ratioY = cropBox.height / cropHeight;
		const targetRatio = Math.min(ratioX, ratioY);

		if (!(targetRatio > 0)) {
			return false;
		}

		cropper.zoomTo(targetRatio);

		const targetLeft = cropBox.left - (parseFloat(state.currentCrop.x || 0) * targetRatio);
		const targetTop = cropBox.top - (parseFloat(state.currentCrop.y || 0) * targetRatio);
		cropper.moveTo(targetLeft, targetTop);

		return true;
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
		state.currentRotation = 0;
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

				const effectiveRotation = normalizeRotation(state.currentRotation || 0);
				if (effectiveRotation !== 0) {
					cropper.rotateTo(effectiveRotation);
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

				const applyZoomBounds = () => {
					if (!state.cropper) {
						return;
					}

					const hasSavedCrop = applySavedCropViewport(cropper);
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
		state.currentRotation = normalizeRotation(parseInt(button.getAttribute('data-image-rotation') || '0', 10));
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

	const refreshStateFromServer = async (button) => {
		if (!button) {
			return;
		}

		const itemId = parseInt(button.getAttribute('data-item-id') || '0', 10);
		const imageIndex = parseInt(button.getAttribute('data-image-index') || '-1', 10);
		if (!itemId || imageIndex < 0) {
			return;
		}

		const formData = new FormData();
		formData.append('action', 'woi_admin_get_order_crop_state');
		formData.append('nonce', config.stateNonce || '');
		formData.append('item_id', `${itemId}`);
		formData.append('image_index', `${imageIndex}`);

		const response = await fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});
		const result = await response.json().catch(() => ({}));

		if (!response.ok || !result || result.success !== true || !result.data) {
			throw new Error('load_failed');
		}

		const data = result.data;
		if (data.imageUrl) {
			button.setAttribute('data-image-url', data.imageUrl);
		}
		button.setAttribute('data-image-crop', JSON.stringify(data.crop || {}));
		button.setAttribute('data-image-rotation', `${normalizeRotation(data.rotation || 0)}`);
		if (typeof data.currentOrientation === 'string' && data.currentOrientation) {
			button.setAttribute('data-current-orientation', data.currentOrientation);
		}
		if (typeof data.puzzleCols !== 'undefined') {
			button.setAttribute('data-current-puzzle-cols', `${data.puzzleCols}`);
		}
		if (typeof data.puzzleRows !== 'undefined') {
			button.setAttribute('data-current-puzzle-rows', `${data.puzzleRows}`);
		}
	};

	const applySaveResponse = (result) => {
		if (!state.activeButton) {
			return;
		}

		const thumbRoot = state.activeButton.parentElement || state.activeButton;
		const frame = thumbRoot ? thumbRoot.querySelector('[data-woi-admin-thumb-frame]') : null;
		const image = thumbRoot
			? (thumbRoot.querySelector('[data-woi-admin-thumb-image]') || thumbRoot.querySelector('.woi-admin-thumb-image'))
			: null;
		if (frame && result.visibleAspectRatio) {
			frame.style.aspectRatio = `${result.visibleAspectRatio}`;
		}
		if (image && result.thumbSrc) {
			image.src = result.thumbSrc;
		}

		state.activeButton.setAttribute('data-image-crop', JSON.stringify(result.crop || {}));
		state.activeButton.setAttribute('data-image-rotation', `${normalizeRotation(result.rotation || 0)}`);
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

	const formatFileSize = (bytes) => {
		if (bytes === null || bytes === undefined) return '—';
		if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
		if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
		return `${bytes} B`;
	};

	const fillInfoField = (key, value) => {
		const el = infoTableEl ? infoTableEl.querySelector(`[data-woi-info="${key}"]`) : null;
		if (el) el.textContent = (value !== null && value !== undefined && value !== '') ? value : '—';
	};

	const setInfoRowVisibility = (key, visible) => {
		const el = infoTableEl ? infoTableEl.querySelector(`[data-woi-info="${key}"]`) : null;
		const row = el ? el.closest('tr') : null;
		if (row) {
			row.hidden = !visible;
		}
	};

	const fillInfoFieldWithLink = (key, label, href) => {
		const el = infoTableEl ? infoTableEl.querySelector(`[data-woi-info="${key}"]`) : null;
		if (!el) {
			return;
		}

		el.textContent = '';
		if (!label || !href) {
			el.textContent = '—';
			return;
		}

		const link = document.createElement('a');
		link.href = href;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = label;
		el.appendChild(link);
	};

	const openInfoModal = (trigger) => {
		if (!infoModal) return;

		const imageLabel = trigger.getAttribute('data-image-label') || 'Image';
		if (infoTitle) infoTitle.textContent = `${imageLabel} — Info`;

		if (infoLoadingEl) infoLoadingEl.hidden = false;
		if (infoTableEl) infoTableEl.hidden = true;
		if (infoErrorEl) { infoErrorEl.hidden = true; infoErrorEl.textContent = ''; }

		infoModal.hidden = false;

		const formData = new FormData();
		formData.append('action', 'woi_admin_image_info');
		formData.append('nonce', config.infoNonce || '');
		formData.append('item_id', trigger.getAttribute('data-item-id') || '0');
		formData.append('image_index', trigger.getAttribute('data-image-index') || '0');

		fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
			.then((r) => r.json())
			.then((result) => {
				if (!result || !result.success) {
					throw new Error((result && result.data && result.data.message) || 'Failed to load image info');
				}
				const d = result.data;

				fillInfoFieldWithLink('filename', d.filename, d.image_url);
				fillInfoField('file_size', d.file_size !== null ? formatFileSize(d.file_size) : null);
				fillInfoField('file_modified', d.file_modified);
				fillInfoField('raw_dims', (d.raw_width && d.raw_height) ? `${d.raw_width} × ${d.raw_height} px` : null);

				fillInfoField('exif_orientation', `${d.exif_orientation} — ${d.exif_desc}`);
				setInfoRowVisibility('exif_date', !!d.exif_date);
				fillInfoField('exif_date', d.exif_date);
				if (d.gps_lat !== null && d.gps_lat !== undefined && d.gps_lng !== null && d.gps_lng !== undefined) {
					const lat = Number(d.gps_lat).toFixed(6);
					const lng = Number(d.gps_lng).toFixed(6);
					setInfoRowVisibility('gps_location', true);
					fillInfoFieldWithLink('gps_location', `${lat}, ${lng}`, `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}`);
				} else {
					setInfoRowVisibility('gps_location', false);
					fillInfoField('gps_location', null);
				}
				fillInfoField('woi_rotation', d.woi_rotation ? `${d.woi_rotation}°` : '0° (none)');
				const rawDimsLabel = (d.raw_width && d.raw_height) ? `${d.raw_width} × ${d.raw_height} px` : null;
				const effDimsLabel = (d.eff_width && d.eff_height) ? `${d.eff_width} × ${d.eff_height} px` : null;
				const showEffDims = !!effDimsLabel && effDimsLabel !== rawDimsLabel;
				setInfoRowVisibility('eff_dims', showEffDims);
				fillInfoField('eff_dims', showEffDims ? effDimsLabel : null);

				const cx = d.crop_x !== null ? Math.round(d.crop_x) : null;
				const cy = d.crop_y !== null ? Math.round(d.crop_y) : null;
				fillInfoField('crop_origin', (cx !== null && cy !== null) ? `${cx}, ${cy}` : null);
				fillInfoField('crop_size', (d.crop_w && d.crop_h) ? `${Math.round(d.crop_w)} × ${Math.round(d.crop_h)} px` : null);
				fillInfoField('crop_aspect', d.crop_aspect ? `${d.crop_aspect} : 1` : null);

				const visLabel = (d.product_vis_w && d.product_vis_h)
					? `${d.product_vis_w}" × ${d.product_vis_h}"` : null;
				fillInfoField('product_vis', visLabel);
				const fullLabel = (d.product_full_w && d.product_full_h)
					? `${d.product_full_w}" × ${d.product_full_h}"${d.wrap_margin ? ` (${d.wrap_margin}" bleed each side)` : ''}` : null;
				fillInfoField('product_full', fullLabel);
				fillInfoField('product_aspect', d.product_aspect ? `${d.product_aspect} : 1` : null);

				const dpiEl = infoTableEl ? infoTableEl.querySelector('[data-woi-info="print_dpi"]') : null;
				if (dpiEl) {
					if (d.print_dpi !== null && d.dpi_quality) {
						const qualityLabels = { excellent: 'Excellent', good: 'Good', fair: 'Fair', low: 'Low — consider a higher resolution photo' };
						dpiEl.textContent = '';
						const dpiSpan = document.createElement('span');
						dpiSpan.className = `woi-admin-dpi-${d.dpi_quality}`;
						dpiSpan.textContent = `${d.print_dpi} DPI — ${qualityLabels[d.dpi_quality] || d.dpi_quality}`;
						dpiEl.appendChild(dpiSpan);
					} else {
						dpiEl.textContent = '—';
					}
				}

				if (infoLoadingEl) infoLoadingEl.hidden = true;
				if (infoTableEl) infoTableEl.hidden = false;
			})
			.catch((err) => {
				if (infoLoadingEl) infoLoadingEl.hidden = true;
				if (infoErrorEl) {
					infoErrorEl.textContent = (err && err.message) ? err.message : 'Failed to load image info.';
					infoErrorEl.hidden = false;
				}
			});
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

		const infoButton = document.createElement('button');
		infoButton.type = 'button';
		infoButton.className = 'woi-admin-image-menu-item';
		infoButton.setAttribute('role', 'menuitem');
		infoButton.textContent = config.infoLabel || 'Show Info';

		menu.appendChild(viewButton);
		menu.appendChild(editButton);
		menu.appendChild(infoButton);

		return { menu, viewButton, editButton, infoButton };
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

		const { menu, viewButton, editButton, infoButton } = createMenuElement();
		const imageUrl = trigger.getAttribute('data-image-url');

		viewButton.addEventListener('click', () => {
			window.open(imageUrl, '_blank', 'noopener,noreferrer');
			closeAllMenus();
		});

		editButton.addEventListener('click', () => {
			refreshStateFromServer(trigger)
				.then(() => {
					hydrateStateFromButton(trigger);
					openModal();
				})
				.catch(() => {
					window.alert(config.loadFailed || 'Unable to load the latest saved crop right now.');
				})
				.finally(() => {
					closeAllMenus();
				});
		});

		infoButton.addEventListener('click', () => {
			closeAllMenus();
			openInfoModal(trigger);
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

	rotateButton.addEventListener('click', () => {
		if (state.imageIndex < 0) {
			return;
		}

		state.currentRotation = normalizeRotation((state.currentRotation || 0) + 90);
		state.currentCrop = null;
		openModal();
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
		formData.append('rotation', `${normalizeRotation(state.currentRotation || 0)}`);
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

	// Info modal close handlers
	if (infoModal) {
		infoModal.querySelectorAll('[data-woi-admin-info-close]').forEach((btn) => {
			btn.addEventListener('click', () => {
				infoModal.hidden = true;
			});
		});

		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && !infoModal.hidden) {
				infoModal.hidden = true;
			}
		});
	}
})();
