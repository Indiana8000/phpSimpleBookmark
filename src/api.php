<?php
header('Content-Type: application/json');
$input  = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

$screenshotURL = "http://192.168.5.15:8080/?url=%s";

require_once 'api_storage.php';
require_once 'api_curl.php';

$storage = new Storage();
$data    = $storage->load();

switch($action){
    case 'getCategories':
        usort($data['categories'], function ($a, $b) {return strnatcasecmp($a['name'],$b['name']);});
        echo json_encode($data['categories']); break;
    case 'addCategory':
        $data['categories'][] = ['id'=>time(),'name'=>$input['name'],'icon'=>$input['icon']??'bi-folder'];
        $storage->save($data);
        echo json_encode(true);
        break;
    case 'updateCategory':
        foreach($data['categories'] as &$c) if($c['id']==$input['id']) { $c['name']=$input['name']; $c['icon']=$input['icon']; }
        $storage->save($data);
        echo json_encode(true);
        break;
    case 'deleteCategory':
        $data['categories']=array_values(array_filter($data['categories'],fn($c)=>$c['id']!=$input['id']));
        foreach ($data['items'] as &$i) {
            if ($i['category_id'] == $input['id']) {
                deleteItemImages($i['id'], 'thumb');
                deleteItemImages($i['id'], 'preview');
            }
        }
        $data['items'] = array_values(array_filter($data['items'],fn($i)=>$i['category_id']!=$input['id']));
        $storage->save($data);
        echo json_encode(true);
        break;
    case 'getItems':
        usort($data['items'], function ($a, $b) {return strnatcasecmp($a['title'],$b['title']);});
        echo json_encode(array_values(array_filter($data['items'],fn($i)=>$i['category_id']==$input['category_id'])));
        break;
    case 'addItem':
        $id = time(); // oder eigener ID-Generator

        $imagePath = '';
        if (!empty($_FILES['image'])) {
            $imagePath   = saveItemImage($id, $_FILES['image']  , 'thumb');
        }
        $previewPath = '';
        if (!empty($_FILES['preview'])) {
            $previewPath = saveItemImage($id, $_FILES['preview'], 'preview');
        }

        if($_POST['title'] == "" || $_POST['content'] == "") {
            $result = getWebTitle($_POST['url']);
            if($_POST['title'] == "" && isset($result['title'])) $_POST['title'] = $result['title'];
            if($_POST['content'] == "" && isset($result['description'])) $_POST['content'] = $result['description'];
        }

        $data['items'][] = [
            'id' => $id,
            'category_id' => $_POST['category_id'],
            'title' => $_POST['title'],
            'url' => $_POST['url'],
            'content' => $_POST['content'],
            'image' => $imagePath,
            'preview' => $previewPath
        ];

        $storage->save($data);
        echo json_encode(true);
        break;

    case 'updateItem':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $_POST['id']) {
                if($_POST['title'] == "" || $_POST['content'] == "") {
                    $result = getWebTitle($_POST['url']);
                    if($_POST['title'] == "" && isset($result['title'])) $_POST['title'] = $result['title'];
                    if($_POST['content'] == "" && isset($result['description'])) $_POST['content'] = $result['description'];
                }

                $i['title'] = $_POST['title'];
                $i['content'] = $_POST['content'];
                $i['url'] = $_POST['url'];

                if (!empty($_FILES['image'])) {
                    $i['image']   = saveItemImage($i['id'], $_FILES['image']  , 'thumb');
                }
                if (!empty($_FILES['preview'])) {
                    $i['preview'] = saveItemImage($i['id'], $_FILES['preview'], 'preview');
                }
            }
        }
        $storage->save($data);
        echo json_encode(true);
        break;

    case 'updateItemCategory':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $input['id']) {
                $i['category_id'] = $input['category_id'];
            }
        }
        $storage->save($data);
        echo json_encode(true);
        break;

    case 'deleteItem':
        deleteItemImages($input['id'], 'thumb');
        deleteItemImages($input['id'], 'preview');
        $data['items'] = array_values(array_filter($data['items'],fn($i)=>$i['id']!=$input['id']));
        $storage->save($data);
        echo json_encode(true);
        break;

    case 'getIcons':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $input['id']) {
                $r = getFaviconsByQuality($i['url']);
                echo json_encode($r);
            }
        }
        break;
    
    case 'setIcon':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $input['id']) {
                $i['image'] = downloadItemImage($i['id'], $input['url'], 'thumb');
            }
        }
        $storage->save($data);
        echo json_encode(true);
        break;

    case 'getScreenshot':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $input['id']) {
                $data = curlDownoadHTML(sprintf($screenshotURL, $i['url']));
                $imageInfo = @getimagesizefromstring($data);
                if ($imageInfo !== false && in_array($imageInfo['mime'], ['image/png', 'image/jpeg', 'image/gif'])) {
                    echo json_encode(['image' => base64_encode($data)]);
                } else {
                    echo json_encode(['error' => $data]);
                }
            }
        }
        break;

    case 'setScreenshot':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $_POST['id']) {
                if (!empty($_POST['preview'])) {
                    $i['preview'] = saveItemImage($i['id'], base64_decode($_POST['preview']), 'preview');
                    $storage->save($data);
                    echo json_encode(true);
                } else {
                    echo json_encode(false);
                }
            }
        }
        //echo json_encode(true);
        break;
    
    case 'deleteScreenshot':
        foreach ($data['items'] as &$i) {
            if ($i['id'] == $input['id']) {
                deleteItemImages($i['id'], 'preview');
                $i['preview'] = '';
                $storage->save($data);
            }
        }
        echo json_encode(true);
        break;
    default: http_response_code(400); echo json_encode(['error'=>'Unknown action']);
}

