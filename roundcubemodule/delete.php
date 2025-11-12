<?php
require '../../main.inc.php';

$id = GETPOST('id', 'int');

header('Content-Type: application/json');

if ($id > 0) {
    $db->begin();

    $sql = "DELETE FROM llx_mailboxmodule_mail WHERE rowid = ".((int) $id);
    $resql = $db->query($sql);

    if ($resql) {
        $db->commit();
       echo json_encode(['success' => true]);
        exit;
    } else {
        $db->rollback();
        $error_message = $db->lasterror;
        echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $error_message]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètre id manquant.']);
    exit;
}
?>