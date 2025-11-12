<?php
/**
 * API de recherche d'entités + vérification d'existence des mails
 * Emplacement: custom/roundcubemodule/components/classification/api/search-entities.php
 */
define('NOCSRFCHECK', 1);
// Recherche de main.inc.php
$res = 0;
$paths = ['../../../../main.inc.php', '../../../../../main.inc.php', '../../../../../../main.inc.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $res = 1;
        break;
    }
}

if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration Dolibarr non trouvée']);
    exit;
}

// Headers pour JSON et CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Vérification de l'authentification
if (empty($user->id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Vérification des droits
if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Droits insuffisants']);
    exit;
}

/**
 * Point d'entrée principal
 */
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'search_entities':
            $type = $_GET['type'] ?? '';
            $query = $_GET['query'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 résultats
            
            if (empty($type) || empty($query)) {
                throw new Exception('Paramètres manquants: type et query requis');
            }
            
            $results = searchEntities($type, $query, $limit);
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'count' => count($results),
                'query' => $query,
                'type' => $type
            ]);
            break;
            
        case 'check_mail_exists':
            handleCheckMailExists();
            break;
            
        case 'test':
            // Test de l'API
            echo json_encode([
                'success' => true,
                'message' => 'API de recherche fonctionnelle',
                'user_id' => $user->id,
                'user_name' => $user->getFullName($langs),
                'timestamp' => date('Y-m-d H:i:s'),
                'supported_types' => [
                    'thirdparty', 'contact', 'projet', 'user', 'facture', 'commande', 'usergroup', 'adherent', 'holiday', 
                    'expensereport', 'propal', 'contract', 'ticket', 'fichinter', 'supplier_proposal', 'supplier_order', 
                    'supplier_invoice', 'reception', 'salary', 'loan', 'don', 'event', 'accounting', 'affaire'
                ]
            ]);
            break;
            
        default:
            throw new Exception('Action non supportée: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * NOUVELLE FONCTION : Vérifier si un mail existe déjà
 */
function handleCheckMailExists() {
    global $db;
    
    try {
        // Récupérer les données JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Pas de données reçues']);
            return;
        }
        
        $uid = $db->escape($input['uid'] ?? '');
        $message_id = $db->escape($input['message_id'] ?? '');
        $mbox = $db->escape($input['mbox'] ?? 'INBOX');
        
        if (empty($uid) && empty($message_id)) {
            echo json_encode(['success' => false, 'error' => 'UID ou message_id requis']);
            return;
        }
        
        // Requête pour vérifier l'existence
        $sql = "SELECT rowid, message_id, subject, from_email, date_received, file_path, fk_soc, direction, 
               date_format(date_received, '%Y-%m-%d %H:%i') as date_formatted
        FROM ".MAIN_DB_PREFIX."mailboxmodule_mail 
        WHERE ";
        
        $conditions = [];
        if (!empty($message_id)) {
            $conditions[] = "message_id = '".$message_id."'";
        }
        if (!empty($uid)) {
            $conditions[] = "(imap_uid = ".(int)$uid." AND imap_mailbox = '".$mbox."')";
        }
        
        $sql .= "(" . implode(" OR ", $conditions) . ")";
        $sql .= " LIMIT 1";
        
        $result = $db->query($sql);
        
        if (!$result) {
            throw new Exception("Erreur SQL: " . $db->lasterror());
        }
        
        if ($db->num_rows($result) === 0) {
            echo json_encode([
                'success' => true, 
                'exists' => false,
                'mailData' => null
            ]);
            return;
        }
        
        $obj = $db->fetch_object($result);
        
        // Le mail existe, récupérer ses liens
        $links = getMailLinks($obj->rowid);
        
        // Préparer les données du mail existant

        $mailData = [
            'mail_id' => $obj->rowid,
            'message_id' => $obj->message_id,
            'subject' => $obj->subject,
            'from_email' => $obj->from_email,
            'date_received' => $obj->date_formatted,
            'file_path' => $obj->file_path,
            'fk_soc' => $obj->fk_soc,
            'direction' => $obj->direction,
            'links' => $links
        ];
        
        echo json_encode([
            'success' => true,
            'exists' => true,
            'mailData' => $mailData
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur check_mail_exists: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Erreur lors de la vérification: ' . $e->getMessage()
        ]);
    }
}

/**
 * NOUVELLE FONCTION : Récupérer les liens d'un mail
 */
