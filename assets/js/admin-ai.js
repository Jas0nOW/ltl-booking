(function () {
	function byId(id) {
		return document.getElementById(id);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var button = byId('ltlb-test-connection');
		if (!button) return;

		var statusEl = byId('ltlb-test-status');

		button.addEventListener('click', function () {
			if (!window.ltlbAdminAI || !ltlbAdminAI.ajaxUrl || !ltlbAdminAI.nonce) return;

			var providerEl = byId('ai_provider');
			var modelEl = byId('ai_model');
			var keyEl = byId('gemini_api_key');

			var provider = providerEl ? providerEl.value : 'gemini';
			var model = modelEl ? modelEl.value : '';
			var apiKey = keyEl ? keyEl.value : '';

			button.disabled = true;
			if (statusEl) {
				statusEl.textContent = (ltlbAdminAI.i18n && ltlbAdminAI.i18n.testing) ? ltlbAdminAI.i18n.testing : 'Testing…';
			}

			var params = new URLSearchParams();
			params.set('action', 'ltlb_test_ai_connection');
			params.set('nonce', ltlbAdminAI.nonce);
			params.set('provider', provider);
			if (model) {
				params.set('model', model);
			}
			if (apiKey) {
				params.set('api_key', apiKey);
			}

			fetch(ltlbAdminAI.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: params.toString()
			})
				.then(function (response) {
					return response.json().catch(function () {
						return null;
					});
				})
				.then(function (payload) {
					var success = false;
					var message = '';

					if (payload && typeof payload.success === 'boolean') {
						success = payload.success;
						message = payload.message || (payload.data && payload.data.message) || '';
					} else if (payload && payload.data && typeof payload.data.success === 'boolean') {
						success = payload.data.success;
						message = payload.data.message || '';
					} else if (payload && payload.data && payload.data.message) {
						message = payload.data.message;
					}

					if (!message) {
						if (success) {
							message = (ltlbAdminAI.i18n && ltlbAdminAI.i18n.successFallback) ? ltlbAdminAI.i18n.successFallback : 'Connection OK.';
						} else {
							message = (ltlbAdminAI.i18n && ltlbAdminAI.i18n.errorFallback) ? ltlbAdminAI.i18n.errorFallback : 'Connection failed.';
						}
					}

					if (statusEl) {
						statusEl.textContent = message;
					}
				})
				.catch(function () {
					if (statusEl) {
						statusEl.textContent = (ltlbAdminAI.i18n && ltlbAdminAI.i18n.errorFallback) ? ltlbAdminAI.i18n.errorFallback : 'Connection failed.';
					}
				})
				.finally(function () {
					button.disabled = false;
				});
		});
	});
})();
