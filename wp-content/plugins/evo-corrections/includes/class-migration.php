<?php
/**
 * Moteur : liste ordonnée des corrections, exécution (ou simulation), journal.
 */

defined( 'ABSPATH' ) || exit;

class Evo_Migration {

	/* Pages et modèles du site (IDs de la base evoindustries.ma). */
	const HOME    = 25;
	const ENZY    = 101;
	const ECOLES  = 167;
	const SANTE   = 253;
	const HOTEL   = 280;
	const INDUS   = 291;
	const BEAUTE  = 536;
	const APROPOS = 208;
	const CONTACT = 258;
	const BLOG    = 355;
	const HEADER  = 310;
	const FOOTER  = 331;

	/* Formulaires MetForm. */
	const FORM_ENZY   = 373;
	const FORM_ECOLES = 495;
	const FORM_SANTE  = 503;
	const FORM_HOTEL  = 508;
	const FORM_INDUS  = 499;

	const BLOG_POSTS = [ 365, 367, 369, 371 ];

	const PRODUCTS = [
		'enzy-floor'   => 'ENZY-FLOOR',
		'enzy-carpet'  => 'ENZY-CARPET',
		'enzy-degrese' => 'ENZY-DEGRESE',
		'enzy-power'   => 'ENZY-POWER',
		'enzy-glass'   => 'ENZY-GLASS',
	];

	/* G13 : couleur fixe par produit. */
	const PRODUCT_COLORS = [
		'enzy-floor'   => '#1A7A6E',
		'enzy-carpet'  => '#E8841A',
		'enzy-degrese' => '#2563A8',
		'enzy-power'   => '#7B2FBE',
		'enzy-glass'   => '#F5C518',
	];

	const BRANDS = [
		'sens-eve'       => 'Sens Ēve',
		'x-pur'          => 'X-PUR',
		'v-kosmetik'     => 'V-Kosmetik',
		'herbal-care'    => 'Herbal Care',
		'fruiteen'       => 'Fruiteen',
		'salon-cosmepro' => 'Salon Cosmepro',
		'neolia'         => 'Néolia',
	];

	const OPT_APPLIED = 'evo_corrections_applied';
	const OPT_LOG     = 'evo_corrections_last_log';
	const OPT_IDS     = 'evo_corrections_ids';

	/** @var Evo_Store */
	public $store;
	public $dry = false;
	public $log = [];
	public $current = '';

	public function __construct( $dry = false ) {
		$this->dry        = (bool) $dry;
		$this->store      = new Evo_Store();
		$this->store->dry = $this->dry;
	}

