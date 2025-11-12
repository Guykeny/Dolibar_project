<?php
/**
 * Plugin Roundcube simplifié pour envoyer SEULEMENT les pièces jointes à Dolibarr
 */

class dolibarr_mail_save extends rcube_plugin
{
    public $task = 'mail|compose|settings';
    private $rc;
    private $dolibarr_config;
    private $compose_id = null;
    
    function init()
    {
        $this->rc = rcmail::get_instance();
        
        if (version_compare(RCMAIL_VERSION, '1.0.0', '<')) {
            error_log('dolibarr_mail_save: Version de Roundcube non supportée');
            return;
        }
        
        if (!$this->load_dolibarr_config()) {
            error_log('dolibarr_mail_save: Configuration Dolibarr introuvable');
            return;
        }
        
        // Hooks simplifiés - juste pour capturer les PJ
        $this->add_hook('attachment_upload', array($this, 'track_attachment_upload'));
        $this->add_hook('message_sent', array($this, 'send_attachments_only'));
        
        if ($this->rc->task == 'compose') {
            $this->add_hook('render_page', array($this, 'inject_compose_js'));
        }
    }
    
    /**
     * JavaScript pour tracker les PJ côté client
     */
    function inject_compose_js($args)
    {
        if ($args['template'] == 'compose') {
            $script = <<<'JS'
<script>
window.dolibarr_attachments = [];

// Observer les PJ ajoutées
$(document).on('fileappended attachment_added', function(e, data) {
    if (data && data.name) {
        window.dolibarr_attachments.push({
            name: data.name,
            size: data.size || 0,
            id: data.id || null
        });
        console.log('PJ ajoutée:', data.name);
    }
});

// Scanner les PJ existantes au chargement
$(document).ready(function() {
    setTimeout(function() {
        $('.attachmentitem, .attachment').each(function() {
            const nameEl = $(this).find('.attachment-name, .filename').first();
            if (nameEl.length) {
                const name = nameEl.text() || nameEl.attr('title') || 'unknown';
                window.dolibarr_attachments.push({
                    name: name,
                    size: 0,
                    id: $(this).attr('id') || null
                });
            }
        });
        
        if (window.dolibarr_attachments.length > 0) {
            console.log('PJ existantes trouvées:', window.dolibarr_attachments.length);
        }
    }, 500);
});
</script>
JS;
            $args['content'] = str_replace('</body>', $script . '</body>', $args['content']);
        }
        return $args;
    }
    
    /**
     * Track les pièces jointes uploadées
     */
    function track_attachment_upload($args)
    {
        $compose_id = $args['id'] ?? $_REQUEST['_id'] ?? null;
        
        if ($compose_id) {
            if (!isset($_SESSION['dolibarr_attachments'])) {
                $_SESSION['dolibarr_attachments'] = [];
            }
            if (!isset($_SESSION['dolibarr_attachments'][$compose_id])) {
                $_SESSION['dolibarr_attachments'][$compose_id] = [];
            }
            
            $att_info = [
                'name' => $args['name'] ?? 'attachment.bin',
                'mimetype' => $args['mimetype'] ?? 'application/octet-stream',
                'size' => $args['size'] ?? 0,
                'path' => $args['path'] ?? null,
                'timestamp' => time()
            ];
            
            $_SESSION['dolibarr_attachments'][$compose_id][] = $att_info;
            $this->rc->write_log('dolibarr_mail_save', "PJ trackée: " . $att_info['name']);
        }
        
        return $args;
    }
    
