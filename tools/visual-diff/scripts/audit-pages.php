<?php
/**
 * Data-driven page picker for visual diff.
 * Run: ddev wp eval-file tools/visual-diff/scripts/audit-pages.php
 */

if (!defined('ABSPATH')) {
    exit(1);
}

function eslov_visual_prod_base(int $blogId): string
{
    switch_to_blog($blogId);
    $host = (string) parse_url(home_url(), PHP_URL_HOST);
    restore_current_blog();

    if ($blogId === 1) {
        return 'https://eslov.se';
    }

    return 'https://' . str_replace('.eslov-se-new.ddev.site', '.eslov.se', $host);
}

function eslov_visual_target_base(int $blogId): string
{
    switch_to_blog($blogId);
    $url = trailingslashit(home_url());
    restore_current_blog();

    return $url;
}

function eslov_visual_page_entry(int $blogId, int $postId, string $label, array $tags, string $notes, ?string $id = null): array
{
    switch_to_blog($blogId);
    $path = wp_make_link_relative(get_permalink($postId)) ?: '/';
    restore_current_blog();

    $slug = $id ?? ('auto-' . $blogId . '-' . $postId);

    return [
        'id' => $slug,
        'label' => $label,
        'blog_id' => $blogId,
        'post_id' => $postId,
        'tags' => $tags,
        'path' => $path,
        'reference' => rtrim(eslov_visual_prod_base($blogId), '/') . $path,
        'target' => rtrim(eslov_visual_target_base($blogId), '/') . $path,
        'notes' => $notes,
    ];
}

function eslov_visual_path_entry(int $blogId, string $id, string $label, string $path, array $tags, string $notes): array
{
    return [
        'id' => $id,
        'label' => $label,
        'blog_id' => $blogId,
        'tags' => $tags,
        'path' => $path,
        'reference' => rtrim(eslov_visual_prod_base($blogId), '/') . $path,
        'target' => rtrim(eslov_visual_target_base($blogId), '/') . $path,
        'notes' => $notes,
    ];
}

function eslov_visual_find_page_with_module(int $blogId, string $modSlug, array $requiredAreas = [], ?string $template = null): ?int
{
    switch_to_blog($blogId);

    foreach (get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']) as $postId) {
        $modules = get_post_meta($postId, 'modularity-modules', true);
        if (!is_array($modules) || $modules === []) {
            continue;
        }

        foreach ($requiredAreas as $area) {
            if (empty($modules[$area])) {
                continue 2;
            }
        }

        if ($template !== null && get_page_template_slug($postId) !== $template) {
            continue;
        }

        $found = false;
        foreach ($modules as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['postid'])) {
                    continue;
                }
                if (get_post_type((int) $item['postid']) === $modSlug) {
                    $found = true;
                    break 2;
                }
            }
        }

        if ($found) {
            restore_current_blog();
            return (int) $postId;
        }
    }

    restore_current_blog();

    return null;
}

$pages = [];

$moduleSamples = [
    ['mod-navigation', ['top-sidebar'], null, 'layout', 'Top-sidebar band + mod-navigation buttons'],
    ['mod-navigation', ['content-area', 'right-sidebar'], null, 'layout', 'Content area + right-sidebar navigation'],
    ['mod-navigation', ['content-area'], null, 'module', 'mod-navigation in content (cards/children)'],
    ['mod-hero', ['slider-area'], null, 'module', 'Hero in slider-area'],
    ['mod-posts', ['content-area'], null, 'module', 'mod-posts in default template'],
    ['mod-posts', ['content-area'], 'one-page.blade.php', 'template', 'One-page template + mod-posts + hero'],
    ['mod-manualinput', ['content-area'], null, 'module', 'mod-manualinput repeater cards'],
    ['mod-contacts', ['right-sidebar', 'slider-area'], null, 'module', 'mod-contacts beside hero'],
    ['mod-section-split', ['content-area', 'slider-area'], null, 'module', 'mod-section-split campaign layout'],
    ['mod-section-card', ['content-area'], null, 'module', 'mod-section-card band'],
    ['mod-timeline', ['content-area'], null, 'module', 'mod-timeline'],
    ['mod-inlaylist', ['content-area'], null, 'module', 'mod-inlaylist accordion'],
    ['mod-table', ['content-area'], null, 'module', 'mod-table'],
    ['mod-fileslist', ['content-area'], null, 'module', 'mod-fileslist downloads'],
    ['mod-notice', ['content-area'], null, 'module', 'mod-notice callout'],
    ['mod-iframe', ['content-area'], null, 'module', 'mod-iframe embed'],
    ['mod-form', ['content-area'], null, 'module', 'mod-form'],
    ['mod-event', ['content-area'], null, 'module', 'mod-event listing block on page'],
    ['mod-text', ['right-sidebar'], null, 'module', 'mod-text in sidebar'],
    ['mod-text', ['content-area'], null, 'module', 'mod-text in content area'],
    ['mod-image', ['content-area'], null, 'module', 'mod-image'],
];

