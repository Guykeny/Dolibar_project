

console.log('🔧 Chargement Bandeau JavaScript Manager (Version Stable Sans Réinitialisation)...');

// Variables globales
let currentMailData = null;
let currentMailUID = null;
let currentMailId = null;
let selectedEntities = { societe: null, contact: null, projet: null , user: null, invoice: null , commande: null , usergroup: null , adherent: null , holiday: null , expensereport: null , propal: null , contract: null,fichinter: null, supplier_proposal: null, supplier_order: null, supplier_invoice: null,reception: null,salary: null,loan: null,expensereport: null,event: null,accounting: null,affaire: null,expedition: null,don: null,ticket: null};
let searchTimeout = null;
let lastProcessedMailUID = null;
let isFormDisplayed = false; // Pour tracker si le formulaire est affiché
let mailDataBackup = {}; // Backup des données complètes par UID
let isEditMode = false; // Mode édition
let existingMailData = null; // Données du mail existant

let preselectData = null;
let compositionMode = false;
let compositionSelections = null;
let autoClassificationEnabled = true;
let pendingSentMail = null;
let activeModules = [];

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'iframe_ready') {
        const preselectData = {
            type: new URLSearchParams(window.location.search).get('preselect_type'),
            id: new URLSearchParams(window.location.search).get('preselect_id'),
            name: new URLSearchParams(window.location.search).get('preselect_name')
        };
        
        if (preselectData.type && preselectData.id) {
            document.getElementById('roundcube-iframe').contentWindow.postMessage({
                type: 'preselect_module',
                data: preselectData
            }, '*');
        }
    }
    
    // Le reste de votre code qui traite 'preselect_module' et 'mail_being_sent'
    if (e.data && e.data.type === 'preselect_module') {
        preselectData = e.data.data;
        if (isFormDisplayed) {
            applyPreselection(preselectData);
        }
    }
    
    if (e.data && e.data.type === 'mail_being_sent') {
        handleMailBeingSent(e.data.data);
    }
});
function applyPreselection(preselectData) {
    if (!preselectData) return;
    
    console.log('Application de la présélection:', preselectData);
    
    // Afficher le bandeau avec le contexte
    const contextDisplay = document.getElementById('context-display');
    if (contextDisplay) {
        let contextText = '';
        
        switch(preselectData.type) {
            case 'societe':
                contextText = `Tiers: ${preselectData.name}`;
                break;
            case 'contact':
                contextText = `Contact: ${preselectData.name}`;
                break;
            case 'projet':
                contextText = `Projet: ${preselectData.name}`;
                break;
            case 'propal':
                contextText = `Proposition: ${preselectData.name}`;
                break;
            case 'commande':
                contextText = `Commande: ${preselectData.name}`;
                break;
            case 'invoice':
                contextText = `Facture: ${preselectData.name}`;
                break;
            default:
                contextText = `${preselectData.type}: ${preselectData.name}`;
        }
        
        contextDisplay.innerHTML = `
            <div class="dolibarr-context">
                📎 ${contextText} (#${preselectData.id})
            </div>
        `;
        contextDisplay.style.display = 'block';
    }
}
// Signal que le bandeau est prêt
window.parent.postMessage({
    type: 'bandeau_ready'
}, '*');

window.handleRoundcubeMessage = async function(e) {
    if (e.data && typeof e.data === 'object') {
        
        const mailData = e.data.data;
        
        // Ajouter cette vérification de sécurité
        if (!mailData) {
            console.log('📨 Message reçu sans données:', e.data.type);
            return;
        }
        
        console.log('📨 Message reçu:', e.data.type, 'UID:', mailData.uid, 'raw_email présent:', !!mailData.raw_email);
        if (mailData.attachments && Array.isArray(mailData.attachments)) {
            console.log('📎 Pièces jointes détectées:', mailData.attachments.length);
            mailData.attachments.forEach((att, index) => {
                console.log(`📎 PJ ${index + 1}:`, att.name, att.size || 'taille inconnue');
            });
        }
        
        if (e.data.type && e.data.type === 'roundcube_mail_complete' && mailData) {
            const newUID = mailData.uid;
            console.log('📧 Mail complet - UID:', newUID, 'currentUID:', currentMailUID, 'raw_email length:', mailData.raw_email ? mailData.raw_email.length : 'N/A');

            console.log('📨 Traitement du mail complet détecté:', newUID);
            
            
            if (currentMailUID && currentMailUID !== newUID) {
                console.log('🔄 Changement d\'UID détecté, réinitialisation complète');
                isEditMode = false;
                existingMailData = null;
                
                // AJOUT : Nettoyer TOUTES les sélections
                Object.keys(selectedEntities).forEach(key => {
                    selectedEntities[key] = null;
                });
                
                // Forcer la réinitialisation du formulaire
                isFormDisplayed = false;
            }
                        
            // Toujours mettre à jour avec les données complètes
            currentMailData = mailData;
            currentMailUID = newUID;
            currentMailId = mailData.message_id;
            
            console.log('✅ currentMailData mis à jour avec raw_email:', !!currentMailData.raw_email);
            
            // NOUVEAU: Vérifier si le mail existe déjà
            const existingMail = await checkIfMailExists(currentMailData);
            
            if (existingMail) {
                console.log('📧 Mail existant détecté, affichage en mode lecture');
                showExistingMailForm(currentMailData, existingMail);
            } else {
                console.log('📧 Nouveau mail, affichage formulaire classement normal');
                updateMailInfo(mailData);
            }
        }
        else if (e.data.type && e.data.type === 'roundcube_mail_selected' && mailData) {
            const newUID = mailData.uid;
            console.log('📧 Mail sélectionné - UID:', newUID, 'currentUID:', currentMailUID);
            
            console.log('📨 Nouveau mail sélectionné:', newUID);
            
            // NOUVEAU: Réinitialiser les modes spéciaux si changement d'UID
            if (currentMailUID && currentMailUID !== newUID) {
                console.log('🔄 Changement d\'UID détecté lors de la sélection, réinitialisation');
                isEditMode = false;
                existingMailData = null;
                // Forcer la réinitialisation du formulaire pour éviter de rester bloqué
                isFormDisplayed = false;
            }
            
            // Ne mettre à jour QUE si on n'a pas déjà les données complètes
            if (!currentMailData || !currentMailData.raw_email || currentMailData.uid !== newUID) {
                currentMailData = mailData;
                currentMailUID = newUID;
                currentMailId = mailData.message_id;
                console.log('📝 Mise à jour partielle sans raw_email');
            } else {
                console.log('🔒 Données complètes préservées');
            }
            
            // NOUVEAU: Vérifier si le mail existe déjà
            const existingMail = await checkIfMailExists(currentMailData);
            
            if (existingMail) {
                console.log('📧 Mail existant détecté, affichage en mode lecture');
                showExistingMailForm(currentMailData, existingMail);
            } else {
                console.log('📧 Nouveau mail, affichage formulaire classement normal');
                updateMailInfo(currentMailData);
            }
        }
    }
};
/**
 * Récupérer les modules actifs depuis le serveur
 */
async function loadActiveModules() {
    try {
        console.log('🔄 Chargement des modules actifs...');
        
        // Construire l'URL correcte pour get_active_modules.php
        const baseUrl = CONFIG.SAVE_URL ? CONFIG.SAVE_URL.replace('/save_mails.php', '') : '/custom/roundcubemodule/scripts';
        const modulesUrl = `${baseUrl}/get_active_modules.php`;
        
        console.log('📍 URL utilisée pour les modules:', modulesUrl);
        
        const response = await fetch(modulesUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const modules = await response.json();
        console.log('📦 Modules actifs reçus:', modules);
        
        activeModules = modules;
        return modules;
        
    } catch (error) {
        console.error('❌ Erreur lors du chargement des modules actifs:', error);
        // Fallback avec tous les modules si erreur
        activeModules = [
            {value: 'thirdparty', label: 'Tiers'},
            {value: 'contact', label: 'Contact'},
            {value: 'project', label: 'Projet / Opportunité'}
        ];
        return activeModules;
    }
}
function applyPreselection(preselect) {
    if (!preselect || !preselect.type || !preselect.id) return;
    
    console.log('Application de la présélection:', preselect);
    
    // Créer l'entité présélectionnée
    const entity = {
        id: preselect.id,
        label: preselect.name || `${preselect.type}_${preselect.id}`,
        name: preselect.name || `${preselect.type}_${preselect.id}`
    };
    
    // Sélectionner automatiquement
    selectEntity(preselect.type, entity);
    
    // Notification à l'utilisateur
    showNotification(`Module ${preselect.type} présélectionné automatiquement`, 'info');
}
/**
 * NOUVELLE FONCTION : Vérifier si un mail existe déjà dans la base
 */
async function checkIfMailExists(mailData) {
    console.log('🔍 Vérification mail:', mailData.uid, mailData.message_id);
    
    if (!mailData.uid && !mailData.message_id) {
        return null;
    }
    
    try {
        const checkUrl = CONFIG.API_URL || '/custom/roundcubemodule/classification/api/search-entities.php';
        console.log('📍 URL utilisée:', checkUrl + '?action=check_mail_exists');
        
        const response = await fetch(checkUrl + '?action=check_mail_exists', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                uid: mailData.uid,
                message_id: mailData.message_id,
                mbox: mailData.folder || 'INBOX'
            })
        });
        
        console.log('📡 Response status:', response.status);
        const responseText = await response.text();
        console.log('📄 Response text:', responseText);
        
        const result = JSON.parse(responseText);
        
        if (result.success && result.exists) {
            console.log('📧 Mail existant trouvé:', result.mailData);
            return result.mailData;
        }
        
        return null;
        
    } catch (error) {
        console.error('❌ Erreur vérification existence mail:', error);
        return null;
    }
}

