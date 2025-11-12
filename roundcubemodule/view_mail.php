<?php

require '../../main.inc.php';
require 'vendor/autoload.php';

use ZBateson\MailMimeParser\MailMimeParser;

//recuperer l'id
$id = GETPOST('id', 'int');
$id = (int) $id;

$sql = "SELECT rowid, subject, from_email, date_received, file_path, imap_mailbox, imap_uid 
        FROM llx_mailboxmodule_mail WHERE rowid = " . $id;
$resql = $db->query($sql);

?>

<style>
/* Styles pour la prévisualisation des pièces jointes */
.attachment-preview-container {
    margin-top: 20px;
}

.attachment-item {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.attachment-item:hover {
    background-color: #e9ecef;
}

.attachment-icon {
    margin-right: 10px;
    font-size: 20px;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    font-weight: 500;
    margin-bottom: 2px;
}

.attachment-size {
    font-size: 12px;
    color: #6c757d;
}

.attachment-actions {
    display: flex;
    gap: 5px;
}

.btn-preview {
    background: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    cursor: pointer;
}

.btn-preview:hover {
    background: #0056b3;
}

.btn-download {
    background: #28a745;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    cursor: pointer;
}

.btn-download:hover {
    background: #1e7e34;
}

/* Modal pour la prévisualisation */
.preview-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
}

.preview-modal-content {
    position: relative;
    margin: 2% auto;
    width: 90%;
    max-width: 1200px;
    height: 90%;
    background: white;
    border-radius: 5px;
    overflow: hidden;
}

.preview-modal-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preview-modal-body {
    height: calc(100% - 60px);
    overflow: auto;
    padding: 20px;
    text-align: center;
}

.close-preview {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
}

.close-preview:hover {
    color: #000;
}

.preview-content {
    max-width: 100%;
    max-height: 100%;
}

.preview-content img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
}

.preview-content iframe {
    width: 100%;
    height: 80vh;
    border: none;
}

.preview-text {
    text-align: left;
    white-space: pre-wrap;
    font-family: monospace;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    max-height: 70vh;
    overflow: auto;
}
</style>

<?php