foreach ($moduleSamples as [$modSlug, $areas, $template, $tag, $note]) {
    $postId = eslov_visual_find_page_with_module(1, $modSlug, $areas, $template);
    if (!$postId) {
        continue;
    }
    switch_to_blog(1);
    $title = get_the_title($postId);
    restore_current_blog();
    $pages[] = eslov_visual_page_entry(1, $postId, $title, [$tag, $modSlug], $note);
}

$fixedMain = [
    eslov_visual_path_entry(1, 'home', 'Homepage', '/', ['layout', 'module'], 'mod-navigation grid launcher + footer bar'),
    eslov_visual_path_entry(1, 'tree-highlighted', 'Tree highlighted navigation', '/omsorg-stod/akut-hjalp/', ['module', 'mod-navigation'], 'mod-navigation tree highlighted format'),
    eslov_visual_path_entry(1, 'section-child-pills', 'Child page pills below title', '/bygga-bo-miljo/kartor-adresser-och-matning/', ['shim', 'layout'], 'ChildPageLinksBelowTitle shim'),
    eslov_visual_path_entry(1, 'search', 'Search results', '/?s=skola', ['utility'], 'Dynamic search results — compare chrome not hit list'),
];

$pages = array_merge($fixedMain, $pages);

$cptArchives = [
    'nyheter' => 'News archive',
    'event' => 'Events archive (main site)',
    'job-listing' => 'Job listings archive',
    'place' => 'Places archive',
    'school' => 'Schools archive',
    'project' => 'Projects archive',
];

foreach ($cptArchives as $postType => $label) {
    switch_to_blog(1);
    $archive = get_post_type_archive_link($postType);
    if ($archive) {
        $path = wp_make_link_relative($archive) ?: '/';
        $pages[] = eslov_visual_path_entry(1, 'main-' . str_replace('-', '_', $postType) . '-archive', $label, $path, ['cpt', 'archive', $postType], 'CPT archive template: ' . $postType);
    }
    restore_current_blog();
}

