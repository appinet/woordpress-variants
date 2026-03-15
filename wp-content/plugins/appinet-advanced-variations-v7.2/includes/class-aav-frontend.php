<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAV_Frontend
{
    private $variation_form_moved = false;

    public function __construct()
    {
        add_filter('woocommerce_available_variation', [$this, 'add_variation_data'], 20, 3);
        add_filter('woocommerce_dropdown_variation_attribute_options_args', [$this, 'preselect_variation_dropdown'], 20);
        add_action('woocommerce_single_product_summary', [$this, 'render_containers'], 35);
        add_filter('woocommerce_product_tabs', [$this, 'register_tab']);
        add_action('wp', [$this, 'maybe_reposition_variation_form'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_fields']);
    }

    public function maybe_reposition_variation_form()
    {
        if ($this->variation_form_moved || !function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return;
        }

        $target = $this->get_variation_form_target((int) $product->get_id());

        if ($target === null) {
            return;
        }

        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        add_action($target['hook'], 'woocommerce_template_single_add_to_cart', $target['priority']);
        $this->variation_form_moved = true;
    }

    public function add_variation_data($data, $product = null, $variation = null)
    {
        $variation_id = !empty($data['variation_id']) ? (int) $data['variation_id'] : 0;

        $data['aav_ingredients'] = wp_kses_post(get_post_meta($variation_id, '_aav_ingredients', true));
        $data['aav_flavor_desc'] = wp_kses_post(get_post_meta($variation_id, '_aav_flavor_desc', true));
        $data['aav_nutrition']   = wp_kses_post(get_post_meta($variation_id, '_aav_nutrition', true));
        $data['aav_badge']       = sanitize_text_field(get_post_meta($variation_id, '_aav_badge', true));
        $data['aav_video_url']   = esc_url_raw(get_post_meta($variation_id, '_aav_video_url', true));
        $data['aav_pdf_id']      = (int) get_post_meta($variation_id, '_aav_pdf_id', true);
        $data['aav_pdf_url']     = $data['aav_pdf_id'] ? wp_get_attachment_url($data['aav_pdf_id']) : '';
        $data['aav_gallery']     = $this->get_gallery_urls($variation_id);

        return $data;
    }

    public function render_containers()
    {
        global $product;

        if (!$product || !$product->is_type('variable')) {
            return;
        }
        ?>
        <div id="aav-variation-content" class="aav-variation-content" style="margin-top:20px;">
            <div id="aav-badge" class="aav-block aav-badge" style="display:none;"></div>
            <div id="aav-flavor-desc" class="aav-block" style="display:none;"></div>
            <div id="aav-ingredients" class="aav-block" style="display:none;"></div>
            <div id="aav-nutrition" class="aav-block" style="display:none;"></div>
            <div id="aav-pdf" class="aav-block" style="display:none;"></div>
            <div id="aav-video" class="aav-block" style="display:none;"></div>
            <div id="aav-gallery" class="aav-block" style="display:none;"></div>
        </div>
        <?php
    }

    public function register_tab($tabs)
    {
        global $product;

        if (!$product || !$product->is_type('variable')) {
            return $tabs;
        }

        $tabs['aav_variation_info'] = [
            'title'    => __('Informacje o wariancie', 'appinet-advanced-variations'),
            'priority' => 60,
            'callback' => [$this, 'render_tab_content'],
        ];

        return $tabs;
    }

    public function render_tab_content()
    {
        echo '<div id="aav-tab-variation-content">';
        echo '<p>Wybierz wariant, aby zobaczyć dodatkowe informacje.</p>';
        echo '</div>';
    }

    public function enqueue_assets()
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        $settings = AAV_Admin::get_settings();
        $current_variation_id = 0;
        global $product;

        if ($product && is_object($product) && method_exists($product, 'is_type') && $product->is_type('variable')) {
            $path = get_query_var('aav_variation_path');
            if ($path) {
                $current_variation_id = AAV_Variation_URLs::resolve_variation_id($product, sanitize_text_field(wp_unslash($path)));
            }
        }
        $form_location = $product && is_object($product) && method_exists($product, 'get_id')
            ? $this->get_variation_form_location((int) $product->get_id())
            : ['position' => 'default'];

        $button_presentations = $this->get_button_presentations($product);
        $button_presentations_flat = array_merge(
            $this->get_global_button_presentations_flat(),
            $this->get_button_presentations_flat($product)
        );
        $resolved_button_text_transform = $this->normalize_text_transform($settings['button_text_transform'] ?? 'none');
        $resolved_media_label_text_transform = $this->normalize_text_transform($settings['button_media_label_text_transform'] ?? '');
        if ($resolved_media_label_text_transform === 'none') {
            $resolved_media_label_text_transform = $resolved_button_text_transform;
        }

        wp_enqueue_script(
            'aav-frontend',
            AAV_URL . 'assets/js/frontend.js',
            ['jquery'],
            AAV_VERSION,
            true
        );

        wp_localize_script('aav-frontend', 'aavData', [
            'productBaseUrl' => trailingslashit(get_permalink()),
            'currentVariationId' => $current_variation_id,
            'displayMode' => $settings['display_mode'],
            'buttonIcon' => $settings['button_icon'],
            'buttonAnimation' => $settings['button_animation'],
            'buttonTextTransform' => $resolved_button_text_transform,
            'buttonMediaLabelTextTransform' => $resolved_media_label_text_transform,
            'variationFormLocation' => $form_location,
            'buttonPresentations' => $button_presentations,
            'buttonPresentationsFlat' => $button_presentations_flat,
        ]);
        wp_add_inline_script('aav-frontend', $this->get_text_transform_inline_script($resolved_button_text_transform, $resolved_media_label_text_transform), 'after');

        wp_enqueue_style(
            'aav-frontend',
            AAV_URL . 'assets/css/frontend.css',
            [],
            AAV_VERSION
        );

        wp_add_inline_style('aav-frontend', $this->get_button_styles($settings));
    }

    private function normalize_text_transform($value)
    {
        $value = (string) $value;
        return in_array($value, ['uppercase', 'lowercase', 'capitalize', 'none'], true) ? $value : 'none';
    }

    private function get_variation_form_position($product_id)
    {
        $settings = AAV_Admin::get_settings();
        $position = (string) ($settings['variation_form_position'] ?? 'default');

        if (function_exists('get_field')) {
            $acf_position = get_field('aav_variants_position', $product_id);
            if (is_string($acf_position) && $acf_position !== '') {
                $position = $acf_position;
            }
        }

        return in_array($position, ['default', 'under_title', 'under_price', 'under_excerpt', 'after_summary', 'custom_hook', 'acf_field'], true)
            ? $position
            : 'default';
    }

    private function get_variation_form_target($product_id)
    {
        $position = $this->get_variation_form_position($product_id);
        if ($position === 'acf_field') {
            return null;
        }

        if ($position === 'custom_hook') {
            $custom_target = $this->get_custom_variation_form_target($product_id);
            if ($custom_target !== null) {
                return $custom_target;
            }
        }

        switch ($position) {
            case 'under_title':
                return ['hook' => 'woocommerce_single_product_summary', 'priority' => 6];
            case 'under_price':
                return ['hook' => 'woocommerce_single_product_summary', 'priority' => 11];
            case 'under_excerpt':
                return ['hook' => 'woocommerce_single_product_summary', 'priority' => 21];
            case 'after_summary':
                return ['hook' => 'woocommerce_after_single_product_summary', 'priority' => 5];
            default:
                return null;
        }
    }

    private function get_custom_variation_form_target($product_id)
    {
        $settings = AAV_Admin::get_settings();
        $hook = sanitize_key((string) ($settings['variation_form_custom_hook'] ?? ''));
        $priority = max(1, min(999, (int) ($settings['variation_form_custom_priority'] ?? 10)));

        if (function_exists('get_field')) {
            $acf_hook = get_field('aav_variants_custom_hook', $product_id);
            $acf_priority = get_field('aav_variants_custom_priority', $product_id);

            if (is_string($acf_hook) && $acf_hook !== '') {
                $hook = sanitize_key($acf_hook);
            }

            if ($acf_priority !== null && $acf_priority !== '') {
                $priority = max(1, min(999, (int) $acf_priority));
            }
        }

        if ($hook === '') {
            return null;
        }

        return [
            'hook' => $hook,
            'priority' => $priority,
        ];
    }

    private function get_variation_form_location($product_id)
    {
        $settings = AAV_Admin::get_settings();
        $position = $this->get_variation_form_position($product_id);
        $location = [
            'position' => $position,
        ];

        if ($position !== 'acf_field') {
            return $location;
        }

        $field_name = sanitize_key((string) ($settings['variation_form_acf_field'] ?? ''));
        $placement = in_array(($settings['variation_form_acf_placement'] ?? 'after'), ['before', 'after'], true)
            ? $settings['variation_form_acf_placement']
            : 'after';

        if (function_exists('get_field')) {
            $acf_field_name = get_field('aav_variants_acf_field', $product_id);
            $acf_placement = get_field('aav_variants_acf_placement', $product_id);

            if (is_string($acf_field_name) && $acf_field_name !== '') {
                $field_name = sanitize_key($acf_field_name);
            }

            if (is_string($acf_placement) && in_array($acf_placement, ['before', 'after'], true)) {
                $placement = $acf_placement;
            }
        }

        $location['acfField'] = $field_name;
        $location['acfPlacement'] = $placement;

        return $location;
    }

    private function get_text_transform_inline_script($button_transform, $media_transform)
    {
        $button_transform = esc_js($button_transform);
        $media_transform = esc_js($media_transform);

        return <<<JS
jQuery(function ($) {
    function applyAavResolvedTextTransforms(context) {
        var \$scope = context && context.length ? context : $(document);
        \$scope.find('.aav-attribute-button').each(function () {
            var \$button = $(this);
            var transform = \$button.hasClass('has-media') ? '{$media_transform}' : '{$button_transform}';
            \$button.find('.aav-attribute-button-label').attr('style', 'text-transform:' + (transform || 'none') + ' !important;');
        });
    }

    applyAavResolvedTextTransforms($(document));
    $(document).on('woocommerce_update_variation_values found_variation reset_data', function () {
        applyAavResolvedTextTransforms($(document));
    });
});
JS;
    }

    public function register_rest_fields()
    {
        register_rest_field('product_variation', 'aav_data', [
            'get_callback' => function ($object) {
                $variation_id = (int) $object['id'];
                return [
                    'ingredients' => wp_kses_post(get_post_meta($variation_id, '_aav_ingredients', true)),
                    'flavor_desc' => wp_kses_post(get_post_meta($variation_id, '_aav_flavor_desc', true)),
                    'nutrition'   => wp_kses_post(get_post_meta($variation_id, '_aav_nutrition', true)),
                    'badge'       => sanitize_text_field(get_post_meta($variation_id, '_aav_badge', true)),
                    'video_url'   => esc_url_raw(get_post_meta($variation_id, '_aav_video_url', true)),
                    'pdf_id'      => (int) get_post_meta($variation_id, '_aav_pdf_id', true),
                    'pdf_url'     => wp_get_attachment_url((int) get_post_meta($variation_id, '_aav_pdf_id', true)),
                    'gallery'     => $this->get_gallery_urls($variation_id),
                ];
            },
            'schema' => null,
        ]);
    }

    public function preselect_variation_dropdown($args)
    {
        if (!empty($args['selected'])) {
            return $args;
        }

        if (empty($args['attribute'])) {
            return $args;
        }

        $request_key = 'attribute_' . sanitize_title((string) $args['attribute']);
        if (!isset($_REQUEST[$request_key])) {
            return $args;
        }

        $args['selected'] = sanitize_title(wp_unslash((string) $_REQUEST[$request_key]));

        return $args;
    }

    private function get_button_styles($settings)
    {
        if (($settings['display_mode'] ?? 'select') !== 'button') {
            return '';
        }

        $background = esc_html($settings['button_background']);
        $text_color = esc_html($settings['button_text_color']);
        $border_color = esc_html($settings['button_border_color']);
        $hover_background = esc_html($settings['button_hover_background']);
        $hover_text_color = esc_html($settings['button_hover_text_color']);
        $hover_border_color = esc_html($settings['button_hover_border_color']);
        $active_background = esc_html($settings['button_active_background']);
        $active_text_color = esc_html($settings['button_active_text_color']);
        $active_border_color = esc_html($settings['button_active_border_color']);
        $border_width = (int) $settings['button_border_width'];
        $border_radius = (int) $settings['button_border_radius'];
        $button_width = (int) $settings['button_width'];
        $button_height = (int) $settings['button_height'];
        $padding_y = (int) $settings['button_padding_y'];
        $padding_x = (int) $settings['button_padding_x'];
        $gap = (int) $settings['button_gap'];
        $image_size = (int) $settings['button_image_size'];
        $font_size = (int) $settings['button_font_size'];
        $font_weight = (int) $settings['button_font_weight'];
        $text_transform = esc_html($settings['button_text_transform']);
        $media_label_font_size = (int) $settings['button_media_label_font_size'];
        $media_label_color = esc_html($settings['button_media_label_color']);
        $media_label_hover_color = esc_html($settings['button_media_label_hover_color']);
        $media_label_active_color = esc_html($settings['button_media_label_active_color']);
        $media_label_text_transform = esc_html($settings['button_media_label_text_transform']);
        $media_label_gap = (int) $settings['button_media_label_gap'];
        $media_label_lines = (int) $settings['button_media_label_lines'];
        $media_flex_direction = esc_html($settings['button_media_flex_direction']);
        $media_align_items = esc_html($settings['button_media_align_items']);
        $media_justify_content = esc_html($settings['button_media_justify_content']);
        $media_text_align = esc_html($settings['button_media_text_align']);
        $button_width_css = $button_width > 0 ? $button_width . 'px' : 'auto';
        $button_height_css = $button_height > 0 ? $button_height . 'px' : 'auto';

        return "
            .aav-button-mode {
                --aav-button-bg: {$background};
                --aav-button-color: {$text_color};
                --aav-button-border: {$border_color};
                --aav-button-hover-bg: {$hover_background};
                --aav-button-hover-color: {$hover_text_color};
                --aav-button-hover-border: {$hover_border_color};
                --aav-button-active-bg: {$active_background};
                --aav-button-active-color: {$active_text_color};
                --aav-button-active-border: {$active_border_color};
                --aav-button-border-width: {$border_width}px;
                --aav-button-radius: {$border_radius}px;
                --aav-button-width: {$button_width_css};
                --aav-button-height: {$button_height_css};
                --aav-button-padding-y: {$padding_y}px;
                --aav-button-padding-x: {$padding_x}px;
                --aav-button-gap: {$gap}px;
                --aav-button-image-size: {$image_size}px;
                --aav-button-font-size: {$font_size}px;
                --aav-button-font-weight: {$font_weight};
                --aav-button-text-transform: {$text_transform};
                --aav-button-media-label-font-size: {$media_label_font_size}px;
                --aav-button-media-label-color: {$media_label_color};
                --aav-button-media-label-hover-color: {$media_label_hover_color};
                --aav-button-media-label-active-color: {$media_label_active_color};
                --aav-button-media-label-text-transform: {$media_label_text_transform};
                --aav-button-media-label-gap: {$media_label_gap}px;
                --aav-button-media-label-lines: {$media_label_lines};
                --aav-button-media-flex-direction: {$media_flex_direction};
                --aav-button-media-align-items: {$media_align_items};
                --aav-button-media-justify-content: {$media_justify_content};
                --aav-button-media-text-align: {$media_text_align};
            }
        ";
    }

    private function get_button_presentations($product)
    {
        if (!$product || !is_object($product) || !method_exists($product, 'get_variation_attributes')) {
            return [];
        }

        $presentations = [];
        foreach ((array) $product->get_variation_attributes() as $attribute_name => $options) {
            $taxonomy = $this->resolve_attribute_taxonomy($attribute_name);
            if ($taxonomy === '') {
                continue;
            }

            $attribute_name = (string) $attribute_name;
            $map_keys = array_unique([
                $attribute_name,
                strpos($attribute_name, 'attribute_') === 0 ? $attribute_name : 'attribute_' . $attribute_name,
                'attribute_' . $taxonomy,
                $taxonomy,
            ]);

            foreach ((array) $options as $term_value) {
                $term = $this->resolve_attribute_term($taxonomy, $term_value);
                if (!$term || is_wp_error($term)) {
                    continue;
                }

                $image_id = (int) get_term_meta($term->term_id, '_aav_term_image_id', true);
                $hover_image_id = (int) get_term_meta($term->term_id, '_aav_term_hover_image_id', true);
                $image_size = (int) get_term_meta($term->term_id, '_aav_term_image_size', true);
                $presentation = [
                    'icon' => (string) get_term_meta($term->term_id, '_aav_term_icon', true),
                    'color' => (string) get_term_meta($term->term_id, '_aav_term_color', true),
                    'imageUrl' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
                    'imageSize' => $image_size > 0 ? $image_size : 0,
                    'hoverImageUrl' => $hover_image_id ? wp_get_attachment_image_url($hover_image_id, 'thumbnail') : '',
                ];

                foreach ($map_keys as $map_key) {
                    $presentations[$map_key][(string) $term_value] = $presentation;
                    $presentations[$map_key][(string) $term->slug] = $presentation;
                    $presentations[$map_key][(string) $term->name] = $presentation;
                    $presentations[$map_key][sanitize_title((string) $term_value)] = $presentation;
                    $presentations[$map_key][sanitize_title((string) $term->name)] = $presentation;
                }
            }
        }

        return $presentations;
    }

    private function get_button_presentations_flat($product)
    {
        if (!$product || !is_object($product) || !method_exists($product, 'get_variation_attributes')) {
            return [];
        }

        $presentations = [];
        foreach ((array) $product->get_variation_attributes() as $attribute_name => $options) {
            $taxonomy = $this->resolve_attribute_taxonomy($attribute_name);
            if ($taxonomy === '') {
                continue;
            }

            foreach ((array) $options as $term_value) {
                $term = $this->resolve_attribute_term($taxonomy, $term_value);
                if (!$term || is_wp_error($term)) {
                    continue;
                }

                $image_id = (int) get_term_meta($term->term_id, '_aav_term_image_id', true);
                $hover_image_id = (int) get_term_meta($term->term_id, '_aav_term_hover_image_id', true);
                $image_size = (int) get_term_meta($term->term_id, '_aav_term_image_size', true);
                $presentation = [
                    'icon' => (string) get_term_meta($term->term_id, '_aav_term_icon', true),
                    'color' => (string) get_term_meta($term->term_id, '_aav_term_color', true),
                    'imageUrl' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
                    'imageSize' => $image_size > 0 ? $image_size : 0,
                    'hoverImageUrl' => $hover_image_id ? wp_get_attachment_image_url($hover_image_id, 'thumbnail') : '',
                ];

                $presentations[(string) $term_value] = $presentation;
                $presentations[sanitize_title((string) $term_value)] = $presentation;
                $presentations[(string) $term->slug] = $presentation;
                $presentations[sanitize_title((string) $term->slug)] = $presentation;
                $presentations[(string) $term->name] = $presentation;
                $presentations[sanitize_title((string) $term->name)] = $presentation;
            }
        }

        return $presentations;
    }

    private function get_global_button_presentations_flat()
    {
        if (!function_exists('wc_get_attribute_taxonomies') || !function_exists('wc_attribute_taxonomy_name')) {
            return [];
        }

        $presentations = [];
        foreach ((array) wc_get_attribute_taxonomies() as $attribute_taxonomy) {
            if (empty($attribute_taxonomy->attribute_name)) {
                continue;
            }

            $taxonomy = wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name);
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $image_id = (int) get_term_meta($term->term_id, '_aav_term_image_id', true);
                $hover_image_id = (int) get_term_meta($term->term_id, '_aav_term_hover_image_id', true);

                // Skip terms without any presentation data to keep payload small.
                if (
                    !$image_id &&
                    !$hover_image_id &&
                    get_term_meta($term->term_id, '_aav_term_icon', true) === '' &&
                    get_term_meta($term->term_id, '_aav_term_color', true) === ''
                ) {
                    continue;
                }

                $presentation = [
                    'icon' => (string) get_term_meta($term->term_id, '_aav_term_icon', true),
                    'color' => (string) get_term_meta($term->term_id, '_aav_term_color', true),
                    'imageUrl' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
                    'imageSize' => ($image_size = (int) get_term_meta($term->term_id, '_aav_term_image_size', true)) > 0 ? $image_size : 0,
                    'hoverImageUrl' => $hover_image_id ? wp_get_attachment_image_url($hover_image_id, 'thumbnail') : '',
                ];

                $presentations[(string) $term->slug] = $presentation;
                $presentations[sanitize_title((string) $term->slug)] = $presentation;
                $presentations[(string) $term->name] = $presentation;
                $presentations[sanitize_title((string) $term->name)] = $presentation;
            }
        }

        return $presentations;
    }

    private function resolve_attribute_taxonomy($attribute_name)
    {
        $attribute_name = str_replace('attribute_', '', (string) $attribute_name);
        $candidates = array_unique([
            $attribute_name,
            sanitize_title($attribute_name),
            function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name($attribute_name) : '',
            function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name(sanitize_title($attribute_name)) : '',
        ]);

        foreach ($candidates as $taxonomy) {
            if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
                return $taxonomy;
            }
        }

        return '';
    }

    private function resolve_attribute_term($taxonomy, $term_value)
    {
        $term_value = (string) $term_value;
        if ($term_value === '') {
            return null;
        }

        $candidates = [
            ['slug', $term_value],
            ['slug', sanitize_title($term_value)],
            ['name', $term_value],
        ];

        foreach ($candidates as [$field, $value]) {
            $term = get_term_by($field, $value, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        return null;
    }

    private function get_gallery_urls($variation_id)
    {
        $ids = (string) get_post_meta($variation_id, '_aav_gallery_ids', true);
        if ($ids === '') {
            return [];
        }

        $urls = [];
        foreach (array_filter(array_map('trim', explode(',', $ids))) as $id) {
            $url = wp_get_attachment_image_url((int) $id, 'large');
            if ($url) {
                $urls[] = $url;
            }
        }
        return $urls;
    }
}
