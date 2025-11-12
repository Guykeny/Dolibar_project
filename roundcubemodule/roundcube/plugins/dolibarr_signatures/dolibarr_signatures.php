<?php
/**
 * Plugin Dolibarr Signatures pour Roundcube
 * Version avec détection automatique de la configuration Dolibarr
 * 
 * Fichier : roundcube/plugins/dolibarr_signatures/dolibarr_signatures.php
 */

class dolibarr_signatures extends rcube_plugin
{
    public $task = 'mail';
    
    // Déclaration explicite de toutes les propriétés
    private $dolibarr_config = array();
    private $dolibarr_url = null;
    private $dolibarr_root = null;  // Ajout de cette propriété manquante
    
    /**
     * Initialisation du plugin
     */
    public function init()
    {
        $this->load_dolibarr_config();
        
        // Ne pas ajouter le hook si la configuration n'est pas chargée
        if (!empty($this->dolibarr_config['db_host'])) {
            // Hooks pour la composition de messages
            $this->add_hook('message_compose', array($this, 'add_signature'));
            $this->add_hook('message_compose_body', array($this, 'add_signature'));
            
            // Hook pour forcer le mode HTML si nécessaire
            $this->add_hook('message_before_send', array($this, 'ensure_html_format'));
        }
    }
    
    /**
     * Charge la configuration Dolibarr en cherchant conf.php
     */
    private function load_dolibarr_config()
    {
        $current_dir = __DIR__;
        $found_conf_path = null;
        
        // Recherche du fichier conf.php de Dolibarr en remontant l'arborescence
        $temp_dir = $current_dir;
        for ($i = 0; $i < 10; $i++) {
            // Chercher dans différents emplacements possibles
            $potential_paths = [
                $temp_dir . '/conf/conf.php',
                $temp_dir . '/htdocs/conf/conf.php',
                dirname($temp_dir) . '/conf/conf.php'
            ];
            
            foreach ($potential_paths as $potential_conf_path) {
                if (file_exists($potential_conf_path)) {
                    $found_conf_path = $potential_conf_path;
                    // Déterminer le répertoire racine de Dolibarr
                    $conf_dir = dirname($potential_conf_path);
                    if (basename($conf_dir) === 'conf') {
                        $this->dolibarr_root = dirname($conf_dir);
                    } else {
                        $this->dolibarr_root = $conf_dir;
                    }
                    break 2;
                }
            }
            
            $temp_dir = dirname($temp_dir);
            if ($temp_dir === '/' || $temp_dir === dirname($temp_dir)) {
                break;
            }
        }
        
        if ($found_conf_path) {
            // Isoler le chargement du fichier de configuration pour éviter les conflits
            $this->load_config_file($found_conf_path);
            
            error_log("Dolibarr Signatures: Configuration chargée depuis " . $found_conf_path);
            error_log("Dolibarr Signatures: DB=" . $this->dolibarr_config['db_name'] . ", Prefix=" . $this->dolibarr_config['table_prefix']);
        } else {
            error_log("Dolibarr Signatures: Impossible de trouver conf.php de Dolibarr");
        }
    }
    
    /**
     * Charge le fichier de configuration de manière isolée
     */
    private function load_config_file($conf_path)
    {
        // Utiliser une fonction anonyme pour isoler les variables
        $load_config = function($path) {
            if (file_exists($path)) {
                include $path;
                
                return array(
                    'db_host' => isset($dolibarr_main_db_host) ? $dolibarr_main_db_host : null,
                    'db_port' => isset($dolibarr_main_db_port) ? $dolibarr_main_db_port : '3306',
                    'db_name' => isset($dolibarr_main_db_name) ? $dolibarr_main_db_name : null,
                    'db_user' => isset($dolibarr_main_db_user) ? $dolibarr_main_db_user : null,
                    'db_pass' => isset($dolibarr_main_db_pass) ? $dolibarr_main_db_pass : null,
                    'db_prefix' => isset($dolibarr_main_db_prefix) ? $dolibarr_main_db_prefix : 'llx_',
                    'url_root' => isset($dolibarr_main_url_root) ? $dolibarr_main_url_root : null
                );
            }
            return array();
        };
        
        $config = $load_config($conf_path);
        
        if (!empty($config['db_host'])) {
            $this->dolibarr_config = array(
                'db_host' => $config['db_host'],
                'db_port' => $config['db_port'],
                'db_name' => $config['db_name'],
                'db_user' => $config['db_user'],
                'db_pass' => $config['db_pass'],
                'table_prefix' => $config['db_prefix']
            );
            $this->dolibarr_url = $config['url_root'];
        }
    }
    
