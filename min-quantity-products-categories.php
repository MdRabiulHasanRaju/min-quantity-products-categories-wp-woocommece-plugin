<?php
/*
Plugin Name: Minimum Quantity for Products & Categories
Description: Sets a minimum quantity for WooCommerce products based on multiple categories or individual product IDs.
Version: 1.3
Author: Md. Rabiul Hasan
*/

add_action('admin_menu', 'mqpc_add_admin_menu');
add_action('admin_init', 'mqpc_settings_init');

function mqpc_add_admin_menu() {
    add_options_page('Min Quantity Settings', 'Min Quantity Settings', 'manage_options', 'min_quantity_pc', 'mqpc_options_page');
}

function mqpc_settings_init() {
    register_setting('mqpc_pluginPage', 'mqpc_settings');

    add_settings_section(
        'mqpc_pluginPage_section',
        __('Minimum Quantity Configuration', 'woocommerce'),
        null,
        'mqpc_pluginPage'
    );

    add_settings_field(
        'mqpc_category_slugs',
        __('Category Slugs (comma-separated)', 'woocommerce'),
        'mqpc_category_slugs_render',
        'mqpc_pluginPage',
        'mqpc_pluginPage_section'
    );

    add_settings_field(
        'mqpc_min_quantity',
        __('Min Quantity for Categories', 'woocommerce'),
        'mqpc_min_quantity_render',
        'mqpc_pluginPage',
        'mqpc_pluginPage_section'
    );

    add_settings_field(
        'mqpc_product_quantities',
        __('Product-wise Quantities (format: ID=Qty,ID=Qty)', 'woocommerce'),
        'mqpc_product_quantities_render',
        'mqpc_pluginPage',
        'mqpc_pluginPage_section'
    );
}

function mqpc_category_slugs_render() {
    $options = get_option('mqpc_settings');
    ?>
    <input type='text' name='mqpc_settings[mqpc_category_slugs]' value='<?php echo $options['mqpc_category_slugs'] ?? ''; ?>'>
    <p class="description">Comma-separated category slugs (e.g., t-shirts,hoodies)</p>
    <?php
}

function mqpc_min_quantity_render() {
    $options = get_option('mqpc_settings');
    ?>
    <input type='number' min='1' name='mqpc_settings[mqpc_min_quantity]' value='<?php echo $options['mqpc_min_quantity'] ?? 1; ?>'>
    <?php
}

function mqpc_product_quantities_render() {
    $options = get_option('mqpc_settings');
    ?>
    <input type='text' name='mqpc_settings[mqpc_product_quantities]' value='<?php echo $options['mqpc_product_quantities'] ?? ''; ?>'>
    <p class="description">Example: 12=2,45=5,99=3 (product ID = quantity)</p>
    <?php
}

function mqpc_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h2>Minimum Quantity Settings</h2>
        <?php
        settings_fields('mqpc_pluginPage');
        do_settings_sections('mqpc_pluginPage');
        submit_button();
        ?>
    </form>
    <?php
}

// Quantity logic filter
add_filter('woocommerce_quantity_input_args', 'mqpc_set_min_quantity', 10, 2 );
function mqpc_set_min_quantity($args, $product) {
    $options = get_option('mqpc_settings');
    $min_cat_qty = isset($options['mqpc_min_quantity']) ? intval($options['mqpc_min_quantity']) : 1;
    $category_slugs = isset($options['mqpc_category_slugs']) ? array_map('trim', explode(',', $options['mqpc_category_slugs'])) : [];

    $product_id = $product->get_id();
    $product_quantities = [];
    if (!empty($options['mqpc_product_quantities'])) {
        $entries = explode(',', $options['mqpc_product_quantities']);
        foreach ($entries as $entry) {
            if (strpos($entry, '=') !== false) {
                list($id, $qty) = array_map('trim', explode('=', $entry));
                $product_quantities[intval($id)] = intval($qty);
            }
        }
    }

    if (isset($product_quantities[$product_id])) {
        $args['min_value'] = max(1, $product_quantities[$product_id]);
    } elseif (!empty($category_slugs) && has_term($category_slugs, 'product_cat', $product_id)) {
        $args['min_value'] = max(1, $min_cat_qty);
    }

    return $args;
}


add_action('woocommerce_check_cart_items', 'mqpc_validate_cart_quantities');

function mqpc_validate_cart_quantities() {
    $options = get_option('mqpc_settings');
    $category_slugs = isset($options['mqpc_category_slugs']) ? array_map('trim', explode(',', $options['mqpc_category_slugs'])) : [];
    $min_cat_qty = isset($options['mqpc_min_quantity']) ? intval($options['mqpc_min_quantity']) : 1;

    // Parse product-specific min quantities
    $product_quantities = [];
    if (!empty($options['mqpc_product_quantities'])) {
        $entries = explode(',', $options['mqpc_product_quantities']);
        foreach ($entries as $entry) {
            if (strpos($entry, '=') !== false) {
                list($id, $qty) = array_map('trim', explode('=', $entry));
                $product_quantities[intval($id)] = intval($qty);
            }
        }
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $quantity = $cart_item['quantity'];

        // Check product-specific
        if (isset($product_quantities[$product_id])) {
            $min_qty = $product_quantities[$product_id];
            if ($quantity < $min_qty) {
                wc_add_notice(sprintf(__('The minimum quantity for "%s" is %d.', 'woocommerce'), $product->get_name(), $min_qty), 'error');
            }
        }
        // Check category-based
        elseif (!empty($category_slugs) && has_term($category_slugs, 'product_cat', $product_id)) {
            if ($quantity < $min_cat_qty) {
                wc_add_notice(sprintf(__('The minimum quantity for category product "%s" is %d.', 'woocommerce'), $product->get_name(), $min_cat_qty), 'error');
            }
        }
    }
}
