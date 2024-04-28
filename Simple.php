<?php
/*
Plugin Name: Woocommerce Variations Table - Grid
Plugin URI: http://codecanyon.net/item/woocommerce-variations-to-table-grid/10494620
Description: Plugin to turn Woocommerce normal variations select menus to table - grid
Version: 1.6.1
Author: Spyros Vlachopoulos
Author URI: http://www.nitroweb.gr
License: GPL2
Text Domain: vartable
*/

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

// stop automatic updates
add_filter('http_request_args', 'vt_prevent_update_check', 10, 2);
function vt_prevent_update_check($r, $url)
{

    if (0 === strpos($url, 'http://api.wordpress.org/plugins/update-check/')) {
        $my_plugin = plugin_basename(__FILE__);
        $plugins = unserialize($r['body']['plugins']);
        unset($plugins->plugins[$my_plugin]);
        unset($plugins->active[array_search($my_plugin, $plugins->active)]);
        $r['body']['plugins'] = serialize($plugins);
    }
    return $r;
}

add_filter('site_transient_update_plugins', 'vt_remove_update_notification');
function vt_remove_update_notification($value)
{

    if (isset($value->response[plugin_basename(__FILE__)])) {
        unset($value->response[plugin_basename(__FILE__)]);
    }
    return $value;
}

// Load plugin textdomain
add_action('plugins_loaded', 'vartable_load_textdomain');
function vartable_load_textdomain()
{

    load_plugin_textdomain('vartable', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('template_redirect', 'vartable_load_vartable_functionality');
function vartable_load_vartable_functionality()
{

    $position = get_option('vartable_position', 'woocommerce_variable_add_to_cart');
    $priority = intval(get_option('vartable_priority', 30));

    if ($position == 'side') {
        $position = 'woocommerce_variable_add_to_cart';
    }
    if ($position == 'under') {
        $position = 'woocommerce_after_single_product_summary';
    }


    if (is_singular('product')) {

        global $post;

        $product = wc_get_product($post);

        $vartable_categories_exc = get_option('vartable_categories_exc');
        $vartable_disabled = get_option('vartable_disabled');

        if (!empty($product)) {

            $product_cats = wp_get_post_terms($product->get_id(), 'product_cat');
            // get all ids
            $pcids = array();
            foreach ($product_cats as $pcdata) {
                $pcids[] = $pcdata->term_id;
            }


            //  if the table is disabled for this product display the default select menus
            $checkcat = array();
            if (is_array($vartable_categories_exc) && is_array($pcids)) {
                $checkcat = array_intersect($pcids, $vartable_categories_exc);
            }


            if (((get_post_meta($product->get_id(), 'disable_variations_table', true) == 1 || !empty($checkcat)) || $vartable_disabled == 1) && get_post_meta($product->get_id(), 'disable_variations_table', true) != 2) {

                // do nothing

            } else {

                remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
                add_action($position, 'vt_woocommerce_variable_add_to_cart', $priority);
            }
        }
    } else {

        remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
        add_action($position, 'vt_woocommerce_variable_add_to_cart', $priority);
    }
}

include('grid_options_page.php');


// delete data is checked, uncheck it
// delete all options 
// run vartable_activate again to get the defaults values.
add_action('admin_init', 'vartable_delete_data_while_active');

function vartable_delete_data_while_active()
{

    if (get_option('vartable_debug') == 'yes') {

        vartable_delete_data();
        vartable_activate();
        update_option('vartable_debug', 'no');
    }
}

// Clean up DB
add_action('admin_init', 'vartable_debug_dbcleanup');
function vartable_debug_dbcleanup()
{

    if (get_option('vartable_debug_dbcleanup') == 'yes') {

        update_option('vartable_debug_dbcleanup', 'no');

        global $wpdb;

        $q = "DELETE FROM $wpdb->postmeta WHERE 
				( meta_key = 'enbable_variations_table_img' AND meta_value = '' )
			OR ( meta_key = 'override_extra_image' AND meta_value = '' )
			OR ( meta_key = 'vt_variation_description' AND meta_value = '' )
			OR ( meta_key = 'vartable_qty_step' AND meta_value = '' )
			OR ( meta_key = 'vartable_qty_default' AND meta_value = '' )
			OR ( meta_key = 'vt_variation_hide' AND meta_value = '' )
		";

        $wpdb->get_results($q);
    }
}

function vartable_delete_data()
{

    $delete_options = vt_fields_func();

    if (get_option('vartable_debug_deactivation') == 'yes' || get_option('vartable_debug') == 'yes') {
        foreach ($delete_options as $field => $fieldtext) {
            delete_option($field);
        }
    }
}

register_deactivation_hook(__FILE__, 'vartable_delete_data');


function vartable_activate()
{

    $vartable_order = array(
        'vartable_sku' => 'SKU',
        'vartable_thumb' => 'Thumbnail',
        'vartable_stock' => 'Stock',
        'vartable_variations' => 'Variations',
        'vartable_price' => 'Price',
        'vartable_total' => 'Total',
        'vartable_offer' => 'Offer Image',
        'vartable_qty' => 'Quantity',
        'vartable_gift' => 'Gift Wrap',
        'vartable_wishlist' => 'Wishlist',
        'vartable_cart' => 'Add to Cart Button',
        'vartable_shp_class' => 'Shipping Class',
    );

    // set options only if they do not exist
    if (get_option('vartable_disabled') === false) {
        update_option('vartable_disabled', 0);
    }
    if (get_option('vartable_thumb') === false) {
        update_option('vartable_thumb', 1);
    }
    if (get_option('vartable_thumb_size') === false) {
        update_option('vartable_thumb_size', 80);
    }
    if (get_option('vartable_price') === false) {
        update_option('vartable_price', 1);
    }
    if (get_option('vartable_shp_class') === false) {
        update_option('vartable_shp_class', 0);
    }
    if (get_option('vartable_total') === false) {
        update_option('vartable_total', 0);
    }
    if (get_option('vartable_cart') === false) {
        update_option('vartable_cart', 1);
    }
    if (get_option('vartable_qty') === false) {
        update_option('vartable_qty', 1);
    }
    if (get_option('vartable_order') === false) {
        update_option('vartable_order', $vartable_order);
    }
    if (get_option('vartable_head') === false) {
        update_option('vartable_head', 1);
    }
    if (get_option('vartable_sorting') === false) {
        update_option('vartable_sorting', 1);
    }
    if (get_option('vartable_lightbox') === false) {
        update_option('vartable_lightbox', 1);
    }
    if (get_option('vartable_hide_mobile_empty') === false) {
        update_option('vartable_hide_mobile_empty', 1);
    }
    if (get_option('vartable_disable_mobile_layout') === false) {
        update_option('vartable_disable_mobile_layout', 0);
    }
    if (get_option('vartable_default_qty') === false) {
        update_option('vartable_default_qty', 1);
    }
    if (get_option('vartable_qty_control') === false) {
        update_option('vartable_qty_control', 0);
    }
    if (get_option('vartable_qty_control_style') === false) {
        update_option('vartable_qty_control_style', 1);
    }
    if (get_option('vartable_cart_icon') === false) {
        update_option('vartable_cart_icon', -1);
    }
    if (get_option('vartable_cart_notext') === false) {
        update_option('vartable_cart_notext', 0);
    }
    if (get_option('vartable_globalposition') === false) {
        update_option('vartable_globalposition', 'bottom');
    }
    if (get_option('vartable_globalcart_status') === false) {
        update_option('vartable_globalcart_status', 0);
    }
    if (get_option('vartable_desc_inline') === false) {
        update_option('vartable_desc_inline', '0');
    }
    if (get_option('vartable_weight') === false) {
        update_option('vartable_weight', '0');
    }
    if (get_option('vartable_dimensions') === false) {
        update_option('vartable_dimensions', '0');
    }
    if (get_option('vartable_position') === false) {
        update_option('vartable_position', 'side');
    }
    if (get_option('vartable_priority') === false) {
        update_option('vartable_priority', 30);
    }
    if (get_option('vartable_tax_sort') === false) {
        update_option('vartable_tax_sort', array());
    }
}
register_activation_hook(__FILE__, 'vartable_activate');



add_action('init', 'vt_yith_wcwtl_premium_init');
function vt_yith_wcwtl_premium_init()
{
    if (function_exists('YITH_WCWTL')) {
        include('integrations/yith-waiting-list.php');
    }
}

// create the shortcode
function vartable_func($atts)
{

    $a = shortcode_atts(array(
        'id' => 0,
        'disabled' => '',
        'categories_exc' => '',
        'roles_exc' => '',
        'categories' => '',
        'sku' => '',
        'thumb' => '',
        'thumb_size' => '',
        'stock' => '',
        'in_stock_text' => '',
        'backorder_text' => '',
        'backorder_style' => '',
        'out_stock_text' => '',
        'low_stock_text' => '',
        'low_stock_thresh' => '',
        'hide_zero' => '',
        'hide_outofstock' => '',
        'vartable_zero_to_out' => '',
        'price' => '',
        'total' => '',
        'offer' => '',
        'image' => '',
        'qty' => '',
        'default_qty' => '',
        'vartable_qty_control' => '',
        'vartable_qty_control_style' => '',
        'vartable_cart_icon' => '',
        'vartable_cart_notext' => '',
        'cart' => '',
        'globalcart' => '',
        'globalcart_status' => '',
        'globalposition' => '',
        'wishlist' => '',
        'gift' => '',
        'order' => '',
        'ajax' => '',
        'desc' => '',
        'weight' => '',
        'dimensions' => '',
        'vartable_position' => '',
        'vartable_priority' => '',
        'vartable_tax_sort' => '',
        'desc_inline' => '',
        'head' => '',
        'customhead' => '',
        'sorting' => '',
        'shortcd' => 1,
        'title' => 0,
        'vartable_shp_class' => 0
    ), $atts);

    return (vt_woocommerce_variable_add_to_cart($a));
}
add_shortcode('vartable', 'vartable_func');

function vartableall_func($atts)
{

    $a = shortcode_atts(array(
        'disabled' => '',
        'categories_exc' => '',
        'roles_exc' => '',
        'categories' => '',
        'sku' => '',
        'thumb' => '',
        'thumb_size' => '',
        'stock' => '',
        'in_stock_text' => '',
        'backorder_text' => '',
        'backorder_style' => '',
        'out_stock_text' => '',
        'low_stock_text' => '',
        'low_stock_thresh' => '',
        'hide_zero' => '',
        'hide_outofstock' => '',
        'vartable_zero_to_out' => '',
        'price' => '',
        'total' => '',
        'offer' => '',
        'image' => '',
        'qty' => '',
        'default_qty' => '',
        'vartable_qty_control' => '',
        'vartable_qty_control_style' => '',
        'vartable_cart_icon' => '',
        'vartable_cart_notext' => '',
        'cart' => '',
        'globalcart' => '',
        'globalcart_status' => '',
        'globalposition' => '',
        'wishlist' => '',
        'gift' => '',
        'order' => '',
        'ajax' => '',
        'desc' => '',
        'weight' => '',
        'dimensions' => '',
        'vartable_position' => '',
        'vartable_priority' => '',
        'vartable_tax_sort' => '',
        'desc_inline' => '',
        'head' => '',
        'customhead' => '',
        'sorting' => '',
        'shortcd' => 1,
        'title' => 0,
        'vartable_shp_class' => 0
    ), $atts);

    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'product',
        'status' => 'publish',
        'fields' => 'ids'
    );

    if ($a['hide_zero'] == 1 || ($a['hide_zero'] == '' && get_option('vartable_hide_zero') == 1)) {
        $args['meta_key'] = '_min_price_variation_id';
        $args['meta_value'] = 0;
        $args['meta_compare'] = '>';
    }

    $args = apply_filters('vartableall_query_args', $args, $a);

    if (!empty($a['categories'])) {

        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id', //This is optional, as it defaults to 'term_id'
                'terms' => explode(',', $a['categories']),
                'operator' => 'IN'
                // Possible values are 'IN', 'NOT IN', 'AND'.

            )
        );
    }

    $posts_array = get_posts($args);

    $alltables = '';

    foreach ($posts_array as $post_id) {

        $a['id'] = $post_id;
        $table_out = vt_woocommerce_variable_add_to_cart($a);
        if (!empty(trim($table_out))) {
            $alltables .= $table_out . '<hr class="clearfix vartablesplit" />';
        }
    }

    return ($alltables);
}
add_shortcode('vartableall', 'vartableall_func');

