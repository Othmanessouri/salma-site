<?php
/**
 * Affichage : bandeau du haut, header collant, styles et scripts, zones cliquables, téléchargements,
 * redirections, anti-spam MetForm, page 404, polices locales, allègement des ressources.
 */

defined( 'ABSPATH' ) || exit;

class Evo_Frontend {

	public static function init() {
		add_shortcode( 'evo_bloc_secteurs', [ __CLASS__, 'sc_secteurs' ] );
		add_shortcode( 'evo_footer_legal', [ __CLASS__, 'sc_footer_legal' ] );
		add_shortcode( 'evo_company_ids', [ __CLASS__, 'sc_company_ids' ] );

		// Le cache d'éléments d'Elementor (24 h) gardait l'ancien menu : menu et bloc secteurs toujours rendus à jour.
		add_filter( 'elementor/element/is_dynamic_content', [ __CLASS__, 'always_fresh' ], 10, 2 );
		add_action( 'wp_update_nav_menu', [ __CLASS__, 'purge_after_menu_save' ] );

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'trim_assets' ], 999 );
		add_action( 'wp_head', [ __CLASS__, 'head' ], 2 );
		// Les polices Google d'Elementor ne sont coupées qu'une fois G11 appliqué (sinon certains textes changeraient de police).
		if ( isset( Evo_Migration::applied()['G11'] ) ) {
			add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
		}
		add_action( 'wp_body_open', [ __CLASS__, 'topbar' ], 5 );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_filter( 'elementor/frontend/widget/should_render', [ __CLASS__, 'should_render' ], 10, 2 );
		add_filter( 'elementor/widget/render_content', [ __CLASS__, 'widget_content' ], 10, 2 );
		add_action( 'template_redirect', [ __CLASS__, 'downloads' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'redirects' ], 2 );
		add_filter( 'template_include', [ __CLASS__, 'custom_404' ], 99 );
		add_filter( 'mf_after_validation_check', [ __CLASS__, 'honeypot' ] );
		add_filter( 'wp_get_attachment_image_attributes', [ __CLASS__, 'alt_fallback' ], 10, 2 );
	}

	/* ---------- assets ---------- */

	public static function assets() {
		$v = EVO_CORR_VERSION . '.' . filemtime( EVO_CORR_DIR . 'assets/css/evo-front.css' );
		wp_enqueue_style( 'evo-fonts', EVO_CORR_URL . 'assets/fonts/fonts.css', [], EVO_CORR_VERSION );
		wp_enqueue_style( 'evo-front', EVO_CORR_URL . 'assets/css/evo-front.css', [ 'evo-fonts' ], $v );
		$colors = '';
		foreach ( Evo_Migration::PRODUCT_COLORS as $slug => $hex ) {
			$colors .= '--evo-' . $slug . ':' . $hex . ';';
		}
		wp_add_inline_style( 'evo-front', ':root{' . $colors . '}' );
		wp_enqueue_script( 'evo-front', EVO_CORR_URL . 'assets/js/evo-front.js', [], $v, [ 'strategy' => 'defer', 'in_footer' => true ] );
		wp_localize_script(
			'evo-front',
			'EVO_CORR',
			[
				'ga4'   => Evo_Settings::get( 'ga4_id' ) ? 1 : 0,
				'merci' => is_page( 'merci' ) ? 1 : 0,
			]
		);
	}

	/** PER3 · PER4 · PER5 : ressources inutiles retirées, scripts différés. */
	public static function trim_assets() {
		// Font Awesome 4 (shims) : plus nécessaire.
		wp_dequeue_style( 'font-awesome-4-shim' );
		wp_dequeue_script( 'font-awesome-4-shim' );
		// Polices Google chargées par des modules tiers (une fois les polices du site remplacées par G11).
		if ( isset( Evo_Migration::applied()['G11'] ) ) {
			foreach ( wp_styles()->queue as $handle ) {
				$src = (string) ( wp_styles()->registered[ $handle ]->src ?? '' );
				if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
		// MetForm uniquement sur les pages qui affichent un formulaire.
		if ( ! self::page_has_form() ) {
			foreach ( [ 'metform-app', 'mf-widget-frontend', 'htm', 'cute-alert', 'recaptcha-v2', 'recaptcha-v3' ] as $h ) {
				wp_dequeue_script( $h );
			}
			foreach ( [ 'metform-style', 'metform-ui', 'text-editor-style', 'cute-alert' ] as $h ) {
				wp_dequeue_style( $h );
			}
		}
		// Click-to-Chat différé.
		if ( wp_script_is( 'ht_ctc_app_js', 'registered' ) ) {
			wp_script_add_data( 'ht_ctc_app_js', 'strategy', 'defer' );
		}
	}

	public static function page_has_form() {
		if ( ! is_singular() ) {
			return false;
		}
		$ids = [ get_queried_object_id(), Evo_Migration::HEADER, Evo_Migration::FOOTER ];
		foreach ( $ids as $id ) {
			if ( false !== strpos( (string) get_post_meta( $id, '_elementor_data', true ), '"widgetType":"metform"' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function head() {
		// Préchargement de la police des titres (G11).
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( EVO_CORR_URL . 'assets/fonts/poppins-600-latin.woff2' ) );
		$ga = Evo_Settings::get( 'ga4_id' );
		if ( $ga && preg_match( '/^G-[A-Z0-9]+$/', $ga ) ) {
			printf(
				"<script async src=\"https://www.googletagmanager.com/gtag/js?id=%1\$s\"></script>\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','%1\$s');</script>\n",
				esc_js( $ga )
			);
		}
	}

	public static function body_class( $classes ) {
		$classes[] = 'evo-corr';
		if ( Evo_Settings::get( 'topbar_enabled' ) ) {
			$classes[] = 'evo-has-topbar';
		}
		return $classes;
	}

	/* ---------- bandeau du haut (maquette de Salma) ---------- */

	public static function topbar() {
		if ( ! Evo_Settings::get( 'topbar_enabled' ) ) {
			return;
		}
		$phone = Evo_Settings::get( 'phone_display' );
		$tel   = 'tel:' . Evo_Settings::get( 'phone_intl' );
		$wa    = Evo_Settings::whatsapp_url( 'Bonjour, je souhaite avoir plus d\'informations sur vos produits.' );
		?>
<div class="evo-topbar" role="complementary" aria-label="<?php esc_attr_e( 'Informations de contact', 'evo-corrections' ); ?>">
	<div class="evo-topbar__inner">
		<p class="evo-topbar__left"><?php echo self::svg( 'leaf' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Filiale du groupe canadien EvoRoyal · Produits fabriqués au Canada</span></p>
		<ul class="evo-topbar__right">
			<li class="evo-topbar__delay"><?php echo self::svg( 'clock' ); // phpcs:ignore ?><span>Devis sous 24 h ouvrées</span></li>
			<li><a href="<?php echo esc_url( $tel ); ?>" class="evo-track-phone"><?php echo self::svg( 'phone' ); // phpcs:ignore ?><strong><?php echo esc_html( $phone ); ?></strong></a></li>
			<li><a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="evo-track-wa"><?php echo self::svg( 'whatsapp' ); // phpcs:ignore ?><strong>WhatsApp</strong></a></li>
		</ul>
	</div>
</div>
		<?php
	}

	/** Icônes inline (tracés Font Awesome Free 5, licence CC BY 4.0). */
	public static function svg( $name ) {
		$paths = [
			'leaf'     => [ '0 0 512 512', 'M383.8 351.7c2.5-2.5 105.2-92.4 105.2-92.4l-17.5-7.5c-10-4.9-7.4-11.5-5-17.4 2.4-7.6 20.1-67.3 20.1-67.3s-47.7 10-57.7 12.5c-7.5 2.4-10-2.5-12.5-7.5s-15-32.4-15-32.4-52.6 59.9-55.1 62.3c-10 7.5-20.1 0-17.6-10 0-10 27.6-129.6 27.6-129.6s-30.1 17.4-40.1 22.4c-7.5 5-12.6 5-17.6-5C293.5 72.3 255.9 0 255.9 0s-37.5 72.3-42.5 79.8c-5 10-10 10-17.6 5-10-5-40.1-22.4-40.1-22.4S183.3 182 183.3 192c2.5 10-7.5 17.5-17.6 10-2.5-2.5-55.1-62.3-55.1-62.3S98.1 167 95.6 172s-5 9.9-12.5 7.5C73 177 25.4 167 25.4 167s17.6 59.7 20.1 67.3c2.4 6 5 12.5-5 17.4L23 259.3s102.6 89.9 105.2 92.4c5.1 5 10 7.5 5.1 22.5-5.1 15-10.1 35.1-10.1 35.1s95.2-20.1 105.3-22.6c8.7-.9 18.3 2.5 18.3 12.5S241 512 241 512h30s-5.8-102.7-5.8-112.8 9.5-13.4 18.4-12.5c10 2.5 105.2 22.6 105.2 22.6s-5-20.1-10-35.1 0-17.5 5-22.5z' ],
			'clock'    => [ '0 0 512 512', 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 448c-110.5 0-200-89.5-200-200S145.5 56 256 56s200 89.5 200 200-89.5 200-200 200zm61.8-104.4l-84.9-61.7c-3.1-2.3-4.9-5.9-4.9-9.7V116c0-6.6 5.4-12 12-12h32c6.6 0 12 5.4 12 12v141.7l66.8 48.6c5.4 3.9 6.5 11.4 2.6 16.8L334.6 349c-3.9 5.3-11.4 6.5-16.8 2.6z' ],
			'phone'    => [ '0 0 512 512', 'M497.39 361.8l-112-48a24 24 0 0 0-28 6.9l-49.6 60.6A370.66 370.66 0 0 1 130.6 204.11l60.6-49.6a23.94 23.94 0 0 0 6.9-28l-48-112A24.16 24.16 0 0 0 122.6.61l-104 24A24 24 0 0 0 0 48c0 256.5 207.9 464 464 464a24 24 0 0 0 23.4-18.6l24-104a24.29 24.29 0 0 0-14.01-27.6z' ],
			'whatsapp' => [ '0 0 448 512', 'M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z' ],
		];
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="evo-ico evo-ico--' . $name . '" viewBox="' . $paths[ $name ][0] . '" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $paths[ $name ][1] . '"/></svg>';
	}

	/* ---------- shortcodes ---------- */

	/** Modèle global « Bloc secteurs » (ACC6). */
	public static function sc_secteurs() {
		static $depth = 0;
		$id = Evo_Migration::id( 'tpl_secteurs' );
		if ( ! $id || $depth > 0 || ! class_exists( '\Elementor\Plugin' ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$depth++;
		$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
		$depth--;
		return $html;
	}

	/** G9 : « · Mentions légales · Politique de confidentialité · RC … · ICE … » (éléments publiés / renseignés seulement). */
	public static function sc_footer_legal() {
		$parts = [];
		foreach ( [ 'mentions-legales' => 'Mentions légales', 'politique-de-confidentialite' => 'Politique de confidentialité' ] as $slug => $label ) {
			$p = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $p && 'publish' === $p->post_status ) {
				$parts[] = '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		$ids = self::sc_company_ids();
		if ( $ids ) {
			$parts[] = $ids;
		}
		return $parts ? ' · ' . implode( ' · ', $parts ) : '';
	}

	public static function sc_company_ids() {
		$out = [];
		if ( Evo_Settings::get( 'company_rc' ) ) {
			$out[] = 'RC : ' . esc_html( Evo_Settings::get( 'company_rc' ) );
		}
		if ( Evo_Settings::get( 'company_ice' ) ) {
			$out[] = 'ICE : ' . esc_html( Evo_Settings::get( 'company_ice' ) );
		}
		return implode( ' · ', $out );
	}

	/* ---------- cache ---------- */

	public static function always_fresh( $is_dynamic, $raw ) {
		$type = is_array( $raw ) ? ( $raw['widgetType'] ?? '' ) : '';
		if ( in_array( $type, [ 'ekit-nav-menu', 'nav-menu' ], true ) ) {
			return true;
		}
		if ( 'shortcode' === $type && false !== strpos( (string) ( $raw['settings']['shortcode'] ?? '' ), 'evo_bloc_secteurs' ) ) {
			return true;
		}
		return $is_dynamic;
	}

	/** Menu enregistré : on vide le cache d'éléments Elementor et le cache de pages, pour que le changement soit visible tout de suite. */
	public static function purge_after_menu_save() {
		delete_post_meta_by_key( '_elementor_element_cache' );
		if ( class_exists( '\SpeedyCache\Delete' ) && method_exists( '\SpeedyCache\Delete', 'run' ) ) {
			try {
				\SpeedyCache\Delete::run( [] );
			} catch ( \Throwable $e ) {
				return;
			}
		}
	}

	/* ---------- rendu des widgets ---------- */

	/** Boutons de téléchargement masqués tant que le fichier n'est pas renseigné (catalogue, certifications). */
	public static function should_render( $should, $widget ) {
		if ( ! $should || 'button' !== $widget->get_name() ) {
			return $should;
		}
		$url = (string) ( $widget->get_settings( 'link' )['url'] ?? '' );
		if ( preg_match( '#/telechargement/([a-z0-9-]+)/?#', $url, $mm ) ) {
			$targets = Evo_Settings::download_targets();
			return ! empty( $targets[ $mm[1] ] );
		}
		return $should;
	}

	public static function widget_content( $content, $widget ) {
		$name    = $widget->get_name();
		$classes = (string) $widget->get_settings( '_css_classes' );

		// ACC4 : zones cliquables sur l'image « 4 secteurs ».
		if ( 'image' === $name && false !== strpos( $classes, 'evo-secteurs-map' ) ) {
			$zones = [
				[ 'enzy-degrese', 'Hôtels & restaurants — ENZY-DEGRESE', 'left:0;top:0;width:49.7%;height:48.6%' ],
				[ 'enzy-carpet', 'Écoles & crèches — ENZY-CARPET', 'left:50.1%;top:0;width:49.9%;height:48.6%' ],
				[ 'enzy-power', 'Industries & usines — ENZY-POWER', 'left:0;top:49.2%;width:49.7%;height:43.6%' ],
				[ 'enzy-floor', 'Santé — ENZY-FLOOR', 'left:50.1%;top:49.2%;width:49.9%;height:43.6%' ],
			];
			$links = '';
			foreach ( $zones as $z ) {
				$links .= '<a class="evo-hotspot" href="' . esc_url( home_url( '/enzy/#' . $z[0] ) ) . '" style="' . esc_attr( $z[2] ) . '" aria-label="' . esc_attr( $z[1] ) . '"><span>' . esc_html( $z[1] ) . '</span></a>';
			}
			$content = preg_replace( '#(<img\b[^>]*>)#', '<span class="evo-map-wrap">$1<span class="evo-hotspots">' . str_replace( '$', '\$', $links ) . '</span></span>', $content, 1 );
		}

		// Consentement : lien vers la politique seulement si la page est publiée.
		if ( 'mf-gdpr-consent' === $name && ! self::legal_published( 'politique-de-confidentialite' ) ) {
			$content = preg_replace( '#<a[^>]*politique-de-confidentialite[^>]*>(.*?)</a>#s', '$1', $content );
		}
		return $content;
	}

	public static function legal_published( $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		return $p && 'publish' === $p->post_status;
	}

	/* ---------- routes ---------- */

	/** /telechargement/{catalogue-enzy|certification-enzy-xxx}/ → fichier renseigné dans les réglages. */
	public static function downloads() {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $home && 0 === strpos( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( ! preg_match( '#^telechargement/([a-z0-9-]+)$#', $path, $mm ) ) {
			return;
		}
		$targets = Evo_Settings::download_targets();
		$target  = $targets[ $mm[1] ] ?? '';
		nocache_headers();
		wp_redirect( $target ? $target : home_url( '/contact/' ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/** SEO6 : anciennes adresses des pages secteurs (301). */
	public static function redirects() {
		if ( ! is_404() ) {
			return;
		}
		$map = get_option( 'evo_corrections_redirects', [] );
		if ( ! $map || ! is_array( $map ) ) {
			return;
		}
		$path = trailingslashit( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $map[ $path ] ) ) {
			$qs = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY ); // phpcs:ignore
			wp_safe_redirect( home_url( $map[ $path ] ) . ( $qs ? '?' . $qs : '' ), 301 );
			exit;
		}
	}

	/** SUI2 : page 404 utile. */
	public static function custom_404( $template ) {
		if ( is_404() ) {
			return EVO_CORR_DIR . 'templates/404.php';
		}
		return $template;
	}

	/* ---------- formulaires ---------- */

	/** FOR5 : champ piège anti-spam (invisible pour les visiteurs). */
	public static function honeypot( $result ) {
		if ( ! empty( $result['form_data']['mf-site-web'] ) ) {
			$result['is_valid'] = false;
			$result['message']  = 'Votre demande n\'a pas pu être envoyée. Merci de nous écrire sur WhatsApp.';
		}
		return $result;
	}

	/* ---------- images ---------- */

	/** SEO3 : alt déduit du titre du média quand il est vide. */
	public static function alt_fallback( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) && $attachment instanceof WP_Post ) {
			$title       = preg_replace( '/[-_]+/', ' ', (string) $attachment->post_title );
			$title       = trim( preg_replace( '/\b(\d+x\d+|scaled|removebg|preview|copy)\b/i', '', $title ) );
			$attr['alt'] = $title ? ucfirst( $title ) : 'EVO Industries';
		}
		return $attr;
	}
}