switch_to_blog(1);
$newsWithTerm = (int) $GLOBALS['wpdb']->get_var(
    "SELECT p.ID FROM {$GLOBALS['wpdb']->posts} p
     INNER JOIN {$GLOBALS['wpdb']->term_relationships} tr ON tr.object_id = p.ID
     INNER JOIN {$GLOBALS['wpdb']->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'amne'
     WHERE p.post_type = 'nyheter' AND p.post_status = 'publish' LIMIT 1"
);
if ($newsWithTerm) {
    $pages[] = eslov_visual_page_entry(1, $newsWithTerm, 'News single with amne taglist', ['cpt', 'single', 'shim'], 'TaxonomyTaglist shim');
}

foreach (['event', 'job-listing', 'place'] as $postType) {
    $posts = get_posts(['post_type' => $postType, 'post_status' => 'publish', 'posts_per_page' => 1]);
    if ($posts !== []) {
        $pages[] = eslov_visual_page_entry(1, (int) $posts[0]->ID, ucfirst($postType) . ' single', ['cpt', 'single', $postType], 'Singular schema template: ' . $postType);
    }
}
restore_current_blog();

$subsites = [
    3 => 'medborgarhuset',
    4 => 'foretag',
    7 => 'programforoffentligmiljo',
    8 => 'varumarkesmanual',
    10 => 'storatorg',
    11 => 'sommarieslov',
    12 => 'historia',
    13 => 'eslovsfesten',
    14 => 'valarbetare',
    15 => 'utveckla',
    16 => 'plus',
];

foreach ($subsites as $blogId => $slug) {
    switch_to_blog($blogId);
    $name = get_bloginfo('name');
    restore_current_blog();

    $pages[] = eslov_visual_path_entry($blogId, 'sub' . $blogId . '-home', 'Subsite home: ' . $name, '/', ['subsite', 'layout'], 'Subsite homepage tokens/header/footer');

    foreach (['mod-posts', 'mod-navigation', 'mod-hero', 'mod-event'] as $modSlug) {
        $postId = eslov_visual_find_page_with_module($blogId, $modSlug);
        if (!$postId) {
            continue;
        }
        switch_to_blog($blogId);
        $title = get_the_title($postId);
        restore_current_blog();
        $pages[] = eslov_visual_page_entry(
            $blogId,
            $postId,
            $name . ' — ' . $modSlug,
            ['subsite', 'module', $modSlug],
            'Subsite example for ' . $modSlug,
            'sub' . $blogId . '-' . str_replace('mod-', '', $modSlug)
        );
    }

    switch_to_blog($blogId);
    $eventArchive = get_post_type_archive_link('event');
    if ($eventArchive) {
        $path = wp_make_link_relative($eventArchive) ?: '/';
        $pages[] = eslov_visual_path_entry($blogId, 'sub' . $blogId . '-events', $name . ' — events archive', $path, ['subsite', 'cpt', 'event'], 'Subsite event archive');
    }
    restore_current_blog();
}

$seen = [];
$unique = [];
foreach ($pages as $page) {
    if (isset($seen[$page['reference']])) {
        continue;
    }
    $seen[$page['reference']] = true;
    $unique[] = $page;
}

$coverage = [
    'generated_at' => gmdate('c'),
    'page_count' => count($unique),
    'module_types_on_main' => [],
    'subsites' => array_keys($subsites),
    'tags' => [],
];

foreach ($unique as $page) {
    foreach ($page['tags'] as $tag) {
        $coverage['tags'][$tag] = ($coverage['tags'][$tag] ?? 0) + 1;
    }
    foreach ($page['tags'] as $tag) {
        if (str_starts_with($tag, 'mod-')) {
            $coverage['module_types_on_main'][$tag] = true;
        }
    }
}

$root = dirname(__DIR__);
$pagesJson = [
    '$schema' => './pages.schema.json',
    'viewports' => [
        ['id' => 'desktop', 'width' => 1280, 'height' => 900],
        ['id' => 'mobile', 'width' => 390, 'height' => 844],
    ],
    'defaults' => [
        'waitUntil' => 'networkidle',
        'postLoadDelayMs' => 1500,
        'fullPage' => true,
        'threshold' => 0.1,
    ],
    'pages' => array_map(static function (array $page): array {
        return [
            'id' => $page['id'],
            'label' => $page['label'],
            'blog_id' => $page['blog_id'],
            'tags' => $page['tags'],
            'matrixRefs' => $page['matrixRefs'] ?? [],
            'reference' => $page['reference'],
            'target' => $page['target'],
            'notes' => $page['notes'],
        ];
    }, $unique),
];

file_put_contents($root . '/pages.generated.json', json_encode($pagesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
file_put_contents($root . '/pages-audit.json', json_encode(['coverage' => $coverage, 'pages' => $unique], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

WP_CLI::success('Wrote ' . count($unique) . ' pages to tools/visual-diff/pages.generated.json');