async function processSentMailClassification(mailData) {
    console.log('🔄 Traitement du classement automatique du mail envoyé');
    
    try {
        updateClassificationStatus('Classification automatique du mail envoyé...', 'loading');
        
        const saveData = {
            uid: `sent_${mailData.timestamp}`,
            mbox: 'Sent',
            message_id: `<sent_${mailData.timestamp}@roundcube>`,
            subject: mailData.subject || 'Sans sujet',
            from_email: getCurrentUserEmail(),
            raw_email: buildRawEmailFromSentData(mailData),
            date: Math.floor(mailData.timestamp / 1000),
            direction: 'sent',
            attachments: mailData.attachments || [],
            to: mailData.to || '',
            links: []
        };
        
        // Ajouter les liens sélectionnés
        Object.keys(mailData.selectedEntities).forEach(type => {
            if (mailData.selectedEntities[type]) {
                saveData.links.push({
                    type: type === 'contract' ? 'contrat' : type,
                    id: parseInt(mailData.selectedEntities[type].id),
                    name: mailData.selectedEntities[type].label || mailData.selectedEntities[type].name || ''
                });
            }
        });
        
        console.log('📤 Sauvegarde du mail envoyé avec liens:', saveData.links);
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saveData)
        });
        
        const result = await response.json();
        
        if (result.status === 'OK') {
            updateClassificationStatus(`✅ Mail envoyé classé automatiquement! (ID: ${result.mail_id})`, 'success');
            showNotification('✅ Mail envoyé et classé automatiquement avec succès!', 'success');
            
            setTimeout(() => {
                clearAllSelections();
            }, 2000);
            
        } else {
            updateClassificationStatus(`❌ Erreur classement automatique: ${result.message}`, 'error');
            showNotification(`❌ Erreur lors du classement automatique: ${result.message}`, 'warning');
        }
        
    } catch (error) {
        console.error('❌ Erreur lors du classement automatique:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification('❌ Erreur lors du classement automatique du mail envoyé', 'error');
    }
}

/**
 * NOUVELLE FONCTION : Construire le contenu brut du mail envoyé
 */
function buildRawEmailFromSentData(mailData) {
    const currentDate = new Date().toISOString();
    
    let rawEmail = 'MIME-Version: 1.0\\n';
    rawEmail += 'Content-Type: text/html; charset=UTF-8\\n';
    rawEmail += `Message-ID: <sent_${mailData.timestamp}@roundcube>\\n`;
    rawEmail += `From: ${getCurrentUserEmail()}\\n`;
    rawEmail += `To: ${mailData.to}\\n`;
    
    if (mailData.cc) {
        rawEmail += `Cc: ${mailData.cc}\\n`;
    }
    
    if (mailData.bcc) {
        rawEmail += `Bcc: ${mailData.bcc}\\n`;
    }
    
    rawEmail += `Subject: ${mailData.subject}\\n`;
    rawEmail += `Date: ${currentDate}\\n`;
    rawEmail += 'X-Direction: sent\\n';
    rawEmail += 'X-Auto-Classified: true\\n';
    rawEmail += '\\n';
    rawEmail += mailData.body || 'Contenu du mail';
    
    return rawEmail;
}

function getCurrentUserEmail() {
    try {
        // 1. PRIORITÉ : Récupérer depuis Roundcube l'email du compte connecté
        const iframe = document.getElementById('roundcube-iframe');
        if (iframe && iframe.contentWindow && iframe.contentWindow.rcmail) {
            const rcmail = iframe.contentWindow.rcmail;
            
            // L'email de l'utilisateur connecté dans Roundcube
            if (rcmail.env && rcmail.env.username) {
                console.log('Email expéditeur récupéré depuis Roundcube:', rcmail.env.username);
                return rcmail.env.username;
            }
            
            // Fallback sur les identités
            if (rcmail.env && rcmail.env.identities) {
                const identities = Object.values(rcmail.env.identities);
                if (identities.length > 0 && identities[0].email) {
                    console.log('Email expéditeur depuis identité Roundcube:', identities[0].email);
                    return identities[0].email;
                }
            }
        }
    } catch (e) {
        console.log('Impossible de récupérer l\'email depuis Roundcube:', e.message);
    }
    
    // 2. Récupérer depuis le sélecteur de comptes
    try {
        const accountSelect = document.getElementById('account-select');
        if (accountSelect && accountSelect.selectedOptions.length > 0) {
            const optionText = accountSelect.selectedOptions[0].textContent;
            // Extraire l'email depuis le texte de l'option
            const emailMatch = optionText.match(/([^@\s]+@[^@\s\)]+)/);
            if (emailMatch) {
                console.log('Email expéditeur depuis sélecteur compte:', emailMatch[1]);
                return emailMatch[1];
            }
        }
    } catch (e) {
        console.log('Impossible de récupérer depuis sélecteur compte:', e.message);
    }
    
    // 3. Depuis les données de comptes JavaScript
    try {
        if (typeof accounts !== 'undefined' && accounts.length > 0) {
            // Trouver le compte par défaut ou le premier
            const defaultAccount = accounts.find(acc => acc.is_default) || accounts[0];
            if (defaultAccount && defaultAccount.email) {
                console.log('Email expéditeur depuis compte par défaut:', defaultAccount.email);
                return defaultAccount.email;
            }
        }
    } catch (e) {
        console.log('Impossible de récupérer depuis accounts:', e.message);
    }
    
    // 4. Fallback CONFIG (dernière option)
    if (typeof CONFIG !== 'undefined' && CONFIG.USER_EMAIL) {
        console.log('Email expéditeur fallback depuis CONFIG:', CONFIG.USER_EMAIL);
        return CONFIG.USER_EMAIL;
    }
    
    // 5. Dernier fallback
    console.warn('Aucun email expéditeur trouvé, utilisation fallback');
    return 'user@localhost';
}

function toggleAutoClassification() {
    autoClassificationEnabled = !autoClassificationEnabled;
    
    const message = autoClassificationEnabled ? 
        '✅ Classement automatique des mails envoyés activé' : 
        '⚠️ Classement automatique des mails envoyés désactivé';
    
    showNotification(message, autoClassificationEnabled ? 'success' : 'warning');
    
    // Mettre à jour l'interface
    updateAutoClassificationUI();
    
    // Sauvegarder la préférence
    try {
       
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('roundcube_auto_classification', autoClassificationEnabled ? '1' : '0');
        }
    } catch (e) {
        console.log('Impossible de sauvegarder la préférence');
    }
    
    return autoClassificationEnabled;
}
window.addEventListener('message', function(event) {
    if (event.data.type === 'roundcube_maildata_complete') {
        if (currentMailData && currentMailData.uid === event.data.uid) {
            currentMailData.attachments = event.data.mailData.attachments;
            console.log('✅ PJ reçues dans currentMailData:', currentMailData.attachments.length);
        }
    }
});
/**
 * NOUVELLE FONCTION : Mettre à jour l'interface
 */
function updateAutoClassificationUI() {
    // Mettre à jour le toggle
    const toggle = document.getElementById('auto-classification-toggle');
    if (toggle) {
        toggle.checked = autoClassificationEnabled;
    }
    
    // Mettre à jour le slider
    const slider = document.querySelector('.auto-classification-control .slider');
    if (slider) {
        slider.style.backgroundColor = autoClassificationEnabled ? '#28a745' : '#ccc';
        const knob = slider.querySelector('span');
        if (knob) {
            knob.style.left = autoClassificationEnabled ? '29px' : '3px';
        }
    }
    
    // Mettre à jour l'indicateur de statut
    const statusDiv = document.getElementById('auto-classification-status');
    if (statusDiv) {
        statusDiv.style.display = autoClassificationEnabled ? 'block' : 'none';
    }
}

/**
 * NOUVELLE FONCTION : Charger les préférences
 */
function loadAutoClassificationPreference() {
    try {
        if (typeof localStorage !== 'undefined') {
            const saved = localStorage.getItem('roundcube_auto_classification');
            if (saved !== null) {
                autoClassificationEnabled = (saved === '1');
            }
        }
    } catch (e) {
        console.log('Impossible de charger les préférences');
    }
}

function showExistingMailForm(mailData, existingData) {
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (!container || !noSelection) {
        console.error('❌ Conteneurs de classement non trouvés');
        return;
    }
    
    console.log('📋 Affichage mail existant:', mailData.uid);
    
    existingMailData = existingData;
    isEditMode = false;
    
    noSelection.style.display = 'none';
    container.style.display = 'block';
    container.innerHTML = generateExistingMailFormHTML(mailData, existingData);
    
    isFormDisplayed = true;
}

/**
 * NOUVELLE FONCTION : Générer HTML pour mail existant
 */
function generateExistingMailFormHTML(mailData, existingData) {
    const links = existingData.links || [];
    
    let linksHtml = '';
    if (links.length > 0) {
        linksHtml = '<div class="existing-links-container" style="background: rgba(40,167,69,0.2); padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
        linksHtml += '<h5 style="color: #28a745; margin-bottom: 10px;">📁 Modules liés actuels :</h5>';
        
        links.forEach(link => {
            const typeLabels = {
                'societe': '🏢 Tiers',
                'contact': '👤 Contact', 
                'projet': '📋 Projet',
                'user': '👤 Utilisateur',
                'propal': '📑 Proposition commerciale',
                'commande': '🛒 Commande client',
                'invoice': '💳 Facture client',
                'expedition': '📦 Expédition',
                'contract': '📜 Contrat',
                'fichinter': '🛠️ Intervention',
                'ticket': '🎫 Ticket',
                'supplier_order': '🛍️ Commande fournisseur',
                'supplier_proposal': '🤝 Proposition fournisseur',
                'supplier_invoice': '🧾 Facture fournisseur',
                'reception': '🚚 Réception',
                'salary': '💰 Salaire',
                'loan': '🏦 Emprunt',
                'don': '🎁 Don',
                'holiday': '🌴 Congé',
                'expensereport': '🧾✈️ Note de frais',
                'usergroup': '👥 Groupe',
                'adherent': '🪪 Adhérent',
                'event': '📅 Événement',
                'accounting': '📊 Comptabilité',
                'affaire': '📂 Affaire'
            };
            
            const typeLabel = typeLabels[link.target_type] || link.target_type;
            linksHtml += `
                <div class="existing-link-item" style="padding: 8px; margin: 5px 0; background: rgba(255,255,255,0.1); border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">
                    <span><strong>${typeLabel}:</strong> ${link.target_name}</span>
                    <small style="color: #999;">ID: ${link.target_id}</small>
                </div>
            `;
        });
        
        linksHtml += '</div>';
    }
    
    return `
        
            ${linksHtml}
            
            <div class="classification-actions" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="enterEditMode()" style="background: #007bff;">
                    ✏️ Modifier le classement
                </button>
                <button class="btn" onclick="viewMailDetails()" style="background: #17a2b8;">
                    👁️ Voir les détails
                </button>
                <button class="btn" onclick="resetClassificationForm()" style="background: #6c757d;">
                    🔄 Actualiser
                </button>
            </div>
            
            <div id="classification-status" style="margin-top: 15px; display: none;">
                <!-- Zone pour afficher le statut -->
            </div>
        </div>
    `;
}

