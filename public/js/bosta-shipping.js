(function (window, document) {
    'use strict';

    function labelFor(item) {
        return item.name_ar ? item.name_ar + ' — ' + item.name : item.name;
    }

    function initialiseAddressFields(root) {
        if (!root || root.dataset.bostaInitialised === '1') {
            return;
        }

        root.dataset.bostaInitialised = '1';

        var citySelect = root.querySelector('.bosta-city-id');
        var districtSelect = root.querySelector('.bosta-district-id');
        var cityName = root.querySelector('.bosta-city-name');
        var districtName = root.querySelector('.bosta-district-name');
        var zoneId = root.querySelector('.bosta-zone-id');
        var savedCityId = root.dataset.cityId || '';
        var savedCityName = root.dataset.cityName || savedCityId;
        var savedDistrictId = root.dataset.districtId || '';
        var savedDistrictName = root.dataset.districtName || savedDistrictId;
        var savedZoneId = root.dataset.zoneId || '';

        function fail(select, savedId, savedName) {
            select.innerHTML = '';
            if (savedId) {
                select.add(new Option(savedName || savedId, savedId, true, true));
                select.disabled = false;
            } else {
                select.add(new Option(root.dataset.locationsFailed, ''));
                select.disabled = true;
            }
        }

        function loadDistricts(cityId, selectedDistrictId) {
            districtSelect.disabled = true;
            districtSelect.innerHTML = '';
            districtSelect.add(new Option(root.dataset.loadingDistricts, ''));

            fetch(root.dataset.districtsUrl.replace('__CITY__', encodeURIComponent(cityId)), {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Bosta districts request failed');
                    return response.json();
                })
                .then(function (districts) {
                    districtSelect.innerHTML = '';
                    districtSelect.add(new Option(root.dataset.selectDistrict, ''));
                    districts.forEach(function (district) {
                        var option = new Option(labelFor(district), district.id);
                        option.dataset.name = district.name;
                        option.dataset.zoneId = district.zone_id || '';
                        option.selected = String(district.id) === String(selectedDistrictId);
                        districtSelect.add(option);
                    });
                    if (selectedDistrictId && !districtSelect.value) {
                        var savedOption = new Option(savedDistrictName || selectedDistrictId, selectedDistrictId, true, true);
                        savedOption.dataset.name = savedDistrictName || selectedDistrictId;
                        savedOption.dataset.zoneId = savedZoneId;
                        districtSelect.add(savedOption);
                    }
                    districtSelect.disabled = false;
                    districtSelect.dispatchEvent(new Event('change'));
                })
                .catch(function () {
                    fail(districtSelect, savedDistrictId, savedDistrictName);
                });
        }

        citySelect.addEventListener('change', function () {
            var option = citySelect.options[citySelect.selectedIndex];
            cityName.value = option && option.value ? option.dataset.name : '';
            districtName.value = '';
            zoneId.value = '';

            if (citySelect.value) {
                loadDistricts(
                    citySelect.value,
                    String(citySelect.value) === String(savedCityId) ? savedDistrictId : ''
                );
            } else {
                districtSelect.innerHTML = '';
                districtSelect.add(new Option(root.dataset.selectCity, ''));
                districtSelect.disabled = true;
            }
        });

        districtSelect.addEventListener('change', function () {
            var option = districtSelect.options[districtSelect.selectedIndex];
            districtName.value = option && option.value ? option.dataset.name : '';
            zoneId.value = option && option.value ? option.dataset.zoneId : '';
        });

        fetch(root.dataset.citiesUrl, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Bosta cities request failed');
                return response.json();
            })
            .then(function (cities) {
                citySelect.innerHTML = '';
                citySelect.add(new Option(root.dataset.selectCity, ''));
                cities.forEach(function (city) {
                    var option = new Option(labelFor(city), city.id);
                    option.dataset.name = city.name;
                    option.selected = String(city.id) === String(savedCityId);
                    citySelect.add(option);
                });
                citySelect.disabled = false;
                if (citySelect.value) {
                    citySelect.dispatchEvent(new Event('change'));
                }
            })
            .catch(function () {
                fail(citySelect, savedCityId, savedCityName);
            });
    }

    function initialiseWithin(container) {
        var scope = container || document;
        scope.querySelectorAll('.bosta-contact-address-fields').forEach(initialiseAddressFields);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initialiseWithin(document);
        });
    } else {
        initialiseWithin(document);
    }

    if (window.jQuery) {
        window.jQuery(document).on('shown.bs.modal', '.contact_modal', function () {
            initialiseWithin(this);
        });
    }
})(window, document);