    /**
     * Envoie SEULEMENT les pièces jointes après envoi réussi du mail
     */
    function send_attachments_only($args)
    {
        $this->rc->write_log('dolibarr_mail_save', "=== ENVOI PJ SEULEMENT ===");
        
        // Récupérer le compose_id
        $this->compose_id = $_REQUEST['_id'] ?? null;
        
        // Extraire les PJ
        $attachments = $this->extract_attachments_only();
        
        if (empty($attachments)) {
            $this->rc->write_log('dolibarr_mail_save', "Aucune PJ à envoyer");
            return $args;
        }
        
        $this->rc->write_log('dolibarr_mail_save', "Envoi de " . count($attachments) . " pièces jointes");
        
        // Préparer SEULEMENT les données des PJ
        $data = [
            'action' => 'attachments_only',
            'attachments' => $attachments,
            'metadata' => [
                'count' => count($attachments),
                'timestamp' => date('Y-m-d H:i:s'),
                'from' => $this->get_user_email()
            ]
        ];
        
        // AVANT d'envoyer à Dolibarr, stocker les PJ pour le GET JavaScript
        $_SESSION['dolibarr_mail_attachments'] = $attachments;
        $_SESSION['last_processed_attachments'] = $attachments;
        $this->rc->write_log('dolibarr_mail_save', "PJ stockées en session pour JavaScript");
        
        // Envoyer à Dolibarr
        $success = $this->send_attachments_to_dolibarr($data);
        
        if ($success) {
            $this->rc->write_log('dolibarr_mail_save', "✅ PJ envoyées avec succès");
        } else {
            $this->rc->write_log('dolibarr_mail_save', "❌ Erreur envoi PJ");
        }
        
        // Ne pas nettoyer tout de suite, garder pour JavaScript
        // $this->cleanup_attachments_session();
        
        return $args;
    }
    
