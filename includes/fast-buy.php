<?php
$filters_type = woodmart_get_opt('portfolio_filters_type', 'masonry');
$filters = woodmart_get_opt('portoflio_filters');
$content_class = woodmart_get_content_class();
add_filter('is_active_sidebar', false);
if ('fragments' === woodmart_is_woo_ajax()) {
    woodmart_get_portfolio_main_loop(true);
    die();
}

if (!woodmart_is_woo_ajax()) {
    get_header();
} else {
    woodmart_page_top_part();
}

$settings = get_option('woofaster_options');

if (!isset($settings['show_cart']) || $settings['show_cart'] == 'inactive') {
    echo '<style>a.added_to_cart {display: none;}</style>';
}

?>
<div class="fast-buy categories <?php echo esc_attr($content_class); ?>">
    <div class="site-content page-fast-buy <?php echo esc_attr($content_class); ?>" role="main">
        <h1 class="text-center">
            <?= single_post_title() ?>
        </h1>
        <?php if (check_license($settings['license'])) : ?>
            <div class="d-flex mb-5">
                <?php
                $taxonomy = 'product_cat';
                $category_main = get_term_by('slug', 'woofaster-fast-buy', 'product_cat')->term_id;
                $orderby = 'date';
                $show_count = 0; // 1 for yes, 0 for no
                $pad_counts = 0; // 1 for yes, 0 for no
                $hierarchical = 1; // 1 for yes, 0 for no  
                $title = '';
                $empty = 0;

                $argsMainCat = array(
                    'taxonomy' => $taxonomy,
                    'child_of' => 0,
                    'parent' => $category_main,
                    'orderby' => $orderby,
                    'order' => 'DESC',
                    'show_count' => $show_count,
                    'pad_counts' => $pad_counts,
                    'hierarchical' => $hierarchical,
                    'title_li' => $title,
                    'hide_empty' => $empty
                );
                $main_categories = get_categories($argsMainCat);
                if (!empty($main_categories) && !is_wp_error($main_categories) && (!isset($settings['header_category']) || $settings['header_category'] == 'active')) {
                    foreach ($main_categories as $category) {
                        // verify that this is a product category page
                        // get the thumbnail id using the queried category term_id
                        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);

                        // get the image URL
                        $image = wp_get_attachment_url($thumbnail_id);

                        // print the IMG HTML
                        echo "<div class='sub-category col-lg-2 col-6'><a class='cat-link' href='#{$category->slug}'><img class='cat-thumbnail' src='{$image}' alt='{$category->name}' target='_blank'>";
                        echo "<span class='cat-title'>{$category->name}</span></a></div>";
                    }
                }
                ?>
            </div>
            <div class="mt-2">
                <?php
                if (!empty($main_categories) && !is_wp_error($main_categories)) {
                    foreach ($main_categories as $category) {
                        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
                        $image = wp_get_attachment_url($thumbnail_id);
                        echo "<div class='card-title collapsible text-center' style='background:{$settings['category_color']}' id='{$category->slug}'><img class='subcat-thumbnail-title' src='{$image}' alt='{$category->name}' />{$category->name}</div><div class='card-body'>";

                        $argsSubCat = [
                            'taxonomy' => $taxonomy,
                            'child_of' => 0,
                            'parent' => $category->term_id,
                            'orderby' => $orderby,
                            'show_count' => $show_count,
                            'pad_counts' => $pad_counts,
                            'hierarchical' => $hierarchical,
                            'title_li' => $title,
                            'hide_empty' => $empty
                        ];
                        if (count(get_categories($argsSubCat)) > 0)
                            $sub_sub_cat = get_categories($argsSubCat);
                        else
                            $sub_sub_cat = [$category];
                        foreach ($sub_sub_cat as $sscat) {
                            if (count(get_categories($argsSubCat)) > 0) {
                                $thumbnail_id = get_term_meta($sscat->term_id, 'thumbnail_id', true);
                                $image = wp_get_attachment_url($thumbnail_id);
                                echo "<div class='card-sub-title collapsible text-center' style='background:{$settings['subcategory_color']}' id='{$sscat->slug}'><img class='subcat-thumbnail-title' src='{$image}' alt='{$sscat->name}' />{$sscat->name}</div><div class='card-body'>";
                            }

                            $argsProductsCat = new WC_Product_Query([
                                // 'category' => $sscat->slug,
                                'orderby' => 'date',
                                'order' => 'DESC',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'product_cat',
                                        'field'    => 'term_id',
                                        'terms'     =>  [$sscat->term_id], // When you have more term_id's seperate them by komma.
                                        'operator'  => 'IN'
                                    ]
                                ]
                            ]);
                            $products = $argsProductsCat->get_products();
                            if (count($products) > 0) {
                                foreach ($products as $product) :
                                    $product_item = wc_get_product($product); ?>
                                    <div class="product-title collapsible">
                                        <a href="<?php echo esc_url(get_permalink($product_item->get_id())); ?>" title="<?php echo esc_attr($product_item->get_title()); ?>">
                                            <?php echo $product_item->get_title(); ?>
                                        </a>
                                        <?php
                                        if (count($product->get_children()) == 0) { ?>
                                            <span class="not-available">عدم موجودی</span>
                                        <?php } ?>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        foreach ($product->get_children() as $child) :
                                            $product_child = wc_get_product($child); ?>
                                            <div class="product-item">
                                                <div class="product-color">
                                                    <?php
                                                    $attributes = $product_child->get_attributes();
                                                    // echo $attributes['pa_color'];
                                                    echo "<span class='attr-color' style='background:{$attributes['pa_color']}'></span>" . $product_child->get_attribute('pa_color');
                                                    ?>
                                                </div>
                                                <span class="product-price">
                                                    <?php echo $product_child->get_price_html(); ?>
                                                </span>
                                                <span class="product-addtocard">
                                                    <?php
                                                    echo apply_filters(
                                                        'woocommerce_loop_add_to_cart_link',
                                                        sprintf(
                                                            '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" class="button %s product_type_%s">%s</a>',
                                                            esc_url($product_child->add_to_cart_url($product_child->get_id(), 1)),
                                                            esc_attr($product_child->get_id()),
                                                            esc_attr($product_child->get_sku()),
                                                            implode(
                                                                ' ',
                                                                array_filter(
                                                                    [
                                                                        'button',
                                                                        'product_type_' . $product_child->product_type,
                                                                        $product_child->is_purchasable() && $product_child->is_in_stock() ? 'add_to_cart_button' : '',
                                                                        $product_child->supports('ajax_add_to_cart') ? 'ajax_add_to_cart' : ''
                                                                    ]
                                                                )
                                                            ),
                                                            esc_attr($product_child->product_type),
                                                            $product_child->add_to_cart_text(),
                                                            esc_attr(isset($class) ? $class : 'button'),
                                                        ),
                                                        $product_child
                                                    );
                                                    ?>
                                                </span>
                                            </div>
                                        <?php
                                        endforeach;
                                        ?>
                                    </div> <?php
                                        endforeach;
                                            ?>
            </div> <?php
                            } else {
                                echo __('در این دسته محصولی یافت نشد!');
                            }
                            wp_reset_postdata();
                        }
                    ?>
    </div> <?php
                    }
                }
                wp_reset_postdata();
            ?>
</div>
<?php
        else :
            echo '<center>افزونه شما بصورت قانونی فعال نشده است لطفا از طریق <a href="/wp-admin/admin.php?page=woofaster">پنل پیشخوان</a> نسبت به فعالسازی آن اقدام نمائید.</center>';
        endif;
?>
</div>
</div>
<script>
    var coll = document.getElementsByClassName("collapsible");
    var i;
    for (i = 0; i < coll.length; i++) {
        coll[i].addEventListener("click", function() {
            this.classList.toggle("active");
            var content = this.nextElementSibling;
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
            } else {
                content.style.maxHeight = "0px"; //content.scrollHeight + "px";
            }
        });
    }
</script>
<?php
if (!woodmart_is_woo_ajax()) {
    get_footer();
} else {
    woodmart_page_bottom_part();
}
