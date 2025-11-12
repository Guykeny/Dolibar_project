<?php
// Recherche de main.inc.php
$res = 0;
$paths = ['../../main.inc.php', '../../../main.inc.php', '../../../../main.inc.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    die('Erreur: Impossible de trouver main.inc.php. Vérifiez le chemin d\'installation.');
}

// Vérifier la connexion utilisateur
if (empty($user->id)) {
    accessforbidden();
}

// Vérifier les droits d'accès au webmail
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    accessforbidden('Vous n\'avez pas les droits pour accéder au webmail');
}

// Configuration
$conf->dol_hide_leftmenu = 1;
$langs->load("mails");

// Header
llxHeader('', 'Roundcube Module - Webmail Intégré');
print('<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.self !== window.top) {
            const urlParams = new URLSearchParams(window.location.search);
            const preselectData = {
                type: urlParams.get("preselect_type"),
                id: urlParams.get("preselect_id"),
                name: urlParams.get("preselect_name")
            };

            if (preselectData.type && preselectData.id) {
                console.log("✅ Envoi des données à la page parente:", preselectData);
                window.parent.postMessage({
                    type: "preselect_module",
                    data: preselectData
                }, "*");
            }
        }
    });
</script>');

// Fonction pour décrypter le mot de passe
function decryptPassword($encryptedPassword) {
    return base64_decode($encryptedPassword);
}

// Récupérer les comptes webmail de l'utilisateur
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_accounts ";
$sql .= "WHERE fk_user = ".$user->id." AND is_active = 1 ";
$sql .= "ORDER BY is_default DESC, account_name ASC";

$resql = $db->query($sql);
$accounts = array();
$default_account = null;

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $accounts[] = $obj;
        if ($obj->is_default || !$default_account) {
            $default_account = $obj;
        }
    }
}

// Si aucun compte configuré, rediriger vers la configuration
if (empty($accounts)) {
    $config_url = dol_buildpath('/user/card.php?id='.$user->id.'&tab=webmail', 1);
    
    print '<div class="center" style="margin-top: 50px;">';
    print '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 20px; max-width: 600px; margin: 0 auto;">';
    print '<h3>📧 Aucun compte webmail configuré</h3>';
    print '<p>Vous devez d\'abord configurer au moins un compte webmail pour accéder à Roundcube.</p>';
    print '<p><a href="'.$config_url.'" class="button">Configurer mes comptes webmail</a></p>';
    print '</div>';
    print '</div>';
    
    llxFooter();
    exit;
}

$roundcube_base_url = '';

if (!empty($conf->global->ROUNDCUBE_URL)) {
    $roundcube_base_url = $conf->global->ROUNDCUBE_URL;
} else {
    $test_path = DOL_DOCUMENT_ROOT . '/custom/roundcubemodule/roundcube/index.php';
    if (file_exists($test_path)) {
        $roundcube_base_url = '/AVOCATS/htdocs/custom/roundcubemodule/roundcube/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcube/index.php')) {
        $roundcube_base_url = '/roundcube/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/roundcubemail/index.php')) {
        $roundcube_base_url = '/roundcubemail/';
    }
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/webmail/index.php')) {
        $roundcube_base_url = '/webmail/';
    }
    else {
        $roundcube_base_url = '/roundcube/';
    }
}

if (strpos($roundcube_base_url, 'http') !== 0) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($roundcube_base_url, '/') !== 0) {
        $roundcube_base_url = '/' . $roundcube_base_url;
    }
    
    $roundcube_base_url = $protocol . $host . $roundcube_base_url;
}

if (substr($roundcube_base_url, -1) !== '/') {
    $roundcube_base_url .= '/';
}


$roundcube_url = $roundcube_base_url;

$shared_secret = 'MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdW'; // Doit correspondre au secret du plugin
$dolibarr_user_id = $user->id;
$default_account_id = $default_account ? $default_account->rowid : '';

// Construction de la nouvelle URL d'autologin pour la page initiale
$separator = (strpos($roundcube_url, '?') === false) ? '?' : '&';
$roundcube_url = $roundcube_url . $separator .
                '_autologin=1' .
                '&secret=' . urlencode($shared_secret) .
                '&dolibarr_id=' . urlencode($dolibarr_user_id) .
                '&account_id=' . urlencode($default_account_id);
// Récupération des paramètres de présélection
$preselect_data = null;
if (isset($_GET['preselect_type']) && isset($_GET['preselect_id'])) {
    $preselect_data = [
        'type' => $_GET['preselect_type'],
        'id' => (int)$_GET['preselect_id'],
        'name' => $_GET['preselect_name'] ?? ''
    ];
}

// Ajouter les paramètres de présélection à l'URL Roundcube
if ($preselect_data) {
    $roundcube_url .= '&preselect_type=' . urlencode($preselect_data['type']) .
                      '&preselect_id=' . urlencode($preselect_data['id']) .
                      '&preselect_name=' . urlencode($preselect_data['name']);
}
// Vérifier si on doit aller en mode composition
if (isset($_GET['_task']) && $_GET['_task'] === 'mail' && 
    isset($_GET['_action']) && $_GET['_action'] === 'compose') {
    
    // Ajouter les paramètres de composition à l'URL Roundcube
    $roundcube_url .= '&_task=mail&_action=compose';
    
    // Ajouter l'email de destination si fourni
    if (isset($_GET['_to']) && !empty($_GET['_to'])) {
        $roundcube_url .= '&_to=' . urlencode($_GET['_to']);
    }
    
}
if (!empty($conf->global->ROUNDCUBE_DEBUG)) {
    print '<!-- Roundcube URL: ' . htmlspecialchars($roundcube_url) . ' -->';
}

require_once DOL_DOCUMENT_ROOT.'/custom/roundcubemodule/components/bandeau/BandeauManager.php';
?>

