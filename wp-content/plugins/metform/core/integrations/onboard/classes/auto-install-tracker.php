<?php

namespace MetForm\Core\Integrations\Onboard\Classes;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks which plugins were silently installed during a wpmet onboarding flow.
 *
 * When a wpmet plugin installs another plugin during onboarding, mark() does two things:
 *   1. Writes the slug to the shared `wpmet_onboarded_plugins` option (registry).
 *   2. Directly sets the installed plugin's own onboard status option so its wizard
 *      never appears — even if that plugin hasn't been updated to check the registry.
 *
 * This means no changes are required in the auto-installed plugins themselves.
 */
class Auto_Install_Tracker {

	const OPTION_KEY = 'wpmet_onboarded_plugins';

	/**
	 * Core wpmet plugins that MetForm knows about by default.
	 * Each plugin should also register itself via the 'wpmet_onboard_status_map' filter
	 * so no single plugin needs to maintain the full list.
	 *
	 * 'plugin-file' => [ 'option' => 'wp_option_name', 'value' => value_when_done ]
	 */
	const ONBOARD_STATUS_MAP = [
		'elementskit-lite/elementskit-lite.php'           => [ 'option' => 'elements_kit_onboard_status', 'value' => 'onboarded' ],
		'emailkit/EmailKit.php'                           => [ 'option' => 'emailkit_onboard_status',     'value' => 'onboarded' ],
		'shopengine/shopengine.php'                       => [ 'option' => 'shopengine_onboard_status',   'value' => true        ],
		'metform/metform.php'                             => [ 'option' => 'met_form_onboard_status',      'value' => 'onboarded' ],
		'gutenkit-blocks-addon/gutenkit-blocks-addon.php' => [ 'option' => 'gutenkit_onboard_status',     'value' => 'onboarded' ],
		'getgenie/getgenie.php'                           => [ 'option' => 'getgenie_onboard_status',     'value' => 'onboarded' ],
		'popup-builder-block/popup-builder-block.php'     => [ 'option' => 'popupkit_onboard_status',      'value' => 'onboarded' ],
	];

	/**
	 * Record a plugin as auto-installed and directly mark its onboarding as complete.
	 *
	 * @param string $plugin_file  e.g. 'elementskit-lite/elementskit-lite.php'
	 * @param string $installed_by Slug of the plugin that triggered the install.
	 */
	public static function mark( string $plugin_file, string $installed_by = 'metform' ): void {
		// 1. Write to shared registry.
		$registry = self::get_registry();
		if ( ! isset( $registry[ $plugin_file ] ) ) {
			$registry[ $plugin_file ] = [
				'installed_by' => $installed_by,
				'installed_at' => time(),
			];
			update_option( self::OPTION_KEY, $registry, false );
		}

		// 2. Directly set the installed plugin's own onboard status.
		// Each plugin registers itself via 'wpmet_onboard_status_map' filter;
		// ONBOARD_STATUS_MAP is just the fallback for plugins that haven't done so yet.
		$map = apply_filters( 'wpmet_onboard_status_map', self::ONBOARD_STATUS_MAP );
		if ( isset( $map[ $plugin_file ] ) && ! get_option( $map[ $plugin_file ]['option'] ) ) {
			update_option( $map[ $plugin_file ]['option'], $map[ $plugin_file ]['value'], false );
		}
	}

	/**
	 * Check whether a plugin was installed during any wpmet onboarding flow.
	 *
	 * @param string $plugin_file  e.g. 'elementskit-lite/elementskit-lite.php'
	 * @return bool
	 */
	public static function was_auto_installed( string $plugin_file ): bool {
		return isset( self::get_registry()[ $plugin_file ] );
	}

	/**
	 * Return the full registry array.
	 *
	 * @return array<string, array{installed_by: string, installed_at: int}>
	 */
	public static function get_registry(): array {
		return (array) get_option( self::OPTION_KEY, [] );
	}
}