function vt_woocommerce_variable_add_to_cart($allsets)
{

    global $product, $post, $woocommerce;

    $out = '';
    $form = '';
    $vtrand = rand(1, 100000);

    // get values from shortcode
    if ($allsets) {
        $vartable_id = $allsets['id'];
        $vartable_disabled = $allsets['disabled'];
        $vartable_categories_exc = $allsets['categories_exc'];
        $vartable_roles_exc = $allsets['roles_exc'];
        $vartable_categories = $allsets['categories'];
        $vartable_sku = $allsets['sku'];
        $vartable_thumb = $allsets['thumb'];
        $vartable_thumb_size = $allsets['thumb_size'];
        $vartable_stock = $allsets['stock'];
        $vartable_in_stock_text = $allsets['in_stock_text'];
        $vartable_backorder_text = $allsets['backorder_text'];
        $vartable_backorder_style = $allsets['backorder_style'];
        $vartable_out_stock_text = $allsets['out_stock_text'];
        $vartable_low_stock_text = $allsets['low_stock_text'];
        $vartable_low_stock_thresh = $allsets['low_stock_thresh'];
        $vartable_hide_zero = $allsets['hide_zero'];
        $vartable_hide_outofstock = $allsets['hide_outofstock'];
        $vartable_zero_to_out = $allsets['vartable_zero_to_out'];
        $vartable_price = $allsets['price'];
        $vartable_total = $allsets['total'];
        $vartable_offer = $allsets['offer'];
        $vartable_image = $allsets['image'];
        $vartable_qty = $allsets['qty'];
        $vartable_default_qty = $allsets['default_qty'];
        $vartable_qty_control = $allsets['vartable_qty_control'];
        $vartable_qty_control_style = $allsets['vartable_qty_control_style'];
        $vartable_cart_icon = $allsets['vartable_cart_icon'];
        $vartable_cart_notext = $allsets['vartable_cart_notext'];
        $vartable_cart = $allsets['cart'];
        $vartable_globalcart = $allsets['globalcart'];
        $vartable_globalcart_status = $allsets['globalcart'];
        $vartable_globalposition = $allsets['globalposition'];
        $vartable_wishlist = $allsets['wishlist'];
        $vartable_gift = $allsets['gift'];
        $vartable_order = $allsets['order'];
        $vartable_ajax = $allsets['ajax'];
        $vartable_desc = $allsets['desc'];
        $vartable_weight = $allsets['weight'];
        $vartable_dimensions = $allsets['dimensions'];
        $vartable_position = $allsets['vartable_position'];
        $vartable_priority = $allsets['vartable_priority'];
        $vartable_desc_inline = $allsets['desc_inline'];
        $vartable_head = $allsets['head'];
        $vartable_customhead = $allsets['customhead'];
        $vartable_sorting = $allsets['sorting'];
        $vartable_tax_sort = $allsets['vartable_tax_sort'];
        $vartable_shortcd = $allsets['shortcd'];
        $vartable_title = $allsets['title'];
        $vartable_shp_class = $allsets['vartable_shp_class'];
    } else {
        $vartable_id = null;
        $vartable_disabled = null;
        $vartable_categories_exc = null;
        $vartable_roles_exc = null;
        $vartable_categories = null;
        $vartable_sku = null;
        $vartable_thumb = null;
        $vartable_thumb_size = null;
        $vartable_stock = null;
        $vartable_in_stock_text = null;
        $vartable_backorder_text = null;
        $vartable_backorder_style = null;
        $vartable_out_stock_text = null;
        $vartable_low_stock_text = null;
        $vartable_low_stock_thresh = null;
        $vartable_hide_zero = null;
        $vartable_hide_outofstock = null;
        $vartable_zero_to_out = null;
        $vartable_price = null;
        $vartable_total = null;
        $vartable_offer = null;
        $vartable_image = null;
        $vartable_qty = null;
        $vartable_default_qty = null;
        $vartable_qty_control = null;
        $vartable_qty_control_style = null;
        $vartable_cart_icon = null;
        $vartable_cart_notext = null;
        $vartable_cart = null;
        $vartable_globalcart = null;
        $vartable_globalcart_status = null;
        $vartable_globalposition = null;
        $vartable_wishlist = null;
        $vartable_gift = null;
        $vartable_order = null;
        $vartable_ajax = null;
        $vartable_desc = null;
        $vartable_weight = null;
        $vartable_dimensions = null;
        $vartable_position = null;
        $vartable_priority = null;
        $vartable_desc_inline = null;
        $vartable_head = null;
        $vartable_customhead = null;
        $vartable_sorting = null;
        $vartable_tax_sort = null;
        $vartable_shortcd = null;
        $vartable_title = null;
        $vartable_shp_class = null;
    }

    $vartable_lightbox                 = get_option('vartable_lightbox');
    $vartable_hide_mobile_empty     = get_option('vartable_hide_mobile_empty');
    $vartable_disable_mobile_layout = get_option('vartable_disable_mobile_layout');

    // check if it is a shortcode and if an id has been set
    if ($vartable_id != null) {
        $product = wc_get_product($vartable_id);
    }

    if (!is_object($product)) {
        if (current_user_can('editor') || current_user_can('administrator')) {
            return (__('Admin only message: no product object found', 'vartable'));
        }
        return false;
    }

    if ($product->get_type() != 'variable') {
        if (current_user_can('editor') || current_user_can('administrator')) {
            return (__('Admin only message: this is not a variable product', 'vartable') . ' | ' . $product->get_title());
        }
        return false;
    }

    $product_cats = wp_get_post_terms($product->get_id(), 'product_cat');
    // get all ids
    foreach ($product_cats as $pcdata) {
        $pcids[] = $pcdata->term_id;
    }

    // make sure that quantity is set
    if (get_option('vartable_default_qty') === false) {
        update_option('vartable_default_qty', 1);
    }
    if (get_option('vartable_qty_control') === false) {
        update_option('vartable_qty_control', 0);
    }
    if (get_option('vartable_qty_control_style') === false) {
        update_option('vartable_qty_control_style', 1);
    }
    if (get_option('vartable_cart_icon') === false) {
        update_option('vartable_cart_icon', -1);
    }
    if (get_option('vartable_cart_notext') === false) {
        update_option('vartable_cart_notext', 0);
    }
    if (get_option('vartable_lightbox') === false) {
        update_option('vartable_lightbox', 1);
    }
    if (get_option('vartable_hide_mobile_empty') === false) {
        update_option('vartable_hide_mobile_empty', 1);
    }
    if (get_option('vartable_disable_mobile_layout') === false) {
        update_option('vartable_disable_mobile_layout', 0);
    }

    $vartable_disabled = ($vartable_disabled == null ? get_option('vartable_disabled') : $vartable_disabled);
    $vartable_categories_exc = ($vartable_categories_exc == null ? get_option('vartable_categories_exc') : $vartable_categories_exc);
    $vartable_roles_exc = ($vartable_roles_exc == null ? get_option('vartable_roles_exc') : $vartable_roles_exc);
    $vartable_sku = ($vartable_sku == null ? get_option('vartable_sku') : $vartable_sku);
    $vartable_thumb = ($vartable_thumb == null ? get_option('vartable_thumb') : $vartable_thumb);
    $vartable_thumb_size = ($vartable_thumb_size == null ? get_option('vartable_thumb_size') : $vartable_thumb_size);
    $vartable_stock = ($vartable_stock == null ? get_option('vartable_stock') : $vartable_stock);
    $vartable_in_stock_text = ($vartable_in_stock_text == null ? get_option('vartable_in_stock_text') : $vartable_in_stock_text);
    $vartable_backorder_text = ($vartable_backorder_text == null ? get_option('vartable_backorder_text') : $vartable_backorder_text);
    $vartable_backorder_style = ($vartable_backorder_style == null ? get_option('vartable_backorder_style') : $vartable_backorder_style);
    $vartable_out_stock_text = ($vartable_out_stock_text == null ? get_option('vartable_out_stock_text') : $vartable_out_stock_text);
    $vartable_low_stock_text = ($vartable_low_stock_text == null ? get_option('vartable_low_stock_text') : $vartable_low_stock_text);
    $vartable_low_stock_thresh = ($vartable_low_stock_thresh == null ? get_option('vartable_low_stock_thresh') : $vartable_low_stock_thresh);
    $vartable_hide_zero = ($vartable_hide_zero == null ? get_option('vartable_hide_zero') : $vartable_hide_zero);
    $vartable_hide_outofstock = ($vartable_hide_outofstock == null ? get_option('vartable_hide_outofstock') : $vartable_hide_outofstock);
    $vartable_zero_to_out = ($vartable_zero_to_out == null ? get_option('vartable_zero_to_out') : $vartable_zero_to_out);
    $vartable_price = ($vartable_price == null ? get_option('vartable_price') : $vartable_price);
    $vartable_total = ($vartable_total == null ? get_option('vartable_total') : $vartable_total);
    $vartable_offer = ($vartable_offer == null ? get_option('vartable_offer') : $vartable_offer);
    $vartable_image = ($vartable_image == null ? get_option('vartable_image') : $vartable_image);
    $vartable_qty = ($vartable_qty == null ? get_option('vartable_qty') : $vartable_qty);
    $vartable_default_qty = ($vartable_default_qty == null ? get_option('vartable_default_qty') : $vartable_default_qty);
    $vartable_qty_control = ($vartable_qty_control == null ? get_option('vartable_qty_control') : $vartable_qty_control);
    $vartable_qty_control_style = ($vartable_qty_control_style == null ? get_option('vartable_qty_control_style') : $vartable_qty_control_style);
    $vartable_cart_icon = ($vartable_cart_icon == null ? get_option('vartable_cart_icon') : $vartable_cart_icon);
    $vartable_cart_notext = ($vartable_cart_notext == null ? get_option('vartable_cart_notext') : $vartable_cart_notext);
    $vartable_cart = ($vartable_cart == null ? get_option('vartable_cart') : $vartable_cart);
    $vartable_globalcart = ($vartable_globalcart == null ? get_option('vartable_globalcart') : $vartable_globalcart);
    $vartable_globalcart_status = ($vartable_globalcart_status == null ? get_option('vartable_globalcart_status') : $vartable_globalcart_status);
    $vartable_globalposition = ($vartable_globalposition == null ? get_option('vartable_globalposition') : $vartable_globalposition);
    $vartable_wishlist = ($vartable_wishlist == null ? get_option('vartable_wishlist') : $vartable_wishlist);
    $vartable_gift = ($vartable_gift == null ? get_option('vartable_gift') : $vartable_gift);
    $vartable_order = ($vartable_order == null ? get_option('vartable_order') : $vartable_order);
    $vartable_ajax = ($vartable_ajax == null ? get_option('vartable_ajax') : $vartable_ajax);
    $vartable_desc = ($vartable_desc == null ? get_option('vartable_desc') : $vartable_desc);
    $vartable_weight = ($vartable_weight == null ? get_option('vartable_weight') : $vartable_weight);
    $vartable_dimensions = ($vartable_dimensions == null ? get_option('vartable_dimensions') : $vartable_dimensions);
    $vartable_position = ($vartable_position == null ? get_option('vartable_position') : $vartable_position);
    $vartable_priority = ($vartable_priority == null ? get_option('vartable_priority') : $vartable_priority);
    $vartable_desc_inline = ($vartable_desc_inline == null ? get_option('vartable_desc_inline') : $vartable_desc_inline);
    $vartable_head = ($vartable_head == null ? get_option('vartable_head') : $vartable_head);
    $vartable_customhead = ($vartable_customhead == null ? get_option('vartable_customhead') : $vartable_customhead);
    $vartable_sorting = ($vartable_sorting == null ? get_option('vartable_sorting') : $vartable_sorting);
    $vartable_tax_sort = ($vartable_tax_sort == null ? get_option('vartable_tax_sort') : $vartable_tax_sort);
    $vartable_shp_class = ($vartable_shp_class == null ? get_option('vartable_shp_class') : $vartable_shp_class);

    if (!class_exists('WC_Product_Gift_Wrap')) {
        $vartable_gift = 0;
    }

    // gift wrap option
    $default_message = '{checkbox} ' . sprintf(__('Gift wrap this item for %s?', 'woocommerce-product-gift-wrap'), '{price}');
    $gift_wrap_enabled = get_option('product_gift_wrap_enabled') == 'yes' ? true : false;
    $gift_wrap_cost = get_option('product_gift_wrap_cost', 0);
    $product_gift_wrap_message = get_option('product_gift_wrap_message');

    if (!$product_gift_wrap_message) {
        $product_gift_wrap_message = $default_message;
    }

    $is_wrappable = get_post_meta($product->get_id(), '_is_gift_wrappable', true);

    if ($is_wrappable == '' && $gift_wrap_enabled) {
        $is_wrappable = 'yes';
    }

    //  if the table is disabled for this product display the default select menus
    $checkcat = array();
    if (is_array($vartable_categories_exc) && is_array($pcids)) {
        $checkcat = array_intersect($pcids, $vartable_categories_exc);
    }

    $checkrole = array();
    if (is_array($vartable_roles_exc) && is_user_logged_in()) {
        $user_info = get_userdata(get_current_user_id());
        $checkrole = array_intersect($user_info->roles, $vartable_roles_exc);
    }
    if (!is_user_logged_in() && is_array($vartable_roles_exc) && in_array('guest', $vartable_roles_exc)) {
        $checkrole['guest'] = 'guest';
    }

    if (((get_post_meta($product->get_id(), 'disable_variations_table', true) == 1 || !empty($checkcat)) || $vartable_disabled == 1 || !empty($checkrole)) && get_post_meta($product->get_id(), 'disable_variations_table', true) != 2 && $vartable_shortcd != 1) {
        // Enqueue variation scripts
        wp_enqueue_script('wc-add-to-cart-variation');

        // Load the template
        wc_get_template('single-product/add-to-cart/variable.php', array(
            'available_variations' => $product->get_available_variations(),
            'attributes' => $product->get_variation_attributes(),
            'selected_attributes' => $product->get_default_attributes()
        ));
        return;
    }

    if (method_exists($product, 'get_variation_attributes')) {
        $varattr = $product->get_variation_attributes();
    } else {
        if (current_user_can('editor') || current_user_can('administrator')) {
            return (apply_filters('vartable_novariable_warning', __('Admin only message: this is not a variable product', 'vartable'), $product));
        }
        return;
    }

    $temp_varattr = array();
    foreach ($varattr as $attr_key => $attr_values) {
        $temp_varattr[sanitize_title($attr_key)] = $attr_values;
    }

    $varattr = array();
    $varattr = $temp_varattr;

    $default_feat_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id(), array(
        $vartable_thumb_size,
        $vartable_thumb_size
    )));
    $default_feat_imagefull = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'full');

    $variations = $product->get_available_variations();

    if (empty($variations)) {
        $out = (__('Now go add some variable products!', 'vartable'));
        if ($vartable_shortcd != 1) {
            echo '<p>' . $out . '</p>';
            return;
        } else {
            return ($out);
        }
    }

    // prepare arrays to use that will hold data
    $anyvariations = array();
    $standardvariations = array();
    $fullvariations = array();

    // get all attributes that are valid
    $attr_keys = array_keys($product->get_attributes());
    $attr_keys2 = array();
    foreach ($attr_keys as $atkey) {
        $attr_keys2[] = str_replace(array(
            'attribute_',
            'pa_'
        ), array(
            '',
            ''
        ), $atkey);
    }

    foreach ($variations as $key => $value) {

        foreach ($value['attributes'] as $attr_key => $attr_value) {

            // skip attributes that are empty and have not been set, mostly created by import plugins
            if (!in_array(str_replace(array(
                'attribute_',
                'pa_'
            ), array(
                '',
                ''
            ), $attr_key), $attr_keys2)) {
                continue;
            }

            if ($attr_value == '') {
                $anyvariations[$key][$value['variation_id']][str_replace('attribute_', '', $attr_key)] = $varattr[str_replace('attribute_', '', $attr_key)];
            } else {
                $standardvariations[$key][$value['variation_id']][str_replace('attribute_', '', $attr_key)] = array($attr_value);
            }
        }
    }

    // get variations that have all attributes set
    $fullvariations = array_diff_key($standardvariations, $anyvariations);

    $combinationarray = array();
    $combinationsresults = array();
    foreach ($anyvariations as $attrkey => $variat) {
        reset($variat);
        $variationid = key($variat);
        $countvariations = count($variat[$variationid]);
        if (isset($standardvariations[$attrkey]) && isset($variat[$variationid]) && is_array($standardvariations[$attrkey]) && is_array($variat[$variationid])) {
            $combinationarray[$attrkey] = $standardvariations[$attrkey][$variationid] + $variat[$variationid];
        }
        if (!is_array($variat[$variationid])) {
            $combinationarray[$attrkey] = $standardvariations[$attrkey][$variationid];
        }
        if (isset($standardvariations[$attrkey][$variationid]) && !is_array($standardvariations[$attrkey][$variationid])) {
            $combinationarray[$attrkey] = $variat[$variationid];
        }
    }

    // get all possible combinations for the attributes set as any
    foreach ($combinationarray as $attrkey => $combarrays) {
        // more than one attribute is set on the variation
        if (count($combarrays) > 1) {
            $combinationsresults[$attrkey] = vartable_combinations($combarrays);
        } else { // ONLY ONE attribute is set on the variation
            if (is_array($combarrays)) {
                foreach ($combarrays as $singleattkey => $singleattval) {
                    if (is_array($singleattval)) {
                        foreach ($singleattval as $thesingleattr) {
                            $combinationsresults[$attrkey][][$singleattkey] = $thesingleattr;
                        }
                    }
                }
            }
        }
    }

    $finalattr = array();
    $i = 0;

    // add variations that have all attributes set to the final array
    foreach ($fullvariations as $fkey => $fvalue) {
        $finalattr[$i] = $variations[$fkey];
        $i++;
    }

    // add variations that have attributes set as any the final array
    foreach ($combinationsresults as $attrkey => $combinations) {

        foreach ($combinations as $combinationsvalues) {
            $finalattr[$i] = $variations[$attrkey];
            if (is_array($combinationsvalues)) {
                foreach ($combinationsvalues as $attrshortkey => $attrstringvalue) {
                    $finalattr[$i]['attributes']['attribute_' . $attrshortkey] = $attrstringvalue;
                }
            }
            // if (!is_array($combinationsvalues)) {
            // have to check this
            // }
            $i++;
        }
    }

    $attr_ordernames = array();
    foreach ($product->get_attributes() as $ankey => $akval) {
        if ($akval['is_variation'] == 1) {
            $attr_ordernames[str_replace(array(
                '_',
                '.',
                ' '
            ), array(
                '-',
                '-',
                '-'
            ), sanitize_title(str_replace('attribute_', '', $ankey)))] = wc_attribute_label($akval['name']);
        }
    }

    // order of attributes
    $attr_order = array();
    foreach ($varattr as $attrkey => $attrvalues) {
        $attr_order[] = 'attribute_' . sanitize_title($attrkey);
    }

    $anyextraimg = 0;
    $anydescription = 0;
    $anydimension = 0;
    $anyweight = 0;
    $anyshpclass = 0;
    $head = '';

    ob_start();
    do_action('woocommerce_before_add_to_cart_form', $product->get_id());
    $woocommerce_before_add_to_cart_form = ob_get_clean();
    $out .= $woocommerce_before_add_to_cart_form;

    ob_start();
    do_action('vartable_before_table', $product->get_id());
    $vartable_before_table = ob_get_clean();
    $out .= $vartable_before_table;

    ob_start();
    do_action('vartable_table_class', $product->get_id());
    $vartable_table_class = ob_get_clean();

    $sorting_js = apply_filters('vartable_sorting_js', get_option('vartable_sorting'), $product->get_id());
    $sorting_col = apply_filters('vartable_sorting_column', get_post_meta($product->get_id(), 'custom_variations_preordering', true), $product->get_id());
    $sorting_direction = apply_filters('vartable_sorting_direction', get_post_meta($product->get_id(), 'custom_variations_preordering_direction', true), $product->get_id());

    if (($vartable_globalcart == 1 || $vartable_globalcart == 2) && ($vartable_globalposition == 'top' || $vartable_globalposition == 'both')) {

        ob_start();
        do_action('vartable_add_gc_button', $product->get_id());
        $vartable_add_gc_button = ob_get_clean();

        if ($vartable_title == 1) {
            $out .= '<h3><a href="' . get_permalink($product->get_id()) . '" title="' . get_the_title($product->get_id()) . '">' . get_the_title($product->get_id()) . '</a></h3>';
        }

        $vt_button_classes = apply_filters('vartable_global_button_classes', array(
            'single_add_to_cart_button',
            'btn',
            'button',
            'button_theme',
            'ajax',
            'add_to_cart',
            'avia-button',
            'fusion-button',
            'button-flat',
            'button-round',
            'alt'
        ));

        $out .= apply_filters('vartable_global_btn', '
        <div class="vartable_gc_wrap vartable_gc_wrap_top">
          <a data-position="top" href="#globalcart" class="globalcartbtn submit ' . implode(' ', $vt_button_classes) . '" data-product_id="gc_' . $product->get_id() . '" id="gc_' . $vtrand . '_top" class="btn button alt">' . __('Add selected to cart', 'vartable') . '<span class="vt_products_count"></span></a>
          <span class="added2cartglobal added2cartglobal_' . $vtrand . '">&#10003;</span>
          <span class="vtspinner vtspinner_top vtspinner_' . $vtrand . '"><img src="' . plugins_url('images/spinner.png', __FILE__) . '" width="16" height="16" alt="spinner" /></span>
        </div>
      ', $product, 'top', $vtrand);
    } else {
        if ($vartable_title == 1) {
            $out .= '<h3><a href="' . get_permalink($product->get_id()) . '" title="' . get_the_title($product->get_id()) . '">' . get_the_title($product->get_id()) . '</a></h3>';
        }
    }

    $cartredirect = get_option('woocommerce_cart_redirect_after_add');

    $out .= '
	<div class="' . ($vartable_disable_mobile_layout == 1 ? 'disable_responsive' : 'enable_responsive') . '_wrap">
    <table 
      id="tb_' . $vtrand . '" 
      class="table vartable ' . ($sorting_js == 1 ? 'is_sortable' : '') . ' ' . ($vartable_hide_mobile_empty == 1 ? 'should_hide_mobile_empty' : '') . ' ' . ($vartable_head == 0 ? 'should_hide_mobile_header' : '') . ' ' . ($vartable_disable_mobile_layout == 1 ? 'disable_responsive' : 'enable_responsive') . ' ' . $vartable_table_class . '" 
      data-random="' . $vtrand . '" 
      ' . ($sorting_js == 1 ? 'data-sort="yes"' : 'data-sort="no"') . ' 
      ' . ($vartable_ajax == 1 ? 'data-vartable_ajax="1"' : 'data-vartable_ajax="0"') . ' 
      ' . ($cartredirect == 'yes' ? 'data-cartredirect="yes"' : 'data-cartredirect="no"') . ' 
      data-globalcart="' . $vartable_globalcart . '"
      ' . ($sorting_col != '' ? 'data-preorder="' . $sorting_col . '"' : 'data-preorder=""') . ' 
      ' . ($sorting_direction != '' ? 'data-preorder_direction="' . $sorting_direction . '"' : 'data-preorder_direction=""') . ' 
      >
    
      %headplaceholder%
    ';

    $out .= '<tbody>
      ';

    // echo vtdb($finalattr);


    // get order / position of the variations to display the table rows
    $moi = 0;
    $mo_reset = 0;
    $menu_order = array();
    foreach ($finalattr as $vt_variation_array) {
        $vt_variation_object = get_post($vt_variation_array['variation_id']);

        if ($mo_reset != $vt_variation_object->menu_order && $vt_variation_object->menu_order != 0) {
            $mo_reset = $vt_variation_object->menu_order;
            $moi = 0;
        }
        $menu_order[$vt_variation_array['variation_id']] = ($vt_variation_object->menu_order * 100) + $moi++;
    }

    $ri = 0;
    $vt_trows = $allcolumns = array();
    $data_sort_value_custom = array();

    foreach ($finalattr as $key => $value) {

        // create an array to hold all TDs
        $allcolumns = array();

        $product_variation = new WC_Product_Variation($value['variation_id']);

        $vartable_qty_default = get_post_meta($value['variation_id'], 'vartable_qty_default', true);

        if (get_post_meta($value['variation_id'], 'vt_variation_hide', true) == 'yes') {
            continue;
        }
        if (!($product_variation->get_price() > 0) && $vartable_hide_zero == 1) {
            continue;
        }

        $varstock = $product_variation->get_stock_quantity();

        if (!($varstock > 0) && $vartable_hide_outofstock == 1) {
            continue;
        }

        if (!isset($vt_trows[$menu_order[$value['variation_id']]])) {
            $vt_trows[$menu_order[$value['variation_id']]] = '';
        }

        ob_start();
        do_action('woocommerce_before_single_variation', $product->get_id(), $value);
        $woocommerce_before_single_variation = ob_get_clean();

        ob_start();
        do_action('vartable_inside_add_to_cart_form', $product->get_id(), $value);
        $vartable_inside_add_to_cart_form = ob_get_clean();

        $vt_trows[$menu_order[$value['variation_id']]] .= $woocommerce_before_single_variation . '
        <tr 
          class="' . $value['variation_id'] . ' ' . (get_post_meta($value['variation_id'], '_stock_status', true) != 'outofstock' ? 'instock' : 'outofstock') . ' ' . ($product_variation->is_purchasable() ? 'is_purchasable' : '') . '" 
          data-price="' . ($product_variation->get_price() !== '' ? wc_format_decimal(wc_get_price_to_display($product_variation), 2) : '') . '">
          ';

        $form .= '
		<form action="' . esc_url($product->add_to_cart_url()) . '" method="POST" data-variation_id="' . $value['variation_id'] . $ri . '" id="vtvariation_' . $value['variation_id'] . $ri . '" class="vtajaxform" enctype="multipart/form-data">
			<input type="hidden" name="variation_id" value="' . $value['variation_id'] . '" />
			<input type="hidden" name="product_id" value="' . esc_attr($product->get_id()) . '" />
			<input type="hidden" name="add-to-cart" value="' . esc_attr($product->get_id()) . '" />
      ' . $vartable_inside_add_to_cart_form . '
      ';

        if ($product_variation->is_purchasable() && ($product_variation->is_in_stock() || $product_variation->backorders_allowed())) {
            $form .= '<input type="hidden" class="hidden_quantity" name="quantity" value="' . ($vartable_qty_default > 0 ? $vartable_qty_default : apply_filters('vartable_default_qty', $vartable_default_qty, $value)) . '" />';
        }

        $form .= '<input type="hidden" class="gift_wrap" name="gift_wrap" value="" />';

        $js_attr_arr = array();
        $js_attr_arr_full = array();
        if (!empty($value['attributes'])) {
            foreach ($value['attributes'] as $attr_key => $attr_value) {
                if ($attr_value != '') {

                    $form .= '<input type="hidden" class="form_vartable_attribute" name="' . $attr_key . '" value="' . $attr_value . '" />
            ';

                    $js_attr_arr[str_replace(array(
                        'attribute_pa_',
                        'attribute_'
                    ), array(
                        '',
                        ''
                    ), $attr_key)] = htmlentities(str_replace('"', '||||||', $attr_value), ENT_QUOTES, 'utf-8', FALSE);
                    $js_attr_arr_full[$attr_key] = htmlentities(str_replace('"', '||||||', $attr_value), ENT_QUOTES, 'utf-8', FALSE);
                }
            }

            $form .= '<input type="hidden" class="form_vartable_attribute_json" name="form_vartable_attribute_json" value=\'' . (json_encode($js_attr_arr)) . '\' />
            ';
            $form .= '<input type="hidden" class="form_vartable_attribute_array" name="form_vartable_attribute_array" value=\'' . (json_encode($js_attr_arr_full)) . '\' />
            ';
        }

        $headenames = vt_fields_func();

        if ($vartable_sku == 1) {
            $allcolumns['vartable_sku'] = '<td class="skucol ' . (empty($value['sku']) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_sku', $headenames['vartable_sku'], $value) . '" data-sort-value="' . wc_clean(trim($value['sku'])) . '">' . $value['sku'] . '</td>';
        }

        if ($vartable_thumb == 1) {
            $rowimg = '';
            $var_feat_image = wp_get_attachment_image_src(get_post_thumbnail_id($value['variation_id']), array(
                $vartable_thumb_size,
                $vartable_thumb_size
            ));
            $var_feat_image_full = wp_get_attachment_image_src(get_post_thumbnail_id($value['variation_id']), 'full');
            if (!empty($var_feat_image)) {
                $rowimg = $var_feat_image;
            } else {
                $rowimg = $default_feat_image;
            }

            if (!empty($var_feat_image_full)) {
                $rowimgfull = $var_feat_image_full;
            } else {
                $rowimgfull = $default_feat_imagefull;
            }

            if (isset($rowimg[0])) {

                if ($vartable_lightbox != 2) {


                    if (function_exists('woo_variation_gallery')) {

                        $wg_product_variation = woo_variation_gallery()->get_frontend()->get_available_variation($product->get_id(), $value['variation_id']);

                        if (isset($wg_product_variation['variation_gallery_images'])) {

                            array_shift($wg_product_variation['variation_gallery_images']);

                            $variation_gallery = '';
                            if (!empty($wg_product_variation['variation_gallery_images'])) {



                                foreach ($wg_product_variation['variation_gallery_images'] as $wg_attachment) {

                                    $variation_gallery .= '<a href="' . $wg_attachment['url'] . '" title="' . $wg_attachment['title'] . '" data-fancybox="vartable_gallery_' . $value['variation_id'] . '_' . $ri . '" class="wg_hidden variationimg vartable_zoom thumb"><img src="' . $wg_attachment['gallery_thumbnail_src'] . '" alt="' . $wg_attachment['title'] . ' - ' . implode(' - ', $value['attributes']) . '" width="' . $wg_attachment['gallery_thumbnail_src_w'] . '" height="' . $wg_attachment['gallery_thumbnail_src_h'] . '" class="' . $wg_attachment['gallery_thumbnail_class'] . '" /></a>';
                                }
                            }

                            // $allcolumns['vartable_thumb'] = '<td class="thumbcol ' . (empty($rowimg[0]) ? 'vtmobilehide' : '') . '"  data-label="' . apply_filters('vartable_dl_thumb', $headenames['vartable_thumb'], $value) . '">
                            //   <a href="' . $rowimgfull[0] . '" itemprop="image" class="variationimg vartable_zoom ' . apply_filters('vartable_thumb_class_filter', 'thumb', $value) . '" title="' . $product->get_title() . ' - ' . implode(' - ', $value['attributes']) . '"  data-fancybox="vartable_gallery_' . $value['variation_id'] . '_' . $ri . '">
                            // 	<img src="' . $rowimg[0] . '" alt="' . $product->get_title() . ' - ' . implode(' - ', $value['attributes']) . '" width="' . $rowimg[1] . '" height="' . $rowimg[2] . '" style="width: ' . $vartable_thumb_size . 'px; height: auto;" />
                            //   </a>
                            //   ' . $variation_gallery .
                            '</td>';
                        }
                    } else {

                        // $allcolumns['vartable_thumb'] = '<td class="thumbcol ' . (empty($rowimg[0]) ? 'vtmobilehide' : '') . '"  data-label="' . apply_filters('vartable_dl_thumb', $headenames['vartable_thumb'], $value) . '">
                        //   <a href="' . $rowimgfull[0] . '" itemprop="image" class="variationimg vartable_zoom ' . apply_filters('vartable_thumb_class_filter', 'thumb', $value) . '" title="' . $product->get_title() . ' - ' . implode(' - ', $value['attributes']) . '"  data-fancybox="vartable_gallery_' . $product->get_id() . '">
                        // 	<img src="' . $rowimg[0] . '" alt="' . $product->get_title() . ' - ' . implode(' - ', $value['attributes']) . '" width="' . $rowimg[1] . '" height="' . $rowimg[2] . '" style="width: ' . $vartable_thumb_size . 'px; height: auto;" />
                        //   </a>' .
                        '</td>';
                    }
                } else {

                    $allcolumns['vartable_thumb'] = '<td class="thumbcol ' . (empty($rowimg[0]) ? 'vtmobilehide' : '') . '"  data-label="' . apply_filters('vartable_dl_thumb', $headenames['vartable_thumb'], $value) . '">
						<img src="' . $rowimg[0] . '" alt="' . $product->get_title() . ' - ' . implode(' - ', $value['attributes']) . '" width="' . $rowimg[1] . '" height="' . $rowimg[2] . '" style="width: ' . $vartable_thumb_size . 'px; height: auto;" />
					  
					</td>';
                }
            } else {
                $allcolumns['vartable_thumb'] = '<td class="thumbcol" data-label="' . apply_filters('vartable_dl_thumb', $headenames['vartable_thumb'], $value) . '">
                  ' . apply_filters('woocommerce_single_product_image_html', sprintf('<img src="%s" alt="%s" style="width: ' . $vartable_thumb_size . 'px; height: auto;" />', wc_placeholder_img_src(), __('Placeholder', 'woocommerce')), $product->get_id()) . '
                  </td>';
            }
        }
?>

        <?php

        if ($vartable_stock == 1) {

            if (
                absint($vartable_low_stock_thresh) > 0
                &&     $varstock < absint($vartable_low_stock_thresh)
                &&     get_post_meta($value['variation_id'], '_manage_stock', true) == 'yes'
                &&     !$product_variation->is_on_backorder()
            ) {
                $allcolumns['vartable_stock'] = '<td class="stockcol ' . $vartable_backorder_style . '" data-label="' . apply_filters('vartable_dl_stock', $headenames['vartable_stock'], $value) . '">' . (get_post_meta($value['variation_id'], '_stock_status', true) != 'outofstock' && $varstock > 0 ? '<span class="lowstock">' . str_replace('%n', $varstock, __($vartable_low_stock_text, 'vartable')) . '</span>' : '<span class="outofstock">' . __($vartable_out_stock_text, 'vartable') . '</span>') . '</td>';
            } elseif ($product_variation->is_on_backorder()) {

                $allcolumns['vartable_stock'] = '<td class="stockcol ' . $vartable_backorder_style . '" data-label="' . apply_filters('vartable_dl_stock', $headenames['vartable_stock'], $value) . '"><span class="backorder">' . __($vartable_backorder_text, 'vartable') . '</span></td>';
            } else {
                $allcolumns['vartable_stock'] = '<td class="stockcol ' . $vartable_backorder_style . '" data-label="' . apply_filters('vartable_dl_stock', $headenames['vartable_stock'], $value) . '">' . (get_post_meta($value['variation_id'], '_stock_status', true) != 'outofstock' ? '<span class="instock">' . str_replace('%n', $varstock, __($vartable_in_stock_text, 'vartable')) . '</span>' : '<span class="outofstock">' . __($vartable_out_stock_text, 'vartable') . '</span>') . '</td>';
            }
        }
        ?>
        <?php
        // get attribute names
        $attrnames = array();
        $attrnamesEn = array();
        $orderedattributes = array_merge(array_flip($attr_order), $value['attributes']);

        foreach ($orderedattributes as $taxon => $taxval) {

            $taxonkey = sanitize_title(str_replace(array(
                '-',
                '.',
                ' '
            ), array(
                '_',
                '_',
                '_'
            ), $taxon));

            $taxonomy_details = wc_attribute_taxonomy_slug($taxonkey);

            $temp = '';
            $temp = get_term_by('slug', $taxval, str_replace('attribute_', '', $taxonomy_details));

            if ($temp === false) {
                $temp = get_term_by('slug', $taxval, str_replace('attribute_', '', $taxon));
            }

            if ($temp !== false) {

                $attrnames[$taxonkey] = apply_filters('woocommerce_variation_option_name', $temp->name, $value);
                $attrnamesEn[$taxonkey] = $taxval;
            } else {
                // get all custom attributes sanitize_title
                if (strpos($product->get_attribute(str_replace('attribute_', '', $taxon)), '|') !== false) {
                    $allcustomattr = explode('|', $product->get_attribute(str_replace('attribute_', '', $taxon)));
                } else {
                    $allcustomattr = explode(', ', $product->get_attribute(str_replace('attribute_', '', $taxon)));
                }

                $customattrnames = array();

                foreach ($allcustomattr as $customattrname) {

                    $customattrnames[sanitize_title(trim($customattrname))] = apply_filters('woocommerce_variation_option_name', $customattrname, $value);
                }

                if ($taxval !== false && $taxval !== null && $taxval !== '' && isset($customattrnames[sanitize_title(trim($taxval))])) {

                    $attrnames[$taxonkey] = apply_filters('woocommerce_variation_option_name', $customattrnames[sanitize_title(trim($taxval))], $value);
                    $attrnamesEn[$taxonkey] = $taxval;
                }
            }
        }

        $attrnames = apply_filters('vartable_attributes_array', $attrnames, $value);

        foreach ($attrnames as $attr_slug => $attr_td_value) {
            if (!isset($allcolumns['vartable_variations'])) {
                $allcolumns['vartable_variations'] = '';
            }

            $attr_key = str_replace('attribute_pa', 'pa', $attr_slug);

            $data_sort_value = wc_clean(trim(htmlentities($attr_td_value, ENT_QUOTES)));



            if (isset($vartable_tax_sort[$attr_key]) && $vartable_tax_sort[$attr_key] == 'preset') {

                $the_term = get_term_by('slug', $value['attributes'][$attr_slug], $attr_key);

                if ($the_term) {
                    if (isset($data_sort_value_custom[$the_term->term_id])) {
                        $data_sort_value = $data_sort_value_custom[$the_term->term_id];
                    } else {
                        $data_sort_value_custom[$the_term->term_id] = get_term_meta($the_term->term_id, 'order', true);
                        $data_sort_value = $data_sort_value_custom[$the_term->term_id];
                    }
                }
            }
            // echo "" . $product_child->get_attribute('pa_color');

            $allcolumns['vartable_variations'] .= apply_filters(
                'vartable_attributes_join',
                '<td style="display: flex;flex-direction: column;align-items: center;min-height: 54px;justify-content: center;" class="optionscol ' . $attr_slug . '" color="' . $attrnamesEn[$attr_slug] . '" data-sort-value="' . $data_sort_value . '" data-label="' . apply_filters('vartable_dl_options', $attr_ordernames[str_replace(array(
                    '_',
                    '.',
                    ' '
                ), array(
                    '-',
                    '-',
                    '-'
                ), sanitize_title(str_replace('attribute_', '', $attr_slug)))]) . '">
				<span class="attr-color" style="background:' . $attrnamesEn[$attr_slug] . ';width: 20px !important;height: 20px !important;display: inline-block;border-radius: 50%;"></span>
				<span style="font-size: xx-small;">' . $data_sort_value . '</span>
				</td>', //. apply_filters('vartable_attributes_term_output', htmlentities($attr_td_value, ENT_QUOTES), $value, $attr_slug, $attr_td_value, $attrnames, $product) . 
                $value,
                $attr_slug,
                $attr_td_value,
                $attrnames,
                $product
            );
        }

        if ($vartable_price == 1) {

            $get_variation_price = $product_variation->get_price_html();

            $allcolumns['vartable_price'] = '
              <td class="pricecol ' . (empty($get_variation_price) ? 'vtmobilehide' : '') . '" 
                data-label="' . apply_filters('vartable_dl_price', $headenames['vartable_price'], $value) . '" 
                data-price="' . wc_format_decimal(wc_get_price_to_display($product_variation), 2) . '" 
                data-sort-value="' . wc_format_decimal(wc_get_price_to_display($product_variation), 2) . '">
                ' . $get_variation_price . '
              </td>';
        }
        if ($vartable_total == 1) {

            $vt_row_total = wc_price(wc_get_price_to_display($product_variation) * ($vartable_qty_default > 0 ? $vartable_qty_default : apply_filters('vartable_default_qty', $vartable_default_qty, $value))) . '
                ' . (get_option('woocommerce_price_display_suffix') != '' ? ' ' . get_option('woocommerce_price_display_suffix') : '');

            $allcolumns['vartable_total'] = '
              <td class="totalcol ' . (empty($vt_row_total) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_total', $headenames['vartable_total'], $value) . '" data-sort-value="' . wc_format_decimal(wc_get_price_to_display($product_variation) * ($vartable_qty_default > 0 ? $vartable_qty_default : apply_filters('vartable_default_qty', $vartable_default_qty, $value)), 2) . '">
                ' . $vt_row_total . '
              </td>';
        }

        if ($vartable_shp_class == 1) {

            $shipping_class_term = get_term($product_variation->get_shipping_class_id(), 'product_shipping_class');

            $vt_shipping_cell_value = (is_a($shipping_class_term, 'WP_Term') ? $shipping_class_term->name : $product_variation->get_shipping_class());


            if (!empty($vt_shipping_cell_value)) {
                $allcolumns['vartable_shp_class'] = '
				  <td class="shpclasscol ' . (empty($vt_shipping_cell_value) ? 'vtmobilehide' : '') . '" 
					data-label="' . apply_filters('vartable_dl_shp_class', $headenames['vartable_shp_class'], $value) . '" 
					data-price="' . $product_variation->get_shipping_class() . '" 
					data-sort-value="' . $product_variation->get_shipping_class() . '">
					' . $vt_shipping_cell_value . '
				  </td>';
                $anyshpclass = 1;
            } else {
                $allcolumns['vartable_shp_class'] = '
				  <td class="shpclasscol ' . (empty($vt_shipping_cell_value) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_shp_class', $headenames['vartable_shp_class'], $value) . '">' . $vt_shipping_cell_value . '</td>';
            }
        }

        $override_extra_image = get_post_meta($value['variation_id'], 'override_extra_image', true);
        $enable_extra_image = get_post_meta($value['variation_id'], 'enbable_variations_table_img', true);
        $vartable_qty_step = get_post_meta($value['variation_id'], 'vartable_qty_step', true);

        // if not set, then set it to 1
        if (intval($vartable_qty_step) == 0) {
            $vartable_qty_step = 1;
        }

        if (get_post_meta($product->get_id(), 'disable_variations_table_offer', true) != 1) {

            if ($vartable_offer == 1 || $enable_extra_image == 'yes') {

                $vt_offer_img_value = '';


                if (!empty($override_extra_image) && $enable_extra_image != 'no') {
                    $vt_offer_img_value = '<img src="' . $override_extra_image . '" alt="' . __('offer', 'vartable') . '" />';
                    $anyextraimg = 1;
                }
                if ($vartable_image != '' && $enable_extra_image != 'no' && empty($override_extra_image)) {
                    $vt_offer_img_value = '<img src="' . $vartable_image . '" alt="' . __('offer', 'vartable') . '" />';
                    $anyextraimg = 1;
                }

                $allcolumns['vartable_offer'] = '<td class="offercol ' . (empty($vt_offer_img_value) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_offer', $headenames['vartable_offer'], $value) . '">' . $vt_offer_img_value . '</td>';
            }
        }

        if ($vartable_qty == 1) {
            $allcolumns['vartable_qty'] = '
              <td class="qtycol" style="display:none;" data-label="' . apply_filters('vartable_dl_qty', $headenames['vartable_qty'], $value) . '">';
            if ($product_variation->is_purchasable() && ($product_variation->is_in_stock() || $product_variation->backorders_allowed())) {

                if ($vartable_qty_control == 1) {

                    $allcolumns['vartable_qty'] .= '
                    <div class="qtywrap ' . ($vartable_qty_control_style == 1 ? 'styled' : '') . '">
                    ';

                    if ($vartable_qty_control_style == 1) {
                        $allcolumns['vartable_qty'] .= '
						<div class="minusqty qtycontrol">
						
						<svg fill="currentColor" height="24px" width="24px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
							 viewBox="0 0 300.003 300.003" xml:space="preserve">
						<g>
							<g>
								<path d="M150.001,0c-82.843,0-150,67.159-150,150c0,82.838,67.157,150.003,150,150.003c82.838,0,150-67.165,150-150.003
									C300.001,67.159,232.838,0,150.001,0z M197.218,166.283H92.41c-8.416,0-15.238-6.821-15.238-15.238s6.821-15.238,15.238-15.238
									H197.22c8.416,0,15.238,6.821,15.238,15.238S205.634,166.283,197.218,166.283z"/>
							</g>
						</g>
						</svg>
						
						</div>
						';
                    } else {
                        $allcolumns['vartable_qty'] .= '
						<div class="minusqty qtycontrol">-</div>
						';
                    }
                }

                $allcolumns['vartable_qty'] .= '
                    <input type="number" step="' . apply_filters('vartable_qty_step', $vartable_qty_step, $value) . '" name="var_quantity" value="' . ($vartable_qty_default > 0 ? $vartable_qty_default : apply_filters('vartable_default_qty', $vartable_default_qty, $value)) . '" title="Qty" class="input-text qty text" size="4" ' . (intval($value['min_qty']) > 0 && !isset($vartable_default_qty) ? 'min="' . $value['min_qty'] . '"' : 'min="0"') . ' ' . (intval($value['max_qty']) > 0 ? 'max="' . $value['max_qty'] . '"' : '') . '>
                  ';

                if ($vartable_qty_control == 1) {

                    if ($vartable_qty_control_style == 1) {

                        $allcolumns['vartable_qty'] .= '
						  <div class="plusqty">
						  <svg fill="currentColor" height="800px" width="800px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
								 viewBox="0 0 300.003 300.003" xml:space="preserve">
							<g>
								<g>
									<path d="M150,0C67.159,0,0.001,67.159,0.001,150c0,82.838,67.157,150.003,149.997,150.003S300.002,232.838,300.002,150
										C300.002,67.159,232.839,0,150,0z M213.281,166.501h-48.27v50.469c-0.003,8.463-6.863,15.323-15.328,15.323
										c-8.468,0-15.328-6.86-15.328-15.328v-50.464H87.37c-8.466-0.003-15.323-6.863-15.328-15.328c0-8.463,6.863-15.326,15.328-15.328
										l46.984,0.003V91.057c0-8.466,6.863-15.328,15.326-15.328c8.468,0,15.331,6.863,15.328,15.328l0.003,44.787l48.265,0.005
										c8.466-0.005,15.331,6.86,15.328,15.328C228.607,159.643,221.742,166.501,213.281,166.501z"/>
								</g>
							</g>
							</svg>
						  
						  </div>
						';
                    } else {
                        $allcolumns['vartable_qty'] .= '
						  <div class="plusqty">+</div>
						';
                    }

                    $allcolumns['vartable_qty'] .= '
                      </div>
                    ';
                }
            }
            $allcolumns['vartable_qty'] .= '</td>';
        }

        ob_start();
        do_action('woocommerce_add_to_cart_class', $product->get_id(), $value);
        $woocommerce_add_to_cart_class = ob_get_clean();

        ob_start();
        do_action('woocommerce_before_add_to_cart_button', $product->get_id(), $value);
        $woocommerce_before_add_to_cart_button = ob_get_clean();

        $allcolumns['vartable_cart'] = '<td class="cartcol ' . ($vartable_cart == 0 ? 'vartablehide' : '') . ' ' . $woocommerce_add_to_cart_class . '" data-label="">' . $woocommerce_before_add_to_cart_button;

        // if is purchasable
        if ($product_variation->is_purchasable() && ($product_variation->is_in_stock() || $product_variation->backorders_allowed())) {

            // if is out of stock and backorder are allowed
            if ((get_post_meta($value['variation_id'], '_stock_status', true) != 'instock' && !empty($value['backorders_allowed'])) || ($vartable_zero_to_out == 1 && $varstock == 0 && get_post_meta($value['variation_id'], '_manage_stock', true) == 'yes')) {
                $carttext = __('Backorder', 'vartable');
            } else {
                $carttext = __('افزودن به سبد خرید', 'vartable');
            }

            // $carttext = apply_filters('woocommerce_product_add_to_cart_text', $carttext, $product_variation);

            $vt_button_classes = apply_filters('vartable_single_button_classes', array(
                'single_add_to_cart_button',
                'button',
                'button_theme',
                'ajax',
                'add_to_cart',
                'avia-button',
                'fusion-button',
                'button-flat',
                'button-round',
                'alt'
            ));

            $cart_icon = false;

            if ($vartable_cart_icon >= 0 && $vartable_cart_icon !== '') {

                $vt_cart_icons = vt_cart_icons();

                if (isset($vt_cart_icons[$vartable_cart_icon])) {
                    $cart_icon = $vt_cart_icons[$vartable_cart_icon];
                    $vt_button_classes[] = 'has_cart_icon';
                }
            }
            if ($vartable_cart_notext == 1) {

                $vt_button_classes[] = 'remove_cart_text';
            }
            $tempProduct = wc_get_product($value['variation_id']);
            $allcolumns['vartable_cart'] .= $form;
            $allcolumns['vartable_cart'] .= apply_filters(
                'woocommerce_loop_add_to_cart_link',
                sprintf(
                    '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" class="button %s product_type_%s">%s</a>',
                    esc_url($tempProduct->add_to_cart_url($tempProduct->get_id(), 1)),
                    esc_attr($tempProduct->get_id()),
                    esc_attr($tempProduct->get_sku()),
                    implode(
                        ' ',
                        array_filter(
                            [
                                'button',
                                'product_type_' . $tempProduct->product_type,
                                $tempProduct->is_purchasable() && $tempProduct->is_in_stock() ? 'add_to_cart_button' : '',
                                $tempProduct->supports('ajax_add_to_cart') ? 'ajax_add_to_cart' : ''
                            ]
                        )
                    ),
                    esc_attr($tempProduct->product_type),
                    $tempProduct->add_to_cart_text(),
                    esc_attr(isset($class) ? $class : 'button'),
                ),
                $tempProduct
            );
            $tempProduct = null;
            // $allcolumns['vartable_cart'] .= $form . '
            //   <button id="add2cartbtn_' . $value['variation_id'] . $ri . '" type="submit" data-product_id="' . $value['variation_id'] . '" class="' . implode(' ', $vt_button_classes) . ' alt">' . $cart_icon . ' <span>' . apply_filters('single_add_to_cart_text', $carttext, $product->get_type(), $value) . '</span></button>';
            if ($vartable_ajax == 1 || $vartable_globalcart == 1 || $vartable_globalcart == 2) {
                $allcolumns['vartable_cart'] .= '
                <div class="added2cartwrap" id="added2cart_' . $value['variation_id'] . $ri . '"><span class="added2cart" >&#10003;</span></div>
                <span class="vtspinner singlebtn vtspinner_' . $value['variation_id'] . $ri . '">
                  <img src="' . plugins_url('images/spinner.png', __FILE__) . '" width="16" height="16" alt="spinner" />
                </span>
                ';
            } else {
                $allcolumns['vartable_cart'] .= '
                <div class="added2cartwrap notvisible" id="added2cart_' . $value['variation_id'] . $ri . '"></div>
                <span class="vtspinner vtspinner_' . $value['variation_id'] . $ri . ' notvisible"></span>
                ';
            }
        }

        ob_start();
        do_action('woocommerce_after_add_to_cart_button', $product->get_id(), $value);
        $woocommerce_after_add_to_cart_button = ob_get_clean();

        // $allcolumns['vartable_cart'] .= $woocommerce_after_add_to_cart_button . '</form></td>';
        $allcolumns['vartable_cart'] .= '</form></td>';

        // empty $form
        $form = '';

        if ($vartable_globalcart == 1 || $vartable_globalcart == 2) {

            $allcolumns['vartable_globalcart'] = '<td class="globalcartcol ' . ($vartable_globalcart == 2 ? 'vartablehide' : '') . '" data-label="">';
            if ($product_variation->is_purchasable() && ($product_variation->is_in_stock() || $product_variation->backorders_allowed())) {
                $allcolumns['vartable_globalcart'] .= '  <input type="checkbox" class="globalcheck" name="check_' . $value['variation_id'] . $ri . '" value="1" ' . ($vartable_globalcart == 2 || $vartable_globalcart_status == 1 ? 'checked="checked"' : '') . '>';
            }
            $allcolumns['vartable_globalcart'] .= '</td>';
        }

        if ($vartable_wishlist == 1 && defined('YITH_WCWL')) {
            $url = strtok($_SERVER["REQUEST_URI"], '?');
            parse_str($_SERVER['QUERY_STRING'], $query_string);
            $query_string['add_to_wishlist'] = basename($value['variation_id']);
            $rdr_str = http_build_query($query_string);

            // $value['variation_id'] can not be added // this is a YITH Wishlist issue
            $wishlist = do_shortcode('[yith_wcwl_add_to_wishlist product_id=' . $value['variation_id'] . ' icon="fa-heart" label=""]');

            $allcolumns['vartable_wishlist'] = '
            <td class="wishcol ' . (empty($wishlist) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_wishlist', $headenames['vartable_wishlist'], $value) . '">
              ' . $wishlist . '
            </td>';
        }

        if ($is_wrappable == 'yes' && $vartable_gift == 1) {

            $current_value = !empty($_REQUEST['gift_wrap']) ? 1 : 0;

            $cost = get_post_meta($product->get_id(), '_gift_wrap_cost', true);

            if ($cost == '') {
                $cost = $gift_wrap_cost;
            }

            $price_text = $cost > 0 ? wc_price($cost) : __('free', 'woocommerce-product-gift-wrap');
            $checkbox = '<input type="checkbox" class="var_gift_wrap" name="var_gift_wrap" value="yes" ' . checked($current_value, 1, false) . ' />';

            $allcolumns['vartable_gift'] = '
            <td class="giftcol" data-label="' . apply_filters('vartable_dl_gift', $headenames['vartable_gift'], $value) . '">
              <label>' . str_replace(array(
                '{price}',
                '{checkbox}',
            ), array(
                $price_text,
                $checkbox
            ), $product_gift_wrap_message) . '</label>
            </td>';
        }

        if ($vartable_desc == 1 && $vartable_desc_inline == 1) {

            $vt_variation_description = '';
            $vt_variation_description = get_post_meta($value['variation_id'], 'vt_variation_description', true);


            if (!$vt_variation_description && isset($value['variation_description']) && !empty($value['variation_description'])) {

                $vt_variation_description = $value['variation_description'];
            } else {

                $value['variation_description'] = $vt_variation_description;
            }

            $allcolumns['vartable_desc'] = '
              <td class="desccol ' . (empty($vt_variation_description) ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_desc', $headenames['vartable_desc'], $value) . '">' . $vt_variation_description . '</td>';
            if (trim($vt_variation_description) != '') {
                $anydescription = 1;
            }
        }

        if ($vartable_dimensions == 1) {
            $vartable_dimensions_str = '';
            $vartable_dimensions_str = $product_variation->get_dimensions(false);

            $allcolumns['vartable_dimensions'] = '
              <td class="dimensions_col ' . (strlen(implode($vartable_dimensions_str)) == 0 ? 'vtmobilehide' : '') . '" data-label="' . apply_filters('vartable_dl_dimensions', $headenames['vartable_dimensions'], $value) . '">' . (strlen(implode($vartable_dimensions_str)) != 0 ? $vartable_dimensions_str['length'] . ' &times; ' . $vartable_dimensions_str['width'] . ' &times; ' . $vartable_dimensions_str['height'] : '') . '</td>';
            if ($product_variation->has_dimensions() && strlen(implode($vartable_dimensions_str)) != 0) {
                $anydimension = 1;
            }
        }

        if ($vartable_weight == 1) {

            if ($product_variation->has_weight()) {

                $vt_variation_weight = $product_variation->get_weight();

                $allcolumns['vartable_weight'] = '
              <td class="weight_col ' . (empty($vt_variation_weight) ? 'vtmobilehide' : '') . '" data-sort-value="' . $product_variation->get_weight() . '" data-label="' . apply_filters('vartable_dl_weight', $headenames['vartable_weight'], $value) . '">' . $product_variation->get_weight() . ($product_variation->has_weight() ? ' ' . get_option('woocommerce_weight_unit') : '') . '</td>';
                $anyweight = 1;
            } else {
                $allcolumns['vartable_weight'] = '
              <td class="weight_col vtmobilehide" data-label="' . apply_filters('vartable_dl_weight', $headenames['vartable_weight'], $value) . '"></td>';
            }
        }

        $allcolumns = apply_filters('vartable_allcolumns', $allcolumns, $value, $attrnames, $product);

        // order columns
        $orderedcols = array();
        if (is_array($vartable_order)) {
            foreach ($vartable_order as $vokey => $vovalue) {
                if (isset($allcolumns[$vokey])) {
                    $orderedcols[$vokey] = $allcolumns[$vokey];
                }
            }
        } else {
            $orderedcols = $allcolumns;
        }

        $orderedcols = array_filter($orderedcols);
        $vt_trows[$menu_order[$value['variation_id']]] .= implode("\n", apply_filters('vartable_output_array', $orderedcols, $value, $attrnames));
        $ri++;

        ob_start();
        do_action('woocommerce_after_single_variation', $product->get_id(), $value);
        $woocommerce_after_single_variation = ob_get_clean();

        $vt_trows[$menu_order[$value['variation_id']]] .= '</tr>' . $woocommerce_after_single_variation . '
          ';
        // add description last
        if ($vartable_desc == 1 && $vartable_desc_inline != 1) {
            $vt_variation_description = '';
            $vt_variation_description = get_post_meta($value['variation_id'], 'vt_variation_description', true);

            if (!$vt_variation_description && isset($value['variation_description'])) {

                $vt_variation_description = $value['variation_description'];
            } else {

                $value['variation_description'] = $vt_variation_description;
            }

            if ($vt_variation_description != '') {
                $vt_trows[$menu_order[$value['variation_id']]] .= '
				  <tr class="descrow ' . (empty($vt_variation_description) ? 'vtmobilehide' : '') . '">
					<td class="desccol ' . (empty($vt_variation_description) ? 'vtmobilehide' : '') . '" colspan="' . (count($orderedcols) + count($attrnames) - 1) . '" data-label="' . apply_filters('vartable_dl_desc', $headenames['vartable_desc'], $value) . '">' . $vt_variation_description . '</td>
				  </tr>';
            }
        }
    }

    $out .= implode("\n", apply_filters('vartable_output_rows_by_id', $vt_trows, $product->get_id()));

    ob_start();
    do_action('woocommerce_after_add_to_cart_form', $product->get_id());
    $woocommerce_after_add_to_cart_form = ob_get_clean();

    $out .= '</tbody>
    </table>
	</div>
    ' . $woocommerce_after_add_to_cart_form . '
    ';

    if (($vartable_globalcart == 1 || $vartable_globalcart == 2) && ($vartable_globalposition == 'bottom' || $vartable_globalposition == 'both')) {

        ob_start();
        do_action('vartable_add_gc_button', $product->get_id());
        $vartable_add_gc_button = ob_get_clean();

        $vt_button_classes = apply_filters('vartable_global_button_classes', array(
            'single_add_to_cart_button',
            'btn',
            'button',
            'button_theme',
            'ajax',
            'add_to_cart',
            'avia-button',
            'fusion-button',
            'button-flat',
            'button-round',
            'alt'
        ));

        $out .= apply_filters('vartable_global_btn', '
        <div class="vartable_gc_wrap vartable_gc_wrap_bottom">
          <a data-position="bottom" href="#globalcart" class="globalcartbtn submit ' . implode(' ', $vt_button_classes) . '" data-product_id="gc_' . $product->get_id() . '" id="gc_' . $vtrand . '_bottom" class="btn button alt">' . __('Add selected to cart', 'vartable') . '<span class="vt_products_count"></span></a>
          <span class="added2cartglobal added2cartglobal_' . $vtrand . '">&#10003;</span>
          <span class="vtspinner vtspinner_bottom vtspinner_' . $vtrand . '"><img src="' . plugins_url('images/spinner.png', __FILE__) . '" width="16" height="16" alt="spinner" /></span>
        </div>
      ', $product, 'bottom', $vtrand);
    }
    if ($vartable_ajax == 1 || $vartable_globalcart == 1 || $vartable_globalcart == 2) {
        if (isset($_GLOBALS['vtajaxfix']) && $_GLOBALS['vtajaxfix'] != 1) {
            $_GLOBALS['vtajaxfix'] = 1;

            ob_start();
            do_action('vartable_add_gc_button', $product->get_id());
            $vartable_add_gc_button = ob_get_clean();
        }
    }

    ob_start();
    do_action('vartable_after_table', $product->get_id());
    $vartable_after_table = ob_get_clean();

    ob_start();
    do_action('woocommerce_product_meta_end', $product->get_id());
    $woocommerce_product_meta_end = ob_get_clean();

    $out .= $vartable_after_table;
    $out .= $woocommerce_product_meta_end;

    if ($vartable_head == 1 && get_post_meta($product->get_id(), 'disable_variations_table_header', true) != 1) {

        // order header
        $headenames = vt_fields_func();
        $headenames['vartable_cart'] = '';
        $headenames = apply_filters('vartable_headenames', $headenames, $product->get_id());
        $orderedheader = array();


        if (is_array($vartable_order)) {

            if ($anyextraimg == 1 && get_post_meta($product->get_id(), 'disable_variations_table_offer', true) != 1) {
                $vartable_order['vartable_offer'] = __('Offer Image', 'vartable');
            }
            $vi = 0;
            foreach ($vartable_order as $vokey => $vovalue) {
                if (($vokey == 'vartable_gift' && $is_wrappable != 'yes') || $vokey == 'vartable_sorting') {
                    continue;
                }
                $sortingval = ' data-sort="float" ';
                // if ($vokey == 'vartable_thumb' || $vokey == 'vartable_gift' || $vokey == 'vartable_wishlist' || $vokey == 'vartable_cart' || $vokey == 'vartable_offer') {
                // 	$sortingval = ' data-sort="string" ';
                // }
                if ($vokey == 'vartable_globalcart' || $vokey == 'vartable_cart') {
                    $sortingval = '';
                }
                if ($vokey == 'vartable_qty') {
                    $sortingval = ' data-sort="int" ';
                }
                if ($vokey == 'vartable_price') {
                    $sortingval = ' data-sort="float" ';
                }
                if ($vokey == 'vartable_shp_class') {
                    $sortingval = ' data-sort="string" ';
                }

                if ((isset(${$vokey}) && ${$vokey} == 1) || ($vokey == 'vartable_offer' && $anyextraimg == 1) || $vokey == 'vartable_variations') {

                    if ($vokey == 'vartable_wishlist' && defined('YITH_WCWL') == false) {
                        continue;
                    }

                    if ($vokey == 'vartable_variations') {

                        $orderedheader[$vokey] = '';

                        foreach ($attr_ordernames as $attrslug => $attrval) {

                            ob_start();
                            do_action('vartable_variations_th', $product->get_id(), $attrslug);
                            $vartable_variations_th = ob_get_clean();


                            $attr_key = str_replace('-', '_', $attrslug);
                            if (isset($vartable_tax_sort[$attr_key])) {
                                if ($vartable_tax_sort[$attr_key] == 'preset') {
                                    $sortingval = 'data-sort="int"';
                                } elseif ($vartable_tax_sort[$attr_key] != '-1') {
                                    $sortingval = 'data-sort="' . $vartable_tax_sort[$attr_key] . '"';
                                }
                            }

                            $orderedheader[$vokey] .= '
                  <th ' . $sortingval . ' class="' . $vokey . ' ' . $attrslug . '" ' . $vartable_variations_th . '>
                    <span>
                      ' . apply_filters('vartable_header_attributes_join', $attrval, $product->get_id(), $attrslug) . '
                    </span>
                  </th>
                  ';
                        }
                    } elseif ($vokey == 'vartable_globalcart' && ($vartable_globalcart == 1 || $vartable_globalcart == 2)) {
                        $orderedheader[$vokey] = '<th ' . $sortingval . 'class="' . $vokey . ' ' . ($vartable_globalcart == 2 ? 'vartablehide' : '') . '"><div class="vartable_selectall button btn"><label for="vtselectall_' . $vtrand . '">' . apply_filters('vartable_header_text', __('Select All', 'vartable'), $product->get_id()) . ' <input class="vartable_selectall_check" id="vtselectall_' . $vtrand . '" type="checkbox" id="selecctall"/></label></div></th>';
                    } else {
                        $orderedheader[$vokey] = '<th ' . $sortingval . ' class="' . $vokey . '" ><span>' . apply_filters('vartable_header_text', $headenames[$vokey], $product->get_id()) . '</span></th>';
                    }
                }

                $orderedheader = apply_filters('header_' . $vokey, $orderedheader, $headenames, $sortingval, $vokey, $product);
                $vi++;
            }
        }

        if ($anyextraimg == 0) {
            unset($orderedheader['vartable_offer']);
            unset($allcolumns['vartable_offer']);
        }
        if ($anydescription == 0 || $vartable_desc_inline != 1) {
            unset($orderedheader['vartable_desc']);
        }
        if ($anydimension == 0) {
            unset($orderedheader['vartable_dimensions']);
        }
        if ($anyweight == 0) {
            unset($orderedheader['vartable_weight']);
        }
        if ($anyshpclass == 0) {
            unset($orderedheader['vartable_shp_class']);
        }

        $joinedheader = implode('', apply_filters('vartable_header_th', $orderedheader, $product->get_id()));

        if (get_post_meta($product->get_id(), 'custom_variations_table_header', true) != '') {
            $head .= get_post_meta($product->get_id(), 'custom_variations_table_header', true);
        } elseif ($vartable_customhead != '' && get_post_meta($product->get_id(), 'custom_variations_table_header_skip', true) != 1) {

            $head .= $vartable_customhead;
        } else {

            // 	$head .= '<thead>
            //     <tr>
            //       ' . $joinedheader . ' 
            //     </tr>
            //   </thead>
            // ';
        }
    }

    $out = str_replace('%headplaceholder%', $head, $out);



    if ($anyextraimg == 0) {
        $out = str_replace('<td class="offercol vtmobilehide" data-label="' . apply_filters('vartable_dl_offer', $headenames['vartable_offer'], $product->get_id()) . '"></td>', '', $out);
    }

    if ($anydescription == 0 && $vartable_desc_inline == 1) {
        $out = str_replace('<td class="desccol vtmobilehide" data-label="' . apply_filters('vartable_dl_desc', $headenames['vartable_desc'], $value) . '"></td>', '', $out);
    }

    if ($anyweight == 0) {
        $out = str_replace('<td class="weight_col vtmobilehide" data-label="' . apply_filters('vartable_dl_weight', $headenames['vartable_weight'], $value) . '"></td>', '', $out);
    }
    if ($anydimension == 0) {
        $out = str_replace('<td class="dimensions_col vtmobilehide" data-label="' . apply_filters('vartable_dl_dimensions', $headenames['vartable_dimensions'], $value) . '"></td>', '', $out);
    }
    if ($anyshpclass == 0) {
        $out = str_replace('<td class="shpclasscol vtmobilehide" data-label="' . apply_filters('vartable_dl_shp_class', $headenames['vartable_shp_class'], $value) . '"></td>', '', $out);
    }

    if ($vartable_shortcd != 1) {
        echo $out;
    } else {
        return ($out);
    }
}


add_action('admin_enqueue_scripts', 'woovartables_enqueue_admin_scripts');
function woovartables_enqueue_admin_scripts()
{

    // admin only css and JS
    if (isset($_GET['page']) && $_GET['page'] == 'variationstable' && is_admin()) {

        global $wp_query, $post;

        $suffix = '.min';
        $version = '1.4.2';

        wp_enqueue_style('selectWoo');
        wp_enqueue_style('select2');
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), $version);

        wp_enqueue_script('selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js', array(
            'jquery'
        ), '1.0.6');
        wp_enqueue_script('wc-enhanced-select', WC()->plugin_url() . '/assets/js/admin/wc-enhanced-select' . $suffix . '.js', array(
            'jquery',
            'selectWoo'
        ), $version);

        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');

        wp_localize_script('wc-enhanced-select', 'wc_enhanced_select_params', array(
            'i18n_no_matches' => _x('No matches found', 'enhanced select', 'woocommerce'),
            'i18n_ajax_error' => _x('Loading failed', 'enhanced select', 'woocommerce'),
            'i18n_input_too_short_1' => _x('Please enter 1 or more characters', 'enhanced select', 'woocommerce'),
            'i18n_input_too_short_n' => _x('Please enter %qty% or more characters', 'enhanced select', 'woocommerce'),
            'i18n_input_too_long_1' => _x('Please delete 1 character', 'enhanced select', 'woocommerce'),
            'i18n_input_too_long_n' => _x('Please delete %qty% characters', 'enhanced select', 'woocommerce'),
            'i18n_selection_too_long_1' => _x('You can only select 1 item', 'enhanced select', 'woocommerce'),
            'i18n_selection_too_long_n' => _x('You can only select %qty% items', 'enhanced select', 'woocommerce'),
            'i18n_load_more' => _x('Loading more results&hellip;', 'enhanced select', 'woocommerce'),
            'i18n_searching' => _x('Searching&hellip;', 'enhanced select', 'woocommerce'),
            // 'ajax_url' => admin_url('admin-ajax.php'),
            'search_products_nonce' => wp_create_nonce('search-products'),
            'search_customers_nonce' => wp_create_nonce('search-customers'),
            'search_categories_nonce' => wp_create_nonce('search-categories'),
            'search_pages_nonce' => wp_create_nonce('search-pages'),
        ));
    }
}

