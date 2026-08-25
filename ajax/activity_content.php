<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

function format_edukav_activity_content_error(string $message, int $code = 400, array $extra = []): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => false,
        'title' => '',
        'secondaryHtml' => '',
        'mainHtml' => '',
        'bodyClasses' => [],
        'scripts' => [],
        'timings' => [],
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'title' => '',
        'secondaryHtml' => '',
        'mainHtml' => '',
        'bodyClasses' => [],
        'scripts' => [],
        'timings' => [],
        'message' => $error['message'] ?? 'Unexpected server error.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function format_edukav_cookie_header(): string {
    $pairs = [];
    foreach ($_COOKIE as $name => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }
    return implode('; ', $pairs);
}

function format_edukav_clean_text(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function format_edukav_node_html(DOMDocument $doc, DOMNode $node): string {
    if ($node->nodeName === 'body' || format_edukav_is_shell_wrapper($node)) {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= format_edukav_node_html($doc, $child);
        }
        return $html;
    }

    return $doc->saveHTML($node) ?: '';
}

function format_edukav_is_shell_wrapper(DOMNode $node): bool {
    if (!$node instanceof DOMElement) {
        return false;
    }

    $shellids = [
        'topofscroll',
        'page',
        'page-content',
        'region-main-box',
        'region-main',
    ];

    $id = strtolower(trim($node->getAttribute('id')));
    return $id !== '' && in_array($id, $shellids, true);
}

function format_edukav_remove_nodes(DOMXPath $xpath, DOMNode $context, array $queries): void {
    foreach ($queries as $query) {
        $nodes = $xpath->query($query, $context);
        if (!$nodes instanceof DOMNodeList || $nodes->length === 0) {
            continue;
        }

        $remove = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }
}

function format_edukav_find_first_node(DOMXPath $xpath, array $queries): ?DOMNode {
    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node instanceof DOMNode) {
                return $node;
            }
        }
    }

    return null;
}

function format_edukav_extract_title(DOMXPath $xpath, DOMNode $root, string $fallback): string {
    if ($root instanceof DOMElement && $root->hasAttribute('data-activityname')) {
        $title = format_edukav_clean_text($root->getAttribute('data-activityname'));
        if ($title !== '') {
            return $title;
        }
    }

    $node = format_edukav_find_first_node($xpath, [
        './/*[@data-activityname]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " instancename ")]',
        './/h1',
        './/h2',
        './/title',
    ]);

    if ($node instanceof DOMElement && $node->hasAttribute('data-activityname')) {
        $title = format_edukav_clean_text($node->getAttribute('data-activityname'));
        if ($title !== '') {
            return $title;
        }
    }

    if ($node instanceof DOMNode) {
        $title = format_edukav_clean_text($node->textContent ?? '');
        if ($title !== '') {
            return $title;
        }
    }

    $title = format_edukav_clean_text($fallback);
    return $title !== '' ? $title : get_string('activity');
}

function format_edukav_fetch_remote_page(string $url): array {
    $start = microtime(true);
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIE => format_edukav_cookie_header(),
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveurl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return [
        'body' => is_string($body) ? $body : '',
        'error' => $error,
        'status' => $status,
        'effectiveurl' => $effectiveurl,
        'elapsedms' => round((microtime(true) - $start) * 1000, 2),
    ];
}

