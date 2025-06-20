<?php
// topolinolib/includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- INIZIO BLOCCO AGGIUNTO E CORRETTO (per notifiche, invariato) ---
$unread_notifications_count = 0;
if (isset($_SESSION['user_id_frontend'])) {
    $current_user_for_notif = $_SESSION['user_id_frontend'];
    // Assicurati che $mysqli esista e la connessione sia valida
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $stmt_count_notif = $mysqli->prepare("SELECT COUNT(notification_id) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt_count_notif) {
            $stmt_count_notif->bind_param("i", $current_user_for_notif);
            $stmt_count_notif->execute();
            $res_count = $stmt_count_notif->get_result();
            if ($row_count = $res_count->fetch_assoc()) {
                $unread_notifications_count = $row_count['unread_count'];
            }
            $stmt_count_notif->close();
        }
    }
}
// --- FINE BLOCCO ---


$site_logo_path_frontend = null;
$site_name_to_display_frontend = defined('SITE_NAME') ? SITE_NAME : 'Catalogo Topolino';

if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('get_site_setting')) {
    $site_logo_path_frontend = get_site_setting('site_logo_path', $mysqli);
    $db_site_name = get_site_setting('site_name_dynamic', $mysqli);
    if (!empty($db_site_name)) {
        $site_name_to_display_frontend = $db_site_name;
    }
}

$current_page_basename = basename($_SERVER['PHP_SELF']);
$user_avatar_header = $_SESSION['avatar_frontend'] ?? null;
$username_initial = isset($_SESSION['username_frontend']) ? strtoupper(substr($_SESSION['username_frontend'], 0, 1)) : '?';

$parent_pages = [
    'series_detail.php' => 'series_list.php',
    'character_detail.php' => 'characters_page.php',
    'author_detail.php' => 'authors_page.php',
    'comic_detail.php' => 'index.php',
    'my_full_collection.php' => 'user_dashboard.php',
    'thread.php' => 'forum.php',
    'first_appearances_list.php' => 'approfondimenti_dropdown'
];
$effective_page_basename = $parent_pages[$current_page_basename] ?? $current_page_basename;