add_action("wp_enqueue_scripts", "woovartables_scripts", 20);
function woovartables_scripts()
{

    global $woocommerce;

    $jsver = '1.4.5';

    wp_register_style('woovartables_css', plugins_url('assets/css/woovartables.css', __FILE__));
    wp_enqueue_style('woovartables_css');

    if (get_option('vartable_sorting') == 1) {
        wp_register_script('woovartables_table_sort', plugins_url('assets/js/stupidtable.js', __FILE__), array('jquery'), $jsver, true);
        wp_enqueue_script("woovartables_table_sort");
    }

    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    if (get_option('vartable_lightbox') == 1) {
        wp_enqueue_script('vartable_fancybox_js', plugins_url('assets/js/jquery.fancybox.min.js', __FILE__), array('jquery'), $jsver, true);
        wp_enqueue_style('vartable_fancybox_css', plugins_url('assets/css/jquery.fancybox.min.css', __FILE__));
    }

    if (get_option('vartable_sorting') == 1) {
        wp_register_script('woovartables_js', plugins_url('assets/js/add-to-cart.js', __FILE__), array('jquery', 'woovartables_table_sort'), '1.4.5', true);
    } else {
        wp_register_script('woovartables_js', plugins_url('assets/js/add-to-cart.js', __FILE__), array('jquery'), '1.4.5', true);
    }
    wp_enqueue_script('woovartables_js');

    $vars = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'cart_url' => apply_filters('woocommerce_add_to_cart_redirect', wc_get_cart_url(), null),
        'vartable_ajax' => get_option('vartable_ajax'),
        'currency_symbol' => get_woocommerce_currency_symbol(),
        'thousand_separator' => wc_get_price_thousand_separator(),
        'decimal_separator' => wc_get_price_decimal_separator(),
        'decimal_decimals' => wc_get_price_decimals(),
        'currency_pos' => get_option('woocommerce_currency_pos'),
        'price_display_suffix' => get_option('woocommerce_price_display_suffix'),
        'lightbox' => get_option('vartable_lightbox')
    );

    $vars = apply_filters('vartable_js_vars', $vars);

    wp_localize_script('woovartables_js', 'localvars', $vars);
}

