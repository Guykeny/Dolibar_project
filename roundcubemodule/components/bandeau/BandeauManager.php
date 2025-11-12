<?php


class BandeauManager {
    
    private $user;
    private $conf;
    private $db;
    private $langs;
    
    public function __construct($user, $conf, $db, $langs) {
        $this->user = $user;
        $this->conf = $conf;
        $this->db = $db;
        $this->langs = $langs;
    }
    
    /**
     * Inclure les styles CSS du bandeau
     */
    public function includeCss() {
        $css_path = dol_buildpath('/custom/roundcubemodule/components/bandeau/css/bandeau.css', 1);
        echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '">' . "\n";
    }
    
    /**
     * Inclure les scripts JavaScript du bandeau
     */
    public function includeJs() {
        $js_path = dol_buildpath('/custom/roundcubemodule/components/bandeau/js/bandeau.js', 1);
        echo '<script src="' . $js_path . '"></script>' . "\n";
        
        $classification_js = dol_buildpath('/custom/roundcubemodule/components/classification/js/mail-classification.js', 1);
        echo '<script src="' . $classification_js . '"></script>' . "\n";
    }
    
    /**
     * Générer la configuration JavaScript avec gestion des comptes webmail
     */
    public function generateJsConfig($roundcube_url, $accounts = []) {
        $config = [
            'API_URL' => dol_buildpath('/custom/roundcubemodule/classification/api/search-entities.php', 1),
            'AJAX_URL' => dol_buildpath('/custom/roundcubemodule/ajax/get_mail_modules.php', 1),
            'SAVE_URL' => dol_buildpath('/custom/roundcubemodule/scripts/save_mails.php', 1),
            'ROUNDCUBE_URL' => $roundcube_url,
            'USER_ID' => $this->user->id,
            'USER_EMAIL' => $this->user->email,
            'DEBUG' => !empty($this->conf->global->ROUNDCUBE_DEBUG),
            'ACCOUNTS' => $accounts, // Ajouter les comptes webmail
            'RIGHTS' => [
                'webmail' => $this->user->hasRight('roundcubemodule', 'webmail', 'read'),
                'accounts' => $this->user->hasRight('roundcubemodule', 'accounts', 'write'),
                'admin' => $this->user->hasRight('roundcubemodule', 'admin', 'write'),
                'config' => $this->user->hasRight('roundcubemodule', 'config', 'write')
            ]
        ];
        
        echo '<script>const CONFIG = ' . json_encode($config) . ';</script>' . "\n";
    }
    
    /**
     * Rendre la section utilisateur
     */
    public function renderUserSection() {
        $rights = [];
        if ($this->user->hasRight('roundcubemodule', 'webmail', 'read')) $rights[] = 'Lecture';
        if ($this->user->hasRight('roundcubemodule', 'accounts', 'write')) $rights[] = 'Comptes';
        if ($this->user->hasRight('roundcubemodule', 'admin', 'write')) $rights[] = 'Admin';
        
        ob_start();
        ?>
        <div class="section info">
            <div class="user-info">

                <h3>👤 Utilisateur</h3>
                <div class="info-row">
                    <span class="info-label">Nom:</span>
                    <span><?php echo $this->user->getFullName($this->langs); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span><?php echo $this->user->email ?: 'Non défini'; ?></span>
                </div>
                <div class="rights-indicator">
                    Droits: <?php echo implode(', ', $rights) ?: 'Aucun'; ?>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    
    public function renderClassificationSection() {
        ob_start();
        ?>
        <div class="section">
            <h3></h3>
            <div id="classification-container">
                <div id="classification-no-selection" class="no-mail-selected">
                    <div>🔭</div>
                    <p style="font-size: 13px; opacity: 0.8;">Sélectionnez un mail pour le classer</p>
                </div>
                
                <div id="classification-form" style="display: none;">
                    <!-- Contenu dynamique géré par mail-classification.js -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    
    public function renderGedSection() {
        ob_start();
        ?>
        <div class="section">
            <div class="ged-info">
                <h3>📁 GED</h3>
                <div style="text-align: center; padding: 20px; opacity: 0.7;">
                    <div style="font-size: 32px; margin-bottom: 10px;">🚧</div>
                    <p style="font-size: 13px;">Fonctionnalité à venir</p>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }
    
    
    public function renderConfigSection() {
        ob_start();
        ?>
        <div class="section">
            <div class="config-section">
                <h3>⚙️ Configuration</h3>
            
                <?php if ($this->user->hasRight('roundcubemodule', 'accounts', 'write')): ?>
                <a class="btn" href="<?php echo dol_buildpath('/user/card.php?id='.$this->user->id.'&tab=webmail', 1); ?>">
                    🔧 Mes comptes mail
                </a>
                <?php endif; ?>
                
                <?php if ($this->user->hasRight('roundcubemodule', 'admin', 'write')): ?>
                <a class="btn" href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/accounts_list.php', 1); ?>">
                    👥 Gérer les comptes
                </a>
                <?php endif; ?>
                
                <?php if ($this->user->hasRight('roundcubemodule', 'config', 'write')): ?>
                <a class="btn" href="<?php echo dol_buildpath('/custom/roundcubemodule/admin/roundcube_config.php', 1); ?>">
                    ⚙️ Paramètres
                </a>
                <?php endif; ?>
                
                <!-- Boutons de test temporaires -->
                <button class="btn" onclick="testClassificationSystem()">🔧 Test Classement</button>
                <button class="btn" onclick="testMail()">📧 Test Mail</button>
            </div>

            </div>
            
        <?php
        return ob_get_clean();
    }
    
    
    public function renderDebugSection() {
        if (empty($this->conf->global->ROUNDCUBE_DEBUG)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="section">
            <h3>🧪 Debug</h3>
            <button class="btn" onclick="testMail()">📧 Simuler mail</button>
            <button class="btn" onclick="showDebug()">📊 Debug complet</button>
            <button class="btn" onclick="checkIframeStatus()">🔍 Status iframe</button>
            <button class="btn" onclick="forceShowClassificationForm()">🔧 Forcer formulaire</button>
            <div id="api-status" style="margin-top: 10px;">
                <div id="api-result" style="font-size: 11px; color: rgba(255,255,255,0.8);">En attente...</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    
    public function render() {
        ob_start();
        ?>
        <div id="bandeau">
            
            <?php echo $this->renderClassificationSection(); ?>
            <?php echo $this->renderGedSection(); ?>
            <?php echo $this->renderConfigSection(); ?>
            <?php echo $this->renderDebugSection(); ?>
        </div>
        
        <!-- Notification -->
        <div id="notification"></div>
        <?php
        return ob_get_clean();
    }
    
    
    public static function renderBandeau($user, $conf, $db, $langs, $roundcube_url, $accounts = []) {
        $bandeau = new self($user, $conf, $db, $langs);
        
        // Inclure les assets
        $bandeau->includeCss();
        $bandeau->generateJsConfig($roundcube_url, $accounts);
        
        // Rendre le HTML
        echo $bandeau->render();
        
        // Inclure les scripts (à la fin pour éviter les erreurs de dépendances)
        $bandeau->includeJs();
    }
}


 
?>