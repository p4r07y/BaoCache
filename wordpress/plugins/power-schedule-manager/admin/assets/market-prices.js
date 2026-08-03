/**
 * Keep editorial market-price units consistent with the selected dataset.
 *
 * @package Power_Schedule_Manager
 */

(function () {
	'use strict';

	const market = document.querySelector('#psm-market');
	const unit = document.querySelector('#psm-market-unit');
	const currency = document.querySelector('#psm-market-currency');
	const label = document.querySelector('#psm-price-label');

	if (
		!(market instanceof HTMLSelectElement) ||
		!(unit instanceof HTMLInputElement) ||
		!(currency instanceof HTMLInputElement)
	) {
		return;
	}

	const presets = {
		coffee_lam_dong: {
			unit: 'VND/kg',
			currency: 'VND',
			label: 'Lâm Đồng',
		},
		coffee_domestic: {
			unit: 'VND/kg',
			currency: 'VND',
			label: '',
		},
		coffee_futures: {
			unit: 'USD/tấn',
			currency: 'USD',
			label: 'Robusta London',
		},
		pepper_domestic: {
			unit: 'VND/kg',
			currency: 'VND',
			label: 'Hồ tiêu Lâm Đồng',
		},
		usd_vnd: {
			unit: 'VND/USD',
			currency: 'VND',
			label: 'USD/VND',
		},
		gold_daily: {
			unit: 'VND/lượng',
			currency: 'VND',
			label: 'SJC',
		},
		gold_world: {
			unit: 'USD/oz',
			currency: 'USD',
			label: 'XAU/USD',
		},
	};

	function updateDefaults() {
		const preset = presets[market.value];

		if (!preset) {
			return;
		}

		unit.value = preset.unit;
		currency.value = preset.currency;

		if (label instanceof HTMLInputElement && label.value === '') {
			label.value = preset.label;
		}
	}

	function updateFieldGroups() {
		const futures = market.value === 'coffee_futures';
		const gold = market.value === 'gold_daily';
		const worldGold = market.value === 'gold_world';

		document.querySelectorAll('[data-psm-market-group]').forEach(
			function (row) {
				const isFutures = row.getAttribute('data-psm-market-group') === 'futures';
				const active = isFutures ? futures : !futures;
				row.hidden = !active;
				row.querySelectorAll('input,select,textarea').forEach(
					function (control) {
						control.disabled = !active;
					}
				);
			}
		);

		document.querySelectorAll('[data-psm-price-field]').forEach(
			function (field) {
				const type = field.getAttribute('data-psm-price-field');
				let visible = true;

				if (gold) {
					visible = type === 'buy' || type === 'sell';
				} else if (worldGold) {
					visible = type === 'price' || type === 'change';
				} else {
					visible = type === 'price' || type === 'change';
				}

				field.hidden = !visible;
				const input = field.querySelector('input');
				if (input instanceof HTMLInputElement) {
					input.disabled = !visible;
				}
			}
		);
	}

	function updateContractUnit() {
		if (
			!(label instanceof HTMLInputElement) ||
			market.value !== 'coffee_futures'
		) {
			return;
		}

		const name = label.value.toLocaleLowerCase('vi');

		if (name.includes('new york')) {
			unit.value = 'cent/lb';
			currency.value = 'USD';
		} else if (name.includes('brazil')) {
			unit.value = 'USD/bao 60kg';
			currency.value = 'USD';
		} else if (name.includes('robusta')) {
			unit.value = 'USD/tấn';
			currency.value = 'USD';
		}
	}

	market.addEventListener('change', function () {
		if (label instanceof HTMLInputElement) {
			label.value = '';
		}
		updateDefaults();
		updateContractUnit();
		updateFieldGroups();
	});

	updateDefaults();
	updateContractUnit();
	updateFieldGroups();

	if (label instanceof HTMLInputElement) {
		label.addEventListener('input', updateContractUnit);
	}
}());
