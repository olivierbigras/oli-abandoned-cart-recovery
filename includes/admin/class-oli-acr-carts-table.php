<?php
/**
 * Tableau d'admin (WP_List_Table) : liste des paniers suivis.
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
 * Liste des paniers suivis.
 */
class OLI_ACR_Carts_Table extends WP_List_Table {

	/**
	 * Statuts filtrés par la vue courante (vide = tous).
	 *
	 * @var string[]
	 */
	private $statuses = array();

	/**
	 * Constructeur.
	 *
	 * @param string[] $statuses Statuts de la vue (vide = tous).
	 */
	public function __construct( $statuses = array() ) {
		$this->statuses = array_values( array_intersect( array_map( 'strval', (array) $statuses ), array_keys( oli_acr_statuses() ) ) );
		parent::__construct(
			array(
				'singular' => 'oli_acr_cart',
				'plural'   => 'oli_acr_carts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Colonnes (1.2) : Client, Statut, Articles, Total, Relances, Date.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Customer', 'oli-abandoned-cart-recovery' ),
			'status'     => __( 'Status', 'oli-abandoned-cart-recovery' ),
			'items'      => __( 'Items', 'oli-abandoned-cart-recovery' ),
			'cart_total' => __( 'Total', 'oli-abandoned-cart-recovery' ),
			'reminders'  => __( 'Reminders', 'oli-abandoned-cart-recovery' ),
			'updated_at' => __( 'Date', 'oli-abandoned-cart-recovery' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array<string, array<int, bool|string>>
	 */
	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'cart_total' => array( 'cart_total', false ),
			'updated_at' => array( 'updated_at', true ),
		);
	}

	/**
	 * Actions groupées.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'oli-abandoned-cart-recovery' ) );
	}

	/**
	 * Préparation des lignes.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = oli_acr_table( 'carts' );
		$per_page = 20;
		$paged    = $this->get_pagenum();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'email', 'cart_total', 'updated_at' ), true ) ? sanitize_key( $_GET['orderby'] ) : 'updated_at';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( $_GET['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable

		$where  = '1=1';
		$params = array();
		if ( $this->statuses ) {
			$where .= ' AND status IN (' . implode( ',', array_fill( 0, count( $this->statuses ), '%s' ) ) . ')';
			$params = array_merge( $params, $this->statuses );
		}
		if ( '' !== $search ) {
			$where .= ' AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s)';
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$params = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		$params_count = $params;
		$params[]     = $per_page;
		$params[]     = ( $paged - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where ne contient que des marqueurs %s construits ci-dessus.
		$total       = (int) ( $params_count ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params_count ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $params ) );
		// phpcs:enable

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Case à cocher.
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_cb( $item ) {
		$id = 'oli-acr-cb-' . (int) $item->id;
		return '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html(
			/* translators: %s: customer email. */
			sprintf( __( 'Select the cart of %s', 'oli-abandoned-cart-recovery' ), $item->email )
		) . '</label><input type="checkbox" id="' . esc_attr( $id ) . '" name="cart_ids[]" value="' . esc_attr( $item->id ) . '" />';
	}

	/**
	 * Colonne Client : courriel (lien vers le panneau), nom, actions toujours visibles (pas seulement au survol).
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_email( $item ) {
		$args = array( 'cart' => (int) $item->id );
		$view = OLI_ACR_Admin_Carts::current_view();
		if ( 'all' !== $view ) {
			$args['view'] = $view;
		}
		$out  = '<a class="row-title oli-acr-email" href="' . esc_url( OLI_ACR_Admin::url( 'carts', $args ) ) . '">' . esc_html( $item->email ) . '</a>';
		$name = trim( $item->first_name . ' ' . $item->last_name );
		if ( $name ) {
			$out .= '<br>' . esc_html( $name );
		}
		$actions = array();
		if ( OLI_ACR_Admin_Carts::can_send( $item ) ) {
			$actions['send'] = '<a href="' . esc_url( OLI_ACR_Admin_Carts::action_url( $item, 'send' ) ) . '">' . esc_html__( 'Send the next reminder', 'oli-abandoned-cart-recovery' ) . '</a>';
		}
		$actions['delete'] = '<a class="submitdelete" href="' . esc_url( OLI_ACR_Admin_Carts::action_url( $item, 'delete' ) ) . '">' . esc_html__( 'Delete', 'oli-abandoned-cart-recovery' ) . '</a>';
		return $out . $this->row_actions( $actions, true );
	}

	/**
	 * Autres colonnes.
	 *
	 * @param object $item        Ligne.
	 * @param string $column_name Colonne.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'status':
				return OLI_ACR_Admin_Carts::status_pill( (string) $item->status );
			case 'items':
				return esc_html( OLI_ACR_Admin_Carts::items_summary( $item ) );
			case 'cart_total':
				return wp_kses_post( wc_price( (float) $item->cart_total, array( 'currency' => $item->currency ) ) );
			case 'reminders':
				$planned = count( OLI_ACR_Templates::active( 'cart' ) );
				return esc_html( $planned > 0 ? (int) $item->emails_sent . ' / ' . max( $planned, (int) $item->emails_sent ) : (string) (int) $item->emails_sent );
			case 'updated_at':
				return OLI_ACR_Admin::relative_date( (string) $item->updated_at );
		}
		return '';
	}

	/**
	 * Message vide.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No carts captured yet.', 'oli-abandoned-cart-recovery' );
	}
}
