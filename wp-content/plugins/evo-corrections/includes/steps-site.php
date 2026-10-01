<?php
/**
 * Structure du site : pages légales (LEG1), slugs /secteurs/ (SEO6), menu (G3), favicon (G5),
 * header mobile (G18), footer (G6 → G10).
 */

defined( 'ABSPATH' ) || exit;

/** Nouveaux chemins des pages secteurs (SEO6). */
function evo_sector_slugs() {
	return [
		Evo_Migration::ECOLES => [ 'ecoles', 'ecoles-creches' ],
		Evo_Migration::SANTE  => [ 'secteur-cliniques', 'sante' ],
		Evo_Migration::HOTEL  => [ 'secteur-hotellerie', 'hotellerie-restauration' ],
		Evo_Migration::INDUS  => [ 'secteur-industrie', 'industrie' ],
	];
}

/* ======================================================================
 * LEG1 — Pages légales (brouillons : textes à valider / remplacer par ceux de Salma)
 * ==================================================================== */
function evo_legal_page_els( $title, $html ) {
	$pad = [ 'unit' => 'px', 'top' => '64', 'right' => '20', 'bottom' => '80', 'left' => '20', 'isLinked' => false ];
	return [
		Evo_Tree::container(
			[ 'content_width' => 'boxed', 'boxed_width' => [ 'unit' => 'px', 'size' => 860, 'sizes' => [] ], 'padding' => $pad, 'css_classes' => 'evo-legal', 'flex_direction' => 'column' ],
			[
				Evo_Tree::widget( 'heading', [ 'title' => $title, 'header_size' => 'h1' ] ),
				Evo_Tree::widget( 'text-editor', [ 'editor' => $html ] ),
			],
			false
		),
	];
}

function evo_step_legal( Evo_Migration $m ) {
	$email = esc_html( Evo_Settings::get( 'email' ) );
	$phone = esc_html( Evo_Settings::get( 'phone_display' ) );
	$pages = [
		'mentions-legales'            => [
			'Mentions légales',
			'<h2>Éditeur du site</h2>'
			. '<p><strong>EVO Industries Morocco</strong> [à compléter : forme juridique et capital]<br>Siège social : [à compléter : adresse], Casablanca, Maroc<br>[evo_company_ids]<br>Téléphone : ' . $phone . ' — Email : <a href="mailto:' . $email . '">' . $email . '</a></p>'
			. '<p>EVO Industries Morocco est la filiale marocaine du groupe canadien <a href="https://evoroyal.ca/" target="_blank" rel="noopener">EvoRoyal Global Industries Inc.</a></p>'
			. '<h2>Directeur de la publication</h2><p>[à compléter]</p>'
			. '<h2>Hébergement</h2><p>[à compléter : nom, adresse et téléphone de l\'hébergeur]</p>'
			. '<h2>Propriété intellectuelle</h2><p>L\'ensemble des contenus de ce site (textes, visuels, logos, fiches techniques) est protégé. Toute reproduction sans autorisation écrite d\'EVO Industries Morocco est interdite. Les marques citées appartiennent à leurs titulaires respectifs.</p>'
			. '<h2>Données personnelles</h2><p>Le traitement des données collectées via les formulaires est décrit dans notre <a href="/politique-de-confidentialite/">politique de confidentialité</a>.</p>',
		],
		'politique-de-confidentialite' => [
			'Politique de confidentialité',
			'<p>EVO Industries Morocco accorde une grande importance à la protection de vos données personnelles, conformément à la loi n° 09-08 relative à la protection des personnes physiques à l\'égard du traitement des données à caractère personnel.</p>'
			. '<h2>Responsable du traitement</h2><p>EVO Industries Morocco, Casablanca, Maroc — <a href="mailto:' . $email . '">' . $email . '</a><br>[evo_company_ids]</p>'
			. '<h2>Données collectées</h2><p>Via les formulaires de devis, de contact et de demande revendeur : nom et prénom, société ou point de vente, email, téléphone, ville, secteur d\'activité, produits ou marques d\'intérêt, message. Ces informations sont fournies volontairement par vous.</p>'
			. '<h2>Finalités</h2><p>Vos données sont utilisées uniquement pour traiter votre demande : établissement d\'un devis, envoi de documentation technique, réponse à une demande de distribution et suivi commercial de cette demande. Elles ne sont ni vendues ni cédées à des tiers.</p>'
			. '<h2>Destinataires</h2><p>L\'équipe commerciale d\'EVO Industries Morocco et, le cas échéant, ses prestataires techniques (hébergement, envoi d\'emails) dans la stricte limite de leur mission.</p>'
			. '<h2>Durée de conservation</h2><p>[à compléter — ex. : 3 ans à compter du dernier contact.]</p>'
			. '<h2>Vos droits</h2><p>Vous disposez d\'un droit d\'accès, de rectification et d\'opposition, pour des motifs légitimes, au traitement de vos données. Pour l\'exercer, écrivez à <a href="mailto:' . $email . '">' . $email . '</a>. Vous pouvez également saisir la Commission nationale de contrôle de la protection des données à caractère personnel (<a href="https://www.cndp.ma" target="_blank" rel="noopener">CNDP</a>).</p>'
			. '<h2>Mesure d\'audience</h2><p>[à compléter si Google Analytics est activé : outil utilisé, données mesurées, durée de conservation des cookies.]</p>',
		],
	];
	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$m->log( "/$slug/ existe déjà (#{$existing->ID}, statut : {$existing->post_status}) : non modifiée.", 'info' );
			$m->remember_id( 'page_' . $slug, $existing->ID );
			continue;
		}
		$id = $m->create_elementor_page( $page[0], $slug, evo_legal_page_els( $page[0], $page[1] ), 'draft' );
		$m->remember_id( 'page_' . $slug, $id );
	}
	$m->log( 'Pages créées en BROUILLON : remplacer les passages [à compléter] par les textes de Salma, puis publier. Les liens du footer et du formulaire apparaissent dès la publication (G9).', 'warn' );
}