	/**
	 * Corrections dans l'ordre d'exécution : id => [ libellé, callable ].
	 * Les libellés reprennent la numérotation du document de Salma.
	 */
	public static function steps() {
		return [
			'G1'     => [ 'G1 — Images du domaine lf-signature-immobilier → médiathèque evoindustries.ma', 'evo_step_g1' ],
			'G2'     => [ 'G2 — Blog Lorem Ipsum en brouillon (page /blog/ + 4 articles)', 'evo_step_g2' ],
			'PER7'   => [ 'PER7 — Fichiers renommés (noms descriptifs) et remplacés partout', 'evo_step_per7' ],
			'ACC6'   => [ 'ACC5 · ACC6 · ENZY9 — Bloc secteurs en modèle global (cartes cliquables, pastilles produits)', 'evo_step_acc6' ],
			'ACC'    => [ 'Accueil — textes, H1, boutons du hero, chiffres, marques cliquables, bandeau avant footer', 'evo_step_home' ],
			'ENZY'   => [ 'Solutions ENZY — textes, dilutions supprimées, fiches techniques, certifications, ancres, visuel', 'evo_step_enzy' ],
			'SEC'    => [ 'Pages secteurs — puces, sur-titres, Santé, Hôtellerie, Industrie', 'evo_step_sectors' ],
			'BEA'    => [ 'Beauté & Bien-être — textes, ancres marques, catalogues, visuel, bloc revendeur', 'evo_step_beaute' ],
			'APR'    => [ 'À propos — H1, chiffres, pastilles, section supprimée', 'evo_step_apropos' ],
			'CON'    => [ 'Contact — H1, bandeau réduit, coordonnées cliquables, mise en page', 'evo_step_contact' ],
			'FOR'    => [ 'Formulaires MetForm — libellés, listes, zone de texte, ville, consentement, anti-spam, /merci/, notifications', 'evo_step_forms' ],
			'FORNEW' => [ 'FOR1 · FOR4 · BEA3 — Pages /devis/ et /merci/, formulaire revendeur, formulaire court Accueil/Contact', 'evo_step_new_forms' ],
			'LEG1'   => [ 'LEG1 — Pages Mentions légales et Politique de confidentialité (brouillons à valider)', 'evo_step_legal' ],
			'SEO6'   => [ 'SEO6 — Slugs /secteurs/… + redirections 301 + page Secteurs', 'evo_step_seo6' ],
			'G3'     => [ 'G3 — Menu principal (Solutions ENZY ▾ · Secteurs ▾ · Beauté · À propos · Contact) + bouton devis', 'evo_step_menu' ],
			'G5'     => [ 'G5 — Favicon 512 × 512', 'evo_step_favicon' ],
			'G18'    => [ 'G18 — Bouton « Demander un devis » dans le header mobile', 'evo_step_mobile_header' ],
			'FOOT'   => [ 'G6 → G10 — Footer : liens produits/marques, coordonnées, ligne légale, EvoRoyal', 'evo_step_footer' ],
			'T21'    => [ '2.1 — Noms de produits et de marques harmonisés partout', 'evo_step_texts' ],
			'G11'    => [ 'G11 · G12 — 2 polices (Poppins + DM Sans), réglages globaux Elementor', 'evo_step_fonts' ],
			'G13'    => [ 'G13 · G14 · G15 — Couleurs produits, styles de boutons, sur-titres', 'evo_step_design' ],
			'G16'    => [ 'G16 — Animations d\'entrée supprimées', 'evo_step_animations' ],
			'PER6'   => [ 'PER6 — Tailles d\'images adaptées (logo, vignettes)', 'evo_step_per6' ],
			'PER4'   => [ 'PER4 — Réglages de performance Elementor', 'evo_step_per4' ],
			'SEO3'   => [ 'SEO3 — Textes alternatifs des images', 'evo_step_seo3' ],
		];
	}

	public static function applied() {
		$a = get_option( self::OPT_APPLIED, [] );
		return is_array( $a ) ? $a : [];
	}

	/** IDs des éléments créés (pages devis/merci, formulaires, modèle...). */
	public static function ids() {
		$a = get_option( self::OPT_IDS, [] );
		return is_array( $a ) ? $a : [];
	}

	public static function id( $key ) {
		$ids = self::ids();
		return isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
	}

	public function remember_id( $key, $id ) {
		if ( $this->dry ) {
			return;
		}
		$ids         = self::ids();
		$ids[ $key ] = (int) $id;
		update_option( self::OPT_IDS, $ids, false );
	}

	/**
	 * Lance les corrections sélectionnées ($only = liste d'IDs, null = toutes celles pas encore appliquées).
	 */
	public function run( $only = null ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_raise_memory_limit( 'admin' );
		$applied = self::applied();
		foreach ( self::steps() as $id => $step ) {
			if ( is_array( $only ) && ! in_array( $id, $only, true ) ) {
				continue;
			}
			if ( null === $only && isset( $applied[ $id ] ) ) {
				continue;
			}
			$this->current = $id;
			$this->log( '— ' . $step[0], 'title' );
			try {
				call_user_func( $step[1], $this );
				// Chaque étape enregistre ses arbres, pour que les suivantes relisent l'état à jour.
				$this->store->flush();
				if ( ! $this->dry ) {
					$applied[ $id ] = current_time( 'mysql' );
					update_option( self::OPT_APPLIED, $applied, false );
				}
			} catch ( \Throwable $e ) {
				$this->log( 'Erreur : ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')', 'error' );
			}
		}
		if ( ! $this->dry ) {
			Evo_Store::clear_caches();
			$this->log( 'Caches Elementor (CSS régénérés) et SpeedyCache vidés.', 'info' );
		}
		update_option( self::OPT_LOG, [ 'date' => current_time( 'mysql' ), 'dry' => $this->dry, 'log' => $this->log ], false );
		return $this->log;
	}

