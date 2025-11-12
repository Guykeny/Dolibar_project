<?php
require '../../main.inc.php';

global $user;

// Vérifier les droits en début de fichier
if (!$user->hasRight('roundcubemodule', 'mailtab', 'read')) {
    accessforbidden("Vous n'avez pas les droits pour consulter cet onglet");
}

// Récupération des paramètres - Compatible avec les deux modes d'appel
$socid = GETPOST('socid', 'int');           // Mode tiers/contact
$contactid = GETPOST('contactid', 'int');   // Mode contact
$id = GETPOST('id', 'int');                 // Mode objet générique
$module_type = GETPOST('module', 'alpha');  // Type d'objet pour le mode générique

// Paramètres de recherche et pagination
$search_type = GETPOST('search_type', 'alpha') ? GETPOST('search_type', 'alpha') : 'subject';
$search_value = GETPOST('search_value', 'alpha');
$search_date = GETPOST('search_date', 'alpha');
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : 25;
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 1;
if ($page < 1) $page = 1;

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    // Construire l'URL de redirection avec seulement les paramètres de base
    $url = $_SERVER['PHP_SELF'] . '?';
    
    if ($socid > 0) {
        $url .= 'socid=' . $socid;
    } elseif ($contactid > 0) {
        $url .= 'contactid=' . $contactid;
    } elseif ($id > 0 && !empty($module_type)) {
        $url .= 'id=' . $id . '&module=' . $module_type;
    }
    
    // Redirection pour nettoyer tous les paramètres de recherche
    header('Location: ' . $url);
    exit;
}

// Déterminer le type d'objet et l'ID
$object_id = 0;
$object_type = '';

if ($socid > 0) {
    $object_id = $socid;
    $object_type = 'societe';
} elseif ($contactid > 0) {
    $object_id = $contactid;
    $object_type = 'contact';
} elseif ($id > 0 && !empty($module_type)) {
    $object_id = $id;
    $object_type = $module_type;
} else {
    $langs->load("errors");
    dol_print_error($db, $langs->trans("ErrorRecordNotFound"));
    exit;
}

// Configuration des objets supportés
$object_config = array(
    'societe' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php',
        'class_name' => 'Societe',
        'head_function' => 'societe_prepare_head',
        'trans_key' => 'ThirdParty',
        'title_field' => 'nom',
        'use_email_field' => true
    ),
    'thirdparty' => array( // Alias pour societe
        'class_file' => DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php',
        'class_name' => 'Societe',
        'head_function' => 'societe_prepare_head',
        'trans_key' => 'ThirdParty',
        'title_field' => 'nom',
        'use_email_field' => true
    ),
    'contact' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php',
        'class_name' => 'Contact',
        'head_function' => 'contact_prepare_head',
        'trans_key' => 'Contact',
        'title_field' => 'firstname',
        'use_email_field' => true
    ),
    'projet' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/projet/class/project.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php',
        'class_name' => 'Project',
        'head_function' => 'project_prepare_head',
        'trans_key' => 'Project',
        'title_field' => 'title',
        'use_email_field' => false
    ),
    'invoice' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php',
        'class_name' => 'Facture',
        'head_function' => 'facture_prepare_head',
        'trans_key' => 'Invoice',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'order' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php',
        'class_name' => 'Commande',
        'head_function' => 'commande_prepare_head',
        'trans_key' => 'Order',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'propal' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php',
        'class_name' => 'Propal',
        'head_function' => 'propal_prepare_head',
        'trans_key' => 'CommercialProposal',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'contract' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php',
        'class_name' => 'Contrat',
        'head_function' => 'contrat_prepare_head',
        'trans_key' => 'Contract',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'ticket' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/ticket.lib.php',
        'class_name' => 'Ticket',
        'head_function' => 'ticket_prepare_head',
        'trans_key' => 'Ticket',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'user' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/user/class/user.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php',
        'class_name' => 'User',
        'head_function' => 'user_prepare_head',
        'trans_key' => 'User',
        'title_field' => 'login',
        'use_email_field' => true
    ),
    'expedition' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/expedition/lib/expedition.lib.php',
        'class_name' => 'Expedition',
        'head_function' => 'expedition_prepare_head',
        'trans_key' => 'Shipment',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'fichinter' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/fichinter/lib/fichinter.lib.php',
        'class_name' => 'Fichinter',
        'head_function' => 'fichinter_prepare_head',
        'trans_key' => 'Intervention',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'usergroup' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php',
        'class_name' => 'UserGroup',
        'head_function' => 'group_prepare_head',
        'trans_key' => 'Group',
        'title_field' => 'nom',
        'use_email_field' => false
    ),
    'partnership' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/partnership/class/partnership.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/partnership/lib/partnership.lib.php',
        'class_name' => 'Partnership',
        'head_function' => 'partnership_prepare_head',
        'trans_key' => 'Partnership',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'supplier_proposal' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/supplier_proposal.lib.php',
        'class_name' => 'SupplierProposal',
        'head_function' => 'supplier_proposal_prepare_head',
        'trans_key' => 'SupplierProposal',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'supplier_order' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php',
        'class_name' => 'CommandeFournisseur',
        'head_function' => 'ordersupplier_prepare_head',
        'trans_key' => 'SupplierOrder',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'supplier_invoice' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php',
        'class_name' => 'FactureFournisseur',
        'head_function' => 'facturesupplier_prepare_head',
        'trans_key' => 'SupplierInvoice',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'reception' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/reception/lib/reception.lib.php',
        'class_name' => 'Reception',
        'head_function' => 'reception_prepare_head',
        'trans_key' => 'Reception',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'salary' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/salaries/lib/salary.lib.php',
        'class_name' => 'Salary',
        'head_function' => 'salary_prepare_head',
        'trans_key' => 'Salary',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'loan' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/loan/class/loan.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/loan/lib/loan.lib.php',
        'class_name' => 'Loan',
        'head_function' => 'loan_prepare_head',
        'trans_key' => 'Loan',
        'title_field' => 'label',
        'use_email_field' => false
    ),
    'don' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/don/class/don.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/don/lib/don.lib.php',
        'class_name' => 'Don',
        'head_function' => 'don_prepare_head',
        'trans_key' => 'Donation',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'holiday' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/holiday/lib/holiday.lib.php',
        'class_name' => 'Holiday',
        'head_function' => 'holiday_prepare_head',
        'trans_key' => 'Holiday',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'expensereport' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/expensereport/lib/expensereport.lib.php',
        'class_name' => 'ExpenseReport',
        'head_function' => 'expensereport_prepare_head',
        'trans_key' => 'ExpenseReport',
        'title_field' => 'ref',
        'use_email_field' => false
    ),
    'adherent' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/adherents/lib/member.lib.php',
        'class_name' => 'Adherent',
        'head_function' => 'member_prepare_head',
        'trans_key' => 'Member',
        'title_field' => 'login',
        'use_email_field' => true
    ),
    'event' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/comm/action/lib/actioncomm.lib.php',
        'class_name' => 'ActionComm',
        'head_function' => 'actions_prepare_head',
        'trans_key' => 'Event',
        'title_field' => 'label',
        'use_email_field' => false
    ),
    'accounting' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/accountancy/lib/accountingaccount.lib.php',
        'class_name' => 'AccountingAccount',
        'head_function' => 'accountingaccount_prepare_head',
        'trans_key' => 'AccountingAccount',
        'title_field' => 'account_number',
        'use_email_field' => false
    ),
    'affaire' => array(
        'class_file' => DOL_DOCUMENT_ROOT.'/custom/affaire/class/affaire.class.php',
        'lib_file' => DOL_DOCUMENT_ROOT.'/custom/affaire/lib/affaire.lib.php',
        'class_name' => 'Affaire',
        'head_function' => 'affaire_prepare_head',
        'trans_key' => 'Affaire',
        'title_field' => 'ref',
        'use_email_field' => false
    )
);

