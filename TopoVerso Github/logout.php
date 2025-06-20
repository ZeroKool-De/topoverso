<?php
// topolinolib/logout.php (NUOVO FILE UNIFICATO)

// Assicura che la configurazione e la sessione siano attive
require_once 'config/config.php';

// Rimuovi tutte le variabili di sessione per un logout completo
$_SESSION = array();

// Se si utilizzano i cookie di sessione, è buona pratica eliminarli
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Infine, distruggi la sessione
session_destroy();

// Reindirizza alla pagina di login del frontend con un messaggio di successo
header('Location: ' . BASE_URL . 'login.php?message=Logout effettuato con successo.');
exit;
?>