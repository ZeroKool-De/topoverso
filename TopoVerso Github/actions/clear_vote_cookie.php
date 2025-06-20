<?php
// topolinolib/actions/clear_vote_cookie.php

require_once '../config/config.php';

// Sicurezza: solo un admin loggato può eseguire questa azione
if (!isset($_SESSION['admin_user_id'])) {
    // Se non è un admin, non fare nulla e torna indietro
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
    exit;
}

$entity_type = $_GET['entity_type'] ?? null;
$entity_id = filter_input(INPUT_GET, 'entity_id', FILTER_VALIDATE_INT);
$redirect_url = $_GET['redirect_url'] ?? BASE_URL . 'index.php';

if ($entity_id && in_array($entity_type, ['comic', 'story'])) {
    $cookie_name = "voted_{$entity_type}_{$entity_id}";
    
    // Per eliminare un cookie, si imposta la sua data di scadenza nel passato
    setcookie($cookie_name, '', time() - 3600, "/");

    $_SESSION['message'] = "Cookie di voto resettato. Ora puoi votare di nuovo.";
    $_SESSION['message_type'] = 'info';
}

header('Location: ' . $redirect_url);
exit;