// ### LA MODIFICA È QUI SOTTO, NELL'ARRAY $menu_structure ###
$menu_structure = [
    'index.php' => ['label' => 'Home'],
    'catalogo_dropdown' => [
        'label' => 'Catalogo',
        'is_dropdown' => true,
        'sub_items' => [
            'by_year.php' => ['label' => 'Per Anno'],
            'series_list.php' => ['label' => 'Serie'],
            'characters_page.php' => ['label' => 'Personaggi'],
            'authors_page.php' => ['label' => 'Autori'],
            'search.php' => ['label' => 'Ricerca Avanzata'],
        ]
    ],
    'interagisci_dropdown' => [
        'label' => 'Interagisci',
        'is_dropdown' => true,
        'sub_items' => [
            'forum.php' => ['label' => 'Forum'],
            'mio_numero.php' => ['label' => 'Il Tuo Numero'],
            'richieste.php' => ['label' => 'Richiedi Numero'],
        ]
    ],
    'approfondimenti_dropdown' => [
        'label' => 'Approfondimenti',
        'is_dropdown' => true,
        'sub_items' => [
            'statistics.php' => ['label' => 'Statistiche'],
            'classifica.php' => ['label' => 'Classifiche'],
            // La pagina con la timeline l'abbiamo rinominata per chiarezza
            'prime_apparizioni.php' => ['label' => 'Timeline Apparizioni'],
            // Questo è il nuovo link corretto per la lista
            'first_appearances_list.php' => ['label' => 'Elenco Prime Apparizioni'],
            'cronologia_topoverso.php' => ['label' => 'Cronologia Storica'],
            'info_contatti.php' => ['label' => 'Info & Contatti'],
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        $html_page_title_prefix = isset($page_title) && !empty($page_title) ? htmlspecialchars($page_title) . ' - ' : '';
        echo $html_page_title_prefix . htmlspecialchars($site_name_to_display_frontend);
    ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <?php if ($site_logo_path_frontend && file_exists(UPLOADS_PATH . $site_logo_path_frontend)): ?>
        <style>
            header #branding h1 a { background-image: url('<?php echo UPLOADS_URL . htmlspecialchars($site_logo_path_frontend); ?>'); background-repeat: no-repeat; background-size: contain; background-position: left center; text-indent: -9999px; display: inline-block; width: 120px; height: 100px; color: transparent !important; }
            header #branding h1 a:hover { color: transparent !important; }
        </style>
    <?php endif; ?>
    <link rel="preload" as="script" href="https://cdn.iubenda.com/cs/iubenda_cs.js"/>
    <link rel="preload" as="script" href="https://cdn.iubenda.com/cs/tcf/stub-v2.js"/>
    <script src="https://cdn.iubenda.com/cs/tcf/stub-v2.js"></script>
    <script>
    (_iub=self._iub||[]).csConfiguration={ cookiePolicyId: 64916928, siteId: 4061146, timeoutLoadConfiguration: 30000, lang: 'it', enableTcf: true, tcfVersion: 2, tcfPurposes: { "2": "consent_only", "3": "consent_only", "4": "consent_only", "5": "consent_only", "6": "consent_only", "7": "consent_only", "8": "consent_only", "9": "consent_only", "10": "consent_only" }, invalidateConsentWithoutLog: true, googleAdditionalConsentMode: true, consentOnContinuedBrowse: false, banner: { position: "top", acceptButtonDisplay: true, customizeButtonDisplay: true, closeButtonDisplay: true, closeButtonRejects: true, fontSizeBody: "14px", }, }
    </script>
    <script async src="https://cdn.iubenda.com/cs/iubenda_cs.js"></script>
</head>
<body>
    <header>
        <div class="container">
            <?php // ### BLOCCO HTML ORIGINALE RIPRISTINATO ### ?>
            <div id="branding">
                <h1><a href="<?php echo BASE_URL; ?>"><?php echo htmlspecialchars($site_name_to_display_frontend); ?></a></h1>
            </div>
            <nav class="text-menu-navigation"> 
                <ul>
                    <?php foreach ($menu_structure as $file_or_key => $item):
                        $is_main_item_active = false; $link_href = '#'; $is_dropdown = isset($item['is_dropdown']) && $item['is_dropdown'];
                        if (!$is_dropdown) { 
                            $link_href = BASE_URL . $file_or_key; 
                            if ($effective_page_basename == $file_or_key) { 
                                $is_main_item_active = true; 
                            }
                        } else { 
                            if (!empty($item['sub_items'])) { 
                                foreach ($item['sub_items'] as $sub_file => $sub_item) { 
                                    if ($current_page_basename == $sub_file) { 
                                        $is_main_item_active = true; 
                                        break; 
                                    } 
                                } 
                            } 
                        } ?>
                        <li class="<?php echo $is_main_item_active ? 'current' : ''; ?> <?php echo $is_dropdown ? 'dropdown-item' : ''; ?>">
                            <a href="<?php echo htmlspecialchars($link_href); ?>" title="<?php echo htmlspecialchars($item['label']); ?>" class="<?php echo $is_dropdown ? 'dropdown-toggle' : ''; ?>">
                                <?php echo htmlspecialchars($item['label']); ?>
                                <?php if ($is_dropdown): ?><span class="dropdown-arrow">▾</span><?php endif; ?>
                            </a>
                            <?php if ($is_dropdown && !empty($item['sub_items'])): ?>
                                <ul class="dropdown-menu">
                                    <?php foreach ($item['sub_items'] as $sub_file => $sub_item): ?>
                                        <li class="<?php echo ($current_page_basename == $sub_file) ? 'current-sub' : ''; ?>"><a href="<?php echo BASE_URL . htmlspecialchars($sub_file); ?>"><?php echo htmlspecialchars($sub_item['label']); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (isset($_SESSION['user_id_frontend'])): ?>
                        <li class="user-menu-item notification-item" id="notification-bell-container">
                            <a href="<?php echo BASE_URL; ?>user_dashboard.php#notifiche" title="Notifiche">
                                <svg class="notification-bell-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                <?php if (isset($unread_notifications_count) && $unread_notifications_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="user-menu-item <?php echo ($effective_page_basename == 'user_dashboard.php') ? 'current' : ''; ?>">
                            <a href="<?php echo BASE_URL; ?>user_dashboard.php" title="Mia Pagina" class="user-avatar-link">
                                <?php if ($user_avatar_header): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($user_avatar_header); ?>" alt="Avatar" class="header-user-avatar">
                                <?php else: ?>
                                    <span class="header-user-avatar placeholder"><?php echo $username_initial; ?></span>
                                <?php endif; ?>
                                <span class="user-menu-text-label">Mia Pagina</span>
                            </a>
                        </li>
                        <li class="user-menu-item"><a href="<?php echo BASE_URL; ?>logout.php" title="Logout">Logout</a></li>
                    <?php else: ?>
                        <li class="user-menu-item <?php echo ($current_page_basename == 'login.php') ? 'current' : ''; ?>"><a href="<?php echo BASE_URL; ?>login.php" title="Login">Login</a></li>
                        <li class="user-menu-item <?php echo ($current_page_basename == 'register.php') ? 'current' : ''; ?>"><a href="<?php echo BASE_URL; ?>register.php" title="Registrati">Registrati</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="header-actions">
                <a href="<?php echo BASE_URL; ?>search.php" class="search-icon-link" title="Ricerca Avanzata"><svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></a>
                <form action="<?php echo BASE_URL; ?>jump_to_issue.php" method="GET" class="quick-jump-form"><input type="text" name="issue" placeholder="N. Albo" title="Inserisci il numero dell'albo" required><button type="submit">Vai</button></form>
            </div>
            <?php // ### FINE BLOCCO HTML RIPRISTINATO ### ?>
        </div>
    </header>