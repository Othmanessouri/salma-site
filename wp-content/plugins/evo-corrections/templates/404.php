<?php
/**
 * SUI2 — Page 404 : message, recherche, liens vers ENZY, secteurs, contact.
 */

defined( 'ABSPATH' ) || exit;

$evo_secteurs = Evo_Migration::id( 'page_secteurs' );
$evo_links    = [
	'Solutions ENZY'       => home_url( '/enzy/' ),
	'Secteurs'             => $evo_secteurs ? get_permalink( $evo_secteurs ) : home_url( '/#secteurs' ),
	'Beauté & Bien-être'   => home_url( '/beaute-bien-etre-origine-canada/' ),
	'Demander un devis'    => home_url( '/devis/' ),
	'Contact'              => home_url( '/contact/' ),
];

get_header();
?>
<main id="content" class="evo-404">
	<p class="evo-404__code">Erreur 404</p>
	<h1>Cette page n'existe pas ou a été déplacée</h1>
	<p>Le lien que vous avez suivi est peut-être ancien. Faites une recherche ou choisissez une rubrique ci-dessous.</p>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="evo-404-s">Rechercher</label>
		<input type="search" id="evo-404-s" name="s" placeholder="Rechercher un produit, un secteur…">
		<button type="submit">Rechercher</button>
	</form>
	<ul class="evo-404__links">
		<?php foreach ( $evo_links as $evo_label => $evo_url ) : ?>
			<li><a href="<?php echo esc_url( $evo_url ); ?>"><?php echo esc_html( $evo_label ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</main>
<?php
get_footer();
