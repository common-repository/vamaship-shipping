(function( $ ) {
	'use strict';

	$( document ).ready(
		function() {

			if (vamaship_shipping_bulk_shipment.bulk_action == true) {
				$( '<option>' ).val( 'generate_shipments_vamaship' ).text( vamaship_shipping_bulk_shipment.action_text1 ).appendTo( "select[name='action']" );
				$( '<option>' ).val('generate_shipments_air_domestic').text(vamaship_shipping_bulk_shipment.action_text2).appendTo("select[name='action']");
				$( '<option>' ).val('generate_shipments_surface_b2c').text(vamaship_shipping_bulk_shipment.action_text3).appendTo("select[name='action']");
				$( '<option>' ).val('generate_shipments_surface_b2b').text(vamaship_shipping_bulk_shipment.action_text4).appendTo("select[name='action']");
				$( '<option>' ).val('generate_shipments_air_internatoinal').text(vamaship_shipping_bulk_shipment.action_text5).appendTo("select[name='action']");
			}
			$( document ).on(
				'change', '.vmp_recal_ship_values',function(){
					// e.preventDefault();
					var max_length = $( '.vmp_max_lenght' ).val();
					var max_width  = $( '.vmp_max_width' ).val();
					var max_height = $( '.vmp_max_height' ).val();
					var max_weight = $( '.vmp_max_weight' ).val();

					var url = $( location ). attr( "href" );
					url     = url.substring( 0, url.indexOf( "edit" ) ) + 'edit&format=get_reshipment_details&max_length=' + max_length + '&max_weight=' + max_weight + '&max_height=' + max_height + '&max_width=' + max_width;
					$( '.vmp_update_shipping_cost' ).attr( 'href', url );

				}
			);
		}
	);

})( jQuery );
