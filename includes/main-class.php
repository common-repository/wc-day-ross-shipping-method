<?php
/**
 * Primary class for Day&Ross Shipping Plugin
 */
defined( 'ABSPATH' ) || exit;

class RapidPlugin_DayAndRoss {
	private static $instance;

	public static function run() {
		return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
	}

	public static function getWeightUnit() {
		return get_option( 'woocommerce_weight_unit' ) == 'kg' ? "Kilograms" : "Pounds";
	}

	public static function getLengthUnit() {
		switch ( get_option( 'woocommerce_dimension_unit' ) ) {
			case 'm' :
				return "Meters";
			case 'cm' :
				return "Centimeters";
			default : //mm - in - yd [Day&Ross Does Not Support mm, ft and yd]
				return "Inches";
		}
	}

	public static function getProductInfo( $content, $qty = 1 ) {
		$parent  = $content['product_id'];
		$product = $content['variation_id'] ?: $parent;
		$title   = trim( preg_replace( '/[^a-zA-Z0-9\s]/', '', strip_tags( html_entity_decode( get_the_title( $product ) ) ) ) );
		$items   = [];
		for ( $k = 0; $k < $qty; $k ++ ) {
			$items[ "ShipmentItem$product" . "000" . $k ] = [
				"Description" => $title . " Piece #1 x" . $qty,
				"Pieces"      => 1,
				"Weight"      => ceil( ( get_post_meta( $product, '_weight', true ) ?: get_post_meta( $parent, '_weight', true ) ) ),
				"Length"      => ceil( ( get_post_meta( $product, '_length', true ) ?: get_post_meta( $parent, '_length', true ) ) ),
				"Width"       => ceil( ( get_post_meta( $product, '_width', true ) ?: get_post_meta( $parent, '_width', true ) ) ),
				"Height"      => ceil( ( get_post_meta( $product, '_height', true ) ?: get_post_meta( $parent, '_height', true ) ) ),
				"WeightUnit"  => self::getWeightUnit(),
				"LengthUnit"  => self::getLengthUnit(),
			];
		}
		$i = 2;
		while ( get_post_meta( $product, '_weight' . $i, true ) ) {
			for ( $k = 0; $k < $qty; $k ++ ) {
				$items[ "ShipmentItem$product" . $i . '0' . $k ] = [
					"Description" => $title . " Piece #$i x" . $qty,
					"Pieces"      => 1,
					"Weight"      => ceil( get_post_meta( $product, '_weight' . $i, true ) ),
					"Length"      => ceil( get_post_meta( $product, '_length' . $i, true ) ),
					"Width"       => ceil( get_post_meta( $product, '_width' . $i, true ) ),
					"Height"      => ceil( get_post_meta( $product, '_height' . $i, true ) ),
					"WeightUnit"  => self::getWeightUnit(),
					"LengthUnit"  => self::getLengthUnit(),
				];
			}
			$i ++;
		}

		return $items;
	}

