<?php
global $rcp_options;
$message = ! empty( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : '';

if( empty( $message ) ) {
	$message = __( 'This content is restricted to subscribers', 'rcp' );
}
?>

<div class="rcp-woocommerce-no-access">
	<?php echo rcp_format_teaser( $message ); ?>
</div>