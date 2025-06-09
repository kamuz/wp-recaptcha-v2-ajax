jQuery(document).ready(function ($) {
	// Handle form submission
	$('#ajax-contact-form').on('submit', function (e) {
		e.preventDefault(); // Prevent default form submission

		const $form = $(this);
		const $result = $('#contact-form-result');
		const formData = $form.serializeArray(); // Serialize form fields

		// Append custom AJAX action and nonce
		formData.push({ name: 'action', value: 'contact_form_submit' });
		formData.push({ name: 'nonce', value: cf_ajax_obj.nonce });

		// Append reCAPTCHA response token
		formData.push({ name: 'g_recaptcha_response', value: grecaptcha.getResponse() });

		// Display loading message
		$result.html('<div class="sending">Sending...</div>');

		// Send AJAX request
		$.post(cf_ajax_obj.ajax_url, formData, function (response) {
			if (response.success) {
				// On success: show message, reset form and reCAPTCHA
				$result.html('<div class="success">' + response.data.message + '</div>');
				$form[0].reset();
				grecaptcha.reset();
			} else {
				// On error: show errors, reset reCAPTCHA
				const errors = response.data.errors.join('<br>');
				$result.html('<div class="error">' + errors + '</div>');
				grecaptcha.reset();
			}
		});
	});
});
