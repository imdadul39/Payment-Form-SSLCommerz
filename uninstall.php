<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
global $wpdb;
$table_name = $wpdb->prefix . 'sslcommerz_payments';
$wpdb->query("DROP TABLE IF EXIsTS $table_name");
