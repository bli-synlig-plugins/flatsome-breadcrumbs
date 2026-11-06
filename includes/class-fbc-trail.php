<?php
/**
 * Builds the breadcrumb trail from the main query.
 *
 * @package Flatsome_Breadcrumbs
 */

if (!defined('ABSPATH')) {
    exit;
}

class FBC_Trail
{
    /**
     * Trail for the current request.
     *
     * Each item is shaped as:
     * label (string), url (string), current (bool), icon (string, "home" or "").
     * The last item is the current page. `fbc_breadcrumb_items` receives the
     * list; `fbc_breadcrumb_item` receives one item, its index, and the list.
     *
     * @param array<string, mixed> $args show_home, home_label, home_icon.
     * @return array<int, array<string, mixed>>
     */
    public static function get(array $args): array
    {
        $args = self::args($args);
        $built = self::normalize_list(self::build($args));
        $items = apply_filters('fbc_breadcrumb_items', $built, $args);

        if (!is_array($items)) {
            return array();
        }

        $clean = array();
        foreach (array_values($items) as $index => $item) {
            $normalized = self::normalize(is_array($item) ? $item : array());
            $filtered = apply_filters('fbc_breadcrumb_item', $normalized, $index, $items);
            if (!is_array($filtered)) {
                continue;
            }
            $normalized = self::normalize($filtered);
            if ($normalized['label'] === '') {
                continue;
            }
            $clean[] = $normalized;
        }

        $last = count($clean) - 1;
        if ($last >= 0) {
            foreach ($clean as $index => $item) {
                $clean[$index]['current'] = $index === $last;
            }
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $args
     * @return array{show_home: bool, home_label: string, home_icon: bool}
     */
    private static function args(array $args): array
    {
        $label = isset($args['home_label']) ? trim((string) $args['home_label']) : '';

        return array(
            'show_home' => !empty($args['show_home']),
            'home_label' => $label !== '' ? $label : __('Home', 'flatsome-breadcrumbs'),
            'home_icon' => !empty($args['home_icon']),
        );
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function build(array $args): array
    {
        if (is_404()) {
            return self::not_found_items($args);
        }

        if (is_search()) {
            return self::search_items($args);
        }

        if (is_front_page()) {
            return self::front_page_items($args);
        }

        if (self::is_wc_trail()) {
            return self::wc_items($args);
        }

        if (is_home()) {
            return self::posts_index_items($args);
        }

        if (is_singular() || self::is_wc_page()) {
            return self::singular_items($args);
        }

        if (is_category() || is_tag() || is_tax()) {
            return self::term_archive_items($args);
        }

        if (is_author()) {
            return self::author_items($args);
        }

        if (is_date()) {
            return self::date_items($args);
        }

        if (is_post_type_archive()) {
            return self::post_type_archive_items($args);
        }

        return self::fallback_items($args);
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function front_page_items(array $args): array
    {
        if ($args['show_home']) {
            return array(self::home_item($args, true));
        }

        $label = '';
        if (is_singular()) {
            $label = get_the_title(get_queried_object_id());
        }
        if ($label === '') {
            $label = __('Home', 'flatsome-breadcrumbs');
        }

        return array(self::crumb($label, home_url('/'), true));
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function posts_index_items(array $args): array
    {
        $items = array();
        self::push($items, self::home_item($args, false));
        $posts = self::posts_page_item(true);
        if ($posts) {
            self::push($items, $posts);
            return $items;
        }

        self::push($items, self::crumb(__('Blog', 'flatsome-breadcrumbs'), '', true));

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function singular_items(array $args): array
    {
        $post_id = (int) get_queried_object_id();
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return self::fallback_items($args);
        }

        if ($post->post_type === 'product') {
            return self::wc_items($args);
        }

        $items = array();
        self::push($items, self::home_item($args, false));

        if ($post->post_type === 'post') {
            self::push($items, self::posts_page_item(false));
            $term = self::primary_term($post_id, 'category');
            if ($term) {
                foreach (self::term_chain($term, true, false) as $crumb) {
                    self::push($items, $crumb);
                }
            }
        } elseif ($post->post_type === 'page' || $post->post_type === 'attachment') {
            foreach (array_reverse(get_post_ancestors($post_id)) as $ancestor_id) {
                $ancestor_id = (int) $ancestor_id;
                if (!self::is_public_post($ancestor_id)) {
                    continue;
                }
                self::push($items, self::crumb(get_the_title($ancestor_id), get_permalink($ancestor_id)));
            }
        } else {
            self::push($items, self::post_type_archive_item($post->post_type, false));
            if (is_post_type_hierarchical($post->post_type)) {
                foreach (array_reverse(get_post_ancestors($post_id)) as $ancestor_id) {
                    $ancestor_id = (int) $ancestor_id;
                    if (!self::is_public_post($ancestor_id)) {
                        continue;
                    }
                    self::push($items, self::crumb(get_the_title($ancestor_id), get_permalink($ancestor_id)));
                }
            } else {
                $taxonomy = self::primary_taxonomy($post->post_type);
                if ($taxonomy !== '') {
                    $term = self::primary_term($post_id, $taxonomy);
                    if ($term) {
                        foreach (self::term_chain($term, true, false) as $crumb) {
                            self::push($items, $crumb);
                        }
                    }
                }
            }
        }

        self::push($items, self::crumb(get_the_title($post_id), get_permalink($post_id), true));

        return self::append_endpoint($items);
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function term_archive_items(array $args): array
    {
        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return self::fallback_items($args);
        }

        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::taxonomy_context_item($term->taxonomy));
        foreach (self::term_chain($term, true, true) as $crumb) {
            self::push($items, $crumb);
        }

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function wc_items(array $args): array
    {
        $items = array();
        self::push($items, self::home_item($args, false));

        if (function_exists('is_shop') && is_shop()) {
            self::push($items, self::shop_item(true));
            return $items;
        }

        self::push($items, self::shop_item(false));

        if (is_singular('product')) {
            $post_id = (int) get_queried_object_id();
            $term = self::primary_term($post_id, 'product_cat');
            if ($term) {
                foreach (self::term_chain($term, true, false) as $crumb) {
                    self::push($items, $crumb);
                }
            }
            self::push($items, self::crumb(get_the_title($post_id), get_permalink($post_id), true));

            return $items;
        }

        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            foreach (self::term_chain($term, true, true) as $crumb) {
                self::push($items, $crumb);
            }
        }

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function search_items(array $args): array
    {
        $query = get_search_query();
        $label = $query === ''
            ? __('Search results', 'flatsome-breadcrumbs')
            : sprintf(
                /* translators: %s: search query. */
                __('Search results for “%s”', 'flatsome-breadcrumbs'),
                $query
            );

        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::crumb($label, get_search_link($query), true));

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function not_found_items(array $args): array
    {
        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::crumb(__('Page not found', 'flatsome-breadcrumbs'), '', true));

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function author_items(array $args): array
    {
        $author = get_queried_object();
        $name = ($author && isset($author->display_name)) ? (string) $author->display_name : __('Author', 'flatsome-breadcrumbs');
        $url = ($author && isset($author->ID)) ? get_author_posts_url((int) $author->ID) : '';

        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::posts_page_item(false));
        self::push($items, self::crumb($name, $url, true));

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function date_items(array $args): array
    {
        $year = (int) get_query_var('year');
        if ($year <= 0) {
            $year = (int) get_the_date('Y');
        }
        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::posts_page_item(false));

        $year_current = is_year() || ($month <= 0 && $day <= 0);
        self::push($items, self::crumb((string) $year, get_year_link($year), $year_current));

        if ($month > 0) {
            $month_current = is_month() || $day <= 0;
            self::push($items, self::crumb(
                date_i18n('F', mktime(0, 0, 0, $month, 1, $year)),
                get_month_link($year, $month),
                $month_current && !is_day()
            ));
        }

        if ($day > 0) {
            self::push($items, self::crumb(
                date_i18n('j', mktime(0, 0, 0, max(1, $month), $day, $year)),
                get_day_link($year, max(1, $month), $day),
                true
            ));
        }

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function post_type_archive_items(array $args): array
    {
        $post_type = get_query_var('post_type');
        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }
        $post_type = is_string($post_type) ? $post_type : '';

        $items = array();
        self::push($items, self::home_item($args, false));
        self::push($items, self::post_type_archive_item($post_type, true));

        return $items;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<int, array<string, mixed>>
     */
    private static function fallback_items(array $args): array
    {
        $items = array();
        self::push($items, self::home_item($args, false));

        $label = '';
        if (is_archive()) {
            $label = wp_strip_all_tags(get_the_archive_title());
        }
        if ($label === '') {
            $label = wp_strip_all_tags((string) single_post_title('', false));
        }
        if ($label !== '') {
            self::push($items, self::crumb($label, '', true));
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function append_endpoint(array $items): array
    {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url() || !function_exists('WC')) {
            return $items;
        }

        $wc = WC();
        if (!is_object($wc) || !isset($wc->query) || !is_object($wc->query) || !method_exists($wc->query, 'get_current_endpoint')) {
            return $items;
        }

        $endpoint = (string) $wc->query->get_current_endpoint();
        if ($endpoint === '') {
            return $items;
        }

        $title = '';
        if (method_exists($wc->query, 'get_endpoint_title')) {
            $title = wp_strip_all_tags((string) $wc->query->get_endpoint_title($endpoint));
        }
        $title = trim($title);
        if ($title === '') {
            $title = ucwords(str_replace(array('-', '_'), ' ', $endpoint));
        }

        if ($items) {
            $last = count($items) - 1;
            $items[$last]['current'] = false;
            if (empty($items[$last]['url'])) {
                $link = get_permalink(get_queried_object_id());
                if (is_string($link) && $link !== '') {
                    $items[$last]['url'] = $link;
                }
            }
        }

        self::push($items, self::crumb($title, '', true));

        return $items;
    }

    private static function is_wc_trail(): bool
    {
        if (!function_exists('is_woocommerce')) {
            return false;
        }

        if (function_exists('is_shop') && is_shop()) {
            return true;
        }

        if (is_singular('product')) {
            return true;
        }

        if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
            return true;
        }

        return is_tax(array('product_cat', 'product_tag'));
    }

    private static function is_wc_page(): bool
    {
        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }

        return false;
    }

    /**
     * @param array{show_home: bool, home_label: string, home_icon: bool} $args
     * @return array<string, mixed>|null
     */
    private static function home_item(array $args, bool $current): ?array
    {
        if (!$args['show_home']) {
            return null;
        }

        return self::crumb(
            $args['home_label'],
            home_url('/'),
            $current,
            $args['home_icon'] ? 'home' : ''
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function posts_page_item(bool $current): ?array
    {
        $page_id = (int) get_option('page_for_posts');
        if ($page_id <= 0) {
            return null;
        }

        return self::crumb(get_the_title($page_id), (string) get_permalink($page_id), $current);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function shop_item(bool $current): ?array
    {
        if (function_exists('wc_get_page_id')) {
            $shop_id = (int) wc_get_page_id('shop');
            if ($shop_id > 0) {
                return self::crumb(get_the_title($shop_id), (string) get_permalink($shop_id), $current);
            }
        }

        $url = get_post_type_archive_link('product');
        if (!is_string($url) || $url === '') {
            return null;
        }

        return self::crumb(__('Shop', 'flatsome-breadcrumbs'), $url, $current);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function post_type_archive_item(string $post_type, bool $current): ?array
    {
        if ($post_type === '') {
            return null;
        }

        $object = get_post_type_object($post_type);
        if (!$object || empty($object->has_archive) || empty($object->public)) {
            return null;
        }

        $url = get_post_type_archive_link($post_type);
        if (!is_string($url) || $url === '') {
            return null;
        }

        $label = isset($object->labels->name) ? (string) $object->labels->name : $post_type;

        return self::crumb($label, $url, $current);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function taxonomy_context_item(string $taxonomy): ?array
    {
        if ($taxonomy === 'category' || $taxonomy === 'post_tag') {
            return self::posts_page_item(false);
        }

        $tax = get_taxonomy($taxonomy);
        if (!$tax || empty($tax->object_type) || !is_array($tax->object_type)) {
            return null;
        }

        if (in_array('product', $tax->object_type, true)) {
            return self::shop_item(false);
        }

        if (count($tax->object_type) === 1 && is_string($tax->object_type[0])) {
            return self::post_type_archive_item($tax->object_type[0], false);
        }

        return null;
    }

    private static function primary_term(int $post_id, string $taxonomy): ?WP_Term
    {
        if ($post_id <= 0 || $taxonomy === '') {
            return null;
        }

        foreach (array('_yoast_wpseo_primary_' . $taxonomy, 'rank_math_primary_' . $taxonomy) as $meta_key) {
            $term_id = (int) get_post_meta($post_id, $meta_key, true);
            if ($term_id <= 0) {
                continue;
            }
            $term = get_term($term_id, $taxonomy);
            if ($term instanceof WP_Term) {
                return $term;
            }
        }

        $terms = get_the_terms($post_id, $taxonomy);
        if (!is_array($terms) || !$terms) {
            return null;
        }

        usort($terms, function ($a, $b) use ($taxonomy) {
            if (!$a instanceof WP_Term || !$b instanceof WP_Term) {
                return 0;
            }
            $depth_a = count(get_ancestors($a->term_id, $taxonomy));
            $depth_b = count(get_ancestors($b->term_id, $taxonomy));
            if ($depth_a === $depth_b) {
                return $a->term_id - $b->term_id;
            }

            return $depth_b - $depth_a;
        });

        $term = $terms[0];

        return $term instanceof WP_Term ? $term : null;
    }

    private static function primary_taxonomy(string $post_type): string
    {
        $taxes = get_object_taxonomies($post_type, 'objects');
        if (!is_array($taxes)) {
            return '';
        }

        $hierarchical = '';
        $any = '';
        foreach ($taxes as $tax) {
            if (!is_object($tax) || empty($tax->public) || empty($tax->name) || $tax->name === 'post_format') {
                continue;
            }
            if ($any === '') {
                $any = (string) $tax->name;
            }
            if (!empty($tax->hierarchical) && $hierarchical === '') {
                $hierarchical = (string) $tax->name;
            }
        }

        return $hierarchical !== '' ? $hierarchical : $any;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function term_chain(WP_Term $term, bool $include_self, bool $self_current): array
    {
        $items = array();
        $ancestors = array_reverse(get_ancestors($term->term_id, $term->taxonomy));
        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term((int) $ancestor_id, $term->taxonomy);
            if ($ancestor instanceof WP_Term) {
                $crumb = self::term_crumb($ancestor, false);
                if ($crumb) {
                    $items[] = $crumb;
                }
            }
        }

        if ($include_self) {
            $crumb = self::term_crumb($term, $self_current);
            if ($crumb) {
                $items[] = $crumb;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function term_crumb(WP_Term $term, bool $current): ?array
    {
        $link = get_term_link($term);
        $url = is_wp_error($link) ? '' : (string) $link;

        return self::crumb($term->name, $url, $current);
    }

    private static function is_public_post(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return false;
        }

        if ($post->post_status === 'publish') {
            return true;
        }

        return current_user_can('read_post', $post_id);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>|null $item
     */
    private static function push(array &$items, ?array $item): void
    {
        if (!$item || empty($item['label'])) {
            return;
        }

        $url = isset($item['url']) ? untrailingslashit((string) $item['url']) : '';
        foreach ($items as $existing) {
            if (!is_array($existing)) {
                continue;
            }
            $existing_url = isset($existing['url']) ? untrailingslashit((string) $existing['url']) : '';
            $same_url = $url !== '' && $existing_url === $url;
            $same_label = (string) $existing['label'] === (string) $item['label'] && $existing_url === $url;
            if ($same_url || $same_label) {
                return;
            }
        }

        $items[] = $item;
    }

    /**
     * @return array<string, mixed>
     */
    private static function crumb(string $label, string $url = '', bool $current = false, string $icon = ''): array
    {
        return array(
            'label' => $label,
            'url' => $url,
            'current' => $current,
            'icon' => $icon,
        );
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private static function normalize_list(array $items): array
    {
        $clean = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = self::normalize($item);
            if ($normalized['label'] === '') {
                continue;
            }
            $clean[] = $normalized;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{label: string, url: string, current: bool, icon: string}
     */
    private static function normalize(array $item): array
    {
        $label = isset($item['label']) ? trim(wp_strip_all_tags((string) $item['label'])) : '';
        $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
        $icon = (isset($item['icon']) && (string) $item['icon'] === 'home') ? 'home' : '';

        return array(
            'label' => $label,
            'url' => is_string($url) ? $url : '',
            'current' => !empty($item['current']),
            'icon' => $icon,
        );
    }
}
