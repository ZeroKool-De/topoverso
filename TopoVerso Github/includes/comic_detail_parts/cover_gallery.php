<?php
// topolinolib/includes/comic_detail_parts/cover_gallery.php
?>
<div class="comic-detail-cover-gallery">
    <div class="cover-image-container">
        <img src="<?php echo htmlspecialchars($initial_cover_path); ?>"
             alt="<?php echo htmlspecialchars($initial_cover_alt); ?>"
             id="currentComicCover"
             class="<?php echo (strpos($initial_cover_path, 'placeholder_cover.png') === false) ? 'clickable-image' : ''; ?>"
             data-modal-caption="<?php echo htmlspecialchars($initial_cover_caption); ?>">
        <?php if (count($all_covers_for_js) > 1): ?>
            <button class="cover-nav prev" id="prevCoverBtn" title="Immagine Precedente">&#10094;</button>
            <button class="cover-nav next" id="nextCoverBtn" title="Immagine Successiva">&#10095;</button>
        <?php endif; ?>
    </div>
    <div class="cover-gallery-info">
        <div class="cover-caption-area" id="coverCaptionDisplay"><?php echo htmlspecialchars($initial_cover_caption); ?></div>
        <?php if (count($all_covers_for_js) > 1): ?>
            <div class="cover-indicator" id="coverIndicatorDisplay">1 / <?php echo count($all_covers_for_js); ?></div>
        <?php endif; ?>
        
        <?php 
        // Mostra informazioni dettagliate sui tipi di copertina disponibili
        $cover_types_available = [];
        if (!empty($comic['cover_image'])) {
            $cover_types_available[] = 'Principale';
        }
        if (!empty($comic['back_cover_image'])) {
            $cover_types_available[] = 'Retrocopertina';
        }
        if ($variant_cover_present) {
            $variant_count = 0;
            foreach ($all_covers_for_js as $cover) {
                if (strpos($cover['caption'], 'Variant') !== false || strpos($cover['caption'], 'variant') !== false) {
                    $variant_count++;
                }
            }
            if ($variant_count > 0) {
                $cover_types_available[] = $variant_count > 1 ? $variant_count . ' Variant' : '1 Variant';
            }
        }
        
        if (!empty($cover_types_available)): ?>
            <div class="cover-types-info">
                <small>Disponibili: <?php echo implode(', ', $cover_types_available); ?></small>
            </div>
        <?php endif; ?>
    </div>
</div>