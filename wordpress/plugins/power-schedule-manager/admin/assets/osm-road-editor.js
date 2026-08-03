/**
 * Named-road and boundary preview for the reusable map library.
 *
 * @package Power_Schedule_Manager
 */

'use strict';

(function () {
	const root = document.querySelector('[data-psm-osm-importer]');
	const configuration = window.PowerScheduleManagerOSM || {};

	if (!(root instanceof HTMLElement)) {
		return;
	}

	if (!window.L) {
		const unavailableMessage = root.querySelector(
			'[data-psm-osm-result]'
		);

		if (unavailableMessage instanceof HTMLElement) {
			unavailableMessage.textContent =
				'Không tải được thư viện Leaflet. Hãy kiểm tra tệp leaflet.js.';
			unavailableMessage.classList.add('is-error');
		}
		return;
	}

	const button = root.querySelector('[data-psm-osm-search]');
	const spinner = root.querySelector('[data-psm-osm-spinner]');
	const result = root.querySelector('[data-psm-osm-result]');
	const candidatesRoot = root.querySelector(
		'[data-psm-osm-candidates]'
	);
	const preview = root.querySelector('[data-psm-osm-preview]');
	const geojsonField = document.getElementById('psm-place-geojson');
	const latField = document.getElementById('psm-place-lat');
	const lngField = document.getElementById('psm-place-lng');
	const zoomField = document.getElementById('psm-place-zoom');
	const statusField = document.getElementById('psm-place-status');
	const nameField = document.getElementById('psm-place-name');
	const aliasesField = document.getElementById('psm-place-aliases');
	const roadNameField = document.getElementById('psm-osm-road-name');
	const searchTypeField = document.getElementById('psm-osm-search-type');
	const localityField = document.getElementById('psm-osm-locality');
	const localityWrap = root.querySelector('[data-psm-osm-locality-wrap]');
	const nameLabel = root.querySelector('[data-psm-osm-name-label]');
	const searchLabel = root.querySelector('[data-psm-osm-search-label]');
	const unitField = document.getElementById('psm-place-unit');
	const locationTypeField = document.getElementById('psm-place-type');
	const useMapBoundsButton = root.querySelector(
		'[data-psm-osm-use-map-bounds]'
	);
	const fields = {
		south: document.getElementById('psm-osm-south'),
		west: document.getElementById('psm-osm-west'),
		north: document.getElementById('psm-osm-north'),
		east: document.getElementById('psm-osm-east')
	};

	if (
		!(button instanceof HTMLButtonElement) ||
		!(spinner instanceof HTMLElement) ||
		!(result instanceof HTMLElement) ||
		!(preview instanceof HTMLElement) ||
		!(geojsonField instanceof HTMLTextAreaElement) ||
		!(roadNameField instanceof HTMLInputElement) ||
		!(searchTypeField instanceof HTMLSelectElement)
	) {
		return;
	}

	let map = null;
	let geometryLayer = null;
	let selectionMarker = null;
	let previewIsCurrent = false;
	let currentCandidates = [];

	function setMessage(message, isError) {
		result.textContent = message;
		result.classList.toggle('is-error', Boolean(isError));
	}

	function setLoading(loading) {
		button.disabled = loading;
		spinner.classList.toggle('is-active', loading);
	}

	function invalidatePreview() {
		if (!previewIsCurrent) {
			return;
		}

		previewIsCurrent = false;
		currentCandidates = [];
		geojsonField.value = '';
		if (geometryLayer) {
			geometryLayer.remove();
			geometryLayer = null;
		}
		initializeMap();
		setMessage(
			'Thông tin tìm kiếm đã thay đổi. Hãy tìm lại để cập nhật đúng hình học trước khi lưu.',
			false
		);
		renderCandidates([]);
	}

	function fieldValue(field) {
		return field instanceof HTMLInputElement ? field.value.trim() : '';
	}

	function setSelectedPoint(latitude, longitude) {
		if (!map) {
			return;
		}

		if (selectionMarker) {
			selectionMarker.setLatLng([latitude, longitude]);
		} else {
			selectionMarker = window.L.marker(
				[latitude, longitude],
				{
					draggable: true,
					title: 'Điểm đại diện của địa điểm'
				}
			).addTo(map);

			selectionMarker.on('dragend', function (event) {
				const position = event.target.getLatLng();
				setSelectedPoint(position.lat, position.lng);
			});
		}

		if (latField instanceof HTMLInputElement) {
			latField.value = Number(latitude).toFixed(7);
		}
		if (lngField instanceof HTMLInputElement) {
			lngField.value = Number(longitude).toFixed(7);
		}
		if (zoomField instanceof HTMLInputElement) {
			zoomField.value = String(map.getZoom());
		}
	}

	function selectedUnitBounds() {
		if (!(unitField instanceof HTMLSelectElement)) {
			return null;
		}

		const allBounds = configuration.unitBounds;
		const bounds =
			allBounds &&
			typeof allBounds === 'object' &&
			allBounds[unitField.value];

		if (!bounds || typeof bounds !== 'object') {
			return null;
		}

		const normalized = {
			south: Number(bounds.south),
			west: Number(bounds.west),
			north: Number(bounds.north),
			east: Number(bounds.east)
		};

		return Object.values(normalized).every(Number.isFinite)
			? normalized
			: null;
	}

	function applyBounds(bounds, fitMap) {
		if (!bounds) {
			return;
		}

		Object.keys(fields).forEach(function (key) {
			if (fields[key] instanceof HTMLInputElement) {
				fields[key].value = String(bounds[key]);
			}
		});

		if (fitMap && map) {
			map.fitBounds(
				[
					[bounds.south, bounds.west],
					[bounds.north, bounds.east]
				],
				{padding: [24, 24]}
			);
		}
	}

	function applyCandidate(candidate) {
		if (!candidate || typeof candidate.geojson !== 'string') {
			return;
		}

		const geometry = JSON.parse(candidate.geojson);
		renderPreview(geometry);
		geojsonField.value = candidate.geojson;
		previewIsCurrent = true;

		const searchedName = roadNameField.value.trim();
		const candidateName =
			typeof candidate.name === 'string' ? candidate.name.trim() : '';

		if (
			nameField instanceof HTMLInputElement &&
			candidateName &&
			(!nameField.value.trim() ||
				nameField.value.trim() === searchedName)
		) {
			nameField.value = candidateName;
		}

		if (aliasesField instanceof HTMLTextAreaElement) {
			const aliases = aliasesField.value
				.split(/\r?\n/)
				.map((value) => value.trim())
				.filter(Boolean);
			const additions = [
				searchedName,
				candidateName,
				...(Array.isArray(candidate.aliases) ? candidate.aliases : [])
			];
			const seen = new Set(
				aliases.map((value) => value.toLocaleLowerCase('vi'))
			);

			additions.forEach((value) => {
				const alias =
					typeof value === 'string' ? value.trim() : '';
				const key = alias.toLocaleLowerCase('vi');

				if (alias && !seen.has(key)) {
					seen.add(key);
					aliases.push(alias);
				}
			});

			aliasesField.value = aliases.join('\n');
		}
		if (locationTypeField instanceof HTMLSelectElement) {
			locationTypeField.value =
				candidate.searchType === 'area'
					? 'area'
					: 'road_segment';
		}
		setSelectedPoint(
			Number(candidate.centerLat),
			Number(candidate.centerLng)
		);

		root
			.querySelectorAll('[data-psm-osm-candidate]')
			.forEach(function (item) {
				item.classList.toggle(
					'is-selected',
					item.dataset.psmOsmCandidate === candidate.id
				);
			});
	}

	function renderCandidates(candidates) {
		if (!(candidatesRoot instanceof HTMLElement)) {
			return;
		}

		candidatesRoot.replaceChildren();
		candidatesRoot.hidden = candidates.length < 2;

		if (candidates.length < 2) {
			return;
		}

		const heading = document.createElement('strong');
		heading.textContent =
			searchTypeField.value === 'area'
				? 'Có nhiều địa giới trùng tên. Chọn đúng khu vực cần lưu:'
				: 'Có nhiều tuyến không liền nhau. Chọn đúng tuyến cần lưu:';
		candidatesRoot.appendChild(heading);

		const list = document.createElement('div');
		list.className = 'psm-osm-candidate-list';

		candidates.forEach(function (candidate, index) {
			const buttonElement = document.createElement('button');
			buttonElement.type = 'button';
			buttonElement.className = 'psm-osm-candidate';
			buttonElement.dataset.psmOsmCandidate = String(candidate.id);
			const candidateName = document.createElement('span');
			const candidateDetails = document.createElement('small');
			if (candidate.searchType === 'area') {
				candidateName.textContent =
					String(candidate.name || 'Khu vực') +
					' · ' +
					String(candidate.osmType || '').toUpperCase() +
					'/' +
					String(candidate.osmId || '');
				candidateDetails.textContent =
					String(candidate.geometryType || 'Polygon') +
					(candidate.adminLevel
						? ' · cấp ' + String(candidate.adminLevel)
						: '') +
					(candidate.boundaryType
						? ' · ' + String(candidate.boundaryType)
						: '') +
					' · ' +
					String(Number(candidate.pointCount) || 0) +
					' điểm';
			} else {
				candidateName.textContent =
					'Tuyến ' + String(index + 1);
				candidateDetails.textContent =
					String(Number(candidate.wayCount) || 0) +
					' đoạn · ' +
					String(Number(candidate.pointCount) || 0) +
					' điểm · ' +
					Number(candidate.centerLat).toFixed(4) +
					', ' +
					Number(candidate.centerLng).toFixed(4);
			}
			buttonElement.append(candidateName, candidateDetails);
			buttonElement.addEventListener('click', function () {
				applyCandidate(candidate);
				setMessage(
					'Đã chọn ' +
						(candidate.searchType === 'area'
							? 'địa giới '
							: 'tuyến ') +
						String(index + 1) +
						'. Kiểm tra hình học và điểm đại diện rồi bấm lưu địa điểm.',
					false
				);
			});
			list.appendChild(buttonElement);
		});

		candidatesRoot.appendChild(list);
	}

	function initializeMap() {
		preview.hidden = false;

		if (!map) {
			map = window.L.map(preview, {
				scrollWheelZoom: false
			});
			window.L.tileLayer(
				String(
					configuration.tileUrl ||
					'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
				),
				{
					maxZoom:
						Number(configuration.maxZoom) || 19,
					tileSize:
						Number(configuration.tileSize) || 256,
					zoomOffset:
						Number(configuration.zoomOffset) || 0,
					crossOrigin:
						configuration.crossOrigin === true,
					attribution: String(
						configuration.attribution ||
						'&copy; OpenStreetMap contributors'
					)
				}
			).addTo(map);

			map.on('click', function (event) {
				setSelectedPoint(event.latlng.lat, event.latlng.lng);
				setMessage(
					'Đã chọn điểm đại diện. Kéo ghim để điều chỉnh rồi bấm lưu.',
					false
				);
			});

			const savedLatitude =
				latField instanceof HTMLInputElement &&
				latField.value.trim() !== ''
					? Number(latField.value)
					: Number.NaN;
			const savedLongitude =
				lngField instanceof HTMLInputElement &&
				lngField.value.trim() !== ''
					? Number(lngField.value)
					: Number.NaN;
			const unitBounds = selectedUnitBounds();

			if (
				unitBounds &&
				!fieldValue(fields.south) &&
				!fieldValue(fields.west) &&
				!fieldValue(fields.north) &&
				!fieldValue(fields.east)
			) {
				applyBounds(unitBounds, false);
			}

			const boundValues = {
				south: fieldValue(fields.south),
				west: fieldValue(fields.west),
				north: fieldValue(fields.north),
				east: fieldValue(fields.east)
			};
			const south = Number(boundValues.south);
			const west = Number(boundValues.west);
			const north = Number(boundValues.north);
			const east = Number(boundValues.east);

			if (
				Number.isFinite(savedLatitude) &&
				Number.isFinite(savedLongitude)
			) {
				map.setView(
					[savedLatitude, savedLongitude],
					zoomField instanceof HTMLInputElement
						? Number(zoomField.value) || 15
						: 15
				);
				setSelectedPoint(savedLatitude, savedLongitude);
			} else if (
				Object.values(boundValues).every(function (value) {
					return value !== '';
				}) &&
				[south, west, north, east].every(Number.isFinite)
			) {
				map.fitBounds(
					[
						[south, west],
						[north, east]
					],
					{ padding: [20, 20] }
				);
			} else {
				map.setView([11.9404, 108.4583], 12);
			}
		}

		window.setTimeout(function () {
			map.invalidateSize();
		}, 0);
	}

	function renderPreview(geojson) {
		initializeMap();

		if (geometryLayer) {
			geometryLayer.remove();
		}

		geometryLayer = window.L.geoJSON(geojson, {
			style: {
				color: '#2563eb',
				weight: searchTypeField.value === 'area' ? 3 : 6,
				opacity: 0.9,
				fillColor: '#60a5fa',
				fillOpacity:
					searchTypeField.value === 'area' ? 0.2 : 0
			}
		}).addTo(map);
		map.fitBounds(geometryLayer.getBounds(), {
			padding: [24, 24],
			maxZoom: 17
		});

		if (
			latField instanceof HTMLInputElement &&
			lngField instanceof HTMLInputElement &&
			Number.isFinite(Number(latField.value)) &&
			Number.isFinite(Number(lngField.value))
		) {
			setSelectedPoint(
				Number(latField.value),
				Number(lngField.value)
			);
		}
		window.setTimeout(function () {
			map.invalidateSize();
		}, 0);
	}

	/**
	 * Render geometry already saved in the place library.
	 *
	 * This makes the editor a useful read-only map viewer without requiring
	 * another Overpass request.
	 *
	 * @return {void}
	 */
	function renderSavedGeometry() {
		const savedGeojson = geojsonField.value.trim();

		try {
			if (savedGeojson) {
				renderPreview(JSON.parse(savedGeojson));
				previewIsCurrent = true;
			} else if (
				latField instanceof HTMLInputElement &&
				lngField instanceof HTMLInputElement &&
				latField.value.trim() !== '' &&
				lngField.value.trim() !== '' &&
				Number.isFinite(Number(latField.value)) &&
				Number.isFinite(Number(lngField.value))
			) {
				renderPreview({
					type: 'Point',
					coordinates: [
						Number(lngField.value),
						Number(latField.value)
					]
				});
				previewIsCurrent = true;
			} else {
				initializeMap();
				setMessage(
					'Bản đồ đã sẵn sàng. Hãy tìm tuyến/địa giới OSM hoặc nhấp lên bản đồ để chọn điểm đại diện.',
					false
				);
				return;
			}

			setMessage(
				'Đang hiển thị dữ liệu bản đồ đã lưu.',
				false
			);

			if (root.dataset.autoOpen === '1') {
				window.requestAnimationFrame(function () {
					preview.scrollIntoView({
						behavior: 'smooth',
						block: 'center'
					});
				});
			}
		} catch (error) {
			setMessage(
				'Dữ liệu bản đồ đã lưu không hợp lệ. Hãy tìm lại tuyến hoặc địa giới.',
				true
			);
		}
	}

	function previewManualGeojson() {
		const value = geojsonField.value.trim();

		if (!value) {
			if (geometryLayer) {
				geometryLayer.remove();
				geometryLayer = null;
			}
			initializeMap();
			setMessage(
				'GeoJSON đã được xóa. Bạn vẫn có thể chọn một điểm đại diện trên bản đồ.',
				false
			);
			return;
		}

		try {
			renderPreview(JSON.parse(value));
			previewIsCurrent = true;
			setMessage(
				'Đã cập nhật bản đồ từ GeoJSON đang nhập.',
				false
			);
		} catch (error) {
			setMessage(
				'GeoJSON chưa hợp lệ nên chưa thể hiển thị trên bản đồ.',
				true
			);
		}
	}

	function debounce(callback, delay) {
		let timeoutId = 0;

		return function () {
			window.clearTimeout(timeoutId);
			timeoutId = window.setTimeout(callback, delay);
		};
	}

	function updateSearchMode() {
		const isArea = searchTypeField.value === 'area';
		root.classList.toggle('is-area-search', isArea);

		if (localityWrap instanceof HTMLElement) {
			localityWrap.hidden = !isArea;
		}
		if (nameLabel instanceof HTMLElement) {
			nameLabel.textContent = isArea
				? 'Tên địa giới trên OSM'
				: 'Tên đường trên OSM';
		}
		if (searchLabel instanceof HTMLElement) {
			searchLabel.textContent = isArea
				? 'Tìm và khoanh địa giới'
				: 'Tìm và xem toàn tuyến';
		}
		roadNameField.placeholder = isArea
			? 'Ví dụ: Lộc Châu'
			: 'Ví dụ: Nguyễn Tri Phương';
	}

	button.addEventListener('click', async function () {
		const roadName = roadNameField.value.trim();
		const isArea = searchTypeField.value === 'area';

		if (
			unitField instanceof HTMLSelectElement &&
			!unitField.value
		) {
			setMessage(
				'Hãy chọn đơn vị điện lực để giới hạn đúng khu vực tìm kiếm.',
				true
			);
			unitField.focus();
			return;
		}

		if (!roadName) {
			setMessage(
				isArea
					? 'Hãy nhập tên địa giới trên OpenStreetMap.'
					: 'Hãy nhập tên đường trên OpenStreetMap.',
				true
			);
			roadNameField.focus();
			return;
		}

		const request = new URLSearchParams({
			action: String(configuration.action || ''),
			nonce: String(configuration.nonce || ''),
			unit_code:
				unitField instanceof HTMLSelectElement
					? unitField.value
					: '',
			search_type: searchTypeField.value,
			locality:
				localityField instanceof HTMLInputElement
					? localityField.value.trim()
					: '',
			road_name: roadName,
			south: fieldValue(fields.south),
			west: fieldValue(fields.west),
			north: fieldValue(fields.north),
			east: fieldValue(fields.east)
		});

		setLoading(true);
		setMessage(
			isArea
				? 'Đang lấy relation/way và dựng ranh giới…'
				: 'Đang lấy tất cả đoạn đường cùng tên…',
			false
		);

		try {
			const response = await fetch(
				String(configuration.ajaxUrl || ''),
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type':
							'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: request.toString()
				}
			);
			const payload = await response.json();

			if (!response.ok || !payload.success || !payload.data) {
				throw new Error(
					payload.data && payload.data.message
						? payload.data.message
						: 'Không thể lấy dữ liệu OSM.'
				);
			}

			const data = payload.data;
			currentCandidates = Array.isArray(data.candidates)
				? data.candidates
				: [data];
			renderCandidates(currentCandidates);
			applyCandidate(currentCandidates[0]);

			if (zoomField instanceof HTMLInputElement) {
				zoomField.value = '15';
			}
			if (statusField instanceof HTMLSelectElement) {
				statusField.value = 'active';
			}
			if (
				nameField instanceof HTMLInputElement &&
				!nameField.value.trim()
			) {
				nameField.value = isArea
					? String(currentCandidates[0].name || roadName)
					: 'Đường ' + roadName;
			}

			setMessage(
				'Đã tìm thấy ' +
					String(currentCandidates.length) +
					(isArea ? ' địa giới, tổng cộng ' : ' tuyến, tổng cộng ') +
					String(data.pointCount) +
					' điểm OSM. Hãy chọn và kiểm tra đúng kết quả trước khi lưu.',
				false
			);
		} catch (error) {
			setMessage(
				error instanceof Error
					? error.message
					: 'Không thể lấy dữ liệu OSM.',
				true
			);
		} finally {
			setLoading(false);
		}
	});

	if (unitField instanceof HTMLSelectElement) {
		unitField.addEventListener('change', function () {
			const bounds = selectedUnitBounds();

			if (!bounds) {
				return;
			}

			invalidatePreview();
			applyBounds(bounds, true);
			setMessage(
				'Đã đặt phạm vi tìm kiếm theo đơn vị điện lực.',
				false
			);
		});
	}

	searchTypeField.addEventListener('change', function () {
		invalidatePreview();
		updateSearchMode();
	});

	if (useMapBoundsButton instanceof HTMLButtonElement) {
		useMapBoundsButton.addEventListener('click', function () {
			if (!map) {
				return;
			}

			const bounds = map.getBounds();
			const selectedBounds = {
				south: Number(bounds.getSouth().toFixed(7)),
				west: Number(bounds.getWest().toFixed(7)),
				north: Number(bounds.getNorth().toFixed(7)),
				east: Number(bounds.getEast().toFixed(7))
			};

			if (
				selectedBounds.north - selectedBounds.south > 1.5 ||
				selectedBounds.east - selectedBounds.west > 1.5
			) {
				setMessage(
					'Vùng bản đồ đang quá rộng. Hãy phóng to khu vực cần tìm.',
					true
				);
				return;
			}

			invalidatePreview();
			applyBounds(selectedBounds, false);
			setMessage(
				'Đã dùng vùng bản đồ hiện tại làm phạm vi tìm kiếm OSM.',
				false
			);
		});
	}

	[
		roadNameField,
		localityField,
		fields.south,
		fields.west,
		fields.north,
		fields.east
	].forEach(function (field) {
		if (
			field instanceof HTMLInputElement
		) {
			field.addEventListener('input', invalidatePreview);
		}
	});

	geojsonField.addEventListener(
		'input',
		debounce(previewManualGeojson, 450)
	);

	initializeMap();
	updateSearchMode();
	renderSavedGeometry();
}());
