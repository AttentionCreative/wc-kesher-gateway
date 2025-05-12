<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Bit_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'kesher_bit_transaction';
        $this->method_title = __('Kesher Bit Payment Gateway', 'kp-kesher-gateway');
        $this->method_description = __('Process payments via Bit transactions.', 'kp-kesher-gateway');
        $this->has_fields = true;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('הפעל/השבת', 'kp-kesher-gateway'),
                'type' => 'checkbox',
                'label' => __('אפשר תשלום באמצעות ביט של קשר', 'kp-kesher-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('כותרת', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('כותרת זו תוצג ללקוח בתהליך התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום באמצעות ביט - קשר', 'kp-kesher-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('תיאור', 'kp-kesher-gateway'),
                'type' => 'textarea',
                'description' => __('תיאור זה יוצג ללקוח בתהליך התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום דרך קשר באמצעות אפליקציית ביט.', 'kp-kesher-gateway'),
            ),
            'username' => array(
                'title' => __('שם משתמש', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('שם המשתמש שלך לממשק ה-API של קשר.', 'kp-kesher-gateway'),
                'default' => '',
            ),
            'password' => array(
                'title' => __('סיסמה', 'kp-kesher-gateway'),
                'type' => 'password',
                'description' => __('הסיסמה שלך לממשק ה-API של קשר.', 'kp-kesher-gateway'),
                'default' => '',
            ),
            'projectnumber' => array(
                'title' => __('מספר פרויקט', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('מספר הפרויקט במערכת קשר.', 'kp-kesher-gateway'),
                'default' => '',
            ),
        );
    }

    public function payment_fields()
    {
?>
<div id="kesher-credit-card-fields" style="display: flex; gap: 15px; align-items: center;">
    <div class="form-row" style="flex: 1;">
        <label for="kesher_govt_id">
            <?php echo esc_html__('תעודת זהות', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" name="kesher_govt_id" id="kesher_govt_id" style="width: 100%;" />
    </div>

    <div class="form-row" style="flex: 1;">
        <label for="kesher_bit_mobile_number">
            <?php echo esc_html__('מספר נייד', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" maxlength="12" name="kesher_bit_mobile_number"
            id="kesher_bit_mobile_number" required style="width: 100%;" />
    </div>
</div>

<?php
    }

    public function process_payment($order_id)
    {
        global $currency_values;

        $order = wc_get_order($order_id);
        $order_total        = $order->get_total();
        $total              = intval($order_total) * 100;
        $billing_phone      = $order->get_billing_phone();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();
        $billing_email      = $order->get_billing_email();
        $billing_address_1  = $order->get_billing_address_1();
        $billing_city       = $order->get_billing_city();
        $currency_code      = get_woocommerce_currency();

        $kesher_govt_id = isset($_POST['kesher_govt_id']) ? wc_clean($_POST['kesher_govt_id']) : '';
        $mobile_number = isset($_POST['kesher_bit_mobile_number']) ? wc_clean($_POST['kesher_bit_mobile_number']) : '';


        $custom_currency_numeric_value = $currency_values[$currency_code];

        $request_body = '{
            "Json": {
                "userName": "' . $this->settings['username'] . '",
                "password": "' . $this->settings['password'] . '",
                "func": "SendBitTransaction",
                "format": "json",
                "transaction": {
                    "Address": "' . $billing_address_1 . '", 
                    "City": "' . $billing_city . '",
                    "Total": "' . $total . '",
                    "Currency": "' . $custom_currency_numeric_value . '",
                    "CreditType": 1,
                    "Phone": "' . $mobile_number . '",
                    "ParamJ": "J4",
                    "TransactionType": "debit",
                    "FirstName": "' . $billing_first_name . '",
                    "LastName": "' . $billing_last_name . '",
                    "ProjectNumber": "' . $this->settings['projectnumber'] . '",
                    "Mail": "' . $billing_email . '",
                    "Moked": "From website",
                    "Id":"' . $kesher_govt_id . '"
                }
            },
            "format": "json"
        }';

        $response = wp_remote_post(
            KESHER_PAYMENT_URL,
            array(
                'body' => $request_body,
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 120
            )
        );
        keser_plugin_log('Bit Payment Response: ' . print_r($response, true));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
        } else {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($response_body['RequestResult']['Code'], $response_body['RequestResult']['Status'])) {

                $order->update_status('pending', 'Waiting for manual confirmation of payment.');
                if (isset($response_body['NumTransaction'])) {
                    update_post_meta($order_id, '_kesher_transaction_number', $response_body['NumTransaction']);

                    $this->get_tran_data($order_id, $response_body['NumTransaction']);
                }
                $order->save();
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                $error_message = $response_body['RequestResult']['Description'] ?? __('Unknown error occurred.', 'kp-kesher-gateway');
                wc_add_notice('Error: ' . $response_body['RequestResult']['Description'] ?? 'Unknown Error', 'error');
                return;
            }
        }
    }

    public function get_tran_data($order_id, $transaction_id)
    {

        $args = '{
                    "Json": {
                    "userName": "' . $this->settings['username'] . '",
                    "password": "' . $this->settings['password'] . '",
                    "func": "GetTranData",
                    "transactionNum": "' . $transaction_id . '"
                },
                "format": "json"
            }';

        $response = wp_remote_post(KESHER_PAYMENT_URL, array(
            'body' => $args,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 120
        ));

        $data = json_decode(wp_remote_retrieve_body($response), true);

        update_post_meta($order_id, '_kesherhk_SendBitTransaction_transactionNum', $data);
        return true;
    }
}