<?php
defined( 'ABSPATH' ) || exit;

/** Evidence-only cross-module advice. It never mutates settings itself. */
final class BaoCache_Optimization_Advisor {
	/** @return array<int, array<string, mixed>> */
	public static function recommendations(): array {
		$items = array();
		$add = static function ( string $risk, string $title, string $mechanism, string $evidence, string $scope, string $tab, string $action ) use ( &$items ): void {
			$items[] = array( 'risk' => $risk, 'title' => $title, 'mechanism' => $mechanism, 'evidence' => $evidence, 'scope' => $scope, 'tab' => $tab, 'action' => $action );
		};
		$qa = get_option( 'baocache_compatibility_qa', array() );
		$checks = is_array( $qa ) && is_array( $qa['checks'] ?? null ) ? $qa['checks'] : array();
		$qa_passed = ! empty( $checks ) && ! in_array( 'fail', $checks, true ) && ! in_array( 'pending', $checks, true );
		if ( ! $qa_passed ) $add( 'high', __( 'Hoàn tất Staging Compatibility QA', 'baocache' ), __( 'Gate production giữ nguyên cho đến khi QA và rollback được ghi nhận.', 'baocache' ), __( 'Checklist staging chưa PASS đầy đủ.', 'baocache' ), __( 'Login, checkout, menu, form, analytics và rollback.', 'baocache' ), 'dashboard', __( 'Mở QA', 'baocache' ) );

		$hints = BaoCache_Resource_Hints::snapshot();
		if ( ! empty( $hints['candidates'] ) ) $add( 'low', __( 'Xem Resource & Font Hints', 'baocache' ), __( 'Preconnect/preload có giới hạn từ Asset Inventory.', 'baocache' ), sprintf( _n( '%d candidate với fingerprint.', '%d candidates với fingerprint.', count( (array) $hints['candidates'] ), 'baocache' ), count( (array) $hints['candidates'] ) ), __( 'Frontend resources; apply/rollback tại module gốc.', 'baocache' ), 'resources', __( 'Mở Resource Hints', 'baocache' ) );

		$third_party = BaoCache_Third_Party_Optimizer::snapshot();
		if ( ! empty( $third_party['candidates'] ) ) $add( 'medium', __( 'Rà soát Third-party Optimizer', 'baocache' ), __( 'Delay chỉ cho script ngoài domain, độc lập và không nhạy cảm.', 'baocache' ), sprintf( _n( '%d candidate sau dependency/risk filter.', '%d candidates sau dependency/risk filter.', count( (array) $third_party['candidates'] ), 'baocache' ), count( (array) $third_party['candidates'] ) ), __( 'Staging trước; apply/rollback tại module gốc.', 'baocache' ), 'assets', __( 'Mở Third-party', 'baocache' ) );

		$commerce = BaoCache_Commerce_Optimizer::snapshot();
		if ( ! empty( $commerce['protected_routes'] ) ) $add( 'medium', __( 'Xác minh Commerce protection', 'baocache' ), __( 'Chỉ thêm route WooCommerce có metadata vào render-blocking exclusion.', 'baocache' ), sprintf( _n( '%d protected route đã xác minh.', '%d protected routes đã xác minh.', count( (array) $commerce['protected_routes'] ), 'baocache' ), count( (array) $commerce['protected_routes'] ) ), __( 'Cart, checkout, account; rollback stale-safe.', 'baocache' ), 'assets', __( 'Mở Commerce', 'baocache' ) );

		$adapters = BaoCache_Theme_Builder_Adapters::snapshot();
		if ( ! empty( $adapters['excluded_handles'] ) ) $add( 'low', __( 'Xác minh Theme & Builder adapters', 'baocache' ), __( 'Chỉ bảo vệ Blocksy/Elementor/Bricks handle đã quan sát.', 'baocache' ), sprintf( _n( '%d observed handle.', '%d observed handles.', count( (array) $adapters['excluded_handles'] ), 'baocache' ), count( (array) $adapters['excluded_handles'] ) ), __( 'Không adapter nào là điều kiện cho core; rollback stale-safe.', 'baocache' ), 'assets', __( 'Mở Adapters', 'baocache' ) );

		$cloudflare = BaoCache_Cloudflare::configuration();
		if ( ! empty( $cloudflare['configured'] ) && empty( $cloudflare['purge_enabled'] ) ) $add( 'info', __( 'Cloudflare exact URL purge đang khóa', 'baocache' ), __( 'Purge chỉ mở với token Cache Purge riêng và Coolify flag.', 'baocache' ), __( 'Audit token đã cấu hình; purge boundary chưa đủ.', 'baocache' ), __( 'Một URL cùng domain; không all/host/prefix/tag purge.', 'baocache' ), 'cloudflare', __( 'Mở Cloudflare', 'baocache' ) );

		$order = array( 'high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3 );
		usort( $items, static fn( array $a, array $b ): int => $order[ $a['risk'] ] <=> $order[ $b['risk'] ] );
		return array_slice( $items, 0, 6 );
	}
}
