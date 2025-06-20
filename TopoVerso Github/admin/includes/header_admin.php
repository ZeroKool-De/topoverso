<?php
ob_start(); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Assicura che la configurazione sia caricata
if (!defined('BASE_URL')) {
    $config_path_admin_header = dirname(__DIR__, 2) . '/config/config.php'; 
    if (file_exists($config_path_admin_header)) {
        require_once $config_path_admin_header;
    } else {
        die("Errore critico: Impossibile caricare la configurazione.");
    }
}
// Assicura che la connessione al DB e le funzioni siano disponibili
if (!isset($mysqli) && file_exists(ROOT_PATH . 'includes/db_connect.php')) {
    require_once ROOT_PATH . 'includes/db_connect.php';
}
if (!function_exists('get_site_setting') && file_exists(ROOT_PATH . 'includes/functions.php')) {
    require_once ROOT_PATH . 'includes/functions.php';
}

// Blocco sicurezza e ruoli unificato
if (!isset($_SESSION['user_id_frontend'])) {
    $redirect_url = 'admin/' . basename($_SERVER['PHP_SELF']);
    if(!empty($_SERVER['QUERY_STRING'])) {
        $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    // MODIFICA: Reindirizza sempre al login principale
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$user_role = $_SESSION['user_role_frontend'] ?? 'user';
if (!in_array($user_role, ['admin', 'contributor'])) {
    die("Accesso negato. Non hai i permessi per visualizzare questa pagina.");
}

// Determina se è un vero admin o solo un contributore per la logica di visualizzazione
$is_admin = ($user_role === 'admin');
$is_contributor_only = ($user_role === 'contributor');
$current_username_display = $_SESSION['username_frontend'] ?? 'Utente';
$admin_panel_name_text = $is_admin ? 'Pannello Admin' : 'Area Contributore';
// --- MODIFICA CHIAVE: Utilizza il nuovo script di logout unificato ---
$logout_url = BASE_URL . 'logout.php';

$current_admin_page = basename($_SERVER['PHP_SELF']);

// Recupero nome sito e logo per admin header
$site_logo_path_admin = null;
$site_name_to_display_admin = defined('SITE_NAME') ? SITE_NAME : 'Catalogo'; 

if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('get_site_setting')) {
    $site_logo_path_admin = get_site_setting('site_logo_path', $mysqli);
    $db_site_name_admin = get_site_setting('site_name_dynamic', $mysqli);
    if (!empty($db_site_name_admin)) {
        $site_name_to_display_admin = $db_site_name_admin;
    }
}

$pages_for_contributors = [
    'comics_manage.php', 'stories_manage.php', 'series_manage.php',
    'persons_manage.php', 'characters_manage.php', 'missing_comics_manage.php',
    'index.php'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars($admin_panel_name_text); ?> - <?php echo htmlspecialchars($site_name_to_display_admin); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/assets/css/admin_style.css?v=<?php echo time(); ?>">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php if ($site_logo_path_admin && file_exists(UPLOADS_PATH . $site_logo_path_admin)): ?>
        <style>
            .admin-header h1 a {
                background-image: url('<?php echo UPLOADS_URL . htmlspecialchars($site_logo_path_admin); ?>');
                background-repeat: no-repeat; background-size: contain; background-position: left center;
                text-indent: -9999px; display: inline-block; min-width: 150px;
                height: 35px; color: transparent !important; vertical-align: middle;
            }
             .admin-header h1 a:hover { color: transparent !important; }
        </style>
    <?php endif; ?>
    </head>
<body>
    <header class="admin-header">
        <h1>
            <a href="<?php echo BASE_URL; ?>admin/">
                <?php echo htmlspecialchars($site_name_to_display_admin); ?> - <?php echo htmlspecialchars($admin_panel_name_text); ?>
            </a>
        </h1>
        <nav class="admin-nav icon-menu">
             <ul>
                <li><a href="<?php echo BASE_URL; ?>" title="Torna al Sito Pubblico"><svg class="admin-menu-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.72"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.72"></path></svg><span class="admin-menu-label">Sito Pubblico</span></a></li>

                <li><a href="<?php echo BASE_URL; ?>admin/index.php" <?php echo ($current_admin_page == 'index.php') ? 'class="active"' : ''; ?> title="Dashboard"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg><span class="admin-menu-label">Dashboard</span></a></li>

                <?php if ($is_admin || ($is_contributor_only && in_array('comics_manage.php', $pages_for_contributors))): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/comics_manage.php" <?php echo ($current_admin_page == 'comics_manage.php' || $current_admin_page == 'stories_manage.php' ) ? 'class="active"' : ''; ?> title="Fumetti/Storie"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg><span class="admin-menu-label">Fumetti</span></a></li>
                <?php endif; ?>

                <?php if ($is_admin || ($is_contributor_only && in_array('series_manage.php', $pages_for_contributors))): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/series_manage.php" <?php echo ($current_admin_page == 'series_manage.php') ? 'class="active"' : ''; ?> title="Serie"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg><span class="admin-menu-label">Serie</span></a></li>
                <?php endif; ?>

                <?php if ($is_admin || ($is_contributor_only && in_array('persons_manage.php', $pages_for_contributors))): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/persons_manage.php" <?php echo ($current_admin_page == 'persons_manage.php') ? 'class="active"' : ''; ?> title="Autori"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><span class="admin-menu-label">Autori</span></a></li>
                <?php endif; ?>

                <?php if ($is_admin || ($is_contributor_only && in_array('characters_manage.php', $pages_for_contributors))): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/characters_manage.php" <?php echo ($current_admin_page == 'characters_manage.php') ? 'class="active"' : ''; ?> title="Personaggi"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg><span class="admin-menu-label">Personaggi</span></a></li>
                <?php endif; ?>

                <?php if ($is_admin || ($is_contributor_only && in_array('missing_comics_manage.php', $pages_for_contributors))): ?>
                     <li><a href="<?php echo BASE_URL; ?>admin/missing_comics_manage.php" <?php echo ($current_admin_page == 'missing_comics_manage.php') ? 'class="active"' : ''; ?> title="Albi Segnalati"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="8" x2="12" y2="8.01"></line></svg><span class="admin-menu-label">Albi Segnalati</span></a></li>
                <?php endif; ?>
                
                <?php if ($is_admin): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/proposals_manage.php" <?php echo ($current_admin_page == 'proposals_manage.php') ? 'class="active"' : ''; ?> title="Proposte Contributi"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m10 15.5 2 2 4-4"></path></svg><span class="admin-menu-label">Proposte</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/requests_manage.php" <?php echo ($current_admin_page == 'requests_manage.php') ? 'class="active"' : ''; ?> title="Richieste Visitatori"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg><span class="admin-menu-label">Richieste</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/forum_manage.php" <?php echo ($current_admin_page == 'forum_manage.php') ? 'class="active"' : ''; ?> title="Gestione Forum"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span class="admin-menu-label">Forum</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/ratings_manage.php" <?php echo ($current_admin_page == 'ratings_manage.php') ? 'class="active"' : ''; ?> title="Gestione Voti"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><span class="admin-menu-label">Voti</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/contact_messages_manage.php" <?php echo ($current_admin_page == 'contact_messages_manage.php') ? 'class="active"' : ''; ?> title="Messaggi Contatto"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg><span class="admin-menu-label">Messaggi Contatto</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/error_reports_manage.php" <?php echo ($current_admin_page == 'error_reports_manage.php') ? 'class="active"' : ''; ?> title="Segnalazioni Errori"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg><span class="admin-menu-label">Segnalazioni</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/settings_manage.php" <?php echo ($current_admin_page == 'settings_manage.php') ? 'class="active"' : ''; ?> title="Impostazioni Sito"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg><span class="admin-menu-label">Impostazioni</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/historical_events_manage.php" <?php echo ($current_admin_page == 'historical_events_manage.php') ? 'class="active"' : ''; ?> title="Eventi Storici"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M8 14h8"></path><path d="M8 18h5"></path></svg><span class="admin-menu-label">Eventi Storici</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/custom_fields_manage.php" <?php echo ($current_admin_page == 'custom_fields_manage.php') ? 'class="active"' : ''; ?> title="Campi Custom"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg><span class="admin-menu-label">Campi Custom</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/users_manage.php" <?php echo ($current_admin_page == 'users_manage.php') ? 'class="active"' : ''; ?> title="Utenti Frontend"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="admin-menu-label">Utenti</span></a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin/backup_db.php" <?php echo ($current_admin_page == 'backup_db.php') ? 'class="active"' : ''; ?> title="Backup DB"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg><span class="admin-menu-label">Backup</span></a></li>
                <?php endif; ?>

                 <li><a href="<?php echo $logout_url; ?>" title="Logout <?php echo htmlspecialchars($current_username_display); ?>"><svg class="admin-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span class="admin-menu-label">Logout (<?php echo substr(htmlspecialchars($current_username_display), 0, 10) . (strlen($current_username_display) > 10 ? '...' : ''); ?>)</span></a></li>
            </ul>
        </nav>
    </header>
    <div class="admin-main-container">
        <main class="admin-content">