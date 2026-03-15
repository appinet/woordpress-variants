<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAV_Variation_URLs
{
    public static function resolve_variation_id($product, $path)
    {
        if (!$product || !is_object($product) || !method_exists($product, 'get_children')) {
            return 0;
        }

        $path = trim((string) $path, '/');
        if ($path === '') {
            return 0;
        }

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            if (self::build_variation_path($variation) === $path) {
                return (int) $variation_id;
            }
        }

        return 0;
    }

    public static function register_rewrite_rules()
    {
        $base = self::get_product_base_slug();

        add_rewrite_tag('%aav_variation_path%', '(.+)');

        if ($base !== '') {
            add_rewrite_rule(
                '^' . preg_quote($base, '#') . '/([^/]+)/(.+?)/?$',
                'index.php?post_type=product&name=$matches[1]&aav_variation_path=$matches[2]',
                'top'
            );
        } else {
            add_rewrite_rule(
                '^([^/]+)/(.+?)/?$',
                'index.php?post_type=product&name=$matches[1]&aav_variation_path=$matches[2]',
                'top'
            );
        }
    }

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'maybe_redirect_query_urls'], 1);
        add_action('template_redirect', [$this, 'prime_variation_from_url'], 2);
        add_filter('woocommerce_available_variation', [$this, 'add_variation_permalink'], 30, 3);
        add_filter('redirect_canonical', [$this, 'maybe_disable_canonical_redirect'], 10, 2);
        add_action('wp_head', [$this, 'output_canonical'], 1);
        add_filter('pre_get_document_title', [$this, 'filter_document_title'], 20);
    }

    public function register_query_vars($vars)
    {
        $vars[] = 'aav_variation_path';
        return $vars;
    }

    public function add_variation_permalink($data, $product = null, $variation = null)
    {
        if (empty($data['variation_id'])) {
            return $data;
        }

        $variation_product = wc_get_product((int) $data['variation_id']);
        if (!$variation_product) {
            return $data;
        }

        $parent_id = $variation_product->get_parent_id();
        if (!$parent_id) {
            return $data;
        }

        $path = $this->get_variation_path($variation_product);

        if ($path !== '') {
            $data['aav_permalink'] = trailingslashit(get_permalink($parent_id)) . $path . '/';
            $data['aav_variation_path'] = $path;
        }

        return $data;
    }

    public function maybe_redirect_query_urls()
    {
        if (!is_product()) {
            return;
        }

        global $product;

        if (is_string($product) || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return;
        }

        $requested = [];
        foreach ($_GET as $key => $value) {
            if (strpos((string) $key, 'attribute_') === 0 && $value !== '') {
                $requested[sanitize_title(substr((string) $key, 10))] = sanitize_title(wp_unslash((string) $value));
            }
        }

        if (empty($requested)) {
            return;
        }

        $variation_id = $this->find_variation_id_by_attributes($product, $requested);
        if (!$variation_id) {
            return;
        }

        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        $path = $this->get_variation_path($variation);
        if ($path === '') {
            return;
        }

        $pretty = trailingslashit(get_permalink($product->get_id())) . $path . '/';
        wp_safe_redirect($pretty, 302);
        exit;
    }

    public function prime_variation_from_url()
    {
        if (!is_product()) {
            return;
        }

        global $product;

        if (is_string($product) || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return;
        }

        $path = get_query_var('aav_variation_path');
        if (!$path) {
            return;
        }

        $variation_id = $this->find_variation_id_by_path($product, sanitize_text_field(wp_unslash($path)));
        if (!$variation_id) {
            return;
        }

        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        foreach ($variation->get_attributes() as $taxonomy => $term_slug) {
            $request_key = 'attribute_' . sanitize_title($taxonomy);
            $_GET[$request_key] = $term_slug;
            $_REQUEST[$request_key] = $term_slug;
        }
    }

    public function output_canonical()
    {
        if (!is_product()) {
            return;
        }

        global $product;
        if (is_string($product) || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return;
        }

        $path = get_query_var('aav_variation_path');
        if (!$path) {
            return;
        }

        $variation_id = $this->find_variation_id_by_path($product, sanitize_text_field(wp_unslash($path)));
        if (!$variation_id) {
            return;
        }

        $canonical = trailingslashit(get_permalink($product->get_id())) . sanitize_text_field(wp_unslash($path)) . '/';
        echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
    }

    public function maybe_disable_canonical_redirect($redirect_url, $requested_url)
    {
        $request_path = wp_parse_url((string) $requested_url, PHP_URL_PATH);
        if (is_string($request_path) && $this->looks_like_variation_request($request_path)) {
            return false;
        }

        if (!is_product()) {
            return $redirect_url;
        }

        $path = get_query_var('aav_variation_path');
        if (!$path) {
            return $redirect_url;
        }

        global $product;
        if (is_string($product) || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return $redirect_url;
        }

        $variation_id = $this->find_variation_id_by_path($product, sanitize_text_field(wp_unslash($path)));

        return $variation_id ? false : $redirect_url;
    }

    private function looks_like_variation_request($request_path)
    {
        $request_path = trim((string) $request_path, '/');
        if ($request_path === '') {
            return false;
        }

        $base = trim((string) self::get_product_base_slug(), '/');
        $segments = array_values(array_filter(explode('/', $request_path), 'strlen'));

        if ($base !== '') {
            $base_segments = array_values(array_filter(explode('/', $base), 'strlen'));
            if (count($segments) <= count($base_segments) + 1) {
                return false;
            }

            foreach ($base_segments as $index => $segment) {
                if (!isset($segments[$index]) || $segments[$index] !== $segment) {
                    return false;
                }
            }

            return true;
        }

        return count($segments) > 1;
    }

    public function filter_document_title($title)
    {
        if (!is_product()) {
            return $title;
        }

        global $product;
        if (is_string($product) || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
            return $title;
        }

        $path = get_query_var('aav_variation_path');
        if (!$path) {
            return $title;
        }

        $variation_id = $this->find_variation_id_by_path($product, sanitize_text_field(wp_unslash($path)));
        if (!$variation_id) {
            return $title;
        }

        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return $title;
        }

        $labels = [];
        foreach ($variation->get_attributes() as $taxonomy => $term_slug) {
            if ($term_slug) {
                $labels[] = wc_attribute_label($taxonomy) . ': ' . $this->get_attribute_value_label($taxonomy, $term_slug);
            }
        }

        return empty($labels) ? $title : $product->get_name() . ' - ' . implode(', ', $labels);
    }

    private function find_variation_id_by_path($product, $path)
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return 0;
        }

        $requested_attributes = $this->parse_variation_path($path);

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            if (!empty($requested_attributes)) {
                $variation_attributes = $this->get_normalized_variation_attributes($variation, false);
                if ($variation_attributes === $requested_attributes) {
                    return $variation_id;
                }
                continue;
            }

            if (self::build_variation_path($variation) === $path) {
                return $variation_id;
            }
        }

        return 0;
    }

    private function find_variation_id_by_attributes($product, $requested)
    {
        $requested = $this->normalize_requested_attributes($requested);
        if (empty($requested)) {
            return 0;
        }

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            if ($this->variation_matches_requested_attributes($variation, $requested)) {
                return $variation_id;
            }
        }

        return 0;
    }

    private function get_variation_path($variation)
    {
        return self::build_variation_path($variation);
    }

    private static function build_variation_path($variation)
    {
        $attributes = $variation->get_attributes();
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $term_slug) {
            if ($term_slug === '' || $term_slug === null) {
                continue;
            }
            $parts[] = sanitize_title($term_slug);
        }

        return implode('/', $parts);
    }

    private function parse_variation_path($path)
    {
        $segments = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));
        if (count($segments) < 2 || count($segments) % 2 !== 0) {
            return [];
        }

        $attributes = [];
        for ($i = 0; $i < count($segments); $i += 2) {
            $key = $this->normalize_attribute_key($segments[$i]);
            $value = $this->normalize_attribute_value($segments[$i + 1]);
            if ($key === '' || $value === '') {
                return [];
            }
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    private function normalize_requested_attributes($requested)
    {
        $normalized = [];
        foreach ((array) $requested as $key => $value) {
            $normalized_key = $this->normalize_attribute_key($key);
            $normalized_value = $this->normalize_attribute_value($value);
            if ($normalized_key === '' || $normalized_value === '') {
                continue;
            }
            $normalized[$normalized_key] = $normalized_value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function get_normalized_variation_attributes($variation, $require_all_values = false)
    {
        $normalized = [];
        foreach ((array) $variation->get_attributes() as $taxonomy => $term_slug) {
            $key = $this->normalize_attribute_key($taxonomy);
            $value = $this->normalize_attribute_value($term_slug);

            if ($key === '') {
                continue;
            }

            if ($value === '') {
                if ($require_all_values) {
                    return [];
                }
                $normalized[$key] = '';
                continue;
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function variation_matches_requested_attributes($variation, $requested)
    {
        $variation_attributes = $this->get_normalized_variation_attributes($variation, false);
        if (empty($variation_attributes)) {
            return false;
        }

        foreach ($variation_attributes as $key => $value) {
            if ($value === '') {
                continue;
            }

            if (!isset($requested[$key]) || $requested[$key] !== $value) {
                return false;
            }
        }

        foreach ($requested as $key => $value) {
            if (!array_key_exists($key, $variation_attributes)) {
                return false;
            }

            if ($variation_attributes[$key] !== '' && $variation_attributes[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function normalize_attribute_key($taxonomy)
    {
        return sanitize_title((string) $taxonomy);
    }

    private function normalize_attribute_value($value)
    {
        return sanitize_title((string) $value);
    }

    private function get_attribute_value_label($taxonomy, $term_slug)
    {
        $taxonomy = (string) $taxonomy;
        $term_slug = (string) $term_slug;

        if (taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $term_slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        return $term_slug;
    }

    private static function get_product_base_slug()
    {
        if (!function_exists('wc_get_permalink_structure')) {
            return 'product';
        }

        $permalinks = wc_get_permalink_structure();
        $base = isset($permalinks['product_rewrite_slug']) ? (string) $permalinks['product_rewrite_slug'] : 'product';
        $base = trim($base, '/');

        return $base;
    }
}
