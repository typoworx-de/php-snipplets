<?php
$proxyRelUri = '/remote-sub-dir';
$proxyUri  = ltrim( str_replace($proxyRelUri, '', $_SERVER['REQUEST_URI']), '/');
$proxyPassUrl  = 'https://www.target-site.com/';

$useCache = TRUE;
$cacheDir = 'cache/';

$isSSL = (!empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'on');
$proxyViaUrl = sprintf('http%s://%s', $isSSL ? 's' : '', $_SERVER['HTTP_HOST']);


if(!$isSSL)
{
    header(sprintf('location: %s', $proxyViaUrl . $_SERVER['REQUEST_URI']));
    exit;
}

if(!empty($useCache) && !is_dir(__DIR__ . '/' . $cacheDir))
{
    mkdir(__DIR__ . '/' . $cacheDir, 0770);
}

$requestHeader = Array();
foreach($_SERVER as $key => $header)
{
    // @see https://de.wikipedia.org/wiki/Liste_der_HTTP-Headerfelder
    if(
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
    )
    {
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        $requestHeader[] = sprintf('%s: %s', $key, $header);
    }
}

// PROXY FORWARD HEADER
$requestHeader[] = sprintf('X-Forwarded-For: for=%s; proto=http%s; by=%s', $_SERVER['REMOTE_ADDR'], $isSSL ? 's' : '', $_SERVER['SERVER_NAME']);


$proxyAbsUrl = $proxyPassUrl . $proxyRelUri;
$requestURL = $proxyPassUrl . $proxyUri;


//die(var_dump('<pre>', $requestURL, $requestHeader));


$response = NULL;

$isCached = FALSE;
$cacheTTL = '+5min';

if(!empty($useCache) && is_dir(__DIR__ . '/' . $cacheDir) && is_readable(__DIR__ . '/' . $cacheDir))
{
    if(empty($_POST) && file_exists(__DIR__ . '/' . $cacheDir . '/' . md5($requestURL . implode('', $requestHeader)) . '.cache'))
    {
        try
        {
            if(strtotime($cacheTTL, filectime(__DIR__ . '/' . $cacheDir . '/' . md5($requestURL) . '.cache')) > time())
            {
                $response = file_get_contents(__DIR__ . '/' . $cacheDir . '/' . md5($requestURL) . '.cache');
                $isCached = !empty($response);
            }
        }
        catch(Exception $e)
        {
        }
    }

    //var_dump('<pre>', $isCached, md5($requestURL), $response);
}

if(empty($isCached))
{
    $requestCount = 0;
    $maxRedirects = 5;
    do
    {
        $doRedirect = false;
        $ch = curl_init($requestURL);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeader);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_MAXCONNECTS, 16);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        if(!empty($_POST) && $requestCount == 0)
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
        }

        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);

        $responseURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if($responseURL != $requestURL)
        {
            curl_close($ch);

            $doRedirect = true;
            $requestCount++;

            $requestURL = $responseURL;
        }
    }
    while($doRedirect || $requestCount >= $maxRedirects);

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    //$sent_request = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    //$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    curl_close($ch);

    // WRITE TO CACHE
    if(!empty($useCache) && !empty($response) && $status == '200' && is_dir(__DIR__ . '/' . $cacheDir) && is_writeable(__DIR__ . '/' . $cacheDir))
    {
        try
        {
            file_put_contents(__DIR__ . '/' . $cacheDir . '/' . md5($requestURL) . '.cache', $response);
        }
        catch(Exception $e)
        {
            //var_dump($e);
        }
    }
}


if(empty($response))
{
    // Something unexpected happened...
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 300');
    echo '<h1>Service Temporarely unavailable!</h1>';
    echo '<p>Please be patient and try again shortly.</p>';
    exit;
}


$headers = array();

$parts = explode("\r\n\r\nHTTP/", $response);
$parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
list($headers, $body) = explode("\r\n\r\n", $parts, 2);
list($responseHeaders, $responseBody) = explode("\r\n\r\n", $parts, 2);

#$responseHeaders = substr($response, 0, $header_size);
#$responseBody = substr($response, $header_size);

$hasContentType = FALSE;
$responseHeaders = explode("\r\n", $responseHeaders);

//die(var_dump('<pre>HEADERS', $responseHeaders, 'BODY', $responseBody));

foreach($responseHeaders as $key => $header_value)
{
    // SKIP HEADERS
    if(
	stripos($header_value, 'Content-Encoding') === 0 ||
	stripos($header_value, 'Transfer-Encoding') === 0 ||
	// Bug?
	stripos($header_value, 'Content-Length') === 0
    )
    {
        //$responseHeaders[$key].=' --> FILTERED!';
        continue;
    }

/*
    if(stripos($header_value, 'Content-Length') === 0)
    {
	$header_value = sprintf('Content-Length: ', strlen($responseBody));
    }
    else
*/
    if(stripos($header_value, 'Content-Type') === 0)
    {
        $hasContentType = TRUE;
        $tmp = explode(':', $header_value, 2);
        $contentType = trim($tmp[1]);
    }

    // REWRITE HEADERS

    //# Test for 301 or 302
    //if(stripos($header_value, 'location') === 0)
    //{
    //    $redirectUrl = trim(substr($header_value, 9, strlen($header_value)));
    //}

    if(stripos($header_value, 'Set-Cookie') === 0)
    {
        $header_value = preg_replace('/Path=[^;]+/', 'Path=' . $proxyRelUri . '/', $header_value);
    }

    //var_dump($header_value);
    header($header_value);
}

if(!$hasContentType && !empty($contentType))
{
    header(sprintf('Content-Type: %s', 'text/html'));
}
else if(stripos($contentType, 'text/html') === 0)
{
    ##$responseBody = preg_replace('/<(.+?)(action|src|href)="[\.\/]{0}(.+)"([^>]*)>/', '<\1\2="./\3"\4>', $responseBody);
    ##$responseBody = preg_replace('/<(.+?)(action|src|href)="[\.]{0}[\/]{1}(.+)"([^>]*)>/', '<\1\2=".\3"\4>', $responseBody);
    $responseBody = preg_replace('/<(.+?)(action|src|href)="(\/.+)"([^>]*)>/', '<\1\2=".\3"\4>', $responseBody);
    if(!strpos($responseBody, '<base href'))
    {
        $baseHref = sprintf('<base href="%s" />', rtrim($proxyViaUrl . $proxyRelUri, '/') . '/');

        $appendAfterTag = '<head>';
        if(strpos($responseBody, '<title>'))
        {
            $appendAfterTag = '</title>';
        }

        $responseBody = str_replace($appendAfterTag, sprintf("%s\n\n\t\t%s", $appendAfterTag, $baseHref), $responseBody);
    }
}

if(!empty($responseBody))
{
    echo $responseBody;
}

/*
if($_GET['DEBUG'])
{
    die(var_dump('<pre>', $requestCount, $requestURL, $_POST));
    die(var_dump('<pre>', $requestHeader, $responseHeaders));
    die(var_dump('<pre>', $_GET, $_POST));
}
*/
