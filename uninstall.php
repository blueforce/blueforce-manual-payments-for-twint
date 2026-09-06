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
 * @package Blueforce_Manual_Payments_For_TWINT
 */

// Nur ausführen, wenn WordPress die Deinstallation auslöst.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bf_twint_options = array(
	'woocommerce_bf_twint_settings',
	'bf_twint_released_count', // Zähler für den Bewertungshinweis.
);

if ( is_multisite() ) {
	// number => 0 hebt das Standardlimit von 100 auf, damit auf grossen
	// Netzwerken auch die Sites jenseits der ersten 100 aufgeräumt werden.
	$bf_twint_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $bf_twint_site_ids as $bf_twint_site_id ) {
		switch_to_blog( $bf_twint_site_id );
		foreach ( $bf_twint_options as $bf_twint_option ) {
			delete_option( $bf_twint_option );
		}
		wp_clear_scheduled_hook( 'bf_twint_cancel_unpaid_orders' );
		wp_clear_scheduled_hook( 'bf_twint_send_payment_reminders' );
		restore_current_blog();
	}
} else {
	foreach ( $bf_twint_options as $bf_twint_option ) {
		delete_option( $bf_twint_option );
	}
	wp_clear_scheduled_hook( 'bf_twint_cancel_unpaid_orders' );
	wp_clear_scheduled_hook( 'bf_twint_send_payment_reminders' );
}

// Entscheidung zum Bewertungshinweis (pro Benutzer). Die usermeta-Tabelle ist
// netzwerkweit, darum einmal ausserhalb der Site-Schleife.
delete_metadata( 'user', 0, '_bf_twint_review_prompt', '', true );
