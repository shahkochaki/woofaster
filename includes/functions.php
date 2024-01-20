<?php

/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */

/**
 * custom option and settings
 */
function woofaster_settings_init()
{
	// Register a new setting for "woofaster" page.
	register_setting('woofaster', 'woofaster_options');

	// Register a new section in the "woofaster" page.
	add_settings_section(
		'woofaster_section_developers',
		'تنظیمات افزونه ایجاد صفحه خرید سریع',
		'woofaster_section_developers_callback',
		'woofaster'
	);

	add_settings_field(
		'woofaster_license',
		'لایسنس',
		'woofaster_field_license',
		'woofaster',
		'woofaster_section_developers',
		[
			'label_for'         => 'license',
			'class'             => 'row',
			'woofaster_custom_data' => 'custom',
		]
	);

	add_settings_field(
		'woofaster_header_category',
		'دسته بندی های فعال',
		'woofaster_field_header_category',
		'woofaster',
		'woofaster_section_developers',
		[
			'label_for'         => 'header_category',
			'class'             => 'row',
			'woofaster_custom_data' => 'custom',
		]
	);

	add_settings_field(
		'woofaster_show_cart',
		'گزینه مشاهده سبد خرید',
		'woofaster_field_show_cart',
		'woofaster',
		'woofaster_section_developers',
		[
			'label_for'         => 'show_cart',
			'class'             => 'row',
			'woofaster_custom_data' => 'custom',
		]
	);

	add_settings_field(
		'woofaster_category_color',
		'پس زمینه دسته بندی های اصلی',
		'woofaster_field_color',
		'woofaster',
		'woofaster_section_developers',
		[
			'label_for'         => 'category_color',
			'class'             => 'row',
			'woofaster_custom_data' => 'custom',
		]
	);

	add_settings_field(
		'woofaster_subcategory_color',
		'پس زمینه دسته بندی های زیرمجموعه',
		'woofaster_field_color',
		'woofaster',
		'woofaster_section_developers',
		[
			'label_for'         => 'subcategory_color',
			'class'             => 'row',
			'woofaster_custom_data' => 'custom',
		]
	);
}

/**
 * Register our woofaster_settings_init to the admin_init action hook.
 */
add_action('admin_init', 'woofaster_settings_init');

function woofaster_section_developers_callback($args)
{
?>
	<img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/header.jpg' ?>" alt="Woo Faster">
	<p id="<?php echo esc_attr($args['id']); ?>">تنظیمات ظاهری - نسخه v1.0.2</p>
<?php
}


function woofaster_field_license($args)
{
	// Get the value of the setting we've registered with register_setting()
	$options = get_option('woofaster_options');
?>
	<input type="text" dir="ltr" name="woofaster_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo $options[$args['label_for']]; ?>">
	<b><?php if (check_license()) echo 'لایسنس شما با موفقیت فعال شده است.';
		else echo 'لایسنس نا معتبر'; ?></b>
	<p class="description">
		لطفا کد فعالسازی دریافت شده را در این قسمت وارد نمائید.
	</p>
	<p class="description">
		جهت دریافت کد فعالسازی لطفا از طریق سایت فروشنده افزونه اقدام نمائید. در غیر اینصورت از سایت <a href="https://livedes.com/plugins/woofaster" target="_blank">LiveDes</a> اقدام به فعالسازی افزونه نمائید.
	</p>
<?php
}

function woofaster_field_header_category($args)
{
	// Get the value of the setting we've registered with register_setting()
	$options = get_option('woofaster_options');
?>
	<select id="<?php echo esc_attr($args['label_for']); ?>" data-custom="<?php echo esc_attr($args['woofaster_custom_data']); ?>" name="woofaster_options[<?php echo esc_attr($args['label_for']); ?>]">
		<option value="active" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'active', false)) : (''); ?>>
			فعال
		</option>
		<option value="inactive" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'inactive', false)) : (''); ?>>
			غیرفعال
		</option>
	</select>
	<p class="description">
		جهت فعالسازی دسته بندی های اصلی می توانید این گزینه را انتخاب نمائید.
	</p>
	<p class="description">
		در صورت فعال بودن این گزینه در بالای صفحه دسته بندی های جهت دسترسی بهتر نمایش داده می شود.
	</p>
