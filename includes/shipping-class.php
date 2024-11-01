<?php
/**
 * Shipping class for Day&Ross Shipping Plugin
 */
defined( 'ABSPATH' ) || exit;

class RapidPlugin_DayAndRoss_Shipping extends WC_Shipping_Method {
	private $API_URL = "http://dayross.dayrossgroup.com/public/ShipmentServices.asmx?WSDL";
	private $activeLevels = [];
	private $found_rates, $services, $services2, $email, $password, $BillToAccount, $delay, $sort, $debug;

	public function __construct( $key = 0 ) {
		$this->id                 = 'rapidplugin_dayandross';
		$this->instance_id        = absint( $key );
		$this->method_title       = __( 'Day&Ross', 'RapidPlugin-DayAndRoss' );
		$this->method_description = __( 'Day&Ross Shipping plugin helps you to connect to Day&Ross API easily!', 'RapidPlugin-DayAndRoss' );
		$this->services           = include RapidPlugin_DayAndRoss_Path . 'includes/services.php';
		$this->services2          = include RapidPlugin_DayAndRoss_Path . 'includes/services2.php';
		$this->supports           = [ 'shipping-zones', 'instance-settings', 'settings' ];
		$this->init();
	}

	private function init() {
		$this->init_form_fields();
		$this->set_settings();
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	private function set_settings() {
		$this->title           = $this->get_option( 'title', $this->method_title );
		$this->custom_services = $this->get_option( 'services', [] );
		$this->email           = $this->get_option( 'email' );
		$this->email2          = $this->get_option( 'email2' );
		$this->password        = $this->get_option( 'password' );
		$this->password2       = $this->get_option( 'password2' );
		$this->BillToAccount   = intval( $this->get_option( 'BillToAccount' ) );
		$this->delay           = intval( $this->get_option( 'general_delay' ) );
		$this->sort            = $this->get_option( 'sort' );
		$this->debug           = ( ( $bool = $this->get_option( 'debug' ) ) && $bool === 'yes' );
	}

	public function load_admin_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	public function is_available( $package ) {
//		$this->debug( json_encode( $package ) );
		if ( empty( $package['destination']['country'] ) || empty( $package['destination']['state'] ) || empty( $package['destination']['postcode'] ) ) {
			return false;
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
	}

	public function process_admin_options() {
		parent::process_admin_options();
		$this->set_settings();
	}

	public function debug( $message, $type = 'notice' ) {
		if ( $this->debug || ( current_user_can( 'manage_options' ) && 'error' == $type ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $message, $type );
			}
		}
	}

	public function init_form_fields() {
		$this->instance_form_fields = include RapidPlugin_DayAndRoss_Path . 'includes/settings.php';
		$this->form_fields          = array(
			'api'           => array(
				'title'       => __( 'API Settings', 'RapidPlugin-DayAndRoss' ),
				'type'        => 'title',
				'description' => __( 'API information given to you by DayAndRoss support team!', 'RapidPlugin-DayAndRoss' ),
			),
			'email'         => array(
				'title' => __( 'Email Address', 'RapidPlugin-DayAndRoss' ),
				'type'  => 'email',
			),
			'password'      => array(
				'title' => __( 'Password', 'RapidPlugin-DayAndRoss' ),
				'type'  => 'password',
			),
			'BillToAccount' => array(
				'title'       => __( 'Bill To Account (Account No.)', 'RapidPlugin-DayAndRoss' ),
				'type'        => 'text',
				'description' => 'A six-digit number special for your account!',
			),
			'general_delay' => array(
				'title'       => __( 'General Delay', 'RapidPlugin-DayAndRoss' ),
				'type'        => 'text',
				'description' => __( 'Set a default delay for all unset services! (in days)', 'RapidPlugin-DayAndRoss' )
			),
			'sort'          => array(
				'title'       => __( 'Order of Services', 'RapidPlugin-DayAndRoss' ),
				'description' => '',
				'type'        => 'select',
				'default'     => 'adminSort',
				'options'     => [ 'adminSort' => 'Manually', 'PriceBaseSort' => 'By Price (low to high)' ]
			),
			'debug'         => array(
				'title'       => __( 'Debug Mode', 'RapidPlugin-DayAndRoss' ),
				'label'       => __( 'Enable Debug Mode', 'RapidPlugin-DayAndRoss' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable Debug Mode to show debugging information on the cart/checkout.', 'RapidPlugin-DayAndRoss' )
			),
		);
		$this->form_fields          = apply_filters( 'rapidplugin_settings_fields', $this->form_fields, null );
	}

	public function generate_services_html() {
		ob_start();
		include RapidPlugin_DayAndRoss_Path . 'includes/display-services.php';

		return ob_get_clean();
	}

	public function validate_services_field( $key ) {
		$services        = [];
		$posted_services = isset( $_POST['RapidPlugin_DayAndRoss'] ) ? map_deep( $_POST['RapidPlugin_DayAndRoss'], 'sanitize_text_field' ) : [];
		if ( ! $posted_services ) {
			return $services;
		}
		foreach ( $posted_services as $code => $settings ) {
			$services[ $code ] = array(
				'name'               => wc_clean( $settings['name'] ),
				'order'              => wc_clean( $settings['order'] ),
				'enabled'            => isset( $settings['enabled'] ) ? true : false,
				'delay'              => $settings['delay'] ? intval( $settings['delay'] ) : '',
				'adjustment'         => wc_clean( $settings['adjustment'] ),
				'adjustment_type'    => wc_clean( $settings['adjustment_type'] ),
				'adjustment_type2'   => wc_clean( $settings['adjustment_type2'] ),
				'adjustment_percent' => str_replace( '%', '', wc_clean( $settings['adjustment_percent'] ) )
			);
		}

		return $services;
	}

	private function getActiveServices() {
		$C    = [ 'EG', 'AM', 'D9', 'UP', 'UL' ];
		$H    = [ 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ];
		$data = [];
		foreach ( $C as $c ) {
			if ( isset( $this->custom_services[ $c ] ) && $this->custom_services[ $c ]['enabled'] ) {
				$data[] = 'C';
				break;
			}
		}
		foreach ( $H as $h ) {
			if ( isset( $this->custom_services[ $h ] ) && $this->custom_services[ $h ]['enabled'] ) {
				$data[] = 'H';
				break;
			}
		}

		return $data;
	}

	private function prepare4Request() {
		$services = $this->getActiveServices();
		if ( ! $services ) {
			$this->debug( "No Service is active to show the rates! Please update Day&Ross settings!" );

			return false;
		}
		$this->debug( 'Active Services: ' . json_encode( $services ) );
		$request = $this->prepare_request();
		$rates   = $this->apiCall( 'GetRate2', $request );
		$this->debug( 'Request: ' . json_encode( $request ) );
		$this->debug( 'Rates: ' . json_encode( $rates ) );
		if ( ! $rates || ! is_array( $rates ) ) {
			return false;
		}
		if ( isset( $rates['ServiceLevelCode'] ) ) {
			$rates = [ $rates ];
		}
		foreach ( $rates as $rate ) {
			$rate_code = strval( $rate['ServiceLevelCode'] );
			$rate_id   = $this->id . ':' . $rate_code;
			$rate_name = strval( $rate['Description'] );
			$rate_cost = floatval( $rate['TotalAmount'] );
			$this->prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $rate['ExpectedDeliveryDate'] );
		}

		return true;
	}

	private function auth() {
		return [
			"division"     => "Sameday",
			"emailAddress" => $this->email,
			"password"     => $this->password,
			"shipment"     => [
				"ShipperAddress" => [
					"Name"         => get_bloginfo( 'name', 'display' ),
					"CompanyName"  => get_bloginfo( 'name', 'display' ),
					"Country"      => strtoupper( WC()->countries->get_base_country() ) != 'CA' ?: 'CAN',
					"Province"     => strtoupper( WC()->countries->get_base_state() ),
					"City"         => strtoupper( WC()->countries->get_base_city() ),
					"PostalCode"   => str_replace( ' ', '', strtoupper( WC()->countries->get_base_postcode() ) ),
					"Address1"     => str_replace( ' ', '', strtoupper( WC()->countries->get_base_postcode() ) ),
					"Address2"     => str_replace( ' ', '', strtoupper( WC()->countries->get_base_postcode() ) ),
					"EmailAddress" => get_option( 'admin_email' ),
				],
				"BillToAccount"  => $this->BillToAccount,
				"ShipmentType"   => "Regular",
				"PaymentType"    => "Prepaid",
				"ReadyTime"      => date( 'Y-m-d\Th:i:s' ),
				"ClosingTime"    => date( 'Y-m-d\Th:i:s', strtotime( "+1 day" ) ),
			],
			"language"     => "EN",
			"labelFormat"  => "PDF",
		];
	}

	private function getItems() {
		$package = $this->package;
		if ( ! $package['contents'] ) {
			$this->debug( "Cart is Empty!" );

			return false;
		}
		$items       = $ServiceLevel = $levels = $all = [];
		$max         = $for = 0;
		$ServiceType = '';
		foreach ( $package['contents'] as $content ) {
			$qty     = intval( $content['quantity'] );
			$product = $content['variation_id'];
			if ( ! $product ) {
				$product = $content['product_id'];
			}
			$ServiceType              = get_post_meta( $product, '_SType', true );
			$level                    = get_post_meta( $product, '_SLevel', true );
			$mx                       = [];
			$ServiceLevel[ $product ] = $level;
			if ( is_array( $level ) && $level ) {
				foreach ( $level as $l ) {
					$e = intval( filter_var( $l, FILTER_SANITIZE_NUMBER_INT ) );
					if ( $e > $max ) {
						$max = $e;
						$for = $product;
					}
					$levels[ $product ][] = $e;
					$all[]                = $e;
				}
			}
			$info = RapidPlugin_DayAndRoss::getProductInfo( $content, $qty );
			if ( ! $info ) {
				$this->debug( "Product $product details are not correct!" );
				continue;
			}
			if ( ! $ServiceType || ! $ServiceLevel ) {
				$this->debug( "Product $product Services are not set! => " . json_encode( [
						$ServiceType,
						json_encode( $ServiceLevel )
					] ) );
				continue;
			}
			$items = array_merge( $items, $info );
		}
		$this->activeLevels = $ServiceLevel[ $for ];
		if ( count( $ServiceLevel ) > 1 ) {
			$all   = array_unique( $all );
			$inter = [];
			foreach ( $levels as $pr => $L ) {
				rsort( $L );
				if ( $L[0] < 6 ) {
					$L = range( 6, $L[0] );
				}
				foreach ( $L as $u => $d ) {
					if ( ! in_array( $d, $all ) ) {
						unset( $L[ $u ] );
					}
				}
				$inter = array_intersect( $L, ( $inter ?: $L ) );
			}
			foreach ( $inter as $t => $item ) {
				$inter[ $t ] = "H$item";
			}
			$this->activeLevels = $inter;
		}

		return [
			"shipment" => array_merge( [
				"Items"       => $items,
				//"ServiceLevel" => $ServiceLevel,
				"ServiceType" => $ServiceType,
			], $this->getExtraServices( $package['contents'] ) ),
		];
	}

	public function getExtraServices( $items, $wcOrders = false ) {
		$data      = [];
		$used      = [];
		$ins_total = $i = 0;
		foreach ( $items as $n => $item ) {
			if ( $wcOrders ) {
				$_product = $item->get_product();
				$product  = $_product->get_variation_id() ?: $_product->get_product_id();
			} else {
				$product = $item['variation_id'];
				if ( ! $product ) {
					$product = $item['product_id'];
				}
			}
			$values = get_post_meta( $product, '_Extra', true ) ?: [];
			foreach ( $values as $index => $code ) {
				if ( ! in_array( $code, $used ) && $code != 'INSFEE' ) {
					$used[]                                        = $code;
					$data[ 'ShipmentSpecialService' . $i ]['Code'] = $code;
				}
				if ( $code == 'INSFEE' ) {
					$_product  = wc_get_product( $product );
					$ins_val   = get_post_meta( $product, '_insurance', true ) ?: $_product->get_price();
					$ins_total += $ins_val;
				}
				$i ++;
			}
			$i ++;
		}
		if ( $ins_total > 0 ) {
			$data[ 'ShipmentSpecialService' . $i ]['Code']             = 'INSFEE';
			$data[ 'ShipmentSpecialService' . $i ]['AccessorialValue'] = $ins_total;
		}
		if ( $data ) {
			return [ 'SpecialServices' => $data ];
		}

		return $data;
	}

	private function getCustomer() {
		$package = $this->package;
		$name    = ( WC()->checkout->get_value( 'shipping_first_name' ) ?: WC()->checkout->get_value( 'billing_first_name' ) ) . ' ' . ( WC()->checkout->get_value( 'shipping_last_name' ) ?: WC()->checkout->get_value( 'billing_last_name' ) );

		return [
			"shipment" => [
				"ConsigneeAddress" => [
					"Name"        => $name ?: 'Website Customer',
					"CompanyName" => WC()->checkout->get_value( 'shipping_company' ) ?: WC()->checkout->get_value( 'billing_company' ),
					"Country"     => $package['destination']['country'] != 'CA' ?: 'CAN',
					"Province"    => $package['destination']['state'],
					"City"        => $package['destination']['city'],
					"PostalCode"  => str_replace( ' ', '', strtoupper( $package['destination']['postcode'] ) ),
				],
			],
		];
	}

	private function prepare_request() {
		return array_merge_recursive( $this->auth(), $this->getItems(), $this->getCustomer() );
	}

	public function calculate_shipping( $package = array() ) {
		$this->found_rates = [];
		$this->package     = $package;
		$request           = [];
		$rates             = $this->prepare4Request();
		if ( ! $rates ) {
			return false;
		}
		$this->add_found_rates();
	}

	public function apiCall( $method, $request ) {
		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}
		if ( ! is_cart() && ! is_checkout() ) {
			return false;
		}
		$request = $this->bodyMaker( $request );
		if ( isset( $_SESSION[ 'Last_' . $method ] ) && isset( $_SESSION[ 'Last_' . $method . '_Time' ] ) ) {
			$last    = intval( $_SESSION[ 'Last_' . $method . '_Time' ] );
			$content = sanitize_text_field( $_SESSION[ 'Last_' . $method . '_Request' ] );
			$value   = sanitize_text_field( $_SESSION[ 'Last_' . $method ] );
			if ( $last && $value && $content && preg_replace( '/<ReadyTime>.*<\/ClosingTime>/', '', $content ) == preg_replace( '/<ReadyTime>.*<\/ClosingTime>/', '', $request ) && $last + 1800 > time() ) {
				return $value;
			}
		}
		$data = [
			'sslverify' => false,
			'timeout'   => 0,
			'headers'   => [
				'Content-Type' => "text/xml; charset=utf-8",
				'Host'         => "dayross.dayrossgroup.com",
				'SOAPAction'   => "http://dayrossgroup.com/web/public/webservices/shipmentServices/" . $method
			],
			'body'      => '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><' . $method . ' xmlns="http://dayrossgroup.com/web/public/webservices/shipmentServices" xmlns:ns2="http://www.dayrossgroup.com/web/common/webServices/OnlineShipping">' . $request . '</' . $method . '></soap:Body></soap:Envelope>',
		];
		$response = wp_remote_post( $this->API_URL, $data );
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( ! $body || strpos( $body, 'error' ) !== false ) {
				$this->debug( "Api didn't respond!" );
				file_put_contents( WP_CONTENT_DIR . '/debug_last_req.txt', $data['body'] );

				return false;
			}
			$response = preg_replace( "/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $body );
			$xml      = new SimpleXMLElement( $response );
			$body     = $xml->xpath( '//soapBody' )[0];
			$body     = json_decode( json_encode( (array) $body ), true );
			if ( $body && isset( $body['GetRate2Response'] ) ) {
				$value                                      = ( $body['GetRate2Response']['GetRate2Result']['ServiceLevels'] );
				$_SESSION[ 'Last_' . $method . '_Time' ]    = time();
				$_SESSION[ 'Last_' . $method . '_Request' ] = sanitize_text_field( $request );
				$_SESSION[ 'Last_' . $method ]              = $value;

				return $value;
			} else {
				$this->debug( "Api body is not correct!" );
				$this->debug( json_encode( $body ) );
			}
		} else {
			$this->debug( $response->get_error_message() );
		}
		$this->debug( "Api call sent!" );

		return false;
	}

