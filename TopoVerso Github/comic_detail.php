<?php
// File principale che orchestra l'inclusione delle varie parti.

require_once 'config/config.php';

// 1. Logica di Business e Recupero Dati
require_once 'includes/comic_detail_parts/data_logic.php';

// 2. Header della Pagina
require_once 'includes/header.php';
?>

<div class="container page-content-container">

    <div id="report-feedback-anchor"></div>
    <?php if ($report_message): ?>
        <div class="message <?php echo htmlspecialchars($report_message_type); ?>" style="margin-top:15px; margin-bottom:15px;">
            <?php echo htmlspecialchars($report_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($collection_message): ?>
        <div class="message <?php echo htmlspecialchars($collection_message_type); ?>">
            <?php echo htmlspecialchars($collection_message); ?>
        </div>
    <?php endif; ?>

    <div class="comic-detail-layout">

        <div class="comic-detail-left-column">
            <?php include 'includes/comic_detail_parts/cover_gallery.php'; ?>
            <?php include 'includes/comic_detail_parts/comic_info_panel.php'; ?>
        </div>

        <div class="comic-detail-right-column">
            <div class="comic-main-title">
                <h1>Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></h1>
                <?php if (!empty($comic['title'])): ?><h2><em><?php echo htmlspecialchars($comic['title']); ?></em></h2><?php endif; ?>
            </div>

            <?php include 'includes/comic_detail_parts/discussion_link.php'; ?>
            <?php include 'includes/comic_detail_parts/report_error_form.php'; ?>

            <div class="average-rating-display">
                <strong>Voto Utenti:</strong>
                <?php if ($average_rating !== null): ?><span class="stars"><?php for ($s_i = 1; $s_i <= 5; $s_i++) { if ($s_i <= $average_rating) { echo '★'; } elseif ($s_i - 0.5 <= $average_rating && $average_rating < $s_i) { echo '★'; } else { echo '☆'; }} ?></span> (<?php echo number_format($average_rating, 1); ?>/5 da <?php echo $rating_count; ?> vot<?php echo ($rating_count == 1) ? 'o' : 'i'; ?>)
                <?php else: ?><span class="no-rating">Non ancora votato dagli utenti.</span><?php endif; ?>
            </div>
            
            <?php include 'includes/comic_detail_parts/user_actions.php'; ?>

            <div class="tabs-container" style="margin-top: 30px;">
                <nav class="tab-nav">
                    <button class="tab-link active" onclick="openDetailTab(event, 'descrizione-panel')">Descrizione</button>
                    <button class="tab-link" onclick="openDetailTab(event, 'storie-panel')">Storie</button>
                    
                    <?php if (!empty($ministories_data)): ?>
                        <button class="tab-link" onclick="openDetailTab(event, 'ministorie-panel')">Mini-Storie</button>
                    <?php endif; ?>

                    <?php if (!empty($related_historical_events_period)): ?>
                        <button class="tab-link" onclick="openDetailTab(event, 'eventi-panel')">Eventi Storici</button>
                    <?php endif; ?>
                </nav>
                <div class="tab-content-panels">
                    <div id="descrizione-panel" class="tab-pane active">
                        <?php include 'includes/comic_detail_parts/description.php'; ?>
                    </div>
                    <div id="storie-panel" class="tab-pane">
                        <?php include 'includes/comic_detail_parts/stories_accordion.php'; ?>
                    </div>
                    
                    <?php if (!empty($ministories_data)): ?>
                    <div id="ministorie-panel" class="tab-pane">
                        <h3 class="stories-section-title">Mini-Storie e Gag</h3>
                        <div class="stories-accordion-container">
                            <?php if (!empty($ministories_data)): ?>
                                <?php foreach ($ministories_data as $index => $story): ?>
                                    <?php
                                        $is_targeted_by_hash = ($active_hash_target_name === 'story-item-'.$story['story_id']);
                                        $open_by_default = ($index === 0 && empty($active_hash_target_name) && count($ministories_data) === 1);
                                        $is_active = $is_targeted_by_hash || $open_by_default;
                                    ?>
                                    <div class="story-accordion-item <?php echo $is_active ? 'is-open' : ''; ?>" id="story-item-<?php echo $story['story_id']; ?>">
                                        <button class="story-accordion-trigger" aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>" aria-controls="story-panel-<?php echo $story['story_id']; ?>" id="story-trigger-<?php echo $story['story_id']; ?>">
                                            <span class="story-title-text">
                                                <?php echo htmlspecialchars($story['title']); ?>
                                            </span>
                                            <span class="accordion-icon" aria-hidden="true"></span>
                                        </button>
                                        <div id="story-panel-<?php echo $story['story_id']; ?>" class="story-accordion-content" role="region" aria-labelledby="story-trigger-<?php echo $story['story_id']; ?>" <?php echo !$is_active ? 'hidden' : ''; ?>>
                                            <div class="story-content-inner-layout">
                                                <?php if ($story['first_page_image']): ?>
                                                    <div class="story-expanded-image-container"><img src="<?php echo UPLOADS_URL . htmlspecialchars($story['first_page_image']); ?>" alt="Prima pagina di: <?php echo htmlspecialchars($story['title']); ?>" class="story-expanded-first-page-img <?php echo (strpos($story['first_page_image'], 'placeholder') === false) ? 'clickable-image' : ''; ?>" data-modal-caption="Prima pagina di: <?php echo htmlspecialchars($story['title']); ?>"></div>
                                                <?php endif; ?>
                                                <div class="story-expanded-text-content">
                                                    <?php if (!empty($story['notes'])): ?><div class="story-notes-section"><h4>Note sulla Storia:</h4><p><?php echo nl2br(htmlspecialchars($story['notes'])); ?></p></div><?php endif; ?>
                                                    <?php if (!empty($story['authors'])): ?>
                                                        <div class="story-meta-section story-authors-section"><strong>Autori/Artisti:</strong><ul><?php foreach ($story['authors'] as $author): ?><li><a href="author_detail.php?id=<?php echo $author['person_id']; ?>" title="Vedi scheda di <?php echo htmlspecialchars($author['name']); ?>"><?php echo htmlspecialchars($author['name']); ?></a> (<?php echo htmlspecialchars($author['role']); ?>)</li><?php endforeach; ?></ul></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($story['characters_in_story'])): ?>
                                                        <div class="story-meta-section story-characters-section"><strong>Personaggi:</strong><ul><?php foreach ($story['characters_in_story'] as $character_in_story): ?><li><a href="character_detail.php?id=<?php echo $character_in_story['character_id']; ?>" title="Vedi scheda di <?php echo htmlspecialchars($character_in_story['name']); ?>"><?php if ($character_in_story['character_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($character_in_story['character_image']); ?>" alt="" class="character-icon"><?php else: echo generate_image_placeholder(htmlspecialchars($character_in_story['name']), 20, 20, 'character-icon img-placeholder'); endif; ?><?php echo htmlspecialchars($character_in_story['name']); ?></a></li><?php endforeach; ?></ul></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="story-rating-widget">
                                        <div class="average-rating-display-compact">
                                            <span class="widget-label">Voto Storia:</span>
                                            <?php
                                                $story_rating_avg = $stories_ratings_info[$story['story_id']]['avg'] ?? 0;
                                                $story_rating_count = $stories_ratings_info[$story['story_id']]['count'] ?? 0;
                                            ?>
                                            <?php if ($story_rating_count > 0): ?>
                                                <span class="stars" title="Voto medio: <?php echo $story_rating_avg; ?> su 5">
                                                    <?php for ($i = 1; $i <= 5; $i++) { echo ($i <= $story_rating_avg + 0.24) ? '★' : '☆'; } ?>
                                                </span>
                                                <small>(<?php echo $story_rating_avg; ?>/5 su <?php echo $story_rating_count; ?> voti)</small>
                                            <?php else: ?>
                                                <span class="no-rating">Nessun voto</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-rating-form-compact">
                                            <?php if (isset($_COOKIE['voted_story_' . $story['story_id']])): ?>
                                                <span class="thank-you-vote">Grazie per il tuo voto!</span>
                                            <?php else: ?>
                                                <form action="<?php echo BASE_URL; ?>actions/rating_actions.php" method="POST" class="star-rating-form" title="Vota questa storia!">
                                                    <input type="hidden" name="entity_type" value="story">
                                                    <input type="hidden" name="entity_id" value="<?php echo $story['story_id']; ?>">
                                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>#story-item-<?php echo $story['story_id']; ?>">
                                                    <div class="stars-input compact">
                                                        <input type="radio" id="story-<?php echo $story['story_id']; ?>-star5" name="rating" value="5" /><label for="story-<?php echo $story['story_id']; ?>-star5" title="5 stelle">★</label>
                                                        <input type="radio" id="story-<?php echo $story['story_id']; ?>-star4" name="rating" value="4" /><label for="story-<?php echo $story['story_id']; ?>-star4" title="4 stelle">★</label>
                                                        <input type="radio" id="story-<?php echo $story['story_id']; ?>-star3" name="rating" value="3" /><label for="story-<?php echo $story['story_id']; ?>-star3" title="3 stelle">★</label>
                                                        <input type="radio" id="story-<?php echo $story['story_id']; ?>-star2" name="rating" value="2" /><label for="story-<?php echo $story['story_id']; ?>-star2" title="2 stelle">★</label>
                                                        <input type="radio" id="story-<?php echo $story['story_id']; ?>-star1" name="rating" value="1" /><label for="story-<?php echo $story['story_id']; ?>-star1" title="1 stella">★</label>
                                                    </div>
                                                    <button type="submit" class="btn btn-sm btn-primary" style="font-size:0.8em; padding: 2px 8px; margin-left: 5px;">Vota</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Nessuna mini-storia trovata per questo albo.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($related_historical_events_period)): ?>
                        <div id="eventi-panel" class="tab-pane">
                            <?php include 'includes/comic_detail_parts/historical_events.php'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
    </div>
    
    <p style="margin-top:30px;"><a href="index.php" class="btn btn-secondary">&laquo; Torna all'elenco principale</a></p>
</div>

<?php include 'includes/comic_detail_parts/comment_form.php'; ?>

<?php include 'includes/comic_detail_parts/sticky_nav.php'; ?>
<?php include 'includes/comic_detail_parts/image_modal.php'; ?>

<?php include 'includes/comic_detail_parts/page_scripts.php'; ?>

<?php
// 3. Footer della Pagina
require_once 'includes/footer.php';

if(isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>