/**
 * NOUVELLE FONCTION : Entrer en mode édition
 */
function enterEditMode() {
    console.log('✏️ Passage en mode édition');
    
    isEditMode = true;
    
    const container = document.getElementById('classification-form');
    if (container && existingMailData) {
        container.innerHTML = generateEditModeFormHTML(currentMailData, existingMailData);
        
        // Charger les modules existants comme sélectionnés
        loadExistingLinksAsSelected(existingMailData.links || []);
        
        // Réinitialiser les événements
        setTimeout(() => {
            initSearchEvents();
        }, 100);
    }
}


function generateEditModeFormHTML(mailData, existingData) {
    return `
        <div class="classification-form-container">
            <!-- 1. BOUTONS D'ACTION EN PREMIER -->
            <div class="classification-actions" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(236, 240, 241, 0.2);">
                <h4 style="color: white; margin-bottom: 15px;">✏️ Actions d'édition :</h4>
                <button class="btn btn-primary" onclick="saveModifiedClassification()" style="background: #28a745;">
                    💾 Enregistrer les modifications
                </button>
                <button class="btn" onclick="cancelEditMode()" style="background: #6c757d;">
                    ❌ Annuler
                </button>
                <button class="btn" onclick="clearAllSelections()" style="background: #dc3545;">
                    🔄 Réinitialiser sélections
                </button>
            </div>
        
            
            ${generateAllClassificationFields()}
            
            
            
            <div id="classification-status" style="margin-top: 15px; display: none;">
                <!-- Zone pour afficher le statut -->
            </div>
        </div>
    `;
}
/**
 * NOUVELLE FONCTION : Réutiliser votre HTML existant
 */
function generateAllClassificationFields() {
    let classificationFields = '';
    
    console.log('Génération des champs, activeModules:', activeModules);
    
    // Vérifier que activeModules est défini et non vide
    if (!activeModules || activeModules.length === 0) {
        console.error('❌ activeModules vide ou non défini');
        return '<p style="color: red;">Erreur: Aucun module actif trouvé</p>';
    }
    
    // Générer les champs directement
    activeModules.forEach(module => {
        const fieldId = module.value === 'thirdparty' ? 'societe' : 
                       module.value === 'project' ? 'projet' : 
                       module.value;
        
        const emoji = getModuleEmoji(module.value);
        
        classificationFields += `
            <div class="classification-field">
                <label>${emoji} ${module.label}:</label>
                <input type="text" 
                       id="search-${fieldId}" 
                       placeholder="Tapez pour rechercher ${module.label.toLowerCase()}..." 
                       autocomplete="off">
                <div id="suggestions-${fieldId}" class="suggestions-container"></div>
                <div id="selected-${fieldId}" class="selected-entity" style="display:none;"></div>
            </div>
        `;
    });
    
    console.log('Champs générés:', classificationFields.length > 0 ? 'OK' : 'VIDE');
    return classificationFields;
}

/**
 * NOUVELLE FONCTION : Charger les liens existants comme sélectionnés
 */
function loadExistingLinksAsSelected(links) {
    // Reset des sélections
    Object.keys(selectedEntities).forEach(key => {
        selectedEntities[key] = null;
    });
    
    // Charger chaque lien
    links.forEach(link => {
        const entity = {
            id: link.target_id,
            label: link.target_name,
            name: link.target_name
        };
        
        let entityType = link.target_type;
        if (entityType === 'contrat') entityType = 'contract';
        
        selectedEntities[entityType] = entity;
        
        // Mettre à jour l'interface
        const input = document.getElementById(`search-${entityType}`);
        const selectedDiv = document.getElementById(`selected-${entityType}`);
        
        if (input) {
            input.value = entity.label || entity.name;
            input.classList.add('field-selected');
            input.style.background = 'rgba(40, 167, 69, 0.2)';
            input.disabled = true;
        }
        
        if (selectedDiv) {
            selectedDiv.innerHTML = `
                <span style="color: #28a745;">✅ ${entity.label || entity.name} <small>(existant)</small></span>
                <button onclick="clearSelection('${entityType}')" style="margin-left: 10px; padding: 2px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">✖</button>
            `;
            selectedDiv.style.display = 'block';
        }
    });
}


async function saveModifiedClassification() {
    console.log('💾 Sauvegarde des modifications...');
    
    if (!currentMailData || !existingMailData) {
        showNotification('❌ Erreur: données manquantes', 'error');
        return;
    }
    
    updateClassificationStatus('Sauvegarde des modifications...', 'loading');
    
    try {
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || '',
            subject: currentMailData.subject || 'Sans sujet',
            from_email: currentMailData.from_email || '',
            raw_email: currentMailData.raw_email || 'Contenu',
            date: parseMailDateForSave(currentMailData.date),
            attachments: currentMailData.attachments || [],
            links: [],
            action: 'sync_links' // Synchroniser (remplacer tous les liens)
        };
        
        // Ajouter tous les liens sélectionnés
        Object.keys(selectedEntities).forEach(type => {
            if (selectedEntities[type]) {
                saveData.links.push({
                    type: type === 'contract' ? 'contrat' : type,
                    id: parseInt(selectedEntities[type].id),
                    name: selectedEntities[type].label || selectedEntities[type].name || ''
                });
            }
        });
        
        // VÉRIFICATION IMPORTANTE : Avertir si aucun lien
        if (saveData.links.length === 0) {
            if (!confirm('⚠️ ATTENTION: Aucun module sélectionné !\n\nCela va supprimer complètement le mail de la base de données.\n\nÊtes-vous sûr de vouloir continuer ?')) {
                updateClassificationStatus('Annulé par l\'utilisateur', 'warning');
                return;
            }
        }
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        const result = await response.json();
        
        // Gérer le cas DELETED
        if (result.status === 'DELETED') {
            updateClassificationStatus('Mail supprimé de la base de données', 'success');
            showNotification('Mail supprimé (aucun lien restant)', 'info');
            
            // Réinitialiser les états pour revenir au formulaire de classement
            existingMailData = null;
            isEditMode = false;
            
            // Réafficher le formulaire de classement normal après un court délai
            setTimeout(() => {
                clearAllSelections();
                // Réafficher le formulaire de classement normal
                showClassificationForm(currentMailData);
                // Réinitialiser les événements
                setTimeout(() => {
                    initSearchEvents();
                }, 100);
            }, 1500);
            
        } else if (result.status === 'UPDATED' || result.status === 'OK') {
            updateClassificationStatus('✅ Modifications sauvegardées!', 'success');
            showNotification('✅ Classement modifié avec succès!', 'success');
            
            // Sortir du mode édition et rafraîchir
            setTimeout(() => {
                clearAllSelections();
                isEditMode = false;
                // Recharger les données mises à jour
                handleRoundcubeMessage({ data: { type: 'roundcube_mail_complete', data: currentMailData } });
            }, 2000);
            
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur sauvegarde:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification(`❌ Erreur: ${error.message}`, 'error');
    }
}
function cancelEditMode() {
    console.log('❌ Annulation du mode édition');
    
    isEditMode = false;
    
    // AJOUT : Nettoyer toutes les sélections chargées en mode édition
    Object.keys(selectedEntities).forEach(key => {
        selectedEntities[key] = null;
    });
    
    console.log('🧹 Sélections nettoyées après annulation');
    
    if (existingMailData && currentMailData) {
        showExistingMailForm(currentMailData, existingMailData);
    }
}

/**
 * NOUVELLE FONCTION : Voir les détails du mail
 */
function viewMailDetails() {
    if (existingMailData) {
        alert(`Détails du mail:
        
Mail ID: ${existingMailData.mail_id}
Sujet: ${currentMailData.subject}
De: ${currentMailData.from_email}
Date d'enregistrement: ${existingMailData.date_created || 'N/A'}
Nombre de modules liés: ${existingMailData.links ? existingMailData.links.length : 0}
        `);
    }
}

// 4. MODIFIER votre fonction resetClassificationForm existante
// REMPLACER cette partie dans votre fonction existante :
function resetClassificationForm() {
    console.log('🔄 Réinitialisation complète du formulaire...');
    
    selectedEntities = Object.fromEntries(Object.keys(selectedEntities).map(key => [key, null]));
    currentMailData = null;
    currentMailUID = null;
    currentMailId = null;
    lastProcessedMailUID = null;
    isFormDisplayed = false;
    
    // NOUVEAU: Reset des variables d'édition
    isEditMode = false;
    existingMailData = null;
    
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
    
    if (noSelection) {
        noSelection.style.display = 'block';
    }
}


