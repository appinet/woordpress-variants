<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAV_Admin
{
    private static $nonce_rendered = false;
    private const SETTINGS_OPTION = 'aav_settings';

    public function __construct()
    {
        add_action('woocommerce_product_after_variable_attributes', [$this, 'render_variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_fields'], 10, 2);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_attribute_term_hooks']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_term_media_scripts']);
    }

    public function render_variation_fields($loop, $variation_data, $variation)
    {
        $ingredients = get_post_meta($variation->ID, '_aav_ingredients', true);
        $flavor_desc = get_post_meta($variation->ID, '_aav_flavor_desc', true);
        $nutrition   = get_post_meta($variation->ID, '_aav_nutrition', true);
        $badge       = get_post_meta($variation->ID, '_aav_badge', true);
        $pdf_id      = (int) get_post_meta($variation->ID, '_aav_pdf_id', true);
        $video_url   = get_post_meta($variation->ID, '_aav_video_url', true);
        $gallery_ids = get_post_meta($variation->ID, '_aav_gallery_ids', true);
        ?>
        <div class="aav-variation-fields" style="padding:12px; margin-top:12px; border:1px solid #ddd;">
            <?php if (!self::$nonce_rendered) : ?>
                <?php wp_nonce_field('aav_save_variation_fields', 'aav_variation_fields_nonce'); ?>
                <?php self::$nonce_rendered = true; ?>
            <?php endif; ?>
            <p class="form-row form-row-full">
                <label for="aav_badge_<?php echo esc_attr($variation->ID); ?>">Badge / Etykieta</label>
                <input type="text" class="short" name="aav_badge[<?php echo esc_attr($variation->ID); ?>]" id="aav_badge_<?php echo esc_attr($variation->ID); ?>" value="<?php echo esc_attr($badge); ?>" />
            </p>

            <p class="form-row form-row-full">
                <label for="aav_flavor_desc_<?php echo esc_attr($variation->ID); ?>">Opis smaku</label>
                <textarea name="aav_flavor_desc[<?php echo esc_attr($variation->ID); ?>]" id="aav_flavor_desc_<?php echo esc_attr($variation->ID); ?>" rows="3" style="width:100%;"><?php echo esc_textarea($flavor_desc); ?></textarea>
            </p>

            <p class="form-row form-row-full">
                <label for="aav_ingredients_<?php echo esc_attr($variation->ID); ?>">Skład produktu</label>
                <textarea name="aav_ingredients[<?php echo esc_attr($variation->ID); ?>]" id="aav_ingredients_<?php echo esc_attr($variation->ID); ?>" rows="4" style="width:100%;"><?php echo esc_textarea($ingredients); ?></textarea>
            </p>

            <p class="form-row form-row-full">
                <label for="aav_nutrition_<?php echo esc_attr($variation->ID); ?>">Wartości odżywcze</label>
                <textarea name="aav_nutrition[<?php echo esc_attr($variation->ID); ?>]" id="aav_nutrition_<?php echo esc_attr($variation->ID); ?>" rows="5" style="width:100%;"><?php echo esc_textarea($nutrition); ?></textarea>
            </p>

            <p class="form-row form-row-full">
                <label for="aav_video_url_<?php echo esc_attr($variation->ID); ?>">URL wideo</label>
                <input type="url" class="fullwidth" name="aav_video_url[<?php echo esc_attr($variation->ID); ?>]" id="aav_video_url_<?php echo esc_attr($variation->ID); ?>" value="<?php echo esc_attr($video_url); ?>" style="width:100%;" />
            </p>

            <p class="form-row form-row-full">
                <label for="aav_pdf_id_<?php echo esc_attr($variation->ID); ?>">PDF wariantu (ID załącznika)</label>
                <input type="number" class="short" name="aav_pdf_id[<?php echo esc_attr($variation->ID); ?>]" id="aav_pdf_id_<?php echo esc_attr($variation->ID); ?>" value="<?php echo esc_attr($pdf_id); ?>" min="0" />
            </p>

            <p class="form-row form-row-full">
                <label for="aav_gallery_ids_<?php echo esc_attr($variation->ID); ?>">Galeria wariantu (ID obrazów oddzielone przecinkami)</label>
                <input type="text" class="fullwidth" name="aav_gallery_ids[<?php echo esc_attr($variation->ID); ?>]" id="aav_gallery_ids_<?php echo esc_attr($variation->ID); ?>" value="<?php echo esc_attr($gallery_ids); ?>" style="width:100%;" />
            </p>
        </div>
        <?php
    }

    public function save_variation_fields($variation_id, $i)
    {
        if (
            !isset($_POST['aav_variation_fields_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aav_variation_fields_nonce'])), 'aav_save_variation_fields')
        ) {
            return;
        }

        if (!current_user_can('edit_post', $variation_id)) {
            return;
        }

        $text_fields = [
            '_aav_ingredients' => 'aav_ingredients',
            '_aav_flavor_desc' => 'aav_flavor_desc',
            '_aav_nutrition'   => 'aav_nutrition',
            '_aav_badge'       => 'aav_badge',
            '_aav_gallery_ids' => 'aav_gallery_ids',
        ];

        foreach ($text_fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key][$variation_id])) {
                $value = wp_kses_post(wp_unslash($_POST[$post_key][$variation_id]));
                update_post_meta($variation_id, $meta_key, $value);
            }
        }

        if (isset($_POST['aav_video_url'][$variation_id])) {
            update_post_meta($variation_id, '_aav_video_url', esc_url_raw(wp_unslash($_POST['aav_video_url'][$variation_id])));
        }

        if (isset($_POST['aav_pdf_id'][$variation_id])) {
            update_post_meta($variation_id, '_aav_pdf_id', absint($_POST['aav_pdf_id'][$variation_id]));
        }
    }

    public static function get_settings()
    {
        $defaults = [
            'display_mode' => 'select',
            'button_icon' => '',
            'button_background' => '#111111',
            'button_text_color' => '#ffffff',
            'button_border_color' => '#111111',
            'button_hover_background' => '#222222',
            'button_hover_text_color' => '#ffffff',
            'button_hover_border_color' => '#222222',
            'button_active_background' => '#111111',
            'button_active_text_color' => '#ffffff',
            'button_active_border_color' => '#111111',
            'button_border_width' => 1,
            'button_border_radius' => 999,
            'button_width' => 0,
            'button_height' => 0,
            'button_padding_y' => 10,
            'button_padding_x' => 14,
            'button_gap' => 8,
            'button_image_size' => 32,
            'button_font_size' => 14,
            'button_font_weight' => 600,
            'button_text_transform' => 'none',
            'button_media_label_font_size' => 12,
            'button_media_label_color' => '#ffffff',
            'button_media_label_hover_color' => '#ffffff',
            'button_media_label_active_color' => '#ffffff',
            'button_media_label_text_transform' => '',
            'button_media_label_gap' => 8,
            'button_media_label_lines' => 2,
            'button_media_flex_direction' => 'column',
            'button_media_align_items' => 'center',
            'button_media_justify_content' => 'center',
            'button_media_text_align' => 'center',
            'button_animation' => 'lift',
        ];

        $settings = get_option(self::SETTINGS_OPTION, []);

        return wp_parse_args(is_array($settings) ? $settings : [], $defaults);
    }

    public function register_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            'Advanced Variations',
            'Advanced Variations',
            'manage_woocommerce',
            'aav-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'aav_settings_group',
            self::SETTINGS_OPTION,
            [$this, 'sanitize_settings']
        );
    }

    public function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : [];

        return [
            'display_mode' => in_array(($input['display_mode'] ?? 'select'), ['select', 'button'], true) ? $input['display_mode'] : 'select',
            'button_icon' => sanitize_text_field($input['button_icon'] ?? ''),
            'button_background' => sanitize_hex_color($input['button_background'] ?? '') ?: '#111111',
            'button_text_color' => sanitize_hex_color($input['button_text_color'] ?? '') ?: '#ffffff',
            'button_border_color' => sanitize_hex_color($input['button_border_color'] ?? '') ?: '#111111',
            'button_hover_background' => sanitize_hex_color($input['button_hover_background'] ?? '') ?: '#222222',
            'button_hover_text_color' => sanitize_hex_color($input['button_hover_text_color'] ?? '') ?: '#ffffff',
            'button_hover_border_color' => sanitize_hex_color($input['button_hover_border_color'] ?? '') ?: '#222222',
            'button_active_background' => sanitize_hex_color($input['button_active_background'] ?? '') ?: '#111111',
            'button_active_text_color' => sanitize_hex_color($input['button_active_text_color'] ?? '') ?: '#ffffff',
            'button_active_border_color' => sanitize_hex_color($input['button_active_border_color'] ?? '') ?: '#111111',
            'button_border_width' => max(0, min(8, absint($input['button_border_width'] ?? 1))),
            'button_border_radius' => max(0, min(999, absint($input['button_border_radius'] ?? 999))),
            'button_width' => max(0, min(600, absint($input['button_width'] ?? 0))),
            'button_height' => max(0, min(300, absint($input['button_height'] ?? 0))),
            'button_padding_y' => max(0, min(60, absint($input['button_padding_y'] ?? 10))),
            'button_padding_x' => max(0, min(80, absint($input['button_padding_x'] ?? 14))),
            'button_gap' => max(0, min(40, absint($input['button_gap'] ?? 8))),
            'button_image_size' => max(16, min(200, absint($input['button_image_size'] ?? 32))),
            'button_font_size' => max(10, min(32, absint($input['button_font_size'] ?? 14))),
            'button_font_weight' => max(300, min(900, absint($input['button_font_weight'] ?? 600))),
            'button_text_transform' => in_array(($input['button_text_transform'] ?? 'none'), ['none', 'uppercase', 'lowercase', 'capitalize'], true) ? $input['button_text_transform'] : 'none',
            'button_media_label_font_size' => max(8, min(32, absint($input['button_media_label_font_size'] ?? 12))),
            'button_media_label_color' => sanitize_hex_color($input['button_media_label_color'] ?? '') ?: '#ffffff',
            'button_media_label_hover_color' => sanitize_hex_color($input['button_media_label_hover_color'] ?? '') ?: '#ffffff',
            'button_media_label_active_color' => sanitize_hex_color($input['button_media_label_active_color'] ?? '') ?: '#ffffff',
            'button_media_label_text_transform' => in_array(($input['button_media_label_text_transform'] ?? ''), ['', 'none', 'uppercase', 'lowercase', 'capitalize'], true) ? $input['button_media_label_text_transform'] : '',
            'button_media_label_gap' => max(0, min(40, absint($input['button_media_label_gap'] ?? 8))),
            'button_media_label_lines' => max(1, min(4, absint($input['button_media_label_lines'] ?? 2))),
            'button_media_flex_direction' => in_array(($input['button_media_flex_direction'] ?? 'column'), ['row', 'row-reverse', 'column', 'column-reverse'], true) ? $input['button_media_flex_direction'] : 'column',
            'button_media_align_items' => in_array(($input['button_media_align_items'] ?? 'center'), ['flex-start', 'center', 'flex-end', 'stretch'], true) ? $input['button_media_align_items'] : 'center',
            'button_media_justify_content' => in_array(($input['button_media_justify_content'] ?? 'center'), ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'], true) ? $input['button_media_justify_content'] : 'center',
            'button_media_text_align' => in_array(($input['button_media_text_align'] ?? 'center'), ['left', 'center', 'right'], true) ? $input['button_media_text_align'] : 'center',
            'button_animation' => in_array(($input['button_animation'] ?? 'lift'), ['none', 'lift', 'pulse', 'scale'], true) ? $input['button_animation'] : 'lift',
        ];
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = self::get_settings();
        $preview_samples = $this->get_settings_preview_samples();
        ?>
        <div class="wrap">
            <h1>Advanced Variations</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aav_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="aav_display_mode">Tryb wyboru wariantu</label></th>
                        <td>
                            <select id="aav_display_mode" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[display_mode]">
                                <option value="select" <?php selected($settings['display_mode'], 'select'); ?>>Select</option>
                                <option value="button" <?php selected($settings['display_mode'], 'button'); ?>>Button</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_icon">Ikona przycisku</label></th>
                        <td>
                            <input id="aav_button_icon" type="text" class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_icon]" value="<?php echo esc_attr($settings['button_icon']); ?>" />
                            <p class="description">Np. `★`, `✓`, `•` albo tekst zostaw pusty.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_background">Kolor tła</label></th>
                        <td><input id="aav_button_background" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_background]" value="<?php echo esc_attr($settings['button_background']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_text_color">Kolor czcionki</label></th>
                        <td><input id="aav_button_text_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_text_color]" value="<?php echo esc_attr($settings['button_text_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_border_color">Kolor border</label></th>
                        <td><input id="aav_button_border_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_border_color]" value="<?php echo esc_attr($settings['button_border_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_hover_background">Hover: kolor tła</label></th>
                        <td><input id="aav_button_hover_background" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_hover_background]" value="<?php echo esc_attr($settings['button_hover_background']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_hover_text_color">Hover: kolor czcionki</label></th>
                        <td><input id="aav_button_hover_text_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_hover_text_color]" value="<?php echo esc_attr($settings['button_hover_text_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_hover_border_color">Hover: kolor border</label></th>
                        <td><input id="aav_button_hover_border_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_hover_border_color]" value="<?php echo esc_attr($settings['button_hover_border_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_active_background">Active: kolor tła</label></th>
                        <td><input id="aav_button_active_background" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_active_background]" value="<?php echo esc_attr($settings['button_active_background']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_active_text_color">Active: kolor czcionki</label></th>
                        <td><input id="aav_button_active_text_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_active_text_color]" value="<?php echo esc_attr($settings['button_active_text_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_active_border_color">Active: kolor border</label></th>
                        <td><input id="aav_button_active_border_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_active_border_color]" value="<?php echo esc_attr($settings['button_active_border_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_border_width">Grubość border</label></th>
                        <td><input id="aav_button_border_width" type="number" min="0" max="8" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_border_width]" value="<?php echo esc_attr($settings['button_border_width']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_border_radius">Zaokrąglenie</label></th>
                        <td><input id="aav_button_border_radius" type="number" min="0" max="999" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_border_radius]" value="<?php echo esc_attr($settings['button_border_radius']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_width">Szerokość buttona</label></th>
                        <td>
                            <input id="aav_button_width" type="number" min="0" max="600" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_width]" value="<?php echo esc_attr($settings['button_width']); ?>" />
                            <p class="description">`0` = szerokość automatyczna.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_height">Wysokość buttona</label></th>
                        <td>
                            <input id="aav_button_height" type="number" min="0" max="300" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_height]" value="<?php echo esc_attr($settings['button_height']); ?>" />
                            <p class="description">`0` = wysokość automatyczna.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_padding_y">Padding pionowy</label></th>
                        <td><input id="aav_button_padding_y" type="number" min="0" max="60" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_padding_y]" value="<?php echo esc_attr($settings['button_padding_y']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_padding_x">Padding poziomy</label></th>
                        <td><input id="aav_button_padding_x" type="number" min="0" max="80" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_padding_x]" value="<?php echo esc_attr($settings['button_padding_x']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_gap">Odstęp wewnętrzny</label></th>
                        <td><input id="aav_button_gap" type="number" min="0" max="40" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_gap]" value="<?php echo esc_attr($settings['button_gap']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_image_size">Rozmiar obrazka</label></th>
                        <td><input id="aav_button_image_size" type="number" min="16" max="200" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_image_size]" value="<?php echo esc_attr($settings['button_image_size']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_font_size">Rozmiar tekstu pod zdjęciem</label></th>
                        <td><input id="aav_button_media_label_font_size" type="number" min="8" max="32" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_font_size]" value="<?php echo esc_attr($settings['button_media_label_font_size']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_color">Kolor tekstu pod zdjęciem</label></th>
                        <td><input id="aav_button_media_label_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_color]" value="<?php echo esc_attr($settings['button_media_label_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_hover_color">Hover: kolor tekstu pod zdjęciem</label></th>
                        <td><input id="aav_button_media_label_hover_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_hover_color]" value="<?php echo esc_attr($settings['button_media_label_hover_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_active_color">Active: kolor tekstu pod zdjęciem</label></th>
                        <td><input id="aav_button_media_label_active_color" type="color" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_active_color]" value="<?php echo esc_attr($settings['button_media_label_active_color']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_gap">Odstęp tekstu od zdjęcia</label></th>
                        <td><input id="aav_button_media_label_gap" type="number" min="0" max="40" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_gap]" value="<?php echo esc_attr($settings['button_media_label_gap']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_lines">Liczba linii tekstu</label></th>
                        <td><input id="aav_button_media_label_lines" type="number" min="1" max="4" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_lines]" value="<?php echo esc_attr($settings['button_media_label_lines']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_flex_direction">Flex direction zdjęcie + tekst</label></th>
                        <td>
                            <select id="aav_button_media_flex_direction" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_flex_direction]">
                                <option value="column" <?php selected($settings['button_media_flex_direction'], 'column'); ?>>column</option>
                                <option value="column-reverse" <?php selected($settings['button_media_flex_direction'], 'column-reverse'); ?>>column-reverse</option>
                                <option value="row" <?php selected($settings['button_media_flex_direction'], 'row'); ?>>row</option>
                                <option value="row-reverse" <?php selected($settings['button_media_flex_direction'], 'row-reverse'); ?>>row-reverse</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_align_items">Align items</label></th>
                        <td>
                            <select id="aav_button_media_align_items" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_align_items]">
                                <option value="center" <?php selected($settings['button_media_align_items'], 'center'); ?>>center</option>
                                <option value="flex-start" <?php selected($settings['button_media_align_items'], 'flex-start'); ?>>flex-start</option>
                                <option value="flex-end" <?php selected($settings['button_media_align_items'], 'flex-end'); ?>>flex-end</option>
                                <option value="stretch" <?php selected($settings['button_media_align_items'], 'stretch'); ?>>stretch</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_justify_content">Justify content</label></th>
                        <td>
                            <select id="aav_button_media_justify_content" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_justify_content]">
                                <option value="center" <?php selected($settings['button_media_justify_content'], 'center'); ?>>center</option>
                                <option value="flex-start" <?php selected($settings['button_media_justify_content'], 'flex-start'); ?>>flex-start</option>
                                <option value="flex-end" <?php selected($settings['button_media_justify_content'], 'flex-end'); ?>>flex-end</option>
                                <option value="space-between" <?php selected($settings['button_media_justify_content'], 'space-between'); ?>>space-between</option>
                                <option value="space-around" <?php selected($settings['button_media_justify_content'], 'space-around'); ?>>space-around</option>
                                <option value="space-evenly" <?php selected($settings['button_media_justify_content'], 'space-evenly'); ?>>space-evenly</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_text_align">Text align</label></th>
                        <td>
                            <select id="aav_button_media_text_align" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_text_align]">
                                <option value="center" <?php selected($settings['button_media_text_align'], 'center'); ?>>center</option>
                                <option value="left" <?php selected($settings['button_media_text_align'], 'left'); ?>>left</option>
                                <option value="right" <?php selected($settings['button_media_text_align'], 'right'); ?>>right</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_font_size">Rozmiar czcionki</label></th>
                        <td><input id="aav_button_font_size" type="number" min="10" max="32" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_font_size]" value="<?php echo esc_attr($settings['button_font_size']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_text_transform">Transformacja tekstu buttona</label></th>
                        <td>
                            <select id="aav_button_text_transform" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_text_transform]">
                                <option value="none" <?php selected($settings['button_text_transform'], 'none'); ?>>Brak</option>
                                <option value="uppercase" <?php selected($settings['button_text_transform'], 'uppercase'); ?>>UPPERCASE</option>
                                <option value="lowercase" <?php selected($settings['button_text_transform'], 'lowercase'); ?>>lowercase</option>
                                <option value="capitalize" <?php selected($settings['button_text_transform'], 'capitalize'); ?>>Capitalize</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_font_weight">Grubość czcionki</label></th>
                        <td><input id="aav_button_font_weight" type="number" min="300" max="900" step="100" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_font_weight]" value="<?php echo esc_attr($settings['button_font_weight']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_media_label_text_transform">Transformacja tekstu pod zdjęciem</label></th>
                        <td>
                            <select id="aav_button_media_label_text_transform" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_media_label_text_transform]">
                                <option value="" <?php selected($settings['button_media_label_text_transform'], ''); ?>>Dziedzicz z buttona</option>
                                <option value="none" <?php selected($settings['button_media_label_text_transform'], 'none'); ?>>Brak</option>
                                <option value="uppercase" <?php selected($settings['button_media_label_text_transform'], 'uppercase'); ?>>UPPERCASE</option>
                                <option value="lowercase" <?php selected($settings['button_media_label_text_transform'], 'lowercase'); ?>>lowercase</option>
                                <option value="capitalize" <?php selected($settings['button_media_label_text_transform'], 'capitalize'); ?>>Capitalize</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aav_button_animation">Animacja</label></th>
                        <td>
                            <select id="aav_button_animation" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[button_animation]">
                                <option value="none" <?php selected($settings['button_animation'], 'none'); ?>>Brak</option>
                                <option value="lift" <?php selected($settings['button_animation'], 'lift'); ?>>Lift</option>
                                <option value="pulse" <?php selected($settings['button_animation'], 'pulse'); ?>>Pulse</option>
                                <option value="scale" <?php selected($settings['button_animation'], 'scale'); ?>>Scale</option>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <h2>Podgląd buttonów</h2>
                <div class="aav-settings-preview">
                    <div class="aav-settings-preview-buttons" data-animation="<?php echo esc_attr($settings['button_animation']); ?>">
                        <button type="button" class="aav-settings-preview-button aav-settings-preview-button-media is-selected">
                            <?php if ($preview_samples['image_url']) : ?>
                                <span class="aav-settings-preview-image"><img src="<?php echo esc_url($preview_samples['image_url']); ?>" alt="" /></span>
                            <?php else : ?>
                                <span class="aav-settings-preview-icon"><?php echo esc_html($settings['button_icon'] ?: '★'); ?></span>
                            <?php endif; ?>
                            <span class="aav-settings-preview-label"><?php echo esc_html($preview_samples['image_label']); ?></span>
                        </button>
                        <button type="button" class="aav-settings-preview-button">
                            <span class="aav-settings-preview-swatch" <?php echo $preview_samples['color'] ? 'style="background:' . esc_attr($preview_samples['color']) . ';"' : ''; ?>></span>
                            <span class="aav-settings-preview-label"><?php echo esc_html($preview_samples['color_label']); ?></span>
                        </button>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function register_attribute_term_hooks()
    {
        if (!function_exists('wc_get_attribute_taxonomies') || !function_exists('wc_attribute_taxonomy_name')) {
            return;
        }

        foreach ((array) wc_get_attribute_taxonomies() as $attribute_taxonomy) {
            if (empty($attribute_taxonomy->attribute_name)) {
                continue;
            }

            $taxonomy = wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name);
            add_action("{$taxonomy}_add_form_fields", [$this, 'render_attribute_term_add_fields']);
            add_action("{$taxonomy}_edit_form_fields", [$this, 'render_attribute_term_edit_fields'], 10, 2);
            add_action("created_{$taxonomy}", [$this, 'save_attribute_term_meta'], 10, 2);
            add_action("edited_{$taxonomy}", [$this, 'save_attribute_term_meta'], 10, 2);
        }
    }

    public function enqueue_term_media_scripts($hook_suffix)
    {
        if ($hook_suffix === 'woocommerce_page_aav-settings') {
            wp_add_inline_style('wp-admin', $this->get_settings_preview_styles());
            wp_add_inline_script('jquery-core', $this->get_settings_preview_script());
            return;
        }

        if (!in_array($hook_suffix, ['edit-tags.php', 'term.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || empty($screen->taxonomy) || strpos((string) $screen->taxonomy, 'pa_') !== 0) {
            return;
        }

        wp_enqueue_media();
        wp_add_inline_script('jquery-core', $this->get_term_media_script());
    }

    public function render_attribute_term_add_fields($taxonomy)
    {
        wp_nonce_field('aav_save_term_meta', 'aav_term_meta_nonce');
        ?>
        <div class="form-field">
            <label for="aav_term_icon">Ikona opcji</label>
            <input type="text" name="aav_term_icon" id="aav_term_icon" value="" />
            <p class="description">Np. `★`, `✓`, `🍓`.</p>
        </div>
        <div class="form-field">
            <label for="aav_term_color">Kolor swatch / tła</label>
            <input type="color" name="aav_term_color" id="aav_term_color" value="#111111" />
            <p class="description">Kolor dla danej wartości wariantu.</p>
        </div>
        <div class="form-field">
            <label for="aav_term_image_id">Miniatura opcji</label>
            <input type="hidden" name="aav_term_image_id" id="aav_term_image_id" value="" />
            <div class="aav-term-image-preview" style="margin:8px 0;"></div>
            <button type="button" class="button aav-term-image-upload">Wybierz obraz</button>
            <button type="button" class="button aav-term-image-remove">Usuń obraz</button>
            <p class="description">Miniatura ma priorytet nad ikoną tekstową.</p>
        </div>
        <div class="form-field">
            <label for="aav_term_image_size">Rozmiar miniatury w buttonie</label>
            <input type="number" name="aav_term_image_size" id="aav_term_image_size" value="" min="16" max="240" />
            <p class="description">Pozostaw puste, aby użyć globalnego rozmiaru obrazka.</p>
        </div>
        <div class="form-field">
            <label for="aav_term_hover_image_id">Hover image</label>
            <input type="hidden" name="aav_term_hover_image_id" id="aav_term_hover_image_id" value="" />
            <div class="aav-term-hover-image-preview" style="margin:8px 0;"></div>
            <button type="button" class="button aav-term-hover-image-upload">Wybierz hover image</button>
            <button type="button" class="button aav-term-hover-image-remove">Usuń hover image</button>
            <p class="description">Obraz pokazany na hover przycisku.</p>
        </div>
        <?php
    }

    public function render_attribute_term_edit_fields($term, $taxonomy)
    {
        $icon = get_term_meta($term->term_id, '_aav_term_icon', true);
        $color = get_term_meta($term->term_id, '_aav_term_color', true);
        $image_id = (int) get_term_meta($term->term_id, '_aav_term_image_id', true);
        $image_size = (int) get_term_meta($term->term_id, '_aav_term_image_size', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $hover_image_id = (int) get_term_meta($term->term_id, '_aav_term_hover_image_id', true);
        $hover_image_url = $hover_image_id ? wp_get_attachment_image_url($hover_image_id, 'thumbnail') : '';
        wp_nonce_field('aav_save_term_meta', 'aav_term_meta_nonce');
        ?>
        <tr class="form-field">
            <th scope="row"><label for="aav_term_icon">Ikona opcji</label></th>
            <td>
                <input type="text" name="aav_term_icon" id="aav_term_icon" value="<?php echo esc_attr($icon); ?>" />
                <p class="description">Np. `★`, `✓`, `🍓`.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="aav_term_color">Kolor swatch / tła</label></th>
            <td>
                <input type="color" name="aav_term_color" id="aav_term_color" value="<?php echo esc_attr($color ?: '#111111'); ?>" />
                <p class="description">Kolor dla danej wartości wariantu.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="aav_term_image_id">Miniatura opcji</label></th>
            <td>
                <input type="hidden" name="aav_term_image_id" id="aav_term_image_id" value="<?php echo esc_attr($image_id); ?>" />
                <div class="aav-term-image-preview" style="margin:8px 0;">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width:60px; height:auto;" />
                    <?php endif; ?>
                </div>
                <button type="button" class="button aav-term-image-upload">Wybierz obraz</button>
                <button type="button" class="button aav-term-image-remove">Usuń obraz</button>
                <p class="description">Miniatura ma priorytet nad ikoną tekstową.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="aav_term_image_size">Rozmiar miniatury w buttonie</label></th>
            <td>
                <input type="number" name="aav_term_image_size" id="aav_term_image_size" value="<?php echo esc_attr($image_size); ?>" min="16" max="240" />
                <p class="description">Pozostaw puste lub `0`, aby użyć globalnego rozmiaru obrazka.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="aav_term_hover_image_id">Hover image</label></th>
            <td>
                <input type="hidden" name="aav_term_hover_image_id" id="aav_term_hover_image_id" value="<?php echo esc_attr($hover_image_id); ?>" />
                <div class="aav-term-hover-image-preview" style="margin:8px 0;">
                    <?php if ($hover_image_url) : ?>
                        <img src="<?php echo esc_url($hover_image_url); ?>" alt="" style="max-width:60px; height:auto;" />
                    <?php endif; ?>
                </div>
                <button type="button" class="button aav-term-hover-image-upload">Wybierz hover image</button>
                <button type="button" class="button aav-term-hover-image-remove">Usuń hover image</button>
                <p class="description">Obraz pokazany na hover przycisku.</p>
            </td>
        </tr>
        <?php
    }

    public function save_attribute_term_meta($term_id, $tt_id = 0)
    {
        if (
            !isset($_POST['aav_term_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aav_term_meta_nonce'])), 'aav_save_term_meta')
        ) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        update_term_meta($term_id, '_aav_term_icon', sanitize_text_field($_POST['aav_term_icon'] ?? ''));
        update_term_meta($term_id, '_aav_term_color', sanitize_hex_color($_POST['aav_term_color'] ?? '') ?: '');
        update_term_meta($term_id, '_aav_term_image_id', absint($_POST['aav_term_image_id'] ?? 0));
        update_term_meta($term_id, '_aav_term_image_size', max(0, min(240, absint($_POST['aav_term_image_size'] ?? 0))));
        update_term_meta($term_id, '_aav_term_hover_image_id', absint($_POST['aav_term_hover_image_id'] ?? 0));
    }

    private function get_term_media_script()
    {
        return <<<'JS'
jQuery(function ($) {
    function renderPreview(container, url) {
        if (!container.length) {
            return;
        }

        container.html(url ? '<img src="' + url + '" alt="" style="max-width:60px;height:auto;" />' : '');
    }

    function bindMediaPicker(triggerSelector, fieldSelector, previewSelector, frameTitle) {
        $(document).on('click', triggerSelector, function (event) {
            event.preventDefault();

            const $button = $(this);
            const $wrapper = $button.closest('td, .form-field');
            const $field = $wrapper.find(fieldSelector);
            const $preview = $wrapper.find(previewSelector);

            const frame = wp.media({
                title: frameTitle,
                button: { text: 'Użyj obrazu' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                $field.val(attachment.id);
                renderPreview($preview, attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
            });

            frame.open();
        });
    }

    bindMediaPicker('.aav-term-image-upload', '#aav_term_image_id', '.aav-term-image-preview', 'Wybierz miniaturę opcji');
    bindMediaPicker('.aav-term-hover-image-upload', '#aav_term_hover_image_id', '.aav-term-hover-image-preview', 'Wybierz hover image');

    $(document).on('click', '.aav-term-image-remove', function (event) {
        event.preventDefault();

        const $wrapper = $(this).closest('td, .form-field');
        $wrapper.find('#aav_term_image_id').val('');
        renderPreview($wrapper.find('.aav-term-image-preview'), '');
    });

    $(document).on('click', '.aav-term-hover-image-remove', function (event) {
        event.preventDefault();

        const $wrapper = $(this).closest('td, .form-field');
        $wrapper.find('#aav_term_hover_image_id').val('');
        renderPreview($wrapper.find('.aav-term-hover-image-preview'), '');
    });
});
JS;
    }

    private function get_settings_preview_styles()
    {
        return <<<'CSS'
.aav-settings-preview {
    margin: 18px 0 10px;
    padding: 18px;
    border: 1px solid #dcdcde;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f6f7f7 100%);
}

.aav-settings-preview-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.aav-settings-preview-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid #111111;
    background: #111111;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.1;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.aav-settings-preview-button-media {
    flex-direction: column;
    justify-content: center;
    text-align: center;
    gap: 8px;
}

.aav-settings-preview-button.is-selected {
    box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.12);
}

.aav-settings-preview-image,
.aav-settings-preview-icon {
    display: inline-flex;
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    align-items: center;
    justify-content: center;
}

.aav-settings-preview-image {
    overflow: hidden;
    border-radius: 10px;
}

.aav-settings-preview-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.aav-settings-preview-swatch {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: #d83333;
    border: 1px solid rgba(255, 255, 255, 0.45);
}

.aav-settings-preview-buttons[data-animation="lift"] .aav-settings-preview-button:hover {
    transform: translateY(-2px);
}

.aav-settings-preview-buttons[data-animation="scale"] .aav-settings-preview-button:hover {
    transform: scale(1.04);
}

.aav-settings-preview-buttons[data-animation="pulse"] .aav-settings-preview-button.is-selected {
    animation: aav-settings-pulse 1.8s infinite;
}

.aav-settings-preview-buttons[data-animation="none"] .aav-settings-preview-button:hover,
.aav-settings-preview-buttons[data-animation="none"] .aav-settings-preview-button.is-selected {
    transform: none;
    animation: none;
}

@keyframes aav-settings-pulse {
    0% { box-shadow: 0 0 0 0 rgba(17, 17, 17, 0.2); }
    70% { box-shadow: 0 0 0 12px rgba(17, 17, 17, 0); }
    100% { box-shadow: 0 0 0 0 rgba(17, 17, 17, 0); }
}
CSS;
    }

    private function get_settings_preview_script()
    {
        return <<<'JS'
jQuery(function ($) {
    const $previewWrap = $('.aav-settings-preview-buttons');
    if (!$previewWrap.length) {
        return;
    }

    function syncPreview() {
        const background = $('#aav_button_background').val() || '#111111';
        const textColor = $('#aav_button_text_color').val() || '#ffffff';
        const borderColor = $('#aav_button_border_color').val() || '#111111';
        const hoverBackground = $('#aav_button_hover_background').val() || '#222222';
        const hoverTextColor = $('#aav_button_hover_text_color').val() || '#ffffff';
        const hoverBorderColor = $('#aav_button_hover_border_color').val() || '#222222';
        const activeBackground = $('#aav_button_active_background').val() || '#111111';
        const activeTextColor = $('#aav_button_active_text_color').val() || '#ffffff';
        const activeBorderColor = $('#aav_button_active_border_color').val() || '#111111';
        const borderWidth = ($('#aav_button_border_width').val() || '1') + 'px';
        const borderRadius = ($('#aav_button_border_radius').val() || '999') + 'px';
        const buttonWidth = $('#aav_button_width').val() || '';
        const buttonHeight = $('#aav_button_height').val() || '';
        const paddingY = ($('#aav_button_padding_y').val() || '10') + 'px';
        const paddingX = ($('#aav_button_padding_x').val() || '14') + 'px';
        const gap = ($('#aav_button_gap').val() || '8') + 'px';
        const imageSize = ($('#aav_button_image_size').val() || '32') + 'px';
        const fontSize = ($('#aav_button_font_size').val() || '14') + 'px';
        const fontWeight = $('#aav_button_font_weight').val() || '600';
        const textTransform = $('#aav_button_text_transform').val() || 'none';
        const mediaLabelFontSize = ($('#aav_button_media_label_font_size').val() || '12') + 'px';
        const mediaLabelColor = $('#aav_button_media_label_color').val() || '#ffffff';
        const mediaLabelHoverColor = $('#aav_button_media_label_hover_color').val() || '#ffffff';
        const mediaLabelActiveColor = $('#aav_button_media_label_active_color').val() || '#ffffff';
        const mediaLabelTextTransform = $('#aav_button_media_label_text_transform').val() || textTransform || 'none';
        const mediaLabelGap = ($('#aav_button_media_label_gap').val() || '8') + 'px';
        const flexDirection = $('#aav_button_media_flex_direction').val() || 'column';
        const alignItems = $('#aav_button_media_align_items').val() || 'center';
        const justifyContent = $('#aav_button_media_justify_content').val() || 'center';
        const textAlign = $('#aav_button_media_text_align').val() || 'center';
        const animation = $('#aav_button_animation').val() || 'lift';
        const icon = $('#aav_button_icon').val() || '★';

        $previewWrap.attr('data-animation', animation);
        $previewWrap.find('.aav-settings-preview-button').css({
            backgroundColor: background,
            color: textColor,
            borderColor: borderColor,
            borderWidth: borderWidth,
            borderRadius: borderRadius,
            width: buttonWidth ? buttonWidth + 'px' : 'auto',
            minWidth: buttonWidth ? buttonWidth + 'px' : 'auto',
            height: buttonHeight ? buttonHeight + 'px' : 'auto',
            fontSize: fontSize,
            fontWeight: fontWeight,
            padding: paddingY + ' ' + paddingX,
            gap: gap
        });
        $previewWrap.find('.aav-settings-preview-button').each(function () {
            const $button = $(this);
            const isSelected = $button.hasClass('is-selected');
            $button.css({
                backgroundColor: isSelected ? activeBackground : background,
                color: isSelected ? activeTextColor : textColor,
                borderColor: isSelected ? activeBorderColor : borderColor
            });
            $button.find('.aav-settings-preview-label').css('color', isSelected ? mediaLabelActiveColor : mediaLabelColor);
            $button.data('defaultBackground', background);
            $button.data('defaultTextColor', textColor);
            $button.data('defaultBorderColor', borderColor);
            $button.data('hoverBackground', hoverBackground);
            $button.data('hoverTextColor', hoverTextColor);
            $button.data('hoverBorderColor', hoverBorderColor);
            $button.data('activeBackground', activeBackground);
            $button.data('activeTextColor', activeTextColor);
            $button.data('activeBorderColor', activeBorderColor);
            $button.data('defaultLabelColor', mediaLabelColor);
            $button.data('hoverLabelColor', mediaLabelHoverColor);
            $button.data('activeLabelColor', mediaLabelActiveColor);
        });
        $previewWrap.find('.aav-settings-preview-icon, .aav-settings-preview-image, .aav-settings-preview-swatch').css({
            width: imageSize,
            height: imageSize,
            flexBasis: imageSize
        });
        $previewWrap.find('.aav-settings-preview-label').css({
            fontSize: mediaLabelFontSize,
            textAlign: textAlign,
            textTransform: mediaLabelTextTransform
        });
        $previewWrap.find('.aav-settings-preview-button:not(.aav-settings-preview-button-media) .aav-settings-preview-label').css('textTransform', textTransform);
        $previewWrap.find('.aav-settings-preview-button-media').css({
            flexDirection: flexDirection,
            alignItems: alignItems,
            justifyContent: justifyContent,
            textAlign: textAlign,
            gap: mediaLabelGap
        });
        $previewWrap.find('.aav-settings-preview-icon').text(icon);
        $previewWrap.find('.aav-settings-preview-swatch').css('backgroundColor', background);
    }

    $previewWrap.on('mouseenter focusin', '.aav-settings-preview-button:not(.is-selected)', function () {
        const $button = $(this);
        $button.css({
            backgroundColor: $button.data('hoverBackground') || '#222222',
            color: $button.data('hoverTextColor') || '#ffffff',
            borderColor: $button.data('hoverBorderColor') || '#222222'
        });
        $button.find('.aav-settings-preview-label').css('color', $button.data('hoverLabelColor') || '#ffffff');
    });

    $previewWrap.on('mouseleave focusout', '.aav-settings-preview-button:not(.is-selected)', function () {
        const $button = $(this);
        $button.css({
            backgroundColor: $button.data('defaultBackground') || '#111111',
            color: $button.data('defaultTextColor') || '#ffffff',
            borderColor: $button.data('defaultBorderColor') || '#111111'
        });
        $button.find('.aav-settings-preview-label').css('color', $button.data('defaultLabelColor') || '#ffffff');
    });

    $('#aav_display_mode, #aav_button_icon, #aav_button_background, #aav_button_text_color, #aav_button_border_color, #aav_button_hover_background, #aav_button_hover_text_color, #aav_button_hover_border_color, #aav_button_active_background, #aav_button_active_text_color, #aav_button_active_border_color, #aav_button_border_width, #aav_button_border_radius, #aav_button_width, #aav_button_height, #aav_button_padding_y, #aav_button_padding_x, #aav_button_gap, #aav_button_image_size, #aav_button_font_size, #aav_button_font_weight, #aav_button_text_transform, #aav_button_media_label_font_size, #aav_button_media_label_color, #aav_button_media_label_hover_color, #aav_button_media_label_active_color, #aav_button_media_label_text_transform, #aav_button_media_label_gap, #aav_button_media_label_lines, #aav_button_media_flex_direction, #aav_button_media_align_items, #aav_button_media_justify_content, #aav_button_media_text_align, #aav_button_animation').on('input change', syncPreview);
    syncPreview();
});
JS;
    }

    private function get_settings_preview_samples()
    {
        $samples = [
            'image_url' => '',
            'image_label' => 'Opcja ze zdjęciem',
            'color' => '#d83333',
            'color_label' => 'Opcja z kolorem',
        ];

        if (!function_exists('wc_get_attribute_taxonomies') || !function_exists('wc_attribute_taxonomy_name')) {
            return $samples;
        }

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
                'number' => 20,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $image_id = (int) get_term_meta($term->term_id, '_aav_term_image_id', true);
                $color = (string) get_term_meta($term->term_id, '_aav_term_color', true);

                if ($samples['image_url'] === '' && $image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    if ($image_url) {
                        $samples['image_url'] = $image_url;
                        $samples['image_label'] = $term->name;
                    }
                }

                if ($samples['color'] === '#d83333' && $color !== '') {
                    $samples['color'] = $color;
                    $samples['color_label'] = $term->name;
                }

                if ($samples['image_url'] !== '' && $samples['color'] !== '#d83333') {
                    return $samples;
                }
            }
        }

        return $samples;
    }
}