	public function __construct() {
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			$this->init();
		} else {
			add_action( 'admin_notices', array( $this, 'requiresWoocommerce' ) );
		}
	}

	public function init() {
		add_action( 'init', [ $this, 'session' ], 1 );
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'http_api_curl', [ $this, 'curlConfig' ], 99999 );
		add_filter( 'plugin_action_links_' . RapidPlugin_DayAndRoss_BaseName, [ $this, 'copyright' ] );
		add_action( 'woocommerce_shipping_init', [ $this, 'newMethod' ] );
		add_filter( 'woocommerce_shipping_methods', [ $this, 'add_method' ] );
		add_action( 'woocommerce_cart_updated', [ $this, 'clearCache' ] );
		add_filter( 'woocommerce_checkout_update_order_review', [ $this, 'clearCache' ] );
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'addSimpleOption' ], 10, 3 );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'saveSimpleOptions' ] );
		add_action( 'woocommerce_variation_options_pricing', [ $this, 'addVariableOption' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'saveVariableOptions' ], 10, 2 );
	}

	public function newMethod() {
		include_once 'shipping-class.php';
	}

	public function clearCache() {
		$packages = WC()->cart->get_shipping_packages();
		if ( $packages ) {
			foreach ( $packages as $key => $value ) {
				$shipping_session = "shipping_for_package_$key";
				unset( WC()->session->$shipping_session );
			}
		}
	}

	public function session() {
		@ini_set( 'session.cookie_secure', '0' );
		if ( ! isset( $_SESSION ) ) {
			session_start();
		}
	}

	public function prepareItems( $products, $order, $Level, $extra ) {
		$items       = $ServiceLevel = [];
		$ServiceType = '';
		if ( $items = $order->get_items() ) {
			foreach ( $items as $item_id => $item ) {
				$_product    = $item->get_product();
				$product     = $_product->get_variation_id() ?: $_product->get_product_id();
				$ServiceType = get_post_meta( $product, '_SType', true );
			}
		}

		return [
			"shipment" => array_merge( [
				"Items"        => $products,
				"ServiceLevel" => $Level,
				"ServiceType"  => $ServiceType,
			], $extra ),
		];
	}

	public function curlConfig( $e ) {
		curl_setopt( $e, CURLOPT_CONNECTTIMEOUT, 0 );
		curl_setopt( $e, CURLOPT_TIMEOUT, 0 );
	}

	public function add_method( $methods ) {
		$methods['rapidplugin_dayandross'] = 'RapidPlugin_DayAndRoss_Shipping';

		return $methods;
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'RapidPlugin-DayAndRoss', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public function copyright( $links ) {
		return array_merge( [
			'<a style="color:#e91e63;font-weight: bold;" href="mailto:rapidplugin.com@gmail.com">' . __( 'Support', 'RapidPlugin-DayAndRoss' ) . '</a>',
			'<a href="' . admin_url( '/admin.php?page=wc-settings&tab=shipping&section=rapidplugin_dayandross' ) . '">' . __( 'Settings', 'RapidPlugin-DayAndRoss' ) . '</a>'
		], $links );
	}

	public function requiresWoocommerce() {
		echo '<div class="error"><p><b>DayAndRoss Shipping Plugin</b> needs Woocommerce Plugin to be activated!</p></div>';
	}

	public function addSimpleOption() {
		global $post;
		woocommerce_wp_select( [
			'id'      => '_SType',
			'name'    => '_SType',
			'label'   => 'Day & Ross Service Type',
			'options' => [ 'C' => 'C - Commercial', 'H' => 'H - HomeWay' ],
			'value'   => get_post_meta( $post->ID, '_SType', true )
		] );
		woocommerce_wp_select( [
			'id'                => '_SLevel',
			'name'              => '_SLevel[]',
			'label'             => 'Day & Ross Service Level',
			'class'             => 'cb-admin-multiselect',
			'custom_attributes' => [ 'multiple' => 'multiple' ],
			'options'           => include RapidPlugin_DayAndRoss_Path . 'includes/services.php',
			'value'             => get_post_meta( $post->ID, '_SLevel', true )
		] );
		woocommerce_wp_select( [
			'id'                => '_Extra',
			'name'              => '_Extra[]',
			'label'             => 'Day & Ross Additional Services',
			'class'             => 'cb-admin-multiselect',
			'custom_attributes' => [ 'multiple' => 'multiple' ],
			'options'           => include RapidPlugin_DayAndRoss_Path . 'includes/services2.php',
			'value'             => get_post_meta( $post->ID, '_Extra', true )
		] );
		woocommerce_wp_text_input( [
			'id'          => '_insurance',
			'name'        => '_insurance',
			'type'        => 'number',
			'label'       => 'Insurance Amount',
			'desc_tip'    => true,
			'description' => 'if you select Insurance and leave this blank, product price will be set as default value.',
			'value'       => get_post_meta( $post->ID, '_insurance', true )
		] );
	}

	public function addVariableOption( $loop, $variation_data, $variation ) {
		$post = $variation->post_parent;
		echo '<div class="options_group form-row form-row-full">';
		woocommerce_wp_select( [
			'id'      => '_SType[' . $variation->ID . ']',
			'name'    => '_SType[' . $variation->ID . ']',
			'label'   => 'Day & Ross Service Level',
			'options' => [ 'C' => 'C - Commercial', 'H' => 'H - HomeWay' ],
			'value'   => get_post_meta( $variation->ID, '_SType', true )
		] );
		woocommerce_wp_select( [
			'id'                => '_SLevel[' . $variation->ID . ']',
			'name'              => '_SLevel[' . $variation->ID . '][]',
			'label'             => 'Day & Ross Service Level',
			'options'           => include RapidPlugin_DayAndRoss_Path . 'includes/services.php',
			'class'             => 'cb-admin-multiselect',
			'custom_attributes' => [ 'multiple' => 'multiple' ],
			'value'             => get_post_meta( $variation->ID, '_SLevel', true )
		] );
		woocommerce_wp_select( [
			'id'                => '_Extra[' . $variation->ID . ']',
			'name'              => '_Extra[' . $variation->ID . '][]',
			'label'             => 'Day & Ross Additional Services',
			'options'           => include RapidPlugin_DayAndRoss_Path . 'includes/services2.php',
			'class'             => 'cb-admin-multiselect',
			'custom_attributes' => [ 'multiple' => 'multiple' ],
			'value'             => get_post_meta( $variation->ID, '_Extra', true )
		] );
		woocommerce_wp_text_input( [
			'id'          => '_insurance[' . $variation->ID . ']',
			'name'        => '_insurance[' . $variation->ID . ']',
			'type'        => 'number',
			'label'       => 'Insurance Amount',
			'description' => 'if you select Insurance and leave this blank, product price will be set as default value.',
			'desc_tip'    => true,
			'value'       => get_post_meta( $variation->ID, '_insurance', true )
		] );
		echo '</div>';
	}

	public function saveSimpleOptions( $product ) {
		$pid    = $product->get_id();
		$levels = isset( $_POST['_SLevel'] ) ? array_map( 'sanitize_text_field', $_POST['_SLevel'] ) : [];
		$extra  = isset( $_POST['_Extra'] ) ? array_map( 'sanitize_text_field', $_POST['_Extra'] ) : [];
		update_post_meta( $pid, '_SType', sanitize_text_field( $_POST['_SType'] ) );
		update_post_meta( $pid, '_SLevel', $levels );
		update_post_meta( $pid, '_Extra', $extra );
		update_post_meta( $pid, '_insurance', floatval( $_POST['_insurance'] ) );
	}

	public function saveVariableOptions( $pid, $i ) {
		$levels = isset( $_POST['_SLevel'][ $pid ] ) ? array_map( 'sanitize_text_field', $_POST['_SLevel'][ $pid ] ) : [];
		$extra  = isset( $_POST['_Extra'][ $pid ] ) ? array_map( 'sanitize_text_field', $_POST['_Extra'][ $pid ] ) : [];
		update_post_meta( $pid, '_SType', sanitize_text_field( $_POST['_SType'][ $pid ] ) );
		update_post_meta( $pid, '_SLevel', $levels );
		update_post_meta( $pid, '_Extra', $extra );
		update_post_meta( $pid, '_insurance', floatval( $_POST['_insurance'][ $pid ] ) );
	}
}