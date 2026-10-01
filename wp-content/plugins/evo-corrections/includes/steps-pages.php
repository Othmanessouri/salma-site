<?php
/**
 * Corrections page par page : Accueil (3), ENZY (4), secteurs (5), Beauté (6), À propos (7), Contact (8).
 */

defined( 'ABSPATH' ) || exit;

/** Produits recommandés par secteur (pastilles des cartes secteurs). */
function evo_sector_products() {
	return [
		'ecoles'     => [ 'enzy-floor', 'enzy-degrese', 'enzy-carpet' ],
		'sante'      => [ 'enzy-floor', 'enzy-glass' ],
		'hotellerie' => [ 'enzy-carpet', 'enzy-degrese', 'enzy-floor' ],
		'industrie'  => [ 'enzy-power', 'enzy-degrese' ],
	];
}

function evo_pills_html( array $slugs ) {
	$out = '';
	foreach ( $slugs as $s ) {
		$out .= '<span class="evo-pill evo-pill--' . esc_attr( $s ) . '">' . esc_html( Evo_Migration::PRODUCTS[ $s ] ) . '</span> ';
	}
	return '<p class="evo-pills">' . trim( $out ) . '</p>';
}

/** Remplace une chaîne dans le texte d'un widget (editor, title...) sans toucher au reste. */
function evo_replace_in( Evo_Migration $m, $pid, $el_id, $search, $replace, $key = null ) {
	$ref = &$m->el( $pid, $el_id );
	if ( null === $ref ) {
		return false;
	}
	$keys = $key ? [ $key ] : [ 'editor', 'title', 'text', 'title_text', 'description_text' ];
	foreach ( $keys as $k ) {
		if ( isset( $ref['settings'][ $k ] ) && is_string( $ref['settings'][ $k ] ) ) {
			foreach ( (array) $search as $s ) {
				foreach ( [ $s, esc_html( $s ), str_replace( '&', '&amp;', $s ) ] as $variant ) {
					if ( false !== strpos( $ref['settings'][ $k ], $variant ) ) {
						$ref['settings'][ $k ] = str_replace( $variant, $replace, $ref['settings'][ $k ] );
						$m->store->mark( $pid );
						return true;
					}
				}
			}
		}
	}
	return false;
}

/** Remplace le texte après « Libellé : » en gardant le libellé et la mise en forme. */
function evo_replace_after_label( Evo_Migration $m, $pid, $el_id, $label, $value ) {
	$ref = &$m->el( $pid, $el_id );
	if ( null === $ref ) {
		return false;
	}
	$html = (string) ( $ref['settings']['editor'] ?? '' );
	$new  = preg_replace( '/(' . preg_quote( $label, '/' ) . '\s*(?:<\/[a-z]+>)?\s*:?\s*(?:<\/[a-z]+>)?\s*)[^<]*/u', '${1}' . str_replace( '$', '\$', esc_html( $value ) ), $html, 1, $count );
	if ( ! $count ) {
		$new = '<p>' . esc_html( $label . ' : ' . $value ) . '</p>';
	}
	$ref['settings']['editor'] = $new;
	$m->store->mark( $pid );
	return true;
}

/** Remplace les éléments d'une liste d'icônes en conservant le style des éléments existants. */
function evo_set_icon_list( Evo_Migration $m, $pid, $el_id, array $items ) {
	$ref = &$m->el( $pid, $el_id );
	if ( null === $ref ) {
		return false;
	}
	$old  = $ref['settings']['icon_list'] ?? [];
	$base = $old[0] ?? [ 'selected_icon' => [ 'value' => 'fas fa-check', 'library' => 'fa-solid' ] ];
	$new  = [];
	foreach ( array_values( $items ) as $i => $item ) {
		$row          = $old[ $i ] ?? $base;
		$row['_id']   = substr( Evo_Tree::new_id(), 0, 7 );
		$row['text']  = $item['text'];
		$row['link']  = Evo_Tree::link( $item['url'] ?? '', ! empty( $item['external'] ) );
		if ( ! empty( $item['icon'] ) ) {
			$row['selected_icon'] = $item['icon'];
		}
		$new[] = $row;
	}
	$ref['settings']['icon_list'] = $new;
	$m->store->mark( $pid );
	return true;
}

function evo_icon( $value ) {
	return [ 'value' => $value, 'library' => 0 === strpos( $value, 'fab' ) ? 'fa-brands' : ( 0 === strpos( $value, 'far' ) ? 'fa-regular' : 'fa-solid' ) ];
}

function evo_add_class( Evo_Migration $m, $pid, $el_id, $classes ) {
	$ref = &$m->el( $pid, $el_id );
	if ( null === $ref ) {
		return false;
	}
	Evo_Tree::add_class( $ref, $classes );
	$m->store->mark( $pid );
	return true;
}

function evo_last_top_id( Evo_Migration $m, $pid ) {
	$els  = $m->store->els( $pid );
	$last = end( $els );
	return $last ? $last['id'] : null;
}

/** Conteneur transparent (sans marge) qui accueille un shortcode. */
function evo_shortcode_container( $shortcode, $class ) {
	$zero = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
	return Evo_Tree::container(
		[ 'content_width' => 'full', 'padding' => $zero, 'margin' => $zero, 'css_classes' => $class ],
		[ Evo_Tree::widget( 'shortcode', [ 'shortcode' => $shortcode ] ) ],
		false
	);
}

/* ======================================================================
 * ACC5 · ACC6 · ENZY9 — Bloc secteurs en modèle global
 * ==================================================================== */
