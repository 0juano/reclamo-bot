<?php
// Reclamo Bot - Versión Argentina
// Fork de Karen Bot de @levelsio adaptado para municipios argentinos
// https://github.com/levelsio/karen-bot
//
// Configuración:
// 1. Copiá este archivo a tu servidor
// 2. Configurá las variables abajo (API keys, etc)
// 3. Agregá una ruta en Nginx: /reclamo -> index.php
// 4. Accedé con /reclamo?key=tu_clave
//
// Repo: https://github.com/0juano/reclamo-bot

// <configuración>

// Clave de acceso (agregá ?key=tu_clave a la URL)
define('KEY_TO_ACCESS_THE_SCRIPT', 'cambiar_esto');

// Resend API (https://resend.com - gratis hasta 3000 emails/mes)
define('RESEND_API_KEY', 'tu_api_key_de_resend');

// LLM Provider: 'openrouter' (recomendado, más barato) o 'openai'
define('LLM_PROVIDER', 'openrouter');

// OpenRouter API (https://openrouter.ai - más barato, muchos modelos)
define('OPENROUTER_API_KEY', 'tu_api_key_de_openrouter');
define('OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku');  // o 'openai/gpt-4o-mini', 'google/gemini-flash-1.5'

// OpenAI API (alternativa)
define('OPENAI_API_KEY', 'tu_api_key_de_openai');
define('OPENAI_MODEL', 'gpt-4o-mini');

// Tus datos
define('YOUR_NAME', 'Tu Nombre Completo');
define('FROM_YOUR_EMAIL', 'tu@email.com');  // Debe estar verificado en Resend
define('CC_EMAILS', '');  // Emails en copia, separados por coma (opcional)

// </configuración>

error_reporting(0);
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_file_uploads', '20');

// Verificar clave de acceso
if (($_GET['key'] ?? '') !== KEY_TO_ACCESS_THE_SCRIPT) {
    http_response_code(404);
    exit('No encontrado');
}

// Cargar municipios disponibles
function getMunicipios() {
    $municipios = [];
    $files = glob(__DIR__ . '/municipios/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $data['_file'] = basename($file, '.json');
            $municipios[] = $data;
        }
    }
    // Ordenar por nombre
    usort($municipios, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
    return $municipios;
}

$municipios = getMunicipios();
$municipioActual = null;

// Si hay municipio seleccionado, cargarlo
if (isset($_GET['m']) || isset($_POST['municipio'])) {
    $mId = $_GET['m'] ?? $_POST['municipio'];
    $mFile = __DIR__ . '/municipios/' . basename($mId) . '.json';
    if (file_exists($mFile)) {
        $municipioActual = json_decode(file_get_contents($mFile), true);
    }
}

// Manejar solicitud AJAX para expandir con GPT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'expand') {
    header('Content-Type: application/json');

    $complaint = trim($_POST['complaint'] ?? '');
    $hasAttachments = isset($_POST['hasAttachments']) && $_POST['hasAttachments'] === 'true';
    $address = trim($_POST['address'] ?? '');
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $municipioNombre = trim($_POST['municipio_nombre'] ?? 'la Municipalidad');

    if (empty($complaint)) {
        echo json_encode(['success' => false, 'error' => 'El texto del reclamo es requerido']);
        exit;
    }

    $systemPrompt = "Sos un asistente que transforma reclamos informales en cartas formales en español argentino para enviar a municipalidades.

FORMATO DE LA CARTA:
Empezá SIEMPRE con un bloque de datos estructurados (solo incluí campos que tengas):

---
Asunto: [una línea resumiendo el problema, ej: 'Bache peligroso en intersección hace 2 semanas']
Ubicación: [dirección/calle mencionada]
Google Maps: [link si fue proporcionado]
---

Después escribí la carta formal:
- Saludo formal: 'De mi mayor consideración:' o 'Sres. {$municipioNombre}:'
- Estructurar el problema de forma clara
- Incluir un pedido de acción específico
- Terminar con despedida formal argentina: 'Saludo a Ud. atentamente,' o 'Sin otro particular, saludo a Ud. muy atentamente,'

IMPORTANTE:
- NUNCA agregar placeholders o texto entre corchetes como [nombre], [dirección], etc.
- La carta debe estar lista para enviar sin ningún texto para completar
- Si no tenés una información, simplemente no la incluyas
- Usá vocabulario argentino (vos, acá, etc. pero formal)

