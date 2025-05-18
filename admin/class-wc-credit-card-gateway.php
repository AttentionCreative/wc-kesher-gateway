<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Credit_Card_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {

        $this->id = 'kesher_credit_card';
        $this->method_title = __('תשלום באמצעות כרטיס אשראי קשר', 'kp-kesher-gateway');
        $this->method_description = __('תשלום באמצעות כרטיס אשראי קשר', 'kp-kesher-gateway');
        $this->has_fields = true;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('הפעל/השבת', 'kp-kesher-gateway'),
                'type' => 'checkbox',
                'label' => __('אפשר תשלום באמצעות כרטיס אשראי של קשר', 'kp-kesher-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('כותרת', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('כותרת זו תוצג ללקוח בעמוד התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום בכרטיס אשראי - קשר', 'kp-kesher-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('תיאור', 'kp-kesher-gateway'),
                'type' => 'textarea',
                'description' => __('תיאור זה יוצג ללקוח בתהליך התשלום.', 'kp-kesher-gateway'),
                'default' => __('תשלום מאובטח דרך קשר באמצעות כרטיס אשראי.', 'kp-kesher-gateway'),
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
            'installments' => array(
                'title' => __('הפעלה/כיבוי תשלומים', 'kp-kesher-gateway'),
                'type' => 'checkbox',
                'description' => __('סמן להפעלת אפשרות לתשלומים', 'kp-kesher-gateway'),
                'default' => 'no',
            ),
            'ins_rules' => array(
                'title' => __('חוקי תשלומים', 'kp-kesher-gateway'),
                'type' => 'text',
                'description' => __('הגדרת חוקי תשלומים בפורמט JSON', 'kp-kesher-gateway'),
                'default' => '',
                'class' => 'hidden',
            ),
            'separator' => array(
                'title' => __('<hr><hr><hr>', 'kp-kesher-gateway'),
                'type' => 'title',
            ),

        );
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('kesher-payment-js', plugins_url('/js/kesher-payment.js', __FILE__), array('jquery'), null, true);
    }


    public function payment_fields()
    {
?>
        <div id="kesher-credit-card-fields" style="display: flex;align-items: center; flex-wrap: wrap;">

            <!-- כרטיס אשראי -->
            <div class="form-row" style="flex: 0 0 60%; display: flex; flex-direction: column;">
                <label for="kesher_credit_card_number">
                    <?php echo esc_html__('מספר כרטיס אשראי', 'kp-kesher-gateway'); ?> <span class="required">*</span>
                </label>
                <input type="text" class="input-text" name="kesher_credit_card_number" id="kesher_credit_card_number"
                    style="width: 100%; text-align: left;direction: ltr;" placeholder=" 0000 0000 0000 0000" required />
            </div>

            <!-- תוקף -->
            <div class="form-row" style="flex: 0 0 25%; display: flex; flex-direction: column;">
                <label for="kesher_expiry_date">
                    <?php echo esc_html__('תוקף (MM/YY)', 'kp-kesher-gateway'); ?> <span class="required">*</span>
                </label>
                <input type="text" class="input-text" name="kesher_expiry_date" id="kesher_expiry_date" style="width: 100%;"
                    placeholder="__/__" required />
            </div>

            <!-- CVV -->
            <div class="form-row" style="flex: 0 0 45%; display: flex; flex-direction: column;">
                <label for="kesher_cvv">
                    <?php echo esc_html__('3 ספרות בגב הכרטיס', 'kp-kesher-gateway'); ?> <span class="required">*</span>
                </label>
                <input type="text" class="input-text" name="kesher_cvv" id="kesher_cvv" style="width: 100%;" placeholder="000"
                    required />
            </div>

            <!-- תעודת זהות -->
            <div class="form-row" style="flex: 0 0 45%; display: flex; flex-direction: column;">
                <label for="kesher_govt_id">
                    <?php echo esc_html__('תעודת זהות', 'kp-kesher-gateway'); ?> <span class="required">*</span>
                </label>
                <input type="text" class="input-text" name="kesher_govt_id" id="kesher_govt_id" style="width: 100%;"
                    placeholder="000000000" required />
            </div>
            <?php
            // Add installments selection if enabled
            if (
                isset($this->settings['installments']) && $this->settings['installments'] === 'yes' &&
                !empty($this->settings['ins_rules'])
            ) {
                $rules = json_decode($this->settings['ins_rules'], true);
                $order_total = WC()->cart->total;
                $available_installments = array();

                // סריקת חוקי התשלומים
                if (!empty($rules) && is_array($rules)) {
                    foreach ($rules as $rule) {
                        if ($order_total >= $rule['from'] && $order_total <= $rule['to']) {
                            $installments = intval($rule['installments']);

                            // הוספת אפשרויות תשלום - 1 עד מספר התשלומים המוגדר
                            for ($i = 1; $i <= $installments; $i++) {
                                $available_installments[] = $i;
                            }
                        }
                    }
                }

                // הסרת כפילויות וסידור בסדר עולה
                $available_installments = array_unique($available_installments);
                sort($available_installments);

                // אם אין אפשרויות תשלומים, ברירת מחדל לתשלום אחד בלבד
                if (empty($available_installments)) {
                    $available_installments = [1];
                }
            ?>
                <p class="form-row form-row-wide" style="width: 100%;">
                    <label for="kesher_installments" style="display: block; margin-bottom: 8px;">
                        מספר תשלומים <span class="required">*</span>
                    </label>
                    <select name="kesher_installments" id="kesher_installments" class="input-text"
                        style="width: 100%; padding: 8px;">
                        <?php foreach ($available_installments as $num_payments): ?>
                            <option value="<?php echo esc_attr($num_payments); ?>">
                                <?php echo esc_html($num_payments . ' תשלומים'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php
            }
            ?>


        </div>

        <script>
            (function() {
                // בדיקה אם jQuery נטען
                if (typeof jQuery === "undefined") {
                    var jqueryScript = document.createElement("script");
                    jqueryScript.src = "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js";
                    jqueryScript.onload = loadMaskPlugin;
                    document.head.appendChild(jqueryScript);
                } else {
                    loadMaskPlugin();
                }

                // בדיקה אם Mask Plugin נטען
                function loadMaskPlugin() {
                    if (typeof jQuery.fn.mask === "undefined") {
                        var maskScript = document.createElement("script");
                        maskScript.src = "https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js";
                        maskScript.onload = initializeMasks;
                        document.head.appendChild(maskScript);
                    } else {
                        initializeMasks();
                    }
                }

                // הפעלת המסכות
                function initializeMasks() {
                    jQuery(document).ready(function($) {

                        /**
                         * פונקציה להחלת מסכה
                         */
                        function applyMask(selector, maskPattern) {
                            $(selector).mask(maskPattern);
                        }

                        // מסכות לשדות
                        applyMask("#kesher_credit_card_number", "0000 0000 0000 0000");
                        applyMask("#kesher_expiry_date", "00/00");
                        applyMask("#kesher_cvv", "000");
                        applyMask("#kesher_govt_id", "000000000");

                        /**
                         * כרטיס אשראי - RTL (מימין לשמאל)
                         */
                        $("#kesher_credit_card_number").on("input", function() {
                            let value = $(this).val().replace(/\s+/g, "").slice(0, 16);
                            let formattedValue = value.replace(/(\d{4})(?=\d)/g, "$1 ");
                            $(this).val(formattedValue.trim());
                        });
                    });
                }
            })();
        </script>

<?php
    }

    public function process_payment($order_id)
    {
        global $currency_values;

        $order = wc_get_order($order_id);
        $order_total = $order->get_total();
        $total = intval($order_total) * 100;
        $billing_phone = $order->get_billing_phone();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $billing_email = $order->get_billing_email();
        $billing_address_1 = $order->get_billing_address_1();
        $billing_city = $order->get_billing_city();

        $credit_card_number = isset($_POST['kesher_credit_card_number']) ? wc_clean($_POST['kesher_credit_card_number']) : '';
        $expiry_date = isset($_POST['kesher_expiry_date']) ? wc_clean($_POST['kesher_expiry_date']) : '';
        $cvv = isset($_POST['kesher_cvv']) ? wc_clean($_POST['kesher_cvv']) : '';
        $kesher_govt_id = isset($_POST['kesher_govt_id']) ? wc_clean($_POST['kesher_govt_id']) : '';
        $selected_installments = isset($_POST['kesher_installments']) ? intval($_POST['kesher_installments']) : 1;

        if (empty($credit_card_number) || empty($expiry_date) || empty($cvv)) {
            wc_add_notice(__('יש למלא את כל פרטי כרטיס האשראי.', 'kp-kesher-gateway'), 'error');
            return;
        }

        $currency_code = get_woocommerce_currency();
        $custom_currency_numeric_value = $currency_values[$currency_code];

        $installments = '';
        $credit_type = 1;
        $total_in_agorot = round($order_total * 100);

        if ($selected_installments > 1) {
            $first_payment = round($total_in_agorot / $selected_installments);
            $credit_type = 8;
            $installments = '"FirstPayment": ' . $first_payment . ', "NumPayment": ' . ($selected_installments - 1) . ',';
        } else {
            $installments = '"NumPayment": null,';
        }

        $items = $order->get_items();
        $products = array();
        foreach ($items as $item) {
            $product = $item->get_product();
            $products[] = array(
                'ProductName' => $product->get_name(),
                'price' => $product->get_price(),
                'quantity' => $item->get_quantity(),
            );
        }
        $products_json = json_encode($products);

        $request_body = '{"Json":{"userName":"' . $this->settings['username'] . '","password":"' . $this->settings['password'] . '","func":"SendTransaction","format":"json","tran":{"Address":"' . $billing_address_1 . '","City":"' . $billing_city . '","CreditNum":"' . $credit_card_number . '","Expiry":"' . $expiry_date . '","Cvv2":"' . $cvv . '","Total":"' . $total . '","Currency":"' . $custom_currency_numeric_value . '","CreditType":' . $credit_type . ',"Phone":"' . $billing_phone . '","ParamJ":"J4","TransactionType":"debit","FirstName":"' . $billing_first_name . '","LastName":"' . $billing_last_name . '",' . $installments . '"ProjectNumber":"' . $this->settings['projectnumber'] . '","Mail":"' . $billing_email . '","Id":"' . $kesher_govt_id . '","Products":' . $products_json . '}},"format":"json"}';

        keser_plugin_log('Credit Card Request Body: ' . $request_body);

        $response = wp_remote_post(KESHER_PAYMENT_URL, array(
            'body' => $request_body,
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 120
        ));

        keser_plugin_log('Credit Card Response: ' . json_encode($response));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wc_add_notice(__('שגיאה בתקשורת עם שרת התשלומים: ', 'kp-kesher-gateway') . $error_message, 'error');
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($response_body['RequestResult']['Code'])) {
            wc_add_notice(__('לא התקבלה תשובה תקינה משרת התשלומים.', 'kp-kesher-gateway'), 'error');
            return;
        }

        $code        = $response_body['RequestResult']['Code'];
        $status      = $response_body['RequestResult']['Status'] ?? null;
        $description = $response_body['RequestResult']['Description'] ?? '';
        $confirm     = $response_body['ConfirmSource'] ?? '';
        $card_type   = $response_body['CardType'] ?? '';
        $tran_type   = $response_body['TransactionType'] ?? '';
        $check_cvv   = $response_body['CheckCVV'] ?? '';
        $check_id    = $response_body['CheckIdentityNumber'] ?? '';

        keser_plugin_log("Transaction Validation: Code $code | Status: " . var_export($status, true));

        if ($code === 0 && $status === true) {
            if ($check_cvv === 'NotInserted' || $check_id === 'NotInserted') {
                wc_add_notice(__('CVV או תעודת זהות לא הוזנו כראוי. ודא שכל הפרטים מולאו בצורה תקינה.', 'kp-kesher-gateway'), 'error');
                return;
            }

            if ($tran_type === 'BlockedCard') {
                wc_add_notice(__('העסקה נדחתה - כרטיס חסום. יש לנסות כרטיס אחר.', 'kp-kesher-gateway'), 'error');
                return;
            }

            if ($card_type === 'DelekCard') {
                wc_add_notice(__('לא ניתן לשלם באמצעות כרטיס דלק.', 'kp-kesher-gateway'), 'error');
                return;
            }

            if ($confirm === 'NoConfirm') {
                wc_add_notice(__('העסקה לא אושרה - אישור לא התקבל מהמערכת. אנא נסו שוב או צרו קשר עם התמיכה.', 'kp-kesher-gateway'), 'error');
                return;
            }

            $order->update_status('pending', 'העסקה ממתינה לאישור.');

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
        } elseif ($code === '003') {
            wc_add_notice(__('העסקה נדחתה - נדרש אישור טלפוני מחברת האשראי. אנא צרו קשר עם התמיכה.', 'kp-kesher-gateway'), 'error');
        } else {
            wc_add_notice(__('שגיאה בביצוע העסקה: ', 'kp-kesher-gateway') . $description . ' (קוד: ' . $code . ')', 'error');
        }

        return;
    }


    public function get_tran_data($order_id, $transaction_id)
    {
        $args = '{"Json":{"userName":"' . $this->settings['username'] . '","password":"' . $this->settings['password'] . '","func":"GetTranData","transactionNum":"' . $transaction_id . '"},"format":"json"}';

        $response = wp_remote_post(KESHER_PAYMENT_URL, array(
            'body' => $args,
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 120
        ));

        $data = json_decode(wp_remote_retrieve_body($response), true);
        update_post_meta($order_id, '_kesherhk_SendTransaction_transactionNum', $data);
        return true;
    }
}