function evo_step_acc6( Evo_Migration $m ) {
	$tpl = Evo_Migration::id( 'tpl_secteurs' );
	if ( ! $tpl || ! get_post( $tpl ) ) {
		$home = $m->store->els( Evo_Migration::HOME );
		$ref  = &Evo_Tree::get( $home, '592a3d0' );
		if ( null === $ref ) {
			$m->warn( 'Bloc secteurs de l\'accueil (592a3d0) introuvable : modèle non créé.' );
			return;
		}
		$block = $ref;
		unset( $ref );
		$block['settings']['_element_id'] = 'secteurs';
		Evo_Tree::add_class( $block, 'evo-secteurs-block' );

		$cards = [
			'fafa9e3' => [ 'ecoles', '/ecoles/', 'Écoles / Crèches' ],
			'ee08f9e' => [ 'sante', '/secteur-cliniques/', 'Santé' ],
			'1eda4b4' => [ 'hotellerie', '/secteur-hotellerie/', 'Hôtellerie & Restauration' ],
			'9b522fd' => [ 'industrie', '/secteur-industrie/', 'Industrie' ],
		];
		$products = evo_sector_products();
		$wrap     = [ $block ];
		foreach ( $cards as $card_id => $c ) {
			$card = &Evo_Tree::get( $wrap, $card_id );
			if ( null === $card ) {
				$m->warn( "Carte secteur $card_id introuvable dans le bloc." );
				continue;
			}
			// Toute la carte devient le lien (ACC5) : on retire les liens internes pour éviter les liens imbriqués.
			$card['settings']['html_tag'] = 'a';
			$card['settings']['link']     = Evo_Tree::link( $c[1] );
			Evo_Tree::add_class( $card, 'evo-sector-card evo-sector-card--' . $c[0] );
			Evo_Tree::walk(
				$card['elements'],
				function ( &$w ) use ( $c ) {
					if ( isset( $w['settings']['link'] ) && is_array( $w['settings']['link'] ) ) {
						$w['settings']['link'] = Evo_Tree::link( '' );
					}
					if ( isset( $w['settings']['link_to'] ) ) {
						$w['settings']['link_to'] = 'none';
					}
					if ( 'icon-box' === ( $w['widgetType'] ?? '' ) ) {
						$w['settings']['title_text'] = $c[2];
					}
				}
			);
			$card['elements'][] = Evo_Tree::widget( 'text-editor', [ 'editor' => evo_pills_html( $products[ $c[0] ] ), '_css_classes' => 'evo-card-pills' ] );
			$card['elements'][] = Evo_Tree::widget( 'text-editor', [ 'editor' => '<p>Voir les solutions →</p>', '_css_classes' => 'evo-card-more' ] );
			unset( $card );
		}

		$type = class_exists( '\Elementor\Modules\Library\Documents\Container' ) ? 'container' : 'section';
		$tpl  = $m->create_post(
			[ 'post_title' => 'Bloc secteurs (modèle global)', 'post_type' => 'elementor_library', 'post_status' => 'publish' ],
			[
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => $type,
				'_elementor_version'       => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.3.2',
			],
			$wrap
		);
		if ( ! $tpl ) {
			return;
		}
		if ( ! $m->dry ) {
			wp_set_object_terms( $tpl, $type, 'elementor_library_type' );
		}
		$m->remember_id( 'tpl_secteurs', $tpl );
		$m->log( 'Modèle global créé (Modèles > Enregistrés) : à modifier une seule fois, il s\'affiche à l\'identique sur l\'Accueil, ENZY et la page Secteurs.' );
	} else {
		$m->log( 'Modèle global déjà présent (#' . $tpl . ').', 'info' );
	}

	// Remplacement des deux blocs par le modèle.
	foreach ( [ Evo_Migration::HOME => '592a3d0', Evo_Migration::ENZY => 'b04059a' ] as $pid => $block_id ) {
		if ( $m->has_marker( $pid, 'evo-global-secteurs' ) ) {
			continue;
		}
		$els = &$m->store->els( $pid );
		if ( Evo_Tree::replace( $els, $block_id, evo_shortcode_container( '[evo_bloc_secteurs]', 'evo-global-secteurs' ) ) ) {
			$m->store->mark( $pid );
			$m->log( 'Bloc secteurs de « ' . get_the_title( $pid ) . ' » remplacé par le modèle global.' );
		} else {
			$m->warn( "Bloc $block_id introuvable sur #$pid." );
		}
		unset( $els );
	}
}

/* ======================================================================
 * 3. Accueil
 * ==================================================================== */