<?php
}


function woofaster_field_show_cart($args)
{
	// Get the value of the setting we've registered with register_setting()
	$options = get_option('woofaster_options');
?>
	<select id="<?php echo esc_attr($args['label_for']); ?>" data-custom="<?php echo esc_attr($args['woofaster_custom_data']); ?>" name="woofaster_options[<?php echo esc_attr($args['label_for']); ?>]">
		<option value="active" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'active', false)) : (''); ?>>
			فعال
		</option>
		<option value="inactive" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'inactive', false)) : (''); ?>>
			غیرفعال
		</option>
	</select>
	<p class="description">
		جهت مشاهده گزینه مشاهده سبد خرید در لیست آیتم ها این گزینه را فعال نمائید.
	</p>
<?php
}

function woofaster_field_color($args)
{
	// Get the value of the setting we've registered with register_setting()
	$options = get_option('woofaster_options');
?>
	<input type="color" name="woofaster_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo $options[$args['label_for']]; ?>">
<?php
}

/**
 * Add the top level menu page.
 */
function woofaster_options_page()
{
	add_menu_page(
		'Woo Faster',
		'Woo Faster',
		'manage_options',
		'woofaster',
		'woofaster_options_page_html',
		plugin_dir_url(__FILE__) . '../assets/images/favicon.png',
	);
}


/**
 * Register our woofaster_options_page to the admin_menu action hook.
 */
add_action('admin_menu', 'woofaster_options_page');


/**
 * Top level menu callback function
 */
function woofaster_options_page_html()
{
	// check user capabilities
	if (!current_user_can('manage_options')) {
		return;
	}

	// add error/update messages

	// check if the user have submitted the settings
	// WordPress will add the "settings-updated" $_GET parameter to the url
	if (isset($_GET['settings-updated'])) {
		// add settings saved message with the class of "updated"
		add_settings_error('woofaster_messages', 'woofaster_message', 'تنظیمات با موفقیت ذخیره شد.', 'updated');
	}

	// show error/update messages
	settings_errors('woofaster_messages');
?>
	<div class="wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		<form action="options.php" method="post">
			<?php
			// output security fields for the registered setting "woofaster"
			settings_fields('woofaster');
			// output setting sections and their fields
			// (sections are registered for "woofaster", each field is registered to a specific section)
			do_settings_sections('woofaster');
			// output save settings button
			submit_button('ذخیره تغییرات');
			?>
		</form>
	</div>
<?php
}

// The shortcode function
function wooster_shortcode_fast_buy()
{
	// Ad code returned
	return require_once plugin_dir_path(__FILE__) . '/fast-buy.php';;
}
// Register shortcode
add_shortcode('woofaster_fast_buy', 'wooster_shortcode_fast_buy');


function woofaster_load_assets()
{
	wp_enqueue_style('r-prismcss', plugin_dir_url(__FILE__) . "../assets/css/style.css");
}

add_action('wp_enqueue_scripts', 'woofaster_load_assets');

// Add category woofaster in woocommerce categories
function add_woofaster_woocommerce_category()
{
	$term = term_exists('خریدسریع', 'product_cat');
	if (!$term) {
		wp_insert_term(
			'خریدسریع',
			'product_cat',
			array(
				'description' => 'دسته مخصوص صفحه خرید سریع | دسته بندی هایی که قصد ایجاد خرید سریع برای آنها دارید  زیر مجموعه این دسته قرار دهید',
				'slug' => 'woofaster-fast-buy'
			)
		);
	}
}
add_filter('init', 'add_woofaster_woocommerce_category');
// Add category woofaster in woocommerce categories


function check_license()
{
	$options = get_option('woofaster_options');
	if (hash('sha256', substr($options['license'], 0, 7) . 'woofaster') == substr($options['license'], 7))
		return true;
	else
		return false;
}
