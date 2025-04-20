<?php

/**
 * Plugin Name: MEC sslcommerz payment gateway
 * Description: MEC sslcommerz payment gateway integration
 * Version: 1.5
 * Author: Crebsol Ltd
 * License: GPL2
 */

defined('ABSPATH') or die('Direct access not allowed');

class SSLCommerz_Payment_Gateway
{
    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'create_sslcommerz_payments_table'));
        add_shortcode('sslcommerz_payment', array($this, 'sslcommerz_payment_shortcode'));
        add_action('init', array($this, 'process_sslcommerz_payment'));
        add_action('template_redirect', array($this, 'handle_payment_response'));
        add_shortcode('payment_history', array($this, 'payment_history_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts')); // Enqueue the custom JavaScript

        add_action('init', array($this, 'setup_custom_email_headers'));
    }

    public function setup_custom_email_headers()
    {
        add_filter('wp_mail_from', array($this, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
    }

    public function custom_mail_from($from_email)
    {
        return 'contact@mecommunity.org';
    }

    public function custom_mail_from_name($from_name)
    {
        return 'MEC Team';
    }



    public function create_sslcommerz_payments_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sslcommerz_payments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tran_id varchar(100) NOT NULL,
            cus_name varchar(100) NOT NULL,
            cus_email varchar(100) NOT NULL,
            cus_phone varchar(50) NOT NULL,
            mec_guest_number int(11) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            payment_status varchar(50) NOT NULL,
            payment_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            sslcommerz_response text,
            PRIMARY KEY  (id),
            UNIQUE KEY tran_id (tran_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    public function sslcommerz_payment_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'amount' => '400',
                'btn_text' => 'Proceed to Payment',
                'guest_num_label' => 'Number of Guests'
            ),
            $atts,
            'sslcommerz_payment'
        );

        ob_start();
?>
        <div class="sslcommerz-payment-form">
            <form method="post" id="sslcommerz-payment-form">
                <?php wp_nonce_field('sslcommerz_payment_nonce', '_wpnonce'); ?>
                <input type="hidden" name="sslcommerz_action" value="process_payment">
                <input type="hidden" name="amount" value="<?php echo esc_attr($atts['amount']); ?>">

                <div class="form-group">
                    <label for="sslcommerz-cus-name">Full Name *</label>
                    <input type="text" name="cus_name" id="sslcommerz-cus-name" required>
                </div>

                <div class="form-group">
                    <label for="sslcommerz-cus-email">Email *</label>
                    <input type="email" name="cus_email" id="sslcommerz-cus-email" required>
                </div>

                <div class="form-group">
                    <label for="sslcommerz-cus-phone">Phone Number *</label>
                    <input type="tel" name="cus_phone" id="sslcommerz-cus-phone" required>
                </div>

                <div class="form-group">
                    <label for="sslcommerz-cus-guest-num"><?php echo esc_html($atts['guest_num_label']); ?></label>
                    <input type="number" name="mec_guest_number" id="sslcommerz-cus-guest-num" min="0">
                </div>

                <div class="payment-summary">
                    <h4>Payment Summary</h4>
                    <p>Amount: ৳<span id="payment-amount"><?php echo esc_html($atts['amount']); ?></span></p>
                </div>

                <button type="submit" class="sslcommerz-pay-button"><?php echo esc_html($atts['btn_text']); ?></button>
            </form>
        </div>
    <?php
        return ob_get_clean();
    }



    public function process_sslcommerz_payment()
    {
        if (!isset($_POST['sslcommerz_action']) || $_POST['sslcommerz_action'] != 'process_payment') {
            return;
        }

        // Validate nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sslcommerz_payment_nonce')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sslcommerz_payments';

        // Configuration - should be moved to settings page
        $store_id = 'sslcommerz ID';
        $store_passwd = 'Store ID';

        $api_url = 'https://sandbox.sslcommerz.com/gwprocess/v3/api.php';



        // Get the number of guests and amount
        $guest_number = isset($_POST['mec_guest_number']) ? intval($_POST['mec_guest_number']) : '';
        $amount = floatval($_POST['amount']);

        // Multiply amount by the number of guests
        $total_amount = $amount * $guest_number + $amount;

        // Generate transaction ID
        $tran_id = 'TXN_' . uniqid();

        // Save to database
        $wpdb->insert(
            $table_name,
            array(
                'tran_id'         => $tran_id,
                'cus_name'        => sanitize_text_field($_POST['cus_name']),
                'cus_email'       => sanitize_email($_POST['cus_email']),
                'cus_phone'       => sanitize_text_field($_POST['cus_phone']),
                'mec_guest_number' => $guest_number,
                'amount'          => $total_amount,
                'payment_status'  => 'Pending',
                'payment_date'    => current_time('mysql'),
            ),
            array(
                '%s', // tran_id
                '%s', // cus_name
                '%s', // cus_email
                '%s', // cus_phone
                '%d', // mec_guest_number
                '%f', // amount
                '%s', // payment_status
                '%s'  // payment_date
            )
        );

        if ($wpdb->last_error) {
            error_log("Error saving payment data: " . $wpdb->last_error);
        } else {
            error_log("Payment data saved successfully!");
        }

        // Prepare API data
        $post_data = array(
            'store_id' => $store_id,
            'store_passwd' => $store_passwd,
            'total_amount' => $total_amount,
            'currency' => 'BDT',
            'tran_id' => $tran_id,
            'success_url' => add_query_arg('tran_id', $tran_id, home_url('/payment-success')),
            'fail_url' => add_query_arg('tran_id', $tran_id, home_url('/payment-failed')),
            'cancel_url' => add_query_arg('tran_id', $tran_id, home_url('/payment-cancelled')),
            'cus_name' => sanitize_text_field($_POST['cus_name']),
            'cus_email' => sanitize_email($_POST['cus_email']),
            'cus_phone' => sanitize_text_field($_POST['cus_phone']),
            'cus_add1' => 'Not Provided',
            'cus_city' => 'Not Provided',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => sanitize_text_field($_POST['desc']),
            'product_category' => 'General',
            'product_profile' => 'general',
            'value_a' => isset($_POST['mec_member_id']) ? sanitize_text_field($_POST['mec_member_id']) : '',
            'value_b' => isset($_POST['mec_guest_number']) ? intval($_POST['mec_guest_number']) : '',
        );

        // API request
        $response = wp_remote_post($api_url, array(
            'body' => $post_data,
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            wp_die("API Connection Error: " . $response->get_error_message());
        }

        $result = json_decode($response['body'], true);

        if (isset($result['GatewayPageURL']) && !empty($result['GatewayPageURL'])) {
            wp_redirect($result['GatewayPageURL']);
            exit;
        } else {
            $error_message = isset($result['failedreason']) ? $result['failedreason'] : 'Unknown error';
            wp_die("Payment processing failed. Error: " . esc_html($error_message) . "<pre>" . print_r($result, true) . "</pre>");
        }
    }

    public function handle_payment_response()
    {
        if (!isset($_GET['tran_id']) || empty($_GET['tran_id'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sslcommerz_payments';
        $tran_id = sanitize_text_field($_GET['tran_id']);

        // Determine payment status from current page
        $payment_status = '';
        if (is_page('payment-success')) {
            $payment_status = 'Completed';
        } elseif (is_page('payment-failed')) {
            $payment_status = 'Failed';
        } elseif (is_page('payment-cancelled')) {
            $payment_status = 'Cancelled';
        }

        // Update database
        if (!empty($payment_status)) {
            $wpdb->update(
                $table_name,
                array(
                    'payment_status' => $payment_status,
                    'payment_date' => current_time('mysql'),
                ),
                array('tran_id' => $tran_id),
                array('%s', '%s'),
                array('%s')
            );
        }

        // Send confirmation email if payment is completed
        if ($payment_status === 'Completed') {
            $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE tran_id = %s", $tran_id));

            if ($payment) {
                $to = $payment->cus_email;
                $subject = 'Payment Confirmation - MEC';
                $message = "Dear {$payment->cus_name},\n\nThank you for your payment of ৳{$payment->amount}.\n\nYour transaction ID is {$payment->tran_id}.\nWe look forward to seeing you at the event!\n\nBest regards,\nMEC Team";
                $headers = ['Content-Type: text/plain; charset=UTF-8'];

                wp_mail($to, $subject, $message, $headers);
            }
        }

        // Display message
        add_action('the_content', function ($content) use ($payment_status) {
            if ($payment_status == 'Completed') {
                $content = '<div class="payment-success">Payment completed successfully! Thank you.</div>' . $content;
            } elseif ($payment_status == 'Failed') {
                $content = '<div class="payment-failed">Payment failed. Please try again.</div>' . $content;
            } elseif ($payment_status == 'Cancelled') {
                $content = '<div class="payment-cancelled">Payment was cancelled.</div>' . $content;
            }
            return $content;
        });
    }


    public function payment_history_shortcode()
    {
        // Optional: Admin-only view
        if (!current_user_can('manage_options')) {
            return '<p>You do not have permission to view this page.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sslcommerz_payments';

        // Filter by status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

        // Build query
        $query = "SELECT * FROM $table_name";
        $params = [];

        if ($status_filter !== 'all') {
            $query .= " WHERE payment_status = %s";
            $params[] = $status_filter;
        }

        $query .= " ORDER BY payment_date DESC";
        $payments = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, ...$params)) : $wpdb->get_results($query);

        // Calculate totals
        $total_registrations = count($payments);
        $total_guests = 0;
        $total_amount_completed = 0;

        foreach ($payments as $payment) {
            $total_guests += intval($payment->mec_guest_number);

            if (strtolower($payment->payment_status) === 'completed') {
                $total_amount_completed += floatval($payment->amount);
            }
        }

        ob_start();
    ?>
        <div class="payment-history">
            <h3>All Payment History</h3>
            <div style="overflow:hidden;">
                <div style="width: 50%;float:left;overflow:hidden">
                    <form method="get" style="margin-bottom: 15px;">
                        <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                        <label for="status">Filter by Status:</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all" <?php selected($status_filter, 'all'); ?>>All</option>
                            <option value="Completed" <?php selected($status_filter, 'Completed'); ?>>Completed</option>
                            <option value="Pending" <?php selected($status_filter, 'Pending'); ?>>Pending</option>
                        </select>
                    </form>
                </div>
                <div style="width:50%; overflow:hidden; display: flex; align-items: center;justify-content: end; height:80px;">
                    <p style="margin: 0;"><strong>Total Amount (Completed Only):</strong> ৳<?php echo number_format($total_amount_completed, 2); ?></p>
                </div>
            </div>
            <div style="overflow: hidden;">
                <p><strong>Total Registrations:</strong> <?php echo $total_registrations; ?>, <strong>Total Guests:</strong> <?php echo $total_guests; ?>, <strong>Total Participate:</strong> <?php echo $total_registrations + $total_guests; ?></p>
            </div>

            <?php if (!empty($payments)) : ?>
                <div class="table-responsive">
                    <table class="payment-history-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Name</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Guests</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment) : ?>
                                <tr>
                                    <td><?php echo esc_html($payment->tran_id); ?></td>
                                    <td><?php echo esc_html($payment->cus_name); ?></td>
                                    <td>৳<?php echo number_format($payment->amount, 2); ?></td>
                                    <td><span class="payment-status <?php echo strtolower($payment->payment_status); ?>"><?php echo esc_html($payment->payment_status); ?></span></td>
                                    <td><?php echo date('M j, Y g:i a', strtotime($payment->payment_date)); ?></td>
                                    <td><?php echo $payment->mec_guest_number ? intval($payment->mec_guest_number) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p>No payment history found.</p>
            <?php endif; ?>
        </div>
<?php

        return ob_get_clean();
    }


    public function enqueue_styles()
    {
        wp_enqueue_style('sslcommerz-styles', plugins_url('css/sslcommerz.css', __FILE__));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('sslcommerz-scripts', plugins_url('js/sslcommerz.js', __FILE__), array('jquery'), null, true);

        // Localize the script to pass the amount from PHP to JavaScript
        wp_localize_script('sslcommerz-scripts', 'sslcommerzData', array(
            'baseAmount' => floatval($_POST['amount']) // Pass the amount here
        ));
    }
}

new SSLCommerz_Payment_Gateway();
