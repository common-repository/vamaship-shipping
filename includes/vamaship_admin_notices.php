<?php
class Vamamship_message {
	private $_message;

	public function __construct( $message = '' ) {
		$this->_message = $message;
		if ( $this->_message !== '' ) {
			add_action( 'admin_notices', array( $this, 'vamaship_mes_fun' ) );
		}
	}

	public function vamaship_mes_fun() {
		?>
		<div class="notice notice-warning is-dismissible"><p>
		<?php
		printf( '%s', esc_html( $this->_message ) );
		?>
		</p></div>
		<?php
		$this->_message = '';
	}
}