<!-- Interface de sélection des comptes (si plusieurs comptes) -->
<?php if (count($accounts) > 1): ?>
<div id="account-selector" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 10px;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <strong>📧 Compte actuel :</strong>
            <select id="account-select" onchange="switchAccount()" style="margin-left: 10px; padding: 5px;">
                <?php foreach ($accounts as $account): ?>
                <option value="<?php echo $account->rowid; ?>" 
                        <?php echo ($account->rowid == $default_account->rowid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($account->email); ?>
                    <?php if ($account->account_name): ?>
                        (<?php echo htmlspecialchars($account->account_name); ?>)
                    <?php endif; ?>
                    <?php if ($account->is_default): ?>
                        - Par défaut
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <button onclick="refreshRoundcube()" style="margin-right: 10px;">🔄 Actualiser</button>
            <button onclick="openNewWindow()">🗗 Nouvelle fenêtre</button>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Affichage simple pour un seul compte -->
<div id="account-selector" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 10px;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <strong>📧 Compte connecté :</strong>
                <?php echo htmlspecialchars($default_account->email); ?>
                <?php if ($default_account->account_name): ?>
                    (<?php echo htmlspecialchars($default_account->account_name); ?>)
                <?php endif; ?>
            </span>
        </div>
        
        <div>
            <button onclick="refreshRoundcube()" style="margin-right: 10px;">🔄 Actualiser</button>
            <button onclick="openNewWindow()">🗗 Nouvelle fenêtre</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Container principal -->
<div id="roundcube-container">
    <!-- Message d'erreur si Roundcube ne charge pas -->
    <div id="roundcube-error" style="display:none;">
        <h3 style="color: #e74c3c;">⚠️ Roundcube non accessible</h3>
        <p>L'URL configurée ne répond pas : <br><code><?php echo htmlspecialchars($roundcube_url); ?></code></p>
        <p>Veuillez vérifier la configuration :</p>
        <a href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/roundcube_setup.php', 1); ?>" class="button">
            ⚙️ Configurer l'URL
        </a>
        <br><br>
        <details>
            <summary>Détails techniques</summary>
            <small style="text-align: left; display: block; margin-top: 10px;">
                URL testée : <?php echo htmlspecialchars($roundcube_url); ?><br>
                Serveur : <?php echo $_SERVER['HTTP_HOST']; ?><br>
                Document root : <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
            </small>
        </details>
    </div>
    
    <!-- Iframe Roundcube -->
    <iframe id="roundcube-iframe" 
            src="<?php echo htmlspecialchars($roundcube_url); ?>"
            onerror="handleIframeError()"
            style="width: 100%; height: 100%; border: none;">
    </iframe>
    
    <?php
    // Rendre le bandeau avec la nouvelle architecture (conservé)
    BandeauManager::renderBandeau($user, $conf, $db, $langs, $roundcube_url);
    ?>
</div>

<!-- Script de détection Roundcube  -->
<script>

function getIframeDetectionScript() {
    return `
(function() {
    console.log('🔍 Script de détection Roundcube v6.0 - Version complète améliorée');
    
    if (window.roundcubeDetectionActive) {
        console.log('Script déjà actif, arrêt');
        return;
    }
    window.roundcubeDetectionActive = true;
    // Récupérer et envoyer les paramètres de présélection depuis l'URL
    
    // Variables globales
    let currentMailData = null;
    let lastUID = null;
    let extractionInProgress = false;
    let extractionQueue = [];
    let pendingExtractions = new Map();
    
    /**
     * NOUVELLE FONCTION : Extraire la date depuis le contenu de l'email
     */
    function extractDateFromEmailContent(content) {
    try {
        // 1. Chercher l'en-tête Date: dans le contenu
        const dateMatch = content.match(/^Date:\\s*(.+)$/im);
        if (dateMatch) {
            const dateString = dateMatch[1].trim();
            const parsedDate = new Date(dateString);
            if (!isNaN(parsedDate.getTime())) {
                console.log('Date extraite du contenu:', dateString);
                return parsedDate.toISOString();
            }
        }
        
        // 2. Chercher dans le HTML la vraie date
        const htmlDateMatch = content.match(/<span class="text-nowrap">([^<]+)<\\/span>/);
        if (htmlDateMatch) {
            const dateString = htmlDateMatch[1].trim(); // "2025-08-26 14:41"
            
            // CORRECTION: Ajouter le décalage horaire explicitement
            const localDate = new Date(dateString);
            if (!isNaN(localDate.getTime())) {
                // Ajouter 2 heures (7200000 ms) pour compenser UTC
                const adjustedDate = new Date(localDate.getTime() + (2 * 60 * 60 * 1000));
                console.log('Date extraite du HTML:', dateString, '->', adjustedDate.toISOString());
                return adjustedDate.toISOString();
            }
        }
        
        return null;
        } catch (e) {
            console.error('Erreur extraction date:', e);
            return null;
        }
    }
    
    /**
     * Vérification avancée de l'état de l'iframe
     */
    function isIframeReady(messageFrame) {
        if (!messageFrame) return false;
        
        try {
            // Vérifier que l'iframe n'est pas sur watermark.html
            if (messageFrame.src.includes('watermark.html') || 
                messageFrame.src.includes('about:blank') || 
                !messageFrame.src) {
                return false;
            }
            
            // Vérifier que le document est accessible et chargé
            const frameDoc = messageFrame.contentDocument || messageFrame.contentWindow?.document;
            if (!frameDoc || frameDoc.readyState !== 'complete') {
                return false;
            }
            
            // Vérifier qu'il y a du contenu utile
            const body = frameDoc.body;
            if (!body || body.innerHTML.length < 100) {
                return false;
            }
            
            // Vérifier qu'on n'est pas sur une page d'erreur
            const errorElements = frameDoc.querySelectorAll('.error, .warning, .notice');
            if (errorElements.length > 0) {
                return false;
            }
            
            return true;
            
        } catch (e) {
            console.log('Iframe pas encore accessible:', e.message);
            return false;
        }
    }
    
    /**
     * Attendre que l'iframe soit prête avec un timeout plus long
     */
    function waitForIframeReady(messageFrame, maxAttempts = 30, delay = 500) {
        return new Promise((resolve) => {
            let attempts = 0;
            
            const checkReady = () => {
                attempts++;
                
                if (isIframeReady(messageFrame)) {
                    console.log(\`✅ Iframe prête après \${attempts} tentatives\`);
                    resolve(true);
                    return;
                }
                
                if (attempts >= maxAttempts) {
                    console.log(\`⏰ Timeout iframe après \${attempts} tentatives\`);
                    resolve(false);
                    return;
                }
                
                setTimeout(checkReady, delay);
            };
            
            // Démarrer la vérification
            setTimeout(checkReady, delay);
        });
    }
    
    /**
     * Forcer le rechargement avec vérification d'état
     */
    async function forceIframeReload(messageFrame, uid, folder = 'INBOX') {
        if (!messageFrame || !uid) return false;
        
        try {
            console.log('🔄 Rechargement forcé iframe pour UID:', uid);
            
            // Construire l'URL complète du message
            const baseUrl = window.location.href.split('?')[0];
            const messageUrl = \`\${baseUrl}?_task=mail&_action=show&_uid=\${uid}&_mbox=\${folder}&_framed=1&_nocache=\${Date.now()}\`;
            
            console.log('🔗 URL de rechargement:', messageUrl);
            
            // Vider d'abord l'iframe
            messageFrame.src = 'about:blank';
            
            // Attendre un peu puis charger la nouvelle URL
            await new Promise(resolve => setTimeout(resolve, 200));
            messageFrame.src = messageUrl;
            
            // Attendre que l'iframe soit prête
            return await waitForIframeReady(messageFrame, 20, 500);
            
        } catch (e) {
            console.error('❌ Erreur rechargement iframe:', e);
            return false;
        }
    }
    
    /**
     * Extraire le contenu depuis l'iframe
     */
    function extractFromIframe(messageFrame, mailData) {
        try {
            const frameDoc = messageFrame.contentDocument || messageFrame.contentWindow.document;
            if (!frameDoc) return null;
            
            const body = frameDoc.body;
            if (!body) return null;
            
            // Chercher le contenu principal du message
            const contentSelectors = [
                '.message-content',
                '.message-part',
                '#messagebody',
                '.messagebody',
                'body'
            ];
            
            for (const selector of contentSelectors) {
                const element = frameDoc.querySelector(selector);
                if (element && element.innerHTML.length > 50) {
                    const content = element.innerHTML;
                    
                    // Vérifier que c'est cohérent avec le message
                    if (content.includes(mailData.subject) || 
                        content.length > 200) {
                        
                        return buildRawEmail(mailData, content, 'text/html');
                    }
                }
            }
            
            // Si pas de sélecteur spécifique, prendre tout le body
            if (body.innerHTML.length > 100) {
                return buildRawEmail(mailData, body.innerHTML, 'text/html');
            }
            
        } catch (e) {
            console.error('Erreur extraction iframe:', e);
        }
        
        return null;
    }
        document.addEventListener('submit', function(e) {
    const form = e.target;
    
    if (form.name === 'form' || form.id === 'compose-form' || 
        form.querySelector('input[name="_task"][value="mail"]')) {
        
        console.log('📤 Envoi de mail détecté');
        const mailData = extractOutgoingMailData(form);
        
        if (mailData && window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'mail_being_sent',
                data: mailData
            }, '*');
        }
    }
}, true);

// Intercepter les clics sur boutons d'envoi
document.addEventListener('click', function(e) {
    const button = e.target;
    
    if (button.type === 'submit' || 
        button.name === '_send' ||
        button.classList.contains('send') ||
        (button.value && button.value.toLowerCase().includes('send')) ||
        (button.textContent && button.textContent.toLowerCase().includes('send')) ||
        (button.textContent && button.textContent.toLowerCase().includes('envoyer'))) {
        
        console.log('🖱️ Clic sur bouton d\\'envoi détecté');
        
        setTimeout(() => {
            const form = button.closest('form');
            if (form) {
                const mailData = extractOutgoingMailData(form);
                
                if (mailData && window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'mail_being_sent',
                        data: mailData
                    }, '*');
                }
            }
        }, 100);
    }
}, true);
function isInComposeMode() {
    return window.location.href.includes('_action=compose') || 
           document.querySelector('textarea[name="_message"], iframe[name="composebody"]');
}
           // NOUVELLE SECTION : Détection et traitement des mails envoyés
document.addEventListener('click', function(e) {
    const button = e.target;
    
    const isSendButton = (
        button.type === 'submit' ||
        button.name === '_send' ||
        button.id === 'rcmbtn_send' ||
        button.className.includes('send') ||
        (button.textContent && (
            button.textContent.toLowerCase().includes('send') ||
            button.textContent.toLowerCase().includes('envoyer')
        ))
    );
    
    if (isSendButton && isInComposeMode()) {
        console.log('📤 Bouton envoi détecté en mode composition');
        
        setTimeout(() => {
            const form = button.closest('form') || document.querySelector('form[name="form"]');
            if (form) {
                console.log('📝 Extraction données formulaire envoi...');
                const mailData = extractOutgoingMailData(form);
                
                console.log('📤 Données mail sortant:', {
                    subject: mailData.subject,
                    to: mailData.to,
                    direction: mailData.direction
                });
                
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'mail_being_sent',
                        data: mailData
                    }, '*');
                    console.log('📨 Message envoyé au bandeau pour traitement');
                }
            }
        }, 100);
    }
}, true);
function extractOutgoingMailData(form) {
    try {
        const data = {
            subject: '',
            to: '',
            cc: '',
            bcc: '',
            body: '',
            attachments: [],
            timestamp: Date.now(),
            direction: 'sent',
            preselect_data: window.dolibarrPreselectData || null
        };
        if (typeof rcmail !== 'undefined' && rcmail.env && rcmail.env.attachments) {
            Object.values(rcmail.env.attachments).forEach(att => {
                data.attachments.push({
                    name: att.name,
                    size: att.size,
                    mimetype: att.mimetype,
                    id: att.id,
                    path: att.path || null, // Si disponible
                    source: 'rcmail_env'
                });
            });
        }
        
        // 2. Depuis les éléments DOM de la liste des pièces jointes
        const attachmentsList = form.querySelectorAll(
            '.attachmentslist li, ' +
            '.attachment-item, ' +
            '[id*="attach"]:not(input), ' +
            '.boxattachments li'
        );
        
        attachmentsList.forEach(item => {
            const nameElement = item.querySelector('.attachment-name, .name, a, span');
            const sizeElement = item.querySelector('.attachment-size, .size');
            const deleteButton = item.querySelector('.delete, .remove, [onclick*="remove"]');
            
            if (nameElement) {
                const name = nameElement.textContent.trim();
                let size = 'unknown';
                let attachId = null;
                
                if (sizeElement) {
                    size = sizeElement.textContent.trim();
                }
                
                // Extraire l'ID depuis les attributs ou onclick
                if (deleteButton && deleteButton.onclick) {
                    const onclickStr = deleteButton.onclick.toString();
                    const idMatch = onclickStr.match(/'([^']+)'/);
                    if (idMatch) {
                        attachId = idMatch[1];
                    }
                }
                
                if (!attachId && item.id) {
                    attachId = item.id.replace('attach', '');
                }
                
                data.attachments.push({
                    name: name,
                    size: size,
                    id: attachId,
                    source: 'dom_list'
                });
            }
        });
        
        // 3. Depuis les inputs de type file (si pas encore uploadé)
        const fileInputs = form.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    data.attachments.push({
                        name: file.name,
                        size: file.size,
                        mimetype: file.type,
                        lastModified: file.lastModified,
                        source: 'file_input'
                    });
                });
            }
        });
        
        // 4. Chercher dans les éléments cachés ou data attributes
        const hiddenAttachments = form.querySelectorAll('[data-attachment-name], [data-file-name]');
        hiddenAttachments.forEach(elem => {
            const name = elem.getAttribute('data-attachment-name') || elem.getAttribute('data-file-name');
            const size = elem.getAttribute('data-attachment-size') || elem.getAttribute('data-file-size');
            const id = elem.getAttribute('data-attachment-id') || elem.getAttribute('data-file-id');
            
            if (name) {
                data.attachments.push({
                    name: name,
                    size: size || 'unknown',
                    id: id,
                    source: 'hidden_data'
                });
            }
        });
        
        console.log('Pièces jointes mail sortant extraites:', data.attachments);
        
        // Extraire le sujet
        const subjectField = form.querySelector('input[name="_subject"]');
        if (subjectField) {
            data.subject = subjectField.value || 'Sans sujet';
        }
        
        // Extraire les destinataires
        const toField = form.querySelector('input[name="_to"], textarea[name="_to"]');
        if (toField) {
            data.to = toField.value || '';
        }
        
        const ccField = form.querySelector('input[name="_cc"], textarea[name="_cc"]');
        if (ccField) {
            data.cc = ccField.value || '';
        }
        
        const bccField = form.querySelector('input[name="_bcc"], textarea[name="_bcc"]');
        if (bccField) {
            data.bcc = bccField.value || '';
        }
        
        // AMÉLIORATION : Extraction du corps plus robuste
        let bodyExtracted = false;
        
        // 1. Essayer le textarea classique
        const bodyTextarea = form.querySelector('textarea[name="_message"]');
        if (bodyTextarea && bodyTextarea.value) {
            data.body = bodyTextarea.value;
            bodyExtracted = true;
        }
        
        // 2. Essayer l'iframe d'édition HTML
        if (!bodyExtracted) {
            const iframe = form.querySelector('iframe[name="composebody"]');
            if (iframe) {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc && iframeDoc.body) {
                        data.body = iframeDoc.body.innerHTML || iframeDoc.body.textContent || '';
                        bodyExtracted = true;
                    }
                } catch (e) {
                    console.log('Iframe non accessible:', e.message);
                }
            }
        }
        
        if (!bodyExtracted) {
            data.body = 'Contenu du mail non accessible';
        }
        
        // NOUVEAU : Construire le raw_email immédiatement
        data.raw_email = buildRawEmailFromOutgoing(data);
        
        console.log('📋 Données du mail extraites:', data);
        return data;
        
    } catch (error) {
        console.error('❌ Erreur extraction données mail sortant:', error);
        return null;
    }
}     

function buildRawEmailFromOutgoing(mailData) {
    const currentDate = new Date().toUTCString();
    const messageId = \`<sent_\${mailData.timestamp}@roundcube>\`;
    
    let rawEmail = 'MIME-Version: 1.0\\n';
    rawEmail += 'Content-Type: text/html; charset=UTF-8\\n';
    rawEmail += \`Message-ID: \${messageId}\\n\`;
    rawEmail += \`To: \${mailData.to}\\n\`;
    
    if (mailData.cc) rawEmail += \`Cc: \${mailData.cc}\\n\`;
    if (mailData.bcc) rawEmail += \`Bcc: \${mailData.bcc}\\n\`;
    
    rawEmail += \`Subject: \${mailData.subject}\\n\`;
    rawEmail += \`Date: \${currentDate}\\n\`;
    rawEmail += 'X-Direction: sent\\n';
    rawEmail += 'X-Auto-Classified: true\\n';
    rawEmail += '\\n';
    rawEmail += mailData.body || 'Contenu du mail';
    
    return rawEmail;
}
    
    /**
     * Extraction depuis les données cachées de Roundcube
     */
    function extractFromRoundcubeData(mailData) {
        try {
            // Chercher dans rcmail.env
            if (window.rcmail && window.rcmail.env) {
                const env = window.rcmail.env;
                
                // Vérifier si on a le bon message sélectionné
                if (env.uid && env.uid == mailData.uid) {
                    // Chercher le contenu dans différentes variables
                    const contentSources = [
                        env.message_content,
                        env.message_body,
                        env.compose?.body,
                        env.draft?.body
                    ];
                    
                    for (const content of contentSources) {
                        if (content && content.length > 50) {
                            console.log('✅ Contenu trouvé dans rcmail.env');
                            return buildRawEmail(mailData, content, 'text/html');
                        }
                    }
                }
            }
            
            // Chercher dans les variables globales
            if (window.rcmail_webmail && window.rcmail_webmail.env) {
                const env = window.rcmail_webmail.env;
                if (env.message_body || env.message_content) {
                    const content = env.message_body || env.message_content;
                    console.log('✅ Contenu trouvé dans rcmail_webmail.env');
                    return buildRawEmail(mailData, content, 'text/html');
                }
            }
            
            // Chercher dans les éléments cachés du DOM
            const hiddenContents = document.querySelectorAll('div[style*="display:none"], div[style*="display: none"]');
            for (const element of hiddenContents) {
                const text = element.textContent || element.innerHTML;
                if (text && text.length > 200 && text.includes(mailData.subject)) {
                    console.log('✅ Contenu trouvé dans élément caché');
                    return buildRawEmail(mailData, element.innerHTML, 'text/html');
                }
            }
            
        } catch (e) {
            console.error('Erreur extraction données Roundcube:', e);
        }
        
        return null;
    }
    
    /**
     * Extraction depuis le preview panel
     */
    function extractFromPreviewPanel(mailData) {
        try {
            // Chercher dans le panneau de prévisualisation
            const previewSelectors = [
                '#messagepreview',
                '.messagepreview',
                '#message-content',
                '.message-content',
                '#messageview .message-body',
                '.messageview .message-body',
                '.message-part'
            ];
            
            for (const selector of previewSelectors) {
                const element = document.querySelector(selector);
                if (element) {
                    const html = element.innerHTML;
                    const text = element.textContent || element.innerText;
                    
                    // Vérifier que c'est bien le bon message
                    if ((html.includes(mailData.subject) || text.includes(mailData.subject)) 
                        && (html.length > 100 || text.length > 50)) {
                        
                        console.log('✅ Contenu trouvé dans preview panel:', selector);
                        return buildRawEmail(mailData, html || text, html ? 'text/html' : 'text/plain');
                    }
                }
            }
            
        } catch (e) {
            console.error('Erreur extraction preview panel:', e);
        }
        
        return null;
    }
    
    /**
     * Extraction via requête AJAX directe
     */
    async function extractViaAjax(mailData) {
        if (!mailData.uid) return null;
        
        try {
            console.log('📡 Tentative extraction via AJAX pour UID:', mailData.uid);
            
            // Construire l'URL de l'API Roundcube
            const ajaxUrl = window.location.href.split('?')[0] + 
                '?_task=mail&_action=show&_uid=' + mailData.uid + 
                '&_mbox=' + (mailData.folder || 'INBOX') +
                '&_framed=1&_extwin=1';
            
            const response = await fetch(ajaxUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const html = await response.text();
                if (html && html.length > 500 && !html.includes('watermark')) {
                    console.log('✅ Contenu récupéré via AJAX, longueur:', html.length);
                    return buildRawEmail(mailData, html, 'text/html');
                }
            }
            
        } catch (e) {
            console.log('⚠️ Extraction AJAX échouée:', e.message);
        }
        
        return null;
    }
    
    /**
     * Construction d'un email brut au format RFC 2822 - MODIFIÉE avec extraction de date
     */
    function buildRawEmail(mailData, content, contentType = 'text/plain') {
        let rawEmail = '';
        
        // MODIFICATION : Extraire la vraie date du contenu avant de construire l'email
        const realDate = extractDateFromEmailContent(content);
        if (realDate) {
            mailData.date = realDate;
            console.log('📅 Date mise à jour dans mailData:', realDate);
        }
        
        // En-têtes obligatoires
        rawEmail += 'MIME-Version: 1.0\\n';
        rawEmail += \`Content-Type: \${contentType}; charset=UTF-8\\n\`;
        
        if (mailData.message_id) {
            rawEmail += \`Message-ID: \${mailData.message_id}\\n\`;
        } else if (mailData.uid) {
            rawEmail += \`Message-ID: <\${mailData.uid}@localhost>\\n\`;
        }
        
        if (mailData.from_email || mailData.from) {
            const fromField = mailData.from_email || extractEmailFromString(mailData.from) || mailData.from;
            rawEmail += \`From: \${fromField}\\n\`;
        }
        
        if (mailData.subject) {
            rawEmail += \`Subject: \${mailData.subject}\\n\`;
        }
        
        if (mailData.date) {
            rawEmail += \`Date: \${mailData.date}\\n\`;
        }
        
        // En-têtes optionnels
        if (mailData.uid) {
            rawEmail += \`X-UID: \${mailData.uid}\\n\`;
        }
        
        if (mailData.folder) {
            rawEmail += \`X-Folder: \${mailData.folder}\\n\`;
        }
        
        rawEmail += \`X-Extracted-Method: Roundcube-v6\\n\`;
        
        // Ligne vide séparant les en-têtes du corps
        rawEmail += '\\n';
        
        // Corps du message
        rawEmail += content;
        
        return rawEmail;
    }
    
    /**
     * Fallback avec métadonnées seulement
     */
    function buildRawEmailFallback(mailData) {
        const content = \`Message: \${mailData.subject || 'Sans sujet'}

        Expéditeur: \${mailData.from || 'Expéditeur inconnu'}
        Email: \${mailData.from_email || 'Email non disponible'}
        Date: \${mailData.date || 'Date non disponible'}
        UID: \${mailData.uid || 'N/A'}

        [Contenu du message non disponible - extraction échouée]

---
Extraction: \${new Date().toISOString()}\`;
        
        return buildRawEmail(mailData, content, 'text/plain');
    }
    
    /**
     * Extraction d'email depuis une chaîne
     */
    function extractEmailFromString(str) {
        if (!str) return null;
        
        const emailRegex = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})/;
        const match = str.match(emailRegex);
        return match ? match[1] : null;
    }
    
    /**
     * Version améliorée de l'extraction de l'email expéditeur
     */
    function extractSenderEmailImproved(mailData) {
        if (mailData.from_email) return mailData.from_email;
        
        try {
            // 1. Extraction depuis le champ from
            if (mailData.from) {
                const email = extractEmailFromString(mailData.from);
                if (email) {
                    console.log('Email extrait du champ from:', email);
                    return email;
                }
            }
            
            // 2. Chercher dans la ligne du message sélectionné
            const messageRow = document.querySelector(\`tr[id*="\${mailData.uid}"], .messagelist tr.selected\`);
            if (messageRow) {
                // Chercher tous les éléments pouvant contenir un email
                const emailElements = messageRow.querySelectorAll('a[href^="mailto:"], [data-email], [title*="@"]');
                
                for (const element of emailElements) {
                    const href = element.getAttribute('href');
                    const dataEmail = element.getAttribute('data-email');
                    const title = element.getAttribute('title');
                    
                    if (href && href.startsWith('mailto:')) {
                        const email = href.replace('mailto:', '').split('?')[0];
                        if (email.includes('@')) {
                            console.log('Email trouvé dans href mailto:', email);
                            return email;
                        }
                    }
                    
                    if (dataEmail && dataEmail.includes('@')) {
                        console.log('Email trouvé dans data-email:', dataEmail);
                        return dataEmail;
                    }
                    
                    if (title && title.includes('@')) {
                        const email = extractEmailFromString(title);
                        if (email) {
                            console.log('Email trouvé dans title:', email);
                            return email;
                        }
                    }
                }
            }
            
            // 3. Chercher dans l'en-tête du message si affiché
            const messageHeader = document.querySelector('#messageheader, .messageheader');
            if (messageHeader) {
                const fromElement = messageHeader.querySelector('.from a[href^="mailto:"], .from [data-email]');
                if (fromElement) {
                    const href = fromElement.getAttribute('href');
                    const dataEmail = fromElement.getAttribute('data-email');
                    
                    if (href && href.startsWith('mailto:')) {
                        return href.replace('mailto:', '').split('?')[0];
                    }
                    if (dataEmail) {
                        return dataEmail;
                    }
                }
            }
            
            // 4. Si toujours rien, essayer d'extraire depuis le texte visible
            const visibleText = mailData.from || '';
            const emailMatch = visibleText.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})/);
            if (emailMatch) {
                console.log('Email extrait du texte visible:', emailMatch[1]);
                return emailMatch[1];
            }
            
            console.log('⚠️ Impossible de trouver l\\'email de l\\'expéditeur');
            return 'unknown@example.com';
            
        } catch (error) {
            console.error('Erreur extraction email expéditeur:', error);
            return 'error@example.com';
        }
    }
    
    /**
     * Extraction complète du contenu brut avec toutes les stratégies - VERSION AMÉLIORÉE avec extraction de date
     */
    async function extractRawEmailContentImproved(mailData) {
        // Si une extraction est en cours, ajouter à la queue
        if (extractionInProgress) {
            console.log('⏳ Extraction en cours, ajout en queue');
            return new Promise((resolve) => {
                extractionQueue.push({ mailData, resolve });
            });
        }
        
        extractionInProgress = true;
        
        try {
            console.log('📄 Début extraction améliorée pour:', mailData.subject);
            
            // 1. Vérifier d'abord les données déjà en cache
            let rawEmail = extractFromRoundcubeData(mailData);
            if (rawEmail) {
                console.log('✅ Contenu récupéré depuis le cache Roundcube');
                // MODIFICATION : La date est déjà extraite dans buildRawEmail
                return rawEmail;
            }
            
            // 2. Essayer le preview panel
            rawEmail = extractFromPreviewPanel(mailData);
            if (rawEmail) {
                console.log('✅ Contenu récupéré depuis le preview panel');
                // MODIFICATION : La date est déjà extraite dans buildRawEmail
                return rawEmail;
            }
            
            // 3. Gestion avancée de l'iframe
            const messageFrame = document.querySelector('iframe[name="messagecontframe"], iframe#messagecontframe');
            if (messageFrame) {
                
                // Vérifier si l'iframe est déjà prête
                if (isIframeReady(messageFrame)) {
                    console.log('✅ Iframe déjà prête, extraction directe');
                    rawEmail = extractFromIframe(messageFrame, mailData);
                    if (rawEmail) {
                        // MODIFICATION : La date est déjà extraite dans buildRawEmail
                        return rawEmail;
                    }
                }
                
                // Si pas prête ou contenu vide, forcer le rechargement
                console.log('🔄 Iframe pas prête, rechargement nécessaire');
                const reloadSuccess = await forceIframeReload(messageFrame, mailData.uid, mailData.folder);
                
                if (reloadSuccess) {
                    rawEmail = extractFromIframe(messageFrame, mailData);
                    if (rawEmail) {
                        console.log('✅ Contenu récupéré après rechargement iframe');
                        // MODIFICATION : La date est déjà extraite dans buildRawEmail
                        return rawEmail;
                    }
                }
            }
            
            // 4. Fallback avec requête AJAX
            rawEmail = await extractViaAjax(mailData);
            if (rawEmail) {
                console.log('✅ Contenu récupéré via AJAX');
                // MODIFICATION : La date est déjà extraite dans buildRawEmail
                return rawEmail;
            }
            
            // 5. Dernier recours : construire avec les métadonnées
            console.log('⚠️ Fallback - construction avec métadonnées');
            return buildRawEmailFallback(mailData);
            
        } catch (error) {
            console.error('❌ Erreur extraction complète:', error);
            return buildRawEmailFallback(mailData);
            
        } finally {
            extractionInProgress = false;
            
            // Traiter la queue
            if (extractionQueue.length > 0) {
                const next = extractionQueue.shift();
                setTimeout(async () => {
                    const result = await extractRawEmailContentImproved(next.mailData);
                    next.resolve(result);
                }, 100);
            }
        }
    }
    
    /**
     * Extraction des métadonnées du mail - MODIFIÉE pour chercher la vraie date
     */
    function extractMailData() {
        let mailData = {
            subject: null,
            from: null,
            from_email: null,
            date: null,
            message_id: null,
            uid: null,
            folder: null,
            has_attachments: false,
            is_read: false,
            raw_email: null,
            attachments: []
        };
        
        try {
            // Extraction via API Roundcube (prioritaire)
            if (window.rcmail && window.rcmail.env) {
                const env = window.rcmail.env;
                
                mailData.uid = env.uid ? String(env.uid) : null;
                mailData.folder = env.mailbox || null;
                mailData.subject = env.subject || null;
                mailData.message_id = env.message_id || null;
                
                // MODIFICATION : Chercher la vraie date dans l'API Roundcube d'abord
                if (env.date) {
                    mailData.date = env.date;
                    console.log('📅 Date trouvée dans rcmail.env:', env.date);
                }
                
                // Extraction de l'expéditeur depuis l'API
                if (env.sender) {
                    mailData.from = env.sender;
                    mailData.from_email = extractEmailFromString(env.sender);
                } else if (env.from) {
                    mailData.from = env.from;
                    mailData.from_email = extractEmailFromString(env.from);
                }
            }
            
            // Fallback DOM pour les données manquantes
            const selectedMessage = document.querySelector(
                '.messagelist tr.selected, #messagelist tr.selected, tr.selected, [id^="rcmrow"].selected'
            );
            
            if (selectedMessage) {
                
                // UID depuis l'ID de la ligne avec décodage base64
                if (!mailData.uid && selectedMessage.id) {
                    const match = selectedMessage.id.match(/^rcmrow(.+)$/);
                    if (match && match[1]) {
                        try {
                            mailData.uid = atob(match[1]);
                            console.log('UID décodé depuis DOM:', mailData.uid);
                        } catch (e) {
                            console.error('Erreur décodage UID:', e);
                            // Fallback sur l'ancienne méthode
                            const uidMatch = selectedMessage.id.match(/\\d+/);
                            if (uidMatch) {
                                mailData.uid = uidMatch[0];
                            }
                        }
                    }
                }
                
                // Sujet
                if (!mailData.subject) {
                    const subjectCell = selectedMessage.querySelector('td.subject a.subject, td.subject .subject');
                    if (subjectCell) {
                        mailData.subject = subjectCell.textContent.trim();
                    }
                }
                
                // Expéditeur avec recherche approfondie
                if (!mailData.from) {
                    const fromCell = selectedMessage.querySelector('td.from, td.sender, .from, .sender, span.adr');
                    if (fromCell) {
                        mailData.from = fromCell.textContent.trim();
                        
                        // Chercher l'email dans tous les attributs possibles
                        const emailAttrs = ['data-email', 'title', 'data-original-title', 'aria-label', 'data-tooltip'];
                        for (const attr of emailAttrs) {
                            const value = fromCell.getAttribute(attr);
                            if (value && value.includes('@')) {
                                mailData.from_email = extractEmailFromString(value);
                                break;
                            }
                        }
                        
                        // Chercher dans les éléments enfants
                        if (!mailData.from_email) {
                            const emailElements = fromCell.querySelectorAll('a[href^="mailto:"], [data-email]');
                            for (const elem of emailElements) {
                                const href = elem.getAttribute('href');
                                const dataEmail = elem.getAttribute('data-email');
                                if (href && href.startsWith('mailto:')) {
                                    mailData.from_email = href.replace('mailto:', '');
                                    break;
                                } else if (dataEmail) {
                                    mailData.from_email = dataEmail;
                                    break;
                                }
                            }
                        }
                        
                        if (!mailData.from_email) {
                            mailData.from_email = extractEmailFromString(mailData.from);
                        }
                    }
                }
                
                // MODIFICATION : Chercher la vraie date dans les attributs title ou data-date
                if (!mailData.date) {
                    const dateCell = selectedMessage.querySelector('td.date, .date, .msgdate');
                    if (dateCell) {
                        // Chercher la vraie date dans les attributs d'abord
                        const realDate = dateCell.getAttribute('title') || 
                                       dateCell.getAttribute('data-date') || 
                                       dateCell.getAttribute('data-original-title');
                        
                        if (realDate && realDate !== dateCell.textContent.trim()) {
                            mailData.date = realDate;
                            console.log('📅 Vraie date trouvée dans attribut:', realDate);
                        } else {
                            mailData.date = dateCell.textContent.trim();
                        }
                    }
                }
                
                // Flags
                mailData.is_read = !selectedMessage.classList.contains('unread');
                mailData.has_attachments = !!selectedMessage.querySelector('.attachment, .icon.attachment');
            }
        // Réinitialiser le tableau des pièces jointes
        mailData.attachments = [];
        const processedAttachments = new Set(); // Pour éviter les doublons

        if (mailData.attachments.length === 0) {
        const messageFrame = document.querySelector('iframe[name="messagecontframe"]');
        if (messageFrame) {
            try {
                const frameDoc = messageFrame.contentDocument;
                if (frameDoc) {
                    const attachLinks = frameDoc.querySelectorAll('a[href*="_action=get"]');
                    
                    attachLinks.forEach(link => {
                        const href = link.getAttribute('href');
                        let name = link.textContent.trim();
                        
                        if (href && name) {
                            // NETTOYER LE NOM (enlever la taille)
                            name = name.replace(/\s*\([^)]*\)\s*$/, '').trim();
                            
                            if (name.length > 0) {
                                mailData.attachments.push({
                                    name: name,
                                    download_url: makeAbsoluteUrl(href),
                                    source: 'dom_extraction'
                                });
                                console.log('✅ PJ trouvée:', name);
                            }
                        }
                    });
                    console.log('TOTAL PJ dans mailData.attachments:', mailData.attachments.length);

                }
            } catch (e) {
                console.log("Impossible d'accéder au contenu de l'iframe:", e.message);
            }
        }
    }


        function makeAbsoluteUrl(relativeUrl) {
            if (!relativeUrl) return '';
            if (relativeUrl.startsWith('http')) return relativeUrl;
            
            const base = window.location.protocol + '//' + window.location.host;
            if (relativeUrl.startsWith('/')) {
                return base + relativeUrl;
            } else {
                const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                return base + currentPath + '/' + relativeUrl;
            }
        }

        function cleanAttachmentName(name) {
            // Nettoyer le nom (enlever les infos de taille en fin)
            return name.replace(/\(~\d+\s*[kmgtpezy]?[o]\)$/i, '').trim();
        }

        console.log('Pièces jointes extraites:', mailData.attachments);
            
        } catch (e) {
            console.error('Erreur extraction données mail:', e);
        }
        if (window.parent && mailData.attachments.length > 0) {
        window.parent.postMessage({
            type: 'roundcube_maildata_complete',
            uid: mailData.uid,
            mailData: mailData
        }, '*');
    }
        
        return mailData;
    }
    
    /**
     * Envoi des données de mail avec extraction améliorée - VERSION COMPLÈTE
     */
    async function sendMailDataImproved(mailData) {

    if ((!mailData.uid || mailData.uid === 'no-uid') && !mailData.subject && isInComposeMode()) {
            console.log('🚫 Mode composition détecté sans données mail valides, abandon traitement');
            return;
        }

        if (!mailData.uid && !mailData.subject) {
            console.log('Pas de UID ni sujet, abandon');
            return;
        }
        
        const currentUID = mailData.uid || 'no-uid';
        
        // Si on traite déjà ce mail, attendre
        if (pendingExtractions.has(currentUID)) {
            console.log('Extraction déjà en cours pour:', currentUID);
            return;
        }
        
        // Si c'est le même mail et qu'on a déjà tout, pas besoin de re-extraire
        if (currentUID === lastUID && currentMailData && currentMailData.raw_email) {
            console.log('Mail déjà traité avec contenu complet:', currentUID);
            return;
        }
        
        lastUID = currentUID;
        mailData.timestamp = new Date().toISOString();
        
        // Améliorer l'extraction de l'email expéditeur
        if (!mailData.from_email) {
            mailData.from_email = extractSenderEmailImproved(mailData);
        }
        
        console.log('📧 Traitement du mail:', mailData.subject, 'UID:', currentUID, 'Date initiale:', mailData.date);
        
        // Marquer comme en cours d'extraction
        pendingExtractions.set(currentUID, true);
        
        try {
   

            // Envoyer d'abord les données de base (sans attendre)
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'roundcube_mail_selected',
                    data: { ...mailData }
                }, '*');
            }
            
            // Extraire le contenu brut de manière asynchrone
            console.log('🔄 Début extraction du contenu brut...');
            
            const rawEmail = await extractRawEmailContentImproved(mailData);
            
            if (rawEmail && rawEmail.length > 50) {
                mailData.raw_email = rawEmail;
                console.log('✅ Contenu brut extrait, longueur:', rawEmail.length, 'Date finale:', mailData.date);
                
                // Envoyer le message complet
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'roundcube_mail_complete',
                        data: { ...mailData }
                    }, '*');
                }
                
                // Mettre à jour les données globales
                currentMailData = { ...mailData };
                
            } else {
                console.warn('⚠️ Extraction du contenu brut échouée');
                
                // Envoyer quand même les métadonnées
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'roundcube_mail_complete',
                        data: {
                            ...mailData,
                            raw_email: \`Métadonnées seulement - Extraction échouée\\n\\nSujet: \${mailData.subject}\\nDe: \${mailData.from}\\nDate: \${mailData.date}\`
                        }
                    }, '*');
                }
            }
            
        } catch (error) {
            console.error('❌ Erreur lors de l\\'extraction:', error);
            
            // En cas d'erreur, envoyer au moins les métadonnées
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'roundcube_mail_complete',
                    data: {
                        ...mailData,
                        raw_email: \`Erreur d'extraction: \${error.message}\\n\\nMétadonnées:\\nSujet: \${mailData.subject}\\nDe: \${mailData.from}\\nDate: \${mailData.date}\`
                    }
                }, '*');
            }
            
        } finally {
            // Nettoyer le flag d'extraction en cours
            pendingExtractions.delete(currentUID);
            
            // Nettoyer les anciennes extractions (éviter les fuites mémoire)
            if (pendingExtractions.size > 10) {
                pendingExtractions.clear();
            }
        }
    }
    
    // Observateur pour détecter les changements
    const observer = new MutationObserver(() => {
        clearTimeout(window.extractTimeout);
        window.extractTimeout = setTimeout(async () => {
            const mailData = extractMailData();
            if (mailData.uid || mailData.subject) {
                await sendMailDataImproved(mailData);
            }
        }, 1000);
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'id', 'src']
    });
    
    // Événements de clic avec délai pour Roundcube
    document.addEventListener('click', function(e) {
        const messageRow = e.target.closest('tr[id^="rcmrow"], .messagelist tr');
        if (messageRow) {
            console.log('🖱️ Clic détecté sur message');
            setTimeout(async () => {
                const mailData = extractMailData();
                await sendMailDataImproved(mailData);
            }, 1500);
        }
    }, true);
    
    // Changements d'URL/hash
    window.addEventListener('hashchange', function() {
        setTimeout(async () => {
            const mailData = extractMailData();
            await sendMailDataImproved(mailData);
        }, 2000);
    });
    
    // Vérification périodique
    
    // Test initial
    
    
    console.log('✅ Détection Roundcube v6.0 initialisée avec améliorations complètes et extraction de date');
})();
    `;
}

/**
 * Injection du script de détection dans l'iframe - CONSERVÉ
 */
function injectDetectionScript() {
    const iframe = document.getElementById('roundcube-iframe');
    if (!iframe) return;
    
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        
        if (iframeDoc) {
            if (!iframeDoc.getElementById('roundcube-detection-script')) {
                const script = iframeDoc.createElement('script');
                script.id = 'roundcube-detection-script';
                script.textContent = getIframeDetectionScript();
                
                iframeDoc.head.appendChild(script);
                console.log('✅ Script de détection injecté avec succès');
            }
        } else {
            console.warn('⚠️ Impossible d\'accéder au contenu de l\'iframe (cross-origin)');
        }
    } catch (error) {
        console.warn('⚠️ Impossible d\'injecter le script de détection:', error.message);
    }
}
// Intercepter l'envoi de mails
function interceptMailSending() {
    document.addEventListener('click', function(e) {
        const sendButton = e.target.closest('input[type="submit"][value*="Send"], button[name="_send"], .button.send');
        if (sendButton) {
            console.log('Envoi de mail détecté');
            
            const composeForm = sendButton.closest('form');
            if (composeForm) {
                const mailData = extractComposeData(composeForm);
                
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'mail_being_sent',
                        data: mailData
                    }, '*');
                }
            }
        }
    }, true);
}

