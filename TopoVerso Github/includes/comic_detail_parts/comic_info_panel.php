<?php
// topolinolib/includes/comic_detail_parts/comic_info_panel.php
?>
<div class="left-column-section">
     <h4>Dati Salienti</h4>
    <ul>
        <li><strong>Numero:</strong> Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></li>
        <li><strong>Data Pubblicazione:</strong> <?php echo $comic['publication_date'] ? format_date_italian($comic['publication_date']) : 'N/D'; ?></li>
        
        <?php 
        // Mostra i copertinisti principali - sempre come "Copertina"
        if (!empty($cover_artists_list)): ?>
            <li><strong>Copertina:</strong> 
                <?php 
                $cover_names = [];
                foreach ($cover_artists_list as $cover_artist) {
                    $cover_names[] = '<a href="author_detail.php?slug=' . urlencode($cover_artist['slug']) . '" title="Vedi scheda di ' . htmlspecialchars($cover_artist['name']) . '">' . htmlspecialchars($cover_artist['name']) . '</a>';
                }
                echo implode(', ', $cover_names);
                ?>
            </li>
        <?php endif; ?>

        <?php 
        // Mostra la retrocopertina solo se presente
        if (!empty($comic['back_cover_image'])): ?>
            <li><strong>Retrocopertina:</strong> 
                <?php if (!empty($back_cover_artists_list)): 
                    $back_cover_names = [];
                    foreach ($back_cover_artists_list as $back_cover_artist) {
                        $back_cover_names[] = '<a href="author_detail.php?slug=' . urlencode($back_cover_artist['slug']) . '" title="Vedi scheda di ' . htmlspecialchars($back_cover_artist['name']) . '">' . htmlspecialchars($back_cover_artist['name']) . '</a>';
                    }
                    echo implode(', ', $back_cover_names);
                else: ?>
                    <em>Stesso autore della copertina</em>
                <?php endif; ?>
            </li>
        <?php endif; ?>

        <?php 
        // Mostra i copertinisti delle variant con conteggio
        if (!empty($variant_cover_artists_list)):
            $variant_count = count($variant_cover_artists_list);
            foreach ($variant_cover_artists_list as $index => $vc_artist): 
                $variant_role_display = 'Copertinista Variant';
                if ($variant_count > 1) {
                    $variant_role_display .= ' #' . ($index + 1);
                }
                ?>
                <li><strong><?php echo htmlspecialchars($variant_role_display); ?>:</strong> <a href="author_detail.php?slug=<?php echo urlencode($vc_artist['slug']); ?>" title="Vedi scheda di <?php echo htmlspecialchars($vc_artist['name']); ?>"><?php echo htmlspecialchars($vc_artist['name']); ?></a></li>
        <?php endforeach;
        endif; ?>
        
        <?php // Mostra i direttori
        if (!empty($directors_list)):
            foreach ($directors_list as $director): ?>
                <li><strong><?php echo htmlspecialchars($director['role']); ?>:</strong> <a href="author_detail.php?slug=<?php echo urlencode($director['slug']); ?>" title="Vedi scheda di <?php echo htmlspecialchars($director['name']); ?>"><?php echo htmlspecialchars($director['name']); ?></a></li>
        <?php endforeach;
        endif; ?>
        
        <?php if (!empty($comic['editor'])): ?><li><strong>Editore:</strong> <?php echo htmlspecialchars($comic['editor']); ?></li><?php endif; ?>
        <?php if (!empty($comic['pages'])): ?><li><strong>Pagine:</strong> <?php echo htmlspecialchars($comic['pages']); ?></li><?php endif; ?>
        <?php if (!empty($comic['price'])): ?><li><strong>Prezzo:</strong> <?php echo htmlspecialchars($comic['price']); ?></li><?php endif; ?>
        <?php if (!empty($comic['periodicity'])): ?><li><strong>Periodicità:</strong> <?php echo htmlspecialchars($comic['periodicity']); ?></li><?php endif; ?>
    </ul>
</div>

