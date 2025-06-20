<?php
// topolinolib/admin/includes/comics_list_view.php
// Questo file viene incluso da comics_manage.php quando $action === 'list'
// Si aspetta che le variabili necessarie ($result_list_comics, $total_comics_val, $page_num, $total_pages_val, ecc.)
// siano già state definite e popolate da comics_manage.php.
?>
<div class="admin-list-controls">
    <a href="comics_manage.php?action=add" class="btn btn-primary">
        <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuovo Fumetto' : 'Aggiungi Nuovo Fumetto'; ?>
    </a>
    <form action="comics_manage.php" method="GET" class="form-inline">
        <input type="hidden" name="action" value="list">
        <input type="text" name="search" class="form-control" placeholder="Cerca numero, titolo..." value="<?php echo htmlspecialchars($search_term_get); ?>">
        <button type="submit" class="btn btn-info">Cerca</button>
        <?php if (!empty($search_term_get)): ?>
            <a href="comics_manage.php?action=list" class="btn btn-secondary">Resetta</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($total_comics_val > 0 && $result_list_comics && $result_list_comics->num_rows > 0): ?>
    <p>Trovati <?php echo $total_comics_val; ?> albi. Pagina <?php echo $page_num; ?> di <?php echo $total_pages_val; ?>.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Cop.</th>
                <th>Retrocop.</th>
                <th>Numero</th>
                <th>Titolo</th>
                <th>Data Pub.</th>
                <th>Gadget</th>
                <th>Var.</th>
                <th style="min-width: 220px;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_list_comics->fetch_assoc()): ?>
            <tr>
                <td><?php if ($row['cover_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($row['cover_image']); ?>" alt="Copertina" style="max-width: 60px; height: auto;"><?php else: echo generate_image_placeholder($row['issue_number'], 60, 84, 'admin-table-placeholder'); endif; ?></td>
                <td><?php if ($row['back_cover_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($row['back_cover_image']); ?>" alt="Retrocopertina" style="max-width: 60px; height: auto;"><?php else: ?>-<?php endif; ?></td>
                <td><?php echo htmlspecialchars($row['issue_number']); ?></td>
                <td><a href="comics_manage.php?action=edit&id=<?php echo $row['comic_id']; ?>"><?php echo htmlspecialchars($row['title'] ?? 'N/D'); ?></a></td>
                <td><?php echo $row['publication_date'] ? date("d/m/Y", strtotime($row['publication_date'])) : 'N/D'; ?></td>
                <td><?php echo !empty($row['gadget_name']) ? 'Sì' : 'No'; ?></td>
                <td><?php echo $row['variant_count']; ?></td>
                <td style="white-space: nowrap;">
                    <a href="comics_manage.php?action=edit&id=<?php echo $row['comic_id']; ?>" class="btn btn-sm btn-warning">
                        <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Mod.' : 'Mod.'; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/stories_manage.php?comic_id=<?php echo $row['comic_id']; ?>" class="btn btn-sm btn-info">
                        <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Storie' : 'Storie'; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $row['comic_id']; ?>" target="_blank" class="btn btn-sm btn-secondary">Vedi</a>
                    <?php if ($is_true_admin): ?>
                        <a href="comics_manage.php?action=delete&id=<?php echo $row['comic_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Eliminare questo fumetto e tutte le sue storie e dati associati? L\'azione è irreversibile.');">Eli.</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php if ($total_pages_val > 1): ?>
    <div class="pagination-controls">
        <?php $base_pag_url = BASE_URL . "admin/comics_manage.php?action=list" . (!empty($search_term_get) ? "&search=" . urlencode($search_term_get) : ""); ?>
        
        <?php if ($page_num > 1): ?>
            <a href="<?php echo $base_pag_url; ?>&page=1" title="Prima pagina">&laquo;&laquo;</a>
            <a href="<?php echo $base_pag_url; ?>&page=<?php echo $page_num - 1; ?>" title="Pagina precedente">&laquo; Prec.</a>
        <?php endif; ?>

        <?php
        $links_to_show = 5;
        $start_page = max(1, $page_num - floor($links_to_show / 2));
        $end_page = min($total_pages_val, $start_page + $links_to_show - 1);
        if ($end_page - $start_page + 1 < $links_to_show) {
            $start_page = max(1, $end_page - $links_to_show + 1);
        }

        if ($start_page > 1) {
            echo '<a href="' . $base_pag_url . '&page=1">1</a>';
            if ($start_page > 2) {
                echo '<span class="disabled">...</span>';
            }
        }

        for ($i_pag = $start_page; $i_pag <= $end_page; $i_pag++): ?>
            <?php if ($i_pag == $page_num): ?><span class="current-page"><?php echo $i_pag; ?></span>
            <?php else: ?><a href="<?php echo $base_pag_url; ?>&page=<?php echo $i_pag; ?>"><?php echo $i_pag; ?></a><?php endif; ?>
        <?php endfor; ?>

        <?php
        if ($end_page < $total_pages_val) {
            if ($end_page < $total_pages_val - 1) {
                echo '<span class="disabled">...</span>';
            }
            echo '<a href="' . $base_pag_url . '&page=' . $total_pages_val . '">' . $total_pages_val . '</a>';
        }
        ?>

        <?php if ($page_num < $total_pages_val): ?>
            <a href="<?php echo $base_pag_url; ?>&page=<?php echo $page_num + 1; ?>" title="Pagina successiva">Succ. &raquo;</a>
            <a href="<?php echo $base_pag_url; ?>&page=<?php echo $total_pages_val; ?>" title="Ultima pagina">&raquo;&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php elseif ($searched): ?>
    <p>Nessun fumetto trovato per la ricerca "<?php echo htmlspecialchars($search_term_get); ?>".
        <a href="comics_manage.php?action=add&prefill_issue_number=<?php echo urlencode($search_term_get); ?>">
            <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi l\'aggiunta di questo numero.' : 'Aggiungi questo numero.'; ?>
        </a>
    </p>
<?php else: ?>
     <p>Nessun fumetto trovato. 
        <a href="comics_manage.php?action=add">
            <?php echo ($is_contributor && !$is_true_admin) ? 'Proponine uno!' : 'Aggiungine uno!'; ?>
        </a>
    </p>
<?php endif; ?>