function extractComposeData(form) {
    const subject = form.querySelector('input[name="_subject"]')?.value || 'Sans sujet';
    const to = form.querySelector('input[name="_to"], textarea[name="_to"]')?.value || '';
    const body = form.querySelector('textarea[name="_message"], iframe[name="composebody"]');
    
    let bodyContent = '';
    if (body) {
        if (body.tagName === 'TEXTAREA') {
            bodyContent = body.value;
        } else if (body.tagName === 'IFRAME') {
            try {
                bodyContent = body.contentDocument?.body?.innerHTML || '';
            } catch (e) {
                bodyContent = 'Contenu HTML';
            }
        }
    }
    
    return {
        subject: subject,
        to: to,
        body: bodyContent,
        timestamp: Date.now()
    };
}

// Ajouter à la fin de votre fonction d'initialisation
interceptMailSending();


// Données des comptes (pour JavaScript)
const accounts = <?php echo json_encode($accounts); ?>;
// Ajoutez cette configuration après la ligne "const accounts = ..."
console.log('🔄 Comptes détectés:', accounts);
const roundcubeBaseUrl = "<?php echo $roundcube_base_url; ?>";

/**
 * Fonction pour décrypter le mot de passe côté client
 */
function decryptPassword(encrypted) {
    try {
        return atob(encrypted);
    } catch (e) {
        console.error('Erreur déchiffrement mot de passe:', e);
        return '';
    }
}


