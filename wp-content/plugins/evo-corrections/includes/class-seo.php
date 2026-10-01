<?php
/**
 * 11. SEO : titles / meta descriptions (SEO2), Open Graph (SEO4), Schema.org (SEO5), sitemap (SEO1).
 * Si Rank Math ou Yoast est activé plus tard, ce module s'efface automatiquement (sauf le nettoyage du sitemap natif).
 */

defined( 'ABSPATH' ) || exit;

class Evo_Seo {

	public static function init() {
		add_filter( 'wp_sitemaps_post_types', [ __CLASS__, 'sitemap_post_types' ] );
		add_filter( 'wp_sitemaps_add_provider', [ __CLASS__, 'sitemap_providers' ], 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', [ __CLASS__, 'sitemap_taxonomies' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ __CLASS__, 'sitemap_exclude' ], 10, 2 );
		add_filter( 'wp_robots', [ __CLASS__, 'robots' ] );
		if ( is_admin() || self::seo_plugin_active() ) {
			return;
		}
		add_filter( 'pre_get_document_title', [ __CLASS__, 'title' ], 20 );
		add_action( 'wp_head', [ __CLASS__, 'meta' ], 3 );
	}

	public static function seo_plugin_active() {
		return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || class_exists( 'SeoPress_Seo_Metabox' );
	}

	/** Titles (≤ 60 car. hors marque) et meta descriptions (≤ 155 car.). */
	public static function pages() {
		$pages = [
			Evo_Migration::HOME    => [ 'EVO Industries Maroc | Nettoyants enzymatiques ENZY pour pros', 'Distributeur au Maroc de solutions de nettoyage enzymatique canadiennes ENZY pour écoles, cliniques, hôtels et industrie. Devis sous 24 h.' ],
			Evo_Migration::ENZY    => [ 'Gamme ENZY : 5 nettoyants enzymatiques professionnels | EVO Industries', 'Sols, tapis, dégraissant, vitres, industrie lourde : 5 formules biodégradables à pH neutre, fiches techniques et certifications.' ],
			Evo_Migration::ECOLES  => [ 'Produits d\'entretien pour écoles et crèches au Maroc | ENZY', 'Nettoyage enzymatique sans émanations nocives pour écoles et crèches au Maroc : sols, tables, sanitaires, tapis. Fiche technique fournie, devis sous 24 h.' ],
			Evo_Migration::SANTE   => [ 'Nettoyage enzymatique pour cliniques et cabinets | ENZY Maroc', 'Un nettoyage en profondeur, sans vapeurs agressives, pour cliniques et cabinets au Maroc. Formules pH 7–8 fabriquées au Canada. Devis sous 24 h.' ],
			Evo_Migration::HOTEL   => [ 'Produits d\'entretien pour hôtels et restaurants au Maroc | ENZY', 'Chambres, cuisines, tapis : un nettoyage enzymatique sans odeur chimique pour hôtels et restaurants au Maroc. Fabriqué au Canada, devis sous 24 h.' ],
			Evo_Migration::INDUS   => [ 'Dégraissant industriel biodégradable au Maroc | ENZY-POWER', 'ENZY-POWER : dégraissant enzymatique pour béton et huiles lourdes, non corrosif, compatible autolaveuses. Fabriqué au Canada, devis sous 24 h.' ],
			Evo_Migration::BEAUTE  => [ 'Marques de beauté canadiennes au Maroc | EVO Industries', 'Sens Ēve, X-PUR, V-Kosmetik, Herbal Care, Fruiteen, Salon Cosmepro, Néolia : 7 marques canadiennes distribuées en exclusivité au Maroc.' ],
			Evo_Migration::APROPOS => [ 'À propos d\'EVO Industries, filiale d\'EvoRoyal (Canada)', 'Filiale marocaine du groupe canadien EvoRoyal, EVO Industries distribue au Maroc la gamme ENZY et 7 marques canadiennes de beauté et bien-être.' ],
			Evo_Migration::CONTACT => [ 'Contact & devis | EVO Industries Maroc', 'Téléphone, email, WhatsApp Business : contactez EVO Industries à Casablanca. Réponse sous 24 h ouvrées à toute demande de devis.' ],
		];
		$extra = [
			'page_devis'     => [ 'Demande de devis | EVO Industries Maroc', 'Décrivez votre besoin (secteur, surface, produits) : devis ENZY et fiche technique sous 24 h ouvrées. Écoles, cliniques, hôtels, industrie.' ],
			'page_secteurs'  => [ 'Nettoyage enzymatique par secteur au Maroc | ENZY', 'Écoles et crèches, santé, hôtellerie et restauration, industrie : la solution ENZY adaptée aux contraintes de chaque secteur.' ],
			'page_merci'     => [ 'Merci | EVO Industries Maroc', '' ],
		];
		foreach ( $extra as $key => $v ) {
			if ( Evo_Migration::id( $key ) ) {
				$pages[ Evo_Migration::id( $key ) ] = $v;
			}
		}
		return $pages;
	}

	public static function current() {
		if ( is_front_page() ) {
			$id = (int) get_option( 'page_on_front' );
		} elseif ( is_singular() ) {
			$id = get_queried_object_id();
		} else {
			return null;
		}
		$pages = self::pages();
		return isset( $pages[ $id ] ) ? $pages[ $id ] : null;
	}

	public static function title( $title ) {
		$c = self::current();
		return $c ? $c[0] : $title;
	}

	public static function og_image() {
		$sector_images = [
			Evo_Migration::ECOLES => 'og-ecoles.jpg',
			Evo_Migration::SANTE  => 'og-sante.jpg',
			Evo_Migration::HOTEL  => 'og-hotellerie.jpg',
			Evo_Migration::INDUS  => 'og-industrie.jpg',
		];
		$id = is_singular() ? get_queried_object_id() : 0;
		if ( isset( $sector_images[ $id ] ) && file_exists( EVO_CORR_DIR . 'assets/img/' . $sector_images[ $id ] ) ) {
			return EVO_CORR_URL . 'assets/img/' . $sector_images[ $id ];
		}
		return EVO_CORR_URL . 'assets/img/og-evo-industries-1200x630.jpg';
	}

	public static function meta() {
		$c     = self::current();
		$title = $c ? $c[0] : wp_get_document_title();
		$desc  = $c ? $c[1] : '';
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( [] ) );
		if ( is_front_page() ) {
			$url = home_url( '/' );
		}
		if ( $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		$og = [
			'og:locale'      => 'fr_FR',
			'og:type'        => is_front_page() ? 'website' : 'article',
			'og:site_name'   => 'EVO Industries Morocco',
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => $url,
			'og:image'       => self::og_image(),
			'og:image:width' => '1200',
			'og:image:height' => '630',
		];
		foreach ( $og as $prop => $value ) {
			if ( '' !== $value ) {
				printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $value ) );
			}
		}
		printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
		self::schema();
	}

	/* ---------- Schema.org (SEO5) ---------- */

	public static function schema() {
		$home  = home_url( '/' );
		$logo  = EVO_CORR_URL . 'assets/img/favicon-evo-512.png';
		$logo_id = attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/2026/08/logo-horizontal-evo-industries.png' );
		if ( $logo_id ) {
			$logo = wp_get_attachment_url( $logo_id );
		}
		$address = [ '@type' => 'PostalAddress', 'addressLocality' => 'Casablanca', 'addressCountry' => 'MA' ];
		$same    = array_values( array_filter( [ Evo_Settings::get( 'linkedin' ), Evo_Settings::get( 'instagram' ) ] ) );
		$graph   = [];

		$graph[] = [
			'@type'              => 'Organization',
			'@id'                => $home . '#organization',
			'name'               => 'EVO Industries Morocco',
			'url'                => $home,
			'logo'               => $logo,
			'email'              => Evo_Settings::get( 'email' ),
			'telephone'          => Evo_Settings::get( 'phone_intl' ),
			'address'            => $address,
			'sameAs'             => $same,
			'parentOrganization' => [ '@type' => 'Organization', 'name' => 'EvoRoyal Global Industries Inc.', 'url' => 'https://evoroyal.ca/' ],
		];
		if ( is_front_page() || is_page( Evo_Migration::CONTACT ) ) {
			$graph[] = [
				'@type'                     => 'LocalBusiness',
				'@id'                       => $home . '#localbusiness',
				'name'                      => 'EVO Industries Morocco',
				'url'                       => $home,
				'image'                     => self::og_image(),
				'telephone'                 => Evo_Settings::get( 'phone_intl' ),
				'email'                     => Evo_Settings::get( 'email' ),
				'address'                   => $address,
				'openingHoursSpecification' => [
					[
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ],
						'opens'     => '09:00',
						'closes'    => '18:00',
					],
				],
				'parentOrganization'        => [ '@id' => $home . '#organization' ],
			];
		}
		if ( is_page( Evo_Migration::ENZY ) ) {
			$desc = [
				'enzy-floor'   => 'Nettoyant enzymatique naturel pour sols : nettoyage en profondeur, pH 7–8, biodégradable.',
				'enzy-carpet'  => 'Nettoyant enzymatique naturel pour tapis, moquettes et textiles d\'ameublement.',
				'enzy-degrese' => 'Dégraissant enzymatique tout usage : cuisines, surfaces, équipements.',
				'enzy-power'   => 'Dégraissant industriel enzymatique pour béton et huiles lourdes, non corrosif.',
				'enzy-glass'   => 'Nettoyant naturel pour vitres, miroirs et inox, sans traces.',
			];
			$up = wp_get_upload_dir()['baseurl'];
			$img = [
				'enzy-floor'   => $up . '/2026/08/enzy-floor-bidon-5l.png',
				'enzy-carpet'  => $up . '/2026/08/enzy-carpet-bidon-5l.png',
				'enzy-degrese' => $up . '/2026/08/enzy-degrese-bidon-5l.png',
				'enzy-power'   => $up . '/2026/09/enzy-power-bidon-5l.png',
				'enzy-glass'   => $up . '/2026/08/enzy-glass-bidon-5l.png',
			];
			foreach ( Evo_Migration::PRODUCTS as $slug => $name ) {
				$graph[] = [
					'@type'        => 'Product',
					'@id'          => get_permalink( Evo_Migration::ENZY ) . '#' . $slug,
					'name'         => $name,
					'description'  => $desc[ $slug ],
					'image'        => $img[ $slug ],
					'brand'        => [ '@type' => 'Brand', 'name' => 'ENZY' ],
					'manufacturer' => [ '@type' => 'Organization', 'name' => 'EvoRoyal Global Industries Inc.' ],
					'countryOfOrigin' => 'CA',
				];
			}
		}
		if ( is_singular() && ! is_front_page() ) {
			$items = [ [ 'name' => 'Accueil', 'url' => $home ] ];
			$post  = get_queried_object();
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $anc ) {
				$items[] = [ 'name' => get_the_title( $anc ), 'url' => get_permalink( $anc ) ];
			}
			$items[] = [ 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) ];
			$list    = [];
			foreach ( $items as $i => $it ) {
				$list[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $it['name'] ), 'item' => $it['url'] ];
			}
			$graph[] = [ '@type' => 'BreadcrumbList', 'itemListElement' => $list ];
		}
		echo '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => $graph ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore
	}

	/* ---------- robots & sitemap (SEO1) ---------- */

	public static function robots( $robots ) {
		$merci = Evo_Migration::id( 'page_merci' );
		if ( ( $merci && is_page( $merci ) ) || is_author() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	public static function sitemap_post_types( $types ) {
		foreach ( [ 'elementskit_template', 'metform-form', 'elementor_library', 'e-landing-page', 'attachment' ] as $t ) {
			unset( $types[ $t ] );
		}
		// Articles : uniquement s'il y en a de publiés (le blog Lorem Ipsum est en brouillon).
		if ( isset( $types['post'] ) && ! wp_count_posts( 'post' )->publish ) {
			unset( $types['post'] );
		}
		return $types;
	}

	public static function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function sitemap_taxonomies( $taxonomies ) {
		unset( $taxonomies['post_tag'], $taxonomies['post_format'] );
		if ( ! wp_count_posts( 'post' )->publish ) {
			unset( $taxonomies['category'] );
		}
		return $taxonomies;
	}

	public static function sitemap_exclude( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$exclude = array_filter( [ Evo_Migration::id( 'page_merci' ), Evo_Migration::BLOG ] );
			$args['post__not_in'] = array_merge( $args['post__not_in'] ?? [], $exclude );
		}
		return $args;
	}
}
