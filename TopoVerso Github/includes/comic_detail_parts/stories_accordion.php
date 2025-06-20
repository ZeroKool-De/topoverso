<?php
// topolinolib/includes/comic_detail_parts/stories_accordion.php
?>
<h3 class="stories-section-title">Storie Contenute</h3>
<div class="stories-accordion-container">
    <?php if (!empty($stories_data)): ?>
        <?php foreach ($stories_data as $index => $story): ?>
            <?php
                  $is_targeted_by_hash = ($active_hash_target_name === 'story-item-'.$story['story_id'] || $active_hash_target_name === 'story-panel-'.$story['story_id'] || $active_hash_target_name === 'story-'.$story['story_id']);
                  $open_by_default = ($index === 0 && empty($active_hash_target_name) && count($stories_data) === 1);
                  $is_active = $is_targeted_by_hash || $open_by_default;
            ?>
            <div class="story-accordion-item <?php echo $is_active ? 'is-open' : ''; ?>" id="story-item-<?php echo $story['story_id']; ?>">
                <button class="story-accordion-trigger" aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>" aria-controls="story-panel-<?php echo $story['story_id']; ?>" id="story-trigger-<?php echo $story['story_id']; ?>">
                    <span class="story-title-text">
                        <?php if ($story['is_ministory'] == 1): ?>
                            <span class="ministory-badge" title="Mini-storia/Gag">GAG</span>
                        <?php endif; ?>
                        <?php
                        $display_story_title = '';
                        if (!empty($story['story_title_main'])) {
                            $display_story_title = '<strong>' . htmlspecialchars($story['story_title_main']) . '</strong>';
                            if (!empty($story['part_number'])) {
                                $part_specific_title = htmlspecialchars($story['title']);
                                $expected_part_title = 'Parte ' . htmlspecialchars($story['part_number']);
                                if ($part_specific_title !== htmlspecialchars($story['story_title_main']) && $part_specific_title !== $expected_part_title && strtolower($part_specific_title) !== strtolower($expected_part_title) ) { $display_story_title .= ': ' . $part_specific_title . ' <em>(Parte ' . htmlspecialchars($story['part_number']) . ')</em>'; } else { $display_story_title .= ' - Parte ' . htmlspecialchars($story['part_number']); }
                            } elseif (!empty($story['title']) && strtolower($story['title']) !== strtolower($story['story_title_main'])) { $display_story_title .= ': ' . htmlspecialchars($story['title']); }
                            if ($story['total_parts']) { $display_story_title .= ' (di ' . htmlspecialchars($story['total_parts']) . ')'; }
                        } else { $display_story_title = htmlspecialchars($story['title']); } 
                        echo $display_story_title;
                        ?>
                        <?php 
                        if (!empty($story['related_historical_event_id']) && !empty($story['related_historical_event_title'])) {
                            $event_link_url = BASE_URL . 'cronologia_topoverso.php';
                            $event_link_params = [];
                            if (!empty($story['related_historical_event_category'])) {
                                $event_link_params['category'] = $story['related_historical_event_category'];
                            }
                            if (!empty($event_link_params)) {
                                $event_link_url .= '?' . http_build_query($event_link_params);
                            }
                            $event_link_url .= '#event-item-' . $story['related_historical_event_id'];
                            echo ' <a href="' . htmlspecialchars($event_link_url) . '" class="historical-event-story-badge" title="Evento storico collegato: ' . htmlspecialchars($story['related_historical_event_title']) . '" onclick="event.stopPropagation();">';
                            echo '📜 Evento Correlato'; 
                            echo '</a>';
                        }
                        ?>
                        <?php if (isset($story['story_comment_count']) && $story['story_comment_count'] > 0): ?>
                            <a href="thread.php?id=<?php echo $story['story_thread_id']; ?>" class="story-comment-badge" title="Leggi i <?php echo $story['story_comment_count']; ?> commenti per questa storia" onclick="event.stopPropagation();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor"><path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-2.5a2 2 0 0 0-1.6.8L8 14.333 6.1 11.8a2 2 0 0 0-1.6-.8H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2.5a1 1 0 0 1 .8.4l1.9 2.533a1 1 0 0 0 1.6 0l1.9-2.533a1 1 0 0 1 .8-.4H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/></svg>
                                <?php echo $story['story_comment_count']; ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($story['series_id'] && !empty($story['series_title'])): ?>
                            <span class="story-series-indicator">(Serie Editoriale: <a href="series_detail.php?slug=<?php echo htmlspecialchars($story['series_slug']); ?>" onclick="event.stopPropagation();"><?php echo htmlspecialchars($story['series_title']); ?></a><?php if ($story['series_episode_number']): ?> - Ep. <?php echo htmlspecialchars($story['series_episode_number']); ?><?php endif; ?>)</span>
                        <?php endif; ?>
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
                            <?php if (!empty($story['other_parts'])):?>
                                <div class="story-meta-section story-other-parts-section"><strong>Altre Parti di Questa Storia:</strong><ul><?php foreach ($story['other_parts'] as $other_part): ?><li><a href="comic_detail.php?slug=<?php echo urlencode($other_part['other_comic_slug']); ?>#story-item-<?php echo $other_part['story_id']; ?>"><?php $part_display = ''; if (!empty($other_part['other_part_number'])) { $part_display = 'Parte ' . htmlspecialchars($other_part['other_part_number']); if (!empty($other_part['other_story_part_title']) && $other_part['other_story_part_title'] !== ('Parte ' . $other_part['other_part_number']) && $other_part['other_story_part_title'] !== $story['story_title_main'] ) { $part_display = htmlspecialchars($other_part['other_story_part_title']) . ' <em>(' . $part_display . ')</em>';}} else { $part_display = htmlspecialchars($other_part['other_story_part_title']); } echo $part_display; ?> (in Topolino #<?php echo htmlspecialchars($other_part['other_comic_issue_number']); ?><?php if(!empty($other_part['other_comic_title'])): echo ' - '.htmlspecialchars($other_part['other_comic_title']); endif; ?>)</a></li><?php endforeach; ?></ul></div>
                            <?php endif; ?>
                            <?php if (!empty($story['authors'])): ?>
                                <div class="story-meta-section story-authors-section"><strong>Autori/Artisti:</strong><ul><?php foreach ($story['authors'] as $author): ?><li><a href="author_detail.php?slug=<?php echo urlencode($author['slug']); ?>" title="Vedi scheda di <?php echo htmlspecialchars($author['name']); ?>"><?php echo htmlspecialchars($author['name']); ?></a> (<?php echo htmlspecialchars($author['role']); ?>)</li><?php endforeach; ?></ul></div>
                            <?php endif; ?>
                            <?php if (!empty($story['characters_in_story'])): ?>
                                <div class="story-meta-section story-characters-section"><strong>Personaggi:</strong><ul><?php foreach ($story['characters_in_story'] as $character_in_story): ?><li><a href="character_detail.php?slug=<?php echo urlencode($character_in_story['slug']); ?>" title="Vedi scheda di <?php echo htmlspecialchars($character_in_story['name']); ?>"><?php if ($character_in_story['character_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($character_in_story['character_image']); ?>" alt="" class="character-icon"><?php else: echo generate_image_placeholder(htmlspecialchars($character_in_story['name']), 20, 20, 'character-icon img-placeholder'); endif; ?><?php echo htmlspecialchars($character_in_story['name']); ?>
                                    <?php if ($character_in_story['first_appearance_story_id'] == $story['story_id'] && $character_in_story['first_appearance_comic_id'] == $comic_id): ?>
                                        <span class="first-appearance-badge-story" title="Prima apparizione di <?php echo htmlspecialchars($character_in_story['name']); ?> in questa storia/albo">1ª App.</span>
                                    <?php endif; ?>
                                    </a></li><?php endforeach; ?></ul></div>
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
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                echo ($i <= $story_rating_avg + 0.24) ? '★' : '☆';
                            }
                            ?>
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
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Nessuna storia specificata per questo numero.</p>
    <?php endif; ?>
</div>