	public function log( $msg, $level = 'ok' ) {
		$this->log[] = [ 'step' => $this->current, 'level' => $level, 'msg' => $msg ];
	}

	public function warn( $msg ) {
		$this->log( $msg, 'warn' );
	}

	/* ---------- accès aux éléments ---------- */

	public function has_post( $post_id ) {
		return $post_id && $this->store->exists( $post_id );
	}

	/** Référence vers un élément ; journalise s'il est introuvable. */
	public function &el( $post_id, $el_id, $quiet = false ) {
		$els = &$this->store->els( $post_id );
		$ref = &Evo_Tree::get( $els, $el_id );
		if ( null === $ref && ! $quiet ) {
			$this->warn( "Élément $el_id introuvable sur #$post_id (déjà modifié ou supprimé à la main ?)" );
		}
		return $ref;
	}

	/** Fusionne des réglages dans un élément. */
	public function set( $post_id, $el_id, array $settings ) {
		$ref = &$this->el( $post_id, $el_id );
		if ( null === $ref ) {
			return false;
		}
		foreach ( $settings as $k => $v ) {
			$ref['settings'][ $k ] = $v;
		}
		$this->store->mark( $post_id );
		return true;
	}

	/** Modifie le texte principal d'un widget (titre, éditeur, texte de bouton...). */
	public function text( $post_id, $el_id, $value ) {
		$ref = &$this->el( $post_id, $el_id );
		if ( null === $ref ) {
			return false;
		}
		$type = $ref['widgetType'] ?? '';
		$map  = [
			'heading'             => 'title',
			'text-editor'         => 'editor',
			'button'              => 'text',
			'icon-box'            => 'title_text',
			'image-box'           => 'title_text',
			'elementskit-heading' => 'ekit_heading_title',
			'testimonial'         => 'testimonial_content',
		];
		$key = $map[ $type ] ?? 'title';
		if ( 'text-editor' === $type && false === strpos( $value, '<' ) ) {
			$value = '<p>' . esc_html( $value ) . '</p>';
		}
		$ref['settings'][ $key ] = $value;
		$this->store->mark( $post_id );
		return true;
	}

	public function remove( $post_id, $el_id ) {
		$els = &$this->store->els( $post_id );
		$r   = Evo_Tree::remove( $els, $el_id );
		if ( null === $r ) {
			$this->warn( "Élément $el_id introuvable sur #$post_id (déjà supprimé ?)" );
			return false;
		}
		$this->store->mark( $post_id );
		return true;
	}

	public function insert( $post_id, $target_id, array $new, $position = 'after' ) {
		$els = &$this->store->els( $post_id );
		if ( ! Evo_Tree::insert( $els, $target_id, $new, $position ) ) {
			$this->warn( "Point d'insertion $target_id introuvable sur #$post_id" );
			return false;
		}
		$this->store->mark( $post_id );
		return true;
	}

	/** Copie (nouveaux IDs) d'un élément d'une page, ou null. */
	public function copy( $post_id, $el_id ) {
		$els = $this->store->els( $post_id );
		$ref = &Evo_Tree::get( $els, $el_id );
		if ( null === $ref ) {
			$this->warn( "Élément modèle $el_id introuvable sur #$post_id" );
			return null;
		}
		return Evo_Tree::clone_el( $ref );
	}

	/** Un marqueur (classe CSS) indique qu'un ajout a déjà été fait : évite les doublons. */
	public function has_marker( $post_id, $class ) {
		$els = $this->store->els( $post_id );
		return (bool) Evo_Tree::find_all( $els, fn( $e ) => Evo_Tree::has_class( $e, $class ) );
	}