function evo_step_home( Evo_Migration $m ) {
	$p = Evo_Migration::HOME;
	if ( ! $m->has_post( $p ) ) {
		$m->warn( 'Page d\'accueil introuvable.' );
		return;
	}
	$wa = Evo_Settings::whatsapp_url( 'Bonjour, je souhaite avoir plus d\'informations sur vos produits.' );

	// Hero : sur-titre + nouveau H1.
	evo_add_class( $m, $p, '6ee1d1ea', 'evo-on-dark evo-home-hero' );
	if ( ! $m->has_marker( $p, 'evo-hero-surtitre' ) ) {
		$sur = $m->copy( $p, '644cc139' );
		if ( $sur ) {
			$sur['settings']['title']       = 'Groupe EvoRoyal · Origine Canada';
			$sur['settings']['header_size'] = 'p';
			Evo_Tree::add_class( $sur, 'evo-surtitre evo-hero-surtitre' );
			$m->insert( $p, '644cc139', $sur, 'before' );
		}
	}
	$m->set( $p, '644cc139', [ 'title' => 'Nettoyage enzymatique professionnel, importé du Canada', 'header_size' => 'h1' ] );
	$m->text( $p, '21fb8a2c', '<p>Filiale marocaine du groupe canadien EvoRoyal, nous distribuons la gamme ENZY aux écoles, cliniques, hôtels et industriels — et une sélection de marques beauté canadiennes.</p>' );

	// ACC1 : deux boutons + lien WhatsApp.
	if ( ! $m->has_marker( $p, 'evo-hero-actions' ) ) {
		$b1 = $m->copy( $p, '2265050' );
		$b2 = $m->copy( $p, '60763dd' );
		if ( $b1 && $b2 ) {
			$b1['settings']['text'] = 'Demander un devis';
			$b1['settings']['link'] = Evo_Tree::link( '/devis/' );
			$b2['settings']['text'] = 'Voir la gamme ENZY';
			$b2['settings']['link'] = Evo_Tree::link( '/enzy/' );
			$row = Evo_Tree::container(
				[
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_justify_content' => 'center',
					'flex_align_items'     => 'center',
					'flex_gap'             => [ 'column' => '16', 'row' => '12', 'isLinked' => false, 'unit' => 'px', 'size' => 16 ],
					'padding'              => [ 'unit' => 'px', 'top' => '8', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ],
					'css_classes'          => 'evo-hero-actions',
				],
				[ $b1, $b2 ]
			);
			$link = Evo_Tree::widget( 'text-editor', [ 'editor' => '<p><a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">Écrire sur WhatsApp →</a></p>', '_css_classes' => 'evo-hero-wa' ] );
			if ( $m->insert( $p, '21fb8a2c', $row, 'after' ) ) {
				$m->insert( $p, $row['id'], $link, 'after' );
				$m->log( 'Hero : boutons « Demander un devis » (→ /devis/) et « Voir la gamme ENZY » + lien WhatsApp.' );
			}
		}
	}

	// Section devis.
	if ( Evo_Tree::has( $m->store->els( $p ), 'e43a488' ) ) {
		$m->remove( $p, 'e43a488' );
	}
	evo_add_class( $m, $p, '234440a', 'evo-home-devis' );
	$m->text( $p, 'c399da4', 'pH neutre' );
	$m->set( $p, 'd4cb820', [ 'title' => '5' ] );
	$m->text( $p, 'b7c3eb7', 'Usages précis' );

	// Témoignages, marques.
	$m->set( $p, 'e57d9c1', [ 'title' => 'Ils nous font confiance' ] );
	$m->text( $p, 'b2c5877', '<p>Sept marques canadiennes, sélectionnées pour leur qualité, importées directement et distribuées en exclusivité au Maroc.</p>' );
	evo_replace_in( $m, $p, 'de82f41', 'Soins capillaire certifié', 'Soins capillaires certifiés' );

	// ACC3 : badges centrés sous leur carte.
	evo_add_class( $m, $p, '14200ce', 'evo-badges-split' );
	// ACC4 : image 4 secteurs cliquable (zones ajoutées à l'affichage).
	evo_add_class( $m, $p, '2d31b92', 'evo-secteurs-map' );

	// ACC8 : images des marques cliquables vers la page Beauté.
	$brand_images = [ 'c880e70' => 'sens-eve', '26c4bf7' => 'x-pur', 'b19a61b' => 'v-kosmetik', '8700556' => 'herbal-care', 'b080866' => 'fruiteen', '7362a20' => 'salon-cosmepro', '2215391' => 'neolia' ];
	foreach ( $brand_images as $id => $slug ) {
		$m->set( $p, $id, [ 'link_to' => 'custom', 'link' => Evo_Tree::link( '/beaute-bien-etre-origine-canada/#' . $slug ) ] );
	}
	// Boutons « La gamme » (catalogues PDF) : nouvel onglet.
	foreach ( [ '15391c5', '3fe39c3', 'fc178b3', 'cd673c8', '07576d7', '11227d1', '3326b5b' ] as $id ) {
		$ref = &$m->el( $p, $id, true );
		if ( null !== $ref && ! empty( $ref['settings']['link']['url'] ) ) {
			$ref['settings']['link']['is_external'] = 'on';
			$m->store->mark( $p );
		}
		unset( $ref );
	}
	// ACC9 : carrousel mobile.
	evo_add_class( $m, $p, 'd78dd94', 'evo-brands-row evo-brands-main' );
	evo_add_class( $m, $p, '17bfdff', 'evo-brands-row evo-brands-extra' );
	evo_add_class( $m, $p, '4b6e649', 'evo-brands-section' );

	// ACC10 : bandeau avant footer, repris de la page À propos.
	if ( ! $m->has_marker( $p, 'evo-prefooter-cta' ) ) {
		$cta = $m->copy( Evo_Migration::APROPOS, 'c174e41' );
		if ( $cta ) {
			Evo_Tree::add_class( $cta, 'evo-prefooter-cta' );
			$first = true;
			Evo_Tree::walk(
				$cta['elements'],
				function ( &$w ) use ( &$first, $wa ) {
					if ( 'button' !== ( $w['widgetType'] ?? '' ) ) {
						return;
					}
					if ( $first ) {
						$w['settings']['text'] = 'Demander un devis';
						$w['settings']['link'] = Evo_Tree::link( '/devis/' );
						$first                 = false;
					} else {
						$w['settings']['text'] = 'Écrire sur WhatsApp';
						$w['settings']['link'] = Evo_Tree::link( $wa, true );
					}
				}
			);
			$m->insert( $p, evo_last_top_id( $m, $p ), $cta, 'after' );
			$m->log( 'Bandeau « Un projet, un site, un volume à chiffrer ? » ajouté avant le footer.' );
		}
	}
}

/* ======================================================================
 * 4. Solutions ENZY
 * ==================================================================== */
function evo_fiche_files() {
	return [
		'enzy-floor'   => [ '2026/08/Fiche-technique-ENZY-FLOOR.pdf', 'ENZY-FLOOR-fiche-technique.pdf' ],
		'enzy-carpet'  => [ '2026/08/Fiche-technique-ENZY-CARPET.pdf', 'ENZY-CARPET-fiche-technique.pdf' ],
		'enzy-degrese' => [ '2026/08/Fiche-technique-ENZY-DEGRESE.pdf', 'ENZY-DEGRESE-fiche-technique.pdf' ],
		'enzy-power'   => [ '2026/08/Fiche-technique-ENZY-POWER.pdf', 'ENZY-POWER-fiche-technique.pdf' ],
	];
}

