<?php

define('NOLOGIN', 1);

if (!defined('DOL_ROOT_PATH')) {
    define('DOL_ROOT_PATH', '../../../');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once DOL_ROOT_PATH . 'main.inc.php';

global $db, $user, $conf;

$active_modules = [];
$debug_log_content = "Début du log Dolibarr Modules:\n";

function buildModuleMap() {
    global $user, $conf, $debug_log_content;
    
    $modules = [];
    
    // Module Société/Tiers
    if (!empty($conf->societe->enabled)) {
        $modules['THIRDPARTY'] = ['value' => 'thirdparty', 'label' => 'Tiers'];
        $debug_log_content .= "Module Tiers: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Tiers: DÉSACTIVÉ\n";
    }
    
    // Module Contact (dépend de Société)
    if (!empty($conf->societe->enabled)) {
        $modules['CONTACT'] = ['value' => 'contact', 'label' => 'Contact'];
        $debug_log_content .= "Module Contact: ACTIVÉ (dépend de Tiers)\n";
    } else {
        $debug_log_content .= "Module Contact: DÉSACTIVÉ (Tiers non actif)\n";
    }
    
    // Module Projet
    if (!empty($conf->projet->enabled)) {
        $modules['PROJECT'] = ['value' => 'project', 'label' => 'Projet / Opportunité'];
        $debug_log_content .= "Module Projet: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Projet: DÉSACTIVÉ\n";
    }
    
    // Module Proposition commerciale
    if (!empty($conf->propal->enabled)) {
        $modules['PROPAL'] = ['value' => 'propal', 'label' => 'Proposition commerciale'];
        $debug_log_content .= "Module Proposition commerciale: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Proposition commerciale: DÉSACTIVÉ\n";
    }
    
    // Module Commande client
    if (!empty($conf->commande->enabled)) {
        $modules['ORDER'] = ['value' => 'commande', 'label' => 'Commande client'];
        $debug_log_content .= "Module Commande client: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Commande client: DÉSACTIVÉ\n";
    }
    
    // Module Expédition
    if (!empty($conf->expedition->enabled)) {
        $modules['SHIPPING'] = ['value' => 'expedition', 'label' => 'Expédition'];
        $debug_log_content .= "Module Expédition: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Expédition: DÉSACTIVÉ\n";
    }
    
    // Module Contrat
    if (!empty($conf->contrat->enabled)) {
        $modules['CONTRACT'] = ['value' => 'contract', 'label' => 'Contrat'];
        $debug_log_content .= "Module Contrat: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Contrat: DÉSACTIVÉ\n";
    }
    
    // Module Intervention
    if (!empty($conf->ficheinter->enabled)) {
        $modules['INTERVENTION'] = ['value' => 'fichinter', 'label' => 'Intervention'];
        $debug_log_content .= "Module Intervention: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Intervention: DÉSACTIVÉ\n";
    }
    
    // Module Ticket
    if (!empty($conf->ticket->enabled)) {
        $modules['TICKET'] = ['value' => 'ticket', 'label' => 'Ticket'];
        $debug_log_content .= "Module Ticket: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Ticket: DÉSACTIVÉ\n";
    }
    
    // Module Commande Fournisseur
    if (!empty($conf->fournisseur->enabled)) {
        $modules['SUPPLIER_ORDER'] = ['value' => 'supplier_order', 'label' => 'Commande Fournisseur'];
        $debug_log_content .= "Module Commande Fournisseur: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Commande Fournisseur: DÉSACTIVÉ\n";
    }
    
    // Module Proposition fournisseur
    if (!empty($conf->supplier_proposal->enabled)) {
        $modules['SUPPLIER_PROPOSAL'] = ['value' => 'supplier_proposal', 'label' => 'Proposition fournisseur'];
        $debug_log_content .= "Module Proposition fournisseur: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Proposition fournisseur: DÉSACTIVÉ\n";
    }
    
    // Module Facture fournisseur
    if (!empty($conf->supplier_invoice->enabled)) {
        $modules['SUPPLIER_INVOICE'] = ['value' => 'supplier_invoice', 'label' => 'Facture fournisseur'];
        $debug_log_content .= "Module Facture fournisseur: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Facture fournisseur: DÉSACTIVÉ\n";
    }
    
    // Module Réception
    if (!empty($conf->reception->enabled)) {
        $modules['RECEPTION'] = ['value' => 'reception', 'label' => 'Réception'];
        $debug_log_content .= "Module Réception: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Réception: DÉSACTIVÉ\n";
    }
    
    // Module Facture client
    if (!empty($conf->facture->enabled)) {
        $modules['INVOICE'] = ['value' => 'invoice', 'label' => 'Facture client'];
        $debug_log_content .= "Module Facture client: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Facture client: DÉSACTIVÉ\n";
    }
    
    // Module Salaire
    if (!empty($conf->salaries->enabled)) {
        $modules['SALARY'] = ['value' => 'salary', 'label' => 'Salaire'];
        $debug_log_content .= "Module Salaire: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Salaire: DÉSACTIVÉ\n";
    }
    
    // Module Emprunt
    if (!empty($conf->loan->enabled)) {
        $modules['LOAN'] = ['value' => 'loan', 'label' => 'Emprunt'];
        $debug_log_content .= "Module Emprunt: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Emprunt: DÉSACTIVÉ\n";
    }
    
    // Module Don
    if (!empty($conf->don->enabled)) {
        $modules['DONATION'] = ['value' => 'don', 'label' => 'Don'];
        $debug_log_content .= "Module Don: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Don: DÉSACTIVÉ\n";
    }
    
    // Module Congé
    if (!empty($conf->holiday->enabled)) {
        $modules['HOLIDAY'] = ['value' => 'holiday', 'label' => 'Congé'];
        $debug_log_content .= "Module Congé: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Congé: DÉSACTIVÉ\n";
    }
    
    // Module Note de frais
    if (!empty($conf->expensereport->enabled)) {
        $modules['EXPENSE_REPORT'] = ['value' => 'expensereport', 'label' => 'Note de frais'];
        $debug_log_content .= "Module Note de frais: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Note de frais: DÉSACTIVÉ\n";
    }
    
    // Module Utilisateur
    if (!empty($conf->user->enabled)) {
        $modules['USER'] = ['value' => 'user', 'label' => 'Utilisateur'];
        $debug_log_content .= "Module Utilisateur: ACTIVÉ\n";
        
        // Module Groupe d'utilisateurs (sous-module de User)
        $modules['USER_GROUP'] = ['value' => 'usergroup', 'label' => 'Groupe'];
        $debug_log_content .= "Module Groupe: ACTIVÉ (sous-module de Utilisateur)\n";
    } else {
        $debug_log_content .= "Module Utilisateur: DÉSACTIVÉ\n";
        $debug_log_content .= "Module Groupe: DÉSACTIVÉ (Utilisateur non actif)\n";
    }
    
    // Module Adhérent
    if (!empty($conf->adherent->enabled)) {
        $modules['MEMBER'] = ['value' => 'adherent', 'label' => 'Adhérent'];
        $debug_log_content .= "Module Adhérent: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Adhérent: DÉSACTIVÉ\n";
    }
    
    // Module Agenda/Événement
    if (!empty($conf->agenda->enabled)) {
        $modules['EVENT'] = ['value' => 'event', 'label' => 'Agenda / Événement'];
        $debug_log_content .= "Module Agenda: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Agenda: DÉSACTIVÉ\n";
    }
    
    // Module Comptabilité
    if (!empty($conf->accounting->enabled)) {
        $modules['ACCOUNTING'] = ['value' => 'accounting', 'label' => 'Comptabilité'];
        $debug_log_content .= "Module Comptabilité: ACTIVÉ\n";
    } else {
        $debug_log_content .= "Module Comptabilité: DÉSACTIVÉ\n";
    }
    
    // Module Affaires (module custom) - vérification par la BDD comme avant
    $sql_affaire = "SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name = 'MAIN_MODULE_KJRAFFAIRE'";
    global $db;
    $res_affaire = $db->query($sql_affaire);
    if ($res_affaire) {
        $obj_affaire = $db->fetch_object($res_affaire);
        if ($obj_affaire && $obj_affaire->value == 1) {
            $modules['AFFAIRE'] = ['value' => 'affaire', 'label' => 'Affaires'];
            $debug_log_content .= "Module Affaires: ACTIVÉ (module custom)\n";
        } else {
            $debug_log_content .= "Module Affaires: DÉSACTIVÉ (module custom)\n";
        }
    } else {
        $debug_log_content .= "Module Affaires: NON TROUVÉ (module custom)\n";
    }
    
    return $modules;
}

// Construire la liste des modules actifs
$module_map = buildModuleMap();

// Convertir en format de sortie pour compatibilité
foreach ($module_map as $key => $details) {
    $active_modules[] = [
        'value' => $details['value'],
        'label' => $details['label']
    ];
    $debug_log_content .= "Module FINAL AJOUTÉ: " . $details['label'] . " (" . $details['value'] . ")\n";
}

$debug_log_content .= "\nNombre total de modules actifs: " . count($active_modules) . "\n";
$debug_log_content .= "Liste finale: " . json_encode(array_column($active_modules, 'value')) . "\n";

header('Content-Type: application/json');
echo json_encode($active_modules);
exit;

?>