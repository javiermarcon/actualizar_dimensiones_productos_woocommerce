<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!class_exists('WP_Error')) {
    class WP_Error {
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term {
        public int $term_id = 0;
        public string $name = '';
        public string $slug = '';
        public int $parent = 0;
        public string $taxonomy = '';
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product {
        private string $weight = '';
        private string $length = '';
        private string $width = '';
        private string $height = '';
        private string $name = 'Test Product';
        private int $id = 0;
        private int $shipping_class_id = 0;
        private string $shipping_class = '';
        private int $save_count = 0;

        public function __construct(int $id = 0, string $name = 'Test Product') {
            $this->id = $id;
            $this->name = $name;
        }

        public function get_id(): int {
            return $this->id;
        }

        public function get_weight(): string {
            return $this->weight;
        }

        public function get_length(): string {
            return $this->length;
        }

        public function get_width(): string {
            return $this->width;
        }

        public function get_height(): string {
            return $this->height;
        }

        public function get_name(): string {
            return $this->name;
        }

        public function get_shipping_class_id(): int {
            return $this->shipping_class_id;
        }

        public function get_shipping_class(): string {
            return $this->shipping_class;
        }

        public function set_weight(float $value): void {
            $this->weight = (string) $value;
        }

        public function set_length($value): void {
            $this->length = (string) $value;
        }

        public function set_width($value): void {
            $this->width = (string) $value;
        }

        public function set_height($value): void {
            $this->height = (string) $value;
        }

        public function set_shipping_class_id(int $value): void {
            $this->shipping_class_id = $value;
        }

        public function save(): void {
            $this->save_count++;
        }

        public function get_save_count(): int {
            return $this->save_count;
        }

        public function set_shipping_class(string $value): void {
            $this->shipping_class = $value;
        }
    }
}

if (!isset($GLOBALS['adpw_test_terms'])) {
    $GLOBALS['adpw_test_terms'] = [];
}
if (!isset($GLOBALS['adpw_test_options'])) {
    $GLOBALS['adpw_test_options'] = [];
}
if (!isset($GLOBALS['adpw_test_term_meta'])) {
    $GLOBALS['adpw_test_term_meta'] = [];
}
if (!isset($GLOBALS['adpw_test_posts'])) {
    $GLOBALS['adpw_test_posts'] = [];
}
if (!isset($GLOBALS['adpw_test_post_terms'])) {
    $GLOBALS['adpw_test_post_terms'] = [];
}
if (!isset($GLOBALS['adpw_test_ancestors'])) {
    $GLOBALS['adpw_test_ancestors'] = [];
}
if (!isset($GLOBALS['adpw_test_products'])) {
    $GLOBALS['adpw_test_products'] = [];
}
if (!isset($GLOBALS['adpw_test_scheduled_events'])) {
    $GLOBALS['adpw_test_scheduled_events'] = [];
}

if (!function_exists('adpw_test_reset_wp_stubs')) {
    function adpw_test_reset_wp_stubs(): void {
        $GLOBALS['adpw_test_terms'] = [];
        $GLOBALS['adpw_test_options'] = [];
        $GLOBALS['adpw_test_term_meta'] = [];
        $GLOBALS['adpw_test_posts'] = [];
        $GLOBALS['adpw_test_post_terms'] = [];
        $GLOBALS['adpw_test_ancestors'] = [];
        $GLOBALS['adpw_test_products'] = [];
        $GLOBALS['adpw_test_scheduled_events'] = [];
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents(string $text): string {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return is_string($converted) ? $converted : $text;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page(
        string $page_title,
        string $menu_title,
        string $capability,
        string $menu_slug,
        $callback = null,
        string $icon_url = '',
        $position = null
    ): void {
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page(
        string $parent_slug,
        string $page_title,
        string $menu_title,
        string $capability,
        string $menu_slug,
        $callback = null,
        $position = null
    ): void {
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return $path;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url = ''): string {
        return $url;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $text): string {
        return $text;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string {
        return trim($text);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $key) ?? '');
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string {
        $title = strtolower(remove_accents($title));
        $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?? '';
        return trim($title, '-');
    }
}

if (!function_exists('absint')) {
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, bool $display = true): string {
        return $selected === $current ? 'selected="selected"' : '';
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, bool $display = true): string {
        return $checked === $current ? 'checked="checked"' : '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): bool {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string {
        return 'nonce';
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action = '', string $name = '_wpnonce'): void {
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action = '', $query_arg = false, bool $stop = true): bool {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        return true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($value = null): void {
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($value = null): void {
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string {
        return rtrim($value, "/\\") . '/';
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string {
        return '00000000-0000-4000-8000-000000000000';
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array {
        return [
            'basedir' => sys_get_temp_dir(),
            'error' => '',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value) {
        return json_encode($value);
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return array_key_exists($option, $GLOBALS['adpw_test_options']) ? $GLOBALS['adpw_test_options'][$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, bool $autoload = true): bool {
        $GLOBALS['adpw_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        unset($GLOBALS['adpw_test_options'][$option]);
        return true;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults = []): array {
        return array_merge($defaults, $args);
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []) {
        $key = $hook . '|' . md5(json_encode($args));
        if (!isset($GLOBALS['adpw_test_scheduled_events'][$key])) {
            return false;
        }

        return $GLOBALS['adpw_test_scheduled_events'][$key]['timestamp'];
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool {
        $key = $hook . '|' . md5(json_encode($args));
        $GLOBALS['adpw_test_scheduled_events'][$key] = [
            'timestamp' => $timestamp,
            'hook' => $hook,
            'args' => $args,
        ];
        return true;
    }
}

if (!function_exists('spawn_cron')) {
    function spawn_cron(int $gmt_time = 0): void {
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('get_terms')) {
    function get_terms(array $args = []): array {
        $terms = array_values((array) ($GLOBALS['adpw_test_terms'] ?? []));
        if (!empty($args['taxonomy'])) {
            $terms = array_values(array_filter($terms, static function ($term) use ($args) {
                return $term instanceof WP_Term && $term->taxonomy === $args['taxonomy'];
            }));
        }

        if (($args['fields'] ?? '') === 'slugs') {
            return array_values(array_map(static function ($term) {
                return (string) $term->slug;
            }, $terms));
        }

        return $terms;
    }
}

if (!function_exists('get_term')) {
    function get_term(int $term_id, string $taxonomy = '') {
        foreach ((array) ($GLOBALS['adpw_test_terms'] ?? []) as $term) {
            if ($term instanceof WP_Term && $term->term_id === $term_id && ($taxonomy === '' || $term->taxonomy === $taxonomy)) {
                return $term;
            }
        }

        return false;
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by(string $field, string $value, string $taxonomy = '') {
        foreach ((array) ($GLOBALS['adpw_test_terms'] ?? []) as $term) {
            if (!$term instanceof WP_Term || ($taxonomy !== '' && $term->taxonomy !== $taxonomy)) {
                continue;
            }

            if ($field === 'name' && $term->name === $value) {
                return $term;
            }

            if ($field === 'slug' && $term->slug === $value) {
                return $term;
            }
        }

        return false;
    }
}

if (!function_exists('get_term_meta')) {
    function get_term_meta(int $term_id, string $key, bool $single = false): string {
        return (string) ($GLOBALS['adpw_test_term_meta'][$term_id][$key] ?? '');
    }
}

if (!function_exists('update_term_meta')) {
    function update_term_meta(int $term_id, string $key, $value): bool {
        if (!isset($GLOBALS['adpw_test_term_meta'][$term_id])) {
            $GLOBALS['adpw_test_term_meta'][$term_id] = [];
        }
        $GLOBALS['adpw_test_term_meta'][$term_id][$key] = (string) $value;
        return true;
    }
}

if (!function_exists('delete_term_meta')) {
    function delete_term_meta(int $term_id, string $key): bool {
        unset($GLOBALS['adpw_test_term_meta'][$term_id][$key]);
        return true;
    }
}

if (!function_exists('get_posts')) {
    function get_posts(array $args = []): array {
        return array_values((array) ($GLOBALS['adpw_test_posts'] ?? []));
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $post_id, string $taxonomy, array $args = []) {
        return array_values((array) ($GLOBALS['adpw_test_post_terms'][$post_id] ?? []));
    }
}

if (!function_exists('get_ancestors')) {
    function get_ancestors(int $object_id, string $object_type = '', string $resource_type = ''): array {
        return array_values((array) ($GLOBALS['adpw_test_ancestors'][$object_id] ?? []));
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product(int $product_id): ?WC_Product {
        return $GLOBALS['adpw_test_products'][$product_id] ?? null;
    }
}
