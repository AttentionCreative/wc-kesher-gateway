<?php
/**
 * Plugin Name: WooCommerce Kesher Gateway
 * Plugin URI: https://github.com/AttentionCreative/wc-kesher-gateway
 * Update URI: https://github.com/AttentionCreative/wc-kesher-gateway
 * Description: תוסף זה נועד לשלב תשלום קשר ב-WooCommerce.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Attention Creative
 * Author URI: https://attention.co.il
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-kesher-gateway
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit('restricted access');
}

// קבועים
define('KESHER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KESHER_PLUGIN_FILE', __FILE__);
define('KESHER_PLUGIN_SLUG', plugin_basename(__FILE__));
define('KESHER_REPO_OWNER', 'AttentionCreative');
define('KESHER_REPO_NAME', 'wc-kesher-gateway');


if (! defined('KESHER_PLUGIN_URL')) {
    define('KESHER_PLUGIN_URL', plugin_dir_url(KESHER_PLUGIN_FILE));
}

if (!defined('KESHER_PAYMENT_URL')) {
    define('KESHER_PAYMENT_URL', 'https://kesherhk.info/ConnectToKesher/ConnectToKesher');
}


// אם יש טוקן, נשתמש בו. אם לא - נשאיר ריק.
define('KESHER_GITHUB_TOKEN', defined('ATTENTION_GITHUB_TOKEN') ? ATTENTION_GITHUB_TOKEN : '');

/**
 * בדיקה יזומה של עדכון דרך URL
 */
add_action('admin_init', function () {
    if (isset($_GET['check_update_kesher'])) {
        kesher_check_for_update();
        exit;
    }
});

/**
 * בדיקת עדכון
 */
function kesher_check_for_update()
{
    $local_version = kesher_get_local_version();
    $remote_version = kesher_get_remote_version();

    echo "<h2>Current Version: " . $local_version . "</h2>";
    echo "<h2>Remote Version: " . $remote_version . "</h2>";

    if (!$remote_version) {
        echo "<h2>No remote version found.</h2>";
        return;
    }

    if (version_compare($local_version, $remote_version, '<')) {
        echo "<h2>Update Available. Updating...</h2>";
        kesher_update_plugin();
    } else {
        echo "<h2>No update available.</h2>";
    }
}

/**
 * קבלת גרסה מקומית
 */
function kesher_get_local_version(): string
{
    $plugin_data = get_file_data(KESHER_PLUGIN_FILE, ['Version' => 'Version']);
    return $plugin_data['Version'];
}

/**
 * קבלת גרסה מרוחקת
 */
function kesher_get_remote_version(): string|false
{
    $url = "https://api.github.com/repos/" . KESHER_REPO_OWNER . "/" . KESHER_REPO_NAME . "/releases/latest";
    
    $args = [
        'headers' => [
            'User-Agent' => 'WooCommerce-Kesher-Gateway-Updater',
        ]
    ];

    if (KESHER_GITHUB_TOKEN) {
        $args['headers']['Authorization'] = 'Bearer ' . KESHER_GITHUB_TOKEN;
    }

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        error_log('Error fetching remote version: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['tag_name'])) {
        return false;
    }

    return str_replace('v', '', $data['tag_name']);
}

/**
 * קבלת URL להורדה
 */
function kesher_get_download_url(): string|false
{
    $url = "https://api.github.com/repos/" . KESHER_REPO_OWNER . "/" . KESHER_REPO_NAME . "/releases/latest";
    
    $args = [
        'headers' => [
            'User-Agent' => 'WooCommerce-Kesher-Gateway-Updater',
        ]
    ];

    if (KESHER_GITHUB_TOKEN) {
        $args['headers']['Authorization'] = 'Bearer ' . KESHER_GITHUB_TOKEN;
    }

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($data['assets'][0]['browser_download_url'])) {
        return false;
    }

    return $data['assets'][0]['browser_download_url'];
}

/**
 * עדכון התוסף
 */
function kesher_update_plugin()
{
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $download_url = kesher_get_download_url();
    if (!$download_url) {
        error_log('Download URL not found.');
        return;
    }

    $tmp_file = get_temp_dir() . 'wc-kesher-gateway.zip';
    $plugin_dir = WP_PLUGIN_DIR . '/wc-kesher-gateway';

    $args = [
        'headers' => [
            'User-Agent' => 'WooCommerce-Kesher-Gateway-Updater',
        ]
    ];

    if (KESHER_GITHUB_TOKEN) {
        $args['headers']['Authorization'] = 'Bearer ' . KESHER_GITHUB_TOKEN;
    }

    $response = wp_remote_get($download_url, $args);

    if (is_wp_error($response)) {
        error_log('Error downloading update: ' . $response->get_error_message());
        return;
    }

    file_put_contents($tmp_file, wp_remote_retrieve_body($response));

    // מחיקת התיקייה הקיימת
    if (is_dir($plugin_dir)) {
        kesher_recursive_delete($plugin_dir);
    }

    // חילוץ הקובץ
    $unzip_result = unzip_file($tmp_file, WP_PLUGIN_DIR);
    if (is_wp_error($unzip_result)) {
        error_log('Error extracting ZIP: ' . $unzip_result->get_error_message());
        unlink($tmp_file);
        return;
    }

    // שינוי שם התיקייה
    $unzipped_dirs = glob(WP_PLUGIN_DIR . '/AttentionCreative-*', GLOB_ONLYDIR);
    if (!empty($unzipped_dirs)) {
        rename($unzipped_dirs[0], $plugin_dir);
    }

    // ניקוי קובץ ה-ZIP
    unlink($tmp_file);
}

/**
 * פונקציה למחיקת תיקייה
 */
function kesher_recursive_delete($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            kesher_recursive_delete($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
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
