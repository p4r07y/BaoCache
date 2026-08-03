/**
 * Cúp Điện Lâm Đồng frontend interactions.
 *
 * @package Power_Schedule_Manager
 */

'use strict';

(function () {
	const configuration =
		window.PowerScheduleManager || {};

	const mapConfiguration =
		configuration.map &&
		typeof configuration.map === 'object'
			? configuration.map
			: {};

	const strings =
		configuration.strings &&
		typeof configuration.strings === 'object'
			? configuration.strings
			: {};

	const mapInstances = new WeakMap();
	const modalStates = new WeakMap();

	let leafletPromise = null;

	/**
	 * Return a localized string.
	 *
	 * @param {string} key      String key.
	 * @param {string} fallback Fallback.
	 * @return {string}
	 */
	function getString(key, fallback) {
		return typeof strings[key] === 'string'
			? strings[key]
			: fallback;
	}

	/**
	 * Validate an HTTP or HTTPS URL.
	 *
	 * @param {unknown} value URL value.
	 * @return {string}
	 */
	function validUrl(value) {
		if (typeof value !== 'string' || !value) {
			return '';
		}

		try {
			const url = new URL(
				value,
				window.location.origin
			);

			if (
				url.protocol !== 'http:' &&
				url.protocol !== 'https:'
			) {
				return '';
			}

			return url.href;
		} catch (error) {
			return '';
		}
	}

	/**
	 * Load a stylesheet once.
	 *
	 * @param {string} url Stylesheet URL.
	 * @return {Promise<void>}
	 */
	function loadStylesheet(url) {
		return new Promise(function (resolve, reject) {
			const normalizedUrl = validUrl(url);

			if (!normalizedUrl) {
				reject(
					new Error('invalid_leaflet_css_url')
				);
				return;
			}

			const existing = Array.from(
				document.querySelectorAll(
					'link[rel="stylesheet"]'
				)
			).find(function (link) {
				return link.href === normalizedUrl;
			});

			if (existing instanceof HTMLLinkElement) {
				resolve();
				return;
			}

			const link = document.createElement('link');

			link.rel = 'stylesheet';
			link.href = normalizedUrl;
			link.dataset.psmLeafletCss = '1';

			link.addEventListener(
				'load',
				function () {
					resolve();
				},
				{ once: true }
			);

			link.addEventListener(
				'error',
				function () {
					reject(
						new Error(
							'leaflet_css_load_failed'
						)
					);
				},
				{ once: true }
			);

			document.head.appendChild(link);
		});
	}

	/**
	 * Load a script once.
	 *
	 * @param {string} url Script URL.
	 * @return {Promise<void>}
	 */
	function loadScript(url) {
		return new Promise(function (resolve, reject) {
			const normalizedUrl = validUrl(url);

			if (!normalizedUrl) {
				reject(
					new Error('invalid_leaflet_js_url')
				);
				return;
			}

			if (
				window.L &&
				typeof window.L.map === 'function'
			) {
				resolve();
				return;
			}

			const existing = Array.from(
				document.querySelectorAll('script[src]')
			).find(function (script) {
				return script.src === normalizedUrl;
			});

			if (existing instanceof HTMLScriptElement) {
				existing.addEventListener(
					'load',
					function () {
						resolve();
					},
					{ once: true }
				);

				existing.addEventListener(
					'error',
					function () {
						reject(
							new Error(
								'leaflet_js_load_failed'
							)
						);
					},
					{ once: true }
				);

				return;
			}

			const script = document.createElement('script');

			script.src = normalizedUrl;
			script.async = true;
			script.dataset.psmLeafletJs = '1';

			script.addEventListener(
				'load',
				function () {
					if (
						window.L &&
						typeof window.L.map ===
							'function'
					) {
						resolve();
						return;
					}

					reject(
						new Error(
							'leaflet_global_missing'
						)
					);
				},
				{ once: true }
			);

			script.addEventListener(
				'error',
				function () {
					reject(
						new Error(
							'leaflet_js_load_failed'
						)
					);
				},
				{ once: true }
			);

			document.head.appendChild(script);
		});
	}

	/**
	 * Load Leaflet assets on demand.
	 *
	 * @return {Promise<Object>}
	 */
	function loadLeaflet() {
		if (
			window.L &&
			typeof window.L.map === 'function'
		) {
			return Promise.resolve(window.L);
		}

		if (leafletPromise) {
			return leafletPromise;
		}

		const cssUrl = validUrl(
			mapConfiguration.leafletCss
		);

		const jsUrl = validUrl(
			mapConfiguration.leafletJs
		);

		leafletPromise = Promise.all([
			loadStylesheet(cssUrl),
			loadScript(jsUrl)
		]).then(function () {
			if (
				!window.L ||
				typeof window.L.map !== 'function'
			) {
				throw new Error(
					'leaflet_initialization_failed'
				);
			}

			return window.L;
		}).catch(function (error) {
			leafletPromise = null;
			throw error;
		});

		return leafletPromise;
	}

	/**
	 * Return the modal belonging to a trigger.
	 *
	 * @param {HTMLElement} trigger Map trigger.
	 * @return {HTMLElement|null}
	 */
	function findModal(trigger) {
		const schedule = trigger.closest('.psm-schedule');

		if (schedule instanceof HTMLElement) {
			const localModal = schedule.querySelector(
				'[data-psm-map-modal]'
			);

			if (localModal instanceof HTMLElement) {
				return localModal;
			}
		}

		const modal = document.querySelector(
			'[data-psm-map-modal]'
		);

		return modal instanceof HTMLElement
			? modal
			: null;
	}

	/**
	 * Return focusable elements inside a modal.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @return {HTMLElement[]}
	 */
	function focusableElements(modal) {
		return Array.from(
			modal.querySelectorAll(
				[
					'a[href]',
					'button:not([disabled])',
					'input:not([disabled])',
					'select:not([disabled])',
					'textarea:not([disabled])',
					'[tabindex]:not([tabindex="-1"])'
				].join(',')
			)
		).filter(function (element) {
			return element instanceof HTMLElement
				&& !element.hidden
				&& element.offsetParent !== null;
		});
	}

	/**
	 * Trap keyboard focus inside an open modal.
	 *
	 * @param {KeyboardEvent} event Keyboard event.
	 * @param {HTMLElement} modal Modal.
	 * @return {void}
	 */
	function trapFocus(event, modal) {
		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal(modal);
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		const focusable = focusableElements(modal);

		if (focusable.length === 0) {
			event.preventDefault();
			return;
		}

		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		if (
			event.shiftKey &&
			document.activeElement === first
		) {
			event.preventDefault();
			last.focus();
			return;
		}

		if (
			!event.shiftKey &&
			document.activeElement === last
		) {
			event.preventDefault();
			first.focus();
		}
	}

	/**
	 * Set map modal status.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @param {string} message Status message.
	 * @param {boolean} isError Whether this is an error.
	 * @return {void}
	 */
	function setStatus(modal, message, isError) {
		const status = modal.querySelector(
			'[data-psm-map-status]'
		);

		if (!(status instanceof HTMLElement)) {
			return;
		}

		status.textContent = message;
		status.classList.toggle(
			'psm-map-status--error',
			isError === true
		);
	}

	/**
	 * Open a map modal.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @param {HTMLElement} trigger Trigger.
	 * @return {void}
	 */
	function openModal(modal, trigger) {
		const previousState = modalStates.get(modal);

		if (
			previousState &&
			previousState.controller instanceof
				AbortController
		) {
			previousState.controller.abort();
		}

		const controller = new AbortController();

		modalStates.set(
			modal,
			{
				trigger: trigger,
				controller: controller,
				keyHandler: null
			}
		);

		modal.hidden = false;
		document.body.classList.add('psm-map-open');
		trigger.setAttribute('aria-expanded', 'true');

		const dialog = modal.querySelector(
			'.psm-map-modal__dialog'
		);

		const keyHandler = function (event) {
			trapFocus(event, modal);
		};

		modal.addEventListener(
			'keydown',
			keyHandler
		);

		const state = modalStates.get(modal);

		if (state) {
			state.keyHandler = keyHandler;
			modalStates.set(modal, state);
		}

		window.requestAnimationFrame(function () {
			if (dialog instanceof HTMLElement) {
				dialog.focus();
			}
		});
	}

	/**
	 * Close a map modal.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @return {void}
	 */
	function closeModal(modal) {
		const state = modalStates.get(modal);

		if (
			state &&
			state.controller instanceof
				AbortController
		) {
			state.controller.abort();
		}

		if (
			state &&
			typeof state.keyHandler === 'function'
		) {
			modal.removeEventListener(
				'keydown',
				state.keyHandler
			);
		}

		modal.hidden = true;

		const openModals = document.querySelectorAll(
			'[data-psm-map-modal]:not([hidden])'
		);

		if (openModals.length === 0) {
			document.body.classList.remove(
				'psm-map-open'
			);
		}

		if (
			state &&
			state.trigger instanceof HTMLElement
		) {
			state.trigger.setAttribute(
				'aria-expanded',
				'false'
			);

			state.trigger.focus();
		}

		modalStates.delete(modal);
	}

	/**
	 * Fetch map data.
	 *
	 * @param {string} url REST URL.
	 * @param {AbortSignal} signal Abort signal.
	 * @return {Promise<Object>}
	 */
	async function fetchMapData(url, signal) {
		const normalizedUrl = validUrl(url);

		if (!normalizedUrl) {
			throw new Error('invalid_map_url');
		}

		const response = await window.fetch(
			normalizedUrl,
			{
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json'
				},
				signal: signal
			}
		);

		if (!response.ok) {
			throw new Error(
				'map_http_' + String(response.status)
			);
		}

		const data = await response.json();

		if (
			!data ||
			typeof data !== 'object' ||
			!Array.isArray(data.locations)
		) {
			throw new Error('invalid_map_response');
		}

		return data;
	}

	/**
	 * Create safe popup content.
	 *
	 * @param {Object} location Location.
	 * @return {HTMLElement}
	 */
	function createPopup(location) {
		const wrapper = document.createElement('div');
		const title = document.createElement('strong');

		title.textContent =
			typeof location.label === 'string'
				? location.label
				: getString(
					'mapLabel',
					'Khu vực có lịch cúp điện'
				);

		wrapper.appendChild(title);

		if (
			typeof location.description === 'string' &&
			location.description
		) {
			const description =
				document.createElement('p');

			description.textContent =
				location.description;

			wrapper.appendChild(description);
		}

		return wrapper;
	}

	/**
	 * Add location labels below the map.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @param {Object[]} locations Locations.
	 * @return {void}
	 */
	function renderLocationList(modal, locations) {
		const section = modal.querySelector(
			'[data-psm-map-locations]'
		);

		const list = modal.querySelector(
			'[data-psm-map-location-list]'
		);

		if (
			!(section instanceof HTMLElement) ||
			!(list instanceof HTMLElement)
		) {
			return;
		}

		list.replaceChildren();

		locations.forEach(function (location) {
			if (
				!location ||
				typeof location !== 'object'
			) {
				return;
			}

			const item = document.createElement('li');
			const label =
				typeof location.label === 'string'
					? location.label.trim()
					: '';

			item.textContent = label || getString(
				'mapLabel',
				'Khu vực có lịch cúp điện'
			);

			list.appendChild(item);
		});

		section.hidden = list.children.length === 0;
	}

	/**
	 * Initialize or reset one Leaflet map.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @param {Object} L Leaflet.
	 * @return {Object}
	 */
	function prepareMap(modal, L) {
		const canvas = modal.querySelector(
			'[data-psm-map-canvas]'
		);

		if (!(canvas instanceof HTMLElement)) {
			throw new Error('map_canvas_missing');
		}

		let record = mapInstances.get(canvas);

		if (!record) {
			const map = L.map(
				canvas,
				{
					scrollWheelZoom: false,
					preferCanvas: true
				}
			);

			const tileUrl =
				typeof mapConfiguration.tileUrl ===
					'string'
					? mapConfiguration.tileUrl
					: '';

			if (!tileUrl) {
				throw new Error('map_tile_url_missing');
			}

			const maxZoom = Number.isInteger(
				mapConfiguration.maxZoom
			)
				? Math.min(
					20,
					Math.max(
						1,
						mapConfiguration.maxZoom
					)
				)
				: 18;

			L.tileLayer(
				tileUrl,
				{
					maxZoom: maxZoom,
					tileSize: Number.isInteger(
						mapConfiguration.tileSize
					)
						? mapConfiguration.tileSize
						: 256,
					zoomOffset: Number.isInteger(
						mapConfiguration.zoomOffset
					)
						? mapConfiguration.zoomOffset
						: 0,
					crossOrigin:
						mapConfiguration.crossOrigin === true,
					attribution:
						typeof mapConfiguration.attribution
							=== 'string'
							? mapConfiguration.attribution
							: ''
				}
			).addTo(map);

			const dataLayer = L.layerGroup().addTo(map);

			record = {
				map: map,
				dataLayer: dataLayer
			};

			mapInstances.set(canvas, record);
		}

		record.dataLayer.clearLayers();

		window.requestAnimationFrame(function () {
			record.map.invalidateSize();
		});

		return record;
	}

	/**
	 * Return public map colors for an event status.
	 *
	 * @param {string} status Event status.
	 * @return {Object}
	 */
	function mapColorsForStatus(status) {
		switch (status) {
			case 'ongoing':
				return {
					stroke: '#dc2626',
					fill: '#fca5a5'
				};
			case 'completed':
				return {
					stroke: '#16a34a',
					fill: '#86efac'
				};
			case 'cancelled':
				return {
					stroke: '#7c3aed',
					fill: '#c4b5fd'
				};
			case 'removed':
				return {
					stroke: '#475569',
					fill: '#cbd5e1'
				};
			case 'scheduled':
			default:
				return {
					stroke: '#2563eb',
					fill: '#93c5fd'
				};
		}
	}

	/**
	 * Add one location to Leaflet.
	 *
	 * @param {Object} L Leaflet.
	 * @param {Object} record Map record.
	 * @param {Object} location Location.
	 * @param {string} status Event status.
	 * @return {Object[]}
	 */
	function addLocation(L, record, location, status) {
		const layers = [];
		const colors = mapColorsForStatus(status);

		if (
			location.geojson &&
			typeof location.geojson === 'object'
		) {
			try {
				const geoLayer = L.geoJSON(
					location.geojson,
					{
						style: {
							color: colors.stroke,
							weight: 5,
							opacity: 0.9,
							fillColor: colors.fill,
							fillOpacity: 0.25
						},
						pointToLayer: function (
							feature,
							latlng
						) {
							return L.circleMarker(
								latlng,
								{
									radius: 8,
									color: colors.stroke,
									weight: 3,
									fillColor: colors.fill,
									fillOpacity: 0.8
								}
							);
						},
						onEachFeature: function (
							feature,
							layer
						) {
							void feature;

							layer.bindPopup(
								createPopup(location)
							);
						}
					}
				);

				geoLayer.addTo(record.dataLayer);
				layers.push(geoLayer);
			} catch (error) {
				/*
				 * Invalid geometry is ignored. The server already
				 * validates GeoJSON, but the client must remain safe.
				 */
			}
		}

		const latitude = Number(location.center_lat);
		const longitude = Number(location.center_lng);

		if (
			layers.length === 0 &&
			Number.isFinite(latitude) &&
			Number.isFinite(longitude) &&
			latitude >= -90 &&
			latitude <= 90 &&
			longitude >= -180 &&
			longitude <= 180
		) {
			const marker = L.circleMarker(
				[latitude, longitude],
				{
					radius: 8,
					color: colors.stroke,
					weight: 3,
					fillColor: colors.fill,
					fillOpacity: 0.8
				}
			);

			marker.bindPopup(
				createPopup(location)
			);

			marker.addTo(record.dataLayer);
			layers.push(marker);
		}

		return layers;
	}

	/**
	 * Render locations on the map.
	 *
	 * @param {HTMLElement} modal Modal.
	 * @param {Object} L Leaflet.
	 * @param {Object[]} locations Locations.
	 * @param {string} status Event status.
	 * @return {void}
	 */
	function renderMap(modal, L, locations, status) {
		const record = prepareMap(modal, L);
		const bounds = L.latLngBounds([]);
		let rendered = 0;
		let preferredZoom = 15;

		locations.forEach(function (location) {
			if (
				!location ||
				typeof location !== 'object'
			) {
				return;
			}

			const layers = addLocation(
				L,
				record,
				location,
				status
			);

			layers.forEach(function (layer) {
				if (
					typeof layer.getBounds ===
						'function'
				) {
					const layerBounds =
						layer.getBounds();

					if (
						layerBounds &&
						layerBounds.isValid()
					) {
						bounds.extend(layerBounds);
					}
				} else if (
					typeof layer.getLatLng ===
						'function'
				) {
					bounds.extend(layer.getLatLng());
				}
			});

			rendered += layers.length;

			const zoom = Number(
				location.default_zoom
			);

			if (Number.isInteger(zoom)) {
				preferredZoom = Math.min(
					20,
					Math.max(1, zoom)
				);
			}
		});

		if (rendered === 0 || !bounds.isValid()) {
			throw new Error('map_has_no_geometry');
		}

		if (
			bounds.getNorthEast().equals(
				bounds.getSouthWest()
			)
		) {
			record.map.setView(
				bounds.getCenter(),
				preferredZoom
			);
		} else {
			record.map.fitBounds(
				bounds,
				{
					padding: [28, 28],
					maxZoom: preferredZoom
				}
			);
		}

		window.setTimeout(function () {
			record.map.invalidateSize();
		}, 50);
	}

	/**
	 * Open and load one event map.
	 *
	 * @param {HTMLElement} trigger Map trigger.
	 * @return {Promise<void>}
	 */
	async function openEventMap(trigger) {
		const modal = findModal(trigger);

		if (!(modal instanceof HTMLElement)) {
			return;
		}

		/*
		 * Open the dialog before validating remote configuration. A broken
		 * provider URL must never make a map control appear unresponsive.
		 */
		openModal(modal, trigger);

		if (mapConfiguration.enabled !== true) {
			setStatus(
				modal,
				getString(
					'loadError',
					'Bản đồ hiện đang tắt.'
				),
				true
			);
			return;
		}

		const mapUrl = validUrl(
			trigger.dataset.mapUrl || ''
		);
		const eventStatus =
			typeof trigger.dataset.eventStatus === 'string'
				? trigger.dataset.eventStatus
				: 'scheduled';

		if (!mapUrl) {
			setStatus(
				modal,
				getString(
					'loadError',
					'Không tìm thấy đường dẫn dữ liệu bản đồ.'
				),
				true
			);
			return;
		}

		setStatus(
			modal,
			getString(
				'loading',
				'Đang tải bản đồ…'
			),
			false
		);

		const state = modalStates.get(modal);

		if (
			!state ||
			!(
				state.controller instanceof
				AbortController
			)
		) {
			return;
		}

		try {
			const results = await Promise.all([
				loadLeaflet(),
				fetchMapData(
					mapUrl,
					state.controller.signal
				)
			]);

			if (!modalStates.has(modal)) {
				return;
			}

			const L = results[0];
			const data = results[1];
			const locations = data.locations;

			if (locations.length === 0) {
				setStatus(
					modal,
					getString(
						'emptyMap',
						'Chưa có dữ liệu bản đồ.'
					),
					false
				);

				renderLocationList(modal, []);
				return;
			}

			renderMap(modal, L, locations, eventStatus);
			renderLocationList(modal, locations);
			setStatus(modal, '', false);
		} catch (error) {
			if (
				error instanceof DOMException &&
				error.name === 'AbortError'
			) {
				return;
			}

			const message =
				error instanceof Error &&
				error.message === 'map_has_no_geometry'
					? getString(
						'emptyMap',
						'Chưa có dữ liệu hình học bản đồ.'
					)
					: getString(
						'loadError',
						'Không thể tải dữ liệu bản đồ.'
					);

			setStatus(modal, message, true);
		}
	}

	/**
	 * Initialize map triggers and close controls.
	 *
	 * @return {void}
	 */
	function initializeMaps() {
		document.addEventListener(
			'click',
			function (event) {
				const target = event.target;

				if (!(target instanceof Element)) {
					return;
				}

				const trigger = target.closest(
					'[data-psm-map-trigger]'
				);

				if (trigger instanceof HTMLElement) {
					event.preventDefault();
					openEventMap(trigger);
					return;
				}

				const closeButton = target.closest(
					'[data-psm-map-close]'
				);

				if (
					closeButton instanceof HTMLElement
				) {
					const modal = closeButton.closest(
						'[data-psm-map-modal]'
					);

					if (modal instanceof HTMLElement) {
						event.preventDefault();
						closeModal(modal);
					}
				}
			}
		);
	}

	/**
	 * Reveal additional electricity areas without another request.
	 *
	 * @return {void}
	 */
	function initializeAreaExpanders() {
		document
			.querySelectorAll('[data-psm-area-more]')
			.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}

				button.addEventListener('click', function () {
					const navigation = button.closest(
						'.psm-area-links'
					);

					if (!(navigation instanceof HTMLElement)) {
						return;
					}

					navigation
						.querySelectorAll(
							'[data-psm-area-extra]'
						)
						.forEach(function (item) {
							if (item instanceof HTMLElement) {
								item.hidden = false;
							}
						});

					button.setAttribute('aria-expanded', 'true');
					button.hidden = true;
				});
			});
	}

	/**
	 * Copy small on-page snippets without loading another library.
	 *
	 * @return {void}
	 */
	function initializeCopyButtons() {
		document
			.querySelectorAll('[data-psm-copy]')
			.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}

				button.addEventListener('click', async function () {
					const value = button.getAttribute('data-psm-copy') || '';

					if (value === '') {
						return;
					}

					try {
						await navigator.clipboard.writeText(value);
						const original = button.textContent;
						button.textContent = 'Đã sao chép';
						window.setTimeout(function () {
							button.textContent = original;
						}, 1600);
					} catch (error) {
						window.prompt('Sao chép nội dung chuyển khoản:', value);
					}
				});
			});
	}

	/**
	 * Filter the unit select while keeping a native, accessible form control.
	 *
	 * @return {void}
	 */
	function initializeUnitFilters() {
		document
			.querySelectorAll('[data-psm-unit-filter]')
			.forEach(function (input) {
				if (!(input instanceof HTMLInputElement)) {
					return;
				}

				const targetId =
					input.dataset.psmUnitTarget || '';
				const select = document.getElementById(targetId);

				if (!(select instanceof HTMLSelectElement)) {
					return;
				}

				const originalOptions = Array.from(select.options).map(
					function (option) {
						return {
							value: option.value,
							label: option.textContent || '',
							selected: option.selected,
						};
					}
				);

				input.addEventListener('input', function () {
					const query = input.value
						.normalize('NFD')
						.replace(/[\u0300-\u036f]/g, '')
						.toLocaleLowerCase('vi')
						.trim();
					const currentValue = select.value;

					select.replaceChildren();

					originalOptions.forEach(function (item, index) {
						const normalizedLabel = item.label
							.normalize('NFD')
							.replace(/[\u0300-\u036f]/g, '')
							.toLocaleLowerCase('vi');

						if (
							index !== 0 &&
							query !== '' &&
							!normalizedLabel.includes(query)
						) {
							return;
						}

						const option = new Option(
							item.label,
							item.value,
							false,
							item.value === currentValue
						);
						select.add(option);
					});

					if (select.options.length === 2 && query !== '') {
						select.selectedIndex = 1;
					}
				});
			});
	}

	/**
	 * Resolve the homepage autocomplete label to a stable unit code.
	 */
	function initializeCompactUnitSearch() {
		document
			.querySelectorAll('[data-psm-compact-unit-search]')
			.forEach(function (form) {
				if (!(form instanceof HTMLFormElement)) {
					return;
				}
				const input = form.querySelector(
					'[data-psm-compact-unit-input]'
				);
				const target = form.querySelector(
					'[data-psm-compact-unit-value]'
				);
				const list = form.querySelector(
					'[data-psm-compact-unit-list]'
				);
				const mobileSelect = form.querySelector(
					'[data-psm-mobile-unit-select]'
				);
				if (
					!(input instanceof HTMLInputElement) ||
					!(target instanceof HTMLInputElement) ||
					!(list instanceof HTMLElement)
				) {
					return;
				}

				const options = Array.from(
					list.querySelectorAll(
						'[data-psm-compact-unit-option]'
					)
				);
				let visibleOptions = [];
				let activeIndex = -1;

				function normalize(value) {
					return String(value || '')
						.normalize('NFD')
						.replace(/[\u0300-\u036f]/g, '')
						.toLocaleLowerCase('vi')
						.replace(/\bdien luc\b/g, '')
						.replace(/\s+/g, ' ')
						.trim();
				}

				function closeList() {
					list.hidden = true;
					input.setAttribute('aria-expanded', 'false');
					input.removeAttribute('aria-activedescendant');
					activeIndex = -1;
					options.forEach(function (option) {
						option.setAttribute('aria-selected', 'false');
					});
				}

				function setActive(index) {
					if (visibleOptions.length === 0) {
						closeList();
						return;
					}
					activeIndex =
						(index + visibleOptions.length) %
						visibleOptions.length;
					visibleOptions.forEach(function (option, optionIndex) {
						const active = optionIndex === activeIndex;
						option.setAttribute(
							'aria-selected',
							active ? 'true' : 'false'
						);
						if (active) {
							input.setAttribute(
								'aria-activedescendant',
								option.id
							);
							option.scrollIntoView({ block: 'nearest' });
						}
					});
				}

				function selectOption(option) {
					input.value = option.dataset.label || '';
					target.value = option.dataset.code || '';
					if (mobileSelect instanceof HTMLSelectElement) {
						mobileSelect.value = target.value;
					}
					input.setCustomValidity('');
					closeList();
				}

				function refreshList() {
					const query = normalize(input.value);
					target.value = '';
					input.setCustomValidity('');
					visibleOptions = [];

					options.forEach(function (option) {
						const label = normalize(option.dataset.label);
						const code = normalize(option.dataset.code);
						const visible =
							query.length > 0 &&
							visibleOptions.length < 7 &&
							(label.includes(query) ||
								code.includes(query));
						option.hidden = !visible;
						if (visible) {
							visibleOptions.push(option);
						}
					});

					if (visibleOptions.length > 0) {
						list.hidden = false;
						input.setAttribute('aria-expanded', 'true');
						setActive(0);
					} else {
						closeList();
					}
				}

				options.forEach(function (option) {
					option.addEventListener('mousedown', function (event) {
						event.preventDefault();
					});
					option.addEventListener('click', function () {
						selectOption(option);
						input.focus();
					});
				});

				input.addEventListener('input', refreshList);
				input.addEventListener('keydown', function (event) {
					if (
						(event.key === 'ArrowDown' ||
							event.key === 'ArrowUp') &&
						visibleOptions.length > 0
					) {
						event.preventDefault();
						setActive(
							activeIndex +
								(event.key === 'ArrowDown' ? 1 : -1)
						);
					} else if (
						event.key === 'Enter' &&
						!list.hidden &&
						visibleOptions[activeIndex]
					) {
						event.preventDefault();
						selectOption(visibleOptions[activeIndex]);
					} else if (event.key === 'Escape') {
						closeList();
					}
				});
				input.addEventListener('blur', function () {
					window.setTimeout(closeList, 100);
				});
				input.addEventListener('focus', function () {
					if (input.value.trim() !== '' && target.value === '') {
						refreshList();
					}
				});
				if (mobileSelect instanceof HTMLSelectElement) {
					mobileSelect.addEventListener('change', function () {
						target.value = mobileSelect.value;
						input.setCustomValidity('');
					});
				}
				form.addEventListener('submit', function (event) {
					if (target.value !== '') {
						return;
					}

					const query = normalize(input.value);
					const exact = options.find(function (option) {
						return (
							normalize(option.dataset.label) === query ||
							normalize(option.dataset.code) === query
						);
					});
					if (exact) {
						selectOption(exact);
						return;
					}

					event.preventDefault();
					input.setCustomValidity(
						'Vui lòng chọn một khu vực trong danh sách gợi ý.'
					);
					input.reportValidity();
				});
			});
	}

	/**
	 * Switch weather overlays without reloading the page.
	 *
	 * @return {void}
	 */
	function initializeWeatherMaps() {
		document
			.querySelectorAll('[data-psm-weather]')
			.forEach(function (container) {
				const frame = container.querySelector(
					'[data-psm-weather-frame]'
				);
				if (!(frame instanceof HTMLIFrameElement)) {
					return;
				}

				const panel = container.querySelector(
					'[data-psm-weather-panel]'
				);
				const buttons = Array.from(
					container.querySelectorAll(
						'[data-psm-weather-source]'
					)
				);

				function selectWeatherLayer(button) {
					const source =
						button.dataset.psmWeatherSource || '';
					if (!source) {
						return;
					}

					buttons.forEach(function (item) {
						const active = item === button;
						item.classList.toggle('is-active', active);
						item.setAttribute(
							'aria-selected',
							active ? 'true' : 'false'
						);
						item.setAttribute(
							'tabindex',
							active ? '0' : '-1'
						);
					});

					if (panel instanceof HTMLElement) {
						panel.setAttribute(
							'aria-labelledby',
							button.id
						);
					}

					frame.title =
						button.dataset.psmWeatherTitle ||
						frame.title;

					if (frame.getAttribute('src') !== source) {
						container.classList.add('is-loading');
						frame.src = source;
					}
				}

				frame.addEventListener('load', function () {
					container.classList.remove('is-loading');
				});

					buttons.forEach(function (button, index) {
					button.addEventListener('click', function () {
						selectWeatherLayer(button);
					});
					button.addEventListener(
						'keydown',
						function (event) {
							let nextIndex = index;
							if (
								event.key === 'ArrowRight' ||
								event.key === 'ArrowDown'
							) {
								nextIndex =
									(index + 1) % buttons.length;
							} else if (
								event.key === 'ArrowLeft' ||
								event.key === 'ArrowUp'
							) {
								nextIndex =
									(index - 1 + buttons.length) %
									buttons.length;
							} else if (event.key === 'Home') {
								nextIndex = 0;
							} else if (event.key === 'End') {
								nextIndex = buttons.length - 1;
							} else {
								return;
							}

							event.preventDefault();
							selectWeatherLayer(buttons[nextIndex]);
							buttons[nextIndex].focus();
						}
						);
					});

					const heroLayers = {
						'#weather-rain': 'rain',
						'#weather-wind': 'wind',
						'#weather-temp': 'temp',
						'#weather-clouds': 'clouds'
					};
					document
						.querySelectorAll('.psm-page-hero__tabs a')
						.forEach(function (link) {
							const layer = heroLayers[link.getAttribute('href') || ''];
							if (!layer) {
								return;
							}
							link.addEventListener('click', function (event) {
								const target = buttons.find(function (button) {
									return button.id.endsWith('-tab-' + layer);
								});
								if (target) {
									event.preventDefault();
									selectWeatherLayer(target);
									container.scrollIntoView({ behavior: 'smooth', block: 'start' });
								}
							});
						});
				});
	}

	/**
	 * Initialize all progressive frontend behavior.
	 *
	 * @return {void}
	 */
	function initializeFrontend() {
		initializeMaps();
		initializeAreaExpanders();
		initializeCopyButtons();
		initializeUnitFilters();
		initializeCompactUnitSearch();
		initializeWeatherMaps();
	}

	/**
	 * Initialize frontend behavior.
	 */
	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			initializeFrontend,
			{ once: true }
		);
	} else {
		initializeFrontend();
	}
})();