Firmá siempre la carta con:

" . YOUR_NAME;

    $userPrompt = "Transformá el siguiente reclamo en una carta formal para {$municipioNombre}.\n\n";

    if (!empty($address)) {
        $userPrompt .= "Ubicación del problema: $address\n";
        if (!empty($lat) && !empty($lng)) {
            $userPrompt .= "Google Maps: https://www.google.com/maps/@$lat,$lng,100m/data=!3m1!1e3\n";
        }
        $userPrompt .= "\n";
    }

    $userPrompt .= "Reclamo:\n$complaint";

    if ($hasAttachments) {
        $userPrompt .= "\n\nNOTA: Se van a adjuntar fotografías como evidencia. Mencionalo en la carta (ej: 'Adjunto fotografías que documentan la situación descripta.')";
    }

    // Configurar según el provider elegido
    if (LLM_PROVIDER === 'openrouter') {
        $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        $apiKey = OPENROUTER_API_KEY;
        $model = OPENROUTER_MODEL;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://github.com/0juano/reclamo-bot',
            'X-Title: Reclamo Bot Argentina'
        ];
    } else {
        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $apiKey = OPENAI_API_KEY;
        $model = OPENAI_MODEL;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $providerName = LLM_PROVIDER === 'openrouter' ? 'OpenRouter' : 'OpenAI';
        echo json_encode(['success' => false, 'error' => "Error al conectar con {$providerName} API"]);
        exit;
    }

    $data = json_decode($response, true);
    if (isset($data['choices'][0]['message']['content'])) {
        echo json_encode(['success' => true, 'expanded' => $data['choices'][0]['message']['content']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Respuesta inválida de OpenAI']);
    }
    exit;
}

// Manejar envío de email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    header('Content-Type: application/json');

    $complaint = trim($_POST['complaint'] ?? '');
    $municipioEmail = trim($_POST['municipio_email'] ?? '');
    $municipioNombre = trim($_POST['municipio_nombre'] ?? '');

    // Extraer Asunto de la carta, o usar primeros 60 caracteres
    if (preg_match('/Asunto:\s*(.+)/i', $complaint, $matches)) {
        $subject = trim($matches[1]);
    } else {
        $subject = mb_substr(preg_replace('/\s+/', ' ', $complaint), 0, 60);
        if (mb_strlen($complaint) > 60) $subject .= '...';
    }

    if (empty($complaint)) {
        echo json_encode(['success' => false, 'message' => 'Por favor completá el reclamo.']);
        exit;
    }

    if (empty($municipioEmail)) {
        echo json_encode(['success' => false, 'message' => 'No hay email configurado para este municipio.']);
        exit;
    }

    $emailBody = $complaint;

    // Procesar adjuntos (redimensionar imágenes a ~1MB máx)
    $attachments = [];
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $fileName = $_FILES['attachments']['name'][$i];
                $mimeType = mime_content_type($tmpName);

                $maxSize = 1024 * 1024; // 1MB
                $fileSize = filesize($tmpName);

                if (strpos($mimeType, 'image/') === 0 && $fileSize > $maxSize) {
                    $imageData = resizeImage($tmpName, $mimeType, $maxSize);
                    $content = base64_encode($imageData);
                    if ($mimeType !== 'image/jpeg') {
                        $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';
                        $mimeType = 'image/jpeg';
                    }
                } else {
                    $content = base64_encode(file_get_contents($tmpName));
                }

                $attachments[] = [
                    'filename' => $fileName,
                    'content' => $content,
                    'content_type' => $mimeType
                ];
            }
        }
    }

    function resizeImage($filePath, $mimeType, $maxSize) {
        switch ($mimeType) {
            case 'image/jpeg': $img = imagecreatefromjpeg($filePath); break;
            case 'image/png': $img = imagecreatefrompng($filePath); break;
            case 'image/gif': $img = imagecreatefromgif($filePath); break;
            case 'image/webp': $img = imagecreatefromwebp($filePath); break;
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

    // Verificar si hubo error con adjuntos
    $filesUploaded = isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0]);
    if ($filesUploaded && empty($attachments)) {
        echo json_encode(['success' => false, 'message' => 'Error al procesar los adjuntos. Por favor intentá de nuevo.']);
        exit;
    }

    // Enviar con Resend
    $emailPayload = [
        'from' => YOUR_NAME . ' <' . FROM_YOUR_EMAIL . '>',
        'to' => [$municipioEmail],
        'subject' => $subject,
        'text' => $emailBody
    ];

    if (!empty(CC_EMAILS)) {
        $emailPayload['cc'] = array_map('trim', explode(',', CC_EMAILS));
    }

    if (!empty($attachments)) {
        $emailPayload['attachments'] = $attachments;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . RESEND_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailPayload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['id'])) {
        $attachmentCount = count($attachments);
        $msg = "Reclamo enviado a {$municipioNombre}!";
        if ($attachmentCount > 0) {
            $msg .= " ({$attachmentCount} foto" . ($attachmentCount != 1 ? 's' : '') . " adjunta" . ($attachmentCount != 1 ? 's' : '') . ")";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        $errorMsg = $responseData['message'] ?? 'Error desconocido';
        echo json_encode(['success' => false, 'message' => 'Error al enviar: ' . $errorMsg]);
    }
    exit;
}

