<?php
/**
 * Tableau d'admin (WP_List_Table) : journal des courriels.
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Journal des courriels.
 */
class OLI_ACR_Log_Table extends WP_List_Table {

	/**
	 * Constructeur.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'oli_acr_log',
				'plural'   => 'oli_acr_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Colonnes.
	 *
	 * @return array<mixed>
	 */
	public function get_columns() {
		return array(
			'sent_at'     => __( 'Date', 'oli-abandoned-cart-recovery' ),
			'email'       => __( 'Recipient', 'oli-abandoned-cart-recovery' ),
			'template_id' => __( 'Reminder', 'oli-abandoned-cart-recovery' ),
			'result'      => __( 'Result', 'oli-abandoned-cart-recovery' ),
			'object_id'   => __( 'Cart', 'oli-abandoned-cart-recovery' ),
		);
	}

	/**
	 * Prépare les lignes.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = oli_acr_table( 'log' );
		$per_page = 30;
		$paged    = $this->get_pagenum();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Noms de table fixes (oli_acr_table()), valeurs passées par prepare().
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ) );
		// phpcs:enable
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Colonnes par défaut.
	 *
	 * @param object $item        Ligne.
	 * @param string $column_name Colonne.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'sent_at':
				return OLI_ACR_Admin::relative_date( (string) $item->sent_at );
			case 'email':
				return esc_html( $item->email );
			case 'template_id':
				$name = OLI_ACR_Admin::template_name( (string) $item->template_id );
				return esc_html( $name ) . ( $item->coupon_code ? '<br><small>' . esc_html__( 'Coupon:', 'oli-abandoned-cart-recovery' ) . ' <code>' . esc_html( $item->coupon_code ) . '</code></small>' : '' );
			case 'result':
				if ( ! empty( $item->recovered_at ) ) {
					return '<span class="oli-acr-pill oli-acr-pill-recovered">' . esc_html__( 'Recovered', 'oli-abandoned-cart-recovery' ) . '</span> ' . wp_kses_post( wc_price( (float) $item->recovered_total ) );
				}
				if ( ! empty( $item->clicked_at ) ) {
					return '<span class="oli-acr-pill oli-acr-pill-reminded">' . esc_html__( 'Clicked', 'oli-abandoned-cart-recovery' ) . '</span>';
				}
				return '<span class="oli-acr-pill oli-acr-pill-open">' . esc_html__( 'Sent', 'oli-abandoned-cart-recovery' ) . '</span>';
			case 'object_id':
				if ( 'order' === $item->object_type ) {
					$order = wc_get_order( (int) $item->object_id );
					/* translators: %s: order number. */
					return $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'Order #%s', 'oli-abandoned-cart-recovery' ), $order->get_order_number() ) ) . '</a>' : '—';
				}
				return OLI_ACR_Carts::get( (int) $item->object_id ) ? '<a href="' . esc_url( OLI_ACR_Admin::url( 'carts', array( 'cart' => (int) $item->object_id ) ) ) . '">' . esc_html__( 'View cart', 'oli-abandoned-cart-recovery' ) . '</a>' : '—';
		}
		return '';
	}

	/**
	 * Message vide.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No email sent yet.', 'oli-abandoned-cart-recovery' );
	}
}
