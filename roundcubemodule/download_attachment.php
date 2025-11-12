<?php
// download_attachment.php - Téléchargement des pièces jointes

require '../../main.inc.php';

// Récupération des paramètres
$attachmentId = GETPOST('attachmentId', 'int');

// Vérification de sécurité de base
if (!$user->hasRight('user', 'user', 'read')) {
    accessforbidden();
}

// Vérifier que l'ID de la pièce jointe est bien présent
if ($attachmentId <= 0) {
    http_response_code(400);
    echo 'Paramètre attachmentId invalide.';
    exit;
}
function cleanAttachmentName($filename) {
    // Supprimer les informations de taille : (~2.3 Mo), (~301 ko), etc.
    $filename = preg_replace('/\(~[\d,.]+ ?[kmgtpezy]?[bo]\)$/i', '', $filename);
    
    // Supprimer d'autres formats possibles : (2.34 MB), (301 KB), etc.
    $filename = preg_replace('/\([\d,.]+ ?[kmgtpezy]?b\)$/i', '', $filename);
    
    // Supprimer les caractères problématiques mais garder les accents
    $filename = preg_replace('/[<>:"|?*~]/', '_', $filename);
    
    // Nettoyer les espaces multiples et trim
    $filename = preg_replace('/\s+/', ' ', trim($filename));
    
    // Si le nom est vide, utiliser un nom par défaut
    if (empty($filename) || $filename === '.') {
        $filename = 'attachment_' . time() . '.bin';
    }
    
    return $filename;
}
// Téléchargement direct depuis la base de données
$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "mailboxmodule_attachment WHERE rowid = " . ((int)$attachmentId);
$resql = $db->query($sql);

if (!$resql || !($att = $db->fetch_object($resql))) {
    http_response_code(404);
    echo 'Pièce jointe non trouvée en base de données.';
    exit;
}

$fullPath = DOL_DATA_ROOT . '/' . $att->filepath;

// Vérification de sécurité : éviter les traversées de répertoires
if (strpos($att->filepath, '..') !== false) {
    http_response_code(403);
    echo 'Chemin invalide détecté.';
    exit;
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo 'Fichier physique non trouvé: ' . $fullPath;
    exit;
}

try {
    // Déterminer le type MIME
    $mimeType = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detectedMime = mime_content_type($fullPath);
        if ($detectedMime !== false) {
            $mimeType = $detectedMime;
        }
    }

    // Nettoyer le nom de fichier pour éviter les problèmes
    $cleanFilename = cleanAttachmentName($att->original_name);

    // En-têtes de téléchargement
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . addslashes($cleanFilename) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Lire et envoyer le fichier
    readfile($fullPath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Erreur lors de la lecture du fichier : ' . $e->getMessage();
}

$db->close();
exit;
?>