<?php

if (defined('DOL_DOCUMENT_ROOT') && strpos($_SERVER['SCRIPT_NAME'], 'admin/modules.php') !== false) {
    ob_start();
}

// Vérifier si le script est appelé directement OU inclus depuis un module
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME']) ||
    (defined('DOL_DOCUMENT_ROOT') && strpos($_SERVER['SCRIPT_NAME'], 'admin/modules.php') !== false)) {

    // Inclusion du main.inc.php seulement si pas déjà inclus
    if (!defined('DOL_DOCUMENT_ROOT')) {
        require_once '../../main.inc.php';
    }

    // Affichage seulement pour exécution manuelle
    if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
        echo "=== Synchronisation manuelle des configurations DB ===\n\n";
    }

    // Recherche du fichier conf.php
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

    if (!$found_conf_path) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "ERREUR : Fichier conf.php de Dolibarr introuvable\n";
        }
        // Do not exit, just return
        return;
    }

    $roundcubeConfigPath = DOL_DOCUMENT_ROOT . '/custom/roundcubemodule/roundcube/config/config.inc.php';

    // Arguments en ligne de commande (optionnel)
    if (isset($argc) && $argc > 1 && !empty($argv[1])) {
        $found_conf_path = $argv[1];
    }
    if (isset($argc) && $argc > 2 && !empty($argv[2])) {
        $roundcubeConfigPath = $argv[2];
    }

    if (!file_exists($found_conf_path)) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "ERREUR : Fichier Dolibarr introuvable : " . $found_conf_path . "\n";
        }
        return;
    }

    if (!file_exists($roundcubeConfigPath)) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "ERREUR : Fichier Roundcube introuvable : " . $roundcubeConfigPath . "\n";
        }
        return;
    }

    if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
        echo "Fichier conf Dolibarr : " . $found_conf_path . "\n";
        echo "Fichier config Roundcube : " . $roundcubeConfigPath . "\n\n";
    }

    // Lecture configuration Dolibarr
    $content = file_get_contents($found_conf_path);
    $dbHost = 'localhost';
    $dbUser = '';
    $dbPass = '';
    $dbName = '';

    if (preg_match('/\$dolibarr_main_db_pass\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
        $dbPass = $matches[1];
    } else {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "Password variable not found in conf file\n";
        }
    }

    if (preg_match('/\$dolibarr_main_db_host\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
        $dbHost = $matches[1];
    }
    if (preg_match('/\$dolibarr_main_db_name\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
        $dbName = $matches[1];
    }
    if (preg_match('/\$dolibarr_main_db_user\s*=\s*[\'"]([^\'"]*)[\'"];/', $content, $matches)) {
        $dbUser = $matches[1];
    }

    if (empty($dbUser)) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "ERREUR : Utilisateur de base de données non trouvé\n";
        }
        return;
    }
    if (empty($dbHost)) $dbHost = 'localhost';

    if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
        echo "Configuration Dolibarr lue :\n";
        echo "- Utilisateur DB : " . $dbUser . "\n";
        echo "- Mot de passe DB : " . (empty($dbPass) ? '(vide)' : str_repeat('*', strlen($dbPass))) . "\n";
        echo "- Hôte DB : " . $dbHost . "\n";
        echo "- Base de données : " . $dbName . "\n\n";
    }

    // Mise à jour configuration Roundcube
    $configContent = file_get_contents($roundcubeConfigPath);
    $newDsn = "mysql://{$dbUser}:{$dbPass}@{$dbHost}/roundcubemail";
    $autologinDsn = "mysql://{$dbUser}:{$dbPass}@{$dbHost}/{$dbName}";

    $patterns = [
        '/(\$config\[\'db_dsnw\'\]\s*=\s*[\'"])([^\'"]*?)([\'"];)/' => $newDsn,
        '/(\$config\[\'autologin_db_dsn\'\]\s*=\s*[\'"])([^\'"]*?)([\'"];)/' => $autologinDsn,
        '/(\$config\[\'autologon_db_dsn\'\]\s*=\s*[\'"])([^\'"]*?)([\'"];)/' => $autologinDsn
    ];

    $newConfigContent = $configContent;
    foreach ($patterns as $pattern => $dsn) {
        $newConfigContent = preg_replace($pattern, '${1}' . $dsn . '${3}', $newConfigContent);
    }

    if ($newConfigContent === $configContent) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "Aucune modification nécessaire\n";
        }
        return;
    }

    if (file_put_contents($roundcubeConfigPath, $newConfigContent) !== false) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "Configuration Roundcube mise à jour avec succès !\n";
            echo "- db_dsnw : " . $newDsn . "\n";
            echo "- autologin_db_dsn : " . $autologinDsn . "\n";
            echo "- autologon_db_dsn : " . $autologinDsn . "\n";
            echo "\n=== Synchronisation terminée ===\n";
        }
        return;
    } else {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
            echo "ERREUR : Impossible d'écrire le fichier de configuration\n";
        }
        return;
    }
}

// If output buffering was started, clean and end it. This discards all the output.
if (defined('DOL_DOCUMENT_ROOT') && strpos($_SERVER['SCRIPT_NAME'], 'admin/modules.php') !== false) {
    ob_end_clean();
}
?>