<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';

if (isset($_GET['issue'])) {
    $issue_number_query = trim($_GET['issue']);

    if (empty($issue_number_query)) {
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        header('Location: ' . $redirect_url);
        exit;
    }

    // Cerca una corrispondenza esatta per il numero albo
    $stmt = $mysqli->prepare("SELECT comic_id, slug FROM comics WHERE issue_number = ? LIMIT 1");
    $stmt->bind_param("s", $issue_number_query);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $comic = $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();
        // Usa lo slug se disponibile per l'URL
        $redirect_url = !empty($comic['slug']) ? 'comic_detail.php?slug=' . urlencode($comic['slug']) : 'comic_detail.php?id=' . $comic['comic_id'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    } else {
        // Nessuna corrispondenza esatta, imposta i messaggi per la pagina di ricerca
        $stmt->close();
        $_SESSION['search_special_message'] = "L'albo Topolino #<strong>" . htmlspecialchars($issue_number_query) . "</strong> non è stato trovato nel catalogo.";
        $_SESSION['search_special_message_type'] = 'info';
        $_SESSION['search_term_for_request'] = $issue_number_query;

        $mysqli->close();
        // Reindirizza a search.php, precompilando il campo di ricerca
        header('Location: ' . BASE_URL . 'search.php?q=' . urlencode($issue_number_query) . '&submit_search=1');
        exit;
    }
} else {
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
    header('Location: ' . $redirect_url);
    exit;
}
?>