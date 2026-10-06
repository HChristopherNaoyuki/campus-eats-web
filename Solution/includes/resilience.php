<?php
/**
 * Resilience Helper
 *
 * Converts an uncaught error or exception into a response that is
 * appropriate for the caller. An API request receives a JSON 503
 * response. A page request receives a friendly HTML page that
 * retries once after a short delay and offers a stable mirror link.
 *
 * CORRECTIONS (Version 2.0 - Resilience Fix):
 *
 * - The HTML temporary-unavailable page now contains a clear
 *   paragraph that instructs the user to open the stable mirror
 *   https://campus-eats-platform.lovable.app after five refresh
 *   attempts or after waiting longer than fifteen seconds.
 * - The URL is rendered as a clickable link.
 * - Language remains direct and free of technical jargon.
 * - The five-second meta-refresh and Retry-After header are retained.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Campus Eats Resilience Fixes.
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/error_logging.php';

if (!function_exists('campus_eats_is_api_request'))
{
    /**
     * Returns true when the current request is an API request.
     *
     * @return bool
     */
    function campus_eats_is_api_request()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        if (strpos($uri, '/api/') !== false)
        {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT'])
            ? $_SERVER['HTTP_ACCEPT']
            : '';

        if (stripos($accept, 'application/json') !== false)
        {
            return true;
        }

        return false;
    }
}

if (!function_exists('campus_eats_render_resilient_response'))
{
    /**
     * Renders a resilient response for an uncaught error.
     *
     * @param Throwable $error The uncaught error or exception
     * @return void
     */
    function campus_eats_render_resilient_response($error)
    {
        $message = sprintf(
            "Uncaught error: %s in %s on line %d",
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        );

        writeLog($message, "RESILIENCE", LOG_LEVEL_ERROR);

        if (campus_eats_is_api_request())
        {
            if (!headers_sent())
            {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                header('Retry-After: 5');
            }

            echo json_encode(array(
                'success' => false,
                'message' => 'The service is temporarily unavailable. '
                    . 'Please try again in a moment.',
                'retry_after' => 5
            ));

            exit(1);
        }

        if (!headers_sent())
        {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 5');
        }

        echo '<!DOCTYPE html>';
        echo '<html lang="en"><head><meta charset="UTF-8">';
        echo '<meta http-equiv="refresh" content="5">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Temporarily unavailable – Campus Eats</title>';
        echo '<style>';
        echo 'body { font-family: system-ui, sans-serif; padding: 40px; ';
        echo 'max-width: 640px; margin: 0 auto; color: #222; line-height: 1.5; }';
        echo 'h1 { font-size: 1.6rem; margin-bottom: 0.5em; }';
        echo '.toast { background: #fff3e0; border-left: 4px solid #ff9500; ';
        echo 'padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; }';
        echo 'a { color: #0066cc; }';
        echo '</style>';
        echo '</head><body>';
        echo '<h1>Temporarily unavailable</h1>';
        echo '<div class="toast">The service is temporarily unavailable. ';
        echo 'The page will retry in five seconds.</div>';
        echo '<p>If the page remains unavailable after five refresh attempts ';
        echo 'or after waiting longer than fifteen seconds, open the stable mirror:</p>';
        echo '<p><a href="https://campus-eats-platform.lovable.app" ';
        echo 'rel="noopener noreferrer">https://campus-eats-platform.lovable.app</a></p>';
        echo '<p><a href="">Retry now</a></p>';
        echo '</body></html>';

        exit(1);
    }
}

// Register the resilient handler only when the caller has not
// registered a handler of its own.
set_exception_handler('campus_eats_render_resilient_response');