if ($resql && ($obj = $db->fetch_object($resql))) {

    print '<h3>' . dol_escape_htmltag($obj->subject) . '</h3>';
    print '<p><b>Expéditeur :</b> ' . dol_escape_htmltag($obj->from_email) . '</p>';
    print '<p><b>Date reçue :</b> ' . dol_print_date($db->jdate($obj->date_received), 'dayhour') . '</p>';
    
    $roundcube_base_url = DOL_URL_ROOT . '/custom/roundcubemodule/roundcube/?_autologin=1&dolibarr_id=' . $user->id . '&secret=' . urlencode('MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdV');
    $uid = (int) $obj->imap_uid;
    $mailbox = urlencode($obj->imap_mailbox);

    if (!empty($obj->file_path) && file_exists(DOL_DATA_ROOT . '/' . $obj->file_path)) {
        $fullpath = DOL_DATA_ROOT . '/' . $obj->file_path;
        $extension = strtolower(pathinfo($fullpath, PATHINFO_EXTENSION));

        print '<h4>Infos du mail :</h4>';

        if ($extension === 'eml') {
            //  Cas EML
            $emlContent = file_get_contents($fullpath);
            $parser = new MailMimeParser();
            $message = $parser->parse($emlContent, false);

            $from = dol_escape_htmltag($message->getHeaderValue('from'));
            $to = dol_escape_htmltag($message->getHeaderValue('to'));
            $subject = dol_escape_htmltag($message->getHeaderValue('subject'));
            $htmlBody = $message->getHtmlContent();

            if (empty($htmlBody)) {
                $textBody = $message->getTextContent(); // plain/text
                $textBody = mb_convert_encoding($textBody, 'UTF-8', 'auto');

                $htmlBody = nl2br(htmlspecialchars($textBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            } else {
                $htmlBody = mb_convert_encoding($htmlBody, 'UTF-8', 'auto');
            }
            $attachments = $message->getAllAttachmentParts();

        } elseif ($extension === 'msg') {
            //  MSG
            $python = "C:\\Users\\Guy Keny\\AppData\\Local\\Programs\\Python\\Python312\\python.exe";
            $script = DOL_DOCUMENT_ROOT . "/custom/roundcubemodule/parser_msg.py";
            $cmd = "\"$python\" \"$script\" \"$fullpath\"";

            $output = shell_exec($cmd);
            $output = mb_convert_encoding($output, 'UTF-8', 'auto');
            $data = json_decode($output, true);

            if ($data !== null) {
                $from = dol_escape_htmltag($data['sender']);
                $to = dol_escape_htmltag($data['to']);
                $subject = dol_escape_htmltag($data['subject']);
                $htmlBody = nl2br(htmlspecialchars($data['body']));
                $attachments = $data['attachments'];
            } else {
                print '<p style="color:red;">Erreur: impossible de lire le fichier MSG.</p>';
                $from = $to = $subject = $htmlBody = '';
                $attachments = [];
            }
        } else {
            print '<p style="color:red;">Fichier inconnu (extension non supportée).</p>';
            $from = $to = $subject = $htmlBody = '';
            $attachments = [];
        }

        print '<p><b>From:</b> ' . $from . '</p>';
        print '<p><b>To:</b> ' . $to . '</p>';
        print '<p><b>Subject:</b> ' . $subject . '</p>';

        // Nettoyage du contenu
        $cleanBody = html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanFrom = html_entity_decode($from, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanTo = html_entity_decode($to, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanSubject = html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanDate = dol_print_date($db->jdate($obj->date_received), 'dayhour');

        // Construction du message original AVEC encodage des sauts de ligne
        $originalMsg = "-------- Message original --------%0D%0A"
            . "De: " . rawurlencode($cleanFrom) . "%0D%0A"
            . "Objet: " . rawurlencode($cleanSubject) . "%0D%0A"
            . "Date: " . rawurlencode($cleanDate) . "%0D%0A%0D%0A"
            . rawurlencode($cleanBody);

        print '<div style="margin: 10px 0;">';
        // Bouton Répondre
        print '<a class="btn btn-primary" style="margin-right:5px;" target="_blank"
       href="' . $roundcube_base_url . '&_task=mail&_action=compose'
            . '&_to=' . rawurlencode($cleanFrom)
            . '&_subject=' . rawurlencode('Re: ' . $cleanSubject)
            . '&_body=%0D%0A%0D%0A' . $originalMsg . '">Répondre</a>';

        // Bouton Répondre à tous
        print '<a class="btn btn-primary" style="margin-right:5px;" target="_blank"
       href="' . $roundcube_base_url . '&_task=mail&_action=compose'
            . '&_to=' . rawurlencode($cleanFrom . (!empty($cleanTo) ? ',' . $cleanTo : ''))
            . '&_subject=' . rawurlencode('Re: ' . $cleanSubject)
            . '&_body=%0D%0A%0D%0A' . $originalMsg . '">Répondre à tous</a>';

        // Bouton Transférer
        print '<a class="btn btn-primary" style="margin-right:5px;" target="_blank"
       href="' . $roundcube_base_url . '&_task=mail&_action=compose'
            . '&_subject=' . rawurlencode('Fwd: ' . $cleanSubject)
            . '&_body=%0D%0A%0D%0A' . $originalMsg . '">Transférer</a>';

        // Bouton Supprimer
        print '<a class="btn btn-danger" style="margin-left:10px;" onclick="return confirm(\'Confirmer la suppression ?\');"
       href="delete.php?id=' . $obj->rowid . '">Supprimer</a>';
        print '</div>';

        //  Afficher le corps
        if ($htmlBody) {
            print '<div style="border:none; padding:10px; max-height:400px; overflow:auto; background:#fff; max-width:800px;">';
            print $htmlBody;
            print '</div>';
        }

        //  Pièces jointes avec prévisualisation
        // NOUVELLE LOGIQUE : Récupérer depuis la base de données
$sql_att = "SELECT * FROM " . MAIN_DB_PREFIX . "mailboxmodule_attachment WHERE fk_mail = " . $id . " ORDER BY rowid";
$res_att = $db->query($sql_att);
$db_attachments = [];

if ($res_att) {
    while ($att_obj = $db->fetch_object($res_att)) {
        $db_attachments[] = $att_obj;
    }
}

if (!empty($db_attachments)) {
    print '<div class="attachment-preview-container">';
    print '<h4>📎 Pièces jointes (' . count($db_attachments) . ') :</h4>';
    
    foreach ($db_attachments as $index => $att) {
        $attachmentIndex = $index + 1;  // Vous pouvez garder ça pour l'affichage si vous voulez
        $filename = cleanAttachmentName($att->original_name);
        $size = $att->filesize;
        
        // Déterminer le contentType selon l'extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filename_lower = strtolower($filename);

        if ($ext === 'pdf' || strpos($filename_lower, '.pdf') !== false) {
            $contentType = 'application/pdf';
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $contentType = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        } elseif (in_array($ext, ['txt', 'csv', 'log'])) {
            $contentType = 'text/plain';
        } else {
            $contentType = 'application/octet-stream';
        }
        
        $icon = getFileIcon($contentType, $filename);
        $sizeFormatted = formatFileSize($size);
        $canPreview = canPreviewFile($contentType, $filename);
        
        print '<div class="attachment-item">';
        print '<span class="attachment-icon">' . $icon . '</span>';
        print '<div class="attachment-info">';
        print '<div class="attachment-name">' . dol_escape_htmltag($filename) . '</div>';
        print '<div class="attachment-size">' . $sizeFormatted . ' - ' . $contentType . '</div>';
        print '</div>';
        print '<div class="attachment-actions">';
        
        if ($canPreview) {
            // CORRECTION : Utiliser $att->rowid au lieu de $attachmentIndex
            print '<button class="btn-preview" onclick="previewAttachment(' . 
                  $att->rowid . ', \'' .   // ✅ ID réel de l'attachment
                  dol_escape_js($filename) . '\', \'' . 
                  dol_escape_js($contentType) . '\', ' . 
                  $id . ')">👁️ Aperçu</button>';
        }

        // Le bouton de téléchargement utilise déjà le bon ID
        print '<a href="download_attachment.php?attachmentId=' . $att->rowid . '" target="_blank" class="btn-download">⬇️ Télécharger</a>';

        print '</div>';
        print '</div>';
    }
    print '</div>'; 
}

    } else {
        print '<p><i>Fichier non trouvé ou chemin vide.</i></p>';
    }

} else {
    print '<p>Mail introuvable.</p>';
}
// Ajoutez cette fonction avant les fonctions helper existantes
function cleanAttachmentName($filename) {
    // Supprimer les informations de taille : (~2.3 Mo), (~301 ko), etc.
    $filename = preg_replace('/\(~[\d,.]+ ?[kmgtpezy]?[bo]\)$/i', '', $filename);
    
    // Supprimer d'autres formats possibles : (2.34 MB), (301 KB), etc.
    $filename = preg_replace('/\([\d,.]+ ?[kmgtpezy]?b\)$/i', '', $filename);
    
    // Nettoyer les espaces multiples et trim
    $filename = preg_replace('/\s+/', ' ', trim($filename));
    
    // Si le nom est vide, utiliser un nom par défaut
    if (empty($filename) || $filename === '.') {
        $filename = 'attachment_' . time() . '.bin';
    }
    
    return $filename;
}
// Fonctions helper
function getFileIcon($contentType, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (strpos($contentType, 'image/') === 0) return '🖼️';
    if (strpos($contentType, 'application/pdf') === 0) return '📋';
    if (strpos($contentType, 'text/') === 0) return '📄';
    if (in_array($ext, ['doc', 'docx']) || strpos($contentType, 'wordprocessing') !== false) return '📝';
    if (in_array($ext, ['xls', 'xlsx']) || strpos($contentType, 'spreadsheet') !== false) return '📊';
    if (in_array($ext, ['ppt', 'pptx']) || strpos($contentType, 'presentation') !== false) return '📊';
    if (in_array($ext, ['zip', 'rar', '7z']) || strpos($contentType, 'zip') !== false) return '🗜️';
    if (in_array($ext, ['mp3', 'wav', 'ogg']) || strpos($contentType, 'audio/') === 0) return '🎵';
    if (in_array($ext, ['mp4', 'avi', 'mov']) || strpos($contentType, 'video/') === 0) return '🎬';
    
    return '📎';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function canPreviewFile($contentType, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Images
    if (strpos($contentType, 'image/') === 0) return true;
    
    // PDF
    if (strpos($contentType, 'application/pdf') === 0) return true;
    
    // Fichiers texte
    if (strpos($contentType, 'text/') === 0) return true;
    if (in_array($ext, ['txt', 'csv', 'log', 'xml', 'json'])) return true;
    
    return false;
}

$db->close();
?>

<!-- Modal de prévisualisation -->
<div id="previewModal" class="preview-modal">
    <div class="preview-modal-content">
        <div class="preview-modal-header">
            <h4 id="previewTitle">Aperçu du fichier</h4>
            <span class="close-preview" onclick="closePreview()">&times;</span>
        </div>
        <div class="preview-modal-body" id="previewBody">
            <p>Chargement...</p>
        </div>
    </div>
</div>

<script>
function previewAttachment(attachmentId, filename, contentType, mailId) {
    const modal = document.getElementById('previewModal');
    const title = document.getElementById('previewTitle');
    const body = document.getElementById('previewBody');
    
    title.textContent = 'Aperçu : ' + filename;
    body.innerHTML = '<p>Chargement...</p>';
    modal.style.display = 'block';
    
    console.log('Début prévisualisation:', {attachmentId, filename, contentType, mailId});
    
    // Utiliser une iframe pour afficher directement le fichier
    // C'est plus simple et ça marche mieux pour les PDF et images
    const url = './preview_attachment.php?attachmentId=' + attachmentId;
    
    // Types de fichiers qui peuvent être affichés dans une iframe
    const supportedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'text/plain',
        'text/html'
    ];
    
    if (supportedTypes.includes(contentType.toLowerCase())) {
        // Afficher dans une iframe
        body.innerHTML = `
            <div class="preview-content" style="width: 100%; height: 600px;">
                <iframe src="${url}" 
                        style="width: 100%; height: 100%; border: none;"
                        title="Aperçu de ${filename}">
                </iframe>
            </div>
        `;
    } else {
        // Pour les autres types, proposer le téléchargement
        body.innerHTML = `
            <div class="preview-content">
                <p>Ce type de fichier (${contentType}) ne peut pas être affiché directement.</p>
                <a href="${url}" target="_blank" class="btn btn-primary">
                    Ouvrir dans un nouvel onglet
                </a>
                <a href="${url}" download="${filename}" class="btn btn-secondary">
                    Télécharger
                </a>
            </div>
        `;
    }
}

// Fonction alternative si vous voulez garder le chargement AJAX
function previewAttachmentAjax(attachmentId, filename, contentType, mailId) {
    const modal = document.getElementById('previewModal');
    const title = document.getElementById('previewTitle');
    const body = document.getElementById('previewBody');
    
    title.textContent = 'Aperçu : ' + filename;
    body.innerHTML = '<p>Chargement...</p>';
    modal.style.display = 'block';
    
    console.log('Début prévisualisation AJAX:', {attachmentId, filename, contentType, mailId});
    
    // Appel avec GET et attachmentId
    fetch('./preview_attachment.php?attachmentId=' + attachmentId)
    .then(response => {
        console.log('Réponse reçue:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error('Erreur HTTP: ' + response.status);
        }
        
        // Selon le type, traiter différemment
        if (contentType.startsWith('image/')) {
            return response.blob();
        } else if (contentType === 'application/pdf') {
            return response.blob();
        } else if (contentType.startsWith('text/')) {
            return response.text();
        } else {
            return response.blob();
        }
    })
    .then(data => {
        if (contentType.startsWith('image/') || contentType === 'application/pdf') {
            // Pour les images et PDF, créer une URL blob
            const blobUrl = URL.createObjectURL(data);
            
            if (contentType.startsWith('image/')) {
                body.innerHTML = `<div class="preview-content">
                    <img src="${blobUrl}" alt="${filename}" style="max-width: 100%; height: auto;">
                </div>`;
            } else if (contentType === 'application/pdf') {
                body.innerHTML = `<div class="preview-content" style="height: 600px;">
                    <iframe src="${blobUrl}" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>`;
            }
            
            // Nettoyer l'URL blob après un délai
            setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } else if (contentType.startsWith('text/')) {
            // Pour le texte, l'afficher directement
            body.innerHTML = `<div class="preview-content">
                <pre class="preview-text">${escapeHtml(data)}</pre>
            </div>`;
        } else {
            body.innerHTML = '<p>Type de fichier non supporté pour la prévisualisation.</p>';
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        body.innerHTML = `<p style="color:red;">Erreur lors du chargement : ${error.message}</p>`;
    });
}

// Fonction utilitaire pour échapper le HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Fonction pour fermer la modal
function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('previewBody');
    
    // Nettoyer le contenu (important pour les iframes)
    body.innerHTML = '';
    modal.style.display = 'none';
}
function downloadAttachment(attachmentId) {
    console.log('Début téléchargement:', { attachmentId: attachmentId });
    
    // Créer un lien de téléchargement
    const link = document.createElement('a');
    link.href = './download_attachment.php?attachmentId=' + attachmentId;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
exit;
?>