// Vérifier si le type d'objet est supporté
if (!isset($object_config[$object_type])) {
    dol_print_error('', "Type d'objet non supporté : " . $object_type);
    exit;
}

$config = $object_config[$object_type];

// Inclure les fichiers nécessaires
require_once $config['class_file'];
if (!empty($config['lib_file'])) {
    require_once $config['lib_file'];
}

// Créer et charger l'objet
$object = new $config['class_name']($db);
if ($object->fetch($object_id) <= 0) {
    $langs->load("errors");
    dol_print_error($db, $langs->trans("ErrorRecordNotFound"));
    exit;
}

// Récupérer l'email si l'objet en a un
$email = '';
if ($config['use_email_field'] && !empty($object->email)) {
    $email = $object->email;
}

// Charger les langues
$langs->load("companies");
$langs->load("mails");

// Titre de la page
$page_title = 'Mails - ' . (isset($object->{$config['title_field']}) ? $object->{$config['title_field']} : $object->ref);
llxHeader('', $page_title);

// Affichage de l'entête avec onglets
$head = $config['head_function']($object);
print dol_get_fiche_head($head, 'mailtab', $langs->trans($config['trans_key']), -1, strtolower($object_type));

// Bandeau de l'objet
$linkback = '';
if ($object_type === 'societe' || $object_type === 'thirdparty') {
    $linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
}
dol_banner_tab($object, ($object_type === 'societe' || $object_type === 'thirdparty') ? 'socid' : 'rowid', $linkback, ($user->socid ? 0 : 1), 'rowid', $config['title_field']);

?>