    /**
     * Ajoute la signature lors de la composition d'un message
     */
    public function add_signature($args)
    {
        // Vérifier que la configuration est chargée
        if (empty($this->dolibarr_config['db_host'])) {
            return $args;
        }
        
        // Éviter d'ajouter la signature plusieurs fois
        static $signature_added = false;
        if ($signature_added) {
            return $args;
        }
        
        try {
            // Connexion à la base Dolibarr
            $db = $this->get_db_connection();
            if (!$db) {
                return $args;
            }
            
            // Récupérer l'email de l'utilisateur actuel dans Roundcube
            $rcmail = rcmail::get_instance();
            $current_email = $rcmail->user->get_username();
            
            error_log("Dolibarr Signatures: Recherche signature pour " . $current_email);
            
            // Rechercher ce compte dans la base Dolibarr
            $account = $this->get_mail_account($db, $current_email);
            
            if ($account) {
                error_log("Dolibarr Signatures: Compte trouvé (ID=" . $account['rowid'] . ")");
                
                // Construire et appliquer la signature
                $args = $this->apply_signature($args, $account, $rcmail);
                $signature_added = true;
                
            } else {
                error_log("Dolibarr Signatures: Aucun compte trouvé pour " . $current_email);
                $this->debug_list_accounts($db);
            }
            
        } catch (PDOException $e) {
            error_log("Dolibarr Signatures PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Dolibarr Signatures Error: " . $e->getMessage());
        }
        
        return $args;
    }
    
