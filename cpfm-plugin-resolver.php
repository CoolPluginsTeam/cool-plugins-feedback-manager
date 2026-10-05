<?php
/**
 * Central plugin identity resolver.
 *
 * Client plugins send a stable registration key (`plugin_id`, e.g. "twae") inside
 * `extra_details`. Plugin names change (renames, translations, free/pro), so this
 * class maps whatever we received to ONE canonical name. Every place that stores,
 * displays or tags a plugin should go through CPFM_Plugin_Resolver::resolve().
 *
 * Safe by design: when there is no plugin_id, or the id is not in the registry,
 * and the name matches no alias, the trimmed raw name is returned unchanged.
 *
 * SINGLE CONTROL FILE. Everything about plugin names is decided here:
 *   - registry()      plugin_id  => latest name(s)           (ids sent by the plugins)
 *   - legacy_names()  old name   => latest name              (renames, slugs)
 *   - latest_names()  every current name, for correct casing (names that need no change)
 * and the rest of the manager only calls the helpers below:
 *   - resolve() / canonical_name()   saving + display
 *   - display_name()                 label for a stored name
 *   - filter_options()               the "All Plugins" dropdown (one entry per plugin)
 *   - filter_clause()                SQL `column IN (...)` that matches a plugin's old and new names
 * Names not listed anywhere are never changed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Plugin_Resolver' ) ) {

	class CPFM_Plugin_Resolver {

		/** @var array<string, array<string, string>>|null */
		private static $registry = null;

		/** @var array<string, string>|null normalized name => plugin id */
		private static $aliases = null;

		/**
		 * plugin_id => canonical names. `pro_name` is only for ids that are shared
		 * by a free and a Pro plugin; it is chosen when the incoming name says "pro".
		 *
		 * @return array<string, array<string, string>>
		 */
		public static function registry() {
			if ( null !== self::$registry ) {
				return self::$registry;
			}

			$registry = array(
				'ect'                => array(
					'name'     => 'Events Shortcodes For The Events Calendar',
					'pro_name' => 'Events Shortcodes Pro',
				),
				'epta'               => array( 'name' => 'Event Page Templates Addon For The Events Calendar' ),
				'ecmd'               => array(
					'name'     => 'Events Calendar Modules For Divi',
					'pro_name' => 'Events Calendar Modules For Divi (PRO)',
				),
				'ectbe'              => array( 'name' => 'Events Widgets For Elementor And The Events Calendar' ),
				'tecc'               => array( 'name' => 'Event Countdown for The Events Calendar' ),
				'ecsa'               => array(
					'name'     => 'Events Search & Filterbar',
					'pro_name' => 'Events Search & Filter Bar Pro',
				),
				'espbp'              => array( 'name' => 'Event Single Page Builder Pro' ),
				'ewpe'               => array( 'name' => 'Events Widgets Pro' ),
				'esas'               => array( 'name' => 'Events Speakers and Sponsors' ),
				'tmdivi'             => array( 'name' => 'Timeline Module For Divi' ),
				'tmdivi_pro'         => array( 'name' => 'Timeline Module for Divi (Pro)' ),
				'ctl'                => array( 'name' => 'Cool Timeline' ),
				'cool-timeline-pro'  => array( 'name' => 'Cool Timeline Pro' ),
				'ctlb'               => array( 'name' => 'Timeline Block' ),
				'timeline-block-pro' => array( 'name' => 'Timeline Block Pro' ),
				'twae'               => array( 'name' => 'Timeline Widget Addon For Elementor' ),
				'twae_pro'           => array( 'name' => 'Timeline Widget Pro For Elementor' ),
			);

			$filtered       = apply_filters( 'cpfm_plugin_registry', $registry );
			self::$registry = is_array( $filtered ) ? $filtered : $registry;

			return self::$registry;
		}

		/** @var array<string, string>|null normalized old name => latest name */
		private static $legacy = null;

		/** @var array<string, string>|null normalized latest name => latest name */
		private static $latest = null;

		/** @var array<string, array<int, string>> table => distinct stored names (per request) */
		private static $raw_cache = array();

		/**
		 * Every current (latest) plugin name. A name listed here is returned with
		 * exactly this spelling and casing. Add new plugins here; extend through the
		 * `cpfm_plugin_latest_names` filter.
		 *
		 * @return array<string, string> normalized name => latest name
		 */
		private static function latest_names() {
			if ( null !== self::$latest ) {
				return self::$latest;
			}

			$names = array(
				// events
				'Event Countdown for The Events Calendar',
				'Events Shortcodes For The Events Calendar',
				'Events Shortcodes Pro',
				'Events Widgets For Elementor And The Events Calendar',
				'Events Widgets Pro',
				'Event Page Templates Addon For The Events Calendar',
				'Event Single Page Builder Pro',
				'Events Calendar Modules For Divi',
				'Events Calendar Modules For Divi (PRO)',
				'Events Search & Filterbar',
				'Events Search & Filter Bar Pro',
				'Events Speakers and Sponsors',
				// timeline
				'Cool Timeline',
				'Cool Timeline Pro',
				'Timeline Widget Addon For Elementor',
				'Timeline Widget Pro For Elementor',
				'Timeline Block',
				'Timeline Block Pro',
				'Timeline Module For Divi',
				'Timeline Module for Divi (Pro)',
				// crypto
				'Cryptocurrency Widgets',
				'Cryptocurrency Widgets For Elementor',
				'Cryptocurrency Price Ticker Widget PRO',
				'Cryptocurrency Payments Using MetaMask For WooCommerce',
				'Pay With MetaMask For WooCommerce Pro',
				'Coin Market Cap',
				'Cryptocurrency Exchanges List PRO',
				// translation
				'AI Translation For TranslatePress',
				'AI Translation For TranslatePress Pro',
				'AutoMLP – AI Translation for WPML',
				'AutoMLP – AI Translation for WPML Pro',
				'AutoPoly - AI Translation For Polylang',
				'AutoPoly - AI Translation For Polylang (Pro)',
				'Loco Automatic Translate Addon',
				'Loco Automatic Translate Addon Pro',
				'Linguator AI – Auto Translate & Create Multilingual Sites',
				'Toolkit for Polylang',
				// forms
				'Conditional Fields for Elementor Form',
				'Conditional Fields for Elementor Form Pro',
				'Cool FormKit Lite - Elementor Form Builder',
				'Cool FormKit for Elementor Forms',
				'Country Code For Elementor Form Telephone Field',
				'Form Input Masks for Elementor Form',
				// other
			);

			$extra = apply_filters( 'cpfm_plugin_latest_names', array() );
			if ( is_array( $extra ) ) {
				$names = array_merge( $names, $extra );
			}

			$latest = array();
			foreach ( $names as $name ) {
				if ( is_string( $name ) && '' !== trim( $name ) && '' !== self::normalize_key( $name ) ) {
					$latest[ self::normalize_key( $name ) ] = trim( $name );
				}
			}

			self::$latest = $latest;

			return self::$latest;
		}

		/**
		 * Old plugin names (renames, slugs, translated-era titles) => the latest name.
		 * Only names that must be replaced are listed: a name that is already the
		 * latest, or one with no latest name decided yet, is left out and stays as is.
		 * Extend through the `cpfm_plugin_legacy_names` filter (raw old name => latest).
		 *
		 * @return array<string, string> normalized old name => latest name
		 */
		private static function legacy_names() {
			if ( null !== self::$legacy ) {
				return self::$legacy;
			}

			$pairs = array(
				// Events.
				'The Events Calendar Countdown Addon'                  => 'Event Countdown for The Events Calendar',
				'Events Shortcodes and Templates Addon'                => 'Events Shortcodes For The Events Calendar',
				'The Events Calendar Shortcode and Templates'          => 'Events Shortcodes For The Events Calendar',
				'events-shortcodes-for-the-events-calendar'            => 'Events Shortcodes For The Events Calendar',
				'events-shortcodes-the-events-calendar-addon'          => 'Events Shortcodes For The Events Calendar',
				'template-events-calendar'                             => 'Events Shortcodes For The Events Calendar',
				'Event Single Page Builder For The Event Calendar'     => 'Event Page Templates Addon For The Events Calendar',
				'The Events Calendar Event Details Page Templates'     => 'Event Page Templates Addon For The Events Calendar',
				'events-calendar-modules-for-divi'                     => 'Events Calendar Modules For Divi',
				'The Events Calendar Search Addon'                     => 'Events Search & Filterbar',
				'events-speakers-and-sponsors'                         => 'Events Speakers and Sponsors',
				// Timeline.
				'Timeline Widget For Elementor'                        => 'Timeline Widget Addon For Elementor',
				// Translation.
				'automatic-translate-addon-for-translatepress'         => 'AI Translation For TranslatePress',
				'Automatic Translations For Polylang'                  => 'AutoPoly - AI Translation For Polylang',
				'AI Translation For Polylang'                          => 'AutoPoly - AI Translation For Polylang',
				'LocoAI - Auto Translation for Loco Translate'         => 'Loco Automatic Translate Addon',
				'LocoAI – Auto Translate for Loco Translate'           => 'Loco Automatic Translate Addon',
				'Linguator – Multilingual AI Translation'              => 'Linguator AI – Auto Translate & Create Multilingual Sites',
				'Duplicate Content Addon For Polylang'                 => 'Toolkit for Polylang',
				'Translation Toolkit for Polylang'                     => 'Toolkit for Polylang',
				// Forms.
				'conditional-fields-for-elementor-form'                => 'Conditional Fields for Elementor Form',
				'Cool Formkit Lite'                                    => 'Cool FormKit Lite - Elementor Form Builder',
				'Country Code Field For Elementor Form'                => 'Country Code For Elementor Form Telephone Field',
			);

			$extra = apply_filters( 'cpfm_plugin_legacy_names', array() );
			if ( is_array( $extra ) ) {
				$pairs = array_merge( $pairs, $extra );
			}

			$legacy = array();
			foreach ( $pairs as $old => $latest ) {
				if ( ! is_string( $old ) || ! is_string( $latest ) || '' === trim( $latest ) ) {
					continue;
				}

				$key = self::normalize_key( $old );

				// Skip empty keys and a pair that maps a name onto itself. A slug that
				// only differs in punctuation (e.g. a hyphenated slug) is still kept.
				if ( '' === $key || trim( $old ) === trim( $latest ) ) {
					continue;
				}

				$legacy[ $key ] = trim( $latest );
			}

			self::$legacy = $legacy;

			return self::$legacy;
		}

		/**
		 * Normalized name => plugin id. Built from the registry names, plus any
		 * extra legacy names/slugs added through the `cpfm_plugin_aliases` filter
		 * (array of raw name => plugin id).
		 *
		 * @return array<string, string>
		 */
		private static function aliases() {
			if ( null !== self::$aliases ) {
				return self::$aliases;
			}

			$aliases = array();

			foreach ( self::registry() as $id => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				foreach ( array( 'name', 'pro_name' ) as $field ) {
					if ( ! empty( $entry[ $field ] ) && is_string( $entry[ $field ] ) ) {
						$aliases[ self::normalize_key( $entry[ $field ] ) ] = (string) $id;
					}
				}
			}

			$extra = apply_filters( 'cpfm_plugin_aliases', array() );
			if ( is_array( $extra ) ) {
				foreach ( $extra as $raw_name => $id ) {
					if ( is_string( $raw_name ) && is_string( $id ) && isset( self::registry()[ $id ] ) ) {
						$aliases[ self::normalize_key( $raw_name ) ] = $id;
					}
				}
			}

			self::$aliases = $aliases;

			return self::$aliases;
		}

		/**
		 * Lowercase and strip everything except letters and digits.
		 *
		 * @param mixed $name Plugin name or slug.
		 * @return string
		 */
		public static function normalize_key( $name ) {
			if ( ! is_scalar( $name ) ) {
				return '';
			}

			return preg_replace( '/[^a-z0-9]+/', '', strtolower( (string) $name ) );
		}

		/**
		 * Read `plugin_id` out of an extra_details payload (JSON, serialized or array).
		 * Returns '' when the payload has no usable key.
		 *
		 * @param mixed $extra_details Stored or incoming extra_details.
		 * @return string
		 */
		public static function extract_plugin_id( $extra_details ) {
			if ( empty( $extra_details ) || ! function_exists( 'cpfm_decode_payload' ) ) {
				return '';
			}

			$decoded = cpfm_decode_payload( $extra_details );

			if ( ! is_array( $decoded ) || empty( $decoded['plugin_id'] ) || ! is_scalar( $decoded['plugin_id'] ) ) {
				return '';
			}

			return sanitize_key( (string) $decoded['plugin_id'] );
		}

		/**
		 * Resolve a plugin to its canonical identity.
		 *
		 * @param string $plugin_name   Name as reported/stored.
		 * @param mixed  $extra_details Optional payload that may carry `plugin_id`.
		 * @param string $plugin_id     Optional explicit id (wins over extra_details).
		 * @return array{id:string,name:string,is_pro:bool,source:string} source: id|alias|raw
		 */
		public static function resolve( $plugin_name, $extra_details = '', $plugin_id = '' ) {
			$raw_name = is_scalar( $plugin_name ) ? trim( (string) $plugin_name ) : '';
			$registry = self::registry();

			$id = is_scalar( $plugin_id ) ? sanitize_key( (string) $plugin_id ) : '';
			if ( '' === $id ) {
				$id = self::extract_plugin_id( $extra_details );
			}

			$source = 'id';

			// Missing or unknown id: fall back to matching the name.
			if ( '' === $id || ! isset( $registry[ $id ] ) || ! is_array( $registry[ $id ] ) ) {
				$id      = '';
				$aliases = self::aliases();
				$key     = self::normalize_key( $raw_name );
				$legacy  = self::legacy_names();

				// Old name with a decided latest name: use it directly.
				if ( '' !== $key && isset( $legacy[ $key ] ) ) {
					return array(
						'id'     => '',
						'name'   => $legacy[ $key ],
						'is_pro' => (bool) preg_match( '/\bpro\b/i', $legacy[ $key ] ),
						'source' => 'alias',
					);
				}

				if ( '' !== $key && isset( $aliases[ $key ] ) ) {
					$id     = $aliases[ $key ];
					$source = 'alias';
				}
			}

			if ( '' === $id ) {
				// Already a current name: return it with its official spelling and casing.
				$latest = self::latest_names();
				$key    = self::normalize_key( $raw_name );
				if ( '' !== $key && isset( $latest[ $key ] ) ) {
					return array(
						'id'     => '',
						'name'   => $latest[ $key ],
						'is_pro' => (bool) preg_match( '/\bpro\b/i', $latest[ $key ] ),
						'source' => 'alias',
					);
				}

				return array(
					'id'     => '',
					'name'   => $raw_name,
					'is_pro' => (bool) preg_match( '/\bpro\b/i', $raw_name ),
					'source' => 'raw',
				);
			}

			$entry  = $registry[ $id ];
			$is_pro = (bool) preg_match( '/\bpro\b/i', $raw_name );
			$name   = '';

			if ( $is_pro && ! empty( $entry['pro_name'] ) ) {
				$name = (string) $entry['pro_name'];
			} elseif ( ! empty( $entry['name'] ) ) {
				$name = (string) $entry['name'];
			}

			// A malformed registry entry must never blank out a name.
			if ( '' === $name ) {
				return array(
					'id'     => '',
					'name'   => $raw_name,
					'is_pro' => $is_pro,
					'source' => 'raw',
				);
			}

			return array(
				'id'     => $id,
				'name'   => $name,
				'is_pro' => $is_pro,
				'source' => $source,
			);
		}

		/**
		 * Shortcut: canonical name only.
		 *
		 * @param string $plugin_name   Name as reported/stored.
		 * @param mixed  $extra_details Optional payload that may carry `plugin_id`.
		 * @return string
		 */
		public static function canonical_name( $plugin_name, $extra_details = '' ) {
			$resolved = self::resolve( $plugin_name, $extra_details );

			return $resolved['name'];
		}

		/**
		 * Label for a stored plugin name. Known plugins show their official name;
		 * anything else keeps the old look (lowercased, words capitalised).
		 *
		 * @param mixed $plugin_name Stored name.
		 * @return string
		 */
		public static function display_name( $plugin_name ) {
			$name     = is_scalar( $plugin_name ) ? trim( (string) $plugin_name ) : '';
			$resolved = self::resolve( $name );

			return 'raw' === $resolved['source'] ? ucwords( strtolower( $name ) ) : $resolved['name'];
		}

		/**
		 * Whether two stored/selected names are the same plugin.
		 *
		 * @param mixed $a First name.
		 * @param mixed $b Second name.
		 * @return bool
		 */
		public static function same_plugin( $a, $b ) {
			$a = self::resolve( $a )['name'];
			$b = self::resolve( $b )['name'];

			return '' !== $a && 0 === strcasecmp( $a, $b );
		}

		/**
		 * The name to use for a selected filter: its canonical name, or '' for "all".
		 *
		 * @param mixed $selected Value from the dropdown or request.
		 * @return string
		 */
		public static function canonical_filter( $selected ) {
			$selected = is_scalar( $selected ) ? trim( (string) $selected ) : '';

			return '' === $selected ? '' : self::resolve( $selected )['name'];
		}

		/**
		 * Distinct plugin names stored in a table, exactly as stored.
		 *
		 * @param string $table Full table name (letters, digits, underscore).
		 * @param bool   $cache Allow a short cached copy (dropdowns only).
		 * @return array<int, string>
		 */
		public static function raw_names( $table, $cache = false ) {
			global $wpdb;

			if ( ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! isset( $wpdb ) ) {
				return array();
			}

			// A fresh list read earlier in this request is always good enough.
			if ( isset( self::$raw_cache[ $table . '|fresh' ] ) ) {
				return self::$raw_cache[ $table . '|fresh' ];
			}
			if ( $cache && isset( self::$raw_cache[ $table . '|cached' ] ) ) {
				return self::$raw_cache[ $table . '|cached' ];
			}

			$key   = 'cpfm_raw_names_' . md5( $table );
			$names = $cache ? get_transient( $key ) : false;

			if ( ! is_array( $names ) ) {
				$names = $wpdb->get_col( "SELECT DISTINCT plugin_name FROM `{$table}` WHERE plugin_name IS NOT NULL AND plugin_name <> ''" );
				$names = is_array( $names ) ? array_values( array_filter( $names, 'is_string' ) ) : array();

				if ( $cache ) {
					set_transient( $key, $names, 10 * MINUTE_IN_SECONDS );
				}
			}

			self::$raw_cache[ $table . ( $cache ? '|cached' : '|fresh' ) ] = $names;

			return $names;
		}

		/**
		 * Dropdown entries for a table: one per plugin, old and new names merged.
		 *
		 * @param string $table Full table name.
		 * @return array<string, string> value (what is submitted) => label
		 */
		public static function filter_options( $table ) {
			$options = array();

			foreach ( self::raw_names( $table, true ) as $raw ) {
				$raw = trim( $raw );
				if ( '' === $raw ) {
					continue;
				}

				$value = self::canonical_filter( $raw );
				$key   = strtolower( $value );

				if ( ! isset( $options[ $key ] ) ) {
					$options[ $key ] = array( $value, self::display_name( $raw ) );
				}
			}

			uasort(
				$options,
				function ( $a, $b ) {
					return strcasecmp( $a[1], $b[1] );
				}
			);

			$out = array();
			foreach ( $options as $entry ) {
				$out[ $entry[0] ] = $entry[1];
			}

			return $out;
		}

		/**
		 * SQL condition that matches a selected plugin under all of its names
		 * (new name plus every old name stored in this table).
		 *
		 * @param string $column   Column, e.g. "plugin_name" or "si.plugin_name".
		 * @param mixed  $selected Selected value (new or old name). '' means no filter.
		 * @param string $table    Full table the column belongs to (used to list the stored names).
		 * @param bool   $trim     Match TRIM(column), as the list tables always did.
		 * @return array{sql:string,params:array<int,string>} sql is '' when nothing is selected
		 */
		public static function filter_clause( $column, $selected, $table, $trim = false ) {
			$none     = array( 'sql' => '', 'params' => array() );
			$selected = is_scalar( $selected ) ? trim( (string) $selected ) : '';

			if ( '' === $selected || ! is_string( $column ) || ! preg_match( '/^[A-Za-z0-9_.]+$/', $column ) ) {
				return $none;
			}

			$canonical = self::resolve( $selected )['name'];
			$names     = array( $selected, $canonical );

			foreach ( self::raw_names( $table ) as $raw ) {
				$trimmed = trim( $raw );
				if ( '' !== $trimmed && 0 === strcasecmp( self::resolve( $trimmed )['name'], $canonical ) ) {
					$names[] = $trim ? $trimmed : $raw;
				}
			}

			$names = array_values( array_unique( array_filter( $names, 'strlen' ) ) );
			$expr  = $trim ? "TRIM({$column})" : $column;

			return array(
				'sql'    => $expr . ' IN (' . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')',
				'params' => $names,
			);
		}
	}
}