	private function bodyMaker( $opts ) {
		$body = '';
		if ( $opts ) {
			foreach ( $opts as $key => $opt ) {
				if ( ! is_array( $opt ) ) {
					$body .= "<$key>$opt</$key>";
				} else {
					$body .= "<$key>";
					foreach ( $opt as $k => $o ) {
						$k = trim( preg_replace( '/\d+/u', '', $k ) );
						if ( ! is_array( $o ) ) {
							$body .= "<$k>$o</$k>";
						} else {
							$body .= "<$k>";
							foreach ( $o as $r => $n ) {
								$r = trim( preg_replace( '/\d+/u', '', $r ) );
								if ( ! is_array( $n ) ) {
									$body .= "<$r>$n</$r>";
								} else {
									$body .= "<$r>";
									foreach ( $n as $z => $q ) {
										$z    = trim( preg_replace( '/\d+/u', '', $z ) );
										$body .= "<$z>$q</$z>";
									}
									$body .= "</$r>";
								}
							}
							$body .= "</$k>";
						}
					}
					$body .= "</$key>";
				}
			}
		}

		return $body;
	}

	private function prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $rate_delivery ) {
		$delay = $this->delay;
		if ( isset( $this->custom_services[ $rate_code ]['delay'] ) && intval( $this->custom_services[ $rate_code ]['delay'] ) ) {
			$delay = intval( $this->custom_services[ $rate_code ]['delay'] );
		}
		$time      = strtotime( $rate_delivery ) + ( $delay * 86400 );
		$delivery  = date( 'Y/m/d', $time );
		$rate_name .= " (Delivery Date: $delivery) ";
		if ( ! empty( $this->custom_services[ $rate_code ]['name'] ) ) {
			$rate_name = str_replace( '[RapidPlugin-DELIVERY]', $delivery, $this->custom_services[ $rate_code ]['name'] );
		}
		if ( ! empty( $this->custom_services[ $rate_code ]['adjustment_percent'] ) && ! empty( $this->custom_services[ $rate_code ]['adjustment_type2'] ) ) {
			$type = $this->custom_services[ $rate_code ]['adjustment_type2'];
			if ( $type == 1 ) {
				$rate_cost = $rate_cost + ( $rate_cost * ( floatval( $this->custom_services[ $rate_code ]['adjustment_percent'] ) / 100 ) );
			} else {
				$rate_cost = $rate_cost - ( $rate_cost * ( floatval( $this->custom_services[ $rate_code ]['adjustment_percent'] ) / 100 ) );
			}
		}
		if ( ! empty( $this->custom_services[ $rate_code ]['adjustment'] ) && ! empty( $this->custom_services[ $rate_code ]['adjustment_type'] ) ) {
			$type = $this->custom_services[ $rate_code ]['adjustment_type'];
			if ( $type == 1 ) {
				$rate_cost = $rate_cost + floatval( $this->custom_services[ $rate_code ]['adjustment'] );
			} else {
				$rate_cost = $rate_cost - floatval( $this->custom_services[ $rate_code ]['adjustment'] );
			}
		}
		if ( isset( $this->custom_services[ $rate_code ] ) && empty( $this->custom_services[ $rate_code ]['enabled'] ) ) {
			$this->debug( "$rate_code was disabled!" );

			return false;
		}
		if ( ! in_array( $rate_code, $this->activeLevels ) ) {
			$this->debug( "$rate_code was not accepted!" );

			return false;
		}
		$package = $this->package;
		if ( ! $package['contents'] ) {
			$this->debug( "Cart is Empty!" );

			return false;
		}
		if ( isset( $this->custom_services[ $rate_code ]['order'] ) ) {
			$sort = $this->custom_services[ $rate_code ]['order'];
		} else {
			$sort = 999;
		}
		$this->found_rates[ $rate_id ] = [
			'id'        => $rate_id,
			'label'     => $rate_name,
			'cost'      => $rate_cost,
			'sort'      => $sort,
			'packages'  => 1,
			'meta_data' => [
				'DayAndRoss_type' => $rate_code,
				'DayAndRoss_id'   => $this->instance_id
			]
		];
	}

	public function add_found_rates() {
		if ( $this->found_rates ) {
			uasort( $this->found_rates, array( $this, $this->sort ) );
			foreach ( $this->found_rates as $key => $rate ) {
				$this->add_rate( $rate );
			}
		}
	}

	public function adminSort( $a, $b ) {
		if ( $a['sort'] == $b['sort'] ) {
			return 0;
		}

		return ( $a['sort'] < $b['sort'] ) ? - 1 : 1;
	}

	public function PriceBaseSort( $a, $b ) {
		if ( $a['cost'] == $b['cost'] ) {
			return 0;
		}

		return ( $a['cost'] < $b['cost'] ) ? - 1 : 1;
	}
}