add_action('wp_footer', 'vartable_footer_code');
function vartable_footer_code()
{
    global $woocommerce;
    if (get_option('vartable_hide_cart_notification') != 1) {

        $vartable_cart_notification_time = get_option('vartable_cart_notification_time');
        if (!$vartable_cart_notification_time) {
            $vartable_cart_notification_time = 6000;
        } else {
            $vartable_cart_notification_time = (intval($vartable_cart_notification_time) * 1000);
        }
        ?>
        <div id="vt_added_to_cart_notification" class="vt_notification" data-time="<?= $vartable_cart_notification_time; ?>" style="display: none;">
            <a href="<?php echo wc_get_cart_url(); ?>" title="<?php echo __('Go to cart', 'vartable'); ?>"><span></span> <?php echo __('&times; product(s) added to cart', 'vartable'); ?> &rarr;</a> <a href="#" class="slideup_panel">&times;</a>
        </div>
        <div id="vt_error_notification" class="vt_notification" style="display: none;">
            <span class="message"></span> <a href="#" class="slideup_panel">&times;</a>
        </div>
    <?php
    }
}

// add product custom fields
add_action('woocommerce_product_options_advanced', 'spyros_disable_table_product_option');
function spyros_disable_table_product_option()
{

    $product = false;
    if (isset($_GET['post'])) {
        $product = wc_get_product(intval($_GET['post']));
    }
    $formatted_attributes = array();
    $attributes = false;
    if ($product) {
        $attributes = $product->get_attributes();
    }
    $attributes_drop = array();

    if (is_array($attributes) && !empty($attributes)) {
        foreach ($attributes as $attr => $attr_deets) {

            $attribute_label = wc_attribute_label($attr);
            $attributes_drop[$attr] = $attribute_label;
        }
    }

    woocommerce_wp_select(array(
        'id' => 'disable_variations_table',
        'label' => __('Variations Table Status', 'vartable'),
        'options' => array(
            '0' => __('Default plugin settings', 'vartable'),
            '1' => __('Force disabling', 'vartable'),
            '2' => __('Force variations table', 'vartable')
        )
    ));
    woocommerce_wp_select(array(
        'id' => 'disable_variations_table_header',
        'label' => __('Disable variations table <strong>header</strong>', 'vartable'),
        'options' => array(
            '0' => __('No', 'vartable'),
            '1' => __('Yes', 'vartable')
        )
    ));
    woocommerce_wp_select(array(
        'id' => 'disable_variations_table_offer',
        'label' => __('Disable variations table <strong>offer/extra image</strong>', 'vartable'),
        'options' => array(
            '0' => __('No', 'vartable'),
            '1' => __('Yes', 'vartable')
        )
    ));
    woocommerce_wp_select(array(
        'id' => 'custom_variations_table_header_skip',
        'label' => __('Skip custom variations table <strong>header</strong> set on settings', 'vartable'),
        'options' => array(
            '0' => __('No', 'vartable'),
            '1' => __('Yes', 'vartable')
        ),
        'desc_tip' => true,
        'description' => __('If set to yes it will replace the custom header that you may have set, on the settings page, with the default one, if you do not set a custom one for this product only.', 'vartable')
    ));

    woocommerce_wp_textarea_input(array(
        'id' => 'custom_variations_table_header',
        'label' => __('Custom variations table header html code', 'vartable'),
        'desc_tip' => true,
        'description' => __('This will replace the table header with your custom html code', 'vartable')
    ));

    $attr_options = array(
        'custom' => __('Custom', 'vartable'),
        'vartable_price' => __('Price', 'vartable'),
        'vartable_sku' => __('SKU', 'vartable'),
        'vartable_weight' => __('Weight', 'vartable'),
        'vartable_stock' => __('Stock Status', 'vartable'),
        'vartable_shp_class' => __('Shipping Class', 'vartable'),
    );

    $attr_options = array_merge($attr_options, $attributes_drop);

    woocommerce_wp_select(array(
        'id' => 'custom_variations_preordering',
        'label' => __('Default variations table ordering', 'vartable'),
        'options' => $attr_options,
        'desc_tip' => true,
        'description' => __('This is a BETA feature, which means it needs more development and testing!', 'vartable')
    ));
    woocommerce_wp_select(array(
        'id' => 'custom_variations_preordering_direction',
        'label' => __('Default variations table ordering direction', 'vartable'),
        'options' => array(
            'custom' => __('Custom', 'vartable'),
            'asc' => __('Ascending', 'vartable'),
            'desc' => __('Descending', 'vartable')
        )
    ));
}

