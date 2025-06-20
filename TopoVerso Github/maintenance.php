<?php
// File: topolinolib/maintenance.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Se l'admin è loggato e arriva qui per errore, reindirizzalo all'admin panel
if (isset($_SESSION['admin_user_id'])) {
    // Assicurati che BASE_URL sia definita
    if (!defined('BASE_URL')) {
        // Prova a includere config.php se non è già stato fatto
        // Questo è un fallback, idealmente config.php è incluso prima
        $config_path_maintenance = __DIR__ . '/config/config.php';
        if (file_exists($config_path_maintenance)) {
            require_once $config_path_maintenance;
        } else {
            // Fallback estremo se config.php non è trovabile
            // Potrebbe essere necessario definire BASE_URL manualmente o gestire l'errore
            define('BASE_URL', './'); // O un percorso relativo corretto
        }
    }
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

http_response_code(503); // Service Unavailable
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sito in Manutenzione</title>
    <style>
        body { text-align: center; padding: 50px; font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; }
        h1 { font-size: 42px; color: #2c3e50; }
        p { font-size: 18px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        svg { width: 80px; height: 80px; margin-bottom: 20px; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <h1>Sito Temporaneamente in Manutenzione</h1>
        <p>Stiamo lavorando per migliorare la tua esperienza. Torneremo online il prima possibile!</p>
        <p>Ci scusiamo per il disagio.</p>
    </div>
</body>
</html>