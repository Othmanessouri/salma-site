<?php

namespace MetForm\Base;
use MetForm\Traits\Singleton;


defined( 'ABSPATH' ) || exit;

/**
 * Assets_Base
 *
 * Shared foundation for Assets_Register and Assets_Enqueue.
 * Holds all asset definitions, the manifest loader, and URL resolvers.
 * Neither registers nor enqueues anything.
 *
 * @package MetForm\Base
 * @since   3.9.6
 */
abstract class Assets_Base {

    use Singleton;
    /**
     * Asset type constants.
     */
    const SCRIPT = 'script';
    const STYLE  = 'style';

    /**
     * Default source root for generated bundles, relative to the plugin root.
     */
    const SOURCE_BUILD = 'build';

    /**
     * Source root for prebuilt third-party libs, relative to the plugin root.
     */
    const SOURCE_ASSETS = 'assets';

    /**
     * Cache for loaded asset manifests to avoid duplicate file reads.
     *
     * @var array<string, array>
     */
    private array $manifest_cache = [];

    /**
     * Cache for merged asset definitions to avoid repeated array construction.
     *
     * @var array<int, array>|null
     */
    private ?array $definitions_cache = null;


    /**
     * Returns admin asset definitions.
     *
     * Everything here loads in wp-admin or the Elementor editor.
     *
     * @since 3.9.6
     *
     * @return array<int, array>
     */
    private function get_admin_asset_definitions(): array {
        return [
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-js-formpicker-control-inspactor',
                'src'      => 'admin/form-picker-inspector/index.js',
                'manifest' => 'admin/form-picker-inspector/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-js-formpicker-control-editor',
                'src'      => 'admin/form-picker-editor/index.js',
                'manifest' => 'admin/form-picker-editor/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'mf-admin-core',
                'src'      => 'admin/onboarding/index.js',
                'manifest' => 'admin/onboarding/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-admin-script',
                'src'      => 'admin/admin-shell/index.js',
                'manifest' => 'admin/admin-shell/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-form-script',
                'src'      => 'admin/form-list/index.js',
                'manifest' => 'admin/form-list/index.asset.php',
                'extra_dependencies' => [ 'jquery' ],
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-admin-entries-view-js',
                'src'      => 'admin/entries-list/index.js',
                'manifest' => 'admin/entries-list/index.asset.php',
                'extra_dependencies' => [ 'jquery' ],
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-entry-script',
                'src'      => 'admin/entry-details/index.js',
                'manifest' => 'admin/entry-details/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-editor-panel-script',
                'src'      => 'admin/editor-panel/index.js',
                'manifest' => 'admin/editor-panel/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-form-analytics',
                'src'      => 'admin/form-analytics/index.js',
                'manifest' => 'admin/form-analytics/index.asset.php',
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'metform-form-picker-modal',
                'src'      => 'admin/form-picker-modal/index.js',
                'manifest' => 'admin/form-picker-modal/index.asset.php',
            ],
            [
                'type'      => self::SCRIPT,
                'handle'    => 'metform-add-new-form-modal',
                'src'      => 'admin/add-new-form-modal/index.js',
                'manifest' => 'admin/add-new-form-modal/index.asset.php',
            ],
            [
                'type'      => self::SCRIPT,
                'handle'    => 'metform-form-picker-modal-react',
                'src'      => 'admin/form-picker-modal/index.js',
                'manifest' => 'admin/form-picker-modal/index.asset.php',
            ],
            [
                'type'      => self::STYLE,
                'handle'    => 'metform-form-picker-modal-react-style',
                'src'      => 'admin/form-picker-modal/index.css',
                'manifest' => 'admin/form-picker-modal/index.asset.php',
            ],
            [
                'type'      => self::STYLE,
                'handle'    => 'metform-add-new-form-modal',
                'src'      => 'admin/add-new-form-modal/index.css',
                'manifest' => 'admin/add-new-form-modal/index.asset.php',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-form-analytics',
                'src'      => 'admin/form-analytics/index.css',
                'manifest' => 'admin/form-analytics/index.asset.php',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-css-formpicker-control-inspactor',
                'src'      => 'admin/form-picker-inspector/index.css',
                'manifest' => 'admin/form-picker-inspector/index.asset.php',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-css-formpicker-control-editor',
                'src'      => 'admin/form-picker-editor/index.css',
                'manifest' => 'admin/form-picker-editor/index.asset.php',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-init-css-admin',
                'src'      => 'admin/onboarding/index.css',
                'manifest' => 'admin/onboarding/index.asset.php',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-onboard-icon',
                'src'      => 'assets/fonts/onboard/css/onboard-icon.css',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-admin-fonts',
                'src'      => 'assets/fonts/metform-icons/css/admin-fonts.css',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-icon',
                'src'      => 'assets/fonts/mf-icon/css/mf-icon.css',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'metform-admin-style',
                'src'      => 'admin/admin-shell/index.css',
                'manifest' => 'admin/admin-shell/index.asset.php',
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'admin-form-list-style',
                'src'      => 'admin/form-list/index.css',
                'manifest' => 'admin/form-list/index.asset.php',
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'metform-admin-entries-view-css',
                'src'      => 'admin/entries-list/index.css',
                'manifest' => 'admin/entries-list/index.asset.php',
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'metform-category-top',
                'src'      => 'styles/category-top.css',
                'manifest' => 'styles/category-top.asset.php',
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'metform-ui',
                'src'      => 'styles/metform-ui.css',
                'manifest' => 'styles/metform-ui.asset.php',
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'mf-wp-dashboard',
                'src'      => 'styles/dashboard.css',
                'manifest' => 'styles/dashboard.asset.php',
            ],
        ];
    }

    /**
     * Returns frontend asset definitions.
     *
     * Everything here loads on the public-facing form (Elementor widget
     * runtime and its supporting scripts).
     *
     * @since 3.9.6
     *
     * @return array<int, array>
     */
    private function get_frontend_asset_definitions(): array {
        return [
            [
                'type'    => self::SCRIPT,
                'handle'   => 'metform-app',
                'src'      => 'frontend/app/index.js',
                'manifest' => 'frontend/app/index.asset.php',
                'extra_dependencies' => [ 'htm', 'jquery', 'wp-element' ],
            ],
            [
                'type'    => self::STYLE,
                'handle'   => 'metform-style',
                'src'      => 'frontend/app/index.css',
                'manifest' => 'frontend/app/index.asset.php',
            ],
            [
                'type'    => self::SCRIPT,
                'handle'   => 'mf-widget-frontend',
                'src'      => 'frontend/widget-bootstrap/index.js',
                'manifest' => 'frontend/widget-bootstrap/index.asset.php',
                'extra_dependencies' => [ 'metform-app' ],
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'recaptcha-support',
                'src'      => 'frontend/recaptcha-support/index.js',
                'manifest' => 'frontend/recaptcha-support/index.asset.php',
                'extra_dependencies' => [ 'jquery' ],
            ],
        ];
    }

    /**
     * Returns third-party (prebuilt libs) asset definitions.
     *
     * These live under `assets/libs/`, are never touched by Webpack, and
     * always resolve against the `assets` source root rather than `build`.
     *
     * @since 3.9.6
     *
     * @return array<int, array>
     */
    private function get_third_party_asset_definitions(): array {
        return [
            [
                'type'     => self::STYLE,
                'handle'   => 'asRange',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/asRange/asRange.min.css',
                'manifest' => '',
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'asRange',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/asRange/jquery-asRange.min.js',
                'manifest' => '',
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'cute-alert',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/cute-alert/cute-alert.js',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'cute-alert',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/cute-alert/style.css',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'mf-select2',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/select2/select2.min.css',
                'manifest' => '',
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'mf-select2',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/select2/select2.min.js',
                'manifest' => '',
            ],
            [
                'type'       => self::STYLE,
                'handle'     => 'flatpickr',
                'source'     => self::SOURCE_ASSETS,
                'src'        => 'libs/flatpickr/flatpickr.min.css',
                'manifest'   => '',
                'deregister' => true, // clears a conflicting 'flatpickr' style before re-registering
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'htm',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/htm/htm.js',
                'manifest' => '',
            ],
            [
                'type'     => self::SCRIPT,
                'handle'   => 'metform-ui',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/ui/ui.min.js',
                'manifest' => '',
            ],
            [
                'type'     => self::STYLE,
                'handle'   => 'text-editor-style',
                'source'   => self::SOURCE_ASSETS,
                'src'      => 'libs/text-editor/text-editor.css',
                'manifest' => '',
            ],
        ];
    }

    /**
     * Merges all asset groups into a single definitions array.
     * Result is cached after the first call.
     *
     * @since 3.9.6
     *
     * @return array<int, array>
     */
    protected function get_all_asset_definitions(): array {
        if ( $this->definitions_cache !== null ) {
            return $this->definitions_cache;
        }

        $this->definitions_cache = array_merge(
            $this->get_admin_asset_definitions(),
            $this->get_frontend_asset_definitions(),
            $this->get_third_party_asset_definitions()
        );

        return $this->definitions_cache;
    }

    /**
     * Loads and caches an asset manifest file.
     * Returns a safe fallback if the file is missing.
     *
     * @since 3.9.6
     *
     * @param string $relative_path Path relative to build/.
     *
     * @return array{ dependencies: string[], version: string }
     */
    protected function load_manifest( string $relative_path ): array {
        if ( $relative_path === '' ) {
            return [ 'dependencies' => [], 'version' => METFORM_VERSION ];
        }

        if ( isset( $this->manifest_cache[ $relative_path ] ) ) {
            return $this->manifest_cache[ $relative_path ];
        }

        $full_path = METFORM_FREE_PATH . 'build/' . $relative_path;

        $this->manifest_cache[ $relative_path ] = file_exists( $full_path )
            ? (array) include $full_path
            : [ 'dependencies' => [], 'version' => METFORM_VERSION ];

        return $this->manifest_cache[ $relative_path ];
    }

    /**
     * Resolves the base URL for a definition's declared source root.
     *
     * @since 3.9.6
     *
     * @param string $source One of self::SOURCE_BUILD, self::SOURCE_ASSETS.
     *
     * @return string Fully-qualified base URL, trailing slash included.
     */
    private static function resolve_source_url( string $source ): string {
        if ( $source === self::SOURCE_ASSETS ) {
            return METFORM_FREE_URL . 'assets/';
        }

        return METFORM_FREE_URL . 'build/';
    }

    /**
     * Returns the full URL for the src of a handle by type.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     * @param string $type   Asset type: self::SCRIPT or self::STYLE.
     *
     * @return string Full URL or empty string if not found.
     */
    public static function src( string $handle, string $type = self::STYLE ): string {
        foreach ( static::instance()->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle && $definition['type'] === $type ) {
                $source = $definition['source'] ?? self::SOURCE_BUILD;
                return self::resolve_source_url( $source ) . $definition['src'];
            }
        }
        return '';
    }

    /**
     * Returns URLs for json files of a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     * @param string $name   Optional specific json key.
     *
     * @return string|array<string, string>
     */
    public static function json( string $handle, string $name = '' ) {
        foreach ( static::instance()->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] !== $handle || ! isset( $definition['json'] ) ) {
                continue;
            }
            $base_url = self::resolve_source_url( $definition['source'] ?? self::SOURCE_BUILD );
            $urls     = array_map(
                fn( $path ) => $base_url . $path,
                $definition['json']
            );
            return $name !== '' ? ( $urls[ $name ] ?? '' ) : $urls;
        }
        return $name !== '' ? '' : [];
    }

    /**
     * Returns URLs for image files of a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     * @param string $name   Optional specific image key.
     *
     * @return string|array<string, string>
     */
    public static function images( string $handle, string $name = '' ) {
        foreach ( static::instance()->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] !== $handle || ! isset( $definition['images'] ) ) {
                continue;
            }
            $base_url = self::resolve_source_url( $definition['source'] ?? self::SOURCE_BUILD );
            $urls     = array_map(
                fn( $path ) => $base_url . $path,
                $definition['images']
            );
            return $name !== '' ? ( $urls[ $name ] ?? '' ) : $urls;
        }
        return $name !== '' ? '' : [];
    }

    /**
     * Returns URLs for font files of a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     * @param string $name   Optional specific font key.
     *
     * @return string|array<string, string>
     */
    public static function font( string $handle, string $name = '' ) {
        foreach ( static::instance()->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] !== $handle || ! isset( $definition['font'] ) ) {
                continue;
            }
            $base_url = self::resolve_source_url( $definition['source'] ?? self::SOURCE_BUILD );
            $urls     = array_map(
                fn( $path ) => $base_url . $path,
                $definition['font']
            );
            return $name !== '' ? ( $urls[ $name ] ?? '' ) : $urls;
        }
        return $name !== '' ? '' : [];
    }
}