/**
 * Changer de compte webmail
 */
function switchAccount() {
    const select = document.getElementById('account-select');
    if (!select) return;
    
    const accountId = select.value;
    
    // Trouver le compte sélectionné
    const account = accounts.find(acc => acc.rowid == accountId);
    
    if (account) {
        console.log('🔄 Changement de compte vers:', account.email);
        
        // 1. Mettre à jour le bandeau avec les infos du nouveau compte
        updateBandeauAccount(account);
        
        // 2. Construire les URLs
        const password = decryptPassword(account.password_encrypted);
        const timestamp = new Date().getTime();
        const sharedSecret = 'MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdW';
        const dolibarrUserId = '<?php echo $user->id; ?>';
        
        // URL de déconnexion Roundcube (VRAIE déconnexion)
        const logoutUrl = roundcubeBaseUrl + '?_task=logout';
        
        // URL de connexion avec le nouveau compte
        const loginUrl = roundcubeBaseUrl + 
                        '?_autologin=1' +
                        '&secret=' + encodeURIComponent(sharedSecret) +
                        '&dolibarr_id=' + encodeURIComponent(dolibarrUserId) +
                        '&account_id=' + encodeURIComponent(account.rowid) +
                        '&_nocache=' + timestamp;
        
        // 3. Afficher un indicateur de chargement
        const iframe = document.getElementById('roundcube-iframe');
        const container = document.getElementById('roundcube-container');
        
        showSwitchProgress(account.email);
        
        // 4. Séquence de déconnexion/reconnexion FORCÉE
        performAccountSwitch(iframe, logoutUrl, loginUrl, account);
    }
}
/**
 * Afficher la progression du changement de compte
 */
