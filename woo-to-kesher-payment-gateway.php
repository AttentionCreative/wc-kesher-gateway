<?php

/**
 * Plugin Name:       תוסף תשלום קשר פתרונות גביה וסליקת אשראי לווקומרס
 * Plugin URI:        https://github.com/AttentionCreative/wc-kesher-gateway
 * Description:       תוסף זה נועד לשלב שלוש שיטות תשלום שונות ב-WooCommerce ולהבטיח אינטראקציה חלקה עם מערכות תשלום של קשר.
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Attention Creative
 * Author URI:        https://attention.co.il
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/AttentionCreative/wc-kesher-gateway
 * Text Domain:       kp-kesher-gateway
 * Domain Path:       /languages
 */


if (! defined('ABSPATH')) {
    exit('restricted access');
}

define('KESHER_VERSION', '1.0.1');

if (! defined('KESHER_ADMIN_URL')) {
    define('KESHER_ADMIN_URL', get_admin_url());
}

if (! defined('KESHER_PLUGIN_FILE')) {
    define('KESHER_PLUGIN_FILE', __FILE__);
}

if (! defined('KESHER_PLUGIN_PATH')) {
    define('KESHER_PLUGIN_PATH', plugin_dir_path(KESHER_PLUGIN_FILE));
}

if (! defined('KESHER_PLUGIN_URL')) {
    define('KESHER_PLUGIN_URL', plugin_dir_url(KESHER_PLUGIN_FILE));
}

if (!defined('KESHER_PAYMENT_URL')) {
    define('KESHER_PAYMENT_URL', 'https://kesherhk.info/ConnectToKesher/ConnectToKesher');
}

add_action('plugins_loaded', 'init_custom_payment_gateways');
function init_custom_payment_gateways()
{

    require_once KESHER_PLUGIN_PATH . "/admin/class-wc-credit-card-gateway.php";
    require_once KESHER_PLUGIN_PATH . "/admin/class-wc-bit-gateway.php";
    require_once KESHER_PLUGIN_PATH . "/admin/class-wc-click-transfer-gateway.php";
    require_once KESHER_PLUGIN_PATH . "/admin/class-cronjob.php";

    add_filter('woocommerce_payment_gateways', 'kesher_custom_payment_gateways');
    function kesher_custom_payment_gateways($gateways)
    {
        $gateways[] = 'WC_Credit_Card_Gateway'; // Regular Credit Card
        $gateways[] = 'WC_Bit_Gateway'; // Bit Payment
        $gateways[] = 'WC_Click_Transfer_Gateway'; // Click Transfer
        return $gateways;
    }
}

// Check Allowed Currencies
add_filter('woocommerce_available_payment_gateways', 'kp_available_gateways');
function kp_available_gateways($gateways)
{
    $currency_code = get_woocommerce_currency();

    if (isset($gateways['bit_gateway']) && $currency_code !== 'USD') {
        unset($gateways['bit_gateway']);
    }

    $allowed_currencies = ['ILS', 'USD', 'GBP', 'EUR'];

    if (!in_array($currency_code, $allowed_currencies, true)) {
        unset($gateways['bit_gateway']);
        unset($gateways['click_transfer_gateway']);
        unset($gateways['credit_card_gateway']);
    }

    return $gateways;
}

//Global Varible Define
global $currency_values;
$currency_values = array(
    'ILS' => 1,
    'USD' => 2,
    'GBP' => 826,
    'EUR' => 978
);

function keser_plugin_log($message, $level = 'info')
{
    if (empty($message) || trim($message) === '2') {
        return;
    }

    if (class_exists('WC_Logger')) {
        $logger = wc_get_logger();
        $context = ['source' => 'kesher_gateway'];
        $formatted_message = date('Y-m-d H:i:s') . " " . print_r($message, true);

        $logger->log($level, $formatted_message, $context);
    }
}
