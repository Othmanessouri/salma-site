<?php
/**
 * Admin Utils Class
 *
 * @package Click_To_Chat
 * @subpackage Administration
 * @since 4.41
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Utils' ) ) {

	/**
	 * Admin Utils Class
	 */
	class HT_CTC_Utils {

		/**
		 * Safely load required files.
		 *
		 * - All paths are resolved relative to the plugin root (HT_CTC_PLUGIN_DIR).
		 * - Files outside the plugin directory are never loaded.
		 *
		 * @param string $file_path Relative path from plugin root.
		 *                          e.g. 'new/inc/commons/class-ht-ctc-formatting.php'.
		 * @return bool True if file loaded successfully, false otherwise.
		 */
		public static function load_file( $file_path ) {
			// Security: strictly prevent any path traversal attempt.
			if ( false !== strpos( $file_path, '..' ) ) {
				return false;
			}

			$file_path = HT_CTC_PLUGIN_DIR . ltrim( $file_path, '/' );

			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				return false;
			}

			require_once $file_path;
			return true;
		}

		/**
		 * Safely load a class file and guarantee class availability.
		 *
		 * IMPORTANT DESIGN RULES:
		 * - All file paths MUST be relative to the plugin root (HT_CTC_PLUGIN_DIR).
		 * - Files outside the plugin directory are never loaded.
		 * - Files are loaded only once (require_once).
		 * - This method MUST be called during bootstrap / loader phase,
		 *   NOT at render or runtime execution points.
		 *
		 * This method:
		 * 1. Checks if the class already exists (no work if already loaded).
		 * 2. Loads the file using the strict plugin-root loader.
		 * 3. Verifies the expected class exists after loading.
		 *
		 * @param string $file_path  Relative path from plugin root.
		 *                           Example: 'new/admin2/includes/class-ht-ctc-admin-header.php'.
		 * @param string $class_name Expected fully-qualified class name.
		 *
		 * @return bool True if the class is available, false otherwise.
		 *
		 * e.g.
		 * call to load class. like. load_class( 'new/admin2/includes/class-ht-ctc-admin-header.php', 'HT_CTC_Admin_Header' );
		 * and then call like if class exist can so..
		 * or
		 * call to load class in if. so if no class can return or so..
		 * like
		 * if (load_class( 'new/admin2/includes/class-ht-ctc-admin-header.php', 'HT_CTC_Admin_Header' )) {
		 *  HT_CTC_Admin_Header::display();
		 * }
		 * or (if load class return false then !flase is true)
		 * if (!load_class( 'new/admin2/includes/class-ht-ctc-admin-header.php', 'HT_CTC_Admin_Header' )) {
		 *  return;
		 * }
		 */
		public static function load_class( $file_path, $class_name ) {

			// If the class is already loaded, nothing to do.
			// The second parameter (false) prevents triggering autoload.
			if ( class_exists( $class_name, false ) ) {
				return true;
			}

			/**
			 * Load the class file using the strict plugin-root loader.
			 *
			 * This load_file guarantees:
			 * - The file path is resolved from HT_CTC_PLUGIN_DIR.
			 * - No fallback to PHP working directory.
			 * - No loading of files outside the plugin.
			 * - No fatal errors if the file is missing.
			 */
			if ( ! self::load_file( $file_path ) ) {
				return false;
			}

			// Verify the class exists after loading the file.
			if ( ! class_exists( $class_name, false ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Get option with fallback from defaults class.
		 *
		 * @param string $option_name        Option name to retrieve.
		 * @param mixed  $if_not_exist_in_db Optional fallback value if not in DB.
		 * @return mixed Option value or default value.
		 */
		public static function get_option( $option_name, $if_not_exist_in_db = false ) {

			// First try to get the value from the database. (db return false if option does not exist, so use strict comparison)
			$value = get_option( $option_name );

			if ( false !== $value ) {
				return $value;
			}

			static $ht_ctc_defaults = null;

			if ( null === $ht_ctc_defaults ) {
				if ( ! class_exists( 'HT_CTC_Defaults' ) ) {
					self::load_class( 'new/admin/db/defaults/class-ht-ctc-defaults.php', 'HT_CTC_Defaults' );
				}

				if ( class_exists( 'HT_CTC_Defaults' ) ) {
					$ht_ctc_defaults = new HT_CTC_Defaults();
				} else {
					$ht_ctc_defaults = false; // Prevent repeated loading attempts
				}
			}

			$default_values = $if_not_exist_in_db;

			if ( $ht_ctc_defaults ) {
				if ( is_callable( array( $ht_ctc_defaults, $option_name ) ) ) {
					$default_values = $ht_ctc_defaults->$option_name();
				} else {
					$default_values = apply_filters( 'ht_ctc_fh_default_values', $default_values, $option_name );
				}
			}

			return $default_values;
		}

		/**
		 * Safely retrieve and sanitize a request variable (GET/POST).
		 *
		 * Use this for "read-only"
		 * if any data mutation is there then use verify_nonce() separately for data-mutating actions.
		 * Internal use of phpcs:ignore consolidates suppressions to this single utility.
		 *
		 * @param string $key           Variable key to look for.
		 * @param mixed  $default_value Default value if key is not set.
		 * @param string $method        Request method ('GET' or 'POST').
		 * @return mixed Sanitized value or default.
		 */
		public static function get_request_var( $key, $default_value = '', $method = 'GET' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Centralized safe read utility. Use verify_nonce() separately for data-mutating actions.
			$source = ( 'POST' === strtoupper( $method ) ) ? $_POST : $_GET;

			if ( ! isset( $source[ $key ] ) ) {
				return $default_value;
			}

			$value = wp_unslash( $source[ $key ] );

			return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : sanitize_text_field( $value );
		}

		/**
		 * Verify administrative request authenticity (Nonce check).
		 *
		 * MUST be used before any data mutation (update_option, delete, etc.)
		 *
		 * @param string $action    Nonce action name.
		 * @param string $query_arg Nonce query argument name.
		 * @return bool|int True on success, die on failure.
		 */
		public static function verify_nonce( $action = 'ht_ctc_nonce', $query_arg = '_htnonce' ) {
			return check_admin_referer( $action, $query_arg );
		}

		/**
		 * Debug-only error log.
		 *
		 * Writes to debug.log only when debug mode is enabled. Enabled when EITHER:
		 *   - HT_CTC_DEBUG_MODE is defined (plugin-specific opt-in, matches existing convention
		 *     used across HT_CTC_Admin_Scripts, HT_CTC_Admin_Demo, HT_CTC_Scripts, etc.), OR
		 *   - WP_DEBUG and WP_DEBUG_LOG are both true (standard WordPress debug).
		 *
		 * Safe to leave in production code — silent unless debug mode is on.
		 *
		 * Reserve for silent-failure paths (swallowed exceptions, fallback bailouts,
		 * DB write failures). Skip for routine flow or errors that already surface to UI.
		 *
		 * PHPCS suppression lives here only — callers don't need their own ignore comments.
		 *
		 * @param string $message Short, greppable description (e.g. "save_settings_to_db: update_option failed").
		 * @param array  $context Optional structured key-value data, JSON-encoded into the log line.
		 */
		public static function debug_log( $message, $context = array() ) {
			$debug_on = defined( 'HT_CTC_DEBUG_MODE' )
				|| ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG );

			if ( ! $debug_on ) {
				return;
			}

			$line = '[HT_CTC] ' . $message;
			if ( ! empty( $context ) ) {
				$line .= ' | ' . wp_json_encode( $context );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-gated helper.
			error_log( $line );
		}

		/**
		 * Build a URL with UTM campaign parameters for PRO upgrade links.
		 *
		 * Appends standard UTM source, medium, campaign, and plugin version parameters
		 * to destination links.
		 *
		 * @param string $medium  Surface rendering the link: banner, sidebar, teaser,
		 *                        pro_tab, plugins_page, menu, inline, toast.
		 * @param string $content Optional. Feature or placement within that surface.
		 * @param string $url     Optional. Destination page. Defaults to pricing.
		 * @return string Unescaped URL - escape at the point of output.
		 */
		public static function pro_url( $medium, $content = '', $url = '' ) {

			if ( '' === $url ) {
				$url = 'https://holithemes.com/plugins/click-to-chat/pricing/';
			}

			$args = array(
				'utm_source'   => 'ctc_main',
				'utm_medium'   => sanitize_key( $medium ),
				'utm_campaign' => 'pro_upgrade',
			);

			if ( '' !== $content ) {
				$args['utm_content'] = sanitize_key( $content );
			}

			// Which build produced the click.
			if ( defined( 'HT_CTC_VERSION' ) ) {
				$args['ctc_v'] = HT_CTC_VERSION;
			}

			// build_query() (inside add_query_arg) does not encode, so encode first.
			return add_query_arg( rawurlencode_deep( $args ), $url );
		}
	}

}
