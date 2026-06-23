<?php

/**
 * Plugin Name: DDEV remote media
 * Description: Rewrites upload URLs for DDEV remote media proxy (nginx fetches from production).
 */

if (!defined('ABSPATH') || getenv('IS_DDEV_PROJECT') !== 'true') {
    return;
}

add_action('template_redirect', 'ddev_remote_media_start_buffer', 0);
add_filter('wp_get_attachment_url', 'ddev_remote_media_rewrite', 10, 1);
add_filter('wp_get_attachment_image_src', 'ddev_remote_media_filter_image_src', 10, 1);
add_filter('wp_calculate_image_srcset', 'ddev_remote_media_filter_srcset', 10, 1);
add_filter('the_content', 'ddev_remote_media_rewrite', 20, 1);
add_filter('widget_text', 'ddev_remote_media_rewrite', 20, 1);
add_filter('widget_text_content', 'ddev_remote_media_rewrite', 20, 1);
add_filter('theme_mods_municipio', 'ddev_remote_media_rewrite_theme_mods', 10, 1);
add_filter('option_theme_mods_municipio', 'ddev_remote_media_rewrite_theme_mods', 10, 1);

function ddev_remote_media_start_buffer(): void
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }

    ob_start('ddev_remote_media_rewrite');
}

function ddev_remote_media_local_app_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $prefix = trailingslashit(home_url('/app/uploads'));
    }
    return $prefix;
}

function ddev_remote_media_local_wp_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $uploadDir = wp_upload_dir();
        $prefix = trailingslashit($uploadDir['baseurl']);
    }
    return $prefix;
}

/**
 * @return string[]
 */
function ddev_remote_media_remote_hosts(): array
{
    static $hosts = null;
    if ($hosts !== null) {
        return $hosts;
    }

    $project = getenv('DDEV_SITENAME') ?: 'eslov-se-new';
    $hosts = array_unique([
        'eslov.se',
        'www.eslov.se',
        'storatorg.eslov.w8e.se',
        'eslov.dev',
        'www.eslov.dev',
        "{$project}.ddev.site",
        "{$project}.ddev.local",
    ]);

    return $hosts;
}

/**
 * @return array{string[], string[]}
 */
function ddev_remote_media_replace_pairs(): array
{
    static $pairs = null;
    if ($pairs !== null) {
        return $pairs;
    }

    $search = [];
    $replace = [];

    foreach (ddev_remote_media_remote_hosts() as $host) {
        foreach (['https', 'http'] as $scheme) {
            $search[] = "{$scheme}://{$host}/app/uploads/";
            $replace[] = ddev_remote_media_local_app_prefix();

            $search[] = "{$scheme}://{$host}/wp-content/uploads/";
            $replace[] = ddev_remote_media_local_wp_prefix();
        }
    }

    $pairs = [$search, $replace];
    return $pairs;
}

function ddev_remote_media_rewrite(string $value): string
{
    [$search, $replace] = ddev_remote_media_replace_pairs();
    $value = str_replace($search, $replace, $value);

    $value = preg_replace(
        '#https?://[a-z0-9.-]+\.eslov\.se/app/uploads/#i',
        ddev_remote_media_local_app_prefix(),
        $value
    ) ?? $value;
    $value = preg_replace(
        '#https?://[a-z0-9.-]+\.eslov\.se/wp-content/uploads/#i',
        ddev_remote_media_local_wp_prefix(),
        $value
    ) ?? $value;
    $value = preg_replace(
        '#https?://[a-z0-9.-]+\.eslov\.dev/wp-content/uploads/#i',
        ddev_remote_media_local_wp_prefix(),
        $value
    ) ?? $value;
    $value = preg_replace(
        '#https?://[a-z0-9.-]+\.eslov\.dev/app/uploads/#i',
        ddev_remote_media_local_app_prefix(),
        $value
    ) ?? $value;

    return $value;
}

/**
 * @param mixed $mods
 * @return mixed
 */
function ddev_remote_media_rewrite_theme_mods($mods)
{
    if (!is_array($mods)) {
        return $mods;
    }

    foreach ($mods as $key => $value) {
        if (is_string($value)) {
            $mods[$key] = ddev_remote_media_rewrite($value);
        }
    }

    return $mods;
}

/**
 * @param array|false $image
 * @return array|false
 */
function ddev_remote_media_filter_image_src($image)
{
    if (!is_array($image) || empty($image[0])) {
        return $image;
    }
    $image[0] = ddev_remote_media_rewrite($image[0]);
    return $image;
}

/**
 * @param array|false $sources
 * @return array|false
 */
function ddev_remote_media_filter_srcset($sources)
{
    if (!is_array($sources)) {
        return $sources;
    }
    foreach ($sources as $width => $source) {
        if (!empty($source['url'])) {
            $sources[$width]['url'] = ddev_remote_media_rewrite($source['url']);
        }
    }
    return $sources;
}
