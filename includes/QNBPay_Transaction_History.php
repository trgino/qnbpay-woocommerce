<?php
/**
 * Adds a transaction history page to the WooCommerce admin menu
 * to view QNBPay related order logs.
 *
 * @package QNBPay
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to manage the QNBPay Transaction History admin page.
 */
class QNBPay_Transaction_History
{
    /** @var QNBPay_Transaction_History|null Singleton instance */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     *
     * @return QNBPay_Transaction_History
     */
    public static function get_instance()
    {
        // If instance doesn't exist, create it
        if (is_null(self::$instance)) {
            // Create a new instance of the class
            $self = new self();
            // Initialize the instance with necessary settings
            $self->init();
            // Assign the newly created instance to the static variable
            self::$instance = $self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Intentionally left blank for Singleton pattern
    }

    public function init()
    {
        // Hook into the admin menu creation process
        add_action('admin_menu', [$this, 'add_transaction_history_page']);
    }

    /**
     * Add the transaction history submenu page under WooCommerce.
     * @return void
     */
    public function add_transaction_history_page()
    {
        // Add a submenu page under the main WooCommerce menu
        add_submenu_page(
            'woocommerce',
            __('QNBPay Transaction History', 'qnbpay-woocommerce'),
            __('QNBPay Transactions', 'qnbpay-woocommerce'),
            'manage_woocommerce',
            'qnbpay-transaction-history',
            [$this, 'transaction_history_page']
        );
    }

    /**
     * Callback function to display the transaction history page content.
     * @return void
     */
    public function transaction_history_page()
    {
        // Process any actions (like 'view') before displaying the page content
        $this->process_actions(); // Handle actions like 'view' before displaying the list
    }

    /**
     * Process actions requested via query parameters (e.g., view details).
     *
     * @access private
     * @return void
     */
    private function process_actions()
    {
        // Check if user has permission, action, ID, and nonce are set
        if (current_user_can('manage_options') && isset($_GET['action']) && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
            $action = sanitize_text_field($_GET['action']);
            $id = absint($_GET['id']);
            $nonce = sanitize_text_field($_GET['_wpnonce']);

            // Verify the nonce for security
            if (!wp_verify_nonce($nonce, 'qnbpay_transaction_action')) {
                wp_die(__('Security check failed', 'qnbpay-woocommerce'));
            }

            // If the action is 'view'
            if ($action == 'view') {
                // Display the transaction details view
                $this->view_transaction_details($id);
                // Stop execution after displaying details
                exit; // Stop further processing after showing details
            }
        }
        // If no action was processed or action wasn't 'view', display the list
        $this->display_transaction_history();
    }

    /**
     * Display transaction history
     * @access private
     * @return void
     */
    private function display_transaction_history()
    {
        global $wpdb;
        // --- Database Query Setup ---

        $transactions = [];

        // Use the custom log table instead of wc_orders
        $table_name = $wpdb->prefix . 'qnbpay_orders';
        // Get current page number for pagination
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Get total number of items for pagination calculation
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
        $total_pages = ceil($total_items / $per_page);

        $transactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY createdate DESC LIMIT %d OFFSET %d",
                $per_page, // Corrected variable name
                $offset
            )
        );
        // --- End Database Query Setup ---
        // --- Display Table ---

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('QNBPay Transaction History', 'qnbpay-woocommerce'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'qnbpay-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Order ID', 'qnbpay-woocommerce'); ?></th> <!-- Link to WC Order -->
                        <th><?php echo esc_html__('Action', 'qnbpay-woocommerce'); ?></th> <!-- Logged Action -->
                        <th><?php echo esc_html__('Date', 'qnbpay-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Actions', 'qnbpay-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <?php // Display message if no transactions found?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No transactions found.', 'qnbpay-woocommerce'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <?php // Display log ID?>
                                <td><?php echo esc_html($transaction->id); ?></td>
                                <td>
                                    <?php if ($transaction->orderid > 0): ?>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction->orderid . '&action=edit')); ?>" target="_blank">
                                            <?php echo esc_html($transaction->orderid); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <?php // Display logged action?>
                                <td><?php echo esc_html($transaction->action); ?></td> <!-- Logged Action -->
                                <?php // Display log creation date/time?>
                                <td>
                                    <div><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->createdate))); ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=qnbpay-transaction-history&action=view&id=' . $transaction->id), 'qnbpay_transaction_action')); ?>" class="button button-small">
                                        <?php echo esc_html__('View', 'qnbpay-woocommerce'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(_n('%s item', '%s items', $total_items, 'qnbpay-woocommerce'), number_format_i18n($total_items)); ?>
                        </span>
                        <span class="pagination-links">
        <?php

        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $page,
        ]);

        ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php

    }

    /**
     * View transaction details
     *
     * @access private
     * @param int $id Log ID from qnbpay_orders table.
     * @return void
     */
    private function view_transaction_details($id)
    {
        global $wpdb;
        // --- Database Query ---

        $table_name = $wpdb->prefix . 'qnbpay_orders';
        // Corrected query: Fetch the specific log entry by its ID, not by orderid using the log id.
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $id
            )
        );

        // --- Check if transactions found ---
        if (!$transaction) { // Check the single result
            wp_die(__('Transaction not found', 'qnbpay-woocommerce'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Transaction Details', 'qnbpay-woocommerce'); ?> (Log ID: <?php echo esc_html($transaction->id); ?> / Order ID: <?php echo esc_html($transaction->orderid); ?>)</h1>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=qnbpay-transaction-history')); ?>" class="button">
                    <?php echo esc_html__('Back to Transaction History', 'qnbpay-woocommerce'); ?>
                </a>
<?php if ($transaction->orderid > 0) {?>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction->orderid . '&action=edit')); ?>" class="button" target="_blank">
                    <?php echo esc_html__('View Order', 'qnbpay-woocommerce'); ?> (<?php echo esc_html($transaction->orderid); ?>)
                </a>
