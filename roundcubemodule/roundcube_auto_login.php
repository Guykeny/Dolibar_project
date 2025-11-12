<?php

// Charger l'environnement Dolibarr
$res = 0;
$paths = [
    '../../main.inc.php', 
    '../../../main.inc.php', 
    '../../../../main.inc.php'
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    die('Erreur: Impossible de charger main.inc.php');
}

// Vérifier la connexion utilisateur et les droits
if (empty($user->id)) {
    accessforbidden();
}

if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    accessforbidden('Vous n\'avez pas les droits pour accéder au webmail');
}

// Récupération de l'URL de Roundcube depuis la configuration de Dolibarr
$roundcube_url = '';
if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_url = $conf->global->ROUNDCUBE_URL;
    if (strpos($roundcube_url, 'http') !== 0) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $roundcube_url = $protocol . $_SERVER['HTTP_HOST'] . $roundcube_url;
    }
} else {
    // URL par défaut si non configurée
    $roundcube_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube/';
}

// Assurer que l'URL se termine par un slash
if (substr($roundcube_url, -1) !== '/') {
    $roundcube_url .= '/';
}

$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
error_log("DEBUG from dolibarr_to_roundcube.php, received account_id: " . $account_id);
$debug = !empty($conf->global->ROUNDCUBE_DEBUG);

// Définir le "secret" partagé. Il doit être le même que dans le plugin Roundcube.
$shared_secret = 'MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdW';


if ($account_id > 0) {
    // Récupérer les informations de signature depuis la base de données
    $sql = "SELECT email, signature_html, signature_text, logo_filename, logo_type, fk_user 
            FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_accounts 
            WHERE rowid = ".intval($account_id)." AND fk_user = ".intval($user->id);
    
    $resql = $db->query($sql);
    
    if ($resql && $obj = $db->fetch_object($resql)) {
        // Construire la signature complète avec logo
        $signature_data = array(
            'email' => $obj->email,
            'text' => $obj->signature_text,
            'html' => ''
        );
        
        // Si un logo est défini
        if (!empty($obj->logo_filename)) {
            $logo_path = '/doctemplates/mail/logo/user_' . $obj->fk_user;
            if ($obj->logo_type == 'account') {
                $logo_path .= '/account_' . $account_id;
            } else {
                $logo_path .= '/global';
            }
            $logo_path .= '/' . $obj->logo_filename;
            
            // URL complète du logo
            $logo_url = DOL_MAIN_URL_ROOT . '/document.php?modulepart=doctemplates&file=' . urlencode($logo_path);
            
            // Ajouter le logo au début de la signature HTML
            $signature_data['html'] = '<div style="margin-bottom: 10px;">
                <img src="' . htmlspecialchars($logo_url) . '" style="max-width: 200px; max-height: 100px; height: auto;">
            </div>';
        }
        
        // Ajouter la signature HTML ou convertir la signature texte en HTML
        if (!empty($obj->signature_html)) {
            $signature_data['html'] .= $obj->signature_html;
        } elseif (!empty($obj->signature_text)) {
            $signature_data['html'] .= '<div>' . nl2br(htmlspecialchars($obj->signature_text)) . '</div>';
        }
        
        // Encoder les signatures pour les passer via l'URL
        $signature_encoded = base64_encode(json_encode($signature_data));
        
        // Créer une session temporaire pour stocker les signatures
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['dolibarr_signature_' . $account_id] = $signature_data;
        $_SESSION['dolibarr_signature_timestamp'] = time();
    }
}


// Construction de l'URL de redirection avec les paramètres attendus par le plugin
$separator = (strpos($roundcube_url, '?') === false) ? '?' : '&';

$redirect_url = $roundcube_url . $separator .
               '_autologin=1' .
               '&secret=' . urlencode($shared_secret) .
               '&dolibarr_id=' . urlencode($user->id);

// Ajouter account_id seulement s'il est spécifié et valide (supérieur à 0)
if (!empty($account_id) && $account_id > 0) {
    $redirect_url .= '&account_id=' . urlencode($account_id);
    
    // Ajouter le flag de signature si on a des données
    if (isset($signature_encoded)) {
        $redirect_url .= '&sig_session=' . session_id();
    }
    
    error_log("Redirection avec account_id: " . $account_id);
} else {
    error_log("Redirection sans account_id (utilisation du compte par défaut)");
}

// Mode debug : afficher les informations au lieu de rediriger
if ($debug) {
    echo "<h1>Debug Autologin</h1>";
    echo "<p>Account ID: " . ($account_id ?: 'Non spécifié') . "</p>";
    if (isset($signature_data)) {
        echo "<h3>Signature trouvée:</h3>";
        echo "<p>Email: " . htmlspecialchars($signature_data['email']) . "</p>";
        echo "<p>Signature texte: <pre>" . htmlspecialchars($signature_data['text']) . "</pre></p>";
        echo "<p>Signature HTML: <div style='border:1px solid #ccc; padding:10px;'>" . $signature_data['html'] . "</div></p>";
    }
    echo "<p>Redirection vers : <a href=\"$redirect_url\">$redirect_url</a></p>";
    echo "</body></html>";
    exit;
}

// Redirection automatique
header('Location: ' . $redirect_url);
exit;
?>