/* ======================================================================
 * SEO6 — /secteurs/… + redirections 301
 * ==================================================================== */
function evo_step_seo6( Evo_Migration $m ) {
	$hub = Evo_Migration::id( 'page_secteurs' );
	if ( ! $hub || ! get_post( $hub ) ) {
		$existing = get_page_by_path( 'secteurs', OBJECT, 'page' );
		if ( $existing ) {
			$hub = (int) $existing->ID;
		} else {
			$els  = [];
			$hero = $m->copy( Evo_Migration::HOME, '6ee1d1ea' );
			if ( $hero ) {
				$h = 0;
				Evo_Tree::walk(
					$hero['elements'],
					function ( &$w ) use ( &$h ) {
						$t = $w['widgetType'] ?? '';
						if ( 'heading' === $t ) {
							if ( 'p' === ( $w['settings']['header_size'] ?? '' ) ) {
								$w['settings']['title'] = 'Secteurs';
							} else {
								$w['settings']['title']       = 'Une solution ENZY pour chaque environnement';
								$w['settings']['header_size'] = 'h1';
							}
						} elseif ( 'text-editor' === $t && ! Evo_Tree::has_class( $w, 'evo-hero-wa' ) ) {
							$w['settings']['editor'] = '<p>Écoles et crèches, établissements de santé, hôtels et restaurants, sites industriels : chaque secteur a ses contraintes et sa page dédiée.</p>';
						}
					}
				);
				$els[] = $hero;
			}
			$els[] = evo_shortcode_container( '[evo_bloc_secteurs]', 'evo-global-secteurs' );
			$cta   = $m->copy( Evo_Migration::HOME, evo_find_marker_id( $m, Evo_Migration::HOME, 'evo-prefooter-cta' ) );
			if ( $cta ) {
				$els[] = $cta;
			}
			$hub = $m->create_elementor_page( 'Secteurs', 'secteurs', $els );
		}
		$m->remember_id( 'page_secteurs', $hub );
	}

	$map = [];
	foreach ( evo_sector_slugs() as $pid => $s ) {
		$p = get_post( $pid );
		if ( ! $p ) {
			$m->warn( "Page #$pid introuvable." );
			continue;
		}
		$map[ '/' . $s[0] . '/' ] = '/secteurs/' . $s[1] . '/';
		if ( $p->post_name === $s[1] && (int) $p->post_parent === (int) $hub ) {
			continue;
		}
		if ( ! $m->dry && $hub > 0 ) {
			Evo_Store::backup_post_fields( $pid );
			wp_update_post( [ 'ID' => $pid, 'post_name' => $s[1], 'post_parent' => $hub ] );
		}
		$m->log( '/' . $s[0] . '/ → /secteurs/' . $s[1] . '/ (redirection 301)' );
	}
	if ( ! $m->dry ) {
		$redirects = get_option( 'evo_corrections_redirects', [] );
		update_option( 'evo_corrections_redirects', array_merge( is_array( $redirects ) ? $redirects : [], $map ), false );
	}

	// Liens internes mis à jour partout (pages, header, footer, modèle global).
	$home_url = untrailingslashit( home_url() );
	$count    = 0;
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( $map, $home_url, &$changed ) {
				if ( empty( $el['settings'] ) ) {
					return;
				}
				Evo_Tree::map_links(
					$el['settings'],
					function ( &$link ) use ( $map, $home_url, &$changed ) {
						foreach ( $map as $old => $new ) {
							$re = '#^(' . preg_quote( $home_url, '#' ) . ')?' . preg_quote( untrailingslashit( $old ), '#' ) . '/?(\#.*)?$#';
							if ( preg_match( $re, $link['url'], $mm ) ) {
								$link['url'] = ( $mm[1] ?? '' ) . $new . ( $mm[2] ?? '' );
								$changed     = true;
							}
						}
					}
				);
				if ( isset( $el['settings']['editor'] ) && is_string( $el['settings']['editor'] ) ) {
					foreach ( $map as $old => $new ) {
						$el['settings']['editor'] = preg_replace( '#href="(' . preg_quote( $home_url, '#' ) . ')?' . preg_quote( $old, '#' ) . '#', 'href="$1' . $new, $el['settings']['editor'], -1, $n );
						$changed                  = $changed || $n > 0;
					}
				}
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
			$count++;
		}
	}
	// Liens personnalisés des menus.
	foreach ( get_posts( [ 'post_type' => 'nav_menu_item', 'numberposts' => -1, 'post_status' => 'any' ] ) as $item ) {
		$url = (string) get_post_meta( $item->ID, '_menu_item_url', true );
		foreach ( $map as $old => $new ) {
			$re = '#^(' . preg_quote( $home_url, '#' ) . ')?' . preg_quote( untrailingslashit( $old ), '#' ) . '/?(\#.*)?$#';
			if ( $url && preg_match( $re, $url, $mm ) && ! $m->dry ) {
				Evo_Store::backup_meta( $item->ID, '_menu_item_url' );
				update_post_meta( $item->ID, '_menu_item_url', ( $mm[1] ?? '' ) . $new . ( $mm[2] ?? '' ) );
			}
		}
	}
	$m->log( $count . ' contenu(s) avec liens internes mis à jour. À faire ensuite : soumettre le nouveau sitemap dans Search Console.', 'info' );
}