<?php }?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:150px;"><?php echo esc_html__('Field', 'qnbpay-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Value', 'qnbpay-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html__('Log ID', 'qnbpay-woocommerce'); ?></strong></td>
                        <td><?php echo esc_html($transaction->id); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Order ID', 'qnbpay-woocommerce'); ?></strong></td>
                        <td><?php echo esc_html($transaction->orderid); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Action', 'qnbpay-woocommerce'); ?></strong></td>
                        <td><?php echo esc_html($transaction->action); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Date', 'qnbpay-woocommerce'); ?></strong></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->createdate))); ?></td>
                    </tr>
                    <tr>
                        <td valign="top"><strong><?php echo esc_html__('Data', 'qnbpay-woocommerce'); ?></strong></td>
                        <td>
        <?php

        $data = json_decode($transaction->data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<pre>' . esc_html($transaction->data) . '</pre>';
        } elseif ($data) {
            $this->printList($data);
        } else {
            echo '<em>-</em>';
        }

        ?>
                        </td>
                    </tr>
                    <tr>
                        <td valign="top"><strong><?php echo esc_html__('Details', 'qnbpay-woocommerce'); ?></strong></td>
                        <td>

        <?php

        $details = json_decode($transaction->details, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<pre>' . esc_html($transaction->details) . '</pre>';
        } elseif ($details) {
            $this->printList($details);
        } else {
            echo '<em>-</em>';
        }

        ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php // --- End Display Details ---?>
        <?php
}

    /**
     * Recursively print an array as an unordered list.
     *
     * @access private
     * @param array $array The array to print.
     * @return void Outputs HTML.
     */
    private function printList($array)
    {
        // Ensure we only iterate over arrays
        if (!is_array($array) || !$array) {
            echo '-';
        }

        // Start an unordered list
        echo "<ul style='margin:0; padding-left: 15px;'>"; // Added some basic styling
        // Loop through the array
        foreach ($array as $key => $value) {
            // Ensure key is scalar before using it
            if (!is_scalar($key)) {
                // This should ideally not happen with json_decode(true)
                $key = '[Invalid Key Type]';
            }

            echo "<li style='margin-bottom: 5px;'>"; // Added some basic styling
            // Use htmlspecialchars on key, casting to string just in case (e.g., integer key)
            echo "<strong>" . htmlspecialchars((string) $key) . ":</strong> "; // Added space after colon

            // If the value is another array, recursively call this function
            if (is_array($value)) {
                $this->printList($value);
            } else {
                // Otherwise, display the key and value
                echo htmlspecialchars((string) $value);
            }
            echo "</li>";
        }
        echo "</ul>";
    }
}