// Centro del mapa por defecto (Argentina)
$defaultLat = $municipioActual['mapa_centro'][0] ?? -34.6037;
$defaultLng = $municipioActual['mapa_centro'][1] ?? -58.3816;
$defaultZoom = $municipioActual['mapa_zoom'] ?? 12;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamo Bot - Reclamos Municipales Argentina</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🇦🇷</text></svg>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        h1 {
            color: #000;
            border-bottom: 3px solid #75AADB;
            padding-bottom: 10px;
        }
        .info {
            background: #E8F4FD;
            border-left: 4px solid #75AADB;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .municipio-selector {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .municipio-selector select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .municipio-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        .municipio-info a { color: #75AADB; }
        form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        textarea {
            min-height: 150px;
            resize: vertical;
            font-family: inherit;
        }
        #map {
            height: 250px;
            width: 100%;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
        }
        .location-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button[type="submit"], .btn-primary {
            background: #75AADB;
            color: white;
        }
        button[type="submit"]:hover, .btn-primary:hover {
            background: #5A8FC0;
        }
        button[type="button"] {
            background: #333;
            color: white;
        }
        button[type="button"]:hover {
            background: #555;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .upload-box {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            margin-bottom: 15px;
        }
        .upload-box:hover {
            border-color: #75AADB;
            background: #f8f9fa;
        }
        .upload-box.dragover {
            border-color: #75AADB;
            background: #E8F4FD;
        }
        .upload-box input[type="file"] { display: none; }
        #imagePreview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        #imagePreview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #999;
        }
        footer a { color: #75AADB; }
    </style>
</head>
<body>
    <h1>🇦🇷 Reclamo Bot</h1>

    <div class="info">
        Hacé reclamos a tu municipio de forma rápida. La IA escribe una carta formal por vos.
    </div>

    <div class="municipio-selector">
        <label for="municipioSelect">Seleccioná tu municipio</label>
        <select id="municipioSelect" onchange="selectMunicipio(this.value)">
            <option value="">-- Elegí un municipio --</option>
            <?php foreach ($municipios as $m): ?>
            <option value="<?= htmlspecialchars($m['_file']) ?>"
                    data-email="<?= htmlspecialchars($m['email'] ?? '') ?>"
                    data-lat="<?= htmlspecialchars($m['mapa_centro'][0] ?? -34.6037) ?>"
                    data-lng="<?= htmlspecialchars($m['mapa_centro'][1] ?? -58.3816) ?>"
                    data-zoom="<?= htmlspecialchars($m['mapa_zoom'] ?? 12) ?>"
                    data-nombre="<?= htmlspecialchars($m['nombre']) ?>"
                    data-telefono="<?= htmlspecialchars($m['telefono'] ?? '') ?>"
                    data-web="<?= htmlspecialchars($m['web'] ?? '') ?>"
                    <?= ($municipioActual && $municipioActual['_file'] === $m['_file']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['nombre']) ?> (<?= htmlspecialchars($m['provincia']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <div class="municipio-info" id="municipioInfo">
            <?php if ($municipioActual): ?>
                📧 <?= htmlspecialchars($municipioActual['email'] ?? 'Sin email') ?>
                <?php if (!empty($municipioActual['telefono'])): ?>
                    | 📞 <?= htmlspecialchars($municipioActual['telefono']) ?>
                <?php endif; ?>
                <?php if (!empty($municipioActual['web'])): ?>
                    | <a href="<?= htmlspecialchars($municipioActual['web']) ?>" target="_blank">Web</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="reclamoForm">
        <input type="hidden" name="action" value="send">
        <input type="hidden" id="selectedLat" name="lat" value="">
        <input type="hidden" id="selectedLng" name="lng" value="">
        <input type="hidden" id="municipioId" name="municipio" value="<?= htmlspecialchars($municipioActual['_file'] ?? '') ?>">
        <input type="hidden" id="municipioEmail" name="municipio_email" value="<?= htmlspecialchars($municipioActual['email'] ?? '') ?>">
        <input type="hidden" id="municipioNombre" name="municipio_nombre" value="<?= htmlspecialchars($municipioActual['nombre'] ?? '') ?>">

        <button type="button" onclick="useMyLocation()" style="margin-bottom: 10px;">📍 Usar mi ubicación</button>
        <label>Hacé clic en el mapa para marcar el problema</label>
        <div id="map"></div>
        <div class="location-info" id="locationInfo">Sin ubicación seleccionada</div>

        <label>Adjuntar fotos (opcional)</label>
        <div class="upload-box" id="uploadBox" onclick="document.getElementById('attachments').click()">
            <div id="uploadPlaceholder">
                📷 Hacé clic o arrastrá fotos acá
            </div>
            <div id="imagePreview"></div>
            <input type="file" id="attachments" name="attachments[]" multiple accept="image/*" onchange="previewImages(this)">
        </div>

        <label for="input">Describí el problema</label>
        <textarea id="input" placeholder="Ej: Hay un bache enorme en la esquina hace 2 semanas. Ya pincharon 3 autos..." oninput="localStorage.setItem('reclamo', this.value)"></textarea>

        <button type="button" id="expandBtn" onclick="expandToFormalLetter()">
            ✍️ Generar carta formal
        </button>

        <label for="complaint" style="margin-top: 20px;">Carta formal (esto se envía)</label>
        <textarea id="complaint" name="complaint" placeholder="La carta formal aparecerá acá..." style="min-height: 300px;"></textarea>

        <div class="buttons">
            <button type="button" class="btn-primary" onclick="sendReclamo()">📤 Enviar reclamo</button>
        </div>

        <div id="statusMessage" style="margin-top: 15px;"></div>
    </form>

    <footer>
        <p>
            Basado en <a href="https://github.com/levelsio" target="_blank">Karen Bot de @levelsio</a> |
            <a href="https://github.com/0juano/reclamo-bot" target="_blank">Contribuí en GitHub</a> |
            <a href="https://github.com/0juano/reclamo-bot#agregar-municipio" target="_blank">Agregá tu municipio</a>
        </p>
    </footer>

    <script>
        const DEFAULT_LAT = <?= $defaultLat ?>;
        const DEFAULT_LNG = <?= $defaultLng ?>;
        const DEFAULT_ZOOM = <?= $defaultZoom ?>;

        const map = L.map('map').setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);

        const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        });
        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri'
        });

        streets.addTo(map);
        L.control.layers({ 'Calles': streets, 'Satélite': satellite }).addTo(map);

        let marker = null;
        let selectedAddress = '';

        function selectMunicipio(id) {
            if (!id) return;

            const select = document.getElementById('municipioSelect');
            const option = select.options[select.selectedIndex];

            const lat = parseFloat(option.dataset.lat);
            const lng = parseFloat(option.dataset.lng);
            const zoom = parseInt(option.dataset.zoom);

            map.setView([lat, lng], zoom);

            document.getElementById('municipioId').value = id;
            document.getElementById('municipioEmail').value = option.dataset.email;
            document.getElementById('municipioNombre').value = option.dataset.nombre;

            // Actualizar info
            let info = '📧 ' + (option.dataset.email || 'Sin email');
            if (option.dataset.telefono) info += ' | 📞 ' + option.dataset.telefono;
            if (option.dataset.web) info += ' | <a href="' + option.dataset.web + '" target="_blank">Web</a>';
            document.getElementById('municipioInfo').innerHTML = info;

            // Actualizar URL
            const url = new URL(window.location);
            url.searchParams.set('m', id);
            window.history.replaceState({}, '', url);
        }

        function useMyLocation() {
            if (!navigator.geolocation) {
                alert('Tu navegador no soporta geolocalización');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => map.setView([pos.coords.latitude, pos.coords.longitude], 16),
                () => alert('No se pudo obtener tu ubicación. Habilitá los permisos.'),
                { enableHighAccuracy: true }
            );
        }

        map.on('click', async function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            document.getElementById('selectedLat').value = lat;
            document.getElementById('selectedLng').value = lng;

            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng).addTo(map);
            }

            document.getElementById('locationInfo').textContent = 'Obteniendo dirección...';

            try {
                const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=es`, {
                    headers: { 'User-Agent': 'ReclamoBot/1.0' }
                });
                const data = await resp.json();
                selectedAddress = data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                document.getElementById('locationInfo').textContent = '📍 ' + selectedAddress;
            } catch (err) {
                selectedAddress = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                document.getElementById('locationInfo').textContent = '📍 ' + selectedAddress;
            }
        });

        // Cargar reclamo guardado
        document.getElementById('input').value = localStorage.getItem('reclamo') || '';

        // Drag and drop
        const uploadBox = document.getElementById('uploadBox');
        const fileInput = document.getElementById('attachments');

        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.classList.add('dragover');
        });
        uploadBox.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('dragover');
        });
        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                previewImages(fileInput);
            }
        });

        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('uploadPlaceholder');
            preview.innerHTML = '';

            if (input.files && input.files.length > 0) {
                placeholder.style.display = 'none';
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                placeholder.style.display = 'block';
            }
        }

        async function expandToFormalLetter() {
            const input = document.getElementById('input').value;
            const attachments = document.getElementById('attachments');
            const hasAttachments = attachments.files && attachments.files.length > 0;
            const btn = document.getElementById('expandBtn');
            const lat = document.getElementById('selectedLat').value;
            const lng = document.getElementById('selectedLng').value;
            const municipioNombre = document.getElementById('municipioNombre').value;

            if (!municipioNombre) {
                alert('Primero seleccioná un municipio.');
                return;
            }

            if (!input.trim()) {
                alert('Primero describí el problema.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Escribiendo...<span class="loading"></span>';

            try {
                const formData = new FormData();
                formData.append('action', 'expand');
                formData.append('complaint', input);
                formData.append('hasAttachments', hasAttachments);
                formData.append('address', selectedAddress);
                formData.append('lat', lat);
                formData.append('lng', lng);
                formData.append('municipio_nombre', municipioNombre);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    document.getElementById('complaint').value = data.expanded;
                } else {
                    alert('Error: ' + (data.error || 'No se pudo generar la carta'));
                }
            } catch (error) {
                alert('Error de conexión. Intentá de nuevo.');
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '✍️ Generar carta formal';
            }
        }

        async function sendReclamo() {
            const complaint = document.getElementById('complaint').value;
            const statusDiv = document.getElementById('statusMessage');
            const attachments = document.getElementById('attachments');
            const hasAttachments = attachments.files && attachments.files.length > 0;
            const municipioEmail = document.getElementById('municipioEmail').value;
            const municipioNombre = document.getElementById('municipioNombre').value;

            if (!municipioEmail) {
                alert('No hay email configurado para este municipio.');
                return;
            }

            if (!complaint.trim()) {
                alert('Primero generá la carta formal.');
                return;
            }

            if (!hasAttachments) {
                if (!confirm('No adjuntaste fotos. ¿Enviar igual?')) {
                    return;
                }
            }

            if (!confirm(`¿Seguro que querés enviar este reclamo a ${municipioNombre}?`)) {
                return;
            }

            const formData = new FormData(document.getElementById('reclamoForm'));
            formData.set('action', 'send');

            statusDiv.innerHTML = '<span style="color: #666;">Enviando...</span>';

            try {
                const response = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    statusDiv.innerHTML = '<span style="color: green;">✓ ' + data.message + '</span>';
                    document.getElementById('input').value = '';
                    document.getElementById('complaint').value = '';
                    localStorage.removeItem('reclamo');
                } else {
                    statusDiv.innerHTML = '<span style="color: red;">✗ ' + data.message + '</span>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<span style="color: red;">Error de conexión. Intentá de nuevo.</span>';
                console.error(error);
            }
        }
    </script>
</body>
</html>
