<?php
/**
 * Plugin Name: Custom Product Text Field
 * Description: Adds a custom text field to WooCommerce product pages for user input.
 * Version: 1.2
 * Author: D3 Logics
 * License: GPLv2 or later
 * Text Domain: custom-product-text-field
 */

if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce.', 'custom-product-text-field'));
    }
});

register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'wc_custom_text_data';
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta("CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        custom_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) " . $wpdb->get_charset_collate() . ";");
});

add_action('woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_text_input([
        'id' => '_cptf_text_label',
        'label' => __('Custom Text Label', 'custom-product-text-field'),
        'desc_tip' => true
    ]);
    woocommerce_wp_checkbox([
        'id' => '_cptf_text_required',
        'label' => __('Required?', 'custom-product-text-field')
    ]);
});

add_action('woocommerce_process_product_meta', function($post_id) {
    foreach (['_cptf_text_label', '_cptf_text_required'] as $key) {
        update_post_meta($post_id, $key, isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '');
    }
});

add_action('woocommerce_product_after_variable_attributes', function($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id' => "_cptf_text_label_var[{$variation->ID}]",
        'label' => __('Custom Text Label', 'custom-product-text-field'),
        'desc_tip' => true,
        'value' => get_post_meta($variation->ID, '_cptf_text_label', true)
    ]);
    woocommerce_wp_checkbox([
        'id' => "_cptf_text_required_var[{$variation->ID}]",
        'label' => __('Required?', 'custom-product-text-field'),
        'value' => get_post_meta($variation->ID, '_cptf_text_required', true)
    ]);
}, 10, 3);

add_action('woocommerce_save_product_variation', function($variation_id, $i) {
    if (isset($_POST['_cptf_text_label_var'][$variation_id])) {
        update_post_meta($variation_id, '_cptf_text_label', sanitize_text_field($_POST['_cptf_text_label_var'][$variation_id]));
    }
    update_post_meta($variation_id, '_cptf_text_required', isset($_POST['_cptf_text_required_var'][$variation_id]) ? 'yes' : 'no');
}, 10, 2);

add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;

    $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
    $label = get_post_meta($product_id, '_cptf_text_label', true);
    $required = get_post_meta($product_id, '_cptf_text_required', true) === 'yes' ? 'required' : '';

    if ($label) {
        printf(
            '<p class="form-row form-row-wide"><label>%s %s</label><input type="text" name="cptf_custom_text" class="input-text" %s></p>',
            esc_html($label),
            $required ? '<span class="required">*</span>' : '',
            esc_attr($required)
        );
    }
});


add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id) {
    if (get_post_meta($product_id, '_cptf_text_required', true) === 'yes' && empty($_POST['cptf_custom_text'])) {
        wc_add_notice(__('Please enter the required text field.', 'custom-product-text-field'), 'error');
        return false;
    }
    return $passed;
}, 10, 2);


add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
    if (!empty($_POST['cptf_custom_text'])) {
        $cart_item_data['cptf_custom_text'] = sanitize_text_field($_POST['cptf_custom_text']);
    }
    return $cart_item_data;
}, 10, 2);


add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['cptf_custom_text'])) {
        $item_data[] = ['name' => __('Custom Text', 'custom-product-text-field'), 'value' => esc_html($cart_item['cptf_custom_text'])];
    }
    return $item_data;
}, 10, 2);


add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values) {
    if (!empty($values['cptf_custom_text'])) {
        $item->add_meta_data(__('Custom Text', 'custom-product-text-field'), $values['cptf_custom_text']);
    }
}, 10, 3);


add_action('woocommerce_thankyou', function($order_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'wc_custom_text_data';
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $custom_text = $item->get_meta('Custom Text');
        if ($custom_text) {
            $wpdb->insert($table, [
                'order_id'    => absint($order_id),
                'product_id'  => absint($item->get_product_id()),
                'custom_text' => sanitize_text_field($custom_text)
            ], ['%d', '%d', '%s']);
        }
    }
}, 10, 1);


add_action('woocommerce_product_options_inventory_product_data', function() {
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input([
        'id' => '_cptf_text_label',
        'label' => __('Custom Text Label', 'custom-product-text-field'),
        'desc_tip' => true,
        'description' => __('Enter a custom label for this product.', 'custom-product-text-field')
    ]);

    woocommerce_wp_checkbox([
        'id' => '_cptf_text_required',
        'label' => __('Required?', 'custom-product-text-field'),
        'description' => __('Check this to make the field required.', 'custom-product-text-field')
    ]);

    echo '</div>';
});


add_action('woocommerce_process_product_meta', function($post_id) {
    if (isset($_POST['_cptf_text_label'])) {
        update_post_meta($post_id, '_cptf_text_label', sanitize_text_field($_POST['_cptf_text_label']));
    }
    update_post_meta($post_id, '_cptf_text_required', isset($_POST['_cptf_text_required']) ? 'yes' : 'no');
});


add_action('woocommerce_variation_options_inventory', function($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id' => "_cptf_text_label_var[{$variation->ID}]",
        'label' => __('Custom Text Label', 'custom-product-text-field'),
        'desc_tip' => true,
        'description' => __('Enter a custom label for this variation.', 'custom-product-text-field'),
        'value' => get_post_meta($variation->ID, '_cptf_text_label', true)
    ]);

    woocommerce_wp_checkbox([
        'id' => "_cptf_text_required_var[{$variation->ID}]",
        'label' => __('Required?', 'custom-product-text-field'),
        'description' => __('Check this to make the field required for this variation.', 'custom-product-text-field'),
        'value' => get_post_meta($variation->ID, '_cptf_text_required', true) ? 'yes' : ''
    ]);
}, 10, 3);


add_action('woocommerce_save_product_variation', function($variation_id, $i) {
    if (isset($_POST['_cptf_text_label_var'][$variation_id])) {
        update_post_meta($variation_id, '_cptf_text_label', sanitize_text_field($_POST['_cptf_text_label_var'][$variation_id]));
    }
    update_post_meta($variation_id, '_cptf_text_required', isset($_POST['_cptf_text_required_var'][$variation_id]) ? 'yes' : 'no');
}, 10, 2);