add_action('save_post', 'spyros_save_table_product_option');
function spyros_save_table_product_option($product_id)
{
    // If this is a auto save do nothing, we only save when update button is clicked
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['disable_variations_table'])) {
        if (is_numeric($_POST['disable_variations_table'])) {
            update_post_meta($product_id, 'disable_variations_table', $_POST['disable_variations_table']);
        }
    } else {
        delete_post_meta($product_id, 'disable_variations_table');
    }

    if (isset($_POST['disable_variations_table_header'])) {
        if (is_numeric($_POST['disable_variations_table_header'])) {
            update_post_meta($product_id, 'disable_variations_table_header', $_POST['disable_variations_table_header']);
        }
    } else {
        delete_post_meta($product_id, 'disable_variations_table_header');
    }

    if (isset($_POST['disable_variations_table_offer'])) {
        if (is_numeric($_POST['disable_variations_table_offer'])) {
            update_post_meta($product_id, 'disable_variations_table_offer', $_POST['disable_variations_table_offer']);
        }
    } else {
        delete_post_meta($product_id, 'disable_variations_table_offer');
    }

    if (isset($_POST['custom_variations_table_header_skip'])) {
        if (is_numeric($_POST['custom_variations_table_header_skip'])) {
            update_post_meta($product_id, 'custom_variations_table_header_skip', $_POST['custom_variations_table_header_skip']);
        }
    } else {
        delete_post_meta($product_id, 'custom_variations_table_header_skip');
    }

    if (isset($_POST['custom_variations_table_header'])) {
        update_post_meta($product_id, 'custom_variations_table_header', $_POST['custom_variations_table_header']);
    } else {
        delete_post_meta($product_id, 'custom_variations_table_header');
    }

    if (isset($_POST['custom_variations_preordering'])) {
        update_post_meta($product_id, 'custom_variations_preordering', $_POST['custom_variations_preordering']);
    } else {
        delete_post_meta($product_id, 'custom_variations_preordering');
    }

    if (isset($_POST['custom_variations_preordering_direction'])) {
        update_post_meta($product_id, 'custom_variations_preordering_direction', $_POST['custom_variations_preordering_direction']);
    } else {
        delete_post_meta($product_id, 'custom_variations_preordering_direction');
    }
}