function showSwitchProgress(email) {
    const container = document.getElementById('roundcube-container');
    
    // Créer overlay de chargement
    let loadingOverlay = document.getElementById('loading-overlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loading-overlay';
        loadingOverlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            font-size: 16px;
        `;
        container.appendChild(loadingOverlay);
    }
    
    loadingOverlay.style.display = 'flex';
    loadingOverlay.innerHTML = `
        <div style="text-align: center; max-width: 400px;">
            <div style="font-size: 18px; margin-bottom: 10px;">🔄 Changement de compte</div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #007cba;">${email}</strong>
            </div>
            <div id="switch-step" style="margin-bottom: 15px; color: #666;">
                Initialisation...
            </div>
            <div style="width: 300px; height: 6px; background: #eee; border-radius: 3px; margin: 0 auto;">
                <div id="switch-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #007cba, #28a745); border-radius: 3px; transition: width 0.5s ease;"></div>
            </div>
            <div style="margin-top: 10px; font-size: 12px; color: #999;">
                Déconnexion puis reconnexion en cours...
            </div>
        </div>
    `;
}


function updateSwitchProgress(percent, step) {
    const progressBar = document.getElementById('switch-progress-bar');
    const stepEl = document.getElementById('switch-step');
    
    if (progressBar) progressBar.style.width = percent + '%';
    if (stepEl) stepEl.textContent = step;
}


function hideSwitchProgress() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        // Animation de fade out
        loadingOverlay.style.transition = 'opacity 0.5s ease';
        loadingOverlay.style.opacity = '0';
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
            loadingOverlay.style.opacity = '1';
        }, 500);
    }
}


function performAccountSwitch(iframe, logoutUrl, loginUrl, account) {
    let stepCount = 0;
    const totalSteps = 6;
    
    // Timeout de sécurité global
    const safetyTimeout = setTimeout(() => {
        console.error('❌ Timeout lors du changement de compte');
        hideSwitchProgress();
        alert('Le changement de compte a pris trop de temps. Veuillez actualiser la page.');
    }, 15000);
    
    const nextStep = (percent, message, callback, delay = 1000) => {
        updateSwitchProgress(percent, message);
        setTimeout(callback, delay);
    };
    
    // Étape 1: Vider l'iframe complètement
    nextStep(10, 'Préparation...', () => {
        iframe.src = 'about:blank';
        
        // Étape 2: Forcer la suppression des cookies Roundcube (si possible)
        nextStep(20, 'Nettoyage de la session...', () => {
            
            // Étape 3: Aller sur la page de logout de Roundcube
            nextStep(35, 'Déconnexion de Roundcube...', () => {
                
                // Event handler pour la déconnexion
                const logoutHandler = () => {
                    console.log('📤 Déconnexion effectuée');
                    
                    // Étape 4: Attendre que la déconnexion soit effective
                    nextStep(50, 'Vérification de la déconnexion...', () => {
                        
                        // Étape 5: Vider à nouveau pour être sûr
                        iframe.src = 'about:blank';
                        
                        nextStep(65, 'Préparation de la nouvelle connexion...', () => {
                            
                            // Étape 6: Connexion avec le nouveau compte
                            nextStep(80, `Connexion à ${account.email}...`, () => {
                                
                                // Event handler pour la nouvelle connexion
                                const loginHandler = () => {
                                    console.log('✅ Nouvelle connexion établie');
                                    
                                    nextStep(95, 'Finalisation...', () => {
                                        
                                        // Vérifier que Roundcube est bien chargé
                                        setTimeout(() => {
                                            nextStep(100, 'Connexion réussie !', () => {
                                                
                                                // Nettoyage et finalisation
                                                clearTimeout(safetyTimeout);
                                                hideSwitchProgress();
                                                
                                                // Notifier le changement
                                                notifyAccountChange(account);
                                                
                                                // Réinjecter le script de détection
                                                setTimeout(injectDetectionScript, 2000);
                                                
                                                console.log('🎉 Changement de compte terminé avec succès');
                                                
                                            }, 1000);
                                        }, 2000);
                                    }, 500);
                                };
                                
                                // Configurer le handler pour la connexion
                                iframe.onload = loginHandler;
                                iframe.onerror = () => {
                                    console.error('❌ Erreur lors de la connexion');
                                    clearTimeout(safetyTimeout);
                                    hideSwitchProgress();
                                    alert('Erreur lors de la connexion au nouveau compte');
                                };
                                
                                // Lancer la connexion
                                iframe.src = loginUrl;
                                
                            }, 500);
                        }, 800);
                    }, 1500);
                };
                
                // Configurer le handler pour la déconnexion
                iframe.onload = logoutHandler;
                iframe.onerror = () => {
                    console.warn('⚠️ Erreur lors de la déconnexion, on continue...');
                    logoutHandler(); // Continuer même si la déconnexion échoue
                };
                
                // Lancer la déconnexion
                iframe.src = logoutUrl;
                
            }, 500);
        }, 800);
    }, 500);
}


function updateBandeauAccount(account) {
    // Mettre à jour les éléments du bandeau si ils existent
    const bandeauEmail = document.querySelector('#bandeau-current-email, .bandeau-email');
    const bandeauAccount = document.querySelector('#bandeau-current-account, .bandeau-account');
    
    if (bandeauEmail) {
        bandeauEmail.textContent = account.email;
    }
    
    if (bandeauAccount) {
        bandeauAccount.textContent = account.account_name || account.email;
    }
    
    // Mettre à jour le titre de la page
    document.title = `Roundcube - ${account.email}`;
    
    console.log('📧 Bandeau mis à jour pour le compte:', account.email);
}


function notifyAccountChange(account) {
    // Envoyer un message au bandeau pour l'informer du changement
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'account_changed',
            data: {
                accountId: account.rowid,
                email: account.email,
                accountName: account.account_name,
                imapHost: account.imap_host,
                isDefault: account.is_default
            }
        }, '*');
    }
    
    // Déclencher un événement personnalisé pour d'autres composants
    const event = new CustomEvent('roundcubeAccountChanged', {
        detail: account
    });
    document.dispatchEvent(event);
    
    console.log('📨 Notification changement de compte envoyée');
}


function refreshRoundcube() {
    const iframe = document.getElementById('roundcube-iframe');
    iframe.src = iframe.src;
    console.log('🔄 Actualisation de Roundcube');
}

/**
 * Ouvrir dans une nouvelle fenêtre
 */
function openNewWindow() {
    const iframe = document.getElementById('roundcube-iframe');
    window.open(iframe.src, 'roundcube', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

/**
 * Gérer les erreurs d'iframe
 */
function handleIframeError() {
    document.getElementById('roundcube-error').style.display = 'block';
    document.getElementById('roundcube-iframe').style.display = 'none';
}

/**
 * Initialisation de la page - CONSERVÉ + AMÉLIORÉ
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Roundcube Module - Version complète avec autologin chargée');
    console.log('📧 Comptes disponibles:', accounts.length);
    console.log('🔄 Comptes détectés:', accounts);

    const iframe = document.getElementById('roundcube-iframe');
    
    // Récupérer les paramètres de présélection depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const preselectData = {
        type: urlParams.get('preselect_type'),
        id: urlParams.get('preselect_id'),
        name: urlParams.get('preselect_name')
    };

    console.log('Paramètres URL détectés:', preselectData);
    
    // Injection du script à chaque chargement de l'iframe
    
    iframe.onload = function() {
    console.log('Iframe Roundcube chargée');

    // Récupérer les paramètres de présélection depuis l'URL de la page parente
    const urlParams = new URLSearchParams(window.location.search);
    const preselectData = {
        type: urlParams.get('preselect_type'),
        id: urlParams.get('preselect_id'),
        name: urlParams.get('preselect_name')
    };

    // Si des paramètres de présélection sont présents, on les envoie à l'iframe parente
    if (preselectData.type && preselectData.id) {
        console.log('✅ Envoi des données à la page parente:', preselectData);
        window.parent.postMessage({
            type: 'preselect_module',
            data: preselectData
        }, '*');
    }

    // Le reste de votre code, comme l'injection périodique
    setTimeout(injectDetectionScript, 2000);
    };
    
    // Gestion des erreurs
    iframe.onerror = function() {
        console.error('❌ Erreur de chargement Roundcube');
        handleIframeError();
    };
    
    // Réinjection périodique
    setInterval(function() {
        if (iframe.contentDocument || iframe.contentWindow) {
            injectDetectionScript();
        }
    }, 5000);
    
    // Écouter les messages du bandeau ou d'autres composants
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type) {
            switch (event.data.type) {
                case 'bandeau_account_change_request':
                    // Le bandeau demande un changement de compte
                    const requestedAccountId = event.data.accountId;
                    const select = document.getElementById('account-select');
                    if (select && requestedAccountId) {
                        select.value = requestedAccountId;
                        switchAccount();
                    }
                    break;
                    
                case 'roundcube_ready':
                    // Roundcube est prêt, envoyer les infos du compte actuel
                    const currentSelect = document.getElementById('account-select');
                    if (currentSelect) {
                        const currentAccountId = currentSelect.value;
                        const currentAccount = accounts.find(acc => acc.rowid == currentAccountId);
                        if (currentAccount) {
                            notifyAccountChange(currentAccount);
                        }
                    }
                    break;
                    
                case 'bandeau_ready':
                    // NOUVEAU : Le bandeau est prêt, envoyer la présélection si elle existe
                    if (preselectData.type && preselectData.id) {
                        console.log('✅ Bandeau prêt, envoi présélection:', preselectData);
                        
                        // Trouver l'iframe du bandeau et lui envoyer les données
                        const bandeauFrame = document.querySelector('#bandeau-classification-frame');
                        if (bandeauFrame && bandeauFrame.contentWindow) {
                            bandeauFrame.contentWindow.postMessage({
                                type: 'preselect_module',
                                data: preselectData
                            }, '*');
                        } else {
                            console.error('❌ Iframe bandeau non trouvée');
                            // Fallback: essayer d'envoyer directement au window parent
                            window.postMessage({
                                type: 'preselect_module',
                                data: preselectData
                            }, '*');
                        }
                    }
                    break;
            }
        }
    });
    
    // Initialiser avec le compte par défaut
    setTimeout(() => {
        const select = document.getElementById('account-select');
        if (select) {
            const defaultAccountId = select.value;
            const defaultAccount = accounts.find(acc => acc.rowid == defaultAccountId);
            if (defaultAccount) {
                updateBandeauAccount(defaultAccount);
            }
        }
    }, 1000);
    
    console.log('✅ Module Roundcube complet initialisé');
});
// Styles CSS additionnels
const style = document.createElement('style');
style.textContent = `
    #account-selector {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    #account-selector select {
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }
    
    #account-selector button {
        background: #007cba;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    #account-selector button:hover {
        background: #005a87;
    }
    
    #roundcube-container {
        position: relative;
    }
    
    #loading-overlay {
        text-align: center;
        color: #007cba;
        font-weight: bold;
    }
    
    #loading-overlay small {
        color: #666;
        font-weight: normal;
    }
    
    #roundcube-error {
        text-align: center;
        padding: 50px;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 5px;
        margin: 20px;
    }
`;
document.head.appendChild(style);
</script>

<?php
llxFooter();
?>