function format_edukav_extract_page_payload(string $html, string $fallbacktitle): array {
    $started = microtime(true);
    libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = false;
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

    $xpath = new DOMXPath($doc);
    $body = $xpath->query('//body')->item(0);
    if (!$body instanceof DOMNode) {
        return [
            'title' => format_edukav_clean_text($fallbacktitle),
            'secondaryHtml' => '',
            'mainHtml' => '',
            'bodyClasses' => [],
            'scripts' => [],
            'parseMs' => round((microtime(true) - $started) * 1000, 2),
        ];
    }

    $selected = format_edukav_find_first_node($xpath, [
        '//*[@id="topofscroll"]',
        '//*[@role="main"]',
        '//*[@id="region-main"]',
        '//*[@id="page-content"]',
    ]);

    if (!$selected instanceof DOMNode) {
        $selected = $body;
    }

    format_edukav_remove_nodes($xpath, $selected, [
        './/*[@id="page-header"]',
        './/*[@id="page-navbar"]',
        './/*[@id="page-footer"]',
        './/*[@id="nav-drawer"]',
        './/*[@id="secondary-navigation"]',
        './/*[@id="region-main-settings-menu"]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " secondary-navigation ")]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " page-context-header ")]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " breadcrumb ")]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " navbar ")]',
    ]);

    $scripts = [];
    $scriptnodes = $xpath->query('//script');
    if ($scriptnodes instanceof DOMNodeList) {
        foreach (iterator_to_array($scriptnodes) as $scriptnode) {
            if ($scriptnode instanceof DOMNode) {
                $scripts[] = $doc->saveHTML($scriptnode) ?: '';
            }
        }
    }

    $bodyclasses = [];
    if ($body instanceof DOMElement && $body->hasAttribute('class')) {
        $classes = preg_split('/\s+/', trim($body->getAttribute('class'))) ?: [];
        $bodyclasses = array_values(array_filter(array_unique(array_map('trim', $classes))));
    }

    return [
        'title' => format_edukav_extract_title($xpath, $selected, $fallbacktitle),
        'secondaryHtml' => '',
        'mainHtml' => format_edukav_node_html($doc, $selected),
        'bodyClasses' => $bodyclasses,
        'scripts' => $scripts,
        'parseMs' => round((microtime(true) - $started) * 1000, 2),
    ];
}

$cmid = required_param('cmid', PARAM_INT);
$sesskeyparam = required_param('sesskey', PARAM_ALPHANUMEXT);
if (!confirm_sesskey($sesskeyparam)) {
    format_edukav_activity_content_error('Sesión inválida.', 403);
}

try {
    $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
    $modulename = $DB->get_field('modules', 'name', ['id' => $cmrecord->module], MUST_EXIST);
    $course = get_course($cmrecord->course);
    $cm = get_coursemodule_from_id($modulename, $cmid, $course->id, false, MUST_EXIST);

    require_login($course, false, $cm);

    $cache = cache::make('format_edukav', 'activitycontent');
    $lang = current_language() ?: 'default';
    $cachekey = sha1(implode('|', [
        $course->id,
        $cmid,
        $USER->id,
        $lang,
    ]));

    $cached = $cache->get($cachekey);
    if (is_array($cached) && !empty($cached['success'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        \core\session\manager::write_close();
    }

    $activityurl = (new \moodle_url('/mod/' . $modulename . '/view.php', ['id' => $cmid]))->out(false);

    $fetchstart = microtime(true);
    $remote = format_edukav_fetch_remote_page($activityurl);
    if ($remote['error'] !== '') {
        format_edukav_activity_content_error('No fue posible cargar la actividad.', 502, [
            'timings' => [
                'fetchMs' => $remote['elapsedms'],
                'totalMs' => round((microtime(true) - $fetchstart) * 1000, 2),
            ],
            'message' => $remote['error'],
        ]);
    }

    if ($remote['status'] < 200 || $remote['status'] >= 400) {
        format_edukav_activity_content_error('La actividad respondió con un estado inválido.', 502, [
            'timings' => [
                'fetchMs' => $remote['elapsedms'],
                'totalMs' => round((microtime(true) - $fetchstart) * 1000, 2),
            ],
            'message' => 'HTTP ' . $remote['status'],
        ]);
    }

    $payload = format_edukav_extract_page_payload($remote['body'], $cm->name ?? get_string('activity'));
    $response = [
        'success' => true,
        'title' => $payload['title'],
        'secondaryHtml' => $payload['secondaryHtml'],
        'mainHtml' => $payload['mainHtml'],
        'bodyClasses' => $payload['bodyClasses'],
        'scripts' => $payload['scripts'],
        'timings' => [
            'fetchMs' => $remote['elapsedms'],
            'parseMs' => $payload['parseMs'],
            'totalMs' => round((microtime(true) - $fetchstart) * 1000, 2),
        ],
    ];

    $cache->set($cachekey, $response);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    format_edukav_activity_content_error(
        'No fue posible cargar la actividad.',
        500,
        [
            'message' => $e->getMessage(),
        ]
    );
}
