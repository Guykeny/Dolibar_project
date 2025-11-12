<?php

// Initialisation de l'environnement Dolibarr

define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
date_default_timezone_set('Europe/Paris');
$dolibarr_main_document_root = dirname(dirname(dirname(__DIR__)));
$main_inc_path = $dolibarr_main_document_root . '/main.inc.php';
if (!file_exists($main_inc_path)) {
    $main_inc_path = $dolibarr_main_document_root . '/htdocs/main.inc.php';
    if (!file_exists($main_inc_path)) {
        http_response_code(500);
        die(json_encode(['status' => 'ERROR', 'message' => 'main.inc.php non trouvé.']));
    }
}
require_once $main_inc_path;
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Fonctions améliorées pour le téléchargement des pièces jointes
function downloadAttachmentFromUrl($url) {
    error_log("=== DÉBUT TÉLÉCHARGEMENT ===");
    error_log("URL: " . $url);
    
    // Vérifier si l'URL contient les paramètres nécessaires
    $parsed_url = parse_url($url);
    if (!$parsed_url || empty($parsed_url['query'])) {
        error_log("ERREUR: URL mal formée");
        return false;
    }
    
    parse_str($parsed_url['query'], $params);
    $token = $params['_token'] ?? '';
    $uid = $params['_uid'] ?? '';
    $part = $params['_part'] ?? '';
    
    error_log("Paramètres extraits - Token: $token, UID: $uid, Part: $part");
    
    if (empty($token) || empty($uid) || empty($part)) {
        error_log("ERREUR: Paramètres manquants dans l'URL");
        return false;
    }
    
    // Méthode 1: cURL avec authentification complète
    $data = downloadWithImprovedCurl($url, $token);
    
    if ($data !== false && strlen($data) > 100) { // Minimum 100 bytes pour un fichier valide
        error_log("SUCCESS: Téléchargement réussi via cURL (" . strlen($data) . " bytes)");
        return $data;
    }
    
    // Méthode 2: file_get_contents avec contexte amélioré
    $data = downloadViaImprovedFileGetContents($url, $token);
    
    if ($data !== false && strlen($data) > 100) {
        error_log("SUCCESS: Téléchargement réussi via file_get_contents (" . strlen($data) . " bytes)");
        return $data;
    }
    
    error_log("ÉCHEC: Toutes les méthodes de téléchargement ont échoué");
    return false;
}

function downloadWithImprovedCurl($url, $token) {
    $ch = curl_init();
    
    // Récupérer tous les cookies disponibles
    $cookies = [];
    
    // Cookie de session PHP
    if (session_id()) {
        $cookies[] = session_name() . '=' . session_id();
    }
    
    // Cookies spécifiques Roundcube (si disponibles dans $_COOKIE)
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'roundcube') !== false || strpos($name, 'rcmail') !== false) {
                $cookies[] = $name . '=' . $value;
            }
        }
    }
    
    $cookie_string = implode('; ', $cookies);
    error_log("Cookies envoyés: " . $cookie_string);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_COOKIE => $cookie_string,
        CURLOPT_HTTPHEADER => [
            'Accept: application/octet-stream,*/*,text/html,application/xhtml+xml',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            'X-Roundcube-Request: ' . $token,
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . dirname($url) . '/',
        ],
    ]);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("cURL Response - HTTP: $httpCode, Content-Type: $contentType, Size: " . strlen($data));
    
    if ($error) {
        error_log("cURL Error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("HTTP Error Code: $httpCode");
        return false;
    }
    
    // Vérifier si on a reçu du HTML (page d'erreur/login)
    if (strpos($contentType, 'text/html') !== false || 
        strpos($data, '<!DOCTYPE') !== false || 
        strpos($data, '<html') !== false) {
        error_log("ERREUR: Page HTML reçue au lieu du fichier");
        return false;
    }
    
    if (strlen($data) < 50) {
        error_log("ERREUR: Fichier trop petit (" . strlen($data) . " bytes)");
        return false;
    }
    
    return $data;
}

