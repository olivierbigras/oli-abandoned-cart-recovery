<?php
/**
 * Test de la migration des modèles (1.2.0) : seule une valeur STRICTEMENT identique à un ancien texte par
 * défaut peut être remplacée ; tout le reste est conservé à l'octet près ; une 2e exécution ne change rien.
 *
 * Site de test seulement (l'option oli_acr_templates est remplacée puis restaurée) :
 *   wp eval-file tests/test-template-migration.php
 * Code de sortie 0 si tout passe, 1 sinon.
 *
 * @package OliAbandonedCartRecovery
 */

if ( ! class_exists( 'OLI_ACR_Templates' ) ) {
	echo "Extension inactive.\n";
	exit( 1 );
}

// wp eval-file exécute ce fichier dans une fonction : les variables partagées passent par $GLOBALS.
$oli_t_saved              = get_option( 'oli_acr_templates' );
$oli_t_fb                 = OLI_ACR_Lang::fallback_language();
$oli_t_en                 = oli_acr_in_locale( 'en_US', array( 'OLI_ACR_Templates', 'default_templates_raw' ) );
$GLOBALS['oli_t_fb']      = $oli_t_fb;
$GLOBALS['oli_t_fail']    = 0;
$GLOBALS['oli_t_count']   = 0;

/**
 * Vérifie une condition.
 *
 * @param bool   $ok    Condition.
 * @param string $label Libellé.
 * @return void
 */
