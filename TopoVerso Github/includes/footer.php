<?php
// topolinolib/includes/footer.php

// Logica per recuperare il nome del sito dinamico, simile a quanto avviene nell'header.
$footer_site_name = defined('SITE_NAME') ? SITE_NAME : 'Catalogo Topolino';
if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('get_site_setting')) {
    $db_site_name_footer = get_site_setting('site_name_dynamic', $mysqli);
    if (!empty($db_site_name_footer)) {
        $footer_site_name = $db_site_name_footer;
    }
}

if (!defined('BASE_URL')) {
    // Fallback nel caso in cui la costante non sia definita
}
?>
        <footer>
            <p>
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($footer_site_name); ?>. Tutti i diritti riservati.
                <span style="margin: 0 10px;">|</span>
                
                <a href="https://www.iubenda.com/privacy-policy/64916928" rel="noreferrer nofollow" target="_blank">Privacy Policy</a>
                <span style="margin: 0 5px;">-</span>
                <a href="#" role="button" class="iubenda-advertising-preferences-link">Personalizza tracciamento pubblicitario</a>
                <?php if (defined('BASE_URL')): ?>
                    <span style="margin: 0 10px;">|</span>
                    <?php
                    $admin_link = BASE_URL . 'login.php?redirect=admin'; // Link per utente sloggato
                    if (isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'admin') {
                        $admin_link = BASE_URL . 'admin/'; // Link per admin già loggato
                    }
                    ?>
                    <a href="<?php echo $admin_link; ?>" target="_blank" style="color: #fff; text-decoration: underline;">Area Admin</a>
                <?php endif; ?>
            </p>
            <p style="font-size: 0.85em; color: #ccc; margin-top: 10px; padding: 0 20px;">
                Le informazioni presenti in questo catalogo sono frutto di appassionate ricerche e del confronto di molteplici fonti. Data la vastità dell'universo di Topolino, potrebbero esserci imprecisioni o omissioni. Questo progetto è in continua crescita: ogni contributo è prezioso! - Tutte le immagini all'interno di questo sito sono © Disney.
            </p>
        </footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const timelineContainer = document.querySelector('.timeline-container');
    if (!timelineContainer) { return; }

    const yearMarkers = timelineContainer.querySelectorAll('.timeline-year-marker');
    const yearGroups = timelineContainer.querySelectorAll('.timeline-year-group');
    const navigationWrapper = timelineContainer.querySelector('.timeline-navigation-wrapper');
    const decadeFilters = timelineContainer.querySelectorAll('.decade-filter'); // <-- NUOVO

    function activateYear(year) {
        yearMarkers.forEach(marker => marker.classList.remove('active'));
        yearGroups.forEach(group => group.classList.remove('active'));

        const markerToActivate = timelineContainer.querySelector(`.timeline-year-marker[data-year="${year}"]`);
        const groupToActivate = timelineContainer.querySelector(`#year-${year}`);

        if (markerToActivate && groupToActivate) {
            markerToActivate.classList.add('active');
            groupToActivate.classList.add('active');

            if (navigationWrapper) {
                markerToActivate.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }
    }

    // --- NUOVA FUNZIONE PER FILTRARE PER DECENNIO ---
    function filterTimelineByDecade(selectedDecade) {
        yearMarkers.forEach(marker => {
            const markerYear = parseInt(marker.dataset.year, 10);
            const markerDecade = Math.floor(markerYear / 10) * 10;
            
            if (selectedDecade === 'all' || markerDecade == selectedDecade) {
                marker.style.display = 'flex'; // Mostra il marcatore
            } else {
                marker.style.display = 'none'; // Nasconde il marcatore
            }
        });

        // Dopo aver filtrato, attiva il primo anno visibile per evitare una vista vuota
        const firstVisibleMarker = timelineContainer.querySelector('.timeline-year-marker[style*="display: flex"]');
        if (firstVisibleMarker) {
            activateYear(firstVisibleMarker.dataset.year);
        } else {
             // Se nessun marcatore è visibile, nascondi tutti i gruppi
             yearGroups.forEach(group => group.classList.remove('active'));
        }
    }

    // Event listener per i click sui segnaposto degli anni
    yearMarkers.forEach(marker => {
        marker.addEventListener('click', function() {
            const year = this.dataset.year;
            activateYear(year);
        });
    });

    // --- NUOVI EVENT LISTENER PER I FILTRI DECENNIO ---
    decadeFilters.forEach(filter => {
        filter.addEventListener('click', function() {
            // Gestione della classe 'active' per i pulsanti filtro
            decadeFilters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
            
            const decade = this.dataset.decade;
            filterTimelineByDecade(decade);
        });
    });

    // Stato iniziale: attiva il primo anno visibile (di default, l'ultimo)
    if (yearMarkers.length > 0) {
        // Troviamo il primo anno visibile dopo il caricamento iniziale (che sono tutti)
        const firstVisibleMarker = timelineContainer.querySelector('.timeline-year-marker');
        if (firstVisibleMarker) {
            // Per coerenza, attiviamo sempre l'ultimo anno visibile all'inizio
            const allVisibleMarkers = timelineContainer.querySelectorAll('.timeline-year-marker');
            const latestYearMarker = allVisibleMarkers[allVisibleMarkers.length - 1];
            activateYear(latestYearMarker.dataset.year);
        }
    }
});
</script>

        </body>
</html>
<?php
ob_end_flush();
?>