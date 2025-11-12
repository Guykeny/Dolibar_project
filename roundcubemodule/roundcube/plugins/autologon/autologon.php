<?php
/**
 * Plugin Roundcube pour l'authentification automatique depuis Dolibarr - VERSION CORRIGÉE
 * Supporte maintenant le changement de comptes multiples
 */

class autologon extends rcube_plugin
{
    public $task = 'login|mail';
    private $db;
    private $config;
    private $dolibarr_config = []; 

    function init()
    {
        $this->add_hook('startup', [$this, 'startup']);
        $this->add_hook('authenticate', [$this, 'authenticate']);
        $this->load_dolibarr_config();
        $this->init_config();
    }

    private function load_dolibarr_config()
    {
        $rcmail = rcmail::get_instance(); 
        $current_dir = __DIR__;
        $found_conf_path = null;
        
        // Recherche du fichier conf.php de Dolibarr en remontant l'arborescence
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
            require_once $found_conf_path;
            
            // Récupérer les informations de la base de données depuis la configuration Dolibarr
            $this->dolibarr_config['dolibarr_main_db_host'] = $dolibarr_main_db_host;
            $this->dolibarr_config['dolibarr_main_db_port'] = isset($dolibarr_main_db_port) ? $dolibarr_main_db_port : '3306';
            $this->dolibarr_config['dolibarr_main_db_name'] = $dolibarr_main_db_name;
            $this->dolibarr_config['dolibarr_main_db_user'] = $dolibarr_main_db_user;
            $this->dolibarr_config['dolibarr_main_db_pass'] = $dolibarr_main_db_pass;
            $this->dolibarr_config['table_prefix'] = $dolibarr_main_db_prefix;
            
            return;
        }
    }

    private function init_config()
    {
        // Paramètres de base du plugin
        $this->config = [
            'shared_secret' => 'MyAx37okNmcBQWxsVIGDW29WDXiiuRkqZVZJQ364oyGFjCDzTvSznzQflQvsYpdW',
            'allowed_ips' => ['127.0.0.1', '::1'] 
        ];

        // Remplacer par les valeurs de Dolibarr
        if (!empty($this->dolibarr_config['dolibarr_main_db_host'])) {
            $this->config['db_host'] = $this->dolibarr_config['dolibarr_main_db_host'];
            $this->config['db_port'] = $this->dolibarr_config['dolibarr_main_db_port'];
            $this->config['db_name'] = $this->dolibarr_config['dolibarr_main_db_name'];
            $this->config['db_user'] = $this->dolibarr_config['dolibarr_main_db_user'];
            $this->config['db_pass'] = $this->dolibarr_config['dolibarr_main_db_pass'];
            $this->config['table_prefix'] = $this->dolibarr_config['table_prefix'];
        }
    }

    private function init_db()
    {
        try {
            $dsn = "mysql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_name']};charset=utf8";
            $this->db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;
        } catch (PDOException $e) {
            error_log("Autologon DB Error: " . $e->getMessage());
            return false;
        }
    }

    function startup($args)
    {
        if (empty($_SESSION['user_id']) && $this->is_autologin_request()) {
            $args['action'] = 'login';
        }
        return $args;
    }

    function authenticate($args)
    {
        if ($this->is_autologin_request() && $this->init_db()) {
            // Récupérer l'ID utilisateur à partir de l'URL
            $dolibarr_id = isset($_GET['dolibarr_id']) ? $_GET['dolibarr_id'] : null;
            $account_id = isset($_GET['account_id']) ? $_GET['account_id'] : null; // NOUVEAU
            error_log("=== AUTOLOGON DEBUG ===");
            error_log("Paramètres GET: " . print_r($_GET, true));
            error_log("dolibarr_id: " . $dolibarr_id);
            error_log("account_id: " . ($account_id ?: 'NULL'));
            error_log("account_id est numeric: " . (is_numeric($account_id) ? 'oui' : 'non'));
            error_log("account_id > 0: " . (($account_id > 0) ? 'oui' : 'non'));
        

            if ($dolibarr_id === null || !is_numeric($dolibarr_id)) {
                error_log("Autologon: ID utilisateur Dolibarr manquant ou invalide");
                return $args;
            }

            // LOG pour debug
            //error_log("Autologon: Tentative de connexion pour utilisateur Dolibarr ID: $dolibarr_id, Compte ID: " . ($account_id ?: 'défaut'));

            // CORRECTION 1: Récupérer les informations du compte spécifique ou par défaut
            $account_data = $this->getAccountData($dolibarr_id, $account_id);
            
            if ($account_data) {
                // Déchiffrer le mot de passe
                $password = $this->decryptPassword($account_data['password_encrypted']);
                
                // LOG pour debug (attention aux logs en production !)
                error_log("Autologon: Connexion pour " . $account_data['email'] . " sur " . $account_data['imap_host']);

                // CORRECTION 2: Construction correcte de l'host
                $host = $this->buildImapHost($account_data);

                // Définir les paramètres d'authentification pour Roundcube
                $args['user'] = $account_data['email'];
                $args['pass'] = $password;
                $args['host'] = $host;
                $args['cookiecheck'] = false;
                $args['valid'] = true;

                $args['csrf_check_login'] = false;
                
                // LOG de succès
                error_log("Autologon: Paramètres définis avec succès");
            } else {
                // CORRECTION 3: Fallback sur l'ancienne méthode si nouvelle table inexistante
                error_log("Autologon: Compte webmail non trouvé, tentative fallback");
                $fallback_data = $this->getFallbackAccountData($dolibarr_id);
                
                if ($fallback_data) {
                    $args['user'] = $fallback_data['login_roundcube'];
                    $args['pass'] = $fallback_data['password_roundcube'];
                    $args['host'] = $fallback_data['host'] ?: 'localhost'; // Host par défaut
                    $args['cookiecheck'] = false;
                    $args['valid'] = true;
                    
                    error_log("Autologon: Fallback réussi pour " . $fallback_data['login_roundcube']);
                } else {
                    error_log("Autologon: Aucune donnée de connexion trouvée");
                }
            }
        }
        return $args;
    }

    /**
     * NOUVELLE MÉTHODE: Récupérer les données d'un compte spécifique
     */
    private function getAccountData($dolibarr_id, $account_id = null)
    {
        try {
            if ($account_id && is_numeric($account_id)) {
                // Récupérer un compte spécifique
                $stmt = $this->db->prepare(
                    "SELECT rowid, email, password_encrypted, imap_host, imap_port, imap_encryption, account_name
                     FROM " . $this->config['table_prefix'] . "mailboxmodule_mail_accounts
                     WHERE fk_user = ? AND rowid = ? AND is_active = 1"
                );
                $stmt->execute([$dolibarr_id, $account_id]);
            } else {
                // Récupérer le compte par défaut
                $stmt = $this->db->prepare(
                    "SELECT rowid, email, password_encrypted, imap_host, imap_port, imap_encryption, account_name
                     FROM " . $this->config['table_prefix'] . "mailboxmodule_mail_accounts
                     WHERE fk_user = ? AND is_active = 1
                     ORDER BY is_default DESC, account_name ASC
                     LIMIT 1"
                );
                $stmt->execute([$dolibarr_id]);
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Autologon getAccountData Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NOUVELLE MÉTHODE: Fallback sur l'ancienne table user
     */
    private function getFallbackAccountData($dolibarr_id)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT login_roundcube, password_roundcube
                 FROM " . $this->config['table_prefix'] . "user
                 WHERE rowid = ?"
            );
            $stmt->execute([$dolibarr_id]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['login_roundcube']) && !empty($row['password_roundcube'])) {
                return [
                    'login_roundcube' => $row['login_roundcube'],
                    'password_roundcube' => $row['password_roundcube'],
                    'host' => 'localhost' // Vous pouvez adapter selon votre config
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Autologon getFallbackAccountData Error: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * NOUVELLE MÉTHODE: Construction correcte de l'host IMAP
     */
    private function buildImapHost($account_data)
    {
        $host = $account_data['imap_host'];
        $port = $account_data['imap_port'] ?: 993;
        $encryption = $account_data['imap_encryption'] ?: 'ssl';
        
        // Si l'host contient déjà le protocole, le retourner tel quel
        if (strpos($host, '://') !== false) {
            return $host;
        }
        
        // Construire l'host avec le protocole
        return $encryption . '://' . $host . ':' . $port;
    }

    private function decryptPassword($encryptedPassword)
    {
        return base64_decode($encryptedPassword);
    }

    private function is_autologin_request()
    {
        // Vérifier si la requête vient de Dolibarr avec les paramètres attendus
        $ip_ok = in_array($_SERVER['REMOTE_ADDR'], $this->config['allowed_ips']);
        $secret_ok = !empty($_GET['secret']) && hash_equals($this->config['shared_secret'], $_GET['secret']);
        $autologin_param_exists = !empty($_GET['_autologin']);

        return $autologin_param_exists && ($ip_ok || $secret_ok);
    }
}