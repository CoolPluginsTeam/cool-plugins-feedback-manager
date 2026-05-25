<?php
/**
 * Encode/decode server_info and extra_details for migration-safe storage.
 *
 * Supports legacy PHP-serialized strings, JSON strings, and raw arrays from REST clients.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cpfm_decode_payload' ) ) {

	/**
	 * Decode a stored or incoming payload into an associative array.
	 *
	 * @param mixed $raw Value from the database, request, or REST param.
	 * @return array<string, mixed>
	 */
	function cpfm_decode_payload( $raw ) {
		if ( empty( $raw ) && ! is_array( $raw ) ) {
			return array();
		}

		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) ) {
			return array();
		}

		$raw = wp_unslash( $raw );

		if ( cpfm_payload_is_json_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		if ( is_serialized( $raw ) ) {
			$decoded = maybe_unserialize( $raw );
			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}
}

if ( ! function_exists( 'cpfm_encode_payload_for_storage' ) ) {

	/**
	 * Normalize incoming payload for database storage.
	 *
	 * Arrays are stored as JSON. Legacy serialized or JSON strings are kept unchanged.
	 *
	 * @param mixed $data Value from REST or form request.
	 * @return string
	 */
	function cpfm_encode_payload_for_storage( $data ) {
		if ( is_array( $data ) ) {
			return wp_json_encode( $data );
		}

		if ( ! is_string( $data ) || '' === $data ) {
			return '';
		}

		$data = wp_unslash( $data );

		if ( is_serialized( $data ) ) {
			return $data;
		}

		if ( cpfm_payload_is_json_string( $data ) ) {
			json_decode( $data );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $data;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cpfm_payload_is_json_string' ) ) {

	/**
	 * Whether a string looks like a JSON object or array.
	 *
	 * @param string $value Raw string.
	 * @return bool
	 */
	function cpfm_payload_is_json_string( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		$trimmed = ltrim( $value );

		return '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] );
	}
}
