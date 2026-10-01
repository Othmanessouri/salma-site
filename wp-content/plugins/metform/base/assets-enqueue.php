<?php

namespace MetForm\Base;
use MetForm\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * Assets_Enqueue
 *
 * Enqueues scripts and styles with WordPress via wp_enqueue_*.
 * No dependency on Assets_Register — fully standalone.
 *
 * load()/get() register on the fly from the asset definition, so no
 * prior Assets_Register call is required — safe even when the script
 * and style for a handle share the same name. script()/style() (and
 * the init() entry point) still assume the handle is already
 * registered.
 *
 * @package ElementsKit_Lite\Config
 * @since   3.9.6
 *
 * @example
 * // Register + enqueue in one call:
 * Assets_Enqueue::instance()->init();
 *
 * // Or enqueue a specific handle directly:
 * Assets_Enqueue::get( 'ekit-icon-pack' );
 */
class Assets_Enqueue extends Assets_Base {

    use Singleton;

    /**
     * Constructor.
     *
     * @since 3.9.6
     */
    private function __construct() {}

    /**
     * Registers then enqueues all asset definitions.
     * Single entry point when you want both in one call.
     *
     * @since 3.9.6
     *
     * @return self
     */
    public function init(): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            $this->enqueue_asset( $definition );
        }
        return $this;
    }

    /**
     * Enqueues both script and style for a handle.
     * Registers on the fly from the asset definition — no prior
     * Assets_Register call needed, even when the script and style
     * share the same handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function load( string $handle ): self {
        foreach ( $this->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle ) {
                $this->enqueue_asset( $definition );
            }
        }
        return $this;
    }

    /**
     * Enqueues the script only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function script( string $handle ): self {
        wp_enqueue_script( $handle );
        return $this;
    }

    /**
     * Enqueues the style only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function style( string $handle ): self {
        wp_enqueue_style( $handle );
        return $this;
    }

    /**
     * Dequeues both script and style for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function unload( string $handle ): self {
        wp_dequeue_script( $handle );
        wp_dequeue_style( $handle );
        return $this;
    }

    /**
     * Dequeues the script only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function dequeue_script( string $handle ): self {
        wp_dequeue_script( $handle );
        return $this;
    }

    /**
     * Dequeues the style only for a handle.
     * Chainable — returns $this.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     *
     * @return self
     */
    public function dequeue_style( string $handle ): self {
        wp_dequeue_style( $handle );
        return $this;
    }

    /**
     * Static shortcut — enqueues both script and style for a handle.
     * Self-sufficient — no prior Assets_Register call needed, even
     * when the script and style share the same handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get( string $handle ): void {
        static::instance()->load( $handle );
    }

    /**
     * Static shortcut — registers then enqueues script only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get_script( string $handle ): void {
        $instance = static::instance();
        foreach ( $instance->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle && $definition['type'] === self::SCRIPT ) {
                $instance->enqueue_asset( $definition );
                return;
            }
        }
    }

    /**
     * Static shortcut — registers then enqueues style only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function get_style( string $handle ): void {
        $instance = static::instance();
        foreach ( $instance->get_all_asset_definitions() as $definition ) {
            if ( $definition['handle'] === $handle && $definition['type'] === self::STYLE ) {
                $instance->enqueue_asset( $definition );
                return;
            }
        }
    }

    /**
     * Static shortcut — dequeues both script and style for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function dequeue( string $handle ): void {
        static::instance()->unload( $handle );
    }

    /**
     * Static shortcut — dequeues the script only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function drop_script( string $handle ): void {
        static::instance()->dequeue_script( $handle );
    }

    /**
     * Static shortcut — dequeues the style only for a handle.
     *
     * @since 3.9.6
     *
     * @param string $handle The asset handle.
     */
    public static function drop_style( string $handle ): void {
        static::instance()->dequeue_style( $handle );
    }

    /**
     * Default source group — assets under build/, versioned via manifest.
     *
     * @since 3.9.6
     */
    private const SRC_BUILD = 'build';

    /**
     * Registers and enqueues a single asset definition with WordPress.
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
     * handle of the same type before enqueueing — used when another
     * plugin/WP core already registered the same handle.
     *
     * @since 3.9.6
     *
     * @param array $definition Asset definition array.
     */
    private function enqueue_asset( array $definition ): void {
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
            wp_enqueue_script( $definition['handle'], $src, $deps, $version, true );
        } else {
            wp_enqueue_style( $definition['handle'], $src, [], $version );
        }
    }
}
