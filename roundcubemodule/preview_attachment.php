<?php
define('NOCSRFCHECK', 1);
require '../../main.inc.php';

// Récupération du paramètre
$attachmentId = GETPOST('attachmentId', 'int');

if ($attachmentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre attachmentId invalide.']);
    exit;
}

try {
    // Requête SQL pour récupérer les informations de la pièce jointe
    $sql = "SELECT filepath, original_name, mimetype, filesize 
            FROM " . MAIN_DB_PREFIX . "mailboxmodule_attachment 
            WHERE rowid = " . (int)$attachmentId;
    
    $resql = $db->query($sql);
    
    if (!$resql || !($obj = $db->fetch_object($resql))) {
        http_response_code(404);
        throw new Exception("Pièce jointe non trouvée (ID: $attachmentId)");
    }
    
    // Construction du chemin complet
    $fullPath = DOL_DATA_ROOT . '/' . $obj->filepath;
    
    // Vérification de l'existence du fichier
    if (!file_exists($fullPath)) {
        http_response_code(404);
        throw new Exception("Fichier physique non trouvé: " . $fullPath);
    }
    
    // Déterminer le type MIME
    $mimeType = $obj->mimetype;
    if (empty($mimeType)) {
        // Utiliser finfo pour une détection plus fiable
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($fullPath);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($fullPath);
        } else {
            // Fallback basé sur l'extension
            $ext = strtolower(pathinfo($obj->original_name, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain',
                'html' => 'text/html',
                'htm' => 'text/html'
            ];
            $mimeType = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
        }
    }
    
    // Nettoyer les buffers de sortie existants
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // En-têtes HTTP pour afficher le fichier
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($fullPath));
    
    // Déterminer si le fichier peut être affiché inline
    $inlineTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain',
        'text/html'
    ];
    
    if (in_array($mimeType, $inlineTypes)) {
        header('Content-Disposition: inline; filename="' . basename($obj->original_name) . '"');
    } else {
        // Pour les autres types, forcer le téléchargement
        header('Content-Disposition: attachment; filename="' . basename($obj->original_name) . '"');
    }
    
    // En-têtes de cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Pour débugger (commentez ces lignes en production)
    error_log("Preview attachment - ID: $attachmentId");
    error_log("Filepath: " . $obj->filepath);
    error_log("Full path: $fullPath");
    error_log("MIME type: $mimeType");
    error_log("Original name: " . $obj->original_name);
    
    // Lire et envoyer le fichier
    $handle = fopen($fullPath, 'rb');
    if ($handle !== false) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        throw new Exception("Impossible d'ouvrir le fichier: $fullPath");
    }
    
} catch (Exception $e) {
    // Log de l'erreur
    error_log("Erreur dans preview_attachment.php: " . $e->getMessage());
    
    // Afficher un message d'erreur dans l'iframe
    http_response_code(500);
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Erreur</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 20px; 
                background-color: #f5f5f5;
            }
            .error { 
                color: #d9534f; 
                padding: 15px;
                background-color: #f9f9f9;
                border: 1px solid #d9534f;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class="error">
            <h3>Erreur de prévisualisation</h3>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p>ID demandé: ' . $attachmentId . '</p>
        </div>
    </body>
    </html>';
}

$db->close();
exit;
?>