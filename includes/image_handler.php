<?php
// includes/image_handler.php

function uploadImage($file, $targetDir, $prefix = 'img_')
{
    // 1. Check Error Code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Return appropriate error message or throw exception
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                return ['error' => 'Arquivo excede o tamanho máximo (php.ini).'];
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'Arquivo excede o tamanho máximo do formulário.'];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => 'Upload interrompido.'];
            case UPLOAD_ERR_NO_FILE:
                return ['error' => 'Nenhum arquivo enviado.'];
            default:
                return ['error' => 'Erro desconhecido no upload.'];
        }
    }

    // 2. Validate MIME Type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        return ['error' => 'Formato de arquivo inválido. Apenas JPG, PNG, GIF, WEBP e BMP.'];
    }

    // 3. Process Image (Convert to JPG if needed/possible)
    // Preference: Convert everything to JPG (or PNG) to "Correct Extension" issues as user requested.
    $finalExt = 'jpg'; // Standardize to JPG for maximum compatibility

    // COLLISION PROOF GENERATOR
    do {
        $finalName = $prefix . uniqid() . '_' . rand(1000, 9999) . '.' . $finalExt;
        $targetPath = $targetDir . '/' . $finalName;
    } while (file_exists($targetPath));

    // Check if GD is available
    if (extension_loaded('gd')) {
        $src = null;
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $src = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($file['tmp_name']);
                break;
            case 'image/bmp':
                $src = imagecreatefrombmp($file['tmp_name']);
                break;
        }

        if ($src) {
            // Helper to handle transparency if saving as JPG (replace transparent with white)
            $width = imagesx($src);
            $height = imagesy($src);
            $output = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($output, 255, 255, 255);
            imagefilledrectangle($output, 0, 0, $width, $height, $white);
            imagecopy($output, $src, 0, 0, 0, 0, $width, $height);

            // Save as JPG (Quality 90)
            if (imagejpeg($output, $targetPath, 90)) {
                imagedestroy($src);
                imagedestroy($output);
                return ['success' => true, 'filename' => $finalName];
            }
        }
    }

    // Fallback if GD fails or not installed: Move and Rename (Keep original ext if GD failed, but we forced jpg above.. let's fallback to original ext)
    $fallbackExt = $allowedMimes[$mime];
    do {
        $fallbackName = $prefix . uniqid() . '_' . rand(1000, 9999) . '.' . $fallbackExt;
    } while (file_exists($targetDir . '/' . $fallbackName));

    if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fallbackName)) {
        return ['success' => true, 'filename' => $fallbackName];
    }

    return ['error' => 'Falha ao mover arquivo. Verifique permissões da pasta.'];
}

/**
 * Downloads an image from a URL and processes it as a JPG
 */
function uploadImageFromUrl($url, $targetDir, $prefix = 'url_')
{
    if (empty($url))
        return ['error' => 'URL vazia.'];

    // 1. Fetch content
    $context = stream_context_create([
        "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36"]
    ]);

    $content = @file_get_contents($url, false, $context);
    if (!$content)
        return ['error' => 'Não foi possível baixar a imagem da URL.'];

    // 2. Save temporary file to validate
    $tmpPath = sys_get_temp_dir() . '/' . uniqid('rem_');
    file_put_contents($tmpPath, $content);

    // 3. Mock a $_FILES array to reuse uploadImage logic
    $mockFile = [
        'name' => basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg',
        'type' => '', // Will be detected by finfo in uploadImage
        'tmp_name' => $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($content)
    ];

    $result = uploadImage($mockFile, $targetDir, $prefix);

    // 4. Cleanup temp file
    if (file_exists($tmpPath))
        @unlink($tmpPath);

    return $result;
}
?>