<?php
// topolinolib/includes/comic_detail_parts/description.php
if (!empty($comic['description'])): ?>
    <div class="comic-description-section">
        <p><?php echo nl2br(htmlspecialchars($comic['description'])); ?></p>
    </div>
<?php else: ?>
    <p style="padding: 20px 0;">Nessuna descrizione disponibile per questo albo.</p>
<?php endif; ?>