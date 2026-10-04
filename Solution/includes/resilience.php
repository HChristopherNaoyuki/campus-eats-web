<?php
/**
 * Resilience Helper
 *
 * Converts an uncaught error or exception into a response that is
 * appropriate for the caller. An API request receives a JSON 503
 * response. A page request receives a friendly HTML page that
 * retries once after a short delay.
 *
 * The helper is not wired into constants.php by this file. The wiring
 * is a separate change. When the helper is not loaded, an uncaught
 * exception reaches the default handler in error_logging.php. That
 * handler already produces a 500 response and logs the error.
 *
 * CORRECTIONS (Version 1.0 - Audit Continuation):
 * - Initial implementation.
 * - The JSON response uses the 503 status code so a client can
 *   distinguish a transient outage from a permanent failure.
 * - The HTML response includes a meta refresh tag with a five-second
 *   delay. The browser retries the same URL. A single retry is
 *   sufficient for a transient outage. A longer outage is reported
 *   through the same page on each retry.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 1.0
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
     * The check examines the request URI and the Accept header. A
     * request whose path begins with "/api/" is an API request. A
     * request whose Accept header prefers JSON is an API request.
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
        echo '<title>Temporarily unavailable</title>';
        echo '<style>';
        echo 'body { font-family: sans-serif; padding: 40px; ';
        echo 'max-width: 600px; margin: 0 auto; color: #333; }';
        echo 'h1 { font-size: 1.5rem; }';
        echo '.toast { background: #fff3e0; border-left: 4px solid #ff9500; ';
        echo 'padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }';
        echo '</style>';
        echo '</head><body>';
        echo '<h1>Temporarily unavailable</h1>';
        echo '<div class="toast">The service is temporarily unavailable. ';
        echo 'The page will retry in five seconds.</div>';
        echo '<p><a href="">Retry now</a></p>';
        echo '</body></html>';

        exit(1);
    }
}

// Register the resilient handler only when the caller has not
// registered a handler of its own. The default handler in
// error_logging.php is replaced by this one when the resilience
// helper is loaded.
set_exception_handler('campus_eats_render_resilient_response');