//Display Fields
add_action('woocommerce_product_after_variable_attributes', 'vartable_variable_fields', 10, 3);

function vartable_variable_fields($loop, $variation_data, $variation)
{
    global $thepostid, $post;
    ?>
    <tr>
        <td>
            <br />
            <?php
            // Checkbox
            woocommerce_wp_select(array(
                'id' => 'enbable_variations_table_img[' . $loop . ']',
                'label' => __('Display Extra Image', 'vartable') . ' ',
                'description' => '',
                'value' => get_post_meta($variation->ID, 'enbable_variations_table_img', true),
                'options' => array(
                    '' => __('Default Settings', 'vartable'),
                    'no' => __('No', 'vartable'),
                    'yes' => __('Yes', 'woocommerce')
                )
            ));
            ?>
        </td>
    </tr>
    <tr>
        <td>
            <label for="override_extra_image[<?php echo $loop; ?>]"><?php _e('Override extra image', 'vartable'); ?></label>
            <?php spyros_media_upload('override_extra_image[' . $loop . ']', get_post_meta($variation->ID, 'override_extra_image', true), $loop); ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            // Step input
            woocommerce_wp_text_input(array(
                'id' => 'vartable_qty_step[' . $loop . ']',
                'label' => __('Quantity Steps', 'vartable') . ' ',
                'value' => get_post_meta($variation->ID, 'vartable_qty_step', true),
                'wrapper_class' => 'form-row-first'
            ));
            ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            // Step input
            woocommerce_wp_text_input(array(
                'id' => 'vartable_qty_default[' . $loop . ']',
                'label' => __('Default Variation Quantity', 'vartable') . ' ',
                'value' => get_post_meta($variation->ID, 'vartable_qty_default', true),
                'wrapper_class' => 'form-row-last'
            ));
            ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            woocommerce_wp_textarea_input(array(
                'id' => 'vt_variation_description[' . $loop . ']',
                'label' => __('Description', 'vartable') . ' ',
                'value' => get_post_meta($variation->ID, 'vt_variation_description', true)
            ));
            ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            woocommerce_wp_checkbox(array(
                'id' => 'vt_variation_hide[' . $loop . ']',
                'label' => __('Hide this variation from the table', 'vartable') . ' ',
                'value' => get_post_meta($variation->ID, 'vt_variation_hide', true)
            ));
            ?>
        </td>
    </tr>
<?php
}