<style>
    .fichecenter { margin: 10px 0; }
    .mail-tabs {
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
    }
    
    .mail-tab-buttons {
        display: flex;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    
    .mail-tab-button {
        background: #f8f8f8;
        border: 1px solid #ddd;
        border-bottom: none;
        padding: 10px 20px;
        cursor: pointer;
        margin-right: 2px;
        border-radius: 5px 5px 0 0;
        color: #333;
        text-decoration: none;
    }
    
    .mail-tab-button:hover {
        background: #e8e8e8;
    }
    
    .mail-tab-button.active {
        background: #fff;
        border-bottom: 1px solid #fff;
        margin-bottom: -1px;
        font-weight: bold;
        color: #000;
    }
    
    .mail-tab-content {
        display: none;
        padding: 0;
    }
    
    .mail-tab-content.active {
        display: block;
    }
    
    .mail-count {
        background: #007cba;
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 11px;
        margin-left: 5px;
    }
    
    .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 20px;
    }
    
    .sortable-header:hover {
        background-color: #f0f0f0;
    }
    
    .sort-arrow {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.3;
    }
    
    .sortable-header.asc .sort-arrow:after {
        content: ' ▲';
        opacity: 1;
    }
    
    .sortable-header.desc .sort-arrow:after {
        content: ' ▼';
        opacity: 1;
    }
    
    .sortable-header:not(.asc):not(.desc) .sort-arrow:after {
        content: ' ▲▼';
    }
    
    .attachment-icon {
        color: #666;
        margin-left: 5px;
        font-size: 12px;
    }
    
    .attachment-count {
        background: #28a745;
        color: white;
        border-radius: 8px;
        padding: 1px 4px;
        font-size: 10px;
        margin-left: 3px;
    }
    
    .container-flex { display: flex; gap: 20px; margin-top: 20px; }
    #email_list_table_container { flex: 1; min-width: 40%; }
    #mail_content_display { flex: 1; border: 1px solid #ddd; padding: 15px; background: #f8f8f8; display: none; }
    .liste { width: 100%; }
    .mail-subject-link { color: #333; text-decoration: none; }
    .mail-subject-link:hover { text-decoration: underline; }
    .close-email { float: right; cursor: pointer; color: #666; }
    .mail-displayed #email_list_table_container { max-width: 40%; }
    .mail-displayed #mail_content_display { display: block; }
    .border { border: 1px solid #ccc; }
    .centpercent { width: 100%; }
    .tableforfield { border-collapse: collapse; }
    .titlefield { background-color: #f0f0f0; padding: 5px; font-weight: bold; }
    .error { color: red; }
    .valignmiddle { vertical-align: middle; }
    
    /* Styles pour le formulaire de recherche */
    .liste_titre_filter {
        background-color: #f0f0f0;
    }
    .liste_titre_filter td {
        padding: 8px;
    }
    .liste_titre_filter input, .liste_titre_filter select {
        margin-right: 5px;
    }
    .search-form-table {
        margin-bottom: 20px;
    }
    
    /* Styles pour la pagination */
    .pagination-controls {
        margin-top: 15px;
        text-align: center;
        padding: 10px 0;
    }
    
    .pagination-btn {
        padding: 8px 15px;
        margin: 0 5px;
        text-decoration: none;
    }
    
    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
</style>

<div class="fichecenter">
    <div class="underbanner clearboth"></div>
    
    <?php
    // Affichage des informations spécifiques selon le type d'objet
    echo '<table class="border centpercent tableforfield">';
    
    if ($object_type === 'societe' || $object_type === 'thirdparty') {
        echo '<tr><td class="titlefield">'.$langs->trans('NatureOfThirdParty').'</td><td>'.$object->getTypeUrl(1).'</td></tr>';
        
        if (getDolGlobalString('SOCIETE_USEPREFIX')) {
            echo '<tr><td class="titlefield">'.$langs->trans('Prefix').'</td><td colspan="3">'.$object->prefix_comm.'</td></tr>';
        }
        
        if ($object->client) {
            echo '<tr><td class="titlefield">'.$langs->trans('CustomerCode').'</td>';
            echo '<td colspan="3">'.showValueWithClipboardCPButton(dol_escape_htmltag($object->code_client));
            $tmpcheck = $object->check_codeclient();
            if ($tmpcheck != 0 && $tmpcheck != -5) {
                echo ' <span class="error">('.$langs->trans("WrongCustomerCode").')</span>';
            }
            echo '</td></tr>';
        }
        
        if ($object->fournisseur) {
            echo '<tr><td class="titlefield">'.$langs->trans('SupplierCode').'</td>';
            echo '<td colspan="3">'.showValueWithClipboardCPButton(dol_escape_htmltag($object->code_fournisseur));
            $tmpcheck = $object->check_codefournisseur();
            if ($tmpcheck != 0 && $tmpcheck != -5) {
                echo ' <span class="error">('.$langs->trans("WrongSupplierCode").')</span>';
            }
            echo '</td></tr>';
        }
        
    } elseif ($object_type === 'contact') {
        echo '<tr><td class="titlefield">'.$langs->trans("Contact").'</td><td>'.dol_escape_htmltag($object->getFullName($langs)).'</td></tr>';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($db);
            if ($societe->fetch($object->socid) > 0) {
                echo '<tr><td class="titlefield">'.$langs->trans("ThirdParty").'</td><td>'.$societe->getNomUrl(1).'</td></tr>';
            }
        }
        
    } else {
        // Affichage générique pour les autres objets
        if (isset($object->ref)) {
            echo '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
        }
        if (isset($object->title)) {
            echo '<tr><td class="titlefield">'.$langs->trans("Title").'</td><td>'.dol_escape_htmltag($object->title).'</td></tr>';
        }
        if (method_exists($object, 'getLibStatut')) {
            echo '<tr><td class="titlefield">'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(3).'</td></tr>';
        }
        if (isset($object->total_ttc)) {
            echo '<tr><td class="titlefield">'.$langs->trans("Total").'</td><td>'.price($object->total_ttc).'</td></tr>';
        }
        
        // Afficher le tiers lié si disponible
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($db);
            if ($societe->fetch($object->socid) > 0) {
                echo '<tr><td class="titlefield">'.$langs->trans("ThirdParty").'</td><td>'.$societe->getNomUrl(1).'</td></tr>';
            }
        }
    }
    
    echo '</table>';
    ?>
</div>

<?php
$display_title = 'Mails ';
if ($object_type === 'societe' || $object_type === 'thirdparty') {
    $display_title .= 'du tiers : '.dol_escape_htmltag($object->name);
} elseif ($object_type === 'contact') {
    $display_title .= 'du contact : '.dol_escape_htmltag($object->getFullName($langs));
} else {
    $display_title .= 'liés à : '.dol_escape_htmltag($object->{$config['title_field']} ?? $object->ref ?? $object->id);
}

print load_fiche_titre($display_title);

// Fonction pour exécuter les requêtes et compter les mails avec pagination
function getMailsData($db, $object_type, $object_id, $direction = null, $search_type = '', $search_value = '', $search_date = '', $search_date_start = '', $search_date_end = '', $limit = 25, $page = 1) {
    $where_direction = "";
    if ($direction) {
        $where_direction = " AND m.direction = '".$db->escape($direction)."'";
    }
    
    // Ajouter les conditions de recherche
    $where_search = "";
    if ($search_value) {
        // Nettoyer la valeur de recherche
        $search_value = trim($search_value);
        
        switch($search_type) {
            case 'subject':
                $where_search .= " AND m.subject LIKE '%".$db->escape($search_value)."%'";
                break;
                
            case 'email':
                // Recherche uniquement dans from_email car to_email n'existe pas
                $where_search .= " AND m.from_email LIKE '%".$db->escape($search_value)."%'";
                break;
                
            case 'direction':
                // Pour direction, on accepte 'sent', 'envoyé', 'received', 'reçu'
                $search_direction = strtolower($search_value);
                if (strpos($search_direction, 'env') !== false || $search_direction == 'sent') {
                    $where_search .= " AND m.direction = 'sent'";
                } elseif (strpos($search_direction, 'reç') !== false || strpos($search_direction, 'rec') !== false || $search_direction == 'received') {
                    $where_search .= " AND m.direction = 'received'";
                }
                break;
                
            case 'content':
                $where_search .= " AND (m.subject LIKE '%".$db->escape($search_value)."%'";
                $where_search .= " OR m.file_path LIKE '%".$db->escape($search_value)."%')";
                break;
        }
    }
    
    // Gestion de la recherche par date
    if ($search_date) {
        // Recherche sur la date exacte (toute la journée) - ancienne méthode conservée
        $where_search .= " AND DATE(m.date_received) = '".$db->escape($search_date)."'";
    } elseif ($search_date_start || $search_date_end) {
        // Recherche par intervalle de dates
        if ($search_date_start && $search_date_end) {
            // Les deux dates sont définies
            $where_search .= " AND DATE(m.date_received) BETWEEN '".$db->escape($search_date_start)."' AND '".$db->escape($search_date_end)."'";
        } elseif ($search_date_start) {
            // Seulement date de début
            $where_search .= " AND DATE(m.date_received) >= '".$db->escape($search_date_start)."'";
        } elseif ($search_date_end) {
            // Seulement date de fin
            $where_search .= " AND DATE(m.date_received) <= '".$db->escape($search_date_end)."'";
        }
    }
    
    // Calculer l'offset pour la pagination
    $offset = ($page - 1) * $limit;
    
    // D'abord, compter le nombre total d'enregistrements
    $sql_count = "SELECT COUNT(DISTINCT m.rowid) as total";
    $sql_count .= " FROM ".MAIN_DB_PREFIX."mailboxmodule_mail m";
    $sql_count .= " INNER JOIN ".MAIN_DB_PREFIX."mailboxmodule_mail_links l ON l.fk_mail = m.rowid";
    $sql_count .= " WHERE l.target_type = '".$db->escape($object_type)."'";
    $sql_count .= " AND l.target_id = ".((int)$object_id);
    $sql_count .= $where_direction;
    $sql_count .= $where_search;
    
    $result_count = $db->query($sql_count);
    $total_count = 0;
    if ($result_count) {
        $obj_count = $db->fetch_object($result_count);
        $total_count = $obj_count->total;
        $db->free($result_count);
    }
    
    // Construction de la requête SQL avec pagination
    $sql = "SELECT m.rowid, m.subject, m.from_email, m.date_received, m.direction,";
    $sql .= " m.message_id, m.file_path, m.imap_mailbox, m.imap_uid,";
    $sql .= " COUNT(DISTINCT a.rowid) as attachment_count";
    $sql .= " FROM ".MAIN_DB_PREFIX."mailboxmodule_mail m";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."mailboxmodule_mail_links l ON l.fk_mail = m.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."mailboxmodule_attachment a ON a.fk_mail = m.rowid";
    $sql .= " WHERE l.target_type = '".$db->escape($object_type)."'";
    $sql .= " AND l.target_id = ".((int)$object_id);
    $sql .= $where_direction;
    $sql .= $where_search;
    $sql .= " GROUP BY m.rowid, m.subject, m.from_email, m.date_received, m.direction, m.message_id, m.file_path, m.imap_mailbox, m.imap_uid";
    $sql .= " ORDER BY m.date_received DESC";
    $sql .= " LIMIT ".((int)$limit)." OFFSET ".((int)$offset);
    
    // Debug mode : afficher la requête si nécessaire
    if (GETPOST('debug', 'int')) {
        echo '<!-- SQL Query: '.htmlspecialchars($sql).' -->';
    }
    
    $result = $db->query($sql);
    $mails = array();
    
    if ($result) {
        while ($obj = $db->fetch_object($result)) {
            $mails[] = $obj;
        }
        $db->free($result);
    } else {
        // En cas d'erreur, afficher en mode debug
        if (GETPOST('debug', 'int')) {
            echo '<!-- SQL Error: '.$db->lasterror().' -->';
        }
        dol_syslog('MailTab SQL Error: '.$db->lasterror(), LOG_ERR);
    }
    
    return array(
        'mails' => $mails,
        'total' => $total_count,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total_count / $limit)
    );
}

// Fonction utilitaire pour récupérer le contenu du mail si stocké ailleurs
function getMailContent($db, $mail_id) {
    // Si le contenu est stocké dans le fichier indiqué par file_path
    $sql = "SELECT file_path FROM ".MAIN_DB_PREFIX."mailboxmodule_mail WHERE rowid = ".((int)$mail_id);
    $result = $db->query($sql);
    
    if ($result) {
        $obj = $db->fetch_object($result);
        if ($obj && $obj->file_path && file_exists($obj->file_path)) {
            // Lire le contenu du fichier .eml
            $content = file_get_contents($obj->file_path);
            // Vous pourriez avoir besoin de parser le contenu .eml ici
            return $content;
        }
    }
    
    return null;
}

// Fonction pour créer les boutons de pagination
function createPaginationButtons($current_page, $total_pages, $params = array()) {
    if ($total_pages <= 1) return '';
    
    $html = '<div class="pagination-controls">';
    
    // Bouton Précédent
    if ($current_page > 1) {
        $prev_params = array_merge($params, array('page' => $current_page - 1));
        $html .= '<a href="?'.http_build_query($prev_params).'" class="button pagination-btn">◄ Précédent</a>';
    } else {
        $html .= '<span class="button pagination-btn disabled">◄ Précédent</span>';
    }
    
    // Affichage de la page actuelle
    $html .= '<span style="margin: 0 15px; font-weight: bold;">Page '.$current_page.' / '.$total_pages.'</span>';
    
    // Bouton Suivant
    if ($current_page < $total_pages) {
        $next_params = array_merge($params, array('page' => $current_page + 1));
        $html .= '<a href="?'.http_build_query($next_params).'" class="button pagination-btn">Suivant ►</a>';
    } else {
        $html .= '<span class="button pagination-btn disabled">Suivant ►</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// Récupérer tous les mails avec pagination
$all_mails_data = getMailsData($db, $object_type, $object_id, null, $search_type, $search_value, $search_date, $search_date_start, $search_date_end, $limit, $page);
$received_mails_data = getMailsData($db, $object_type, $object_id, 'received', $search_type, $search_value, $search_date, $search_date_start, $search_date_end, $limit, $page);
$sent_mails_data = getMailsData($db, $object_type, $object_id, 'sent', $search_type, $search_value, $search_date, $search_date_start, $search_date_end, $limit, $page);

$all_mails = $all_mails_data['mails'];
$received_mails = $received_mails_data['mails'];
$sent_mails = $sent_mails_data['mails'];

$count_all = $all_mails_data['total'];
$count_received = $received_mails_data['total'];
$count_sent = $sent_mails_data['total'];

// Préparer les paramètres pour la pagination
$pagination_params = array(
    'socid' => $socid,
    'contactid' => $contactid,
    'id' => $id,
    'module' => $module_type,
    'search_type' => $search_type,
    'search_value' => $search_value,
    'search_date' => $search_date,
    'search_date_start' => $search_date_start,
    'search_date_end' => $search_date_end,
    'limit' => $limit
);

// Nettoyer les paramètres vides
$pagination_params = array_filter($pagination_params, function($value) {
    return $value !== '' && $value !== null && $value !== 0;
});
?>

<!-- Formulaire de recherche -->
<div class="fichecenter">
    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="socid" value="<?php echo $socid; ?>">
        <input type="hidden" name="contactid" value="<?php echo $contactid; ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="module" value="<?php echo $module_type; ?>">
        <input type="hidden" name="page" value="1">
        
        <table class="noborder search-form-table" width="100%">
            <tr class="liste_titre_filter">
                <td>
                    <select name="search_type" class="flat" style="width: 150px;">
                        <option value="subject" <?php echo (GETPOST('search_type', 'alpha') == 'subject' || empty(GETPOST('search_type', 'alpha'))) ? 'selected' : ''; ?>>Sujet</option>
                        <option value="email" <?php echo (GETPOST('search_type', 'alpha') == 'email') ? 'selected' : ''; ?>>Email</option>
                        <option value="direction" <?php echo (GETPOST('search_type', 'alpha') == 'direction') ? 'selected' : ''; ?>>Direction</option>
                        <option value="content" <?php echo (GETPOST('search_type', 'alpha') == 'content') ? 'selected' : ''; ?>>Contenu mail</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="search_value" value="<?php echo dol_escape_htmltag(GETPOST('search_value', 'alpha')); ?>" placeholder="Rechercher..." style="width: 250px;">
                </td>
                <td style="white-space: nowrap;">
                    <label>Date unique : </label>
                    <input type="date" name="search_date" value="<?php echo dol_escape_htmltag(GETPOST('search_date', 'alpha')); ?>" style="width: 140px;">
                </td>
                <td style="white-space: nowrap;">
                    <label>OU Période : </label>
                    <input type="date" name="search_date_start" value="<?php echo dol_escape_htmltag(GETPOST('search_date_start', 'alpha')); ?>" placeholder="Date début" style="width: 140px;">
                    <span> au </span>
                    <input type="date" name="search_date_end" value="<?php echo dol_escape_htmltag(GETPOST('search_date_end', 'alpha')); ?>" placeholder="Date fin" style="width: 140px;">
                </td>
                <td>
                    <label>Limite : </label>
                    <select name="limit" class="flat">
                        <option value="5" <?php echo ($limit == 5) ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo ($limit == 10) ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo ($limit == 25) ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100</option>
                    </select>
                </td>
                <td align="right">
                    <button type="submit" class="button"><?php echo $langs->trans("Search"); ?></button>
                    <button type="button" class="button" onclick="resetFilters()"><?php echo $langs->trans("RemoveFilter"); ?></button>
                </td>
            </tr>
        </table>
    </form>
</div>

<div class="fichecenter">
    <!-- Sous-onglets pour les mails -->
    <div class="mail-tabs">
        <ul class="mail-tab-buttons">
            <li><a href="#" class="mail-tab-button active" data-tab="all">Tous les mails <?php if ($count_all > 0) echo '<span class="mail-count">'.$count_all.'</span>'; ?></a></li>
            <li><a href="#" class="mail-tab-button" data-tab="received">Mails reçus <?php if ($count_received > 0) echo '<span class="mail-count">'.$count_received.'</span>'; ?></a></li>
            <li><a href="#" class="mail-tab-button" data-tab="sent">Mails envoyés <?php if ($count_sent > 0) echo '<span class="mail-count">'.$count_sent.'</span>'; ?></a></li>
        </ul>
    </div>

    <div class="container-flex" id="main_container">
        <div id="email_list_table_container">
            
            <!-- Onglet Tous les mails -->
            <div id="tab-all" class="mail-tab-content active">
                <?php
                if (count($all_mails) > 0) {
                    print '<table class="noborder" width="100%" id="email_list_table_all">';
                    print '<tr class="liste_titre">';
                    print '<th class="sortable-header" data-column="subject">Sujet <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="from_email">Email <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="date_received">Date <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="direction">Direction <span class="sort-arrow"></span></th>';
                    print '</tr>';
                    print '<tbody id="table_body_all">';

                    foreach ($all_mails as $mail) {
                        print '<tr id="mail_row_'.$mail->rowid.'" data-subject="'.dol_escape_htmltag($mail->subject).'" data-email="'.dol_escape_htmltag($mail->from_email).'" data-date="'.$mail->date_received.'" data-direction="'.$mail->direction.'">';
                        
                        // Colonne Sujet avec indicateur de pièces jointes
                        print '<td>';
                        print '<a href="#" class="mail-subject-link" data-mail-id="'.$mail->rowid.'">'.dol_escape_htmltag($mail->subject).'</a>';
                        if ($mail->attachment_count > 0) {
                            print '<span class="attachment-icon" title="'.$mail->attachment_count.' pièce(s) jointe(s)">📎</span>';
                            if ($mail->attachment_count > 1) {
                                print '<span class="attachment-count">'.$mail->attachment_count.'</span>';
                            }
                        }
                        print '</td>';
                        
                        print '<td>'.dol_escape_htmltag($mail->from_email).'</td>';
                        print '<td>'.dol_print_date($db->jdate($mail->date_received), 'dayhour').'</td>';
                        print '<td>'.($mail->direction === 'sent' ? $langs->trans("Sent") : $langs->trans("Received")).'</td>';
                        print '</tr>';
                    }
                    print '</tbody>';
                    print '</table>';
                    
                    // Afficher la pagination pour cet onglet
                    echo createPaginationButtons($page, $all_mails_data['total_pages'], $pagination_params);
                } else {
                    print '<p>Aucun mail trouvé</p>';
                }
                ?>
            </div>

            <!-- Onglet Mails reçus -->
            <div id="tab-received" class="mail-tab-content">
                <?php
                if (count($received_mails) > 0) {
                    print '<table class="noborder" width="100%" id="email_list_table_received">';
                    print '<tr class="liste_titre">';
                    print '<th class="sortable-header" data-column="subject">Sujet <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="from_email">Expéditeur <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="date_received">Date <span class="sort-arrow"></span></th>';
                    print '</tr>';
                    print '<tbody id="table_body_received">';

                    foreach ($received_mails as $mail) {
                        print '<tr id="mail_row_'.$mail->rowid.'" data-subject="'.dol_escape_htmltag($mail->subject).'" data-email="'.dol_escape_htmltag($mail->from_email).'" data-date="'.$mail->date_received.'">';
                        
                        // Colonne Sujet avec indicateur de pièces jointes
                        print '<td>';
                        print '<a href="#" class="mail-subject-link" data-mail-id="'.$mail->rowid.'">'.dol_escape_htmltag($mail->subject).'</a>';
                        if ($mail->attachment_count > 0) {
                            print '<span class="attachment-icon" title="'.$mail->attachment_count.' pièce(s) jointe(s)">📎</span>';
                            if ($mail->attachment_count > 1) {
                                print '<span class="attachment-count">'.$mail->attachment_count.'</span>';
                            }
                        }
                        print '</td>';
                        
                        print '<td>'.dol_escape_htmltag($mail->from_email).'</td>';
                        print '<td>'.dol_print_date($db->jdate($mail->date_received), 'dayhour').'</td>';
                        print '</tr>';
                    }
                    print '</tbody>';
                    print '</table>';
                    
                    // Afficher la pagination pour cet onglet
                    echo createPaginationButtons($page, $received_mails_data['total_pages'], $pagination_params);
                } else {
                    print '<p>Aucun mail reçu trouvé</p>';
                }
                ?>
            </div>

            <!-- Onglet Mails envoyés -->
            <div id="tab-sent" class="mail-tab-content">
                <?php
                if (count($sent_mails) > 0) {
                    print '<table class="noborder" width="100%" id="email_list_table_sent">';
                    print '<tr class="liste_titre">';
                    print '<th class="sortable-header" data-column="subject">Sujet <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="from_email">Destinataire <span class="sort-arrow"></span></th>';
                    print '<th class="sortable-header" data-column="date_received">Date <span class="sort-arrow"></span></th>';
                    print '</tr>';
                    print '<tbody id="table_body_sent">';

                    foreach ($sent_mails as $mail) {
                        print '<tr id="mail_row_'.$mail->rowid.'" data-subject="'.dol_escape_htmltag($mail->subject).'" data-email="'.dol_escape_htmltag($mail->from_email).'" data-date="'.$mail->date_received.'">';
                        
                        // Colonne Sujet avec indicateur de pièces jointes
                        print '<td>';
                        print '<a href="#" class="mail-subject-link" data-mail-id="'.$mail->rowid.'">'.dol_escape_htmltag($mail->subject).'</a>';
                        if ($mail->attachment_count > 0) {
                            print '<span class="attachment-icon" title="'.$mail->attachment_count.' pièce(s) jointe(s)">📎</span>';
                            if ($mail->attachment_count > 1) {
                                print '<span class="attachment-count">'.$mail->attachment_count.'</span>';
                            }
                        }
                        print '</td>';
                        
                        print '<td>'.dol_escape_htmltag($mail->from_email).'</td>';
                        print '<td>'.dol_print_date($db->jdate($mail->date_received), 'dayhour').'</td>';
                        print '</tr>';
                    }
                    print '</tbody>';
                    print '</table>';
                    
                    // Afficher la pagination pour cet onglet
                    echo createPaginationButtons($page, $sent_mails_data['total_pages'], $pagination_params);
                } else {
                    print '<p>Aucun mail envoyé trouvé</p>';
                }
                ?>
            </div>
        </div>

        <div id="mail_content_display">
            <span class="close-email" id="close_email_display">&times;</span>
            <p>Sélectionnez un sujet d'e-mail pour afficher son contenu.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des sous-onglets
    const tabButtons = document.querySelectorAll('.mail-tab-button');
    const tabContents = document.querySelectorAll('.mail-tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetTab = this.getAttribute('data-tab');

            // Retirer active de tous
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Activer le bon onglet
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
            
            // Réinitialiser l'affichage du mail
            closeMail();
            
            // Réattacher les événements après changement d'onglet
            setTimeout(attachMailEvents, 100);
        });
    });

    // Fonction de tri des tableaux
    function sortTable(tableBodyId, column, order) {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            let aVal, bVal;
            
            if (column === 'date_received') {
                // Convertir les dates au format "2025-08-07 02:25:47" en timestamps
                const dateA = a.dataset.date;
                const dateB = b.dataset.date;
                
                // Créer des objets Date et obtenir les timestamps
                aVal = new Date(dateA).getTime();
                bVal = new Date(dateB).getTime();
                
                // Si la conversion échoue, utiliser 0
                if (isNaN(aVal)) aVal = 0;
                if (isNaN(bVal)) bVal = 0;
                
                // Comparaison numérique directe
                if (order === 'asc') {
                    return aVal - bVal;
                } else {
                    return bVal - aVal;
                }
            } else if (column === 'subject') {
                aVal = a.dataset.subject.toLowerCase();
                bVal = b.dataset.subject.toLowerCase();
            } else if (column === 'from_email') {
                aVal = a.dataset.email.toLowerCase();
                bVal = b.dataset.email.toLowerCase();
            } else if (column === 'direction') {
                aVal = a.dataset.direction;
                bVal = b.dataset.direction;
            }
            
            // Pour les autres colonnes (texte)
            if (column !== 'date_received') {
                if (aVal < bVal) return order === 'asc' ? -1 : 1;
                if (aVal > bVal) return order === 'asc' ? 1 : -1;
                return 0;
            }
        });
        
        // Réinsérer les lignes triées
        rows.forEach(row => tbody.appendChild(row));
    }

    // Initialiser les en-têtes triables
    function initSortableHeaders() {
        const sortableHeaders = document.querySelectorAll('.sortable-header');
        
        sortableHeaders.forEach(header => {
            // Supprimer les anciens événements
            const newHeader = header.cloneNode(true);
            header.parentNode.replaceChild(newHeader, header);
        });

        // Ajouter les nouveaux événements
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.column;
                const currentOrder = this.classList.contains('asc') ? 'asc' : 
                                   this.classList.contains('desc') ? 'desc' : '';
                
                // Déterminer le nouvel ordre
                let newOrder;
                if (currentOrder === '') newOrder = 'asc';
                else if (currentOrder === 'asc') newOrder = 'desc';
                else newOrder = 'asc';
                
                // Retirer les classes de tri de tous les en-têtes du même tableau
                const table = this.closest('table');
                table.querySelectorAll('.sortable-header').forEach(h => {
                    h.classList.remove('asc', 'desc');
                });
                
                // Ajouter la classe au header cliqué
                this.classList.add(newOrder);
                
                // Déterminer quel tableau trier
                let tableBodyId;
                if (table.id === 'email_list_table_all') {
                    tableBodyId = 'table_body_all';
                } else if (table.id === 'email_list_table_received') {
                    tableBodyId = 'table_body_received';
                } else if (table.id === 'email_list_table_sent') {
                    tableBodyId = 'table_body_sent';
                }
                
                // Effectuer le tri
                sortTable(tableBodyId, column, newOrder);
            });
        });
    }

    // Initialiser au chargement
    initSortableHeaders();

    // Gestion de l'affichage des mails
    function attachMailEvents() {
        const mailSubjectLinks = document.querySelectorAll('.mail-subject-link');
        const mailContentDisplay = document.getElementById('mail_content_display');
        const mainContainer = document.getElementById('main_container');

        mailSubjectLinks.forEach(link => {
            // Supprimer les anciens événements pour éviter les doublons
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);
        });

        document.querySelectorAll('.mail-subject-link').forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const mailId = this.dataset.mailId;

                document.querySelectorAll('tr[id^="mail_row_"]').forEach(row => {
                    row.style.backgroundColor = '';
                });
                
                // Chercher la ligne dans tous les tableaux
                const targetRow = document.getElementById('mail_row_'+mailId);
                if (targetRow) {
                    targetRow.style.backgroundColor = '#e0e0ff';
                }

                mailContentDisplay.innerHTML = '<p>Chargement de l\'e-mail...</p>';
                mainContainer.classList.add('mail-displayed');

                fetch('view_mail.php?id=' + mailId)
                    .then(response => {
                        if (!response.ok) throw new Error('Erreur réseau');
                        return response.text();
                    })
                    .then(html => {
                        mailContentDisplay.innerHTML = '<span class="close-email" id="close_email_display">&times;</span>' + html;
                        document.getElementById('close_email_display').addEventListener('click', closeMail);
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        mailContentDisplay.innerHTML = '<p style="color:red;">Erreur de chargement</p>';
                    });
            });
        });
    }

    function closeMail() {
        const mainContainer = document.getElementById('main_container');
        mainContainer.classList.remove('mail-displayed');
        document.querySelectorAll('tr[id^="mail_row_"]').forEach(row => {
            row.style.backgroundColor = '';
        });
    }

    // Attacher les événements aux mails
    attachMailEvents();

    // Événement pour fermer l'email
    const closeButton = document.getElementById('close_email_display');
    if (closeButton) {
        closeButton.addEventListener('click', closeMail);
    }
});