/** ENZY5 : copies renommées des fiches techniques ; renvoie slug => URL. */
function evo_prepare_fiches( Evo_Migration $m ) {
	$up     = wp_get_upload_dir();
	$urls   = [];
	$copied = get_option( 'evo_corrections_copied_files', [] );
	$copied = is_array( $copied ) ? $copied : [];
	foreach ( evo_fiche_files() as $slug => $f ) {
		$src      = $up['basedir'] . '/' . $f[0];
		$dest_rel = dirname( $f[0] ) . '/' . $f[1];
		$dest     = $up['basedir'] . '/' . $dest_rel;
		if ( ! file_exists( $src ) && ! file_exists( $dest ) ) {
			$m->warn( 'Fiche technique absente : ' . $f[0] );
			continue;
		}
		if ( ! $m->dry && ! file_exists( $dest ) ) {
			copy( $src, $dest );
			$copied[] = $dest;
			$m->register_attachment( $dest, 'Fiche technique ' . Evo_Migration::PRODUCTS[ $slug ] );
		}
		$urls[ $slug ] = $up['baseurl'] . '/' . $dest_rel;
	}
	if ( ! $m->dry ) {
		update_option( 'evo_corrections_copied_files', array_values( array_unique( $copied ) ), false );
	}
	// ENZY4 : pas de fiche GLASS fournie, le document demande la fiche ENZY-DEGRESE.
	if ( isset( $urls['enzy-degrese'] ) ) {
		$urls['enzy-glass'] = $urls['enzy-degrese'];
	}
	return $urls;
}

function evo_step_enzy( Evo_Migration $m ) {
	$p = Evo_Migration::ENZY;
	if ( ! $m->has_post( $p ) ) {
		$m->warn( 'Page ENZY introuvable.' );
		return;
	}

	// Cartes : ancres (ENZY8). La carte GLASS portait l'ancre « power ».
	$cards = [ '7a98a3c' => 'enzy-floor', '158d982' => 'enzy-carpet', '71f486f' => 'enzy-degrese', '0bf251d' => 'enzy-power', '12a9fc7' => 'enzy-glass' ];
	foreach ( $cards as $id => $slug ) {
		$m->set( $p, $id, [ '_element_id' => $slug ] );
	}
	$m->log( 'Ancres #enzy-floor, #enzy-carpet, #enzy-degrese, #enzy-power, #enzy-glass en place.' );

	// Textes des cartes POWER et GLASS.
	$m->set( $p, '8031544', [ 'title' => 'ENZY-POWER' ] );
	$m->text( $p, 'c36cce5', '<p>Dégraissant industriel béton / huiles lourdes</p>' );
	evo_replace_after_label( $m, $p, '52367a7', 'Recommandé pour', 'Industrie · BTP · Ateliers' );
	$m->text( $p, '1ee0947', '<p>Nettoyant naturel pour vitres, miroirs &amp; inox — dégraisse en profondeur sans laisser de traces, séchage rapide, zéro auréole.</p>' );
	evo_replace_after_label( $m, $p, '07b8dda', 'Recommandé pour', 'Hôtellerie · Bureaux · Santé' );

	// Dilutions supprimées des 5 cartes (conteneurs des listes modes + ratios).
	foreach ( [ 'd585113', '09bd2f9', 'f80f3fb', '7de9e28', 'abe42a7' ] as $id ) {
		if ( Evo_Tree::has( $m->store->els( $p ), $id ) ) {
			$m->remove( $p, $id );
		}
	}
	$m->text( $p, '8f00cd6', '<p>Chaque formule répond à un usage précis, avec sa fiche technique téléchargeable.</p>' );
	$m->log( 'Modes et ratios de dilution retirés des 5 cartes ; phrase d\'intro ajustée (elle annonçait les ratios).' );

	// ENZY7 : tableau des dilutions.
	if ( Evo_Tree::has( $m->store->els( $p ), 'da292da' ) ) {
		$m->remove( $p, 'da292da' );
		$m->log( 'Section « Tableau des dilutions » supprimée.' );
	}

	// Valeurs.
	$ref = &$m->el( $p, '5571c4e' );
	if ( null !== $ref ) {
		$ref['settings']['description_text'] = str_replace( 'Quatre formules pour quatre usages précis', 'Cinq formules pour cinq usages précis', (string) $ref['settings']['description_text'] );
		$m->store->mark( $p );
	}
	unset( $ref );
	evo_add_class( $m, $p, '2a97fc2', 'evo-values-grid' );
	$icons = [ '11c6d43' => 'fas fa-file-alt', '69fac06' => 'fas fa-shield-alt', '8cea592' => 'fas fa-leaf', '5571c4e' => 'fas fa-bullseye', 'f6ef3d0' => 'fas fa-bolt', '68676a5' => 'fab fa-canadian-maple-leaf' ];
	foreach ( $icons as $id => $icon ) {
		$ref = &$m->el( $p, $id, true );
		if ( null !== $ref && empty( $ref['settings']['selected_icon']['value'] ) ) {
			$ref['settings']['selected_icon'] = evo_icon( $icon );
			$m->store->mark( $p );
		}
		unset( $ref );
	}

	// Formulaire.
	$m->set( $p, 'ac360e2', [ 'title' => 'Demandez votre devis — réponse sous 24 h ouvrées' ] );
	$m->set( $p, '878f348', [ '_element_id' => 'devis-enzy' ] );

	// ENZY3 · ENZY4 · ENZY5 : fiches techniques (fichiers renommés, nouvel onglet).
	$fiches  = evo_prepare_fiches( $m );
	$buttons = [ 'de50d81' => 'enzy-floor', 'e586a62' => 'enzy-carpet', '299e5d5' => 'enzy-degrese', 'aa7fb6f' => 'enzy-power', '7e20b60' => 'enzy-glass' ];
	foreach ( $buttons as $id => $slug ) {
		if ( isset( $fiches[ $slug ] ) ) {
			$m->set( $p, $id, [ 'link' => Evo_Tree::link( $fiches[ $slug ], true ) ] );
		}
	}
	$m->log( 'Fiches techniques : POWER → fiche POWER, GLASS → fiche DEGRESE (comme demandé), fichiers renommés ENZY-XXX-fiche-technique.pdf, ouverture en nouvel onglet.' );

	// Boutons « Devis gratuit » : vers le formulaire de la page.
	foreach ( [ 'cb2eaf3', 'b27c3e5', '7ed1ac9', 'ad58aef', 'c7b118d' ] as $id ) {
		$m->set( $p, $id, [ 'link' => Evo_Tree::link( '#devis-enzy' ) ] );
	}

	// ENZY6 : bouton « Certification » sur chaque carte.
	if ( ! $m->has_marker( $p, 'evo-cert-btn' ) ) {
		$rows = [ 'b4e2cd3' => [ 'de50d81', 'enzy-floor' ], '3e9ec6e' => [ 'e586a62', 'enzy-carpet' ], 'dccc56e' => [ '299e5d5', 'enzy-degrese' ], '8cafaf5' => [ 'aa7fb6f', 'enzy-power' ], 'b84902f' => [ '7e20b60', 'enzy-glass' ] ];
		foreach ( $rows as $row_id => $r ) {
			$btn = $m->copy( $p, $r[0] );
			if ( ! $btn ) {
				continue;
			}
			$btn['settings']['text']         = 'Certification';
			$btn['settings']['link']         = Evo_Tree::link( '/telechargement/certification-' . $r[1] . '/', true );
			$btn['settings']['selected_icon'] = [ 'value' => '', 'library' => '' ];
			Evo_Tree::add_class( $btn, 'evo-cert-btn evo-download-btn' );
			$m->insert( $p, $row_id, $btn, 'append' );
		}
		$m->log( 'Boutons « Certification » ajoutés (masqués tant que le PDF n\'est pas renseigné dans Outils > Corrections EVO).' );
	}

	// ENZY1 : visuel de la gamme + bouton catalogue ; ENZY2 : icônes en grille 2 × 2 sur mobile.
	evo_add_class( $m, $p, '2afdb27', 'evo-hero-with-visual evo-enzy-hero' );
	evo_add_class( $m, $p, '26b2ad9', 'evo-icons-2x2' );
	if ( ! $m->has_marker( $p, 'evo-hero-visual' ) ) {
		$img = $m->import_asset( 'enzy-gamme-5-bidons.webp', 'Gamme ENZY — 5 bidons', 'Les 5 bidons de la gamme ENZY : FLOOR, CARPET, DEGRESE, POWER et GLASS' );
		if ( $img ) {
			$image = Evo_Tree::widget( 'image', [ 'image' => $m->dry ? [ 'url' => '', 'id' => '' ] : Evo_Migration::media_array( $img ), 'image_size' => 'full', '_css_classes' => 'evo-hero-visual' ] );
			$m->insert( $p, '2afdb27', $image, 'append' );
		}
		$cat = $m->copy( $p, 'e1f2fda' );
		if ( $cat ) {
			$cat['settings']['text'] = 'Télécharger le catalogue ENZY (PDF)';
			$cat['settings']['link'] = Evo_Tree::link( '/telechargement/catalogue-enzy/', true );
			Evo_Tree::add_class( $cat, 'evo-download-btn' );
			$row = Evo_Tree::container(
				[
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_justify_content' => 'center',
					'flex_align_items'     => 'center',
					'flex_gap'             => [ 'column' => '14', 'row' => '12', 'isLinked' => false, 'unit' => 'px', 'size' => 14 ],
					'padding'          => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
					'css_classes'      => 'evo-hero-actions',
				]
			);
			if ( $m->insert( $p, 'f6ff5b0', $row, 'after' ) ) {
				$els = &$m->store->els( $p );
				Evo_Tree::move( $els, 'e1f2fda', $row['id'], 'append' );
				unset( $els );
				$m->insert( $p, $row['id'], $cat, 'append' );
			}
		}
		$m->log( 'Hero : visuel des 5 bidons + bouton « Télécharger le catalogue ENZY (PDF) » (affiché dès que le PDF est renseigné).' );
	}
	$m->set( $p, 'e1f2fda', [ 'link' => Evo_Tree::link( '#devis-enzy' ) ] );
}

