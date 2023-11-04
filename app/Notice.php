<?php

namespace BeycanPress\BinancePay;

class Notice {
	
    /**
     * @param string $level
     * @param string $message
     * @param boolean $dismissible
     * @return void
     */
	public static function addNotice(string $level, string $message, bool $dismissible = false) : void {
		add_action(
			'admin_notices',
			function () use ($level, $message, $dismissible) {
				$dismiss = $dismissible ? ' is-dismissible' : '';
				?>
				<div class="notice notice-<?php echo esc_attr($level) . esc_attr($dismiss); ?>" style="padding:12px 12px">
					<?php echo '<strong>BinancePay Server:</strong> ' . esc_html__($message, 'binance_pay_gateway') ?>
				</div>
				<?php
			}
		);
	}
}
