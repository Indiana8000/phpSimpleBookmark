<?php

function parseUrlAndFile($url) {
    $web = parse_url($url);
    if ($web === false) return false;
    $file = isset($web['path']) ? pathinfo($web['path']) : [];
    return array_merge($web, $file);
}

function setupCurlHandle(string $url, bool $header = false): CurlHandle {
    $ch = curl_init($url);
    if ($ch === false) {
        throw new \RuntimeException('curl_init failed for URL: ' . $url);
    }

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
    if (curl_errno($ch))
        error_log('cURL error: ' . curl_error($ch));
    return $html === false ? null : $html;
}

function curlDownloadFile($url, $fullFilePath): bool {
    $ch = setupCurlHandle($url, false);
    $fp = fopen($fullFilePath, 'w+');
    if ($fp === false) {
        error_log('Failed to open file for writing: ' . $fullFilePath);
        return false;
    }
    curl_setopt($ch, CURLOPT_FILE, $fp);
    $data = curl_exec($ch);
    if (curl_errno($ch))
        error_log('cURL error: ' . curl_error($ch));
    fclose($fp);
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
        error_log('cURL http error: ' . $httpCode . ' for URL: ' . $url);
        return null;
    }

    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if (str_contains((string)$contentType, 'svg') || isSvgContent($data)) {
        return parseSvgSize($data);
    }

    $info = @getimagesizefromstring($data);
    if ($info === false) {
        return null;
    }

    return $info;
}

function isSvgContent(string $data): bool {
    $trimmed = ltrim($data);
    return str_contains($trimmed, '<svg') && (
        str_starts_with($trimmed, '<svg') ||
        str_starts_with($trimmed, '<?xml')
    );
}

function parseSvgSize(string $data): ?array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($data);
    libxml_clear_errors();
    if ($xml === false) {
        return null;
    }

    $attrs = $xml->attributes();
    $width = 0;
    $height = 0;

    if (isset($attrs['width']) && isset($attrs['height'])) {
        $width = (int) $attrs['width'];
        $height = (int) $attrs['height'];
    }

    if (($width === 0 || $height === 0) && isset($attrs['viewBox'])) {
        $parts = preg_split('/[\s,]+/', trim((string)$attrs['viewBox']));
        if (count($parts) === 4) {
            $width = (int) $parts[2];
            $height = (int) $parts[3];
        }
    }

    return [
        0       => $width,
        1       => $height,
        2       => -1,
        3       => "width=\"$width\" height=\"$height\"",
        'mime'  => 'image/svg+xml',
    ];
}

?>