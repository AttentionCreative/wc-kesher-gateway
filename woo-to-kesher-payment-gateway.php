<?php
/**
 * Plugin Name: WooCommerce Kesher Gateway
 * Plugin URI: https://github.com/AttentionCreative/wc-kesher-gateway
 * Update URI: https://github.com/AttentionCreative/wc-kesher-gateway
 * Description: תוסף זה נועד לשלב תשלום קשר ב-WooCommerce.
 * Version: 1.0.3
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
 * שילוב מערכת העדכונים של וורדפרס
 */
add_filter('pre_set_site_transient_update_plugins', 'kesher_check_for_wp_update');
add_filter('plugins_api', 'kesher_plugin_info', 10, 3);

function kesher_check_for_wp_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $remote_version = kesher_get_remote_version();
    $local_version  = kesher_get_local_version();

    if ($remote_version && version_compare($local_version, $remote_version, '<')) {

        $plugin_data = (object) [
            'slug'        => dirname(KESHER_PLUGIN_SLUG),
            'plugin'      => KESHER_PLUGIN_SLUG,
            'new_version' => $remote_version,
            'package'     => kesher_get_download_url(), // הורדה מה-main תמיד
            'url'         => "https://github.com/" . KESHER_REPO_OWNER . "/" . KESHER_REPO_NAME,
        ];

        $transient->response[KESHER_PLUGIN_SLUG] = $plugin_data;
    }

    return $transient;
}


/**
 * הצגת פרטי העדכון בעמוד Plugins
 */
function kesher_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== dirname(KESHER_PLUGIN_SLUG)) {
        return $res;
    }

    $remote_version = kesher_get_remote_version();

    if (!$remote_version) {
        return $res;
    }

    $res = (object) [
        'name'          => 'WooCommerce Kesher Gateway',
        'slug'          => dirname(KESHER_PLUGIN_SLUG),
        'version'       => $remote_version,
        'author'        => 'Attention Creative',
        'homepage'      => "https://github.com/" . KESHER_REPO_OWNER . "/" . KESHER_REPO_NAME,
        'download_link' => kesher_get_download_url(),
        'tested'        => '6.3',
        'requires'      => '5.2',
        'requires_php'  => '7.2',
    ];

    return $res;
}

/**
 * קבלת גרסה מקומית
 */
function kesher_get_local_version(): string {
    $plugin_data = get_file_data(KESHER_PLUGIN_FILE, ['Version' => 'Version']);
    return $plugin_data['Version'];
}


/**
 * @return string|false
 */
function kesher_get_remote_version(){
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

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['tag_name'])) {
        return false;
    }

    return str_replace('v', '', $data['tag_name']);
}
/**
 * @return string|false
 */
function kesher_get_download_url()
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
        error_log('Error fetching download URL: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    error_log('Download URL Response: ' . print_r($data, true));

    if (!isset($data['assets'][0]['browser_download_url'])) {
        error_log('No download URL found in response.');
        return false;
    }

    $download_url = $data['assets'][0]['browser_download_url'];

    // וידוא שהקובץ הוא בשם הנכון
    if (strpos($download_url, 'wc-kesher-gateway.zip') === false)
    error_log('Download URL does not match expected filename.');
        return false;
    }

    return $download_url;
}



add_action('admin_init', function () {
    if (isset($_GET['check_kesher_update'])) {
        $local_version  = kesher_get_local_version();
        $remote_version = kesher_get_remote_version();
        $download_url   = kesher_get_download_url();

        header('Content-Type: application/json');
        echo json_encode([
            'local_version' => $local_version,
            'remote_version' => $remote_version,
            'download_url' => $download_url,
        ]);

        exit;
    }
});





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
