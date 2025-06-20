<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// NUOVA LOGICA DI CONTROLLO ACCESSO SPECIFICA PER ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) { // Solo i veri admin possono accedere qui
    // Se un contributore prova ad accedere, puoi reindirizzarlo alla dashboard admin
    // o a una pagina di "accesso negato".
    if (isset($_SESSION['user_id_frontend'])) { // È un utente frontend loggato
         $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
         $_SESSION['admin_action_message_type'] = 'error';
         header('Location: ' . BASE_URL . 'admin/index.php'); // Reindirizza alla dashboard admin (che mostrerà solo i link permessi)
         exit;
    }
    header('Location: ' . BASE_URL . 'admin/login.php'); // Non loggato, vai al login admin
    exit;
}

$page_title = "Backup Database";
$message = '';
$message_type = '';

$mysqldump_path = 'C:/wamp64/bin/mysql/mysql9.1.0/bin/mysqldump.exe';

if (isset($_POST['perform_backup'])) {
    $backup_file_name = 'backup_' . preg_replace('/[^a-zA-Z0-9_-]/', '', DB_NAME) . '_' . date("Y-m-d_H-i-s") . '.sql';
    
    // Costruisci il comando mysqldump
    $command_parts = [
        escapeshellcmd($mysqldump_path),
        '--host=' . escapeshellarg(DB_HOST),
        '--user=' . escapeshellarg(DB_USER)
    ];

    if (defined('DB_PASS') && DB_PASS !== '') {
        // Per mysqldump, è spesso più sicuro usare un file di opzioni o variabili d'ambiente per la password.
        // Tuttavia, per un comando diretto, questo è un modo comune, ma attenzione alla history della shell.
        // In alternativa, alcuni sistemi permettono di omettere --password= e mysqldump lo chiederà interattivamente
        // o lo leggerà da MYSQL_PWD, ma questo è più difficile da gestire in script PHP.
        // Un'opzione è creare un file .my.cnf temporaneo, ma aggiunge complessità.
        // Per ora, lo includiamo con escapeshellarg.
        $command_parts[] = '--password=' . escapeshellarg(DB_PASS);
    }
    
    $command_parts[] = escapeshellarg(DB_NAME);
    
    // Il comando completo. Redirigiamo l'output a un file.
    $command = implode(' ', $command_parts) . ' > ' . escapeshellarg($backup_file_name);

    $output_array = []; // Per catturare l'output di exec
    $return_var = null;

    // Esegui il comando usando exec()
    // Aggiungiamo '2>&1' per catturare anche gli errori standard (stderr) nell'output
    @exec($command . ' 2>&1', $output_array, $return_var);

    if ($return_var === 0 && file_exists($backup_file_name) && filesize($backup_file_name) > 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql'); // Tipo MIME più specifico
        header('Content-Disposition: attachment; filename="' . basename($backup_file_name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file_name));
        
        ob_clean(); 
        flush();    
        readfile($backup_file_name);
        
        unlink($backup_file_name);
        exit;

    } else {
        $message = "Errore durante la creazione del backup. Il file non è stato creato correttamente o è vuoto.";
        if (!empty($output_array)) {
            // $output_array è un array, lo convertiamo in stringa per la visualizzazione
            $message .= "<br>Output del comando: <pre>" . htmlspecialchars(implode("\n", $output_array)) . "</pre>";
        }
        if ($return_var !== 0 && $return_var !== null) {
            $message .= "<br>Codice di ritorno del comando: " . htmlspecialchars($return_var);
        }
        if (!function_exists('exec')) {
            $message .= "<br>La funzione 'exec' potrebbe essere disabilitata nella configurazione PHP.";
        }
        // Verifica se mysqldump è accessibile
        $mysqldump_check_output = [];
        $mysqldump_check_return = null;
        @exec(escapeshellcmd($mysqldump_path) . ' --version 2>&1', $mysqldump_check_output, $mysqldump_check_return);
        if ($mysqldump_check_return !== 0) {
            $message .= "<br>Verifica che 'mysqldump' sia installato e accessibile nel PATH del server o che il percorso <code>".htmlspecialchars($mysqldump_path)."</code> sia corretto.";
             $message .= "<br>Output controllo versione mysqldump: <pre>" . htmlspecialchars(implode("\n", $mysqldump_check_output)) . "</pre>";
        }

        $message_type = 'error';
    }
}

require_once ROOT_PATH . 'admin/includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; // $message può contenere HTML (pre), quindi non usare htmlspecialchars qui ?>
        </div>
    <?php endif; ?>

    <div class="backup-form-container">
        <p>Clicca il pulsante qui sotto per generare un backup completo del database (<code><?php echo htmlspecialchars(DB_NAME); ?></code>).</p>
        <p>Il file di backup verrà scaricato automaticamente dal tuo browser in formato <code>.sql</code>.</p>
        <p><strong>Nota:</strong> A seconda della dimensione del database, il processo potrebbe richiedere alcuni secondi o più.</p>
        
        <form action="backup_db.php" method="POST" style="margin-top: 20px;">
            <button type="submit" name="perform_backup" class="btn btn-success btn-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Esegui e Scarica Backup Database
            </button>
        </form>
        <p style="margin-top: 20px; font-size:0.9em; color: #6c757d;">
            Assicurati che il server abbia i permessi necessari e che l'utility <code>mysqldump</code> sia accessibile.
            Il percorso attualmente configurato per <code>mysqldump</code> è: <code><?php echo htmlspecialchars($mysqldump_path); ?></code>. Se non funziona, potrebbe essere necessario modificarlo nello script PHP.
        </p>
    </div>
</div>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>