<?php
// Compile emoji data list into an autocompleteable list

$contents = file_get_contents( 'https://raw.githubusercontent.com/iamcal/emoji-data/master/emoji.json' );
file_put_contents( dirname( __DIR__ ) . '/static/emoji-raw.json', $contents );

$data = json_decode( $contents );
$map = array();

$modifier_file = file_get_contents( dirname( __DIR__ ) . '/static/emoji-modifier-base.json' );
$modifier_obj = json_decode( $modifier_file );

$modifier_base_array = $modifier_obj->modifier_base;
$modifiers_array = $modifier_obj->modifiers;

$categories = array( 'People', 'Nature', 'Foods', 'Activity', 'Places', 'Objects', 'Symbols', 'Flags' );

foreach ( $data as $emoji ) {
	// Exclude any not supported by Twemoji
	if ( empty( $emoji->has_img_twitter ) ) {
		continue;
	}

	$category = array_search( $emoji->category, $categories );
	if ( false === $category ) {
		if ( 0 === strpos( $emoji->short_name, 'flag-' ) ) {
			$category = 7;
		} else {
			$category = 100;
		}
	}

	$code = "0x" . $emoji->unified;
	$code = str_replace( '-', "-0x", $code );
	$code = explode( '-', $code );

	if( array_intersect( $code, $modifier_base_array ) && count( $code ) === 1 ){
		foreach( $modifiers_array as $modifier ){
				$code_array = $code;
				$code_array[] = $modifier;
				$map[ $category ][] = array(
					'code'       => $code_array,
					'sort_order' => $emoji->sort_order,
				);
		}
	}
	else{
		$map[ $category ][] = array(
			'code'       => $code,
			'sort_order' => $emoji->sort_order,
		);
	}
}

ksort( $map );

foreach ( $map as $category => $emoji_list ) {
	usort( $map[ $category ], function( $a, $b ) {
		if ( $a['sort_order'] == $b['sort_order'] ) {
			return 0;
		}

		return ( $a['sort_order'] < $b['sort_order'] ) ? -1 : 1;
	} );

	foreach ( $map[ $category ] as $id => $emoji ) {
		$map[ $category ][ $id ] = $emoji['code'];
	}
}

file_put_contents( dirname( __DIR__ ) . '/static/emoji.json', json_encode( $map ) );
