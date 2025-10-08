<?php
/**
 * Pusher Configuration
 * Real-time messaging for Hostinger deployment
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Pusher\Pusher;

// Pusher credentials
define('PUSHER_APP_ID', '2060857');
define('PUSHER_KEY', '5f2e092f1e11a34b880f');
define('PUSHER_SECRET', 'f2403e9bcc17b2e517b4');
define('PUSHER_CLUSTER', 'ap1');

/**
 * Get Pusher instance
 * @return Pusher
 */
function getPusherInstance() {
    static $pusher = null;
    
    if ($pusher === null) {
        $pusher = new Pusher(
            PUSHER_KEY,
            PUSHER_SECRET,
            PUSHER_APP_ID,
            [
                'cluster' => PUSHER_CLUSTER,
                'useTLS' => true
            ]
        );
    }
    
    return $pusher;
}

/**
 * Trigger a Pusher event
 * @param string $channel Channel name
 * @param string $event Event name
 * @param array $data Event data
 * @return bool Success status
 */
function triggerPusherEvent($channel, $event, $data) {
    try {
        $pusher = getPusherInstance();
        $pusher->trigger($channel, $event, $data);
        return true;
    } catch (Exception $e) {
        error_log("Pusher Error: " . $e->getMessage());
        return false;
    }
}
?>
