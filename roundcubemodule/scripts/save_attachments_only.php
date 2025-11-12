<?php
// Headers CORS complets
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Gérer la pré-requête OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fichier de stockage
$storage_file = __DIR__ . '/attachments.json';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lire les données POST
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $attachments = [];
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $att) {
                $attachment_data = [
                    'name' => $att['name'] ?? 'fichier',
                    'size' => $att['size'] ?? 0,
                    'mimetype' => $att['mimetype'] ?? 'application/octet-stream'
                ];
                
                // INCLURE LE CONTENU BASE64 SI DISPONIBLE
                if (isset($att['content'])) {
                    $attachment_data['content'] = $att['content'];
                }
                
                $attachments[] = $attachment_data;
            }
        }
        
        // Sauvegarder
        file_put_contents($storage_file, json_encode($attachments, JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'status' => 'OK',
            'message' => 'POST réussi',
            'count' => count($attachments)
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lire les pièces jointes sauvegardées
        if (file_exists($storage_file)) {
            $content = file_get_contents($storage_file);
            $attachments = json_decode($content, true) ?: [];
            
            // Nettoyer après lecture
            unlink($storage_file);
            
            echo json_encode([
                'status' => 'OK',
                'attachments' => $attachments,
                'count' => count($attachments),
                'message' => 'GET réussi'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'status' => 'OK',
                'attachments' => [],
                'count' => 0,
                'message' => 'Aucune PJ disponible'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Erreur serveur'
    ], JSON_UNESCAPED_UNICODE);
}
?>