function formatMailDate(dateString) {
    if (!dateString) return 'Date non disponible';
    
    try {
        let dateObj = null;
        
        console.log('📅 Debug formatMailDate - Input brut:', JSON.stringify(dateString));
        
        // CORRECTION DÉFINITIVE : Nettoyer TOUS les caractères problématiques
        const cleanedString = String(dateString)
            .replace(/^["""''`´–—\-\s]+|["""''`´–—\-\s]+$/g, '') // Supprimer début et fin
            .replace(/["""''`´–—]/g, '') // Supprimer TOUS les guillemets et tirets
            .replace(/\s+/g, ' ') // Normaliser les espaces
            .toLowerCase()
            .trim();
        
        console.log('📅 Debug - String nettoyée:', JSON.stringify(cleanedString));
        
        // Vérifier si c'est "aujourd'hui" avec différentes variantes
        const isToday = cleanedString.includes('aujourdhui') || 
                       cleanedString.includes('today') ||
                       cleanedString.includes('auj') ||
                       cleanedString.match(/^(aujourd\s*hui|today)/i);
        
        if (isToday) {
            console.log('📅 ✅ Détection "Aujourd\'hui" confirmée !');
            
            // Extraire l'heure - chercher dans la chaîne nettoyée
            const timeMatch = cleanedString.match(/(\d{1,2}:\d{2})/);
            if (timeMatch) {
                const timeStr = timeMatch[1];
                const [hours, minutes] = timeStr.split(':').map(Number);
                
                console.log('📅 Heure extraite:', timeStr, 'Hours:', hours, 'Minutes:', minutes);
                
                // CORRECTION: Créer la date d'aujourd'hui sans manipulation de timezone
                dateObj = new Date();
                
                // IMPORTANT: Utiliser les méthodes qui respectent le timezone local
                const year = dateObj.getFullYear();
                const month = dateObj.getMonth();
                const day = dateObj.getDate();
                
                // Recréer la date avec l'heure spécifiée en local
                dateObj = new Date(year, month, day, hours, minutes, 0, 0);
                
                console.log('📅 Date "Aujourd\'hui" créée (locale):', dateObj.toString());
                console.log('📅 Date "Aujourd\'hui" ISO:', dateObj.toISOString());
            } else {
                console.log('📅 Pas d\'heure trouvée, utilisation heure actuelle');
                dateObj = new Date();
            }
        }
        // Gérer "hier"
        else if (cleanedString.includes('hier') || 
                 cleanedString.includes('yesterday')) {
            
            console.log('📅 Détection format "Hier"');
            
            const timeMatch = cleanedString.match(/(\d{1,2}:\d{2})/);
            dateObj = new Date();
            
            // Utiliser les méthodes locales
            const year = dateObj.getFullYear();
            const month = dateObj.getMonth();
            const day = dateObj.getDate() - 1; // Hier
            
            if (timeMatch) {
                const [hours, minutes] = timeMatch[1].split(':').map(Number);
                dateObj = new Date(year, month, day, hours, minutes, 0, 0);
            } else {
                dateObj = new Date(year, month, day, 12, 0, 0, 0);
            }
        }
        // Si c'est déjà un timestamp ISO valide
        else if (dateString.includes('T') && dateString.includes('Z')) {
            dateObj = new Date(dateString);
        }
        // Si c'est un timestamp numérique
        else if (!isNaN(dateString) && String(dateString).length > 10) {
            dateObj = new Date(parseInt(dateString));
        }
        // Format RFC 2822
        else if (dateString.match(/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}/i)) {
            dateObj = new Date(dateString);
        }
        // Format standard
        else if (dateString.match(/\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}/i)) {
            dateObj = new Date(dateString);
        }
        // Format court jour + heure
        else if (dateString.match(/^(Lun|Mar|Mer|Jeu|Ven|Sam|Dim)\s\d{1,2}:\d{2}$/i)) {
            const timeMatch = dateString.match(/(\d{1,2}):(\d{2})/);
            const dayMatch = dateString.match(/^(Lun|Mar|Mer|Jeu|Ven|Sam|Dim)/i);
            
            if (timeMatch && dayMatch) {
                const dayNames = {
                    'Lun': 1, 'Mar': 2, 'Mer': 3, 'Jeu': 4, 
                    'Ven': 5, 'Sam': 6, 'Dim': 0
                };
                
                const targetDay = dayNames[dayMatch[1]];
                const hours = parseInt(timeMatch[1]);
                const minutes = parseInt(timeMatch[2]);
                
                const today = new Date();
                const currentDay = today.getDay();
                
                let daysOffset = targetDay - currentDay;
                if (daysOffset > 0) {
                    daysOffset -= 7;
                }
                
                const year = today.getFullYear();
                const month = today.getMonth();
                const day = today.getDate() + daysOffset;
                
                dateObj = new Date(year, month, day, hours, minutes, 0, 0);
            }
        }
        // Essayer de parser directement avec la chaîne nettoyée
        else {
            console.log('📅 Tentative parsing direct avec chaîne nettoyée');
            dateObj = new Date(cleanedString);
            
            // Si ça échoue, essayer avec la chaîne originale
            if (isNaN(dateObj.getTime())) {
                dateObj = new Date(dateString);
            }
        }
        
        // Vérifier si la date est valide
        if (dateObj && !isNaN(dateObj.getTime())) {
            // CORRECTION: Utiliser les méthodes locales pour éviter le décalage UTC
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            const hours = String(dateObj.getHours()).padStart(2, '0');
            const minutes = String(dateObj.getMinutes()).padStart(2, '0');
            
            const formattedDate = `${year}-${month}-${day} ${hours}:${minutes}`;
            console.log('📅 ✅ Date formatée avec succès:', dateString, '->', formattedDate);
            return formattedDate;
        }
        
        // Si impossible à parser
        console.warn('📅 ❌ Date non parsable après tous les essais:', dateString);
        return cleanedString || dateString;
        
    } catch (error) {
        console.error('📅 ❌ Erreur parsing date:', error, 'pour:', dateString);
        return dateString || 'Date invalide';
    }
}
function parseMailDateForSave(dateString) {
    console.log('🔧 parseMailDateForSave - Input:', JSON.stringify(dateString));
    
    if (!dateString) {
        return Math.floor(Date.now() / 1000);
    }
    
    // Si c'est déjà un timestamp numérique, le retourner
    if (typeof dateString === 'number') {
        return dateString;
    }
    
    try {
        // Si la date est déjà au format YYYY-MM-DD HH:MM, la parser directement
        if (dateString.match(/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/)) {
            const dateObj = new Date(dateString.replace(' ', 'T') + ':00');
            if (!isNaN(dateObj.getTime())) {
                const timestamp = Math.floor(dateObj.getTime() / 1000);
                console.log('🔧 Timestamp depuis format standard:', timestamp);
                return timestamp;
            }
        }
        
        // Sinon, utiliser formatMailDate pour parser
        const formattedDate = formatMailDate(dateString);
        console.log('🔧 Date formatée par formatMailDate:', formattedDate);
        
        // Créer un objet Date depuis la date formatée
        const dateObj = new Date(formattedDate.replace(' ', 'T') + ':00');
        
        if (!isNaN(dateObj.getTime())) {
            const timestamp = Math.floor(dateObj.getTime() / 1000);
            console.log('🔧 Timestamp final:', timestamp, '(', new Date(timestamp * 1000).toISOString(), ')');
            return timestamp;
        }
        
        console.log('🔧 Erreur conversion en Date object');
        return Math.floor(Date.now() / 1000);
        
    } catch (error) {
        console.error('🔧 Erreur parseMailDateForSave:', error);
        return Math.floor(Date.now() / 1000);
    }
}
function updateMailInfo(mailData) {
    // Utiliser une variable pour tracker l'UID précédent
    const previousUID = currentMailUID;
    
    // Si on était en mode mail existant et qu'on change de mail
    if (existingMailData && previousUID && previousUID !== mailData.uid) {
        console.log('🔄 Passage d\'un mail existant à un nouveau mail, réinitialisation forcée');
        existingMailData = null;
        isEditMode = false;
        isFormDisplayed = false;
        
        // Forcer l'affichage du nouveau formulaire
        showClassificationForm(mailData);
        return;
    }
    
    // Si le formulaire n'est pas encore affiché OU si on force la réinitialisation
    if (!isFormDisplayed) {
        showClassificationForm(mailData);
        return;
    }
    
    // Sinon, on met à jour UNIQUEMENT la zone d'info du mail
    const mailInfoContainer = document.querySelector('.mail-data-container');
    if (mailInfoContainer) {
        console.log('📋 Mise à jour des infos du mail uniquement');
        mailInfoContainer.innerHTML = `
            <p style="margin: 5px 0;"><strong>Sujet:</strong> ${mailData.subject || 'N/A'}</p>
            <p style="margin: 5px 0;"><strong>De:</strong> ${mailData.from || mailData.from_email || 'N/A'}</p>
            <p style="margin: 5px 0;"><strong>UID:</strong> ${mailData.uid || 'N/A'}</p>
            <p style="margin: 5px 0;"><strong>Date:</strong> ${formatMailDate(mailData.date)}</p>
        `;
    }
}
function cleanupMailStates() {
    isEditMode = false;
    existingMailData = null;
    console.log('🧹 États nettoyés pour nouveau mail');
}
/**
 * Afficher le formulaire de classement UNIQUEMENT la première fois
 */

function showClassificationForm(mailData) {
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (!container || !noSelection) {
        console.error('❌ Conteneurs de classement non trouvés');
        return;
    }
    
    console.log('📋 Affichage initial du formulaire pour le mail:', mailData.uid);
    
    noSelection.style.display = 'none';
    container.style.display = 'block';
    container.innerHTML = generateClassificationFormHTML(mailData);
    
    isFormDisplayed = true;
    
    // Restaurer les sélections si elles existent
    restoreSelections();
    if (preselectData) {
        setTimeout(() => {
            applyPreselection(preselectData);
            preselectData = null; // Utiliser une seule fois
        }, 500);
    }
}


function generateClassificationFormHTML(mailData) {
    let classificationFields = '';
    
    // Générer les champs uniquement pour les modules actifs
    activeModules.forEach(module => {
        const fieldId = module.value === 'thirdparty' ? 'societe' : 
                       module.value === 'project' ? 'projet' : 
                       module.value;
        
        const emoji = getModuleEmoji(module.value);
        
        classificationFields += `
            <div class="classification-field">
                <label>${emoji} ${module.label}:</label>
                <input type="text" 
                       id="search-${fieldId}" 
                       placeholder="Tapez pour rechercher ${module.label.toLowerCase()}..." 
                       autocomplete="off">
                <div id="suggestions-${fieldId}" class="suggestions-container"></div>
                <div id="selected-${fieldId}" class="selected-entity" style="display:none;"></div>
            </div>
        `;
    });
    
    return `
        <div class="classification-form-container">
            <!-- 1. BOUTONS D'ACTION EN PREMIER -->
            <div class="classification-actions" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(236, 240, 241, 0.2);">
                <h4 style="color: white; margin-bottom: 15px;">🎯 Actions rapides :</h4>
                <button class="btn btn-primary" onclick="classifyAndSaveMail()" style="background: #28a745;">
                    📁 Classer ce mail 
                </button>
                <button class="btn" onclick="saveMailWithoutLinks()" style="background: #6c757d;">
                    💾 Sauvegarder sans lien
                </button>
                <button class="btn" onclick="clearAllSelections()" style="background: #dc3545;">
                    🔄 Réinitialiser
                </button>
            </div>
            
            
            <!-- 2. CHAMPS DE CLASSEMENT -->
            <h5 style="color: white; margin-bottom: 15px;">📁 Classement :</h5>
            ${classificationFields}
            
            <!-- Zone de statut (reste à la fin) -->
            <div id="classification-status" style="margin-top: 15px; display: none;">
                <!-- Zone pour afficher le statut de sauvegarde -->
            </div>
        </div>
    `;
}
/**
 * Obtenir l'emoji approprié pour chaque module
 */
