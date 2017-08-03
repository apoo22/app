define('ext.wikia.recirculation.plista', ['jquery'], function ($) {
	function shouldFetchPlista(items) {
		var hasSponsoredContent = items.some(function (item) {
			return item.presented_by;
		});

		return !hasSponsoredContent && ['AU', 'NZ'].indexOf(Geo.getCountryCode()) > -1;
	}

	function fetchPlista() {
		return $.getJSON('http://farm.plista.com/recommendation/?publickey=845c651d11cf72a0f766713f&widgetname=api' +
			'&count=1&adcount=1&image[width]=320&image[height]=180'
		)
			.then(function (data) {
				if (data[0]) {
					return data[0];
				} else {
					return $.Deferred().reject('Plista returned no content');
				}
			});
	}

	function mapPlista(plistaData) {
		return {
			meta: 'wikia-impactfooter',
			source: 'plista',
			thumbnail: plistaData.img,
			title: plistaData.title,
			url: plistaData.url,
			presented_by: 'Plista',
			isPlista: true
		}
	}

	function prepareData(renderData) {
		return function () {
			var length = renderData.items.length;

			if (shouldFetchPlista(renderData.items)) {
				return fetchPlista()
					.then(mapPlista)
					.then(function (data) {
						renderData.items.splice(5, 0, data);

						renderData.items = renderData.items.slice(0, length);
					}, function () {
						// If Plista did not return anything, just don't add it to renderData

						return $.Deferred().resolve();
					});
			}
		}

	}

	return {
		prepareData: prepareData
	}
});