	/** Tous les posts qui ont des données Elementor (pages, modèles, formulaires, bibliothèque). */
	public function elementor_post_ids() {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_elementor_data' AND pm.meta_value <> '' AND p.post_type NOT IN ('revision')
			 AND p.post_status NOT IN ('trash','auto-draft','inherit')"
		);
		return array_map( 'intval', $ids );
	}

	/* ---------- création ---------- */

	/** Crée un post (page, formulaire, modèle) ; suivi pour la restauration. */
	public function create_post( array $args, array $meta = [], $els = null ) {
		static $fake = -1;
		if ( $this->dry ) {
			$this->log( 'Simulation : création de « ' . ( $args['post_title'] ?? '' ) . ' » (' . ( $args['post_type'] ?? 'post' ) . ')', 'info' );
			$id = $fake--;
			if ( null !== $els ) {
				$this->store->set( $id, $els );
			}
			return $id;
		}
		$id = wp_insert_post( wp_slash( array_merge( [ 'post_status' => 'publish', 'post_type' => 'page' ], $args ) ), true );
		if ( is_wp_error( $id ) ) {
			$this->log( 'Création impossible : ' . $id->get_error_message(), 'error' );
			return 0;
		}
		Evo_Store::track_created( $id );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, is_string( $v ) ? wp_slash( $v ) : $v );
		}
		if ( null !== $els ) {
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $els ) ) );
		}
		$this->log( 'Créé : « ' . ( $args['post_title'] ?? '' ) . ' » (#' . $id . ')' );
		return $id;
	}

	/** Crée une page Elementor pleine largeur (même gabarit que les autres pages). */
	public function create_elementor_page( $title, $slug, array $els, $status = 'publish', $parent = 0 ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing && ! $this->dry ) {
			$this->log( "La page /$slug/ existe déjà (#{$existing->ID}) : conservée.", 'info' );
			return (int) $existing->ID;
		}
		return $this->create_post(
			[ 'post_title' => $title, 'post_name' => $slug, 'post_type' => 'page', 'post_status' => $status, 'post_parent' => $parent ],
			[
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => 'wp-page',
				'_elementor_version'       => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.3.2',
				'_wp_page_template'        => 'elementor_header_footer',
			],
			$els
		);
	}

	/** Importe un fichier du dossier assets/img du plugin dans la médiathèque. */
	public function import_asset( $file, $title, $alt ) {
		$key = 'asset_' . sanitize_key( $file );
		if ( self::id( $key ) && get_post( self::id( $key ) ) ) {
			return self::id( $key );
		}
		if ( $this->dry ) {
			return -1;
		}
		$src = EVO_CORR_DIR . 'assets/img/' . $file;
		$up  = wp_upload_bits( $file, null, file_get_contents( $src ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! empty( $up['error'] ) ) {
			$this->log( 'Import média impossible (' . $file . ') : ' . $up['error'], 'error' );
			return 0;
		}
		$id = $this->register_attachment( $up['file'], $title, $alt );
		$this->remember_id( $key, $id );
		return $id;
	}

	/** Enregistre un fichier déjà présent dans uploads comme média (avec tailles). */
	public function register_attachment( $path, $title, $alt = '' ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$type = wp_check_filetype( $path );
		$id   = wp_insert_attachment(
			[
				'post_title'     => $title,
				'post_mime_type' => $type['type'] ? $type['type'] : 'application/octet-stream',
				'post_status'    => 'inherit',
			],
			$path
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		Evo_Store::track_created( $id );
		$meta = wp_generate_attachment_metadata( $id, $path );
		if ( $meta ) {
			wp_update_attachment_metadata( $id, $meta );
		}
		if ( $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		return (int) $id;
	}

	public static function media_array( $attachment_id ) {
		return [
			'url'    => (string) wp_get_attachment_url( $attachment_id ),
			'id'     => (int) $attachment_id,
			'size'   => '',
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'source' => 'library',
		];
	}
}