function getModuleEmoji(moduleValue) {
    const emojiMap = {
        'thirdparty': '🏢',
        'contact': '👤',
        'project': '📋',
        'user': '👤',
        'usergroup': '👥',
        'propal': '📑',
        'commande': '🛒',
        'expedition': '📦',
        'contract': '📜',
        'fichinter': '🛠️',
        'ticket': '🎫',
        'supplier_order': '🛍️',
        'supplier_proposal': '🤝',
        'supplier_invoice': '🧾',
        'reception': '🚚',
        'invoice': '💳',
        'salary': '💰',
        'loan': '🏦',
        'don': '🎁',
        'holiday': '🌴',
        'expensereport': '🧾✈️',
        'adherent': '🪪',
        'event': '📅',
        'accounting': '📊',
        'affaire': '📂'
    };
    
    return emojiMap[moduleValue] || '📄';
}

/**
 * Initialiser les événements de recherche APRÈS création du formulaire
 */
/**
 * Initialiser les événements de recherche APRÈS création du formulaire
 */
function initSearchEvents() {
    activeModules.forEach(module => {
        const fieldId = module.value === 'thirdparty' ? 'societe' : 
                       module.value === 'project' ? 'projet' : 
                       module.value;
        
        const input = document.getElementById(`search-${fieldId}`);
        if (input) {
            // Retirer l'ancien event handler s'il existe
            input.onkeyup = null;
            
            // Ajouter le nouveau avec debounce
            input.addEventListener('keyup', function(e) {
                const value = e.target.value;
                handleSearchInput(fieldId, value);
            });
            
            // Empêcher la soumission du formulaire sur Enter
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }
    });
}
/**
 * Initialiser l'objet selectedEntities avec les modules actifs
 */
function initSelectedEntities() {
    selectedEntities = {};
    
    activeModules.forEach(module => {
        const fieldId = module.value === 'thirdparty' ? 'societe' : 
                       module.value === 'project' ? 'projet' : 
                       module.value;
        selectedEntities[fieldId] = null;
    });
    
    console.log('📋 selectedEntities initialisé:', Object.keys(selectedEntities));
}
/**
 * Gérer la recherche avec debounce
 */
function handleSearchInput(type, query) {
    // Annuler la recherche précédente
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Si la recherche est trop courte
    if (query.length < 2) {
        hideSearchResults(type);
        return;
    }
    
    // Afficher "Recherche..."
    const suggestionsContainer = document.getElementById(`suggestions-${type}`);
    if (suggestionsContainer) {
        suggestionsContainer.innerHTML = '<div class="suggestion-item">Recherche...</div>';
        suggestionsContainer.style.display = 'block';
    }
    
    // Lancer la recherche après 500ms
    searchTimeout = setTimeout(() => {
        performSearch(type, query);
    }, 500);
}

/**
 * Effectuer la recherche
 */
function performSearch(type, query) {
    const typeMap = { 
    'societe': 'thirdparty', 
    'contact': 'contact', 
    'projet': 'projet',
    'user': 'user',
    'usergroup': 'usergroup',
    'propal': 'propal',
    'commande': 'commande',
    'expedition': 'expedition',
    'contract': 'contract',
    'fichinter': 'fichinter',
    'ticket': 'ticket',
    'supplier_order': 'supplier_order',
    'supplier_proposal': 'supplier_proposal',
    'supplier_invoice': 'supplier_invoice',
    'reception': 'reception',
    'invoice': 'invoice',
    'salary': 'salary',
    'loan': 'loan',
    'don': 'don',
    'holiday': 'holiday',
    'expensereport': 'expensereport',
    'adherent': 'adherent',
    'event': 'event',
    'accounting': 'accounting',
    'affaire': 'affaire'
};

    
    const apiType = typeMap[type] || type;
    const url = `${CONFIG.API_URL}?action=search_entities&type=${apiType}&query=${encodeURIComponent(query)}`;
    
    console.log('🔎 Recherche', type, ':', query);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results) {
                showSearchResults(type, data.results);
            } else {
                showSearchResults(type, []);
            }
        })
        .catch(error => {
            console.error('❌ Erreur recherche', type, ':', error);
            showSearchResults(type, []);
        });
}

// Rendre la fonction globale pour compatibilité
window.searchEntity = function(type, query) {
    handleSearchInput(type, query);
};

/**
 * Afficher les résultats de recherche
 */
function showSearchResults(type, results) {
    const container = document.getElementById(`suggestions-${type}`);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = '<div class="suggestion-item" style="font-style: italic; color: #999;">Aucun résultat trouvé</div>';
    } else {
        results.forEach(result => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.1);';
            item.innerHTML = `
                <strong>${result.label || result.name}</strong>
                <small style="display: block; color: #999;">ID: ${result.id}</small>
            `;
            
            item.onclick = function() {
                selectEntity(type, result);
            };
            
            item.onmouseenter = function() {
                this.style.background = 'rgba(255,255,255,0.1)';
            };
            
            item.onmouseleave = function() {
                this.style.background = 'transparent';
            };
            
            container.appendChild(item);
        });
    }
    
    container.style.display = 'block';
}

/**
 * Sélectionner une entité
 */
function selectEntity(type, entity) {
    const input = document.getElementById(`search-${type}`);
    const selectedDiv = document.getElementById(`selected-${type}`);
    
    if (input) {
        input.value = entity.label || entity.name;
        input.classList.add('field-selected');
        input.style.background = 'rgba(40, 167, 69, 0.2)';
        input.disabled = true; // Désactiver le champ une fois sélectionné
    }
    
    if (selectedDiv) {
        selectedDiv.innerHTML = `
            <span style="color: #28a745;">✅ ${entity.label || entity.name}</span>
            <button onclick="clearSelection('${type}')" style="margin-left: 10px; padding: 2px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">✖</button>
        `;
        selectedDiv.style.display = 'block';
    }
    
    selectedEntities[type] = entity;
    hideSearchResults(type);
    
    console.log(`✅ ${type} sélectionné:`, entity);
    showNotification(`✅ ${type} sélectionné: ${entity.label || entity.name}`, 'success');
}

/**
 * Effacer une sélection spécifique
 */
function clearSelection(type) {
    const input = document.getElementById(`search-${type}`);
    const selectedDiv = document.getElementById(`selected-${type}`);
    
    if (input) {
        input.value = '';
        input.classList.remove('field-selected');
        input.style.background = 'transparent';
        input.disabled = false; // Réactiver le champ
    }
    
    if (selectedDiv) {
        selectedDiv.style.display = 'none';
    }
    
    selectedEntities[type] = null;
    console.log(`❌ Sélection ${type} effacée`);
}

/**
 * Effacer toutes les sélections
 */
/**
 * Effacer toutes les sélections (version dynamique)
 */
function clearAllSelections() {
    activeModules.forEach(module => {
        const fieldId = module.value === 'thirdparty' ? 'societe' : 
                       module.value === 'project' ? 'projet' : 
                       module.value;
        clearSelection(fieldId);
    });
    showNotification('🔄 Toutes les sélections effacées', 'info');
}
async function reloadActiveModules() {
    console.log('🔄 Rechargement des modules actifs...');
    await loadActiveModules();
    initSelectedEntities();
    
    // Si un formulaire est affiché, le régénérer
    if (isFormDisplayed && currentMailData) {
        showClassificationForm(currentMailData);
    }
    
    showNotification(`📦 ${activeModules.length} modules actifs rechargés`, 'success');
}
/**
 * Restaurer les sélections après mise à jour du formulaire
 */
function restoreSelections() {
    Object.keys(selectedEntities).forEach(type => {
        if (selectedEntities[type]) {
            const entity = selectedEntities[type];
            const input = document.getElementById(`search-${type}`);
            const selectedDiv = document.getElementById(`selected-${type}`);
            
            if (input) {
                input.value = entity.label || entity.name;
                input.classList.add('field-selected');
                input.style.background = 'rgba(40, 167, 69, 0.2)';
                input.disabled = true;
            }
            
            if (selectedDiv) {
                selectedDiv.innerHTML = `
                    <span style="color: #28a745;">✅ ${entity.label || entity.name}</span>
                    <button onclick="clearSelection('${type}')" style="margin-left: 10px; padding: 2px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">✖</button>
                `;
                selectedDiv.style.display = 'block';
            }
        }
    });
    
    // Réinitialiser les événements
    initSearchEvents();
}

/**
 * Masquer les résultats de recherche
 */
function hideSearchResults(type) {
    const container = document.getElementById(`suggestions-${type}`);
    if (container) {
        container.style.display = 'none';
    }
}
// NOUVELLES FONCTIONS pour le mode composition
function showClassificationFormForComposition() {
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (!container || !noSelection) {
        console.error('Conteneurs de classement non trouvés');
        return;
    }
    
    console.log('Affichage formulaire pour composition');
    
    // Utiliser votre fonction existante avec des données factices
    const fakeMail = {
        subject: "Mail en cours de rédaction",
        from: "Mode composition",
        uid: "composition",
        date: new Date().toISOString()
    };
    
    noSelection.style.display = 'none';
    container.style.display = 'block';
    container.innerHTML = generateClassificationFormHTML(fakeMail);
    
    // Modifier le titre et les boutons
    setTimeout(() => {
        const title = container.querySelector('h4');
        if (title) {
            title.innerHTML = '📤 Préparer le classement du mail à envoyer :';
        }
        
        const mailContainer = container.querySelector('.mail-data-container');
        if (mailContainer) {
            mailContainer.innerHTML = `
                <div style="background: rgba(0,123,255,0.1); padding: 10px; border-radius: 5px;">
                    <p style="margin: 5px 0; color: orange;">
                        💡 Sélectionnez les modules maintenant que tu veux rattacher au mail.
                    </p>
                </div>
            `;
        }
        
        const actionsDiv = container.querySelector('.classification-actions');
        if (actionsDiv) {
            actionsDiv.innerHTML = `
                <button class="btn" onclick="clearAllSelections()" style="background: #dc3545;">
                    🔄 Réinitialiser
                </button>
                <button class="btn" onclick="hideCompositionForm()" style="background: #6c757d;">
                    ❌ Masquer
                </button>
                <div style="background: rgba(40,167,69,0.1); padding: 10px; border-radius: 5px; margin-top: 15px;">
                    <small style="color: #28a745;">
                        ✅ Le classement sera appliqué automatiquement à l'envoi
                    </small>
                </div>
            `;
        }
    }, 100);
    
    isFormDisplayed = true;
    compositionMode = true;
    
    // Appliquer la présélection
    if (preselectData) {
        setTimeout(() => {
            applyPreselection(preselectData);
            preselectData = null;
        }, 500);
    }
}

