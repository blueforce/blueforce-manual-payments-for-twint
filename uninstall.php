<?php
/**
 * Deinstallationsroutine.
 *
 * Entfernt die vom Plugin gespeicherten Gateway-Einstellungen
 * (WooCommerce-Option «woocommerce_bf_twint_settings»), wenn das Plugin
 * über «Löschen» entfernt wird.
 *
 * Bestelldaten (Order-Meta «_bf_twint_*») bleiben bewusst erhalten – sie
 * gehören zur Bestellhistorie des Shops und dürfen nicht mitgelöscht werden.
 *
 * @package TWINT_For_WooCommerce
 */

// Nur ausführen, wenn WordPress die Deinstallation auslöst.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bf_twint_option = 'woocommerce_bf_twint_settings';

if ( is_multisite() ) {
	$bf_twint_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $bf_twint_site_ids as $bf_twint_site_id ) {
		switch_to_blog( $bf_twint_site_id );
		delete_option( $bf_twint_option );
		restore_current_blog();
	}
} else {
	delete_option( $bf_twint_option );
}
