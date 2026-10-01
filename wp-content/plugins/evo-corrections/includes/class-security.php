<?php
/**
 * 12. Sécurité : énumération des utilisateurs bloquée, pages auteur désactivées, XML-RPC coupé (SECU1, SECU2).
 * Le renommage du compte « adminadmin » reste manuel (voir Outils > Corrections EVO).
 */

defined( 'ABSPATH' ) || exit;

class Evo_Security {

	public static function init() {
		add_filter( 'rest_endpoints', [ __CLASS__, 'rest_endpoints' ] );
		add_action( 'template_redirect', [ __CLASS__, 'block_author' ], 0 );
		add_filter( 'oembed_response_data', [ __CLASS__, 'oembed' ] );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', [ __CLASS__, 'headers' ] );
		add_filter( 'author_link', [ __CLASS__, 'author_link' ] );
		remove_action( 'wp_head', 'rsd_link' );
	}

	/** /wp-json/wp/v2/users réservé aux utilisateurs connectés. */
	public static function rest_endpoints( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/** ?author=1 et /author/xxx/ → accueil. */
	public static function block_author() {
		if ( is_admin() ) {
			return;
		}
		if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function oembed( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}

	public static function headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function author_link() {
		return home_url( '/' );
	}

	public static function flush_on_activation() {
		flush_rewrite_rules( false );
	}
}
