<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// CONTROLLO ACCESSO UNIFICATO E MIGLIORATO
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Utilizziamo le sessioni unificate impostate dal nuovo login.php
$is_logged_in = isset($_SESSION['user_id_frontend']);
$user_role = $_SESSION['user_role_frontend'] ?? 'guest';

// Definiamo i ruoli validi per accedere all'area admin
$is_admin = ($user_role === 'admin');
$is_contributor = ($user_role === 'contributor');

// Se l'utente non è né admin né contributor, non può accedere.
if (!$is_admin && !$is_contributor) {
    // Reindirizziamo al login del frontend, che è ora l'unico punto d'accesso
    header('Location: ' . BASE_URL . 'login.php?redirect=admin/index.php');
    exit;
}

$page_title = "Dashboard";
// L'header_admin ora gestisce tutto, inclusa la visualizzazione del nome utente
require_once ROOT_PATH . 'admin/includes/header_admin.php'; 

// Definizioni per i pannelli della dashboard
$all_dashboard_panels = [
    [
        'title' => 'Gestione Fumetti',
        'link' => BASE_URL . 'admin/comics_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
        'description' => 'Aggiungi, modifica o proponi albi e relative storie.',
        'roles' => ['admin', 'contributor']
    ],
    [
        'title' => 'Gestione Serie Storie',
        'link' => BASE_URL . 'admin/series_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>',
        'description' => 'Crea e organizza le serie di storie (es. saghe, cicli narrativi).',
        'roles' => ['admin', 'contributor']
    ],
    [
        'title' => 'Gestione Persone',
        'link' => BASE_URL . 'admin/persons_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'description' => 'Gestisci autori, disegnatori e altri collaboratori.',
        'roles' => ['admin', 'contributor']
    ],
    [
        'title' => 'Gestione Personaggi',
        'link' => BASE_URL . 'admin/characters_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>',
        'description' => 'Aggiungi o modifica i personaggi dei fumetti.',
        'roles' => ['admin', 'contributor']
    ],
    [
        'title' => 'Albi Segnalati dagli Utenti',
        'link' => BASE_URL . 'admin/missing_comics_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="8" x2="12" y2="8.01"></line></svg>',
        'description' => 'Visualizza i numeri mancanti segnalati e proponine l\'aggiunta.',
        'roles' => ['admin', 'contributor']
    ],
    // PANNELLI SOLO ADMIN
    [
        'title' => 'Gestione Proposte Contributi',
        'link' => BASE_URL . 'admin/proposals_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m10 15.5 2 2 4-4"></path></svg>',
        'description' => 'Revisiona le proposte inviate dai Contributori.',
        'roles' => ['admin']
    ],
    [
        'title' => 'Gestione Richieste Visitatori',
        'link' => BASE_URL . 'admin/requests_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
        'description' => 'Gestisci le richieste di albi mancanti inviate dai visitatori.',
        'roles' => ['admin']
    ],
    [
        'title' => 'Gestione Segnalazioni Errori',
        'link' => BASE_URL . 'admin/error_reports_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'description' => 'Gestisci le segnalazioni di errori inviate dagli utenti.',
        'roles' => ['admin']
    ],
    [
        'title' => 'Gestione Utenti',
        'link' => BASE_URL . 'admin/users_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'description' => 'Visualizza e gestisci gli utenti registrati al sito.',
        'roles' => ['admin']
    ],
     [
        'title' => 'Impostazioni Sito',
        'link' => BASE_URL . 'admin/settings_manage.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
        'description' => 'Gestisci impostazioni globali come nome e logo del sito.',
        'roles' => ['admin']
    ],
    [
        'title' => 'Backup Database',
        'link' => BASE_URL . 'admin/backup_db.php',
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>',
        'description' => 'Esegui un backup completo del database.',
        'roles' => ['admin']
    ],
];


// Logica per filtrare i pannelli da visualizzare in base al ruolo
$dashboard_panels_to_display = array_filter($all_dashboard_panels, function($panel) use ($is_admin, $is_contributor) {
    if ($is_admin && in_array('admin', $panel['roles'])) {
        return true;
    }
    if ($is_contributor && in_array('contributor', $panel['roles'])) {
        return true;
    }
    return false;
});

// Gestione dei messaggi di sessione per notificare l'utente
$message = $_SESSION['admin_message'] ?? null;
$message_type = $_SESSION['admin_message_type'] ?? 'info';
if ($message) {
    unset($_SESSION['admin_message']);
    unset($_SESSION['admin_message_type']);
}
?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?></h2>
    <p style="font-size: 1.1em; color: #555; margin-bottom: 30px;">
        Benvenuto nell'area <?php echo $is_admin ? 'amministrativa' : 'contributi'; ?>, 
        <strong><?php echo htmlspecialchars($_SESSION['username_frontend'] ?? 'Ospite'); ?></strong>! Seleziona una sezione per iniziare:
    </p>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <?php foreach ($dashboard_panels_to_display as $panel): ?>
            <a href="<?php echo $panel['link']; ?>" class="dashboard-panel">
                <div class="panel-icon">
                    <?php echo $panel['icon_svg']; ?>
                </div>
                <div class="panel-content">
                    <h3><?php echo htmlspecialchars($panel['title']); ?></h3>
                    <p><?php echo htmlspecialchars($panel['description']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($dashboard_panels_to_display)): ?>
            <p>Non ci sono sezioni a te accessibili al momento.</p>
        <?php endif; ?>
    </div>
    
    <?php // ### INIZIO BLOCCO AGGIUNTO: STRUMENTI DI MANUTENZIONE ### ?>
    <?php if ($is_admin): ?>
    <div class="admin-panel maintenance-tools" style="margin-top: 40px;">
        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Strumenti di Manutenzione</h3>
        <div class="tool-item">
            <h4>Calcolo Prime Apparizioni</h4>
            <p>Questo strumento analizza tutte le storie e calcola la data della prima apparizione per ogni personaggio. Eseguilo se hai aggiunto o modificato molte storie per aggiornare i dati pubblici.</p>
            <form action="utility_calculate_appearances.php" method="POST" onsubmit="return confirm('Sei sicuro di voler avviare il ricalcolo delle prime apparizioni? L\'operazione potrebbe richiedere alcuni secondi.');">
                <button type="submit" class="btn-maintenance">Avvia Ricalcolo Prime Apparizioni</button>
            </form>
        </div>
        <div class="tool-item">
            <h4>Generazione Slug</h4>
            <p>Questo strumento genera gli "slug" (URL amichevoli) per fumetti, storie, autori, personaggi e serie che non ne hanno uno. Utile per la SEO.</p>
            <form action="<?php echo BASE_URL; ?>utility_generate_slugs.php" method="POST" onsubmit="return confirm('Sei sicuro di voler generare gli slug mancanti?');">
                <button type="submit" class="btn-maintenance">Genera Slug Mancanti</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php // ### FINE BLOCCO AGGIUNTO ### ?>

</div>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>