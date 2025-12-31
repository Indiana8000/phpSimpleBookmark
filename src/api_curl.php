<?php


function parseUrlAndFile($url) {
    $web = parse_url($url);
    $file = pathinfo($web['path']);
    return array_merge($web, $file);
}

function setupCurlHandle(string $url, bool $header = false): CurlHandle {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT:!DH');

    if($header) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ]);
    }
    return $ch;
}

function curlDownoadHTML(string $url): ?string {
    $ch = setupCurlHandle($url, true);
    $html = curl_exec($ch);
    if(curl_errno($ch))
        error_log('cURL error: ' . curl_error($ch));
    return $html;
}

function curlDownloadFile($url, $fullFilePath): bool {
    $ch = setupCurlHandle($url, false);
    curl_setopt($ch, CURLOPT_FILE, fopen($fullFilePath, 'w+'));

    $data = curl_exec($ch);
    if(curl_errno($ch))
        error_log('cURL error: ' . curl_error($ch));
    return $data !== false;
}

function curlGetImageSize($url): ?array {
    $ch = setupCurlHandle($url, false);

    $data = curl_exec($ch);
    if ($data === false) {
        error_log('cURL error: ' . curl_error($ch));
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode >= 400) {
        error_log('cURL http error: ' . $httpCode);
        return null;
    }

    $info = @getimagesizefromstring($data);
    if ($info === false) {
        return null;
    }

    return $info;
}

?>