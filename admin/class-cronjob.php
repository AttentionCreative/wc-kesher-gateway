<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KesherHK_Cronjob
{

    public static function process_pending_orders()
    {

        $args = array(
            'status' => 'pending',
            'limit' => -1,
            'payment_method' => array('kesher_credit_card', 'kesher_bit_transaction', 'kesher_cash_transaction')
        );

        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $transaction_id = get_post_meta($order_id, '_kesher_transaction_number', true);
            $payment_gateway = $order->get_payment_method();
            $check_count = get_post_meta($order_id, '_kesher_check_count', true) ?: 0;

            if (!empty($transaction_id)) {
                if ($payment_gateway === "kesher_credit_card") {
                    $obj = new WC_Credit_Card_Gateway();
                } elseif ($payment_gateway === "kesher_bit_transaction") {
                    $obj = new WC_Bit_Gateway();
                } elseif ($payment_gateway === "kesher_cash_transaction") {
                    $obj = new WC_Click_Transfer_Gateway();
                } else {
                    keser_plugin_log("⚠️ הזמנה $order_id - שער תשלום לא מוכר: $payment_gateway");
                    continue;
                }

                $response = $obj->get_tran_data($order_id, $transaction_id);

                // תנאי לזיהוי עסקה מאושרת לפי תגובת קשר
                $is_approved = is_array($response)
                    && isset($response['Status'], $response['TransactionType'], $response['CreditStatus'], $response['NumTransaction'])
                    && $response['Status'] === 'עבר בהצלחה'
                    && $response['TransactionType'] === 'עסקת חובה'
                    && (int)$response['CreditStatus'] === 0
                    && !empty($response['NumTransaction']);

                if ($is_approved) {
                    $order->add_order_note('התשלום אושר בהצלחה, סטטוס ההזמנה עודכן אוטומטית למצב בטיפול');
                    $order->payment_complete($response['NumTransaction']);
                    $order->update_status('processing');
                    keser_plugin_log("✅ הזמנה $order_id עודכנה לבטיפול");
                } else {
                    $check_count++;
                    update_post_meta($order_id, '_kesher_check_count', $check_count);
                    keser_plugin_log("❌ הזמנה $order_id - התשלום עדיין לא הושלם (ניסיון $check_count מתוך 6)");

                    if ($check_count >= 6) {
                        $order->update_status('failed', 'ההזמנה סומנה ככושלת לאחר 6 ניסיונות בדיקה, הלקוח לא ביצע את התשלום');
                        $order->add_order_note('ההזמנה סומנה ככושלת לאחר 6 ניסיונות בדיקה מול קשר – התשלום לא אושר');
                        keser_plugin_log("⚠️ הזמנה $order_id סומנה ככושלת לאחר 6 בדיקות");
                    }
                }
            } else {
                keser_plugin_log("⚠️ הזמנה $order_id נדחתה - חסר מספר עסקה");
            }
        }
    }
}

add_action('kesherhk_cron_hook', array('KesherHK_Cronjob', 'process_pending_orders'));

function kesherhk_cron_schedule_interval($schedules)
{
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__('Every 5 Minutes'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'kesherhk_cron_schedule_interval');

function kesherhk_schedule_cron()
{
    if (!wp_next_scheduled('kesherhk_cron_hook')) {
        wp_schedule_event(time(), 'every_five_minutes', 'kesherhk_cron_hook');
    }
}
add_action('init', 'kesherhk_schedule_cron');