function downloadViaImprovedFileGetContents($url, $token) {
    $cookies = [];
    
    if (session_id()) {
        $cookies[] = session_name() . '=' . session_id();
    }
    
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'roundcube') !== false || strpos($name, 'rcmail') !== false) {
                $cookies[] = $name . '=' . $value;
            }
        }
    }
    
    $cookie_string = implode('; ', $cookies);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Cookie: ' . $cookie_string,
                'X-Roundcube-Request: ' . $token,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/octet-stream,*/*',
            ],
            'timeout' => 30,
            'follow_location' => 1,
        ]
    ]);
    
    $data = @file_get_contents($url, false, $context);
    
    if ($data === false) {
        error_log("file_get_contents failed");
        return false;
    }
    
    if (strpos($data, '<!DOCTYPE') !== false || strpos($data, '<html') !== false) {
        error_log("ERREUR file_get_contents: Page HTML reçue");
        return false;
    }
    
    if (strlen($data) < 50) {
        error_log("ERREUR file_get_contents: Fichier trop petit");
        return false;
    }
    
    return $data;
}
// Version corrigée
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
// FONCTION UNIQUE ET CORRIGÉE pour sauvegarder les pièces jointes
function saveAttachmentData($data, $original_name, $mail_id, $uid, $db, $attachments_dir) {
    // Nettoyer le nom de fichier
    $safe_name = cleanAttachmentName($original_name);
    
    // Créer un nom de fichier unique avec timestamp et aléatoire
    $timestamp = time();
    $random = rand(100, 999);
    $dest_filename = $uid . '_' . $timestamp . '_' . $random . '_' . $safe_name;
    $dest_path = $attachments_dir . $dest_filename;
    
    // Écrire le fichier
    if (file_put_contents($dest_path, $data) === false) {
        error_log("Failed to write attachment to: " . $dest_path);
        return false;
    }
    
    $relative_path = 'data/fichier_join/' . $dest_filename;
    
    // Insertion en base avec gestion d'erreur
    $sql_att = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_attachment
        (fk_mail, filename, filepath, original_name, filesize,datec)
        VALUES (
            ".intval($mail_id).",
            '".$db->escape($dest_filename)."',
            '".$db->escape($relative_path)."',
            '".$db->escape($original_name)."',
            ".strlen($data).",
            '".$db->idate(dol_now())."'
        )";
    
    if (!$db->query($sql_att)) {
        error_log("DB error for attachment: " . $db->lasterror());
        // Supprimer le fichier en cas d'erreur DB
        @unlink($dest_path);
        return false;
    }
    
    error_log("Attachment saved successfully: " . $dest_filename . " (" . strlen($data) . " bytes)");
    error_log("Relative path in DB: " . $relative_path);
    
    // RETOURNER le nom de fichier créé pour l'utiliser ailleurs
    return $dest_filename;
}

header('Content-Type: application/json; charset=utf-8');
global $db, $conf, $user;
ob_start();

try {
    // === Lecture et validation des données JSON ===
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Pas de données reçues");

    // === Validation et normalisation des données ===
    $uid = $db->escape($input['uid'] ?? '');
    $mbox = $db->escape($input['mbox'] ?? 'INBOX');
    $message_id = $db->escape($input['message_id'] ?? '');
    $subject = $db->escape($input['subject'] ?? 'Sans sujet');
    $date = $input['date'] ?? dol_now();
    $from_raw = $input['from_email'] ?? '';
    $raw_email_content = $input['raw_email'] ?? null;
    $attachments = $input['attachments'] ?? [];
    $links = $input['links'] ?? [];
    $action = $input['action'] ?? null;
    $direction = $input['direction'] ?? 'received';
    $to_emails = $input['to'] ?? '';
    
    // Logs de debug
    error_log("=== DÉMARRAGE TRAITEMENT MAIL ===");
    error_log("Nombre de pièces jointes reçues: " . count($attachments));
    error_log("Session ID: " . session_id());
    
    $valid_directions = ['received', 'sent'];
    if (!in_array($direction, $valid_directions)) {
        $direction = 'received';
    }

    // === Contrôles des données obligatoires ===
    if (!is_array($links)) $links = [];
    if (empty($raw_email_content)) throw new Exception("Contenu brut de l'e-mail manquant.");

    // === Adaptation selon la direction ===
    if ($direction === 'sent') {
        if (empty($from_raw)) {
            $from_raw = getUserEmailFromDolibarr($user, $db);
        }
        
        if ($mbox === 'INBOX') {
            $mbox = 'Sent';
        }
        
        if (empty($uid) || strpos($uid, 'sent_') === 0 || strpos($uid, 'compose_') === 0) {
            $uid = 'sent_' . time() . '_' . rand(1000, 9999);
        }
    } else {
        if (empty($from_raw)) throw new Exception("Champ 'from_email' vide ou absent");
    }

    // === Validation et extraction de l'email ===
    if (preg_match('/<([^>]+)>/', $from_raw, $matches)) {
        $from_email = $db->escape(trim($matches[1]));
    } else {
        $from_email = $db->escape(trim($from_raw));
    }
    if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email d'expéditeur invalide: $from_email");
    }

    // === Validation des liens ===
    foreach ($links as $index => $link) {
        if (empty($link['type']) || empty($link['id'])) {
            throw new Exception("Lien invalide à l'index $index : type et id requis");
        }
        
        if (!validate_linked_object($link['type'], $link['id'], $db)) {
            throw new Exception("L'objet {$link['type']} avec l'ID {$link['id']} n'existe pas");
        }
    }

    // === Validation format de date ===
    if (!empty($input['date'])) {
        if (is_numeric($input['date'])) {
            $date = (int)$input['date'];
        } else {
            $timestamp = strtotime($input['date']);
            if ($timestamp === false || $timestamp <= 0) {
                error_log("Format de date invalide reçu : " . $input['date']);
                $date = dol_now();
            } else {
                $date = $timestamp;
            }
        }
    } else {
        $date = dol_now();
    }

    // === Vérification d'existence adaptée selon la direction ===
    if ($direction === 'sent') {
        $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                      WHERE message_id='".$message_id."' AND direction='sent'";
    } else {
        $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                      WHERE message_id='".$message_id."' 
                      OR (imap_uid=".(int)$uid." AND imap_mailbox='".$mbox."')";
    }
    
    $res = $db->query($sql_check);
    
    // --- Logique si le mail existe déjà ---
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $mail_id = $obj->rowid;
        
        $existing = getExistingLinks($mail_id, $db);

        $proposed = [];
        foreach ($links as $lnk) {
             $proposed[] = ['type' => $lnk['type'], 'id' => (int)$lnk['id']];
        }
        $to_add = array_udiff($proposed, $existing, function ($a, $b) { return ($a['type'] <=> $b['type']) ?: ($a['id'] <=> $b['id']); });
        $to_delete = array_udiff($existing, $proposed, function ($a, $b) { return ($a['type'] <=> $b['type']) ?: ($a['id'] <=> $b['id']); });

        if (empty($to_add) && empty($to_delete)) {
            $existingWithNames = getExistingLinksWithNames($mail_id, $db);
            echo json_encode([
                'status'=>'ALREADY_CLASSIFIED',
                'message'=>'Mail déjà classé',
                'mail_id'=>$mail_id,
                'existing_links'=>$existingWithNames
            ]);
            exit;
        }

        // Gestion des actions (add_links, delete_links, sync_links)
        if ($action === 'add_links') {
            foreach ($to_add as $lnk) {
                $target_name = get_module_name($lnk['type'], $lnk['id'], $db);
                
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links (fk_mail, target_type, target_id, target_name) 
                        VALUES (".$mail_id.", '".$db->escape($lnk['type'])."', ".(int)$lnk['id'].", '".$db->escape($target_name)."')";
                if (!$db->query($sql)) {
                    throw new Exception("Erreur ajout lien : ".$db->lasterror());
                }
            }
            echo json_encode(['status'=>'UPDATED','message'=>'Liens ajoutés']);
            exit;
        } elseif ($action === 'delete_links') {
             foreach ($to_delete as $ex) {
                 $sql = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links 
                         WHERE fk_mail=".$mail_id." AND target_type='".$db->escape($ex['type'])."' AND target_id=".(int)$ex['id'];
                 if (!$db->query($sql)) {
                     throw new Exception("Erreur suppression lien : ".$db->lasterror());
                 }
            }
            echo json_encode(['status'=>'UPDATED','message'=>'Liens supprimés']);
            exit;
        } elseif ($action === 'sync_links') {
            $sql_delete = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links WHERE fk_mail=".$mail_id;
                if (!$db->query($sql_delete)) {
                    throw new Exception("Erreur suppression liens : ".$db->lasterror());
                }
                
                // 2. Si aucun nouveau lien n'est fourni, supprimer le mail complètement
                if (empty($proposed) || count($proposed) == 0) {
                    error_log("Aucun lien proposé, suppression complète du mail ID: $mail_id");
                    
                    // 2a. Récupérer les infos du mail avant suppression
                    $sql_mail = "SELECT file_path FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                                WHERE rowid=".$mail_id;
                    $res_mail = $db->query($sql_mail);
                    $mail_info = $db->fetch_object($res_mail);
                    
                    // 2b. Récupérer et supprimer les pièces jointes
                    $sql_att = "SELECT filepath FROM ".MAIN_DB_PREFIX."mailboxmodule_attachment 
                                WHERE fk_mail=".$mail_id;
                    $res_att = $db->query($sql_att);
                    
                    while ($att = $db->fetch_object($res_att)) {
                        // Supprimer le fichier physique de la pièce jointe
                        $att_full_path = DOL_DATA_ROOT . '/' . $att->filepath;
                        if (file_exists($att_full_path)) {
                            @unlink($att_full_path);
                            error_log("Pièce jointe supprimée : " . $att_full_path);
                        }
                    }
                    
                    // 2c. Supprimer les enregistrements des pièces jointes de la base
                    $sql_del_att = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_attachment 
                                    WHERE fk_mail=".$mail_id;
                    $db->query($sql_del_att);
                    
                    // 2d. Supprimer le fichier .eml physique
                    if ($mail_info && $mail_info->file_path) {
                        $eml_full_path = DOL_DATA_ROOT . '/' . $mail_info->file_path;
                        if (file_exists($eml_full_path)) {
                            @unlink($eml_full_path);
                            error_log("Fichier EML supprimé : " . $eml_full_path);
                        }
                    }
                    
                    // 2e. Supprimer aussi les fichiers copiés dans les modules (ecm_files)
                    $sql_ecm = "SELECT filepath, filename FROM ".MAIN_DB_PREFIX."ecm_files 
                                WHERE label LIKE '%Email%".$db->escape($subject)."%'
                                OR label LIKE '%Pièce jointe%'";
                    $res_ecm = $db->query($sql_ecm);
                    
                    while ($ecm = $db->fetch_object($res_ecm)) {
                        $ecm_path = DOL_DATA_ROOT . '/' . $ecm->filepath . '/' . $ecm->filename;
                        if (file_exists($ecm_path)) {
                            @unlink($ecm_path);
                            error_log("Fichier ECM supprimé : " . $ecm_path);
                        }
                    }
                    
                    // Supprimer les entrées ecm_files
                    $sql_del_ecm = "DELETE FROM ".MAIN_DB_PREFIX."ecm_files 
                                    WHERE label LIKE '%Email%".$db->escape($subject)."%'
                                    OR label LIKE '%Pièce jointe%'";
                    $db->query($sql_del_ecm);
                    
                    // 2f. Finalement, supprimer le mail de la base
                    $sql_del = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
                                WHERE rowid=".$mail_id;
                    if (!$db->query($sql_del)) {
                        throw new Exception("Erreur suppression mail : ".$db->lasterror());
                    }
                    
                    echo json_encode([
                        'status'=>'DELETED',
                        'message'=>'Mail supprimé complètement (aucun lien)',
                        'mail_id'=>$mail_id,
                        'reason'=>'no_links'
                    ]);
                    exit;
                }
                
                // 3. Sinon, ajouter les nouveaux liens
                foreach ($proposed as $lnk) {
                    $target_name = get_module_name($lnk['type'], $lnk['id'], $db);
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links (fk_mail, target_type, target_id, target_name) 
                            VALUES (".$mail_id.", '".$db->escape($lnk['type'])."', ".(int)$lnk['id'].", '".$db->escape($target_name)."')";
                    if (!$db->query($sql)) {
                        throw new Exception("Erreur synchronisation liens : ".$db->lasterror());
                    }
                }
                
                echo json_encode(['status'=>'UPDATED','message'=>'Liens synchronisés']);
                exit;
        } else {
            $existingWithNames = getExistingLinksWithNames($mail_id, $db);
            echo json_encode([
                'status'=>'DIFFERENT_LINKS',
                'message'=>'Le mail est déjà classé différemment',
                'mail_id'=>$mail_id,
                'existing'=>$existingWithNames,
                'proposed'=>$proposed,
                'to_add'=>$to_add,
                'to_delete'=>$to_delete
            ]);
            exit;
        }
    }

    // --- Mail nouveau (n'existe pas) ---

    // Sauvegarde du .eml
    $data_dir = DOL_DATA_ROOT . '/data/mails/';
    if (!is_dir($data_dir)) {
        if (!mkdir($data_dir, 0775, true)) {
            throw new Exception("Impossible de créer le répertoire : " . $data_dir);
        }
    }
    
    $filename_base = preg_replace('/[^\w\s\-\.]/', '', $subject);
    $filename_base = substr($filename_base, 0, 50);
    if (empty($filename_base)) {
        $filename_base = ($direction === 'sent' ? 'sent_email_' : 'email_') . md5($uid . microtime());
    }
    $filename_eml = $filename_base . '_' . time() . '_' . $direction . '.eml';
    $full_file_path = $data_dir . $filename_eml;
    
    if (file_put_contents($full_file_path, $raw_email_content) === false) {
        throw new Exception("Impossible d'écrire le fichier EML : " . $full_file_path);
    }
    $relative_file_path = 'data/mails/' . $filename_eml;

    // === Recherche du tiers adaptée selon la direction ===
    $fk_soc = null;
    $tiers_name = '';
    
    if ($direction === 'sent') {
        $to_emails_array = extractEmailsFromTo($to_emails);
        
        foreach ($to_emails_array as $to_email) {
            $sql_soc = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE email = '".$db->escape($to_email)."'";
            $resql_soc = $db->query($sql_soc);
            if ($resql_soc && $db->num_rows($resql_soc) > 0) {
                $obj_soc = $db->fetch_object($resql_soc);
                $fk_soc = $obj_soc->rowid;
                $tiers_name = $obj_soc->nom;
                break;
            }
        }
    } else {
        $sql_soc = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE email = '".$from_email."'";
        $resql_soc = $db->query($sql_soc);
        if ($resql_soc && $db->num_rows($resql_soc) > 0) {
            $obj_soc = $db->fetch_object($resql_soc);
            $fk_soc = $obj_soc->rowid;
            $tiers_name = $obj_soc->nom;
        }
    }

    // === Insertion du mail dans la table principale ===
    $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail 
                   (message_id, subject, from_email, date_received, file_path, fk_soc, imap_mailbox, imap_uid, direction)
                   VALUES ('".$message_id."', '".$subject."', '".$from_email."', '".$db->idate($date)."', 
                           '".$db->escape($relative_file_path)."', ".($fk_soc !== null ? intval($fk_soc) : "NULL").", 
                           '".$mbox."', ".(int)$uid.", '".$db->escape($direction)."')";
    
    if (!$db->query($sql_insert)) {
        throw new Exception("Erreur insertion mail : ".$db->lasterror());
    }
    
    $new_mail_id = $db->last_insert_id(MAIN_DB_PREFIX."mailboxmodule_mail", 'rowid');
    if (!$new_mail_id) {
        throw new Exception("Impossible de récupérer l'ID du nouveau mail");
    }
   
    $attachments_dir = DOL_DATA_ROOT . '/data/fichier_join/';
    if (!is_dir($attachments_dir)) {
        if (!mkdir($attachments_dir, 0775, true)) {
            throw new Exception("Impossible de créer le répertoire des pièces jointes : " . $attachments_dir);
        }
    }

    // === TRAITEMENT DES PIÈCES JOINTES AVANT LES LIENS ===
    $nb_attachments = 0;
    $processed_attachments = [];
    $saved_attachments_info = []; // Stocker les infos des pièces jointes sauvegardées

    foreach ($attachments as $index => $att) {
    $name = isset($att['name']) ? trim($att['name']) : 'unknown.bin';
    $source = $att['source'] ?? 'unknown';
    $download_url = $att['download_url'] ?? '';
    
    error_log("=== TRAITEMENT PIÈCE JOINTE $index ===");
    error_log("Nom: $name");
    error_log("Source: $source");
    error_log("URL: $download_url");
    
    // CAS 1: Contenu direct en base64 (depuis le plugin PHP)
    if (empty($download_url) && (!empty($att['content']) || !empty($att['data']))) {
        error_log("Contenu direct détecté (base64)");
        
        $content_base64 = $att['content'] ?? $att['data'];
        $attachment_data = base64_decode($content_base64);
        
        if ($attachment_data !== false && strlen($attachment_data) > 0) {
            $clean_name = preg_replace('/\(~\d+\s*[kmgtpezy]?[bo]\)$/i', '', $name);
            $clean_name = trim($clean_name);
            
            if (empty($clean_name)) {
                $clean_name = 'attachment_' . $index . '.bin';
            }
            
            $saved_filename = saveAttachmentData($attachment_data, $clean_name, $new_mail_id, $uid, $db, $attachments_dir);
            if ($saved_filename) {
                $nb_attachments++;
                
                $saved_attachments_info[] = [
                    'path' => $attachments_dir . $saved_filename,
                    'name' => $clean_name,
                    'filename' => $saved_filename
                ];
                
                error_log("SUCCESS: Pièce jointe sauvegardée depuis contenu direct - $clean_name");
            } else {
                error_log("ERREUR: Échec sauvegarde contenu direct - $clean_name");
            }
        } else {
            error_log("ERREUR: Décodage base64 échoué");
        }
        continue;
    }
    
    // CAS 2: URL à télécharger (depuis JavaScript)
    if (empty($download_url)) {
        error_log("IGNORER: Ni URL ni contenu direct");
        continue;
    }
    
    $clean_name = preg_replace('/\(~\d+\s*[kmgtpezy]?[bo]\)$/i', '', $name);
    $clean_name = trim($clean_name);
    
    if (empty($clean_name)) {
        $clean_name = 'attachment_' . $index . '.bin';
    }
    
    $attachment_key = md5($download_url);
    if (in_array($attachment_key, $processed_attachments)) {
        error_log("IGNORER: Pièce jointe déjà traitée");
        continue;
    }
    $processed_attachments[] = $attachment_key;
    
    $attachment_data = downloadAttachmentFromUrl($download_url);
    
    if ($attachment_data !== false) {
        $saved_filename = saveAttachmentData($attachment_data, $clean_name, $new_mail_id, $uid, $db, $attachments_dir);
        if ($saved_filename) {
            $nb_attachments++;
            
            $saved_attachments_info[] = [
                'path' => $attachments_dir . $saved_filename,
                'name' => $clean_name,
                'filename' => $saved_filename
            ];
            
            error_log("SUCCESS: Pièce jointe sauvegardée depuis URL - $clean_name ($saved_filename)");
        } else {
            error_log("ERREUR: Échec sauvegarde - $clean_name");
        }
    } else {
        error_log("ERREUR: Échec téléchargement - $clean_name");
    }
}

    error_log("=== RÉSUMÉ PIÈCES JOINTES ===");
    error_log("Pièces jointes traitées avec succès: $nb_attachments");

    // === INSERTION DES LIENS ET COPIE VERS LES MODULES ===
    foreach ($links as $link) {
        $link_type = $db->escape($link['type']);
        $link_id = (int)$link['id'];
        $target_name = get_module_name($link_type, $link_id, $db);
        
        $sql_link = "INSERT INTO ".MAIN_DB_PREFIX."mailboxmodule_mail_links (fk_mail, target_type, target_id, target_name)
                     VALUES (".$new_mail_id.", '".$link_type."', ".$link_id.", '".$db->escape($target_name)."')";
        if (!$db->query($sql_link)) {
            throw new Exception("Erreur insertion mailboxmodule_mail_links : ".$db->lasterror());
        }
        
        error_log("=== COPIE VERS MODULE $link_type (ID: $link_id) ===");
        error_log("Pièces jointes à copier: " . count($saved_attachments_info));
        
        // Utiliser les infos des pièces jointes déjà sauvegardées
        save_files_to_module($link_type, $link_id, $full_file_path, $filename_eml, $subject, $saved_attachments_info, $conf, $db);
    }
    
    // Message de succès adapté
    $msg = "Mail " . ($direction === 'sent' ? 'envoyé' : 'reçu') . " UID=$uid enregistré. ";
    if ($nb_attachments > 0) $msg .= "$nb_attachments pièce(s) jointe(s) sauvegardée(s). ";
    if ($fk_soc) $msg .= "Lié au tiers : $tiers_name (ID=$fk_soc).";
    if (count($links) > 0) $msg .= " Mail lié à ".count($links)." module(s).";
    
    echo json_encode([
        'status' => 'OK', 
        'message' => $msg, 
        'mail_id' => $new_mail_id,
        'direction' => $direction,
        'attachments_count' => $nb_attachments
    ]);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}

exit();

// --- Fonctions utilitaires ---

function getExistingLinks($mail_id, $db) {
    $existing = [];
    
    $sql_links = "SELECT target_type, target_id 
                  FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links 
                  WHERE fk_mail=".(int)$mail_id;
    $res_links = $db->query($sql_links);
    
    if ($res_links) {
        while ($row = $db->fetch_object($res_links)) {
            $existing[] = ['type' => $row->target_type, 'id' => (int)$row->target_id];
        }
    }
    
    return $existing;
}

function getExistingLinksWithNames($mail_id, $db) {
    $existing = [];
    
    $sql_links = "SELECT target_type, target_id, target_name 
                  FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links 
                  WHERE fk_mail=".(int)$mail_id;
    $res_links = $db->query($sql_links);
    
    if ($res_links) {
        while ($row = $db->fetch_object($res_links)) {
            $existing[] = [
                'target_type' => $row->target_type,
                'target_id' => (int)$row->target_id,
                'target_name' => $row->target_name
            ];
        }
    }
    
    return $existing;
}

function get_module_name($type, $id, $db) {
    $name = '';
    switch ($type) {
        case 'propal':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."propal WHERE rowid=".$id;
            break;
        case 'commande':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."commande WHERE rowid=".$id;
            break;
        case 'facture':
        case 'invoice':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."facture WHERE rowid=".$id;
            break;
        case 'projet':
        case 'project':
            $sql = "SELECT title as ref FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".$id;
            break;
        case 'societe':
        case 'tiers':
        case 'thirdparty':
            $sql = "SELECT nom as ref FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".$id;
            break;
        case 'contact':
            $sql = "SELECT CONCAT(firstname, ' ', lastname) as ref FROM ".MAIN_DB_PREFIX."socpeople WHERE rowid=".$id;
            break;
        case 'user':
            $sql = "SELECT CONCAT(firstname, ' ', lastname) as ref FROM ".MAIN_DB_PREFIX."user WHERE rowid=".$id;
            break;
        case 'contrat':
        case 'contract':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."contrat WHERE rowid=".$id;
            break;
        case 'expedition':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."expedition WHERE rowid=".$id;
            break;
        case 'fichinter':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid=".$id;
            break;
        case 'ticket':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."ticket WHERE rowid=".$id;
            break;
        case 'partnership':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."partnership WHERE rowid=".$id;
            break;
        case 'supplier_proposal':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."supplier_proposal WHERE rowid=".$id;
            break;
        case 'supplier_order':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."commande_fournisseur WHERE rowid=".$id;
            break;
        case 'supplier_invoice':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid=".$id;
            break;
        case 'reception':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."reception WHERE rowid=".$id;
            break;
        case 'salary':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."salary WHERE rowid=".$id;
            break;
        case 'loan':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."loan WHERE rowid=".$id;
            break;
        case 'don':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."don WHERE rowid=".$id;
            break;
        case 'holiday':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."holiday WHERE rowid=".$id;
            break;
        case 'expensereport':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."expensereport WHERE rowid=".$id;
            break;
        case 'usergroup':
            $sql = "SELECT nom as ref FROM ".MAIN_DB_PREFIX."usergroup WHERE rowid=".$id;
            break;
        case 'adherent':
            $sql = "SELECT CONCAT(firstname, ' ', lastname) as ref FROM ".MAIN_DB_PREFIX."adherent WHERE rowid=".$id;
            break;
        case 'event':
            $sql = "SELECT label as ref FROM ".MAIN_DB_PREFIX."actioncomm WHERE id=".$id;
            break;
        case 'accounting':
            $sql = "SELECT doc_ref as ref FROM ".MAIN_DB_PREFIX."accounting_bookkeeping WHERE rowid=".$id;
            break;
        case 'affaire':
            $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."affaire WHERE rowid=".$id;
            break;
        
        default:
            return $type . '_' . $id;
    }
    
    $res = $db->query($sql);
    if ($res && $db->num_rows($res) > 0) {
        $obj = $db->fetch_object($res);
        $name = $obj->ref;
    }
    
    return $name ?: ($type . '_' . $id);
}

function validate_linked_object($type, $id, $db) {
    $id = (int)$id;
    if ($id <= 0) return false;
    
    $table_map = [
        'thirdparty' => 'societe',
        'tiers' => 'societe',
        'societe' => 'societe',
        'contact' => 'socpeople',
        'projet' => 'projet',
        'project' => 'projet',
        'propal' => 'propal',
        'commande' => 'commande',
        'contract' => 'contrat',
        'contrat' => 'contrat',
        'invoice' => 'facture',
        'facture' => 'facture',
        'user' => 'user',
        'expedition' => 'expedition',
        'fichinter' => 'fichinter',
        'ticket' => 'ticket',
        'partnership' => 'partnership',
        'supplier_proposal' => 'supplier_proposal',
        'supplier_order' => 'commande_fournisseur',
        'supplier_invoice' => 'facture_fourn',
        'reception' => 'reception',
        'salary' => 'salary',
        'loan' => 'loan',
        'don' => 'don',
        'holiday' => 'holiday',
        'expensereport' => 'expensereport',
        'usergroup' => 'usergroup',
        'adherent' => 'adherent',
        'event' => 'actioncomm',
        'accounting' => 'accounting_bookkeeping',
        'affaire' => 'affaire',
    ];
    
    if (!isset($table_map[$type])) {
        return false;
    }
    
    $table = $table_map[$type];
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".$id;
    $result = $db->query($sql);
    
    return ($result && $db->num_rows($result) > 0);
}

function save_files_to_module($type, $id, $eml_src_path, $eml_filename, $subject, $attachments_local, $conf, $db) {
    error_log("=== DÉBUT save_files_to_module ===");
    error_log("Type: $type, ID: $id");
    error_log("Nombre de pièces jointes: " . count($attachments_local));
    if($type === 'user')  {
        $target_dir = DOL_DATA_ROOT.'/'.$type.'s/'.$id.'/';

    }else {
    $target_dir = DOL_DATA_ROOT.'/'.$type.'/'.$id.'/';
    }
    error_log("Répertoire cible: $target_dir");
    
    if (!is_dir($target_dir)) {
        error_log("Création du répertoire...");
        if (!mkdir($target_dir, 0777, true)) {
            error_log("ERREUR: Impossible de créer le répertoire : " . $target_dir);
            return;
        }
        error_log("Répertoire créé avec succès");
    }
    
    if (!is_writable($target_dir)) {
        error_log("ERREUR: Répertoire non accessible en écriture");
        return;
    }

    // 1. Copier le fichier EML
    error_log("--- COPIE FICHIER EML ---");
    $dest_filename_eml = time().'_'.$eml_filename;
    
    $sql_check_eml = "SELECT rowid FROM ".MAIN_DB_PREFIX."ecm_files 
                      WHERE src_object_type = '".$db->escape($type)."'
                      AND src_object_id = ".((int)$id)."
                      AND filepath = '".$db->escape($type.'/'.$id)."'
                      AND filename = '".$db->escape($dest_filename_eml)."'";
    $resql_eml = $db->query($sql_check_eml);
    
    if ($resql_eml && $db->num_rows($resql_eml) == 0) {
        if (copy($eml_src_path, $target_dir . $dest_filename_eml)) {
            error_log("EML copié avec succès");
            
            // CORRECTION: Supprimer le champ filesize
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."ecm_files 
                    (ref, label, entity, filepath, filename, src_object_type, src_object_id, date_c, tms)
                    VALUES ('".$db->escape($dest_filename_eml)."', '".$db->escape('Email: '.$subject)."', 
                           ".(int)$conf->entity.", '".$db->escape($type.'/'.$id)."', 
                           '".$db->escape($dest_filename_eml)."', '".$db->escape($type)."', 
                           ".$id.", '".$db->idate(dol_now())."', '".$db->idate(dol_now())."')";
            
            error_log("SQL insertion EML: $sql");
            
            if (!$db->query($sql)) {
                error_log("ERREUR insertion EML: " . $db->lasterror());
            } else {
                error_log("SUCCESS: EML inséré dans ecm_files");
            }
        } else {
            error_log("ERREUR: Échec copie EML");
        }
    } else {
        error_log("EML déjà présent");
    }
    
    // 2. Copier les pièces jointes
    error_log("--- COPIE PIÈCES JOINTES ---");
    $success_count = 0;
    
    foreach ($attachments_local as $att_index => $att) {
        $src = $att['path'] ?? '';
        $name = $att['name'] ?? 'unknown.bin';
        
        error_log("PJ [$att_index]: $name");
        error_log("  Source: $src");
        
        if ($src && file_exists($src)) {
            
            
        $safe_name = cleanAttachmentName($name);
        
        
        $safe_name = preg_replace('/[^A-Za-z0-9_\.\-\s]/', '_', $safe_name);
        
        // Nettoyer les espaces multiples
        $safe_name = preg_replace('/\s+/', '_', $safe_name);
        $safe_name = trim($safe_name);
        
        if (empty($safe_name)) {
            $safe_name = 'attachment_' . time() . '.bin';
        }
        
        error_log("  Nom nettoyé: '$name' -> '$safe_name'");
        
        $dest_filename_att = time().'_'.rand(100,999).'_'.$safe_name;
        $dest_path_att = $target_dir . $dest_filename_att;
            
            // Vérifier si le fichier n'existe pas déjà
            $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."ecm_files 
                          WHERE src_object_type = '".$db->escape($type)."'
                          AND src_object_id = ".((int)$id)."
                          AND filepath = '".$db->escape($type.'/'.$id)."'
                          AND filename = '".$db->escape($dest_filename_att)."'";
            
            $resql = $db->query($sql_check);
            
            if (!$resql) {
                error_log("  ERREUR requête check: " . $db->lasterror());
                continue;
            }
            
            if ($db->num_rows($resql) == 0) {
                error_log("  Copie du fichier...");
                if (copy($src, $dest_path_att)) {
                    error_log("  Copie réussie");
                    
                    // CORRECTION: Supprimer le champ filesize
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."ecm_files 
                            (ref, label, entity, filepath, filename, src_object_type, src_object_id, date_c, tms)
                            VALUES ('".$db->escape($dest_filename_att)."', '".$db->escape('Pièce jointe: '.$name)."', 
                                   ".(int)$conf->entity.", '".$db->escape($type.'/'.$id)."', 
                                   '".$db->escape($dest_filename_att)."', '".$db->escape($type)."', 
                                   ".$id.", '".$db->idate(dol_now())."', '".$db->idate(dol_now())."')";
                    
                    error_log("  SQL insertion PJ: $sql");
                    
                    if (!$db->query($sql)) {
                        error_log("  ERREUR insertion PJ: " . $db->lasterror());
                    } else {
                        error_log("  SUCCESS: PJ insérée dans ecm_files");
                        $success_count++;
                    }
                } else {
                    error_log("  ERREUR: Échec copie fichier");
                }
            } else {
                error_log("  Fichier déjà présent dans ecm_files");
            }
        } else {
            error_log("  ERREUR: Fichier source introuvable");
        }
    }
    
    error_log("=== FIN save_files_to_module ===");
    error_log("Pièces jointes insérées avec succès: $success_count");
    
    // Vérification finale
    $sql_final = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."ecm_files 
                  WHERE src_object_type='".$db->escape($type)."' AND src_object_id=".$id;
    $res_final = $db->query($sql_final);
    if ($res_final) {
        $obj_final = $db->fetch_object($res_final);
        error_log("Total fichiers dans ecm_files pour $type/$id : " . $obj_final->nb);
    }
}
function getUserEmailFromDolibarr($user, $db) {
    if (isset($user) && !empty($user->email)) {
        return $user->email;
    }
    
    if (isset($user) && !empty($user->id)) {
        $sql = "SELECT email FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".(int)$user->id;
        $result = $db->query($sql);
        if ($result && $db->num_rows($result) > 0) {
            $obj = $db->fetch_object($result);
            return $obj->email ?: 'user@localhost';
        }
    }
    
    return 'user@localhost';
}

function extractEmailsFromTo($to_string) {
    $emails = [];
    
    if (empty($to_string)) {
        return $emails;
    }
    
    $parts = preg_split('/[,;]/', $to_string);
    
    foreach ($parts as $part) {
        $part = trim($part);
        
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $part, $matches)) {
            $emails[] = $matches[1];
        }
    }
    
    return array_unique($emails);
}

?>