// Fonctions globales pour la prévisualisation des pièces jointes
function previewAttachment(attachmentId, contentType, filename) {
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('previewBody');
    const title = document.getElementById('previewTitle');

    // Mettre à jour le titre
    title.textContent = 'Aperçu : ' + filename;
    
    // Afficher un message de chargement
    body.innerHTML = '<p>Chargement...</p>';
    modal.style.display = 'block';

    // Construire l'URL pour l'iframe
    const iframeUrl = './preview_attachment.php?attachmentId=' + attachmentId + '&contentType=' + encodeURIComponent(contentType);
    
    // Créer un iframe qui pointe directement vers le script PHP
    const iframe = document.createElement('iframe');
    iframe.src = iframeUrl;
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = 'none';

    // Remplacer le contenu du modal par l'iframe
    body.innerHTML = '';
    body.appendChild(iframe);
}

function resetFilters() {
    document.querySelector('select[name="search_type"]').value = 'subject';
    document.querySelector('input[name="search_value"]').value = '';
    document.querySelector('input[name="search_date"]').value = '';
    document.querySelector('input[name="search_date_start"]').value = '';
    document.querySelector('input[name="search_date_end"]').value = '';
    document.querySelector('select[name="limit"]').value = '25';
    document.querySelector('input[name="page"]').value = '1';
    
    // Soumettre le formulaire
    document.querySelector('form').submit();
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
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (modal && event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
print dol_get_fiche_end();
llxFooter();
$db->close();
?>