function deleteItemImages($itemId, $path) {
    $dir = __DIR__ . "/uploads/" . $path . "/";
    foreach (glob($dir . 'item_' . $itemId . '.*') as $file) {
        unlink($file);
    }
}

function saveItemImage($itemId, $file, $path) {
    $dir = __DIR__ . "/uploads/" . $path . "/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Alte Bilder entfernen (Update-Fall)
    deleteItemImages($itemId, $path);

    $ts = time(); // Timestamp as cache buster
    if(is_array($file)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?? 'png');
        $filename = "item_{$itemId}.{$ts}.{$ext}";
        move_uploaded_file($file['tmp_name'], $dir . $filename);
    } else {
        $filename = "item_{$itemId}.{$ts}.png";
        file_put_contents($dir . $filename, $file);
    }

    return "uploads/" . $path . "/" . $filename;
}

function downloadItemImage($itemId, $url, $path) {
    $dir = __DIR__ . "/uploads/" . $path . "/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    error_log("Downloading item image for item ID $itemId from URL: $url");

    // Alte Bilder entfernen (Update-Fall)
    deleteItemImages($itemId, $path);

    $ts = time(); // Timestamp as cache buster
    $parsed = parseUrlAndFile($url);
    $ext = $parsed['extension'] ?? 'png';
    $filename = "item_{$itemId}.{$ts}.{$ext}";
    error_log("Determined filename: $filename");

    if(curlDownloadFile($url, $dir . $filename)) {
        return "uploads/" . $path . "/" . $filename;
    }
    return false;
}

function getWebTitle(string $url): array
{
    $result = [];

    // Ceck URL
    $parsed = parseUrlAndFile($url);
    if (!isset($parsed['scheme'], $parsed['host'])) {
        return [];
    }

    // Download HTML
    $html = curlDownoadHTML($url);
    if ($html === false) {
        return [];
    }

    // Encoding HTML
    $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    // DOM parse
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    //xpath extract
    $xpath = new DOMXPath($dom);

    $titleNode = $xpath->query('//title')->item(0);
    if ($titleNode && $titleNode instanceof DOMNode) {
        $result['title'] = trim($titleNode->textContent);
    }

    $descNode = $xpath->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]')->item(0);
    if ($descNode && $descNode instanceof DOMElement) {
        $result['description'] = trim($descNode->getAttribute('content'));
    }

    return $result;
}

function getFaviconsByQuality(string $url): array
{
    $result = [];

    // Check & set base URL
    $parsed = parseUrlAndFile($url);
    if (!isset($parsed['scheme'], $parsed['host'])) {
        return [];
    }
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
    
    // First try with original URL, fallback to base URL
    $result = parseWebpage($url, $baseUrl);
    if(count($result) == 0) {
        $result = parseWebpage($baseUrl, $baseUrl);
    }

    return $result;
}

function parseWebpage(string $url, string $baseUrl): array
{
    $result = [];

    $parsed = parseUrlAndFile($url);
    if (!isset($parsed['scheme'], $parsed['host'])) {
        return [];
    }

    $html = curlDownoadHTML($url);
    if ($html === false) {
        return [];
    }

    // DOM parsen
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $links = $dom->getElementsByTagName('link');
    foreach ($links as $link) {
        $rel = strtolower($link->getAttribute('rel'));

        if (!preg_match('/icon/', $rel)) {
            continue;
        }

        $href = $link->getAttribute('href');
        if (!$href) {
            continue;
        }

        // Absolute URL erzeugen
        if (!preg_match('~^https?://~', $href)) {
            if(substr($href, 0, 4) == "data") {
                continue;
            } else if(substr($href, 0, 2) == "//") {
                $href = 'https:' . $href;
            } else if(substr($href, 0, 1) == "/") {
                $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
            } else if(substr($href, 0, 2) == "./") {
                $href = rtrim($url, '/') . ltrim($href, '.');
            } else {
                $href = rtrim($url, '/') . '/' . $href;
            }
        }

        // Bildgröße ermitteln
        $size = curlGetImageSize($href);
        if ( $size == null || $size === false) {
            continue;
        }

        $result[] = [
            'url'    => $href,
            'width'  => $size[0],
            'height' => $size[1],
            'area'   => $size[0] * $size[1],
            'type'   => $size['mime'] ?? null
        ];
    }

    // Fallback: /favicon.ico
    $fallback = $baseUrl . '/favicon.ico';
    if (curlGetImageSize($fallback)) {
        $size = curlGetImageSize($fallback);
        $result[] = [
            'url'    => $fallback,
            'width'  => $size[0],
            'height' => $size[1],
            'area'   => $size[0] * $size[1],
            'type'   => $size['mime'] ?? null
        ];
    }

    $result = array_values(array_unique($result, SORT_REGULAR));
    usort($result, fn($a, $b) => $b['area'] <=> $a['area']);
    return $result;
}

?>