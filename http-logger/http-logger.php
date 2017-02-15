<?php
if(!function_exists('http_get_request_headers'))
{
    function http_get_request_headers()
    {
        $requestHeader = [];
        if(count($_SERVER))
        {
            foreach ($_SERVER as $key => $headerLine)
            {
                // @see https://de.wikipedia.org/wiki/Liste_der_HTTP-Headerfelder
                if (
                    stripos($key, 'User-Agent') === 0
                    || (
                        stripos($key, 'HTTP_') === 0
                        && stripos($key, 'HTTP_HOST') !== 0
                        && stripos($key, 'HTTP_CONNECTION') !== 0
                    )
                    || stripos($key, 'X-') === 0
                    || stripos($key, 'Cache-') === 0
                    || stripos($key, 'Expire') === 0
                    || stripos($key, 'ETag') === 0
                    || stripos($key, 'Pragma') === 0
                    || stripos($key, 'If-') === 0
                    || stripos($key, 'Expect') === 0
                    || stripos($key, 'Date') === 0
                    || stripos($key, 'Content-') === 0
                    || stripos($key, 'Cookie') === 0
                    || stripos($key, 'Referer') === 0
                    || stripos($key, 'Range') === 0
                    || stripos($key, 'Content-') === 0
                    || (stripos($key, 'Accept') === 0 && stripos($key, 'Accept-Encoding') !== 0)
                    || stripos($key, 'Trailer') === 0
                    || stripos($key, 'Max-Forwards') === 0
                    || stripos($key, 'Transfer-Encoding') === 0
                    || stripos($key, 'Authorization') === 0
                ) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                    $requestHeader[] = sprintf('%s: %s', $key, $headerLine);
                }
            }

            return $requestHeader;
        }

        return false;
    }
}

$logFile = fopen(__DIR__ . '/http-logger.log', 'a+');
if($logFile)
{
    $isSSL = isset($_SERVER['HTTPS'])
        ? $_SERVER['HTTPS'] == 'on'
        : (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            ? $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
            : ''
        )
    ;
    $protocol = strtolower($isSSL ? 'https' : substr($_SERVER['SERVER_PROTOCOL'], 0, strpos($_SERVER['SERVER_PROTOCOL'], '/')));

    $logMessage = sprintf(
        "%s: %s://%s%s\n\tGET: %s\n\tPOST: %s\n\tHEADERS: %s\n",
        $_SERVER['REQUEST_METHOD'], $protocol, $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'],
        json_encode($_GET), json_encode($_POST), json_encode(http_get_request_headers())
    );

    fwrite($logFile, $logMessage);
    fclose($logFile);

    echo "OK";
}
