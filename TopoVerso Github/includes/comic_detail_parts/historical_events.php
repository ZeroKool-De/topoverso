<?php
// topolinolib/includes/comic_detail_parts/historical_events.php
?>
<?php if (!empty($related_historical_events_period)): ?>
<div class="historical-events-section">
    <h4>Eventi Storici Collegati a Questo Periodo/Albo</h4>
    <?php foreach($related_historical_events_period as $event_period): ?>
        <div class="related-event-item">
            <?php if (!empty($event_period['event_image_path'])): ?>
                <img src="<?php echo UPLOADS_URL . htmlspecialchars($event_period['event_image_path']); ?>" 
                     alt="<?php echo htmlspecialchars($event_period['event_title']); ?>" 
                     style="max-width: 150px; height: auto; border-radius: 4px; margin-right: 15px; margin-bottom: 5px; float: left; border:1px solid #eee;">
            <?php endif; ?>
            <a href="<?php echo BASE_URL . 'cronologia_topoverso.php?category=' . urlencode($event_period['category'] ?? '') . '#event-item-' . $event_period['event_id']; ?>" class="event-title-link">
                <?php echo htmlspecialchars($event_period['event_title']); ?>
            </a>
            <span class="event-period">
                <?php echo format_date_italian($event_period['event_date_start']); ?>
                <?php if ($event_period['event_date_end'] && $event_period['event_date_end'] !== $event_period['event_date_start']): ?>
                    - <?php echo format_date_italian($event_period['event_date_end']); ?>
                <?php endif; ?>
            </span>
            <?php if($event_period['category']): ?>
                <span class="event-category-detail"><?php echo htmlspecialchars($event_period['category']); ?></span>
            <?php endif; ?>
            <p class="event-description-preview">
                <?php echo htmlspecialchars(substr($event_period['event_description'], 0, 150)); ?>
                <?php if(strlen($event_period['event_description']) > 150) echo "..."; ?>
            </p>
        </div>
    <?php endforeach; ?>
     <p style="text-align:right; margin-top:10px;"><a href="<?php echo BASE_URL; ?>cronologia_topoverso.php" class="btn btn-sm btn-secondary">Vedi tutta la cronologia &raquo;</a></p>
</div>
<?php endif; ?>