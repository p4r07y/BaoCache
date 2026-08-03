<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-uninstall-manager.php';
BaoCache_Uninstall_Manager::uninstall();
