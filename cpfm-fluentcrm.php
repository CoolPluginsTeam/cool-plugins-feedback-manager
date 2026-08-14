<?php
/**
 * This file is responsible for syncing feedback/opt-in contacts to FluentCRM.
 *
 * Required FluentCRM Pro setup (one-time, on the CRM site):
 * - List: "Plugin Feedback Data"
 * - Custom contact fields (slugs must match): user_domain, plugin_name, plugin_version,
 *   plugin_initial, feedback, data_source
 * - Incoming Webhook with default list above; copy Smart URL into $webhook_url
 */
class cpfm_fluentcrm {

	public $webhook_url;
	public $list_name;
	public $email_verify_api_key;

	public function __construct() {

		$this->webhook_url = 'https://my.coolplugins.net/?fluentcrm=1&route=contact&hash=e45c3373-30c3-4809-bf03-13b98a61926b';
		// $this->webhook_url          = 'https://staging22.coolplugins.net/?fluentcrm=1&route=contact&hash=e45c3373-30c3-4809-bf03-13b98a61926b';
		$this->list_name            = 'Plugin Feedback Data';
		$this->email_verify_api_key = '15f916123f1d123318522dd301f40a49020e6bb9a8e06f9954907474597a';
	}

	/**
	 * Build a FluentCRM tag from plugin_name + data_source.
	 * Slugs and display names normalize to the same title.
	 * Deactivation → "{Plugin} (Feedback Data)", opt-in → "{Plugin} (Opt-in)".
	 */
	public function get_plugin_tag( $plugin_name, $data_source = '' ) {

		$plugin_name = trim( (string) $plugin_name );

		if ( '' === $plugin_name ) {
			$plugin_name = 'Unknown Plugin';
		} else {
			$plugin_name = strtolower( $plugin_name );
			$plugin_name = preg_replace( '/[\-_]+/', ' ', $plugin_name );
			$plugin_name = preg_replace( '/\s+/', ' ', $plugin_name );
			$plugin_name = ucwords( trim( $plugin_name ) );
		}

		$suffix = ( 'opt_in' === $data_source ) ? 'Opt-in' : 'Deactivation Feedback Data';

		return $plugin_name . ' (' . $suffix . ')';
	}

	public function verify_email( $email ) {

		$client                 = new QuickEmailVerification\Client( $this->email_verify_api_key );
		$quickemailverification = $client->quickemailverification();
		$response               = $quickemailverification->verify( $email );

		return $response->body;
	}

	/**
	 * Sync a contact to FluentCRM via the Pro incoming webhook.
	 *
	 * @param array $args email, plugin_name, plugin_version, plugin_initial, domain,
	 *                    feedback (optional), data_source (opt_in|deactivation).
	 * @return bool True when the webhook request succeeded.
	 */
	public function sync_contact( $args ) {

		$email = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		$is_mail_valid = $this->verify_email( $email );

		if ( $is_mail_valid['result'] !== 'valid' ) {
			return false;
		}

		$plugin_name    = isset( $args['plugin_name'] ) ? sanitize_text_field( $args['plugin_name'] ) : '';
		$plugin_version = isset( $args['plugin_version'] ) ? sanitize_text_field( $args['plugin_version'] ) : '';
		$plugin_initial = isset( $args['plugin_initial'] ) ? sanitize_text_field( $args['plugin_initial'] ) : '';
		$domain         = isset( $args['domain'] ) ? esc_url_raw( $args['domain'] ) : '';
		$feedback       = isset( $args['feedback'] ) ? sanitize_textarea_field( $args['feedback'] ) : '';
		$data_source    = isset( $args['data_source'] ) ? sanitize_text_field( $args['data_source'] ) : '';

		$data = array(
			'email'          => $email,
			'first_name'     => $email,
			'status'         => 'subscribed',
			'lists'          => $this->list_name,
			'tags'           => $this->get_plugin_tag( $plugin_name, $data_source ),
			'user_domain'    => $domain,
			'plugin_name'    => $plugin_name,
			'plugin_version' => $plugin_version,
			'plugin_initial' => $plugin_initial,
			'feedback'       => $feedback,
			'data_source'    => $data_source,
		);

		$response = wp_remote_post(
			$this->webhook_url,
			array(
				'method'    => 'POST',
				'headers'   => array(
					'Content-Type' => 'application/json',
				),
				'body'      => json_encode( $data ),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return true;
	}
}
