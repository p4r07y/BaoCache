<?php
defined( 'ABSPATH' ) || exit;

final class BaoCache_Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		( new BaoCache_Runtime() )->register();
		( new BaoCache_Frontend_Metrics() )->register();
		( new BaoCache_Warmup() )->register();
		( new BaoCache_Metrics() )->register();
		( new BaoCache_Purge() )->register();
		( new BaoCache_Analytics() )->register();
		( new BaoCache_CSP() )->register();
		// The admin controller also owns the sanitized probe cron callback. It is
		// registered on cron requests too; its admin assets/menu remain hook-gated.
		( new BaoCache_Admin() )->register();
	}
}