    /**
     * Extrait SEULEMENT les pièces jointes (pas l'email complet)
     */
    private function extract_attachments_only()
    {
        $attachments = [];
        
        $this->rc->write_log('dolibarr_mail_save', "Extraction PJ pour compose_id: " . ($this->compose_id ?? 'null'));
        
        // Source 1: Notre tracking
        if ($this->compose_id && isset($_SESSION['dolibarr_attachments'][$this->compose_id])) {
            foreach ($_SESSION['dolibarr_attachments'][$this->compose_id] as $att) {
                if (isset($att['path']) && file_exists($att['path'])) {
                    $content = file_get_contents($att['path']);
                    if ($content !== false) {
                        $attachments[] = [
                            'name' => $att['name'],
                            'mimetype' => $att['mimetype'],
                            'size' => strlen($content),
                            'content' => base64_encode($content)
                        ];
                        $this->rc->write_log('dolibarr_mail_save', "PJ extraite: " . $att['name']);
                    }
                }
            }
        }
        
        // Source 2: Sessions Roundcube (fallback)
        if (empty($attachments)) {
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'compose_data_') === 0 && is_array($value) && isset($value['attachments'])) {
                    // Si on a le compose_id, vérifier la correspondance
                    if ($this->compose_id && strpos($key, $this->compose_id) === false) {
                        continue;
                    }
                    
                    foreach ($value['attachments'] as $att) {
                        $file_path = $att['path'] ?? $att['tmp_name'] ?? null;
                        if ($file_path && file_exists($file_path)) {
                            $content = file_get_contents($file_path);
                            if ($content !== false) {
                                $attachments[] = [
                                    'name' => $att['name'] ?? 'attachment.bin',
                                    'mimetype' => $att['mimetype'] ?? 'application/octet-stream',
                                    'size' => strlen($content),
                                    'content' => base64_encode($content)
                                ];
                                $this->rc->write_log('dolibarr_mail_save', "PJ extraite (fallback): " . ($att['name'] ?? 'unknown'));
                            }
                        }
                    }
                    
                    // Si on a trouvé des PJ, arrêter la recherche
                    if (!empty($attachments)) {
                        break;
                    }
                }
            }
        }
        
        return $attachments;
    }
    
    /**
     * Envoie SEULEMENT les pièces jointes à Dolibarr (pas l'email complet)
     */
    private function send_attachments_to_dolibarr($data)
    {
        $dolibarr_url = $this->get_dolibarr_url();
        if (!$dolibarr_url) {
            $this->rc->write_log('dolibarr_mail_save', "ERREUR: URL Dolibarr introuvable");
            return false;
        }
        
        // URL vers un script spécialisé pour recevoir SEULEMENT les PJ
        $save_url = $dolibarr_url . '/custom/roundcubemodule/scripts/save_attachments_only.php';
        
        $this->rc->write_log('dolibarr_mail_save', "Envoi PJ vers: $save_url");
        $this->rc->write_log('dolibarr_mail_save', "Nombre de PJ: " . count($data['attachments']));
        $this->rc->write_log('dolibarr_mail_save', "Taille données: " . strlen(json_encode($data)) . " bytes");
        
        $ch = curl_init($save_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Log détaillé pour debug
        $this->rc->write_log('dolibarr_mail_save', "cURL info: " . json_encode([
            'url' => $info['url'],
            'http_code' => $http_code,
            'total_time' => $info['total_time'],
            'size_download' => $info['size_download']
        ]));
        
        if ($error) {
            $this->rc->write_log('dolibarr_mail_save', "ERREUR cURL: $error");
            return false;
        }
        
        if ($http_code !== 200) {
            $this->rc->write_log('dolibarr_mail_save', "ERREUR HTTP: Code $http_code");
            $this->rc->write_log('dolibarr_mail_save', "Réponse brute: " . substr($result, 0, 500));
            return false;
        }
        
        // Vérifier si la réponse est du JSON valide
        if (empty($result)) {
            $this->rc->write_log('dolibarr_mail_save', "ERREUR: Réponse vide du serveur");
            return false;
        }
        
        $this->rc->write_log('dolibarr_mail_save', "Réponse reçue: " . substr($result, 0, 200));
        
        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rc->write_log('dolibarr_mail_save', "ERREUR JSON: " . json_last_error_msg());
            $this->rc->write_log('dolibarr_mail_save', "Réponse brute: " . $result);
            return false;
        }
        
        if ($response && $response['status'] === 'OK') {
            $this->rc->write_log('dolibarr_mail_save', "Succès envoi PJ: " . ($response['message'] ?? 'OK'));
            if (isset($response['files_processed'])) {
                $this->rc->write_log('dolibarr_mail_save', "Fichiers traités: " . $response['files_processed']);
            }
            return true;
        } else {
            $this->rc->write_log('dolibarr_mail_save', "Erreur réponse: " . json_encode($response));
            return false;
        }
    }
    
    /**
     * Nettoie les sessions des PJ après envoi
     */
    private function cleanup_attachments_session()
    {
        if ($this->compose_id) {
            unset($_SESSION['dolibarr_attachments'][$this->compose_id]);
        }
        
        $this->rc->write_log('dolibarr_mail_save', "Sessions PJ nettoyées");
    }
    
    /**
     * Récupère l'email de l'utilisateur
     */
    private function get_user_email()
    {
        $identity = $this->rc->user->get_identity();
        if ($identity && isset($identity['email'])) {
            return $identity['email'];
        }
        
        return $_SESSION['username'] ?? 'user@localhost';
    }
    
    /**
     * Charge la configuration Dolibarr
     */
    private function load_dolibarr_config()
    {
        if (isset($this->dolibarr_config) && !empty($this->dolibarr_config)) {
            return true;
        }
        
        $current_dir = __DIR__;
        $max_depth = 10;
        
        for ($i = 0; $i < $max_depth; $i++) {
            $potential_path = $current_dir . '/conf/conf.php';
            
            if (file_exists($potential_path)) {
                $config = (function () use ($potential_path) {
                    require $potential_path;
                    return get_defined_vars();
                })();
                
                $this->dolibarr_config = [
                    'document_root' => $config['dolibarr_main_document_root'] ?? '',
                ];
                
                return true;
            }
            
            $parent_dir = dirname($current_dir);
            if ($parent_dir === $current_dir) {
                break;
            }
            $current_dir = $parent_dir;
        }
        
        return false;
    }
    
    /**
     * Détermine l'URL de Dolibarr
     */
    private function get_dolibarr_url()
    {
        if (!isset($this->dolibarr_config)) {
            if (!$this->load_dolibarr_config()) {
                return false;
            }
        }
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        if (!empty($this->dolibarr_config['document_root'])) {
            $doc_root = $this->dolibarr_config['document_root'];
            $web_root = $_SERVER['DOCUMENT_ROOT'];
            $relative_path = str_replace($web_root, '', $doc_root);
            return $protocol . '://' . $host . $relative_path;
        }
        
        // Fallback
        $path = str_replace('/roundcube', '', dirname($_SERVER['SCRIPT_NAME']));
        return $protocol . '://' . $host . $path;
    }
}
?>