    /**
     * Établit la connexion à la base de données
     */
    private function get_db_connection()
    {
        try {
            $dsn = "mysql:host={$this->dolibarr_config['db_host']};port={$this->dolibarr_config['db_port']};dbname={$this->dolibarr_config['db_name']};charset=utf8mb4";
            $db = new PDO($dsn, $this->dolibarr_config['db_user'], $this->dolibarr_config['db_pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $db;
        } catch (PDOException $e) {
            error_log("Dolibarr Signatures: Erreur de connexion DB: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Récupère le compte mail depuis la base Dolibarr
     */
    private function get_mail_account($db, $email)
    {
        $sql = "SELECT rowid, signature_html, signature_text, logo_filename, logo_type, fk_user 
                FROM {$this->dolibarr_config['table_prefix']}mailboxmodule_mail_accounts 
                WHERE email = :email AND is_active = 1 
                ORDER BY is_default DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        return $stmt->fetch();
    }
    
    /**
     * Applique la signature au message
     */
    private function apply_signature(&$args, $account, $rcmail)
    {
        // Construire la signature HTML et texte
        $signature_html = $this->build_html_signature($account);
        $signature_text = $account['signature_text'] ?: '';
        
        if (empty($signature_html) && empty($signature_text)) {
            return $args;
        }
        
        // Forcer le mode HTML si on a une signature HTML
        if (!empty($signature_html)) {
            // Forcer le mode HTML pour Roundcube
            $_POST['_is_html'] = 1;
            $args['param']['html'] = true;
            
            // S'assurer que le body actuel est en HTML
            if (!isset($args['body']) || empty($args['body'])) {
                $args['body'] = '';
            }
            
            // Si le body n'est pas déjà en HTML, le convertir
            if (strpos($args['body'], '<') === false) {
                $args['body'] = nl2br(htmlspecialchars($args['body']));
            }
            
            // Ajouter la signature HTML
            $separator = '<br><br>--<br>';
            $args['body'] = $args['body'] . $separator . $signature_html;
            
            error_log("Dolibarr Signatures: Signature HTML ajoutée (mode HTML forcé)");
        } else {
            // Mode texte uniquement si pas de signature HTML
            $separator = "\n\n--\n";
            $args['body'] = ($args['body'] ?? '') . $separator . $signature_text;
            error_log("Dolibarr Signatures: Signature texte ajoutée");
        }
        
        return $args;
    }
    
    /**
     * Construit la signature HTML avec logo si disponible
     */
    private function build_html_signature($account)
    {
        $signature_html = '';
        
        // Ajouter le logo si présent
        if (!empty($account['logo_filename'])) {
            // Essayer de charger le logo en base64
            $logo_base64 = $this->get_logo_as_base64($account);
            
            if ($logo_base64) {
                $signature_html = '<div style="margin-bottom: 10px;">' .
                    '<img src="' . $logo_base64 . '" ' .
                    'style="max-width: 200px; max-height: 100px; height: auto; display: block;" ' .
                    'alt="Logo" />' .
                    '</div>';
                
                error_log("Dolibarr Signatures: Logo intégré en base64");
            } else {
                // Fallback : essayer avec l'URL externe
                $logo_url = $this->get_logo_url($account);
                if ($logo_url) {
                    $signature_html = '<div style="margin-bottom: 10px;">' .
                        '<img src="' . $logo_url . '" ' .
                        'style="max-width: 200px; max-height: 100px; height: auto; display: block;" ' .
                        'alt="Logo" />' .
                        '</div>';
                    
                    error_log("Dolibarr Signatures: Logo URL = " . $logo_url);
                }
            }
        }
        
        // Ajouter la signature HTML ou convertir le texte
        if (!empty($account['signature_html'])) {
            // S'assurer que la signature HTML est bien formée
            $signature_html .= '<div>' . $account['signature_html'] . '</div>';
        } elseif (!empty($account['signature_text'])) {
            $signature_html .= '<div>' . nl2br(htmlspecialchars($account['signature_text'])) . '</div>';
        }
        
        // Envelopper le tout dans un conteneur
        if (!empty($signature_html)) {
            $signature_html = '<div class="dolibarr-signature">' . $signature_html . '</div>';
        }
        
        return $signature_html;
    }
    
    /**
     * Récupère le logo en base64
     */
    private function get_logo_as_base64($account)
    {
        // Construire le chemin physique du fichier
        $logo_relative_path = $this->build_logo_path($account);
        
        // Essayer différents chemins possibles
        $possible_paths = array();
        
        if (!empty($this->dolibarr_root)) {
            $possible_paths[] = $this->dolibarr_root . '/documents' . $logo_relative_path;
            $possible_paths[] = $this->dolibarr_root . '/../documents' . $logo_relative_path;
            $possible_paths[] = dirname($this->dolibarr_root) . '/documents' . $logo_relative_path;
        }
        
        // Essayer aussi des chemins relatifs
        $possible_paths[] = __DIR__ . '/../../../../documents' . $logo_relative_path;
        $possible_paths[] = __DIR__ . '/../../../../../documents' . $logo_relative_path;
        
        foreach ($possible_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                error_log("Dolibarr Signatures: Logo trouvé : " . $path);
                
                // Lire le fichier et le convertir en base64
                $image_data = file_get_contents($path);
                if ($image_data) {
                    // Déterminer le type MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $image_data);
                    finfo_close($finfo);
                    
                    // Si le type MIME n'est pas détecté, essayer par l'extension
                    if (!$mime_type) {
                        $ext = strtolower(pathinfo($account['logo_filename'], PATHINFO_EXTENSION));
                        $mime_types = array(
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            'webp' => 'image/webp'
                        );
                        $mime_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'image/jpeg';
                    }
                    
                    // Retourner l'image en base64
                    return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                }
            }
        }
        
        error_log("Dolibarr Signatures: Logo non trouvé dans les chemins testés");
        return null;
    }
    
    /**
     * Construit l'URL du logo (fallback)
     */
    private function get_logo_url($account)
    {
        if (empty($this->dolibarr_url)) {
            // Essayer de construire l'URL depuis l'environnement actuel
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Supposer que Dolibarr est à la racine ou dans /doli
            $base_url = $protocol . '://' . $host;
            
            // Chercher le chemin Dolibarr dans l'URL courante
            $current_path = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('#/(doli|dolibarr)[^/]*/#i', $current_path, $matches)) {
                $base_url .= $matches[0];
            } else {
                $base_url .= '/doli/';
            }
            
            $this->dolibarr_url = rtrim($base_url, '/') . '/htdocs';
        }
        
        $logo_path = $this->build_logo_path($account);
        $logo_url = $this->dolibarr_url . '/document.php?modulepart=doctemplates&file=' . urlencode($logo_path);
        
        // S'assurer que l'URL est absolue
        if (strpos($logo_url, 'http') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $logo_url = $protocol . '://' . $host . $logo_url;
        }
        
        return $logo_url;
    }
    
    /**
     * Construit le chemin du logo
     */
    private function build_logo_path($account)
    {
        $logo_path = '/doctemplates/mail/logo/user_' . $account['fk_user'];
        
        if ($account['logo_type'] == 'account') {
            $logo_path .= '/account_' . $account['rowid'];
        } else {
            $logo_path .= '/global';
        }
        
        return $logo_path . '/' . $account['logo_filename'];
    }
    
    /**
     * Détermine si le mode HTML est activé
     */
    private function is_html_mode($args, $rcmail)
    {
        // Vérifier plusieurs sources pour le mode HTML
        if (!empty($args['param']['_is_html'])) {
            return true;
        }
        
        if (isset($_POST['_is_html']) && $_POST['_is_html'] == '1') {
            return true;
        }
        
        if (!isset($args['param']['_is_html'])) {
            return $rcmail->config->get('html_editor_mode', true);
        }
        
        return false;
    }
    
    /**
     * S'assure que le message est bien au format HTML avant l'envoi
     */
    public function ensure_html_format($args)
    {
        // Si le message contient des balises HTML de signature, forcer le format HTML
        if (isset($args['message']) && 
            (strpos($args['message']->get_body(), '<div class="dolibarr-signature">') !== false ||
             strpos($args['message']->get_body(), '<img src=') !== false)) {
            
            $args['message']->set_html_body($args['message']->get_body());
            error_log("Dolibarr Signatures: Format HTML forcé pour l'envoi");
        }
        
        return $args;
    }
    
    /**
     * Debug : liste les comptes disponibles
     */
    private function debug_list_accounts($db)
    {
        try {
            $sql = "SELECT email FROM {$this->dolibarr_config['table_prefix']}mailboxmodule_mail_accounts WHERE is_active = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($emails)) {
                error_log("Dolibarr Signatures: Emails disponibles : " . implode(', ', $emails));
            } else {
                error_log("Dolibarr Signatures: Aucun compte email actif trouvé dans la base");
            }
        } catch (Exception $e) {
            error_log("Dolibarr Signatures: Erreur lors du listing des comptes : " . $e->getMessage());
        }
    }
}
?>