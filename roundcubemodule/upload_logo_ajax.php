<?php
/* upload_logo_ajax.php - Traitement AJAX de l'upload de logo */

// IMPORTANT : Ces définitions DOIVENT être faites AVANT l'include de main.inc.php
define('NOCSRFCHECK', 1);     // Désactiver la vérification CSRF
define('NOREQUIREMENU', 1);    // Pas de menu
define('NOREQUIREHTML', 1);    // Pas de HTML
define('NOREQUIREAJAX', '1');  // Mode AJAX

// Nettoyer tous les buffers de sortie existants
while (ob_get_level()) {
    ob_end_clean();
}

// Désactiver complètement l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);

// Header JSON IMMÉDIATEMENT
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Inclure Dolibarr avec le minimum nécessaire
$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";

// Fonction pour envoyer une réponse JSON et terminer
function sendJsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Vérification de l'authentification
if (!isset($user) || !$user->id) {
    sendJsonResponse(['success' => false, 'error' => 'Non authentifié']);
}

// Récupérer les paramètres
$user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$account_id = isset($_POST['logo_account_id']) ? intval($_POST['logo_account_id']) : null;

// Vérifier que l'utilisateur a les droits
if ($user_id <= 0) {
    sendJsonResponse(['success' => false, 'error' => 'ID utilisateur invalide']);
}

// Vérifier si c'est bien l'utilisateur ou un admin
if ($user->id != $user_id && !$user->admin) {
    sendJsonResponse(['success' => false, 'error' => 'Droits insuffisants']);
}

// Vérifier l'upload
if (!isset($_FILES['logo_file'])) {
    sendJsonResponse(['success' => false, 'error' => 'Aucun fichier reçu']);
}

if ($_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite PHP)',
        UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
        UPLOAD_ERR_PARTIAL => 'Upload partiel',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier',
        UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Erreur écriture disque',
        UPLOAD_ERR_EXTENSION => 'Extension PHP a arrêté l\'upload'
    ];
    
    $error_code = $_FILES['logo_file']['error'];
    $error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Erreur inconnue';
    sendJsonResponse(['success' => false, 'error' => $error_msg]);
}

$file = $_FILES['logo_file'];

// Validation du type de fichier
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    sendJsonResponse(['success' => false, 'error' => 'Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, WEBP']);
}

// Vérifier le type MIME réel
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mimes)) {
    sendJsonResponse(['success' => false, 'error' => 'Type MIME invalide']);
}

// Limite de taille : 2 MB
if ($file['size'] > 2 * 1024 * 1024) {
    sendJsonResponse(['success' => false, 'error' => 'Fichier trop volumineux (maximum 2 MB)']);
}

// Vérifier que c'est bien une image
$image_info = @getimagesize($file['tmp_name']);
if ($image_info === false) {
    sendJsonResponse(['success' => false, 'error' => 'Le fichier n\'est pas une image valide']);
}

// Créer le répertoire de destination
$base_dir = DOL_DATA_ROOT . '/doctemplates/mail/logo/user_' . $user_id;
if ($account_id) {
    $base_dir .= '/account_' . $account_id;
} else {
    $base_dir .= '/global';
}

// Créer le répertoire s'il n'existe pas
if (!is_dir($base_dir)) {
    if (!mkdir($base_dir, 0755, true)) {
        sendJsonResponse(['success' => false, 'error' => 'Impossible de créer le répertoire de destination']);
    }
}

// Générer un nom de fichier unique et sécurisé
$filename = 'logo_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
$filepath = $base_dir . '/' . $filename;

// Déplacer le fichier uploadé
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    sendJsonResponse(['success' => false, 'error' => 'Impossible de sauvegarder le fichier']);
}

// Vérifier que le fichier existe bien après le déplacement
if (!file_exists($filepath)) {
    sendJsonResponse(['success' => false, 'error' => 'Le fichier n\'a pas été correctement sauvegardé']);
}

// Construire l'URL du logo
$relative_path = 'mail/logo/user_' . $user_id;
if ($account_id) {
    $relative_path .= '/account_' . $account_id;
} else {
    $relative_path .= '/global';
}

$logo_url = DOL_URL_ROOT . '/document.php?modulepart=doctemplates&file=' . $relative_path . '/' . $filename;

// Succès - Envoyer la réponse JSON
sendJsonResponse([
    'success' => true,
    'message' => 'Logo uploadé avec succès',
    'filename' => $filename,
    'url' => $logo_url,
    'size' => filesize($filepath)
]);

// Le script ne devrait jamais arriver ici car sendJsonResponse() fait exit()
?>