function oli_t_check( $ok, $label ) {
	++$GLOBALS['oli_t_count'];
	if ( ! $ok ) {
		++$GLOBALS['oli_t_fail'];
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
}

/**
 * Modèle comme l'écrivait la 1.1.x : textes de la langue de repli + clé plate.
 *
 * @param array<mixed> $def   Modèle par défaut (en_US).
 * @param array<mixed> $over  Champs modifiés.
 * @param array<mixed> $flat  Clé plate modifiée (sans texts).
 * @return array<mixed>
 */
function oli_t_tpl( $def, $over = array(), $flat = array() ) {
	$oli_t_fb = $GLOBALS['oli_t_fb'];
	$texts    = array_merge( OLI_ACR_Templates::pick_texts( $def ), $over );
	$tpl   = array_merge( $def, $texts, $flat );
	$tpl['texts'] = array(
		$oli_t_fb => $texts,
		'en_US'   => $texts,
	);
	return $tpl;
}

$oli_t_custom = array(
	'subject anglais modifié'          => array( 'tpl_cart_1', array( 'subject' => 'Did you forget something, {first_name}?' ), array() ),
	'sujet en français saisi'          => array( 'tpl_cart_1', array( 'subject' => 'Votre panier vous attend, {first_name}' ), array() ),
	'corps modifié'                    => array( 'tpl_cart_2', array( 'content' => '<p>Bonjour {first_name},</p><p>Votre panier vous attend.</p>{cart_items}' ), array() ),
	'défaut + majuscules'              => array( 'tpl_cart_1', array( 'subject' => 'You Left Something In Your Cart' ), array() ),
	'défaut + espace double et finale' => array( 'tpl_cart_2', array( 'subject' => str_replace( 'something', 'something ', $oli_t_en['tpl_cart_2']['subject'] ) . ' ' ), array() ),
	'corps défaut + saut de ligne'     => array( 'tpl_cart_1', array( 'content' => str_replace( '</p><p>', "</p>\n<p>", $oli_t_en['tpl_cart_1']['content'] ) ), array() ),
	'clé plate seule modifiée'         => array( 'tpl_cart_1', array(), array( 'subject' => 'Sujet plat personnalisé' ) ),
);

foreach ( $oli_t_custom as $oli_t_label => $oli_t_case ) {
	list( $oli_t_id, $oli_t_over, $oli_t_flat ) = $oli_t_case;
	$oli_t_tpl = oli_t_tpl( $oli_t_en[ $oli_t_id ], $oli_t_over, $oli_t_flat );
	update_option( 'oli_acr_templates', array( $oli_t_id => $oli_t_tpl ), false );
	OLI_ACR_Templates::sync_languages();
	$oli_t_after = get_option( 'oli_acr_templates' );
	foreach ( array_merge( $oli_t_over, $oli_t_flat ) as $oli_t_field => $oli_t_value ) {
		if ( isset( $oli_t_over[ $oli_t_field ] ) ) {
			oli_t_check( $oli_t_after[ $oli_t_id ]['texts'][ $oli_t_fb ][ $oli_t_field ] === $oli_t_value, "$oli_t_label : texts[$oli_t_fb][$oli_t_field] conservé à l'octet près" );
		}
		oli_t_check( $oli_t_after[ $oli_t_id ][ $oli_t_field ] === $oli_t_value, "$oli_t_label : clé plate $oli_t_field conservée à l'octet près" );
	}
	$oli_t_once = maybe_serialize( $oli_t_after );
	OLI_ACR_Templates::sync_languages();
	oli_t_check( maybe_serialize( get_option( 'oli_acr_templates' ) ) === $oli_t_once, "$oli_t_label : 2e exécution sans changement" );
}

// Défaut français écrit dans une langue de repli non française (ex. site en_US) : jamais remplacé par l'anglais.
if ( 0 !== strpos( $oli_t_fb, 'fr' ) ) {
	$oli_t_frd = oli_acr_in_locale( 'fr_CA', array( 'OLI_ACR_Templates', 'default_templates_raw' ) );
	if ( $oli_t_frd['tpl_cart_1']['subject'] !== $oli_t_en['tpl_cart_1']['subject'] ) {
		$oli_t_tpl = oli_t_tpl( $oli_t_en['tpl_cart_1'], OLI_ACR_Templates::pick_texts( $oli_t_frd['tpl_cart_1'] ) );
		update_option( 'oli_acr_templates', array( 'tpl_cart_1' => $oli_t_tpl ), false );
		OLI_ACR_Templates::sync_languages();
		$oli_t_after = get_option( 'oli_acr_templates' );
		oli_t_check( $oli_t_after['tpl_cart_1']['texts'][ $oli_t_fb ]['subject'] === $oli_t_frd['tpl_cart_1']['subject'] && $oli_t_after['tpl_cart_1']['subject'] === $oli_t_frd['tpl_cart_1']['subject'], 'défaut français dans une langue non française : conservé' );
	}
}

// Défaut strict : la langue de repli prend son texte par défaut (traduit si une traduction est chargée).
$oli_t_tpl = oli_t_tpl( $oli_t_en['tpl_cart_1'] );
update_option( 'oli_acr_templates', array( 'tpl_cart_1' => $oli_t_tpl ), false );
OLI_ACR_Templates::sync_languages();
$oli_t_after = get_option( 'oli_acr_templates' );
$oli_t_want  = OLI_ACR_Lang::run_in( $oli_t_fb, array( 'OLI_ACR_Templates', 'default_templates_raw' ) );
oli_t_check( $oli_t_after['tpl_cart_1']['texts'][ $oli_t_fb ]['subject'] === $oli_t_want['tpl_cart_1']['subject'], 'défaut strict : réaligné sur le défaut de la langue de repli' );
$oli_t_once = maybe_serialize( $oli_t_after );
OLI_ACR_Templates::sync_languages();
oli_t_check( maybe_serialize( get_option( 'oli_acr_templates' ) ) === $oli_t_once, 'défaut strict : 2e exécution sans changement' );

update_option( 'oli_acr_templates', $oli_t_saved, false );
echo $GLOBALS['oli_t_count'] . ' vérifications, ' . $GLOBALS['oli_t_fail'] . " échec(s)\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
exit( $GLOBALS['oli_t_fail'] ? 1 : 0 );