//JS to add fields for new variations
add_action('woocommerce_product_after_variable_attributes_js', 'vartable_variable_fields_js');

/**
 * Create new fields for new variations
 *
 */
function vartable_variable_fields_js()
{
?>
    <tr>
        <td>
            <br />
            <?php
            // Checkbox
            woocommerce_wp_select(array(
                'id' => 'enbable_variations_table_img[ + loop + ]',
                'label' => __('Display Extra Image', 'vartable') . ' ',
                'description' => '',
                'value' => $variation_data['enbable_variations_table_img'][0],
                'options' => array(
                    '' => __('Default Settings', 'vartable'),
                    'no' => __('No', 'vartable'),
                    'yes' => __('Yes', 'woocommerce')
                )
            ));
            ?>
        </td>
    </tr>
    <tr>
        <td>
            <label for="override_extra_image[ + loop + ]"><?php _e('Override extra image', 'vartable'); ?></label>
            <?php spyros_media_upload('override_extra_image[ + loop + ]', $variation_data['override_extra_image'][0], '+ loop +'); ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            // Checkbox
            woocommerce_wp_text_input(array(
                'id' => 'vartable_qty_step[ + loop + ]',
                'label' => __('Quantity Steps', 'vartable') . ' ',
                'description' => '',
                'value' => $variation_data['vartable_qty_step'][0],
                'wrapper_class' => 'form-row-first'
            ));
            ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            // Checkbox
            woocommerce_wp_text_input(array(
                'id' => 'vartable_qty_default[ + loop + ]',
                'label' => __('Default Variation Quantity', 'vartable') . ' ',
                'description' => '',
                'value' => $variation_data['vartable_qty_default'][0],
                'wrapper_class' => 'form-row-last'
            ));
            ?>
        </td>
    </tr>

    <tr>
        <td>
            <?php
            // Checkbox
            woocommerce_wp_textarea_input(array(
                'id' => 'vt_variation_description[ + loop + ]',
                'label' => __('Description', 'vartable') . ' ',
                'description' => '',
                'value' => $variation_data['vt_variation_description'][0]
            ));
            ?>
        </td>
    </tr>


    <tr>
        <td>
            <?php
            woocommerce_wp_checkbox(array(
                'id' => 'vt_variation_hide[ + loop + ]',
                'label' => __('Hide this variation from the table', 'vartable') . ' ',
                'value' => $variation_data['vt_variation_hide'][0]
            ));
            ?>
        </td>
    </tr>
<?php
}
//Save variation fields
add_action('woocommerce_process_product_meta_variable', 'vartable_save_variable_fields', 10, 1);
add_action('woocommerce_save_product_variation', 'vartable_save_variable_fields', 10, 1);

/**
 * Save new fields for variations
 *
 */
function vartable_save_variable_fields($post_id)
{
    if (isset($_POST['variable_post_id'])) {

        $variable_sku = $_POST['variable_sku'];
        $variable_post_id = $_POST['variable_post_id'];

        // Checkbox
        $enbable_variations_table_img = $_POST['enbable_variations_table_img'];
        $override_extra_image = $_POST['override_extra_image'];
        $vartable_qty_step = $_POST['vartable_qty_step'];
        $vartable_qty_default = $_POST['vartable_qty_default'];
        $vt_variation_hide = (isset($_POST['vt_variation_hide']) ? $_POST['vt_variation_hide'] : '');
        $vt_variation_description = $_POST['vt_variation_description'];


        foreach ($variable_post_id as $i => $vid) {
            $variation_id = (int)$variable_post_id[$i];

            if (isset($enbable_variations_table_img[$i]) && $enbable_variations_table_img[$i] != '') {
                update_post_meta($variation_id, 'enbable_variations_table_img', stripslashes($enbable_variations_table_img[$i]));
            } else {
                delete_post_meta($variation_id, 'enbable_variations_table_img');
            }

            if (isset($override_extra_image[$i]) && $override_extra_image[$i] != '') {
                update_post_meta($variation_id, 'override_extra_image', stripslashes($override_extra_image[$i]));
            } else {
                delete_post_meta($variation_id, 'override_extra_image');
            }


            if (isset($vt_variation_description[$i]) && $vt_variation_description[$i]  != '') {
                update_post_meta($variation_id, 'vt_variation_description', stripslashes($vt_variation_description[$i]));
            } else {
                delete_post_meta($variation_id, 'vt_variation_description');
            }

            if (isset($vartable_qty_step[$i]) && $vartable_qty_step[$i]  != '') {
                update_post_meta($variation_id, 'vartable_qty_step', stripslashes($vartable_qty_step[$i]));
            } else {
                delete_post_meta($variation_id, 'vartable_qty_step');
            }

            if (isset($vartable_qty_default[$i]) && $vartable_qty_default[$i] != '') {
                update_post_meta($variation_id, 'vartable_qty_default', stripslashes($vartable_qty_default[$i]));
            } else {
                delete_post_meta($variation_id, 'vartable_qty_default');
            }

            if (isset($vt_variation_hide[$i]) && $vt_variation_hide[$i] != '') {
                update_post_meta($variation_id, 'vt_variation_hide', stripslashes($vt_variation_hide[$i]));
            } else {
                delete_post_meta($variation_id, 'vt_variation_hide');
            }
        }
    }
}

function vartable_combinations($arrays, $i = 0)
{
    $key = array_keys($arrays);
    if (!isset($arrays[$key[$i]])) {
        return array();
    }
    if ($i == count($arrays) - 1) {
        return $arrays[$key[$i]];
    }

    // get vartable_combinations from subsequent arrays
    $tmp = vartable_combinations($arrays, $i + 1);

    $result = array();

    // concat each array from tmp with each element from $arrays[$i]
    foreach ($arrays[$key[$i]] as $v) {
        foreach ($tmp as $t) {
            $result[] = is_array($t) ? array_merge(array(
                $key[$i] => $v
            ), $t) : array(
                $key[$i] => $v,
                $key[($i + 1)] => $t
            );
        }
    }

    return $result;
}

/****
 *  
  Get all cateogries
 * 
 ****/
