<?php

class ActionsRoundcubeModule 
{
    /**
     * Empêche l'affichage du bouton "Envoyez un mail" sur toutes les fiches
     */
   public function addHtmlHeader($parameters, &$object, &$action, $hookmanager) 
   {
       echo "<!-- Hook addHtmlHeader exécuté -->";
                
       $contexts = [
           'thirdpartycard',
           'projectcard', 
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
       ];
       
       foreach ($contexts as $context) {
           if (in_array($context, explode(':', $parameters['context']))) {
               echo "<!-- Dans $context -->";
               echo '<style>
                   a.butAction[href*="action=presend"] {
                       display: none !important;
                   }
               </style>';
               break;
           }
       }
                
       return 0;
   }

   public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
    global $langs, $conf, $user;

    if (!$user->hasRight('roundcubemodule', 'webmail', 'read')) {
        return 0;
    }
           
    if ($action === 'edit' || $action === 'presend') {
        return 0;
    }
                  
    if (in_array('thirdpartycard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = !empty($object->email) ? $object->email : '';
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=societe&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->nom);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('contactcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = !empty($object->email) ? $object->email : '';
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $contactName = (!empty($object->firstname) ? $object->firstname . ' ' : '') . (!empty($object->lastname) ? $object->lastname : '');
        $url .= '&preselect_type=contact&preselect_id=' . $object->id . '&preselect_name=' . urlencode($contactName);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
                  
    if (in_array('projectcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=projet&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->title);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('usercard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = !empty($object->email) ? $object->email : '';
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $userName = '';
        if (!empty($object->firstname) && !empty($object->lastname)) {
            $userName = $object->firstname . ' ' . $object->lastname;
        } elseif (!empty($object->lastname)) {
            $userName = $object->lastname;
        } elseif (!empty($object->login)) {
            $userName = $object->login;
        }
        $url .= '&preselect_type=user&preselect_id=' . $object->id . '&preselect_name=' . urlencode($userName);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('propalcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=propal&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('ordercard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=commande&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('expeditioncard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=expedition&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('contractcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=contract&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('fichintercard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=fichinter&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('ticketcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=ticket&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('invoicecard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = '';
        if (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $societe = new Societe($object->db);
            if ($societe->fetch($object->socid)) {
                $email = $societe->email;
            }
        }
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $url .= '&preselect_type=invoice&preselect_id=' . $object->id . '&preselect_name=' . urlencode($object->ref);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    if (in_array('adherentcard', explode(':', $parameters['context']))) {
        $langs->load("roundcubemodule@roundcubemodule");
        $email = !empty($object->email) ? $object->email : '';
        $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
        if (!empty($email)) {
            $url .= '&_to=' . urlencode($email);
        }
        $memberName = (!empty($object->firstname) ? $object->firstname . ' ' : '') . (!empty($object->lastname) ? $object->lastname : '');
        $url .= '&preselect_type=adherent&preselect_id=' . $object->id . '&preselect_name=' . urlencode($memberName);
        print '<a class="butAction" href="'.$url.'">';
        print $langs->trans("ENVOYER EMAIL");
        print '</a>';
    }
    
    // Pour les autres contextes sans email spécifique
    $simpleContexts = [
        'supplier_proposalcard' => 'supplier_proposal',
        'supplier_ordercard' => 'supplier_order', 
        'supplier_invoicecard' => 'supplier_invoice',
        'receptioncard' => 'reception',
        'salarycard' => 'salary',
        'loancard' => 'loan',
        'doncard' => 'don',
        'holidaycard' => 'holiday',
        'expensereportcard' => 'expensereport',
        'groupcard' => 'usergroup',
        'eventcard' => 'event',
        'accountingcard' => 'accounting'
    ];
    
    foreach ($simpleContexts as $context => $type) {
        if (in_array($context, explode(':', $parameters['context']))) {
            $langs->load("roundcubemodule@roundcubemodule");
            $url = DOL_URL_ROOT.'/custom/roundcubemodule/roundcube.php?_task=mail&_action=compose';
            $name = !empty($object->ref) ? $object->ref : (!empty($object->title) ? $object->title : $object->id);
            $url .= '&preselect_type=' . $type . '&preselect_id=' . $object->id . '&preselect_name=' . urlencode($name);
            print '<a class="butAction" href="'.$url.'">';
            print $langs->trans("ENVOYER EMAIL");
            print '</a>';
            break;
        }
    }
                  
    return 0;
    }
}