function hideCompositionForm() {
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (container) container.style.display = 'none';
    if (noSelection) noSelection.style.display = 'block';
    
    isFormDisplayed = false;
    compositionMode = false;
}

function detectCompositionMode() {
    try {
        const parentUrl = window.parent.location.href;
        if (parentUrl.includes('_action=compose')) {
            console.log('Mode composition détecté, affichage du formulaire');
            setTimeout(() => {
                showClassificationFormForComposition();
                initSearchEvents();
            }, 2000);
        }
    } catch (e) {
        console.log('Impossible de détecter le mode composition');
    }
}
/**
 * FONCTION CORRIGÉE : Gérer les mails envoyés
 */
async function handleMailBeingSent(mailData) {
    
    console.log('📤 handleMailBeingSent appelé avec:', {
        subject: mailData.subject,
        to: mailData.to,
        attachmentsCount: mailData.attachments ? mailData.attachments.length : 0
    });

    // RÉCUPÉRER LES PIÈCES JOINTES DU PLUGIN PHP
    console.log('🔄 Récupération des pièces jointes depuis le plugin PHP...');
    
    try {
        const baseUrl = CONFIG.SAVE_URL ? CONFIG.SAVE_URL.replace('/save_mails.php', '') : '/custom/roundcubemodule/scripts';
        const attachmentsUrl = `${baseUrl}/save_attachments_only.php`
        ;
        
        console.log('🔗 URL des pièces jointes:', attachmentsUrl);

        // Attendre que Roundcube ait envoyé les PJ
        await new Promise(resolve => setTimeout(resolve, 1500));

        // Récupérer les pièces jointes
        let attachData = null;
        let attempts = 0;
        const maxAttempts = 3;

        while (attempts < maxAttempts) {
            attempts++;
            console.log(`🔄 Tentative ${attempts}/${maxAttempts} de récupération des PJ...`);
            
            try {
                const attachResponse = await fetch(attachmentsUrl, { 
                    method: 'GET',
                    headers: { 'Cache-Control': 'no-cache' }
                });

                if (!attachResponse.ok) throw new Error(`HTTP error! status: ${attachResponse.status}`);

                const responseText = await attachResponse.text();
                attachData = JSON.parse(responseText);
                
                console.log(`📎 Réponse PJ (tentative ${attempts}):`, attachData);
                
                if (attachData.attachments && attachData.attachments.length > 0) {
                    console.log('✅ Pièces jointes récupérées:', attachData.attachments.length);
                    break;
                }
                
            } catch (error) {
                console.error(`❌ Erreur tentative ${attempts}:`, error);
            }
            
            if (attempts < maxAttempts) await new Promise(resolve => setTimeout(resolve, 1000));
        }

        // FORMATER LES PIÈCES JOINTES POUR DOLIBARR
        if (attachData && attachData.attachments && attachData.attachments.length > 0) {
            // Créer les attachments au format attendu par Dolibarr
            mailData.attachments = attachData.attachments.map((att, index) => ({
                name: att.name,
                size: att.size,
                mimetype: att.mimetype,
                // INCLURE LE CONTENU BASE64 SI DISPONIBLE
                content: att.content || generateFakeContent(att.name, att.size),
                encoding: 'base64',
                // Champs supplémentaires pour Dolibarr
                source: 'roundcube',
                tmp_name: `roundcube_att_${index}_${Date.now()}`
            }));
            
            console.log('📎 Pièces jointes formatées pour Dolibarr:', mailData.attachments);
        } else {
            console.log('⚠️ Aucune pièce jointe récupérée');
            mailData.attachments = [];
        }

    } catch (error) {
        console.error('❌ Erreur récupération pièces jointes:', error);
        mailData.attachments = [];
    }

    // Reste du code inchangé...
    if (!autoClassificationEnabled) {
        console.log('⚠️ Classement automatique désactivé');
        showNotification('Mail envoyé sans classement automatique (fonction désactivée)', 'info');
        return;
    }

    const hasSelection = Object.values(selectedEntities).some(entity => entity !== null);
    if (!hasSelection) {
        console.log('ℹ️ Aucun module sélectionné, pas de classement automatique');
        showNotification('Mail envoyé sans classement (aucun module sélectionné)', 'info');
        return;
    }

    console.log('✅ Démarrage du classement automatique');

    try {
        updateClassificationStatus('📤 Classement automatique en cours...', 'loading');

        // Préparer les données pour Dolibarr
        const saveData = {
            uid: `sent_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
            mbox: 'Sent',
            message_id: `<sent_${Date.now()}@roundcube>`,
            subject: mailData.subject || 'Sans sujet',
            from_email: getCurrentUserEmail(),
            to: mailData.to || '',
            raw_email: mailData.raw_email || buildRawEmailFromCompose(mailData),
            date: Math.floor(Date.now() / 1000),
            attachments: mailData.attachments, // Utiliser directement les attachments formatés
            direction: 'sent',
            links: []
        };

        Object.keys(selectedEntities).forEach(type => {
            if (selectedEntities[type]) {
                saveData.links.push({
                    type: type === 'contract' ? 'contrat' : type,
                    id: parseInt(selectedEntities[type].id),
                    name: selectedEntities[type].label || selectedEntities[type].name || ''
                });
            }
        });

        console.log('📤 Données finales pour Dolibarr:', saveData);

        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saveData)
        });

        const responseText = await response.text();
        const result = JSON.parse(responseText);

        if (result.status === 'OK') {
            updateClassificationStatus(`✅ Mail envoyé classé automatiquement! (ID: ${result.mail_id})`, 'success');
            showNotification('✅ Mail envoyé et classé automatiquement avec succès!', 'success');
            setTimeout(() => clearAllSelections(), 2000);
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'warning');
        }

    } catch (error) {
        console.error('❌ Erreur lors du classement automatique:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification('❌ Erreur lors du classement automatique', 'error');
    }
}




function buildRawEmailFromCompose(mailData) {
    return `Subject: ${mailData.subject}
To: ${mailData.to}
Content-Type: text/html; charset=UTF-8
Date: ${new Date().toISOString()}

${mailData.body}`;
}
/**
 * FONCTION PRINCIPALE : Classer et sauvegarder le mail
 */
async function classifyAndSaveMail() {
    console.log('📁 Début du classement et sauvegarde du mail...');
    console.log('Current mail data:', currentMailData);
    console.log('Selected entities:', selectedEntities);
    console.log(' - currentMailData.attachments:', currentMailData.attachments);

    
    // Vérifications
    if (!currentMailData) {
        showNotification('❌ Aucun mail sélectionné', 'error');
        return;
    }
    
    const hasSelection = Object.values(selectedEntities).some(entity => entity !== null);
    if (!hasSelection) {
        showNotification('⚠️ Veuillez sélectionner au moins un élément pour le classement', 'warning');
        return;
    }
    
    updateClassificationStatus('Préparation de la sauvegarde...', 'loading');
    
    try {
        // Préparer les données pour save_mails.php
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || `<${Date.now()}@roundcube>`,
            subject: currentMailData.subject || 'Sans sujet',
            from_email: currentMailData.from_email || 'unknown@example.com',
            raw_email: currentMailData.raw_email ||  'Contenu du mail',
            date: parseMailDateForSave(currentMailData.date),
            attachments: currentMailData.attachments || [],
            links: []
        };
        console.log('🐛 DEBUG - currentMailData.attachments:', currentMailData.attachments);
        console.log('🐛 DEBUG - saveData.attachments:', saveData.attachments);
        if (saveData.attachments.length > 0) {
            console.log('📎 Envoi de', saveData.attachments.length, 'pièces jointes');
        }
        // Ajouter les liens de classement
                    // Tiers
        if (selectedEntities.societe) {
            saveData.links.push({
                type: 'societe',
                id: parseInt(selectedEntities.societe.id),
                name: selectedEntities.societe.label || selectedEntities.societe.name || ''
            });
        }

        // Contact
        if (selectedEntities.contact) {
            saveData.links.push({
                type: 'contact',
                id: parseInt(selectedEntities.contact.id),
                name: selectedEntities.contact.label || selectedEntities.contact.name || ''
            });
        }

        // Projet
        if (selectedEntities.projet) {
            saveData.links.push({
                type: 'projet',
                id: parseInt(selectedEntities.projet.id),
                name: selectedEntities.projet.label || selectedEntities.projet.name || ''
            });
        }

        // Utilisateur
        if (selectedEntities.user) {
            saveData.links.push({
                type: 'user',
                id: parseInt(selectedEntities.user.id),
                name: selectedEntities.user.label || selectedEntities.user.name || ''
            });
        }

        // Groupe d'utilisateurs
        if (selectedEntities.usergroup) {
            saveData.links.push({
                type: 'usergroup',
                id: parseInt(selectedEntities.usergroup.id),
                name: selectedEntities.usergroup.label || selectedEntities.usergroup.name || ''
            });
        }

        // Proposition commerciale
        if (selectedEntities.propal) {
            saveData.links.push({
                type: 'propal',
                id: parseInt(selectedEntities.propal.id),
                name: selectedEntities.propal.label || selectedEntities.propal.name || ''
            });
        }

        // Commande client
        if (selectedEntities.commande) {
            saveData.links.push({
                type: 'commande',
                id: parseInt(selectedEntities.commande.id),
                name: selectedEntities.commande.label || selectedEntities.commande.name || ''
            });
        }

        // Expédition
        if (selectedEntities.expedition) {
            saveData.links.push({
                type: 'expedition',
                id: parseInt(selectedEntities.expedition.id),
                name: selectedEntities.expedition.label || selectedEntities.expedition.name || ''
            });
        }

        // Contrat
        if (selectedEntities.contract) {
            saveData.links.push({
                type: 'contract',
                id: parseInt(selectedEntities.contract.id),
                name: selectedEntities.contract.label || selectedEntities.contract.name || ''
            });
        }

        // Intervention
        if (selectedEntities.fichinter) {
            saveData.links.push({
                type: 'fichinter',
                id: parseInt(selectedEntities.fichinter.id),
                name: selectedEntities.fichinter.label || selectedEntities.fichinter.name || ''
            });
        }

        // Ticket
        if (selectedEntities.ticket) {
            saveData.links.push({
                type: 'ticket',
                id: parseInt(selectedEntities.ticket.id),
                name: selectedEntities.ticket.label || selectedEntities.ticket.name || ''
            });
        }

        // Commande fournisseur
        if (selectedEntities.supplier_order) {
            saveData.links.push({
                type: 'supplier_order',
                id: parseInt(selectedEntities.supplier_order.id),
                name: selectedEntities.supplier_order.label || selectedEntities.supplier_order.name || ''
            });
        }

        // Proposition fournisseur
        if (selectedEntities.supplier_proposal) {
            saveData.links.push({
                type: 'supplier_proposal',
                id: parseInt(selectedEntities.supplier_proposal.id),
                name: selectedEntities.supplier_proposal.label || selectedEntities.supplier_proposal.name || ''
            });
        }

        // Facture fournisseur
        if (selectedEntities.supplier_invoice) {
            saveData.links.push({
                type: 'supplier_invoice',
                id: parseInt(selectedEntities.supplier_invoice.id),
                name: selectedEntities.supplier_invoice.label || selectedEntities.supplier_invoice.name || ''
            });
        }

        // Réception
        if (selectedEntities.reception) {
            saveData.links.push({
                type: 'reception',
                id: parseInt(selectedEntities.reception.id),
                name: selectedEntities.reception.label || selectedEntities.reception.name || ''
            });
        }

        // Facture client
        if (selectedEntities.invoice) {
            saveData.links.push({
                type: 'invoice',
                id: parseInt(selectedEntities.invoice.id),
                name: selectedEntities.invoice.label || selectedEntities.invoice.name || ''
            });
        }

        // Salaire
        if (selectedEntities.salary) {
            saveData.links.push({
                type: 'salary',
                id: parseInt(selectedEntities.salary.id),
                name: selectedEntities.salary.label || selectedEntities.salary.name || ''
            });
        }

        // Emprunt
        if (selectedEntities.loan) {
            saveData.links.push({
                type: 'loan',
                id: parseInt(selectedEntities.loan.id),
                name: selectedEntities.loan.label || selectedEntities.loan.name || ''
            });
        }

        // Don
        if (selectedEntities.don) {
            saveData.links.push({
                type: 'don',
                id: parseInt(selectedEntities.don.id),
                name: selectedEntities.don.label || selectedEntities.don.name || ''
            });
        }

        // Congés
        if (selectedEntities.holiday) {
            saveData.links.push({
                type: 'holiday',
                id: parseInt(selectedEntities.holiday.id),
                name: selectedEntities.holiday.label || selectedEntities.holiday.name || ''
            });
        }

        // Note de frais
        if (selectedEntities.expensereport) {
            saveData.links.push({
                type: 'expensereport',
                id: parseInt(selectedEntities.expensereport.id),
                name: selectedEntities.expensereport.label || selectedEntities.expensereport.name || ''
            });
        }

        // Adhérent
        if (selectedEntities.adherent) {
            saveData.links.push({
                type: 'adherent',
                id: parseInt(selectedEntities.adherent.id),
                name: selectedEntities.adherent.label || selectedEntities.adherent.name || ''
            });
        }

        // Agenda / Événement
        if (selectedEntities.event) {
            saveData.links.push({
                type: 'event',
                id: parseInt(selectedEntities.event.id),
                name: selectedEntities.event.label || selectedEntities.event.name || ''
            });
        }

        // Comptabilité
        if (selectedEntities.accounting) {
            saveData.links.push({
                type: 'accounting',
                id: parseInt(selectedEntities.accounting.id),
                name: selectedEntities.accounting.label || selectedEntities.accounting.name || ''
            });
        }

        // Affaires
        if (selectedEntities.affaire) {
            saveData.links.push({
                type: 'affaire',
                id: parseInt(selectedEntities.affaire.id),
                name: selectedEntities.affaire.label || selectedEntities.affaire.name || ''
            });
        }

        
        console.log('📤 Données à envoyer:', JSON.stringify(saveData, null, 2));
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        console.log('URL de sauvegarde:', saveUrl);
        
        updateClassificationStatus('Envoi au serveur...', 'loading');
        
        // Appeler save_mails.php
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.error('Réponse brute:', responseText);
            
            if (responseText.includes('Fatal error') || responseText.includes('Warning')) {
                showNotification('❌ Erreur PHP dans save_mails.php - Voir console', 'error');
                updateClassificationStatus('❌ Erreur serveur PHP', 'error');
                return;
            }
            
            throw new Error('Réponse invalide du serveur');
        }
        
        console.log('📥 Réponse parsée:', result);
        
        // AJOUT: Gérer le cas DELETED
        if (result.status === 'DELETED') {
            updateClassificationStatus('Mail supprimé de la base de données', 'success');
            showNotification('Mail supprimé (aucun lien restant)', 'info');
            
            // Réinitialiser les états pour revenir au formulaire de classement
            existingMailData = null;
            isEditMode = false;
            
            // Effacer les sélections
            setTimeout(() => {
                clearAllSelections();
                // Réafficher le formulaire de classement normal
                showClassificationForm(currentMailData);
                // Réinitialiser les événements
                setTimeout(() => {
                    initSearchEvents();
                }, 100);
            }, 1500);
            
            return; // Important: sortir de la fonction ici
        }
        
        // Gérer la réponse normale
        if (result.status === 'OK') {
            updateClassificationStatus(`✅ Mail classé et sauvegardé! (ID: ${result.mail_id})`, 'success');
            showNotification('✅ Mail classé et sauvegardé avec succès!', 'success');
            
            const newMailData = {
                mail_id: result.mail_id,
                links: []
            };
            Object.keys(selectedEntities).forEach(type => {
            if (selectedEntities[type]) {
                newMailData.links.push({
                    target_type: type === 'contract' ? 'contrat' : type,
                    target_id: selectedEntities[type].id,
                    target_name: selectedEntities[type].label || selectedEntities[type].name
                    });
                }
            });
            // Effacer les sélections après succès
            setTimeout(() => {
                clearAllSelections();
                showExistingMailForm(currentMailData, newMailData);
            }, 2000);
            
        } else if (result.status === 'ALREADY_CLASSIFIED') {
            updateClassificationStatus('⚠️ Ce mail est déjà classé', 'warning');
            showNotification('⚠️ Ce mail est déjà classé', 'warning');
            
        } else if (result.status === 'DIFFERENT_LINKS') {
            handleDifferentLinks(result);
            
        } else if (result.status === 'ERROR') {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'error');
            
        } else {
            updateClassificationStatus('❌ Réponse inattendue', 'error');
            showNotification('❌ Erreur lors de la sauvegarde', 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur lors du classement:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification(`❌ Erreur: ${error.message}`, 'error');
    }
}

function getDolibarrUrl(type, id) {
    
    let dolibarrRoot = '';
    
    if (typeof CONFIG !== 'undefined' && CONFIG.DOL_URL_ROOT) {
        dolibarrRoot = CONFIG.DOL_URL_ROOT;
    } else {
        // Calculer depuis l'URL actuelle
        const currentPath = window.location.pathname;
        if (currentPath.includes('/custom/roundcubemodule/')) {
            dolibarrRoot = currentPath.substring(0, currentPath.indexOf('/custom/roundcubemodule/'));
        }
    }
    
    // Assurer qu'il n'y a pas de double slash
    if (dolibarrRoot.endsWith('/')) {
        dolibarrRoot = dolibarrRoot.slice(0, -1);
    }
    
    const urlMap = {
        'societe': '/societe/card.php?socid=',
        'thirdparty': '/societe/card.php?socid=',
        'contact': '/contact/card.php?id=',
        'projet': '/projet/card.php?id=',
        'project': '/projet/card.php?id=',
        'user': '/user/card.php?id=',
        'usergroup': '/user/group/card.php?id=',
        'propal': '/comm/propal/card.php?id=',
        'commande': '/commande/card.php?id=',
        'invoice': '/compta/facture/card.php?facid=',
        'expedition': '/expedition/card.php?id=',
        'contract': '/contrat/card.php?id=',
        'contrat': '/contrat/card.php?id=',
        'fichinter': '/fichinter/card.php?id=',
        'ticket': '/ticket/card.php?track_id=',
        'supplier_order': '/fourn/commande/card.php?id=',
        'supplier_proposal': '/supplier_proposal/card.php?id=',
        'supplier_invoice': '/fourn/facture/card.php?facid=',
        'reception': '/reception/card.php?id=',
        'salary': '/salaries/card.php?id=',
        'loan': '/loan/card.php?id=',
        'don': '/don/card.php?id=',
        'holiday': '/holiday/card.php?id=',
        'expensereport': '/expensereport/card.php?id=',
        'adherent': '/adherents/card.php?rowid=',
        'event': '/comm/action/card.php?id=',
        'accounting': '/accountancy/bookkeeping/card.php?piece_num=',
        'affaire': '/custom/affaire/card.php?id='
    };
    
    const path = urlMap[type];
    if (path) {
        return dolibarrRoot + path + id;
    }
    
    // Fallback pour types non reconnus
    return dolibarrRoot + '/custom/generic/card.php?type=' + type + '&id=' + id;
}
function generateExistingMailFormHTML(mailData, existingData) {
    const links = existingData.links || [];
    
    let linksHtml = '';
    if (links.length > 0) {
        linksHtml = '<div class="existing-links-container" style="background: rgba(40,167,69,0.2); padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
        linksHtml += '<h5 style="color: #28a745; margin-bottom: 10px;">📁 Modules liés actuels :</h5>';
        
        links.forEach(link => {
            const typeLabels = {
                'societe': '🏢 Tiers',
                'contact': '👤 Contact', 
                'projet': '📋 Projet',
                'user': '👤 Utilisateur',
                'propal': '📑 Proposition commerciale',
                'commande': '🛒 Commande client',
                'invoice': '💳 Facture client',
                'expedition': '📦 Expédition',
                'contract': '📜 Contrat',
                'fichinter': '🛠️ Intervention',
                'ticket': '🎫 Ticket',
                'supplier_order': '🛍️ Commande fournisseur',
                'supplier_proposal': '🤝 Proposition fournisseur',
                'supplier_invoice': '🧾 Facture fournisseur',
                'reception': '🚚 Réception',
                'salary': '💰 Salaire',
                'loan': '🏦 Emprunt',
                'don': '🎁 Don',
                'holiday': '🌴 Congé',
                'expensereport': '🧾✈️ Note de frais',
                'usergroup': '👥 Groupe',
                'adherent': '🪪 Adhérent',
                'event': '📅 Événement',
                'accounting': '📊 Comptabilité',
                'affaire': '📂 Affaire'
            };
            
            const typeLabel = typeLabels[link.target_type] || link.target_type;
            const dolibarrUrl = getDolibarrUrl(link.target_type, link.target_id);
            
            // NOUVEAU : Lien cliquable avec style amélioré
            linksHtml += `
                <div class="existing-link-item" style="padding: 8px; margin: 5px 0; background: rgba(255,255,255,0.1); border-radius: 3px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s;">
                    <a href="${dolibarrUrl}" 
                       target="_blank" 
                       style="color: #28a745; text-decoration: none; flex-grow: 1; display: flex; align-items: center;"
                       onmouseover="this.style.textDecoration='underline'; this.parentElement.style.background='rgba(255,255,255,0.2)'"
                       onmouseout="this.style.textDecoration='none'; this.parentElement.style.background='rgba(255,255,255,0.1)'"
                       title="Ouvrir dans Dolibarr">
                        <span style="margin-right: 10px;"><strong>${typeLabel}:</strong> ${link.target_name}</span>
                        <span style="margin-left: auto; margin-right: 10px;">🔗</span>
                    </a>
                    <small style="color: #999;">ID: ${link.target_id}</small>
                </div>
            `;
        });
        
        linksHtml += '</div>';
    }
    
    return `
        ${linksHtml}
        
        <div class="classification-actions" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="enterEditMode()" style="background: #007bff;">
                ✏️ Modifier le classement
            </button>
            <button class="btn" onclick="viewMailDetails()" style="background: #17a2b8;">
                👁️ Voir les détails
            </button>
            <button class="btn" onclick="resetClassificationForm()" style="background: #6c757d;">
                🔄 Actualiser
            </button>
        </div>
        
        <div id="classification-status" style="margin-top: 15px; display: none;">
            <!-- Zone pour afficher le statut -->
        </div>
    `;
}
/**
 * Sauvegarder le mail sans liens de classement
 */
async function saveMailWithoutLinks() {
    console.log('💾 Sauvegarde du mail sans classement...');
    
    if (!currentMailData) {
        showNotification('❌ Aucun mail sélectionné', 'error');
        return;
    }
    
    updateClassificationStatus('Sauvegarde sans classement...', 'loading');
    
    try {
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || `<${Date.now()}@roundcube>`,
            subject: currentMailData.subject || 'Sans sujet',
            from_email: currentMailData.from_email || 'unknown@example.com',
            raw_email: currentMailData.raw_email ||  'Contenu du mail',
            date: parseMailDateForSave(currentMailData.date),
            attachments: currentMailData.attachments || [],
            links: []// Pas de liens
        };
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing:', e);
            throw new Error('Réponse invalide');
        }
        
        if (result.status === 'OK' || result.status === 'ALREADY_CLASSIFIED') {
            updateClassificationStatus('✅ Mail sauvegardé sans classement', 'success');
            showNotification('✅ Mail sauvegardé sans classement', 'success');
            
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification(`❌ Erreur: ${error.message}`, 'error');
    }
}

/**
 * Gérer le cas où le mail a déjà des liens différents
 */
function handleDifferentLinks(result) {
    const statusDiv = document.getElementById('classification-status');
    if (!statusDiv) return;
    
    let html = '<div style="background: rgba(255,193,7,0.2); padding: 10px; border-radius: 5px;">';
    html += '<h5 style="color: #ffc107;">⚠️ Ce mail est déjà classé différemment</h5>';
    
    if (result.existing && result.existing.length > 0) {
        html += '<p><strong>Classement actuel:</strong></p><ul>';
        result.existing.forEach(link => {
            html += `<li>${link.target_name || link.name} (${link.target_type || link.type})</li>`;
        });
        html += '</ul>';
    }
    
    html += '<div style="margin-top: 10px;">';
    html += `<button onclick="reclassifyMail('sync_links')" class="btn" style="background: #28a745;">Remplacer</button> `;
    html += `<button onclick="reclassifyMail('add_links')" class="btn" style="background: #007bff;">Ajouter</button> `;
    html += `<button onclick="clearAllSelections()" class="btn" style="background: #6c757d;">Annuler</button>`;
    html += '</div></div>';
    
    statusDiv.innerHTML = html;
    statusDiv.style.display = 'block';
}

/**
 * Reclasser le mail
 */
async function reclassifyMail(action) {
    console.log(`📁 Reclassement avec action: ${action}`);
    
    updateClassificationStatus('Mise à jour...', 'loading');
    
    try {
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || `<${Date.now()}@roundcube>`,
            subject: currentMailData.subject || 'Sans sujet',
            from_email: currentMailData.from_email || 'unknown@example.com',
            raw_email: currentMailData.raw_email ||  'Contenu du mail',
            date: parseMailDateForSave(currentMailData.date),
            attachments: currentMailData.attachments || [],
            links: [],
            action: action
        };
        
        // Ajouter les liens
        if (selectedEntities.societe) {
            saveData.links.push({
                type: 'societe',
                id: parseInt(selectedEntities.societe.id),
                name: selectedEntities.societe.label || ''
            });
        }
        
        if (selectedEntities.contact) {
            saveData.links.push({
                type: 'contact',
                id: parseInt(selectedEntities.contact.id),
                name: selectedEntities.contact.label || ''
            });
        }
        
        if (selectedEntities.projet) {
            saveData.links.push({
                type: 'projet',
                id: parseInt(selectedEntities.projet.id),
                name: selectedEntities.projet.label || ''
            });
        }
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        const result = await response.json();
        
        if (result.status === 'UPDATED') {
            updateClassificationStatus('✅ Classement mis à jour!', 'success');
            showNotification('✅ Classement mis à jour!', 'success');
            
            setTimeout(() => {
                clearAllSelections();
            }, 2000);
            
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
    }
}

/**
 * Mettre à jour le statut
 */
function updateClassificationStatus(message, type) {
    const statusDiv = document.getElementById('classification-status');
    if (!statusDiv) return;
    
    const colors = {
        loading: '#007bff',
        success: '#28a745',
        warning: '#ffc107',
        error: '#dc3545'
    };
    
    statusDiv.innerHTML = `
        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px; border-left: 4px solid ${colors[type]};">
            ${type === 'loading' ? '⏳' : ''} ${message}
        </div>
    `;
    statusDiv.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

/**
 * Réinitialiser COMPLÈTEMENT le formulaire
 */
function resetClassificationForm() {
    console.log('🔄 Réinitialisation complète du formulaire...');
    
    selectedEntities = { societe: null, contact: null, projet: null };
    currentMailData = null;
    currentMailUID = null;
    currentMailId = null;
    lastProcessedMailUID = null;
    isFormDisplayed = false;
    
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
    
    if (noSelection) {
        noSelection.style.display = 'block';
    }
}

/**
 * Fonction de notification
 */
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;
    
    notification.className = 'show ' + type;
    notification.textContent = message;
    
    setTimeout(() => {
        notification.className = '';
    }, 4000);
}

/**
 * Test manuel
 */
function testMail() {
    console.log('🧪 Test manuel...');
    
    // Réinitialiser pour forcer un nouveau mail
    currentMailUID = null;
    
    const testData = {
        type: 'roundcube_mail_selected',
        data: {
            uid: 'test_' + Date.now(),
            message_id: '<test.' + Date.now() + '@example.com>',
            subject: 'Mail de test - ' + new Date().toLocaleTimeString(),
            from: 'Test User <test@example.com>',
            from_email: 'test@example.com',
            date: new Date().toISOString(),
            folder: 'INBOX'
        }
    };
    
    window.handleRoundcubeMessage({ data: testData });
    showNotification('📧 Mail de test chargé', 'info');
}

/**
 * Initialisation
 */
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 Bandeau JavaScript - Initialisation...');
    
    await loadActiveModules();
    initSelectedEntities();
    // Écouter les messages
    window.addEventListener('message', handleRoundcubeMessage);
    
    // Vérifier la configuration
    if (typeof CONFIG !== 'undefined') {
        console.log('✅ Configuration chargée:', {
            API_URL: CONFIG.API_URL,
            SAVE_URL: CONFIG.SAVE_URL,
            USER_ID: CONFIG.USER_ID
        });
    } else {
        console.error('❌ CONFIG non défini!');
    }
    
    // Initialiser les événements après un court délai
    setTimeout(() => {
        initSearchEvents();
    }, 500);
    detectCompositionMode();
    console.log('✅ Bandeau initialisé');
    
});

// Export des fonctions
window.classifyAndSaveMail = classifyAndSaveMail;
window.saveMailWithoutLinks = saveMailWithoutLinks;
window.reclassifyMail = reclassifyMail;
window.searchEntity = searchEntity;
window.selectEntity = selectEntity;
window.clearSelection = clearSelection;
window.clearAllSelections = clearAllSelections;
window.resetClassificationForm = resetClassificationForm;
window.testMail = testMail;
window.enterEditMode = enterEditMode;
window.cancelEditMode = cancelEditMode;
window.viewMailDetails = viewMailDetails;
window.saveModifiedClassification = saveModifiedClassification;

window.hideCompositionForm = hideCompositionForm;
window.handleMailBeingSent = handleMailBeingSent;
window.toggleAutoClassification = toggleAutoClassification;
window.loadAutoClassificationPreference = loadAutoClassificationPreference;
window.updateAutoClassificationUI = updateAutoClassificationUI;
window.reloadActiveModules = reloadActiveModules;

console.log('✅ Bandeau JavaScript chargé - Version Stable Sans Réinitialisation');