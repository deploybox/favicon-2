<?php

declare(strict_types=1);

// Set error reporting for development, remove in production
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- Configuration Constants ---
const DEFAULT_ICON_PATH = __DIR__ . '/cache/default.ico';
const CACHE_DIR = '/cache'; // Relative to __DIR__ for Vercel or absolute for local
const DEFAULT_TIMEOUT = 5; // Default timeout for external HTTP requests in seconds
const USER_AGENT_BASE = 'forkdo/favicon-2'; // Base for the User-Agent string

// --- Initialize Environment ---
/**
 * Defines the temporary path for caching.
 * On Vercel, it uses '/tmp'. Otherwise, it creates a 'cache' directory in the script's directory.
 */
define('TMP_PATH', getenv('VERCEL') ? '/tmp' : __DIR__ . CACHE_DIR);

// Ensure the cache directory exists
if (!is_dir(TMP_PATH) && !mkdir(TMP_PATH, 0775, true)) {
    error_log('Failed to create cache directory: ' . TMP_PATH);
    output_error_image(); // Output an error image if cache directory cannot be created
}

// --- Main Script Logic ---
try {
    $url = $_GET['url'] ?? '';

    if (empty($url)) {
        http_response_code(404);
        exit('URL not provided.');
    }

    $parsedUrl = parse_url($url);

    if ($parsedUrl === false || !isset($parsedUrl['host']) && !isset($parsedUrl['path'])) {
        output_default_image();
    }

    // Sanitize and reconstruct the base URL for consistency
    $host = $parsedUrl['host'] ?? ($parsedUrl['path'] ?? '');
    $scheme = $parsedUrl['scheme'] ?? 'http';
    $port = $parsedUrl['port'] ?? null;

    // Reconstruct base URL for validation and subsequent operations
    $baseUrl = $scheme . '://' . $host . ($port ? ':' . $port : '');

    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        output_default_image();
    }

    // Check cache first
    if (check_cache($host)) {
        exit; // If cache hit, output and exit
    }

    // Attempt to get the favorite icon from the HTML
    $icon = get_favorite_icon($baseUrl);

    // If no icon found from HTML, try default /favicon.ico
    if (empty($icon)) {
        $defaultIconUrl = $baseUrl . '/favicon.ico';
        if (get_url_content($defaultIconUrl, DEFAULT_TIMEOUT, true, true)) {
            output_image($defaultIconUrl, $host);
        }
        output_default_image(); // Fallback to default if /favicon.ico also fails
    }

    // Resolve relative or protocol-relative icon URLs
    $iconUrl = $icon;
    if (str_starts_with($icon, '//')) {
        $iconUrl = $scheme . ':' . $icon;
    } elseif (!str_starts_with($icon, 'http')) {
        // Handle paths relative to the root or current directory
        if (!str_starts_with($icon, '/')) {
            // Append to base URL if it's a relative path without leading slash
            $path = rtrim($parsedUrl['path'] ?? '', '/');
            $iconUrl = $baseUrl . ($path ? $path . '/' : '/') . $icon;
        } else {
            // Prepend base URL for root-relative paths
            $iconUrl = $baseUrl . $icon;
        }
    }

    output_image($iconUrl, $host);

} catch (Throwable $e) {
    // Catch any unexpected errors and log them
    error_log('An unexpected error occurred: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
    output_error_image();
}

// --- Helper Functions ---

/**
 * Outputs a predefined default favicon.
 *
 * @return void
 */
function output_default_image(): void
{
    header('Content-type: image/x-icon');
    if (file_exists(DEFAULT_ICON_PATH)) {
        $content = file_get_contents(DEFAULT_ICON_PATH);
        exit($content);
    }
    // Fallback if default.ico is missing
    http_response_code(500);
    exit('Default icon not found.');
}

/**
 * Outputs a generic error image (can be same as default or a distinct one).
 * This function is called when critical errors occur, like cache directory creation failure.
 *
 * @return void
 */
function output_error_image(): void
{
    header('Content-type: image/x-icon');
    // Consider having a separate, very simple, hardcoded error image or falling back to default.
    // For simplicity, reusing default for now.
    if (file_exists(DEFAULT_ICON_PATH)) {
        $content = file_get_contents(DEFAULT_ICON_PATH);
        http_response_code(500);
        exit($content);
    }
    http_response_code(500);
    exit('Internal server error.');
}

/**
 * Checks if a cached version of the favicon exists and outputs it if found and not refreshed.
 *
 * @param string $host The host of the URL.
 * @return bool True if cached image was served, false otherwise.
 */
function check_cache(string $host): bool
{
    $refresh = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($refresh) {
        return false;
    }

    $cacheFile = TMP_PATH . '/' . md5($host) . '.ico'; // Add .ico extension for clarity
    if (file_exists($cacheFile)) {
        $content = file_get_contents($cacheFile);
        if ($content !== false && !empty($content)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_buffer($finfo, $content);
            finfo_close($finfo);

            // Set appropriate content type based on detected MIME type
            if ($fileType === 'image/svg+xml') {
                header('Content-type: image/svg+xml');
            } elseif ($fileType === 'image/x-icon') {
                header('Content-type: image/x-icon');
            } else {
                // If it's an image but not svg or x-icon, attempt to serve with its type
                if (str_starts_with($fileType, 'image/')) {
                    header('Content-type: ' . $fileType);
                } else {
                    // If not a recognized image type, treat as corrupt or invalid cache
                    unlink($cacheFile); // Delete invalid cache file
                    return false;
                }
            }

            header('X-Icon-Cache: Hit');
            header('Content-Length: ' . strlen($content)); // Add Content-Length header
            exit($content);
        } else {
            // Cache file is empty or corrupted, delete it
            unlink($cacheFile);
        }
    }

    return false;
}

/**
 * Outputs the image content and caches it.
 *
 * @param string $url The URL of the image to output.
 * @param string $host The host for caching purposes.
 * @return void
 */
function output_image(string $url, string $host): void
{
    // Determine content type based on extension
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        header('Content-type: image/svg+xml');
    } elseif (in_array($ext, ['ico', 'cur'])) {
        header('Content-type: image/x-icon');
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) { // Add common image types
        header('Content-type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
    } else {
        // Fallback or attempt to determine from content if extension is unknown
        // For now, default to image/x-icon if extension is not common or specific
        header('Content-type: image/x-icon');
    }

    $content = get_url_content($url, DEFAULT_TIMEOUT, true, false);

    if (empty($content)) {
        output_default_image(); // Fallback if content is empty
    }

    $cacheFile = TMP_PATH . '/' . md5($host) . '.ico'; // Use .ico extension for cached files
    if (file_put_contents($cacheFile, $content) === false) {
        error_log('Failed to write to cache file: ' . $cacheFile);
    }

    header('Content-Length: ' . strlen($content)); // Add Content-Length header
    exit($content);
}

/**
 * Fetches content from a URL using cURL.
 *
 * @param string $url The URL to fetch.
 * @param int $timeout The timeout in seconds.
 * @param bool $followRedirects Whether to follow HTTP redirects.
 * @param bool $checkExists If true, only returns true if HTTP status is 200, otherwise returns content.
 * @return string|bool The content of the URL, true/false if checkExists is true, or false on failure.
 */
function get_url_content(string $url, int $timeout = DEFAULT_TIMEOUT, bool $followRedirects = true, bool $checkExists = false): string|bool
{
    $ch = curl_init();

    // Construct a more robust User-Agent
    $userAgent = sprintf(
        'Mozilla/5.0 (compatible; %s/%s; +%s)',
        $_SERVER['HTTP_HOST'] ?? 'UnknownHost',
        USER_AGENT_BASE,
        $url
    );

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get content as string
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); // Connection timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider setting to true and providing CA path in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Consider setting to 2 in production
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Limit redirects to prevent loops

    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($output === false) {
        error_log('cURL error for ' . $url . ': ' . $curlError);
        return false;
    }

    if ($checkExists) {
        return $httpCode >= 200 && $httpCode < 300; // Check for 2xx success codes
    }

    // Only return content for successful HTTP codes
    if ($httpCode >= 200 && $httpCode < 300) {
        return $output;
    }

    return false;
}

/**
 * Extracts the favorite icon URL from a given webpage's HTML.
 *
 * @param string $url The URL of the webpage to parse.
 * @return string The URL of the favicon, or an empty string if not found.
 */
function get_favorite_icon(string $url): string
{
    $content = get_url_content($url, DEFAULT_TIMEOUT, true, false);

    if (empty($content)) {
        return '';
    }

    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    @$dom->loadHTML($content);

    $xpath = new DOMXPath($dom);

    // Prioritize 'shortcut icon' over 'icon'
    $nodes = $xpath->query('//link[@rel="shortcut icon"] | //link[@rel="icon"]');

    if ($nodes->length > 0) {
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            if (!empty($href)) {
                // Prioritize SVG if multiple icons are found (e.g., in different sizes)
                if (str_ends_with(strtolower($href), '.svg')) {
                    return $href;
                }
                // Return the first valid icon found, preferring 'shortcut icon' due to query order
                return $href;
            }
        }
    }

    return '';
}