/* ======================================================================
 * 5. Pages secteurs
 * ==================================================================== */
function evo_step_sectors( Evo_Migration $m ) {
	$common = [
		Evo_Migration::ECOLES => [ 'bullets' => 'f6f4791', 'why' => '1390293', 'why_text' => 'Pourquoi ENZY dans votre école' ],
		Evo_Migration::SANTE  => [ 'bullets' => '5ed189c5', 'why' => '304f8668', 'why_text' => 'Pourquoi ENZY dans votre clinique' ],
		Evo_Migration::HOTEL  => [ 'bullets' => 'b49f1f7', 'why' => '2e08ded0', 'why_text' => 'Pourquoi ENZY dans votre hôtel' ],
		Evo_Migration::INDUS  => [ 'bullets' => '6877b599', 'why' => '1a18d0f0', 'why_text' => 'Pourquoi ENZY sur votre site de production' ],
	];
	foreach ( $common as $pid => $c ) {
		if ( ! $m->has_post( $pid ) ) {
			$m->warn( "Page secteur #$pid introuvable." );
			continue;
		}
		evo_set_icon_list(
			$m,
			$pid,
			$c['bullets'],
			[ [ 'text' => 'Fabriqué au Canada' ], [ 'text' => 'pH 7–8' ], [ 'text' => 'Fiche technique fournie' ] ]
		);
		$m->set( $pid, $c['why'], [ 'title' => $c['why_text'] ] );
		$m->log( get_the_title( $pid ) . ' : puces du hero + sur-titre « ' . $c['why_text'] . ' »' );
	}
	// « Voir aussi » : « école » → « Écoles / Crèches ».
	foreach ( [ Evo_Migration::SANTE => '18f5df0', Evo_Migration::HOTEL => 'ac8a645', Evo_Migration::INDUS => 'd556926' ] as $pid => $id ) {
		$m->set( $pid, $id, [ 'title_text' => 'Écoles / Crèches' ] );
	}
	// Sur-titre témoignage Hôtellerie.
	$m->set( Evo_Migration::HOTEL, '5c3009d9', [ 'title' => 'Témoignage' ] );

	// 5.1 Santé.
	$p = Evo_Migration::SANTE;
	$m->set( $p, '7dc376b0', [ 'title' => 'Un nettoyage en profondeur, sans vapeurs agressives pour vos patients et vos équipes' ] );
	evo_replace_in( $m, $p, '1b23383c', 'Un nettoyage profond et une désinfection douce, sans vapeurs toxiques', 'Un nettoyage profond, sans vapeurs toxiques' );
	evo_set_icon_list( $m, $p, '474ae16d', [ [ 'text' => 'Nettoyage profond' ] ] );
	evo_set_icon_list( $m, $p, '1b491c25', [ [ 'text' => 'Sans vapeurs toxiques' ] ] );
	// SAN1 : ajout ENZY-GLASS.
	if ( ! $m->has_marker( $p, 'evo-added-glass' ) ) {
		$card = $m->copy( $p, '2bbd5323' );
		if ( $card ) {
			Evo_Tree::add_class( $card, 'evo-added-glass' );
			$glass = attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/2026/08/enzy-glass-bidon-5l.png' ) ?: attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/2026/08/evo-industries.png' );
			Evo_Tree::walk(
				$card['elements'],
				function ( &$w ) use ( $glass ) {
					$t = $w['widgetType'] ?? '';
					if ( 'heading' === $t ) {
						$w['settings']['title'] = 'ENZY-GLASS';
					} elseif ( 'text-editor' === $t ) {
						$w['settings']['editor'] = '<p>Vitres, inox</p>';
					} elseif ( 'image' === $t && $glass ) {
						$w['settings']['image'] = Evo_Migration::media_array( $glass );
					}
				}
			);
			$m->insert( $p, '2bbd5323', $card, 'after' );
			$m->log( 'Santé : ENZY-GLASS ajouté aux produits recommandés (« Vitres, inox »).' );
		}
	}

	// 5.2 Hôtellerie.
	$p = Evo_Migration::HOTEL;
	$m->set( $p, '75aa5cb8', [ 'title' => 'Chambres, cuisines, tapis : un nettoyage sans odeur chimique pour vos clients' ] );
	$m->text( $p, '3761fd8c', '<p>Tapis, moquettes, textiles d’ameublement</p>' );
	$m->text( $p, '5c347f68', '<p>Cuisines, salles de bain, surfaces</p>' );
	$m->set( $p, 'fc4ecdc', [ 'title' => 'ENZY-FLOOR' ] );
	$m->text( $p, '5ed2f65', '<p>Sols, lobby, couloirs</p>' );

	// 5.3 Industrie.
	$p = Evo_Migration::INDUS;
	$m->text( $p, '44b1d17d', '<p>Dégraissage courant des équipements et surfaces</p>' );
	$m->text( $p, '1c3b434b', '<p>Huiles lourdes, béton, dégraissage intensif</p>' );
	// IND1 : visuel POWER.
	$power = attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/2026/09/enzy-power-bidon-5l.png' ) ?: attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/2026/09/power.png' );
	if ( $power ) {
		$m->set( $p, '5043afe', [ 'image' => Evo_Migration::media_array( $power ) ] );
		$m->log( 'Industrie : visuel ENZY-POWER corrigé (c\'était celui de GLASS).' );
	} else {
		$m->set( $p, '5043afe', [ 'image' => [ 'url' => wp_get_upload_dir()['baseurl'] . '/2026/09/power.png', 'id' => '', 'size' => '', 'alt' => 'Bidon ENZY-POWER 5 L', 'source' => 'library' ] ] );
	}
	// IND2 : POWER en premier.
	$els = &$m->store->els( $p );
	if ( Evo_Tree::has( $els, '4e99ebe' ) && Evo_Tree::has( $els, '15670181' ) && '15670181' === ( Evo_Tree::get( $els, '203d81e0' )['elements'][0]['id'] ?? '' ) ) {
		Evo_Tree::move( $els, '4e99ebe', '15670181', 'before' );
		$m->store->mark( $p );
		$m->log( 'Industrie : ENZY-POWER affiché en premier.' );
	}
	unset( $els );
	// IND3 : arguments acheteurs industriels.
	if ( ! $m->has_marker( $p, 'evo-ind-args' ) ) {
		$single = function ( $el, $text ) {
			$item         = $el['settings']['icon_list'][0] ?? [ 'selected_icon' => [ 'value' => 'fas fa-check', 'library' => 'fa-solid' ] ];
			$item['_id']  = substr( Evo_Tree::new_id(), 0, 7 );
			$item['text'] = $text;
			$item['link'] = Evo_Tree::link( '' );
			$el['settings']['icon_list'] = [ $item ];
			Evo_Tree::add_class( $el, 'evo-ind-args' );
			return $el;
		};
		$a = $m->copy( $p, '762fd6c3' );
		$b = $m->copy( $p, 'ba244f1' );
		if ( $a && $b ) {
			$a = $single( $a, 'Non corrosif pour les machines' );
			$b = $single( $b, 'Compatible autolaveuses et nettoyeurs haute pression' );
			$m->insert( $p, '175d55eb', $a, 'after' );
			$m->insert( $p, '165b8431', $b, 'after' );
			$m->log( 'Industrie : « Non corrosif pour les machines » et « Compatible autolaveuses et nettoyeurs haute pression » ajoutés.' );
		}
	}
}

