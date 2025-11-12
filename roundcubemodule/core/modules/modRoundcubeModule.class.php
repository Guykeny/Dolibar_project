<?php

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modRoundcubeModule extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 104010;
        $this->rights_class = 'roundcubemodule';
        $this->family = "crm";
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Module complet de webmail Roundcube avec redirection des mails depuis Dolibarr et onglets Mails";
        $this->version = '2.2'; // Version incrémentée pour la fusion avec MyMailBox
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-envelope';
        $this->config_page_url = array('roundcube_config.php@roundcubemodule');
        $this->langfiles = array('roundcubemodule@roundcubemodule');
        
        // Répertoires nécessaires pour les deux modules
        $this->dirs = array(
            "/roundcubemodule/temp",
            "/roundcubemodule/redirectmail/temp",
            "/mymailbox_module/temp" // Ajouté depuis MyMailBox
        );
        
        // FUSION : Onglets combinés des deux modules
        $this->tabs = array(
            // Onglet utilisateur (depuis RoundcubeModule original)
            'user:+roundcube:Roundcube:roundcubemodule@roundcubemodule:/custom/roundcubemodule/user_webmail_tab.php?id=__ID__',
            
            // NOUVEAUX ONGLETS : Tous les onglets Mails depuis MyMailBox
            // Tiers - mode original avec socid
            'thirdparty:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?socid=__ID__&module=thirdparty',
            
            // Contact - mode original avec contactid
            'contact:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=contact',
            
            // Tous les autres objets - mode générique avec module et id
            'project:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=projet',
            'propal:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=propal',
            'order:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=order',
            'expedition:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=expedition',
            'contract:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=contract',
            'fichinter:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=fichinter',
            'ticket:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=ticket',
            'supplier_proposal:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=supplier_proposal',
            'supplier_order:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=supplier_order',
            'supplier_invoice:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=supplier_invoice',
            'reception:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=reception',
            'invoice:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=invoice',
            'salary:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=salary',
            'loan:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=loan',
            'don:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=don',
            'holiday:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=holiday',
            'expensereport:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=expensereport',
            'user:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=user',
            'group:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=usergroup',
            'adherent:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=adherent',
            'event:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=event',
            'accounting:+mailtab:Mails:@roundcubemodule:/custom/roundcubemodule/mailtab.php?id=__ID__&module=accounting'
        );
        
        // FUSION : Droits des deux modules
        $this->rights = array();
        $r = 0;
        
        // Droits du module Roundcube (existants)
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Utiliser le webmail Roundcube';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'webmail';
        $this->rights[$r][5] = 'read';
        $r++;
        
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Gérer ses comptes webmail';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'accounts';
        $this->rights[$r][5] = 'write';
        $r++;
        
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Administrer tous les comptes webmail';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = 'write';
        $r++;
        
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Configurer le module Roundcube';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 1; // admin only
        $this->rights[$r][4] = 'config';
        $this->rights[$r][5] = 'write';
        $r++;
        
        
        // NOUVEAUX DROITS : Pour les onglets Mails (depuis MyMailBox)
        $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
        $this->rights[$r][1] = 'Consulter les onglets Mails';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'mailtab';
        $this->rights[$r][5] = 'read';
        $r++;
        
       

        // FUSION : Menus combinés
        $this->menu = array();
        $r = 0;
        
        // Menu principal Webmail
        $this->menu[$r] = array(
            'fk_menu' => 0,
            'type' => 'top',
            'titre' => 'Webmail',
            'mainmenu' => 'fa-envelope',
            'leftmenu' => '',
            'url' => '/custom/roundcubemodule/roundcube.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 100,
            'enabled' => '1',
            'picto' => 'fa-envelope',
            'perms' => '$user->hasRight("roundcubemodule", "webmail", "read")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu : Mes comptes
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Mes comptes',
            'leftmenu' => 'roundcube_accounts',
            'url' => '/user/card.php?id=__USER_ID__&tab=roundcube',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 110,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "accounts", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // NOUVEAU : Sous-menu Archive Mails (depuis MyMailBox)
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Archive des mails',
            'leftmenu' => 'roundcube_mailarchive',
            'url' => '/custom/roundcubemodule/mail_archive.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 112,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "mailtab", "read")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu Redirection mail
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Configuration redirection',
            'leftmenu' => 'roundcube_redirect',
            'url' => '/custom/roundcubemodule/admin/redirect_config.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 115,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "redirect", "read")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu admin : Configuration
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Configuration',
            'leftmenu' => 'roundcube_config',
            'url' => '/custom/roundcubemodule/admin/roundcube_config.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 120,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "config", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // Sous-menu admin : Gestion des comptes
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=roundcube',
            'type' => 'left',
            'titre' => 'Gestion des comptes',
            'leftmenu' => 'roundcube_admin',
            'url' => '/custom/roundcubemodule/admin/accounts_list.php',
            'langs' => 'roundcubemodule@roundcubemodule',
            'position' => 130,
            'enabled' => '1',
            'perms' => '$user->hasRight("roundcubemodule", "admin", "write")',
            'target' => '',
            'user' => 2
        );
        $r++;
        
        // FUSION : Hooks des deux modules
        $this->module_parts = array(
            'hooks' => array(
                'thirdpartycard',  // Depuis redirectmail
                'projectcard',     // Depuis redirectmail
                // NOUVEAU : Hooks pour les onglets mails sur tous les objets
                'contactcard',
                'propalcard',
                'ordercard',
                'expeditioncard',
                'contractcard',
                'fichintercard',
                'ticketcard',
                'supplier_proposalcard',
                'supplier_ordercard',
                'supplier_invoicecard',
                'receptioncard',
                'invoicecard',
                'salarycard',
                'loancard',
                'doncard',
                'holidaycard',
                'expensereportcard',
                'usercard',
                'groupcard',
                'adherentcard',
                'eventcard',
                'accountingcard'
            )
        );

        // Versions minimum requises
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(16, 0);
        
        $this->enabled = '1';
        $this->always_enabled = 0;
    }

    public function init($options = '')
    {
        global $conf, $db;

        // Constantes du module Roundcube (existantes)
        $sql = array();
        if (!isset($conf->global->ROUNDCUBE_URL)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_URL', '/custom/roundcubemodule/roundcube/', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->ROUNDCUBE_USE_AUTOLOGIN)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_USE_AUTOLOGIN', '1', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->ROUNDCUBE_AUTO_REDIRECT)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_AUTO_REDIRECT', '0', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->ROUNDCUBE_MENU_AUTOLOGIN)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('ROUNDCUBE_MENU_AUTOLOGIN', '1', 'chaine', 0, ".$conf->entity.")";
        }
        
        // Constantes pour la redirection mail
        if (!isset($conf->global->REDIRECTMAIL_ENABLED)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('REDIRECTMAIL_ENABLED', '1', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->REDIRECTMAIL_TARGET_URL)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('REDIRECTMAIL_TARGET_URL', '/custom/roundcubemodule/compose.php', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->REDIRECTMAIL_BUTTON_TEXT)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('REDIRECTMAIL_BUTTON_TEXT', 'Envoyer un email via Roundcube', 'chaine', 0, ".$conf->entity.")";
        }
        
        // NOUVELLES CONSTANTES : Pour MyMailBox
        if (!isset($conf->global->MYMAILBOX_ENABLED)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('MYMAILBOX_ENABLED', '1', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->MYMAILBOX_SHOW_TABS)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('MYMAILBOX_SHOW_TABS', '1', 'chaine', 0, ".$conf->entity.")";
        }
        if (!isset($conf->global->MYMAILBOX_AUTO_ARCHIVE)) {
            $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, type, visible, entity) VALUES ('MYMAILBOX_AUTO_ARCHIVE', '1', 'chaine', 0, ".$conf->entity.")";
        }
        
        foreach ($sql as $query) $db->query($query);

        // Création des tables fusionnées
        $this->createDatabaseStructure();
        
        // Création base Roundcube (existant)
        // Création base Roundcube avec nom personnalisé