function evo_find_marker_id( Evo_Migration $m, $pid, $class ) {
	$found = Evo_Tree::find_all( $m->store->els( $pid ), fn( $e ) => Evo_Tree::has_class( $e, $class ) );
	return $found ? $found[0]['id'] : '';
}

/* ======================================================================
 * G3 — Menu principal + bouton « Demander un devis »
 * ==================================================================== */
function evo_menu_args( $item, array $overrides = [] ) {
	return array_merge(
		[
			'menu-item-object-id'   => $item->object_id,
			'menu-item-object'      => $item->object,
			'menu-item-parent-id'   => $item->menu_item_parent,
			'menu-item-position'    => $item->menu_order,
			'menu-item-type'        => $item->type,
			'menu-item-title'       => $item->title,
			'menu-item-url'         => $item->url,
			'menu-item-description' => $item->description,
			'menu-item-attr-title'  => $item->attr_title,
			'menu-item-target'      => $item->target,
			'menu-item-classes'     => implode( ' ', array_filter( (array) $item->classes ) ),
			'menu-item-xfn'         => $item->xfn,
			'menu-item-status'      => 'publish',
		],
		$overrides
	);
}

function evo_step_menu( Evo_Migration $m ) {
	// Menu qui contient « Solutions ENZY ».
	$menu  = null;
	$items = [];
	foreach ( wp_get_nav_menus() as $candidate ) {
		$list = wp_get_nav_menu_items( $candidate->term_id, [ 'post_status' => 'any' ] );
		foreach ( (array) $list as $it ) {
			if ( (int) $it->object_id === Evo_Migration::ENZY && 'post_type' === $it->type ) {
				$menu  = $candidate;
				$items = $list;
				break 2;
			}
		}
	}
	if ( ! $menu ) {
		$m->warn( 'Menu principal introuvable (aucun menu ne contient la page ENZY).' );
		return;
	}
	if ( ! $m->dry && ! get_option( Evo_Store::OPT_MENU_BACKUP ) ) {
		$backup = [];
		foreach ( $items as $it ) {
			$backup[] = [
				'ID'        => $it->ID,
				'title'     => get_post_field( 'post_title', $it->ID ),
				'order'     => $it->menu_order,
				'parent'    => $it->menu_item_parent,
				'url'       => get_post_meta( $it->ID, '_menu_item_url', true ),
				'type'      => $it->type,
				'object'    => $it->object,
				'object_id' => $it->object_id,
			];
		}
		update_option( Evo_Store::OPT_MENU_BACKUP, $backup, false );
	}
	if ( $m->dry ) {
		$m->log( 'Simulation : menu « ' . $menu->name . ' » réorganisé (Solutions ENZY ▾ · Secteurs ▾ · Beauté & Bien-être · À propos · Contact).', 'info' );
		return;
	}

	$by_object = [];
	$enzy      = null;
	foreach ( $items as $it ) {
		if ( 'post_type' === $it->type ) {
			$by_object[ (int) $it->object_id ] = $it;
		}
		if ( (int) $it->object_id === Evo_Migration::ENZY && ! $enzy ) {
			$enzy = $it;
		}
	}
	$find_title = function ( $re ) use ( $items ) {
		foreach ( $items as $it ) {
			if ( preg_match( $re, html_entity_decode( $it->title, ENT_QUOTES, 'UTF-8' ) ) ) {
				return $it;
			}
		}
		return null;
	};

	$pos = 1;
	wp_update_nav_menu_item( $menu->term_id, $enzy->ID, evo_menu_args( $enzy, [ 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++, 'menu-item-title' => 'Solutions ENZY' ] ) );

	// Produits sous « Solutions ENZY ».
	foreach ( Evo_Migration::PRODUCTS as $slug => $label ) {
		$existing = null;
		foreach ( $items as $it ) {
			if ( 'custom' === $it->type && false !== strpos( (string) $it->url, '#' . $slug ) ) {
				$existing = $it;
			}
		}
		$args = [ 'menu-item-title' => $label, 'menu-item-url' => home_url( '/enzy/#' . $slug ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $enzy->ID, 'menu-item-position' => $pos++, 'menu-item-status' => 'publish' ];
		if ( $existing ) {
			wp_update_nav_menu_item( $menu->term_id, $existing->ID, evo_menu_args( $existing, $args ) );
		} else {
			$new = wp_update_nav_menu_item( $menu->term_id, 0, $args );
			if ( $new && ! is_wp_error( $new ) ) {
				Evo_Store::track_created( $new );
			}
		}
	}

	// « Secteurs » et ses 4 pages.
	$hub      = Evo_Migration::id( 'page_secteurs' );
	$secteurs = $hub && isset( $by_object[ $hub ] ) ? $by_object[ $hub ] : $find_title( '/^Secteurs$/u' );
	$args     = $hub && get_post( $hub )
		? [ 'menu-item-title' => 'Secteurs', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $hub, 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++, 'menu-item-status' => 'publish' ]
		: [ 'menu-item-title' => 'Secteurs', 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/#secteurs' ), 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++, 'menu-item-status' => 'publish' ];
	if ( $secteurs ) {
		$secteurs_id = wp_update_nav_menu_item( $menu->term_id, $secteurs->ID, evo_menu_args( $secteurs, $args ) );
	} else {
		$secteurs_id = wp_update_nav_menu_item( $menu->term_id, 0, $args );
		if ( $secteurs_id && ! is_wp_error( $secteurs_id ) ) {
			Evo_Store::track_created( $secteurs_id );
		}
	}
	$titles = [ Evo_Migration::ECOLES => 'Écoles / Crèches', Evo_Migration::SANTE => 'Santé', Evo_Migration::HOTEL => 'Hôtellerie & Restauration', Evo_Migration::INDUS => 'Industrie' ];
	foreach ( $titles as $pid => $title ) {
		$args = [ 'menu-item-title' => $title, 'menu-item-parent-id' => $secteurs_id, 'menu-item-position' => $pos++ ];
		if ( isset( $by_object[ $pid ] ) ) {
			wp_update_nav_menu_item( $menu->term_id, $by_object[ $pid ]->ID, evo_menu_args( $by_object[ $pid ], $args ) );
		} else {
			$new = wp_update_nav_menu_item( $menu->term_id, 0, array_merge( $args, [ 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pid, 'menu-item-status' => 'publish' ] ) );
			if ( $new && ! is_wp_error( $new ) ) {
				Evo_Store::track_created( $new );
			}
		}
	}

	// Beauté, À propos, Contact (le lien Contact était vide).
	$beaute = $find_title( '/^Beaut/u' );
	if ( $beaute ) {
		wp_update_nav_menu_item( $menu->term_id, $beaute->ID, evo_menu_args( $beaute, [ 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++, 'menu-item-title' => 'Beauté & Bien-être' ] ) );
	}
	$apropos = $by_object[ Evo_Migration::APROPOS ] ?? $find_title( '/propos/u' );
	if ( $apropos ) {
		wp_update_nav_menu_item( $menu->term_id, $apropos->ID, evo_menu_args( $apropos, [ 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++, 'menu-item-title' => 'À propos' ] ) );
	}
	$contact      = $by_object[ Evo_Migration::CONTACT ] ?? $find_title( '/^Contact$/u' );
	$contact_args = [ 'menu-item-title' => 'Contact', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => Evo_Migration::CONTACT, 'menu-item-url' => '', 'menu-item-parent-id' => 0, 'menu-item-position' => $pos++ ];
	if ( $contact ) {
		wp_update_nav_menu_item( $menu->term_id, $contact->ID, evo_menu_args( $contact, $contact_args ) );
	} else {
		$new = wp_update_nav_menu_item( $menu->term_id, 0, array_merge( $contact_args, [ 'menu-item-status' => 'publish' ] ) );
		if ( $new && ! is_wp_error( $new ) ) {
			Evo_Store::track_created( $new );
		}
	}
	$m->log( 'Menu « ' . $menu->name . ' » : Solutions ENZY ▾ (5 produits) · Secteurs ▾ (4 pages) · Beauté & Bien-être · À propos · Contact (lien réparé).' );

	// Bouton du header → page devis.
	foreach ( [ 'c259b87' ] as $id ) {
		$m->set( Evo_Migration::HEADER, $id, [ 'link' => Evo_Tree::link( '/devis/' ) ] );
	}
	$m->log( 'Bouton « Demander un devis » du header → /devis/.' );
}

/* ======================================================================
 * G5 — Favicon
 * ==================================================================== */
function evo_step_favicon( Evo_Migration $m ) {
	if ( get_option( 'site_icon' ) ) {
		$m->log( 'Un favicon est déjà défini : conservé.', 'info' );
		return;
	}
	$id = $m->import_asset( 'favicon-evo-512.png', 'Favicon EVO Industries', 'EVO Industries' );
	if ( $id > 0 ) {
		Evo_Store::set_option( 'site_icon', $id, $m->dry );
		$m->log( 'Favicon 512 × 512 (globe du logo) défini dans Apparence > Personnaliser > Identité du site.' );
	}
}

/* ======================================================================
 * G18 — Bouton devis dans le header mobile
 * ==================================================================== */
function evo_step_mobile_header( Evo_Migration $m ) {
	$h = Evo_Migration::HEADER;
	if ( ! $m->has_post( $h ) ) {
		$m->warn( 'Header ElementsKit (#310) introuvable.' );
		return;
	}
	if ( $m->has_marker( $h, 'evo-mobile-devis' ) ) {
		return;
	}
	$btn = $m->copy( $h, 'c259b87' );
	if ( ! $btn ) {
		return;
	}
	foreach ( array_keys( $btn['settings'] ) as $k ) {
		if ( 0 === strpos( $k, 'hide_' ) ) {
			unset( $btn['settings'][ $k ] );
		}
	}
	$btn['settings']['text'] = 'Devis';
	$btn['settings']['link'] = Evo_Tree::link( '/devis/' );
	Evo_Tree::add_class( $btn, 'evo-mobile-devis' );
	if ( $m->insert( $h, 'f17ed9e', $btn, 'after' ) ) {
		evo_add_class( $m, $h, '1d217c2', 'evo-mobile-header' );
		$m->log( 'Header mobile : bouton « Devis » visible entre le logo et le menu burger.' );
	}
}

/* ======================================================================
 * G6 → G10 — Footer
 * ==================================================================== */
function evo_step_footer( Evo_Migration $m ) {
	$f = Evo_Migration::FOOTER;
	if ( ! $m->has_post( $f ) ) {
		$m->warn( 'Footer ElementsKit (#331) introuvable.' );
		return;
	}
	// G6 · G7 : liens produits et marques vers les ancres.
	$targets = [
		'0c932db' => array_combine( array_values( Evo_Migration::PRODUCTS ), array_map( fn( $s ) => '/enzy/#' . $s, array_keys( Evo_Migration::PRODUCTS ) ) ),
		'7e6054c' => array_combine( array_values( Evo_Migration::BRANDS ), array_map( fn( $s ) => '/beaute-bien-etre-origine-canada/#' . $s, array_keys( Evo_Migration::BRANDS ) ) ),
		'78be289' => [ 'Devis' => '/devis/', 'Contact' => '/contact/', 'Devenir revendeur' => '/beaute-bien-etre-origine-canada/#devenir-revendeur', 'À propos' => '/a-propos/' ],
	];
	foreach ( $targets as $id => $by_text ) {
		$ref = &$m->el( $f, $id );
		if ( null === $ref ) {
			continue;
		}
		foreach ( $ref['settings']['icon_list'] as &$item ) {
			$text = evo_normalize_names( trim( wp_strip_all_tags( (string) $item['text'] ) ) );
			if ( isset( $by_text[ $text ] ) ) {
				$item['text'] = $text;
				$item['link'] = Evo_Tree::link( $by_text[ $text ] );
			}
		}
		unset( $item, $ref );
		$m->store->mark( $f );
	}
	$m->log( 'Footer : 5 liens produits → /enzy/#…, 7 liens marques → /beaute-bien-etre-origine-canada/#…, « Devis » → /devis/, « Devenir revendeur » → formulaire revendeur.' );

	// G8 : colonne Coordonnées (maquette de Salma).
	evo_set_icon_list(
		$m,
		$f,
		'80141c3',
		[
			[ 'text' => Evo_Settings::get( 'address' ), 'icon' => evo_icon( 'fas fa-map-marker-alt' ) ],
			[ 'text' => Evo_Settings::get( 'phone_display' ), 'url' => 'tel:' . Evo_Settings::get( 'phone_intl' ), 'icon' => evo_icon( 'fas fa-phone-alt' ) ],
			[ 'text' => Evo_Settings::get( 'email' ), 'url' => 'mailto:' . Evo_Settings::get( 'email' ), 'icon' => evo_icon( 'far fa-envelope' ) ],
			[ 'text' => 'WhatsApp Business', 'url' => Evo_Settings::whatsapp_url(), 'external' => true, 'icon' => evo_icon( 'fab fa-whatsapp' ) ],
			[ 'text' => Evo_Settings::get( 'hours' ), 'icon' => evo_icon( 'far fa-clock' ) ],
			[ 'text' => 'LinkedIn', 'url' => Evo_Settings::get( 'linkedin' ), 'external' => true, 'icon' => evo_icon( 'fab fa-linkedin-in' ) ],
			[ 'text' => 'Instagram', 'url' => Evo_Settings::get( 'instagram' ), 'external' => true, 'icon' => evo_icon( 'fab fa-instagram' ) ],
		]
	);
	evo_add_class( $m, $f, 'be2fd2f', 'evo-footer-contact' );
	$m->log( 'Footer : colonne Coordonnées (adresse, téléphone cliquable, email, WhatsApp Business, horaires, LinkedIn, Instagram).' );

	// G9 : ligne légale.
	$m->text( $f, 'babf702', '<p>© 2026 EVO Industries Morocco[evo_footer_legal]</p>' );
	$m->log( 'Footer : ligne légale (Mentions légales · Politique de confidentialité · RC / ICE) — chaque élément s\'affiche dès qu\'il existe.' );

	// G10 : lien EvoRoyal.
	$ref = &$m->el( $f, '05c0c66' );
	if ( null !== $ref && false === strpos( (string) $ref['settings']['editor'], 'evoroyal.ca' ) ) {
		$ref['settings']['editor'] = str_replace( 'EvoRoyal Global Industries Inc.', '<a href="https://evoroyal.ca/" target="_blank" rel="noopener">EvoRoyal Global Industries Inc.</a>', (string) $ref['settings']['editor'] );
		$m->store->mark( $f );
		$m->log( 'Footer : « EvoRoyal Global Industries Inc. » cliquable vers evoroyal.ca.' );
	}
	unset( $ref );
}