/* ======================================================================
 * 6. Beauté & Bien-être
 * ==================================================================== */
function evo_step_beaute( Evo_Migration $m ) {
	$p = Evo_Migration::BEAUTE;
	if ( ! $m->has_post( $p ) ) {
		$m->warn( 'Page Beauté introuvable.' );
		return;
	}
	// Catégories.
	$m->set( $p, 'f3694d4', [ 'title_text' => 'Hygiène bucco-dentaire' ] );
	$m->set( $p, 'b0b1f91', [ 'title_text' => 'Soins capillaires' ] );
	$m->set( $p, '4071d23', [ 'title_text' => 'Soins capillaires' ] );
	$m->set( $p, '646542a', [ 'title_text' => 'Soins corps' ] );

	// Textes des marques.
	evo_replace_in( $m, $p, 'f5c9f27', 'Certifié Peta, Vegan, et Cruelty free .', 'Certifiée PETA : vegan et cruelty-free.' );
	evo_replace_in( $m, $p, '94037ea', 'approuvé Santé Canada', 'Approuvé par Santé Canada' );
	evo_replace_in( $m, $p, 'b9726bb', 'reparation', 'réparation' );
	evo_replace_in( $m, $p, 'd82a741', 'hypoallergenique', 'hypoallergénique' );
	foreach ( [ 'f5c9f27', '94037ea', 'd82a741', 'b9726bb', '98d213d', 'c1f604c', 'db7e7d4' ] as $id ) {
		$ref = &$m->el( $p, $id, true );
		if ( null !== $ref && isset( $ref['settings']['editor'] ) ) {
			$ref['settings']['editor'] = preg_replace( '/\s+([.,])/u', '$1', $ref['settings']['editor'] );
			$m->store->mark( $p );
		}
		unset( $ref );
	}

	// Ancres par marque (G7).
	$anchors = [ '3f57a7c' => 'sens-eve', 'e68cb13' => 'x-pur', '365f4fe' => 'v-kosmetik', 'e8c7697' => 'herbal-care', '09c4ed2' => 'fruiteen', '0f8a0e5' => 'salon-cosmepro', '284ed45' => 'neolia' ];
	foreach ( $anchors as $id => $slug ) {
		$m->set( $p, $id, [ '_element_id' => $slug ] );
		evo_add_class( $m, $p, $id, 'evo-brand-card' );
	}
	$m->log( 'Ancres #sens-eve, #x-pur, #v-kosmetik, #herbal-care, #fruiteen, #salon-cosmepro, #neolia en place.' );

	// Boutons « Catalogue PDF » (BEA1) en nouvel onglet.
	foreach ( [ 'd07c9b5', 'c3c785f', '5ebaa4e', '755ae3d', '717a115', 'e19afe4', '0254ef9' ] as $id ) {
		$ref = &$m->el( $p, $id );
		if ( null !== $ref ) {
			$ref['settings']['text']                = 'Catalogue PDF';
			$ref['settings']['link']['is_external'] = 'on';
			$m->store->mark( $p );
		}
		unset( $ref );
	}
	evo_check_catalogues( $m );

	// Bloc revendeur.
	$m->set( $p, 'd37c9b9', [ 'header_size' => 'p' ] );
	evo_add_class( $m, $p, 'd37c9b9', 'evo-surtitre' );
	$m->text( $p, '0cd926e', '<p>Devenez revendeur de nos marques canadiennes au Maroc : conditions de distribution, gammes disponibles et accompagnement dédié.</p>' );
	$m->set( $p, 'a3760f7', [ 'link' => Evo_Tree::link( '#devenir-revendeur' ) ] );

	// BEA2 : visuel mosaïque + 2e bouton.
	evo_add_class( $m, $p, '772e491', 'evo-hero-with-visual evo-beaute-hero' );
	if ( ! $m->has_marker( $p, 'evo-hero-visual' ) ) {
		$img = $m->import_asset( 'mosaique-7-marques.webp', 'Mosaïque des 7 marques canadiennes', 'Les 7 marques canadiennes distribuées par EVO Industries : Sens Ēve, X-PUR, V-Kosmetik, Herbal Care, Fruiteen, Salon Cosmepro, Néolia' );
		if ( $img ) {
			$image = Evo_Tree::widget( 'image', [ 'image' => $m->dry ? [ 'url' => '', 'id' => '' ] : Evo_Migration::media_array( $img ), 'image_size' => 'full', '_css_classes' => 'evo-hero-visual' ] );
			$m->insert( $p, '772e491', $image, 'append' );
		}
		$btn = $m->copy( $p, 'b396c4d' );
		if ( $btn ) {
			$btn['settings']['text'] = 'Devenir revendeur';
			$btn['settings']['link'] = Evo_Tree::link( '#devenir-revendeur' );
			$m->insert( $p, 'b396c4d', $btn, 'after' );
			evo_add_class( $m, $p, '4294c89', 'evo-hero-actions' );
		}
		$m->log( 'Hero : mosaïque des 7 marques + bouton « Devenir revendeur ».' );
	}
	// BEA4 : grille cohérente (dernière carte centrée).
	evo_add_class( $m, $p, '1b30374', 'evo-brands-grid' );
}

