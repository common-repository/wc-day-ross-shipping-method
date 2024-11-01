<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'    => array(
		'title'       => __( 'Method Title', 'RapidPlugin-DayAndRoss' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'RapidPlugin-DayAndRoss' ),
		'default'     => __( 'DayAndRoss', 'RapidPlugin-DayAndRoss' ),
		'desc_tip'    => true
	),
	'rates'    => array(
		'title'       => __( 'Rates and Services', 'RapidPlugin-DayAndRoss' ),
		'type'        => 'title',
		'description' => __( 'The following settings determine the rates you offer your customers.', 'RapidPlugin-DayAndRoss' ),
	),
	'services' => array(
		'type' => 'services'
	),
);