function woovartables_get_all_categories($selected)
{
    global $wpdb, $sitepress;

    if ($sitepress) {
        // remove WPML term filters
        remove_filter('get_terms_args', array(
            $sitepress,
            'get_terms_args_filter'
        ));
        remove_filter('get_term', array(
            $sitepress,
            'get_term_adjust_id'
        ));
        remove_filter('terms_clauses', array(
            $sitepress,
            'terms_clauses'
        ));
    }

    if (empty($selected)) {
        $selected = array();
    }

    $output = '';

    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    );

    $terms = get_terms($args);

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $output .= '<option ' . (in_array($term->term_id, $selected) ? 'selected' : '') . ' value="' . $term->term_id . '">' . $term->name . '</option>';
        }
    }

    if ($sitepress) {
        // restore WPML term filters
        add_filter('terms_clauses', array(
            $sitepress,
            'terms_clauses'
        ));
        add_filter('get_term', array(
            $sitepress,
            'get_term_adjust_id'
        ));
        add_filter('get_terms_args', array(
            $sitepress,
            'get_terms_args_filter'
        ));
    }

    return $output;
}

function spyros_media_upload($fname, $value = '', $ai = '')
{

    // This will enqueue the Media Uploader script
    wp_enqueue_media();
?>
    <div>
        <input type="text" name="<?php echo $fname; ?>" id="<?php echo $fname; ?>" value="<?php echo $value; ?>" class="regular-text">
        <input type="button" name="upload-btn<?php echo $ai; ?>" id="upload-btn<?php echo $ai; ?>" class="button-secondary button button-action" value="<?php echo __('Open Media Manager', 'vartable'); ?>"><br />
        <img class="img_<?php echo $ai; ?>" src="<?php echo $value; ?>" />

    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('#upload-btn<?php echo $ai; ?>').click(function(e) {
                e.preventDefault();
                var image = wp.media({
                        title: 'Upload Image',
                        // mutiple: true if you want to upload multiple files at once
                        multiple: false
                    }).open()
                    .on('select', function(e) {
                        // This will return the selected image from the Media Uploader, the result is an object
                        var uploaded_image = image.state().get('selection').first();
                        // We convert uploaded_image to a JSON object to make accessing it easier
                        // Output to the console uploaded_image
                        // console.log(uploaded_image);
                        var image_url = uploaded_image.toJSON().url;
                        // console.log(image_url);
                        // Let's assign the url value to the input field
                        jQuery('input[name="<?php echo $fname; ?>"]').val(image_url);
                        jQuery('img.img_<?php echo $ai; ?>').attr('src', image_url);
                    });
            });
        });
    </script>
<?php
}

// Add settings link on plugin page
function vartable_plugin_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=variationstable">' . __('Settings', 'vartable') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

$vartable_plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$vartable_plugin", 'vartable_plugin_settings_link');

function vt_resetajaxfix()
{
    $_GLOBALS['vtajaxfix'] = 0;
}
add_action('wp_footer', 'vt_resetajaxfix');

// remove gift wrap frontend hook
if (class_exists('WC_Product_Gift_Wrap')) {
    require_once 'wp-filters-extra.php';
    function vartable_gifthook_the_remove()
    {
        vartable_remove_filters_for_anonymous_class('woocommerce_after_add_to_cart_button', 'WC_Product_Gift_Wrap', 'gift_option_html', 10);
    }
    add_action('plugins_loaded', 'vartable_gifthook_the_remove', 1);
}

add_action('plugins_loaded', 'vartable_wc_swatches_on_plugin_loaded', 1000);
function vartable_wc_swatches_on_plugin_loaded()
{

    if (function_exists('wc_swatches_variation_attribute_options')) {

        require_once 'integrations/woocommerce-swatches.php';
    }
}

add_action('wp_ajax_add_variation_to_cart', 'vartable_ajax_add_variation_to_cart');
add_action('wp_ajax_nopriv_add_variation_to_cart', 'vartable_ajax_add_variation_to_cart');

function vartable_ajax_add_variation_to_cart()
{

    ob_start();

    $product_id = apply_filters('vartable_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);

    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : '';
    $variations = isset($_POST['variations']) ? json_decode(str_replace('||||||', '\"', stripslashes($_POST['variations'])), true) : '';

    $passed_validation = apply_filters('vartable_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);

    if (
        $passed_validation && WC()
        ->cart
        ->add_to_cart($product_id, $quantity, $variation_id, $variations)
    ) {

        do_action('woocommerce_set_cart_cookies', TRUE);
        do_action('vartable_ajax_added_to_cart', $product_id);

        if (get_option('woocommerce_cart_redirect_after_add') == 'yes' || get_option('vartable_ajax') != 1) {

            wc_add_to_cart_message(array(
                $product_id => $quantity
            ), true);
        }

        // Return fragments
        if (get_option('vartable_ajax') == 1) {
            WC_AJAX::get_refreshed_fragments();
        }
    } else {

        // If there was an error adding to the cart, redirect to the product page to show any errors

        $error_messages = false;
        if (wc_notice_count('error') > 0) {
            $error_notices = wc_get_notices('error');
            if (!empty($error_notices)) {
                foreach ($error_notices as $error_notice) {
                    $error_messages[] = $error_notice['notice'];
                }
            }
        }
        wc_clear_notices();

        $data = array(
            'error' => true,
            'error_message' => implode('<br />', $error_messages),
            'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
        );

        wp_send_json($data);
    }

    die();
}

function vtdb($out)
{
    if (is_user_logged_in()) {
        $out = '<pre>' . print_r($out, true) . '</pre>';
    }
    return $out;
}

function vartable_is_plugin_active($plugin)
{
    return in_array($plugin, (array)get_option('active_plugins', array()));
}

function vartable_delete_all_between($beginning, $end, $string)
{
    $beginningPos = strpos($string, $beginning);
    $endPos = strpos($string, $end);
    if ($beginningPos === false || $endPos === false) {
        return $string;
    }

    $textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);

    return str_replace($textToDelete, $beginning . $end, $string);
}

function vartable_get_editable_roles()
{

    $roles = array();

    foreach (get_editable_roles() as $role_name => $role_info) {

        $roles[$role_name] = $role_info['name'];
    }

    return $roles;
}

/* WooCommerce version 2.x and 3.x compatibility functions */

function vt_get_price_including_tax($product, $args)
{
    if (WC_VERSION < 3.0) {
        return $product->get_price_including_tax($args['qty'], $args['price']);
    } else {
        return wc_get_price_including_tax($product, $args);
    }
}

function vt_get_parent_id($prod_obj)
{
    if (WC_VERSION < 3.0) {
        return $prod_obj->post->ID;
    } else {
        return $prod_obj->get_parent_id();
    }
}

function vt_cart_icons()
{

    return apply_filters('vartableall_cart_icons', array(
        '<svg width="24px" height="24px" viewBox="0 -2.55 20.095 20.095" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_13" data-name="Path 13" d="M437.249,829.36a1.874,1.874,0,0,0,1.72,1.633H447.1c.9,0,1.24-.72,1.626-1.633l1.93-4.382H440l-.136-.964h12.2l-2.262,5.346c-.678,1.556-1.213,2.66-2.709,2.66h-8.128a2.664,2.664,0,0,1-2.71-2.66l-.8-7.36h-3.484v-1h4.406Zm1.225,3.64a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,438.474,833Zm-.531,1.969h1V834h-1ZM446.474,833a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,446.474,833Zm-.531,1.969h1V834h-1Z" transform="translate(-431.973 -821)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="0 -2.2 19.438 19.438" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_10" data-name="Path 10" d="M346.729,827.961l-1.35,5.437c-.7,1.993-1.189,2.6-2.656,2.6h-8.5c-1.467,0-2.05-.463-2.656-2.6l-1.35-5.437a1.5,1.5,0,0,1,.037-2.992h2.078l3.74-4,.895.841-2.98,3.157h9.444l-3.452-3.157.9-.841,4.34,4h1.478a1.5,1.5,0,0,1,.037,2.992Zm-14.1,5.469a1.677,1.677,0,0,0,1.594,1.561h8.5c.88,0,1.382-.605,1.594-1.561l1.295-5.443H331.335Zm13.039-7.444h-14.4c-.733,0-1.328.229-1.328.512s.595.511,1.328.511h14.4c.734,0,1.328-.229,1.328-.511S346.4,825.986,345.668,825.986Z" transform="translate(-328.754 -820.971)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="0 -3 17 17" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_8" data-name="Path 8" d="M278.973,826.978l-1.594,6.42c-.7,1.993-1.189,2.6-2.656,2.6h-8.5c-1.467,0-2.05-.463-2.656-2.6l-1.594-6.42h3.018A2.214,2.214,0,0,1,267.246,825H273.7a2.215,2.215,0,0,1,2.255,1.978Zm-14.344,6.452a1.677,1.677,0,0,0,1.594,1.561h8.5c.88,0,1.382-.605,1.594-1.561l1.295-5.443H263.335Zm9.072-7.448h-6.454a1.244,1.244,0,0,0-1.327,1h9.108A1.245,1.245,0,0,0,273.7,825.982Zm.256,5h-6.968v-1h6.968Zm-1,2h-4.968v-1h4.968Z" transform="translate(-261.973 -825)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="0 -1.02 19.036 19.036" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_11" data-name="Path 11" d="M379.806,829.36c-.678,1.556-1.213,2.66-2.709,2.66h-8.128a2.664,2.664,0,0,1-2.71-2.66l-.316-5.346v-1.722l-2.911-2.589.7-.708,3.158,2.755h.049v2.264h15.125Zm-12.849-4.382.292,4.382a1.874,1.874,0,0,0,1.72,1.633H377.1c.9,0,1.24-.72,1.626-1.633l1.93-4.382Zm2.017,1.013h8.949v1h-8.949ZM375.952,829h-6.978v-1h6.978Zm-7.478,4a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,368.474,833Zm-.531,1.969h1V834h-1ZM376.474,833a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,376.474,833Zm-.531,1.969h1V834h-1Z" transform="translate(-363.032 -818.995)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="-0.5 0 13 13" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_2" data-name="Path 2" d="M106.974,837h-12l2-11.031h1.989a1.98,1.98,0,0,1,3.96,0h2.051Zm-6-12.011c-1.042-.01-1.04.338-1.04.98h2.049C101.983,825.367,102.013,825,100.974,824.989ZM102.925,827v.994h-.943V827H99.933v.994h-.974V827H97.848l-1.75,9h9.719l-1.75-9ZM101,824c-.021,0-.041.005-.062.006s-.041-.006-.063-.006Z" transform="translate(-94.974 -824)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="-1.5 0 13 13" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_1" data-name="Path 1" d="M80.974,837h-10V825.969h1.989A2.023,2.023,0,0,1,75,824H76.88a2.021,2.021,0,0,1,2.042,1.969h2.052Zm-5-12.011c-1.374,0-2.015.339-2.033.98h4.035C77.961,825.368,77.207,824.978,75.974,824.989Zm4,2.011H78.926v.994h-.943V827H73.934v.994H72.96V827H71.943v9h8.031Z" transform="translate(-70.974 -824)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="0 -2.55 20.094 20.094" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_15" data-name="Path 15" d="M518.8,829.36c-.678,1.556-1.213,2.66-2.709,2.66h-8.128a2.664,2.664,0,0,1-2.71-2.66l-.8-7.36h-3.484v-1h4.375l.361,3.014h15.358Zm-12.556,0a1.874,1.874,0,0,0,1.72,1.633H516.1c.9,0,1.271-.72,1.657-1.633l1.837-4.382H505.8Zm1.225,3.64a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,507.474,833Zm-.531,1.969h1V834h-1ZM515.474,833a1.5,1.5,0,1,1-1.5,1.5A1.5,1.5,0,0,1,515.474,833Zm-.531,1.969h1V834h-1Z" transform="translate(-500.973 -821)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="-0.5 0 12 12" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_3" data-name="Path 3" d="M130.7,837h-6.454a2.283,2.283,0,0,1-2.273-2.292V825h11v9.708A2.284,2.284,0,0,1,130.7,837Zm1.269-10.99h-9.025v8.6a1.369,1.369,0,0,0,1.363,1.375h6.3a1.369,1.369,0,0,0,1.363-1.375Zm-4.528,3.948v.032a2.506,2.506,0,0,1-2.492-2.323V827h1v.667a1.482,1.482,0,0,0,1.489,1.375,1.511,1.511,0,0,0,1.52-1.375V827h.971v.667A2.477,2.477,0,0,1,127.442,829.958Z" transform="translate(-121.974 -825)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="-2 0 14.99 14.99" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_5" data-name="Path 5" d="M181.7,837h-6.454a2.283,2.283,0,0,1-2.273-2.292V825h2.977v-.667a2.506,2.506,0,0,1,2.492-2.323v.032a2.478,2.478,0,0,1,2.491,2.291V825h3.04v9.708A2.283,2.283,0,0,1,181.7,837Zm-6.392-1.01h6.3a1.369,1.369,0,0,0,1.363-1.375V828h-9.025v6.615A1.368,1.368,0,0,0,175.309,835.99Zm4.653-11.657a1.511,1.511,0,0,0-1.52-1.375,1.482,1.482,0,0,0-1.489,1.375V825h3.009Zm-6.017,1.677V827h9.025v-.99Z" transform="translate(-172.974 -822.01)" fill="currentColor"/>
		</svg>',
        '<svg width="24px" height="24px" viewBox="0 -2.51 17 17" xmlns="http://www.w3.org/2000/svg">
		  <path id="Path_6" data-name="Path 6" d="M214.973,826.978l-1.594,6.42c-.7,1.993-1.188,2.6-2.656,2.6h-8.5c-1.467,0-2.05-.463-2.656-2.6l-1.594-6.42h3.152l0,0,2.85-2.947h5l2.85,2.947,0,0ZM200.63,833.43a1.677,1.677,0,0,0,1.594,1.561h8.5c.88,0,1.382-.605,1.594-1.561l1.295-5.443H199.335Zm7.742-8.425h-3.8l-1.951,1.973h7.7Zm.6,4.995h1v2.969h-1Zm-3,0h1v2.978h-1Zm-3,0h1v2.978h-1Z" transform="translate(-197.973 -824.029)" fill="currentColor"/>
		</svg>',
    ));
}
