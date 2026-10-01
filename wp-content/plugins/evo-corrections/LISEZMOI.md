# EVO Corrections — mode d'emploi

Plugin qui applique la liste de corrections de Salma (30/09/2026) sur evoindustries.ma
**sans refaire le site** : il modifie les pages Elementor existantes (mêmes sections, mêmes styles),
avec sauvegarde automatique et restauration en un clic.

## Installation

1. Faire une sauvegarde complète (Backuply : base + fichiers).
2. Envoyer le dossier `wp-content/plugins/evo-corrections/` sur le serveur (FTP), ou téléverser
   `evo-corrections.zip` dans Extensions > Ajouter > Téléverser.
3. Activer « EVO Corrections ».
4. **Outils > Corrections EVO** :
   - renseigner/valider les coordonnées (section 2) ;
   - cliquer **Simuler** : le journal liste ce qui sera fait, sans rien modifier ;
   - cliquer **Appliquer la sélection** : les corrections s'exécutent une par une.
5. Vider le cache du navigateur et contrôler le site (375 px, 768 px, 1280 px).

En cas de problème : **Tout restaurer** remet les contenus, le menu et les options dans leur état
d'avant, et supprime les pages, formulaires et médias créés par le plugin.
Chaque correction peut aussi être décochée et appliquée séparément.

En SSH : `wp evo-corrections apply --dry-run`, `wp evo-corrections apply`, `wp evo-corrections restore`,
`wp evo-corrections status`.

## Ce qui est fait automatiquement

| Doc | Correction |
|---|---|
| G1 | 17 images servies depuis lf-signature-immobilier → médiathèque evoindustries.ma (0 occurrence restante) |
| G2 | /blog/ + 4 articles Lorem Ipsum en brouillon (sortis du sitemap) |
| 2.1 | ENZY-XXX, Sens Ēve, X-PUR, V-Kosmetik, Herbal Care, Salon Cosmepro, À propos : partout |
| G3 | Menu : Solutions ENZY ▾ (5 produits) · Secteurs ▾ (4 pages) · Beauté · À propos · Contact (lien réparé) ; bouton devis → /devis/ |
| G4 · G18 · G19 | Header collant qui se réduit, bouton « Devis » en mobile, cibles ≥ 44 px, ancres décalées sous le header |
| G5 | Favicon 512 × 512 (globe du logo) |
| G6 → G10 | Footer : liens produits/marques vers les ancres, colonne Coordonnées (maquette de Salma), ligne légale, lien EvoRoyal |
| Header | Bandeau du haut de la maquette : Filiale EvoRoyal · Devis sous 24 h · téléphone · WhatsApp |
| G11 · G12 | 2 polices (Poppins titres, DM Sans texte) hébergées localement, font-display swap, préchargement ; Site Settings (H1–H6, texte, boutons, palette) |
| G13 → G16 | Couleur fixe par produit, boutons primaire/secondaire, sur-titres harmonisés, animations d'entrée retirées |
| ACC1 → ACC10 | Nouveau H1 + sur-titre, 2 boutons + WhatsApp, badges centrés, image 4 secteurs cliquable, bloc secteurs en modèle global (cartes cliquables + pastilles), devis compact, marques cliquables, carrousel mobile, bandeau avant footer |
| ENZY1 → 10 | Visuel 5 bidons, dilutions et tableau supprimés, fiches techniques corrigées/renommées (nouvel onglet), ancres, boutons Certification, valeurs en grille |
| Secteurs | Puces, sur-titres, textes Santé / Hôtellerie / Industrie, GLASS en Santé, POWER en premier + bon visuel, arguments industriels |
| Beauté | Catégories et textes, ancres des 7 marques, « Catalogue PDF », mosaïque + « Devenir revendeur », formulaire revendeur dédié |
| À propos · Contact | H1, chiffres, pastilles, section supprimée ; bandeau réduit, coordonnées cliquables, Instagram, mise en page |
| FOR1 → FOR5 | Page /devis/, page /merci/, libellés, listes lisibles dans les emails, multi-sélection des 5 produits, secteur pré-rempli, surface « Je ne sais pas », ville, zone de texte, consentement 09-08, anti-spam, notification + accusé de réception |
| SEO1 → SEO6 | Titles/meta du document, Open Graph, Schema.org, textes alternatifs, sitemap nettoyé, /secteurs/… + 301 |
| PER | Tailles d'images, fichiers renommés, Font Awesome 4 retiré, MetForm chargé seulement sur les pages à formulaire, Click-to-Chat différé |
| SECU1 · SECU2 | Énumération des utilisateurs bloquée, pages auteur désactivées, XML-RPC coupé |
| SUI1 · SUI2 | Événements GA4 (dès que l'ID est saisi), page 404 utile |

## « À fournir : Salma » (Outils > Corrections EVO, section 2)

Tant que le champ est vide, l'élément reste masqué : catalogue ENZY (PDF), 5 certifications,
RC / ICE, ID Google Analytics. Les pages **Mentions légales** et **Politique de confidentialité**
sont créées en brouillon : remplacer les passages [à compléter] puis publier ; les liens du footer
et du formulaire apparaissent alors automatiquement.

## Choix faits (à valider)

- **ENZY4** : pas de fiche GLASS fournie → le bouton GLASS ouvre la fiche ENZY-DEGRESE, comme écrit.
- **ENZY** : la phrase d'intro annonçait les ratios de dilution supprimés → « Chaque formule répond à un usage précis, avec sa fiche technique téléchargeable. »
- **FOR3** : « Surface à traiter » devient une liste (moins de 100 m² … plus de 2 000 m², Je ne sais pas).
- **Accueil / Contact** : le formulaire Fluent Forms est remplacé par un formulaire MetForm court (même logique que les autres : consentement, /merci/, notifications).
- **G12** : les couleurs globales existantes sont conservées (le design en dépend) ; la palette de la marque est ajoutée.
- **ENZY1 / BEA2** : le visuel est placé sous les boutons pour garder les héros centrés.
- Carte ENZY-DEGRESE : son texte décrit le dégraissage béton (celui de POWER) — non demandé dans la liste, laissé tel quel.
