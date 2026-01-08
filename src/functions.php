<?php
/**
 * Reclamo Bot - Funciones reutilizables
 * Extraídas para testing
 */

/**
 * Cargar municipios disponibles desde archivos JSON
 */
function getMunicipios($basePath = null) {
    $basePath = $basePath ?? __DIR__ . '/../municipios';
    $municipios = [];
    $files = glob($basePath . '/*.json') ?: [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data !== null && json_last_error() === JSON_ERROR_NONE) {
            $data['_file'] = basename($file, '.json');
            $municipios[] = $data;
        }
    }
    // Ordenar por nombre
    usort($municipios, fn($a, $b) => strcmp($a['nombre'] ?? '', $b['nombre'] ?? ''));
    return $municipios;
}

/**
 * Redimensionar imagen para cumplir límite de tamaño
 */
function resizeImage($filePath, $mimeType, $maxSize) {
    switch ($mimeType) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($filePath); break;
        case 'image/png': $img = @imagecreatefrompng($filePath); break;
        case 'image/gif': $img = @imagecreatefromgif($filePath); break;
        case 'image/webp': $img = @imagecreatefromwebp($filePath); break;
        default: return file_get_contents($filePath);
    }

    if (!$img) return file_get_contents($filePath);

    $width = imagesx($img);
    $height = imagesy($img);
    $quality = 85;

    do {
        ob_start();
        imagejpeg($img, null, $quality);
        $data = ob_get_clean();
        $quality -= 10;
    } while (strlen($data) > $maxSize && $quality > 20);

    if (strlen($data) > $maxSize) {
        $scale = sqrt($maxSize / strlen($data));
        $newWidth = (int)($width * $scale);
        $newHeight = (int)($height * $scale);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img);
        $img = $resized;
        ob_start();
        imagejpeg($img, null, 70);
        $data = ob_get_clean();
    }

    imagedestroy($img);
    return $data;
}

/**
 * Extraer asunto de una carta o generar uno desde el contenido
 */
function extractSubject($complaint) {
    if (preg_match('/Asunto:\s*(.+)/i', $complaint, $matches)) {
        return trim($matches[1]);
    }

    $cleanedComplaint = preg_replace('/\s+/', ' ', trim($complaint));
    $subject = mb_substr($cleanedComplaint, 0, 60);
    if (mb_strlen($cleanedComplaint) > 60) {
        $subject .= '...';
    }
    return $subject;
}

/**
 * Parsear emails de CC desde string separado por comas
 */
function parseCcEmails($ccString) {
    if (empty($ccString)) {
        return [];
    }
    return array_map('trim', explode(',', $ccString));
}

/**
 * Generar mensaje de adjuntos con pluralización correcta
 */
function formatAttachmentMessage($count, $municipioNombre) {
    $msg = "Reclamo enviado a {$municipioNombre}!";
    if ($count > 0) {
        $plural = $count != 1;
        $msg .= " ({$count} foto" . ($plural ? 's' : '') . " adjunta" . ($plural ? 's' : '') . ")";
    }
    return $msg;
}

/**
 * Validar coordenadas geográficas
 */
function validateCoordinates($lat, $lng) {
    $validLat = '';
    $validLng = '';

    if (!empty($lat) && is_numeric($lat) && $lat >= -90 && $lat <= 90) {
        $validLat = $lat;
    }
    if (!empty($lng) && is_numeric($lng) && $lng >= -180 && $lng <= 180) {
        $validLng = $lng;
    }

    return ['lat' => $validLat, 'lng' => $validLng];
}

/**
 * Hacer request a API externa
 */
function makeApiRequest($url, $headers, $payload, $timeout = 30) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $curlError
    ];
}