<div class="left-column-section">
    <h4>Vota questo Albo</h4>
    <div class="average-rating-display">
        <?php if ($comic_rating_info['count'] > 0): ?>
            <span class="stars" title="Voto medio: <?php echo $comic_rating_info['avg']; ?> su 5">
                <?php 
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $comic_rating_info['avg'] ? '★' : '☆';
                }
                ?>
            </span>
            <small>(Media: <?php echo $comic_rating_info['avg']; ?>/5 su <?php echo $comic_rating_info['count']; ?> voti)</small>
        <?php else: ?>
            <span class="no-rating">Non ancora votato.</span>
        <?php endif; ?>
    </div>

    <?php if (isset($_COOKIE['voted_comic_' . $comic_id])): ?>
        <p style="font-weight:bold; color:#155724;">Grazie per aver votato questo albo!</p>
    <?php else: ?>
        <form action="<?php echo BASE_URL; ?>actions/rating_actions.php" method="POST" class="star-rating-form">
            <input type="hidden" name="entity_type" value="comic">
            <input type="hidden" name="entity_id" value="<?php echo $comic_id; ?>">
            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            
            <div class="stars-input">
                <input type="radio" id="comic-star5" name="rating" value="5" /><label for="comic-star5" title="5 stelle">★</label>
                <input type="radio" id="comic-star4" name="rating" value="4" /><label for="comic-star4" title="4 stelle">★</label>
                <input type="radio" id="comic-star3" name="rating" value="3" /><label for="comic-star3" title="3 stelle">★</label>
                <input type="radio" id="comic-star2" name="rating" value="2" /><label for="comic-star2" title="2 stelle">★</label>
                <input type="radio" id="comic-star1" name="rating" value="1" /><label for="comic-star1" title="1 stella">★</label>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($comic['gadget_name']) || !empty($all_gadget_images_for_js) && !(count($all_gadget_images_for_js) === 1 && strpos($all_gadget_images_for_js[0]['path'], 'placeholder_image.png') !== false) ): ?>
<div class="left-column-section comic-gadget-section">
    <h4>Gadget Allegato</h4>
    <?php if(!empty($comic['gadget_name'])): ?>
        <p class="gadget-name-title"><?php echo htmlspecialchars($comic['gadget_name']); ?></p>
    <?php endif; ?>

    <?php if (!empty($all_gadget_images_for_js)): ?>
        <div class="comic-gadget-gallery">
            <div class="gadget-image-gallery-container">
                <img src="<?php echo htmlspecialchars($initial_gadget_image_path); ?>"
                     alt="<?php echo htmlspecialchars($initial_gadget_image_alt); ?>"
                     id="currentGadgetImage"
                     class="<?php echo (strpos($initial_gadget_image_path, 'placeholder_image.png') === false) ? 'clickable-image' : ''; ?>"
                     data-modal-caption="<?php echo htmlspecialchars($initial_gadget_image_caption); ?>">
                <?php if (count($all_gadget_images_for_js) > 1): ?>
                    <button class="gadget-gallery-nav prev" id="prevGadgetBtn" title="Immagine Gadget Precedente">&#10094;</button>
                    <button class="gadget-gallery-nav next" id="nextGadgetBtn" title="Immagine Gadget Successiva">&#10095;</button>
                <?php endif; ?>
            </div>
            <div class="gadget-gallery-info">
                <div class="gadget-caption-area" id="gadgetCaptionDisplay"><?php echo htmlspecialchars($initial_gadget_image_caption); ?></div>
                <?php if (count($all_gadget_images_for_js) > 1): ?>
                    <div class="gadget-indicator-area" id="gadgetIndicatorDisplay">1 / <?php echo count($all_gadget_images_for_js); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (!empty($comic['gadget_name'])): ?>
         <p class="gadget-name"><?php echo htmlspecialchars($comic['gadget_name']); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
if (!empty($other_staff_list)): ?>
<div class="left-column-section">
    <h4>Staff Albo</h4>
    <ul><?php foreach ($other_staff_list as $staff_member): ?><li><strong><?php echo htmlspecialchars($staff_member['role']); ?>:</strong> <a href="author_detail.php?slug=<?php echo urlencode($staff_member['slug']); ?>" title="Vedi scheda di <?php echo htmlspecialchars($staff_member['name']); ?>"><?php echo htmlspecialchars($staff_member['name']); ?></a></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!empty($custom_fields_data)): ?>
<div class="left-column-section">
    <h4>Informazioni Aggiuntive</h4>
    <ul><?php foreach ($custom_fields_data as $cf): ?><li><strong><?php echo htmlspecialchars($cf['label']); ?>:</strong> <?php echo htmlspecialchars($cf['value']); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>