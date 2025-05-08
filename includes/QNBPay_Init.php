<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initializes the QNBPay plugin.
 *
 * Sets up hooks for styles, payment gateway registration, dashboard widgets,
 * meta boxes, and plugin row meta links.
 *
 * @package QNBPay
 * @since 1.0.0
 */
class QNBPay_Init// Changed class name to follow WordPress standards (PascalCase)

{
    /** @var QNBPay_Init|null Singleton instance */
    private static $instance = null;

    /** @var array QNBPay gateway settings. */
    public $qnbOptions;

    /**
     * Get the singleton instance of this class.
     *
     * @return QNBPay_Init
     */
    public static function get_instance()
    {
        // Check if the instance is null, meaning it hasn't been created yet
        if (is_null(self::$instance)) {
            // Create a new instance of the class
            $self = new self();
            // Initialize the instance with necessary settings
            $self->init();
            // Assign the newly created instance to the static variable
            self::$instance = $self;
        }
        // Return the singleton instance
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Intentionally left blank for Singleton pattern
    }

    /**
     * Initialize the core class properties and hooks.
     * Adds actions for styles, gateway registration, meta boxes, and plugin row meta.
     * @since 1.0.0
     * @return void
     */
    public function init()
    {
        // Get QNBPay settings
        $this->qnbOptions = get_option('woocommerce_qnbpay_settings');
        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', [$this, 'pluginStyles']);

        // Register the payment gateway with WooCommerce
        add_filter('woocommerce_payment_gateways', [$this, 'addQNBPayGateway']);

        // Add and save the product meta box for installment limits
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);

        // Add links to the plugin row on the plugins page
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
    }

    /**
     * Add custom links to the plugin row meta.
     *
     * @param array  $links Existing plugin links.
     * @param string $file  Plugin file path.
     * @return array Modified plugin links.
     */
    public function plugin_row_meta($links, $file)
    {
        // Only add links for this specific plugin
        if (plugin_basename(QNBPAY_FILE) !== $file) {
            return $links;
        }

        // Define the additional links
        $more = [
            '<a href="https://qnbpay.com.tr/" target="_blank">' . __('Website', 'qnbpay-woocommerce') . '</a>',
            '<a href="https://qnbpay.com.tr/contact" target="_blank">' . __('Report Issue', 'qnbpay-woocommerce') . '</a>',
        ];

        // Merge new links with existing ones
        return array_merge($links, $more);
    }

    /**
     * Add meta box to product edit screen for installment limit if enabled.
     *
     * @param string $post_type The post type being edited.
     */
    public function add_meta_box($post_type)
    {
        // Only add meta box for 'product' post type
        if ($post_type != 'product' || !data_get($this->qnbOptions, 'enabled')) {
            return;
        }

        // Check if the 'limit by product' setting is enabled
        $limitInstallmentByProduct = data_get($this->qnbOptions, 'limitInstallmentByProduct', 'no') === 'yes';
        if ($limitInstallmentByProduct) {
            // Add the meta box to the product edit screen sidebar
            add_meta_box(
                'qnbpay_installment_limit',
                __('Installment Limit', 'qnbpay-woocommerce'),
                [$this, 'render_meta_box_content'],
                $post_type,
                'side'
            );
        }
    }

    /**
     * Save the installment limit meta box data.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_meta_box($post_id)
    {
        // Check if it's an autosave, if so, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );

        if ( $is_autosave || $is_revision ) {
            return;
        }

        // Check post type and user permissions
        if (!(get_post_type($post_id) == 'product' && current_user_can('manage_woocommerce'))) {
            return;
        }

        // Check if our meta box data was submitted
        if (isset($_POST['_limitInstallment'])) {
            // If a valid installment limit (greater than 0) was selected, save it
            if (intval($_POST['_limitInstallment']) > 0) {
                update_post_meta($post_id, '_limitInstallment', intval($_POST['_limitInstallment']));
            } else {
                // If 'Default' (value 0) was selected, delete the meta key
                delete_post_meta($post_id, '_limitInstallment');
            }
        }
    }

    /**
     * Render the content of the installment limit meta box.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content($post)
    {
        // Get the currently saved installment limit for this product
        $limitInstallment = get_post_meta($post->ID, '_limitInstallment', true);

        ?>
<select name="_limitInstallment">
    <option value="0"><?php _e('Default', 'qnbpay-woocommerce'); ?></option>
        <?php

        foreach (range(1, 12) as $_ment) {
            ?>
    <option value="<?php echo $_ment; ?>" <?php selected($limitInstallment, $_ment); ?>>
        <?php printf(__('%s Installement', 'qnbpay-woocommerce'), $_ment); ?>
    </option>
    <?php

        }

        ?>
</select>
<?php

    }

    /**
     * Enqueue frontend styles and scripts.
     *
     * @since 1.0.0
     * @return void
     */
    public function pluginStyles()
    {
        // Register and enqueue the main CSS file for the payment form
        wp_register_style('qnbpay-card_css', QNBPAY_URL . 'assets/qnbpay.min.css', false, QNBPAY_VERSION);
        wp_enqueue_style('qnbpay-card_css');
    }

    /**
     * Add the QNBPay gateway to the list of WooCommerce payment gateways.
     *
     * @param array $gateways Existing payment gateways.
     * @return array Modified list of payment gateways.
     */
    public function addQNBPayGateway($gateways)
    {
        if (class_exists('QNBPay_Gateway')) {
            // Add our gateway class to the list of available gateways
            $gateways[] = 'QNBPay_Gateway';
        }
        return $gateways;
    }
}