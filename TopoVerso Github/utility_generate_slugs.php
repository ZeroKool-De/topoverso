<?php
// File: topolinolib/utility_generate_slugs.php
// Questo script serve per generare gli "slug" per tutti gli albi e autori esistenti nel database.

require_once 'config/config.php'; 
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_user_id'])) { die('Accesso non autorizzato. Solo gli amministratori possono eseguire questo script.'); }


$page_title = "Utility - Genera Slugs";
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $updated_count = 0;
    $error_count = 0;
    $entity_type = '';
    $id_column = '';
    $text_column = '';
    $fallback_prefix = '';
    $issue_column = null;

    if (isset($_POST['start_update_comics'])) {
        $entity_type = 'comics';
        $id_column = 'comic_id';
        $text_column = 'title';
        $issue_column = 'issue_number';
        $fallback_prefix = 'topolino-';
    
    } elseif (isset($_POST['start_update_persons'])) {
        $entity_type = 'persons';
        $id_column = 'person_id';
        $text_column = 'name';
        $fallback_prefix = 'autore-';

    } elseif (isset($_POST['start_update_characters'])) {
        $entity_type = 'characters';
        $id_column = 'character_id';
        $text_column = 'name';
        $fallback_prefix = 'personaggio-';
    } elseif (isset($_POST['start_update_series'])) { // --- NUOVO BLOCCO ---
        $entity_type = 'story_series';
        $id_column = 'series_id';
        $text_column = 'title';
        $fallback_prefix = 'serie-';
    }

    if (!empty($entity_type)) {
        $sql_select = "SELECT {$id_column}, {$text_column}" . ($issue_column ? ", {$issue_column}" : "") . " FROM {$entity_type} WHERE slug IS NULL OR slug = ''";
        $result = $mysqli->query($sql_select);

        if ($result && $result->num_rows > 0) {
            $stmt_update = $mysqli->prepare("UPDATE {$entity_type} SET slug = ? WHERE {$id_column} = ?");

            while ($item = $result->fetch_assoc()) {
                $text_for_slug = !empty($item[$text_column]) ? $item[$text_column] : ($fallback_prefix . ($item[$issue_column] ?? $item[$id_column]));
                
                $new_slug = generate_slug($text_for_slug, $mysqli, $entity_type, 'slug', $item[$id_column], $id_column);
                
                $stmt_update->bind_param("si", $new_slug, $item[$id_column]);
                if ($stmt_update->execute()) {
                    $updated_count++;
                } else {
                    $error_count++;
                }
            }
            $stmt_update->close();

            if ($error_count > 0) {
                $message = "Processo per '{$entity_type}' completato con {$error_count} errori.";
                $message_type = 'error';
            } else {
                $message = "Aggiornamento completato con successo! <strong>{$updated_count}</strong> record di tipo '{$entity_type}' sono stati aggiornati con un nuovo slug.";
                $message_type = 'success';
            }
        } else {
            $message = "Nessun record di tipo '{$entity_type}' da aggiornare. Sembra che tutti abbiano già uno slug!";
            $message_type = 'info';
        }
        if($result) $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container page-content-container" style="margin-top: 50px;">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div style="padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
            <h3>Aggiorna Slug per Albi (Comics)</h3>
            <p>Genera URL SEO-friendly per tutti gli albi che non ne hanno uno.</p>
            <form action="utility_generate_slugs.php" method="POST">
                <button type="submit" name="start_update_comics" class="btn btn-primary" onclick="return confirm('Sei sicuro di voler aggiornare gli slug per tutti gli albi esistenti?');">
                    Avvia Aggiornamento Albi
                </button>
            </form>
        </div>

        <div style="padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
            <h3>Aggiorna Slug per Autori (Persons)</h3>
            <p>Genera URL SEO-friendly per tutti gli autori/persone che non ne hanno uno.</p>
            <form action="utility_generate_slugs.php" method="POST">
                <button type="submit" name="start_update_persons" class="btn btn-warning" onclick="return confirm('Sei sicuro di voler aggiornare gli slug per tutti gli autori esistenti?');">
                    Avvia Aggiornamento Autori
                </button>
            </form>
        </div>
        
        <div style="padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
            <h3>Aggiorna Slug per Personaggi (Characters)</h3>
            <p>Genera URL SEO-friendly per tutti i personaggi che non ne hanno uno.</p>
            <form action="utility_generate_slugs.php" method="POST">
                <button type="submit" name="start_update_characters" class="btn btn-success" onclick="return confirm('Sei sicuro di voler aggiornare gli slug per tutti i personaggi esistenti?');">
                    Avvia Aggiornamento Personaggi
                </button>
            </form>
        </div>

        <?php // --- BLOCCO AGGIUNTO --- ?>
        <div style="padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
            <h3>Aggiorna Slug per Serie</h3>
            <p>Genera URL SEO-friendly per tutte le serie che non ne hanno uno.</p>
            <form action="utility_generate_slugs.php" method="POST">
                <button type="submit" name="start_update_series" class="btn btn-info" onclick="return confirm('Sei sicuro di voler aggiornare gli slug per tutte le serie esistenti?');">
                    Avvia Aggiornamento Serie
                </button>
            </form>
        </div>
        
        <p style="margin-top: 30px;"><a href="<?php echo BASE_URL; ?>admin/" class="btn btn-secondary">&laquo; Torna al Pannello Admin</a></p>
    </div>
</body>
</html>
<?php
if(isset($mysqli)) $mysqli->close();
?>