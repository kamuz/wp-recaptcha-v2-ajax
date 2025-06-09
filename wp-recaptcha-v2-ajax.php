<?php
/**
 * Plugin Name: AJAX reCAPCHA v2
 * Plugin URI: https://wpdev.pp.ua
 * Description: AJAX reCAPTCHA-enabled contact form
 * Author: Volodymyr Kamuz
 * Author URI: https://wpdev.pp.ua
 * Version: 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define reCAPTCHA keys.
define( 'RECAPTCHA_SITE_KEY', 'your_site_key_here' );
define( 'RECAPTCHA_SECRET_KEY', 'your_secret_key_here' );

/**
 * Enqueue parent theme styles and contact form scripts.
 */
function cf_enqueue_assets() {
	wp_enqueue_style(
		'cf_style',
		plugin_dir_url( __FILE__ ) . '/wp-recaptcha-v2-ajax.css',
		array(),
		'1.0.0'
	); // Load styles.

	wp_enqueue_script(
		'google-recaptcha',
		'https://www.google.com/recaptcha/api.js',
		array(),
		'1.0.0',
		true
	); // Load reCAPTCHA script from Google.

	wp_enqueue_script(
		'contact-form-ajax',
		plugin_dir_url( __FILE__ ) . '/wp-recaptcha-v2-ajax.js',
		array( 'jquery' ),
		'1.0.0',
		true
	); // Load custom AJAX form script.

	wp_localize_script(
		'contact-form-ajax',
		'cf_ajax_obj',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cf_ajax_nonce' ),
		)
	); // Pass AJAX URL and nonce to JS.
}
add_action( 'wp_enqueue_scripts', 'cf_enqueue_assets' );

/**
 * Verify Google reCAPTCHA response token.
 *
 * @param string $recaptcha_response The response from client.
 * @return bool True if valid, false otherwise.
 */
function verify_recaptcha( $recaptcha_response ) {
	if ( empty( $recaptcha_response ) ) {
		return false; // reCAPTCHA response is missing.
	}

	$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

	// Send request to Google to verify the reCAPTCHA token.
	$response = wp_remote_post(
		$verify_url,
		array(
			'body' => array(
				'secret'   => RECAPTCHA_SECRET_KEY,
				'response' => sanitize_text_field( $recaptcha_response ),
				'remoteip' => sanitize_text_field( $remote_ip ),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false; // Error while connecting to reCAPTCHA service.
	}

	$body   = wp_remote_retrieve_body( $response );
	$result = json_decode( $body, true );

	return isset( $result['success'] ) && true === $result['success']; // Return verification status.
}

/**
 * Handle AJAX request for contact form submission.
 */
function ajax_handle_contact_form() {
	check_ajax_referer( 'cf_ajax_nonce', 'nonce' ); // Verify nonce for security.

	// Sanitize and retrieve submitted fields.
	$name               = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
	$email              = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
	$message            = sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ?? '' ) );
	$recaptcha_response = sanitize_text_field( wp_unslash( $_POST['g_recaptcha_response'] ?? '' ) );

	$errors = array();

	// Validate required fields.
	if ( empty( $name ) ) {
		$errors[] = 'Name is required.';
	}

	if ( empty( $email ) || ! is_email( $email ) ) {
		$errors[] = 'Valid email is required.';
	}

	if ( empty( $message ) ) {
		$errors[] = 'Message is required.';
	}

	// Validate reCAPTCHA.
	if ( ! verify_recaptcha( $recaptcha_response ) ) {
		$errors[] = 'reCAPTCHA verification failed.';
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( array( 'errors' => $errors ) ); // Return validation errors.
	}

	// Prepare email data.
	$to      = get_option( 'admin_email' );
	$subject = 'Contact Form: ' . $name;
	$body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";
	$headers = array( 'Reply-To: ' . $email );

	// Send the email.
	$sent = wp_mail( $to, $subject, $body, $headers );

	if ( $sent ) {
		wp_send_json_success( array( 'message' => 'Your message has been sent successfully.' ) ); // Return success.
	} else {
		wp_send_json_error( array( 'errors' => array( 'Failed to send message. Please try again.' ) ) ); // Return failure.
	}
}
add_action( 'wp_ajax_contact_form_submit', 'ajax_handle_contact_form' );
add_action( 'wp_ajax_nopriv_contact_form_submit', 'ajax_handle_contact_form' ); // Support for logged-out users.

/**
 * Shortcode to output the contact form.
 *
 * @return string HTML of the form.
 */
function ajax_contact_form_shortcode() {
	ob_start();
	?>

	<div id="contact-form-result"></div>

	<form id="ajax-contact-form" method="post" action="">
		<div class="form-group">
			<label for="contact_name">Name *</label>
			<input type="text" name="contact_name" id="contact_name" required>
		</div>

		<div class="form-group">
			<label for="contact_email">Email *</label>
			<input type="email" name="contact_email" id="contact_email" required>
		</div>

		<div class="form-group">
			<label for="contact_message">Message *</label>
			<textarea name="contact_message" id="contact_message" rows="5" required></textarea>
		</div>

		<div class="form-group">
			<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( RECAPTCHA_SITE_KEY ); ?>"></div>
		</div>

		<div class="form-group">
			<button type="submit">Send Message</button>
		</div>
	</form>

	<?php
	return ob_get_clean(); // Return form HTML.
}
add_shortcode( 'simple_contact_form', 'ajax_contact_form_shortcode' );