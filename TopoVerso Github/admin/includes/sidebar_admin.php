<?php
// admin/includes/sidebar_admin.php
?>
<aside class="admin-sidebar">
    <nav class="sidebar-nav">
        <ul>
            <li><a href="<?php echo BASE_URL; ?>admin/index.php" <?php echo ($current_admin_page == 'index.php') ? 'class="active"' : ''; ?> title="Dashboard">
                <svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span class="admin-menu-label">Dashboard</span>
            </a></li>

            <?php if ($is_admin || ($is_contributor_only && in_array('comics_manage.php', $pages_for_contributors))): ?>
                <li><a href="<?php echo BASE_URL; ?>admin/comics_manage.php" <?php echo ($current_admin_page == 'comics_manage.php' || $current_admin_page == 'stories_manage.php' ) ? 'class="active"' : ''; ?> title="Fumetti/Storie">
                    <svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                    <span class="admin-menu-label">Fumetti</span>
                </a></li>
            <?php endif; ?>

            <?php if ($is_admin || ($is_contributor_only && in_array('series_manage.php', $pages_for_contributors))): ?>
                <li><a href="<?php echo BASE_URL; ?>admin/series_manage.php" <?php echo ($current_admin_page == 'series_manage.php') ? 'class="active"' : ''; ?> title="Serie">
                    <svg class="admin-menu-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span class="admin-menu-label">Serie</span>
                </a></li>
            <?php endif; ?>

            <?php if ($is_admin || ($is_contributor_only && in_array('persons_manage.php', $pages_for_contributors))): ?>
                <li><a href="<?php echo BASE_URL; ?>admin/persons_manage.php" <?php echo ($current_admin_page == 'persons_manage.php') ? 'class="active"' : ''; ?> title="Autori">
                    <svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span class="admin-menu-label">Autori</span>
                </a></li>
            <?php endif; ?>

            <?php if ($is_admin || ($is_contributor_only && in_array('characters_manage.php', $pages_for_contributors))): ?>
                <li><a href="<?php echo BASE_URL; ?>admin/characters_manage.php" <?php echo ($current_admin_page == 'characters_manage.php') ? 'class="active"' : ''; ?> title="Personaggi">
                    <svg class="admin-menu-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                    <span class="admin-menu-label">Personaggi</span>
                </a></li>
            <?php endif; ?>

            <?php if ($is_admin || ($is_contributor_only && in_array('missing_comics_manage.php', $pages_for_contributors))): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin/missing_comics_manage.php" <?php echo ($current_admin_page == 'missing_comics_manage.php') ? 'class="active"' : ''; ?> title="Albi Segnalati">
                        <svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="8" x2="12" y2="8.01"></line></svg>
                        <span class="admin-menu-label">Albi Segnalati</span>
                    </a></li>
            <?php endif; ?>
            
            <?php if ($is_admin): // --- SEZIONI SOLO ADMIN --- ?>
                <li class="sidebar-divider">Gestione Sito</li>
                <li><a href="<?php echo BASE_URL; ?>admin/proposals_manage.php" <?php echo ($current_admin_page == 'proposals_manage.php') ? 'class="active"' : ''; ?> title="Proposte Contributi"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m10 15.5 2 2 4-4"></path></svg><span class="admin-menu-label">Proposte</span></a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/requests_manage.php" <?php echo ($current_admin_page == 'requests_manage.php') ? 'class="active"' : ''; ?> title="Richieste Visitatori"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg><span class="admin-menu-label">Richieste</span></a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/forum_manage.php" <?php echo ($current_admin_page == 'forum_manage.php') ? 'class="active"' : ''; ?> title="Gestione Forum"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span class="admin-menu-label">Forum</span></a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/users_manage.php" <?php echo ($current_admin_page == 'users_manage.php') ? 'class="active"' : ''; ?> title="Utenti Frontend"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="admin-menu-label">Utenti</span></a></li>
                <li class="sidebar-divider">Contenuti Aggiuntivi</li>
                <li><a href="<?php echo BASE_URL; ?>admin/historical_events_manage.php" <?php echo ($current_admin_page == 'historical_events_manage.php') ? 'class="active"' : ''; ?> title="Eventi Storici"><svg class="admin-menu-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M8 14h8"></path><path d="M8 18h5"></path></svg><span class="admin-menu-label">Eventi Storici</span></a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/custom_fields_manage.php" <?php echo ($current_admin_page == 'custom_fields_manage.php') ? 'class="active"' : ''; ?> title="Campi Custom"><svg class="admin-menu-icon" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg><span class="admin-menu-label">Campi Custom</span></a></li>
                 <li class="sidebar-divider">Amministrazione</li>
                <li><a href="<?php echo BASE_URL; ?>admin/settings_manage.php" <?php echo ($current_admin_page == 'settings_manage.php') ? 'class="active"' : ''; ?> title="Impostazioni Sito"><svg class="admin-menu-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg><span class="admin-menu-label">Impostazioni</span></a></li>
                
                <li><a href="<?php echo BASE_URL; ?>admin/backup_db.php" <?php echo ($current_admin_page == 'backup_db.php') ? 'class="active"' : ''; ?> title="Backup DB"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg><span class="admin-menu-label">Backup</span></a></li>
                
                <li><a href="<?php echo BASE_URL; ?>admin/utility_calculate_appearances.php" <?php echo ($current_admin_page == 'utility_calculate_appearances.php') ? 'class="active"' : ''; ?> title="Calcola Prime Apparizioni"><svg class="admin-menu-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg><span class="admin-menu-label">Calcola Apparizioni</span></a></li>

            <?php endif; ?>
        </ul>
    </nav>
</aside>