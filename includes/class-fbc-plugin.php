<?php
/**
 * Bootstrap: textdomain, Flatsome notice, and the UX element.
 *
 * @package Flatsome_Breadcrumbs
 */

if (!defined('ABSPATH')) {
    exit;
}

class FBC_Plugin
{
    public static function init(): void
    {
        add_action('init', array(__CLASS__, 'load_textdomain'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        FBC_Element::init();
    }

    public static function load_textdomain(): void
    {
        load_plugin_textdomain(
            'flatsome-breadcrumbs',
            false,
            dirname(plugin_basename(FBC_FILE)) . '/languages'
        );
    }

    public static function has_flatsome(): bool
    {
        if (function_exists('flatsome_option') || class_exists('Flatsome_Default', false) || defined('FLATSOME_VERSION')) {
            return true;
        }

        $theme = wp_get_theme();

        return $theme->get_template() === 'flatsome';
    }

    public static function admin_notice(): void
    {
        if (self::has_flatsome() || !current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'Flatsome Breadcrumbs needs the Flatsome theme for the UX Builder element. The shortcode still renders, and the element is registered when Flatsome is active.',
            'flatsome-breadcrumbs'
        );
        echo '</p></div>';
    }
}