/** BEA1 : vérifie que les 7 catalogues PDF existent. */
function evo_check_catalogues( Evo_Migration $m ) {
	$up      = wp_get_upload_dir();
	$missing = [];
	foreach ( [ 'Sens_Eve_Earth', 'X-PUR', 'V-Kosmetik', 'Herbal_Care', 'Fruiteen', 'Salon_Cosmepro', 'Neolia' ] as $b ) {
		if ( ! file_exists( $up['basedir'] . '/2026/09/EVO_' . $b . '_Catalogue.pdf' ) ) {
			$missing[] = $b;
		}
	}
	if ( $missing ) {
		$m->warn( 'Catalogues PDF absents : ' . implode( ', ', $missing ) );
	} else {
		$m->log( 'Les 7 catalogues PDF sont bien présents sur le serveur.' );
	}
}

/* ======================================================================
 * 7. À propos
 * ==================================================================== */
function evo_step_apropos( Evo_Migration $m ) {
	$p = Evo_Migration::APROPOS;
	if ( ! $m->has_post( $p ) ) {
		$m->warn( 'Page À propos introuvable.' );
		return;
	}
	$m->set( $p, '186a7034', [ 'header_size' => 'h1' ] );
	evo_add_class( $m, $p, '1bcee7c', 'evo-apropos-hero' );

	// Chiffres clés : même style que le premier (APR2), 8+ → 7.
	$m->set( $p, 'f4b8079', [ 'title' => '7' ] );
	$first = $m->el( $p, 'dca6a67' );
	if ( null !== $first ) {
		$style = array_filter(
			$first['settings'],
			fn( $k ) => 0 === strpos( $k, 'typography_' ) || 0 === strpos( $k, 'title_color' ) || 'header_size' === $k || '__globals__' === $k,
			ARRAY_FILTER_USE_KEY
		);
		foreach ( [ 'f4b8079', 'f99e372', 'd7f5d17' ] as $id ) {
			$ref = &$m->el( $p, $id );
			if ( null !== $ref ) {
				foreach ( array_keys( $ref['settings'] ) as $k ) {
					if ( 0 === strpos( $k, 'typography_' ) ) {
						unset( $ref['settings'][ $k ] );
					}
				}
				$ref['settings'] = array_merge( $ref['settings'], $style );
				Evo_Tree::add_class( $ref, 'evo-stat-number' );
				$m->store->mark( $p );
			}
			unset( $ref );
		}
		evo_add_class( $m, $p, 'dca6a67', 'evo-stat-number' );
		$m->log( 'Chiffres clés : même style pour les 4 (plus de H2), « 8+ » → « 7 ».' );
	}

	// Ligne GLASS.
	$ref = &$m->el( $p, '1a6d4e1' );
	if ( null !== $ref ) {
		$ref['settings']['editor'] = preg_replace( '/ENZY[\s\-]GLASS\s*:[^<]*/u', 'ENZY-GLASS — vitres et miroirs', (string) $ref['settings']['editor'] );
		$m->store->mark( $p );
	}
	unset( $ref );

	// APR1 : pastilles.
	$m->text( $p, '5d8f963', '<p class="evo-pills evo-pills--light"><span class="evo-pill">Casablanca</span> <span class="evo-pill">Deux univers</span> <span class="evo-pill">Quatre secteurs servis</span></p>' );

	// APR3 : section supprimée.
	if ( Evo_Tree::has( $m->store->els( $p ), 'b70606d' ) ) {
		$m->remove( $p, 'b70606d' );
		$m->log( 'Section « Quatre environnements, quatre contraintes » supprimée.' );
	}

	// Bandeau CTA : bouton devis vers la page devis.
	$m->set( $p, 'b4b0e82', [ 'link' => Evo_Tree::link( '/devis/' ) ] );
	evo_add_class( $m, $p, 'c174e41', 'evo-prefooter-cta' );
}