function getMailLinks($mail_id) {
    global $db;
    
    $links = [];
    
    try {
        $sql = "SELECT target_type, target_id, target_name 
                FROM ".MAIN_DB_PREFIX."mailboxmodule_mail_links 
                WHERE fk_mail = ".(int)$mail_id."
                ORDER BY target_type, target_name";
        
        $result = $db->query($sql);
        
        if ($result) {
            while ($row = $db->fetch_object($result)) {
                $links[] = [
                    'target_type' => $row->target_type,
                    'target_id' => (int)$row->target_id,
                    'target_name' => $row->target_name
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Erreur getMailLinks: " . $e->getMessage());
    }
    
    return $links;
}

/**
 * Fonction de recherche d'entités (votre code existant)
 */
function searchEntities($type, $query, $limit = 10) {
    global $db, $user;
    
    $results = [];
    $query = trim($query);
    
    if (strlen($query) < 2) {
        return $results;
    }
    
    $query_escaped = $db->escape($query);
    
    try {
        switch ($type) {
            case 'thirdparty':
                $sql = "SELECT s.rowid as id, s.nom as label, s.code_client, s.email 
                        FROM " . MAIN_DB_PREFIX . "societe s 
                        WHERE s.entity IN (" . getEntity('societe') . ") 
                        AND (s.nom LIKE '%" . $query_escaped . "%' 
                             OR s.code_client LIKE '%" . $query_escaped . "%'
                             OR s.email LIKE '%" . $query_escaped . "%')
                        ORDER BY s.nom ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'contact':
                $sql = "SELECT c.rowid as id, 
                               CONCAT(c.firstname, ' ', c.lastname) as label,
                               c.email,
                               s.nom as societe_nom
                        FROM " . MAIN_DB_PREFIX . "socpeople c
                        LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON c.fk_soc = s.rowid
                        WHERE c.entity IN (" . getEntity('contact') . ")
                        AND (c.firstname LIKE '%" . $query_escaped . "%' 
                             OR c.lastname LIKE '%" . $query_escaped . "%'
                             OR c.email LIKE '%" . $query_escaped . "%'
                             OR s.nom LIKE '%" . $query_escaped . "%')
                        ORDER BY c.lastname, c.firstname ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'projet':
                $sql = "SELECT p.rowid as id, p.title as label, p.ref, s.nom as societe_nom
                        FROM " . MAIN_DB_PREFIX . "projet p
                        LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON p.fk_soc = s.rowid
                        WHERE p.entity IN (" . getEntity('project') . ")
                        AND (p.title LIKE '%" . $query_escaped . "%' 
                             OR p.ref LIKE '%" . $query_escaped . "%'
                             OR s.nom LIKE '%" . $query_escaped . "%')
                        ORDER BY p.title ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'user':
                $sql = "SELECT rowid as id, CONCAT(login, ' - ', lastname, ' ', firstname) as label
                        FROM " . MAIN_DB_PREFIX . "user
                        WHERE login LIKE '%" . $query_escaped . "%' 
                           OR firstname LIKE '%" . $query_escaped . "%'
                           OR lastname LIKE '%" . $query_escaped . "%'
                        ORDER BY lastname ASC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'invoice':
                $sql = "SELECT rowid as id, CONCAT(ref, ' - ', IFNULL(label, '')) as label
                        FROM " . MAIN_DB_PREFIX . "facture
                        WHERE ref LIKE '%" . $query_escaped . "%' 
                           OR label LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC 
                        LIMIT " . (int)$limit;
                break;
                
            case 'commande':
                $sql = "SELECT rowid as id, CONCAT(ref, ' - ', IFNULL(note_private, '')) as label
                        FROM " . MAIN_DB_PREFIX . "commande
                        WHERE ref LIKE '%" . $query_escaped . "%' 
                           OR note_private LIKE '%" . $query_escaped . "%'
                        ORDER BY date_creation DESC 
                        LIMIT " . (int)$limit;
                break;
            
            case 'usergroup':
                $sql = "SELECT rowid as id, nom as label
                        FROM " . MAIN_DB_PREFIX . "usergroup
                        WHERE nom LIKE '%" . $query_escaped . "%'
                        ORDER BY rowid DESC
                        LIMIT " . (int)$limit;
                break;
            
            case 'adherent':
                $sql = "SELECT rowid as id, CONCAT(lastname, ' ', firstname) as label
                        FROM " . MAIN_DB_PREFIX . "adherent
                        WHERE firstname LIKE '%" . $query_escaped . "%'
                           OR lastname LIKE '%" . $query_escaped . "%'
                        ORDER BY rowid DESC
                        LIMIT " . (int)$limit;
                break;
                
            case 'holiday':
                $sql = "SELECT rowid as id, motif as label
                        FROM " . MAIN_DB_PREFIX . "holiday
                        WHERE motif LIKE '%" . $query_escaped . "%'
                        ORDER BY rowid DESC
                        LIMIT " . (int)$limit;
                break;

            case 'expensereport':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "expensereport
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY date_create DESC
                        LIMIT " . (int)$limit;
                break;

            case 'propal':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "propal
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'contract':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "contrat
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'ticket':
                $sql = "SELECT rowid as id, subject as label
                        FROM " . MAIN_DB_PREFIX . "ticket
                        WHERE subject LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'fichinter':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "fichinter
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'supplier_proposal':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "supplier_proposal
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;
            
            case 'supplier_order':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "commande_fournisseur
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY date_creation DESC
                        LIMIT " . (int)$limit;
                break;

            case 'supplier_invoice':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "facture_fourn
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'reception':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "reception
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY date_creation DESC
                        LIMIT " . (int)$limit;
                break;

            case 'salary':
                $sql = "SELECT rowid as id, label as label
                        FROM " . MAIN_DB_PREFIX . "salary
                        WHERE label LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'loan':
                $sql = "SELECT rowid as id, label as label
                        FROM " . MAIN_DB_PREFIX . "loan
                        WHERE label LIKE '%" . $query_escaped . "%'
                        ORDER BY dateo DESC
                        LIMIT " . (int)$limit;
                break;

            case 'don':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "don
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY datec DESC
                        LIMIT " . (int)$limit;
                break;

            case 'event':
                $sql = "SELECT id as id, label
                        FROM " . MAIN_DB_PREFIX . "actioncomm
                        WHERE label LIKE '%" . $query_escaped . "%'
                        ORDER BY datep DESC
                        LIMIT " . (int)$limit;
                break;

            case 'accounting':
                $sql = "SELECT rowid as id, piece as label
                        FROM " . MAIN_DB_PREFIX . "accounting_bookkeeping
                        WHERE piece LIKE '%" . $query_escaped . "%'
                        ORDER BY date_doc DESC
                        LIMIT " . (int)$limit;
                break;

            case 'expedition':
                $sql = "SELECT rowid as id, ref as label
                        FROM " . MAIN_DB_PREFIX . "expedition
                        WHERE ref LIKE '%" . $query_escaped . "%'
                        ORDER BY date_creation DESC
                        LIMIT " . (int)$limit;
                break;

            case 'affaire':
                $sql = "SELECT p.rowid as id, p.title as label
                        FROM " . MAIN_DB_PREFIX . "projet p
                        INNER JOIN " . MAIN_DB_PREFIX . "projet_extrafields pe ON pe.fk_object = p.rowid
                        WHERE p.title LIKE '%" . $query_escaped . "%'
                        ORDER BY p.datec DESC
                        LIMIT " . (int)$limit;
                break;
                
            default:
                throw new Exception('Type d\'entité non supporté: ' . $type);
        }
        
        $resql = $db->query($sql);
        
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $result = [
                    'id' => $obj->id,
                    'label' => $obj->label,
                    'type' => $type
                ];
                
                // Ajouter des informations contextuelles
                switch ($type) {
                    case 'thirdparty':
                        if (!empty($obj->code_client)) {
                            $result['label'] .= ' (' . $obj->code_client . ')';
                        }
                        if (!empty($obj->email)) {
                            $result['email'] = $obj->email;
                        }
                        break;
                        
                    case 'contact':
                        if (!empty($obj->email)) {
                            $result['email'] = $obj->email;
                        }
                        if (!empty($obj->societe_nom)) {
                            $result['label'] .= ' - ' . $obj->societe_nom;
                        }
                        break;
                        
                    case 'projet':
                        if (!empty($obj->ref)) {
                            $result['label'] = $obj->ref . ' - ' . $result['label'];
                        }
                        if (!empty($obj->societe_nom)) {
                            $result['societe'] = $obj->societe_nom;
                        }
                        break;
                }
                
                $results[] = $result;
            }
            $db->free($resql);
        } else {
            throw new Exception('Erreur SQL: ' . $db->lasterror());
        }
        
    } catch (Exception $e) {
        error_log('Erreur recherche entités: ' . $e->getMessage());
        throw $e;
    }
    
    return $results;
}
?>