try {
    // Récupérer les paramètres de connexion depuis la config Dolibarr
    $db_host = empty($conf->db->host) ? 'localhost' : $conf->db->host;
    $db_user = $conf->db->user;
    $db_pass = '';
    
    
        $db_name_roundcube = 'roundcubemail'; // Changez ce nom comme vous voulez

        // Fallback vers conf.php pour récupérer les paramètres réels
        $current_dir = __DIR__;
        $found_conf_path = null;
        
        $temp_dir = $current_dir;
        for ($i = 0; $i < 10; $i++) {
            $potential_conf_path = $temp_dir . '/conf/conf.php';
            if (file_exists($potential_conf_path)) {
                $found_conf_path = $potential_conf_path;
                break;
            }
            $temp_dir = dirname($temp_dir);
            if ($temp_dir === '/' || $temp_dir === $current_dir) {
                break;
            }
            $current_dir = $temp_dir;
        }

        if ($found_conf_path) {
            $content = file_get_contents($found_conf_path);
            
            if (preg_match('/\$dolibarr_main_db_host\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
                $db_host = $matches[1];
            }
            if (preg_match('/\$dolibarr_main_db_user\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
                $db_user = $matches[1];
            }
            if (preg_match('/\$dolibarr_main_db_pass\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
                $db_pass = $matches[1];
            }
        }

        // Se connecter SANS spécifier de base pour pouvoir la créer
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Vérifier si la base existe et la créer si nécessaire
        $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name_roundcube'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE DATABASE `$db_name_roundcube` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            dol_syslog("Base de données $db_name_roundcube créée", LOG_INFO);
        }

        // Se connecter à la base créée
        $pdo->exec("USE `$db_name_roundcube`");

        // Créer les tables Roundcube
        $sql_file = dol_buildpath('/custom/roundcubemodule/sql/mysql.initial.sql');
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $pdo->exec($sql);
            dol_syslog("Tables Roundcube créées dans $db_name_roundcube", LOG_INFO);
        } else {
            dol_syslog("Fichier SQL introuvable : $sql_file", LOG_ERR);
        }

    } catch (PDOException $e) {
        dol_syslog("Erreur PDO lors de la création de la base Roundcube : " . $e->getMessage(), LOG_ERR);
    }
    // Synchronisation automatique avec logs détaillés
        // Synchronisation automatique
        $detectFile = dol_buildpath('/custom/roundcubemodule/detecte.php');
        if (file_exists($detectFile)) {
            include_once $detectFile;
        }

        return parent::init($options);
    }
    
    /**
     * Créer la structure de base de données fusionnée :
     * - Toutes les tables du module Roundcube original
     * - Toutes les tables du module MyMailBox
     * - Nouvelles tables pour la redirection si nécessaire
     */
    private function createDatabaseStructure()
    {
        global $db, $conf;
        
        try {
            // Tables du module Roundcube original (inchangées)
            $table_accounts = MAIN_DB_PREFIX."mailboxmodule_mail_accounts";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_accounts` (
                `rowid` int NOT NULL AUTO_INCREMENT,
                `fk_user` int NOT NULL,
                `account_name` varchar(100) DEFAULT NULL,
                `email` varchar(255) NOT NULL,
                `password_encrypted` text,
                `imap_host` varchar(255) DEFAULT NULL,
                `imap_port` int DEFAULT 993,
                `imap_encryption` varchar(10) DEFAULT 'ssl',
                `smtp_host` varchar(255) DEFAULT NULL,
                `smtp_port` int DEFAULT 587,
                `smtp_encryption` varchar(10) DEFAULT 'tls',
                `smtp_auth` tinyint DEFAULT 1,
                `imap_folder_sent` varchar(100) DEFAULT 'Sent',
                `imap_folder_trash` varchar(100) DEFAULT 'Trash',
                `imap_folder_drafts` varchar(100) DEFAULT 'Drafts',
                `imap_folder_spam` varchar(100) DEFAULT 'Spam',
                `signature_text` text,
                `signature_html` text,
                `reply_to` varchar(255) DEFAULT NULL,
                `display_name` varchar(100) DEFAULT NULL,
                `organization` varchar(100) DEFAULT NULL,
                `user_language` varchar(10) DEFAULT 'fr_FR',
                `user_timezone` varchar(50) DEFAULT 'Europe/Paris',
                `user_theme` varchar(20) DEFAULT 'elastic',
                `date_format` varchar(20) DEFAULT 'd/m/Y',
                `time_format` varchar(20) DEFAULT 'H:i',
                `compose_mode` varchar(20) DEFAULT 'html',
                `reply_mode` varchar(20) DEFAULT 'quote',
                `draft_autosave_interval` int DEFAULT 300,
                `auto_mark_read` tinyint DEFAULT 1,
                `display_next_message` tinyint DEFAULT 0,
                `mail_refresh_interval` int DEFAULT 300,
                `logo_filename` varchar(255) DEFAULT NULL,
                `logo_type` varchar(20) DEFAULT 'global',
                `sync_enabled` tinyint DEFAULT 1,
                `sync_interval` int DEFAULT 300,
                `sync_folders` text,
                `last_sync` datetime DEFAULT NULL,
                `oauth_provider` varchar(50) DEFAULT NULL,
                `oauth_token` text,
                `oauth_refresh_token` text,
                `oauth_expires` datetime DEFAULT NULL,
                `is_default` tinyint DEFAULT 0,
                `is_active` tinyint DEFAULT 1,
                `last_connection` datetime DEFAULT NULL,
                `connection_count` int DEFAULT 0,
                `last_error` text,
                `error_count` int DEFAULT 0,
                `quota_used` bigint DEFAULT 0,
                `quota_total` bigint DEFAULT 0,
                `message_count` int DEFAULT 0,
                `date_creation` datetime NOT NULL,
                `date_modification` datetime DEFAULT NULL,
                `fk_user_creat` int DEFAULT NULL,
                `fk_user_modif` int DEFAULT NULL,
                `import_key` varchar(14) DEFAULT NULL,
                `status` int DEFAULT 1,
                `note_private` text,
                `note_public` text,
                PRIMARY KEY (`rowid`),
                KEY `fk_user` (`fk_user`),
                KEY `email` (`email`),
                KEY `is_default` (`is_default`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            // Créer le répertoire de logos si besoin
            $logo_base = DOL_DOCUMENT_ROOT . '/doctemplates/mail/logo';
            if (!is_dir($logo_base)) {
                dol_mkdir($logo_base);
            }

            // Tables d'archivage (depuis MyMailBox + améliorées)
            $table_mail = MAIN_DB_PREFIX."mailboxmodule_mail";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_mail` (
                `rowid` INT(11) NOT NULL AUTO_INCREMENT,
                `message_id` VARCHAR(255) NOT NULL UNIQUE,
                `subject` VARCHAR(255) DEFAULT NULL,
                `from_email` VARCHAR(255) DEFAULT NULL,
                `date_received` DATETIME DEFAULT NULL,
                `file_path` VARCHAR(255) DEFAULT NULL,
                `fk_soc` INT(11) DEFAULT NULL,
                `imap_mailbox` VARCHAR(255) DEFAULT NULL,
                `imap_uid` INT(11) DEFAULT NULL,
                `direction` VARCHAR(10) DEFAULT 'received',
                PRIMARY KEY (`rowid`),
                UNIQUE KEY `idx_message_id` (`message_id`),
                KEY `idx_fk_soc` (`fk_soc`),
                KEY `idx_imap_uid` (`imap_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            $table_attach = MAIN_DB_PREFIX."mailboxmodule_attachment";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_attach` (
                `rowid` INT(11) NOT NULL AUTO_INCREMENT,
                `fk_mail` INT(11) NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) DEFAULT NULL,
                `mimetype` VARCHAR(100) DEFAULT NULL,
                `filepath` VARCHAR(255) NOT NULL,
                `filesize` INT DEFAULT 0,
                `is_inline` TINYINT DEFAULT 0,
                `content_id` VARCHAR(255) DEFAULT NULL,
                `entity` INT(11) DEFAULT 1,
                `datec` DATETIME NOT NULL,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_mail` (`fk_mail`),
                KEY `idx_filename` (`filename`),
                KEY `idx_is_inline` (`is_inline`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            $table_links = MAIN_DB_PREFIX."mailboxmodule_mail_links";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_links` (
                `rowid` INT NOT NULL AUTO_INCREMENT,
                `fk_mail` INT NOT NULL,
                `target_type` VARCHAR(32) NOT NULL,
                `target_id` INT NOT NULL,
                `target_name` VARCHAR(255) DEFAULT NULL,
                `link_type` VARCHAR(20) DEFAULT 'manual',
                `confidence_score` DECIMAL(3,2) DEFAULT 1.00,
                `date_created` DATETIME DEFAULT NULL,
                `fk_user_created` INT DEFAULT NULL,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_mail` (`fk_mail`),
                KEY `idx_target` (`target_type`, `target_id`),
                KEY `idx_link_type` (`link_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            $table_filters = MAIN_DB_PREFIX."mailboxmodule_mail_filters";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_filters` (
                `rowid` int NOT NULL AUTO_INCREMENT,
                `fk_account` int NOT NULL,
                `filter_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
                `filter_order` int DEFAULT '0',
                `is_active` tinyint DEFAULT '1',
                `conditions` text COLLATE utf8mb4_unicode_ci,
                `conditions_operator` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'AND',
                `actions` text COLLATE utf8mb4_unicode_ci,
                `match_count` int DEFAULT '0',
                `last_match` datetime DEFAULT NULL,
                `date_creation` datetime NOT NULL,
                `date_modification` datetime DEFAULT NULL,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_account` (`fk_account`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_filter_order` (`filter_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);
            
            $table_logs = MAIN_DB_PREFIX."mailboxmodule_mail_logs";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_logs` (
                `rowid` int NOT NULL AUTO_INCREMENT,
                `fk_account` int NOT NULL,
                `fk_user` int NOT NULL,
                `action` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `details` text COLLATE utf8mb4_unicode_ci,
                `date_action` datetime NOT NULL,
                `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `error_message` text COLLATE utf8mb4_unicode_ci,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_account` (`fk_account`),
                KEY `idx_fk_user` (`fk_user`),
                KEY `idx_date_action` (`date_action`),
                KEY `idx_action` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            $table_templates = MAIN_DB_PREFIX."mailboxmodule_mail_templates";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_templates` (
                `rowid` int NOT NULL AUTO_INCREMENT,
                `fk_user` int NOT NULL,
                `fk_account` int DEFAULT NULL,
                `template_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
                `template_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `body_text` text COLLATE utf8mb4_unicode_ci,
                `body_html` text COLLATE utf8mb4_unicode_ci,
                `attachments` text COLLATE utf8mb4_unicode_ci,
                `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `is_public` tinyint DEFAULT '0',
                `is_active` tinyint DEFAULT '1',
                `use_count` int DEFAULT '0',
                `last_used` datetime DEFAULT NULL,
                `date_creation` datetime NOT NULL,
                `date_modification` datetime DEFAULT NULL,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_user` (`fk_user`),
                KEY `idx_fk_account` (`fk_account`),
                KEY `idx_template_code` (`template_code`),
                KEY `idx_is_public` (`is_public`),
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

            // Table pour logs de redirection
            $table_redirect_logs = MAIN_DB_PREFIX."roundcube_redirect_logs";
            $sql = "CREATE TABLE IF NOT EXISTS `$table_redirect_logs` (
                `rowid` int NOT NULL AUTO_INCREMENT,
                `fk_user` int NOT NULL,
                `object_type` varchar(50) NOT NULL,
                `object_id` int NOT NULL,
                `redirect_url` varchar(500) NOT NULL,
                `date_redirect` datetime NOT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`rowid`),
                KEY `idx_fk_user` (`fk_user`),
                KEY `idx_object` (`object_type`, `object_id`),
                KEY `idx_date_redirect` (`date_redirect`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);

        } catch (Exception $e) {
            dol_syslog("Erreur création structure Roundcube/MyMailBox fusionné: " . $e->getMessage(), LOG_ERR);
        }
    }
    
    public function remove($options = '')
    {
        global $conf, $db;
        
        // Supprimer les constantes des modules fusionnés
        $sql = array();
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'ROUNDCUBE_%'";
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'REDIRECTMAIL_%'";
        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MYMAILBOX_%'";
        foreach ($sql as $query) $db->query($query);

        // ⚠️ On NE DROP PAS les tables d'archivage par défaut (sécurité des données).
        // Vous pouvez décommenter prudemment si nécessaire :
        /*
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."roundcube_redirect_logs");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_mail_links");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_attachment");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_mail");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_mail_accounts");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_mail_filters");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_webmail_logs");
        $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."mailboxmodule_webmail_templates");
        */

        return parent::remove($options);
    }
}
?>