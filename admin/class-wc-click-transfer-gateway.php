<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Click_Transfer_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'kesher_cash_transaction';
        $this->method_title = __('תשלום באמצעות העברה בקליק', 'kp-kesher-gateway');
        $this->method_description = __('תשלום באתר באמצעות העברה בנקאית', 'kp-kesher-gateway');
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
                'label' => __('אפשר תשלום באמצעות העברה בקליק - קשר', 'kp-kesher-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('כותרת', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('כותרת זו תוצג ללקוח בעמוד התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום באמצעות העברה בקליק - קשר', 'kp-kesher-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('תיאור', 'kp-kesher-gateway'),
                'type' => 'textarea',
                'description' => __('תיאור זה יוצג ללקוח בעמוד התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום באמצעות העברה בקליק דרך קשר.', 'kp-kesher-gateway'),
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
                'description' => __('מספר פרויקט במערכת קשר.', 'kp-kesher-gateway'),
                'default' => '',
            ),
        );
    }
    public function payment_fields()
    {
?>
<div id="kesher-credit-card-fields" style="display: grid; grid-template-columns: repeat(2, 1fr);">
    <div class="form-row">
        <label for="cash_banknumber">
            <?php echo esc_html__('בנק', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" name="cash_banknumber" id="cash_banknumber" style="width: 100%;"
            required />
    </div>

    <div class="form-row">
        <label for="cash_branchnumber">
            <?php echo esc_html__('סניף', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" name="cash_branchnumber" id="cash_branchnumber" style="width: 100%;"
            required />
    </div>

    <div class="form-row">
        <label for="cash_accountnumber">
            <?php echo esc_html__('מספר חשבון', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" name="cash_accountnumber" id="cash_accountnumber" style="width: 100%;"
            required />
    </div>

    <div class="form-row">
        <label for="kesher_govt_id">
            <?php echo esc_html__('תעודת זהות', 'kp-kesher-gateway'); ?> <span class="required">*</span>
        </label>
        <input type="number" class="input-text" name="kesher_govt_id" id="kesher_govt_id" style="width: 100%;" />
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
        $cash_accountnumber = isset($_POST['cash_accountnumber']) ? wc_clean($_POST['cash_accountnumber']) : '';
        $cash_banknumber    = isset($_POST['cash_banknumber']) ? wc_clean($_POST['cash_banknumber']) : '';
        $cash_branchnumber  = isset($_POST['cash_branchnumber']) ? wc_clean($_POST['cash_branchnumber']) : '';
        $kesher_govt_id  = isset($_POST['kesher_govt_id']) ? wc_clean($_POST['kesher_govt_id']) : '';

        $custom_currency_numeric_value = $currency_values[$currency_code];

        $request_body = json_encode([
            'Json' => [
                'userName' => $this->settings['username'],
                'password' => $this->settings['password'],
                'func' => 'SendFastBankTransfer',
                'format' => 'json',
                'payment' => [
                    'Total' => $total, // total in pennies
                    'Bank' => $cash_banknumber,
                    'Branch' => $cash_branchnumber,
                    'Account' => $cash_accountnumber,
                    'Id' => $kesher_govt_id, // Government ID
                    'TransferReason' => 'Salary Payment', // fixed correct text!
                    'Currency' => 1, // always 1
                    'Name' => '', // required even if empty
                    'Phone' => $billing_phone,
                    'Mail' => $billing_email,
                    'Address' => $billing_address_1,
                    'City' => $billing_city,
                    'ReceiptName' => '',
                    'ReceiptFor' => '',
                    'Details' => '',
                    'NumHouse' => '',
                    'FirstName' => $billing_first_name,
                    'LastName' => $billing_last_name,
                    'ApartmentNumber' => 0,
                    'Entrance' => '',
                    'Phone2' => '',
                    'Floor' => '',
                    'Country' => ''
                ]
            ],
            'format' => 'json'
        ]);
        keser_plugin_log('Send Payment Request Body: ' . json_encode($request_body));

        $response = wp_remote_post(KESHER_PAYMENT_URL, array(
            'body' => $request_body,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 120
        ));

        keser_plugin_log('Send Payment Response: ' . json_encode($response));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
        } else {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($response_body['Success']) && $response_body['Success'] == 1) {
                $order->update_status('pending', 'Waiting for manual confirmation of payment.');

                if (isset($response_body['NumTransaction'])) {
                    update_post_meta($order_id, '_kesher_transaction_number', $response_body['NumTransaction']);
                    error_log('Saved transaction number: ' . $response_body['NumTransaction']);

                    $this->get_tran_data($order_id, $response_body['NumTransaction']);
                }

                $order->save();
                WC()->cart->empty_cart();

                if (isset($response_body['Link'])) {
                    // Redirect user directly to the payment Link
                    return array(
                        'result' => 'success',
                        'redirect' => $response_body['Link']
                    );
                } else {
                    // Fallback - if no Link, return to normal thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                }
            } else {
                $error_message = $response_body['Message'] ?? __('Unknown error occurred.', 'kp-kesher-gateway');
                error_log('WC_Click_Transfer_Gateway: Payment API Error - ' . $error_message);
                wc_add_notice('Error: ' . $error_message, 'error');
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
        update_post_meta($order_id, '_kesherhk_SendCashTransaction_transactionNum', $data);
        return true;
    }
}