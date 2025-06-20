<?php
require_once 'config/config.php'; // Per avviare la sessione e usare BASE_URL

// Rimuovi solo le variabili di sessione specifiche dell'utente frontend
unset($_SESSION['user_id_frontend']);
unset($_SESSION['username_frontend']);

// Non distruggere l'intera sessione se vuoi mantenere altre info (es. carrello, preferenze tema, o sessione admin se sullo stesso browser)
// Se sei sicuro di voler terminare tutto per l'utente, puoi usare i comandi di distruzione sessione completa.
// Per ora, un unset mirato è più sicuro.

// session_destroy(); // Attenzione: questo distruggerebbe anche la sessione admin se attiva nello stesso browser.

// Reindirizza alla pagina di login con un messaggio
$_SESSION['logout_message_frontend'] = "Logout effettuato con successo.";
header('Location: ' . BASE_URL . 'login.php');
exit;
?>