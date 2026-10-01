<?php

namespace MetForm\Base;
use MetForm\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * Assets_Register
 *
 * Registers scripts and styles with WordPress via wp_register_*.
 * No dependency on Assets_Enqueue — fully standalone.
 *
 * @package ElementsKit_Lite\Config
 * @since   3.9.6
 */
class Assets_Register extends Assets_Base {

    use Singleton;

    /**
     * Constructor.
     *
     * @since 3.9.6
     */
    private function __construct() {}

    /**
     * Registers all asset definitions with WordPress.
     * Call once on the appropriate WP hook.
     *
     * @since 3.9.6
     *
     * @return self
     */
    public function init(): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            $this->register_asset( $definition );
        }
        return $this;
    }

    /**
     * Registers both script and style for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function register( string $handle ): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle ) {
                $this->register_asset( $definition );
            }
        }
        return $this;
    }

    /**
     * Registers the script only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function register_script( string $handle ): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle && $definition['type'] === self::SCRIPT ) {
                $this->register_asset( $definition );
            }
        }
        return $this;
    }

    /**
     * Registers the style only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function register_style( string $handle ): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle && $definition['type'] === self::STYLE ) {
                $this->register_asset( $definition );
            }
        }
        return $this;
    }

    /**
     * Deregisters both script and style for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function deregister( string $handle ): self {
        wp_deregister_script( $handle );
        wp_deregister_style( $handle );
        return $this;
    }

    /**
     * Deregisters the script only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function deregister_script( string $handle ): self {
        wp_deregister_script( $handle );
        return $this;
    }

    /**
     * Deregisters the style only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function deregister_style( string $handle ): self {
        wp_deregister_style( $handle );
        return $this;
    }

    /**
     * Static shortcut — registers both script and style for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get( string $handle ): void {
        static::instance()->register( $handle );
    }

    /**
     * Static shortcut — registers the script only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get_script( string $handle ): void {
        static::instance()->register_script( $handle );
    }

    /**
     * Static shortcut — registers the style only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get_style( string $handle ): void {
        static::instance()->register_style( $handle );
    }

    /**
     * Static shortcut — deregisters both script and style for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function remove( string $handle ): void {
        static::instance()->deregister( $handle );
    }

    /**
     * Static shortcut — deregisters the script only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function remove_script( string $handle ): void {
        static::instance()->deregister_script( $handle );
    }

    /**
     * Static shortcut — deregisters the style only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function remove_style( string $handle ): void {
        static::instance()->deregister_style( $handle );
    }

    /**
     * Default source group — assets under build/, versioned via manifest.
     *
     * @since 3.9.6
     */
    private const SRC_BUILD = 'build';

    /**
     * Registers a single asset definition with WordPress.
     *
     * Scripts inherit both manifest and extra dependencies.
     * Styles use an empty deps array — manifest deps are script handles
     * and are not valid style dependencies.
     *
     * Definitions may set 'source' to override the base folder (defaults
     * to 'build'), e.g. 'assets' for third-party libs under assets/libs/
     * that ship without a manifest.
     *
     * Definitions may set 'deregister' => true to deregister an existing
     * handle of the same type before registering — used when another
     * plugin/WP core already registered the same handle.
     *
     * @since 3.9.6
     *
     * @param array $definition Asset definition array.
     */
    private function register_asset( array $definition ): void {
        $manifest = $this->load_manifest( $definition['manifest'] );
        $base     = $definition['source'] ?? self::SRC_BUILD;
        $src      = METFORM_FREE_URL . $base . '/' . $definition['src'];
        $version  = $manifest['version'] ?? METFORM_VERSION;

        if ( ! empty( $definition['deregister'] ) ) {
            $definition['type'] === self::SCRIPT
                ? wp_deregister_script( $definition['handle'] )
                : wp_deregister_style( $definition['handle'] );
        }

        if ( $definition['type'] === self::SCRIPT ) {
            $deps = array_unique( array_merge(
                $manifest['dependencies'] ?? [],
                $definition['extra_dependencies'] ?? []
            ) );
            wp_register_script( $definition['handle'], $src, $deps, $version, true );
        } else {
            wp_register_style( $definition['handle'], $src, [], $version );
        }
    }
}