/* ======================================================================
 * 8. Contact
 * ==================================================================== */
function evo_step_contact( Evo_Migration $m ) {
	$p = Evo_Migration::CONTACT;
	if ( ! $m->has_post( $p ) ) {
		$m->warn( 'Page Contact introuvable.' );
		return;
	}
	// Titres : un seul H1.
	$m->set( $p, 'f0bbeeb', [ 'title' => 'Contact & devis — réponse sous 24 h ouvrées', 'header_size' => 'p' ] );
	$m->set( $p, '841dce8', [ 'header_size' => 'h1' ] );

	// CON1 : bandeau réduit + fil d'Ariane.
	evo_add_class( $m, $p, 'fbc4808', 'evo-contact-band' );
	$m->set( $p, 'fbc4808', [ 'min_height' => [ 'unit' => 'px', 'size' => 200, 'sizes' => [] ] ] );
	evo_set_icon_list( $m, $p, '6ee94526', [ [ 'text' => 'Accueil', 'url' => '/' ], [ 'text' => 'Contact', 'url' => '/contact/' ] ] );

	// CON3 : cartes coordonnées cliquables.
	$m->set( $p, '7abe2c5', [ 'link' => Evo_Tree::link( 'tel:' . Evo_Settings::get( 'phone_intl' ) ) ] );
	$m->set( $p, '2a38f779', [ 'link' => Evo_Tree::link( 'mailto:' . Evo_Settings::get( 'email' ) ) ] );
	$m->set( $p, '7d7b5f7', [ 'link' => Evo_Tree::link( Evo_Settings::whatsapp_url(), true ) ] );
	$m->set( $p, 'd469d78', [ 'link' => Evo_Tree::link( '' ), 'description_text' => 'Lun–Ven, 9h–18h' ] );
	$m->text( $p, '3aae9701', 'Suivez-nous' );

	// CON4 : Instagram (et LinkedIn sans paramètres de suivi).
	$ref = &$m->el( $p, '34f125fc' );
	if ( null !== $ref ) {
		foreach ( $ref['settings']['social_icon_list'] as &$item ) {
			$icon = (string) ( $item['social_icon']['value'] ?? '' );
			if ( false !== stripos( $icon, 'instagram' ) ) {
				$item['link'] = Evo_Tree::link( Evo_Settings::get( 'instagram' ), true );
			} elseif ( false !== stripos( $icon, 'linkedin' ) ) {
				$item['link'] = Evo_Tree::link( Evo_Settings::get( 'linkedin' ), true );
			}
		}
		unset( $item );
		$m->store->mark( $p );
		$m->log( 'Réseaux sociaux : Instagram → instagram.com/evoindustriesmorocco, LinkedIn nettoyé.' );
	}
	unset( $ref );

	// CON2 : colonne gauche = intro + coordonnées ; colonne droite = formulaire.
	$els = &$m->store->els( $p );
	if ( Evo_Tree::has( $els, '17b351d0' ) && Evo_Tree::has( $els, '7800d43' ) ) {
		$after = '735ed26';
		foreach ( [ '122d4c6c', '7b5a4dc1', '5a587a9', '3aae9701', '34f125fc' ] as $id ) {
			if ( Evo_Tree::move( $els, $id, $after, 'after' ) ) {
				$after = $id;
			}
		}
		Evo_Tree::remove( $els, '17b351d0' );
		$m->store->mark( $p );
		$m->log( 'Mise en page : coordonnées déplacées sous le texte d\'intro, formulaire à droite visible sans défiler.' );
	}
	unset( $els );
	evo_add_class( $m, $p, '8518a22', 'evo-contact-main' );
	evo_add_class( $m, $p, '7800d43', 'evo-contact-left' );
	evo_add_class( $m, $p, '7667cb0', 'evo-contact-form' );
}
