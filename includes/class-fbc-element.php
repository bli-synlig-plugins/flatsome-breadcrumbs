<?php
/**
 * UX Builder element and [ux_breadcrumbs] shortcode.
 *
 * @package Flatsome_Breadcrumbs
 */

if (!defined('ABSPATH')) {
    exit;
}

class FBC_Element
{
    const TAG = 'ux_breadcrumbs';

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'), 5);
        add_action('ux_builder_enqueue_scripts', array(__CLASS__, 'enqueue_builder'));
        add_shortcode(self::TAG, array(__CLASS__, 'render'));
        // After WP Icons for Flatsome registers the wpicons option type (priority 10).
        add_action('ux_builder_setup', array(__CLASS__, 'register'), 20);
    }

    public static function register_assets(): void
    {
        if (!wp_style_is('flatsome-breadcrumbs', 'registered')) {
            wp_register_style(
                'flatsome-breadcrumbs',
                FBC_URL . 'assets/css/frontend.css',
                array(),
                FBC_VERSION
            );
        }

        if (!wp_script_is('flatsome-breadcrumbs', 'registered')) {
            wp_register_script(
                'flatsome-breadcrumbs',
                FBC_URL . 'assets/js/breadcrumbs.js',
                array(),
                FBC_VERSION,
                true
            );
        }
    }

    /**
     * @param string $context editor|content
     */
    public static function enqueue_builder($context = ''): void
    {
        if ($context !== 'content') {
            return;
        }

        self::register_assets();
        self::enqueue_assets();
    }

    public static function register(): void
    {
        if (!function_exists('add_ux_builder_shortcode')) {
            return;
        }

        add_ux_builder_shortcode(self::TAG, array(
            'name' => __('Breadcrumbs', 'flatsome-breadcrumbs'),
            // Same gettext call Flatsome uses, so the element lands in Layout.
            'category' => __('Layout', 'flatsome'),
            'icon' => 'dashicons-arrow-right-alt',
            'info' => __('Breadcrumb trail', 'flatsome-breadcrumbs'),
            'wrap' => false,
            'options' => self::options(),
        ));
    }

    /**
     * @param mixed $atts
     */
    public static function render($atts, $content = ''): string
    {
        unset($content);

        $parsed = self::parse_atts($atts);
        $preview = self::is_builder_preview();
        $items = $preview
            ? self::preview_items($parsed)
            : FBC_Trail::get(array(
                'show_home' => $parsed['show_home'],
                'home_label' => $parsed['home_label'],
                'home_icon' => $parsed['home_icon'],
            ));

        if (!$items) {
            return '';
        }

        $schema_items = $items;
        $mark_current = true;
        if ($parsed['hide_current']) {
            array_pop($items);
            $mark_current = false;
        }

        $schema = (!$preview && $parsed['schema']) ? self::schema_html($schema_items) : '';
        if (!$items) {
            return $schema;
        }

        self::register_assets();
        self::enqueue_assets();

        $separator = self::separator_html($parsed['separator'], $parsed['separator_icon'], $parsed['icon_size']);

        return self::nav_html($items, $parsed, $separator, $mark_current) . $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(): array
    {
        $defaults = self::defaults();
        $separator = array(
            'separator' => array(
                'type' => 'select',
                'heading' => __('Preset', 'flatsome-breadcrumbs'),
                'default' => $defaults['separator'],
                'options' => array(
                    'chevron' => __('Chevron', 'flatsome-breadcrumbs'),
                    'slash' => __('Slash', 'flatsome-breadcrumbs'),
                    'arrow' => __('Arrow', 'flatsome-breadcrumbs'),
                    'dot' => __('Dot', 'flatsome-breadcrumbs'),
                ),
            ),
        );

        if (self::wpicons_available()) {
            $separator['separator_icon'] = array(
                'type' => 'wpicons',
                'heading' => __('Custom icon', 'flatsome-breadcrumbs'),
                'description' => __('Overrides the preset when an icon is selected.', 'flatsome-breadcrumbs'),
                'default' => '',
            );
        }

        return array(
            'content_options' => array(
                'type' => 'group',
                'heading' => __('Content', 'flatsome-breadcrumbs'),
                'options' => array(
                    'show_home' => array(
                        'type' => 'checkbox',
                        'heading' => __('Show home', 'flatsome-breadcrumbs'),
                        'default' => $defaults['show_home'],
                    ),
                    'home_label' => array(
                        'type' => 'textfield',
                        'heading' => __('Home label', 'flatsome-breadcrumbs'),
                        'default' => $defaults['home_label'],
                        'full_width' => true,
                        'conditions' => 'show_home === "true"',
                    ),
                    'home_icon' => array(
                        'type' => 'checkbox',
                        'heading' => __('Home as icon', 'flatsome-breadcrumbs'),
                        'default' => $defaults['home_icon'],
                        'conditions' => 'show_home === "true"',
                    ),
                    'hide_current' => array(
                        'type' => 'checkbox',
                        'heading' => __('Hide current', 'flatsome-breadcrumbs'),
                        'default' => $defaults['hide_current'],
                    ),
                    'max_items' => array(
                        'type' => 'slider',
                        'heading' => __('Max items before ellipsis', 'flatsome-breadcrumbs'),
                        'description' => __('Middle crumbs kept visible. Home and the current page stay. 0 collapses only when the row overflows.', 'flatsome-breadcrumbs'),
                        'default' => $defaults['max_items'],
                        'min' => '0',
                        'max' => '8',
                        'step' => '1',
                        'unit' => '',
                    ),
                    'schema' => array(
                        'type' => 'checkbox',
                        'heading' => __('Enable schema markup', 'flatsome-breadcrumbs'),
                        'default' => $defaults['schema'],
                    ),
                ),
            ),
            'separator_options' => array(
                'type' => 'group',
                'heading' => __('Separator', 'flatsome-breadcrumbs'),
                'options' => $separator,
            ),
            'style_options' => array(
                'type' => 'group',
                'heading' => __('Style', 'flatsome-breadcrumbs'),
                'options' => array(
                    'align' => array(
                        'type' => 'select',
                        'heading' => __('Align', 'flatsome-breadcrumbs'),
                        'default' => $defaults['align'],
                        'options' => array(
                            'left' => __('Left', 'flatsome-breadcrumbs'),
                            'center' => __('Center', 'flatsome-breadcrumbs'),
                            'right' => __('Right', 'flatsome-breadcrumbs'),
                        ),
                    ),
                    'font_size' => array(
                        'type' => 'slider',
                        'heading' => __('Font size', 'flatsome-breadcrumbs'),
                        'default' => $defaults['font_size'],
                        'min' => '10',
                        'max' => '48',
                        'step' => '1',
                        'unit' => 'px',
                    ),
                    'gap' => array(
                        'type' => 'slider',
                        'heading' => __('Gap', 'flatsome-breadcrumbs'),
                        'default' => $defaults['gap'],
                        'min' => '0',
                        'max' => '48',
                        'step' => '1',
                        'unit' => 'px',
                    ),
                    'icon_size' => array(
                        'type' => 'slider',
                        'heading' => __('Icon size', 'flatsome-breadcrumbs'),
                        'default' => $defaults['icon_size'],
                        'min' => '8',
                        'max' => '48',
                        'step' => '1',
                        'unit' => 'px',
                    ),
                ),
            ),
            'color_options' => array(
                'type' => 'group',
                'heading' => __('Colors', 'flatsome-breadcrumbs'),
                'options' => array(
                    'link_color' => self::color_option(__('Link', 'flatsome-breadcrumbs')),
                    'hover_color' => self::color_option(__('Hover', 'flatsome-breadcrumbs')),
                    'current_color' => self::color_option(__('Current', 'flatsome-breadcrumbs')),
                    'separator_color' => self::color_option(__('Separator', 'flatsome-breadcrumbs')),
                ),
            ),
            'advanced_options' => array(
                'type' => 'group',
                'heading' => __('Advanced', 'flatsome-breadcrumbs'),
                'options' => array(
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => __('Class', 'flatsome-breadcrumbs'),
                        'default' => '',
                        'full_width' => true,
                    ),
                    'id' => array(
                        'type' => 'textfield',
                        'heading' => __('ID', 'flatsome-breadcrumbs'),
                        'default' => '',
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function color_option(string $heading): array
    {
        return array(
            'type' => 'colorpicker',
            'heading' => $heading,
            'default' => '',
            'format' => 'rgb',
            'alpha' => true,
            'position' => 'bottom right',
            // Titles stay in English so Flatsome ekstra farger can merge swatches.
            'helpers' => self::color_helpers(),
        );
    }

    /**
     * @return array<int, array{title: string, value: string}>
     */
    private static function color_helpers(): array
    {
        return array(
            array('title' => 'Dark', 'value' => 'rgb(0,0,0)'),
            array('title' => 'White', 'value' => 'rgb(255,255,255)'),
            array('title' => 'Primary', 'value' => self::theme_color('color_primary', '#446084')),
            array('title' => 'Secondary', 'value' => self::theme_color('color_secondary', '#d26e4b')),
            array('title' => 'Success', 'value' => self::theme_color('color_success', '#7a9c59')),
            array('title' => 'Alert', 'value' => self::theme_color('color_alert', '#b20000')),
        );
    }

    private static function theme_color(string $mod, string $fallback): string
    {
        if (!function_exists('flatsome_option')) {
            return $fallback;
        }

        $value = flatsome_option($mod);
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return trim($value);
    }

    /**
     * True when WP Icons for Flatsome has registered the wpicons option type.
     */
    public static function wpicons_available(): bool
    {
        if (!function_exists('add_ux_builder_option_type') || !function_exists('wpicons_get_icon')) {
            return false;
        }

        return class_exists('WIF_Builder', false);
    }

    /**
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return array(
            'show_home' => 'true',
            'home_label' => __('Home', 'flatsome-breadcrumbs'),
            'home_icon' => 'false',
            'hide_current' => 'false',
            'max_items' => '0',
            'schema' => 'false',
            'separator' => 'chevron',
            'separator_icon' => '',
            'align' => 'left',
            'font_size' => '14',
            'gap' => '8',
            'icon_size' => '14',
            'link_color' => '',
            'hover_color' => '',
            'current_color' => '',
            'separator_color' => '',
            'class' => '',
            'id' => '',
        );
    }

    /**
     * @param mixed $atts
     * @return array<string, mixed>
     */
    private static function parse_atts($atts): array
    {
        if (!is_array($atts)) {
            $atts = array();
        }

        $atts = shortcode_atts(self::defaults(), $atts, self::TAG);
        $align = in_array($atts['align'], array('left', 'center', 'right'), true) ? $atts['align'] : 'left';
        $separator = in_array($atts['separator'], array('chevron', 'slash', 'arrow', 'dot'), true) ? $atts['separator'] : 'chevron';

        $classes = array();
        $class_value = trim((string) $atts['class']);
        if ($class_value !== '') {
            foreach (preg_split('/\s+/', $class_value) as $part) {
                $clean = sanitize_html_class($part);
                if ($clean !== '') {
                    $classes[] = $clean;
                }
            }
        }

        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $atts['id']);

        return array(
            'show_home' => self::is_enabled($atts['show_home']),
            'home_label' => sanitize_text_field((string) $atts['home_label']),
            'home_icon' => self::is_enabled($atts['home_icon']),
            'hide_current' => self::is_enabled($atts['hide_current']),
            'max_middle' => self::sanitize_number($atts['max_items'], 0, 8, 0),
            'schema' => self::is_enabled($atts['schema']),
            'separator' => $separator,
            'separator_icon' => self::sanitize_icon_value((string) $atts['separator_icon']),
            'align' => $align,
            'font_size' => self::sanitize_number($atts['font_size'], 10, 48, 14),
            'gap' => self::sanitize_number($atts['gap'], 0, 48, 8),
            'icon_size' => self::sanitize_number($atts['icon_size'], 8, 48, 14),
            'link_color' => self::sanitize_color((string) $atts['link_color']),
            'hover_color' => self::sanitize_color((string) $atts['hover_color']),
            'current_color' => self::sanitize_color((string) $atts['current_color']),
            'separator_color' => self::sanitize_color((string) $atts['separator_color']),
            'class_list' => array_values(array_unique($classes)),
            'id' => is_string($id) ? $id : '',
        );
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<int, array<string, string>>
     */
    private static function preview_items(array $parsed): array
    {
        $items = array();
        if ($parsed['show_home']) {
            $label = $parsed['home_label'] !== '' ? $parsed['home_label'] : __('Home', 'flatsome-breadcrumbs');
            $items[] = array(
                'label' => $label,
                'url' => '#',
                'icon' => $parsed['home_icon'] ? 'home' : '',
            );
        }

        foreach (array(
            __('Docs', 'flatsome-breadcrumbs'),
            __('Components', 'flatsome-breadcrumbs'),
            __('Breadcrumb', 'flatsome-breadcrumbs'),
        ) as $label) {
            $items[] = array(
                'label' => $label,
                'url' => '#',
                'icon' => '',
            );
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $parsed
     */
    private static function nav_html(array $items, array $parsed, string $separator, bool $mark_current): string
    {
        $menu_id = 'fbc-menu-' . self::next_id();
        $count = count($items);
        $parts = array();

        foreach ($items as $index => $item) {
            $first = $index === 0;
            $last = $index === $count - 1;
            if ($index === 1 && $count > 2) {
                $parts[] = self::ellipsis_html($menu_id, $separator);
            }
            $parts[] = self::item_html($item, $first, !$first && !$last, $mark_current && $last, $separator);
        }

        $classes = array('fbc', 'fbc-align-' . $parsed['align']);
        foreach ($parsed['class_list'] as $class) {
            $classes[] = $class;
        }
        $classes = array_unique($classes);
        $id_attr = $parsed['id'] !== '' ? ' id="' . esc_attr($parsed['id']) . '"' : '';

        return '<nav class="' . esc_attr(implode(' ', $classes)) . '"' . $id_attr
            . ' style="' . esc_attr(self::style_attr($parsed)) . '"'
            . ' aria-label="' . esc_attr__('Breadcrumb', 'flatsome-breadcrumbs') . '"'
            . ' data-max-middle="' . esc_attr((string) $parsed['max_middle']) . '">'
            . '<ol class="fbc-list" role="list">' . implode('', $parts) . '</ol>'
            . '</nav>';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function item_html(array $item, bool $first, bool $collapsible, bool $current, string $separator): string
    {
        $label = isset($item['label']) ? (string) $item['label'] : '';
        $url = isset($item['url']) ? (string) $item['url'] : '';
        $attrs = ' data-fbc-item data-fbc-label="' . esc_attr($label) . '"';
        if ($collapsible) {
            $attrs .= ' data-fbc-collapsible';
        }
        if ($current) {
            $attrs .= ' data-fbc-current';
        }
        if ($url !== '') {
            $attrs .= ' data-fbc-url="' . esc_url($url) . '"';
        }

        $inner = $first ? '' : $separator;
        $inner .= self::label_html($item, $current);

        return '<li class="fbc-item"' . $attrs . '>' . $inner . '</li>';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function label_html(array $item, bool $current): string
    {
        $label = esc_html((string) $item['label']);
        $is_home_icon = isset($item['icon']) && $item['icon'] === 'home';
        if ($is_home_icon) {
            $content = self::home_svg() . '<span class="fbc-sr">' . $label . '</span>';
        } else {
            $content = '<span class="fbc-text">' . $label . '</span>';
        }

        $url = isset($item['url']) ? (string) $item['url'] : '';
        if ($current || $url === '') {
            $class = $current ? 'fbc-current' : 'fbc-text';
            $aria = $current ? ' aria-current="page"' : '';

            return '<span class="' . esc_attr($class) . '"' . $aria . '>' . $content . '</span>';
        }

        return '<a class="fbc-link" href="' . esc_url($url) . '">' . $content . '</a>';
    }

    private static function ellipsis_html(string $menu_id, string $separator): string
    {
        return '<li class="fbc-ellipsis" data-fbc-ellipsis hidden>'
            . $separator
            . '<button type="button" class="fbc-ellipsis-btn" aria-expanded="false" aria-haspopup="menu" aria-controls="'
            . esc_attr($menu_id) . '" aria-label="' . esc_attr__('Show hidden pages', 'flatsome-breadcrumbs') . '">'
            . '<span aria-hidden="true">…</span>'
            . '</button>'
            . '<div class="fbc-menu" id="' . esc_attr($menu_id) . '" role="menu" hidden></div>'
            . '</li>';
    }

    private static function separator_html(string $preset, string $custom, int $icon_size): string
    {
        $icon = self::custom_icon_html($custom, $icon_size);
        if ($icon === '') {
            $icon = self::preset_svg($preset);
        }

        return '<span class="fbc-sep" aria-hidden="true">' . $icon . '</span>';
    }

    private static function custom_icon_html(string $value, int $size): string
    {
        if ($value === '' || !function_exists('wpicons_get_icon')) {
            return '';
        }

        $args = array(
            'size' => $size,
            'color' => 'currentColor',
            'stroke' => 2,
            'class' => 'fbc-sep-icon',
        );

        if (class_exists('WIF_Icons')) {
            $parsed = WIF_Icons::parse($value);
            $svg = WIF_Icons::svg($parsed, $args);
        } else {
            $svg = wpicons_get_icon(self::icon_ref($value), $args);
        }

        if (!is_string($svg) || stripos($svg, '<svg') === false) {
            return '';
        }

        if (wp_style_is('wpicons', 'registered')) {
            wp_enqueue_style('wpicons');
        }

        return $svg;
    }

    private static function icon_ref(string $value): string
    {
        $value = self::sanitize_icon_value($value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, ':') !== false) {
            return $value;
        }

        if (strpos($value, '/') !== false) {
            $parts = explode('/', $value, 2);
            $library = $parts[0] !== '' ? $parts[0] : 'lucide';
            $name = isset($parts[1]) ? $parts[1] : '';

            return $name === '' ? '' : $library . ':' . $name;
        }

        return 'lucide:' . $value;
    }

    private static function preset_svg(string $name): string
    {
        $icons = array(
            'slash' => array(
                'class' => 'lucide lucide-slash',
                'body' => '<path d="M22 2 2 22"/>',
            ),
            'arrow' => array(
                'class' => 'lucide lucide-arrow-right',
                'body' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            ),
            'chevron' => array(
                'class' => 'lucide lucide-chevron-right',
                'body' => '<path d="m9 18 6-6-6-6"/>',
            ),
            'dot' => array(
                'class' => 'lucide lucide-dot',
                'body' => '<circle cx="12" cy="12" r="1"/>',
            ),
        );

        if (!isset($icons[$name])) {
            $name = 'chevron';
        }

        $icon = $icons[$name];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fbc-sep-icon ' . $icon['class'] . '" aria-hidden="true" focusable="false">' . $icon['body'] . '</svg>';
    }

    private static function home_svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fbc-home-icon" aria-hidden="true" focusable="false"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
    }

    /**
     * @param array<string, mixed> $parsed
     */
    private static function style_attr(array $parsed): string
    {
        $rules = array(
            '--fbc-font-size:' . (int) $parsed['font_size'] . 'px',
            '--fbc-gap:' . (int) $parsed['gap'] . 'px',
            '--fbc-icon-size:' . (int) $parsed['icon_size'] . 'px',
        );

        $map = array(
            'link_color' => '--fbc-link',
            'hover_color' => '--fbc-hover',
            'current_color' => '--fbc-current',
            'separator_color' => '--fbc-separator',
        );
        foreach ($map as $key => $var) {
            if (!empty($parsed[$key])) {
                $rules[] = $var . ':' . $parsed[$key];
            }
        }

        return implode(';', $rules);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function schema_html(array $items): string
    {
        if (!$items) {
            return '';
        }

        $elements = array();
        $position = 1;
        foreach ($items as $item) {
            $entry = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => (string) $item['label'],
            );
            if (!empty($item['url'])) {
                $entry['item'] = (string) $item['url'];
            }
            $elements[] = $entry;
            $position++;
        }

        $json = wp_json_encode(
            array(
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $elements,
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (!is_string($json) || $json === '') {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private static function enqueue_assets(): void
    {
        wp_enqueue_style('flatsome-breadcrumbs');
        wp_enqueue_script('flatsome-breadcrumbs');
    }

    private static function is_builder_preview(): bool
    {
        if (defined('UX_BUILDER_IFRAME') && UX_BUILDER_IFRAME) {
            return true;
        }

        return function_exists('ux_builder_is_iframe') && ux_builder_is_iframe();
    }

    private static function next_id(): string
    {
        static $seq = 0;
        $seq++;

        return $seq . '-' . substr(uniqid(), -5);
    }

    /**
     * @param mixed $value
     */
    private static function is_enabled($value): bool
    {
        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
    }

    /**
     * @param mixed $value
     */
    private static function sanitize_number($value, int $min, int $max, int $default): int
    {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9.-]/', '', $value);
        }
        if ($value === '' || $value === null || !is_numeric($value)) {
            return $default;
        }

        $number = (int) round((float) $value);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    private static function sanitize_icon_value(string $value): string
    {
        $value = trim(wp_strip_all_tags($value));
        if (strpos($value, 'wpicons:') === 0) {
            $value = substr($value, strlen('wpicons:'));
        }
        if ($value === '' || strlen($value) > 120) {
            return '';
        }
        if (preg_match('/^[a-z0-9_-]+([:\/][a-z0-9_-]+){0,3}$/i', $value) !== 1) {
            return '';
        }

        return $value;
    }

    /**
     * Hex, rgb/rgba, hsl/hsla, var(--token), and a short list of CSS color names.
     */
    public static function sanitize_color(string $color, string $fallback = ''): string
    {
        $color = trim($color);
        if ($color === '') {
            return $fallback;
        }

        if (preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            return $color;
        }

        if (preg_match('/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $color)) {
            return $color;
        }

        if (preg_match('/^hsla?\(\s*\d{1,3}(?:deg)?\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $color)) {
            return $color;
        }

        if (preg_match('/^var\(\s*--[a-zA-Z0-9_-]+\s*\)$/', $color)) {
            return $color;
        }

        if (preg_match('/^rgb\(\s*var\(\s*--[a-zA-Z0-9_-]+\s*\)\s*\)$/', $color)) {
            return $color;
        }

        $named = array(
            'transparent', 'currentcolor', 'inherit',
            'black', 'white', 'red', 'green', 'blue', 'yellow',
            'orange', 'purple', 'pink', 'gray', 'grey',
        );
        $lower = strtolower($color);
        if (in_array($lower, $named, true)) {
            return $lower === 'currentcolor' ? 'currentColor' : $lower;
        }

        return $fallback;
    }
}
