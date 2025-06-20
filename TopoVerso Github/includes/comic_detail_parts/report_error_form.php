<?php
// topolinolib/includes/comic_detail_parts/report_error_form.php
?>
<div class="report-error-button-container">
    <button id="toggleReportFormBtn" class="btn report-error-button">Segnala Errore / Info Mancante</button>
</div>

<div id="reportErrorFormContainer" class="report-error-form-container">
    <h4>Segnala un errore per Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></h4>
    <form action="comic_detail.php?id=<?php echo $comic_id; ?>" method="POST">
        <input type="hidden" name="submit_error_report" value="1">
        <input type="hidden" name="comic_id_report" value="<?php echo $comic_id; ?>">
        <input type="hidden" name="reported_issue_number_display" value="<?php echo htmlspecialchars($comic['issue_number']); ?>">
        <div class="form-group">
            <label>La segnalazione riguarda:</label>
            <label style="font-weight:normal;"><input type="radio" name="report_type_target" value="general" checked onchange="toggleStorySelectReport(false)"> Informazioni generali sull'albo</label>
            <br>
            <label style="font-weight:normal;"><input type="radio" name="report_type_target" value="story" onchange="toggleStorySelectReport(true)"> Una storia specifica</label>
        </div>
        <div class="form-group story-select-group" id="storySelectGroupReport">
            <label for="story_id_report">Seleziona la storia:</label>
            <select name="story_id_report" id="story_id_report" class="form-control">
                <option value="">-- Seleziona una storia --</option>
                <?php foreach ($stories_data as $story_item_report): ?>
                    <option value="<?php echo $story_item_report['story_id']; ?>">
                        <?php echo htmlspecialchars($story_item_report['title']); ?>
                        <?php if ($story_item_report['story_title_main'] && $story_item_report['story_title_main'] !== $story_item_report['title']) echo " (Saga: " . htmlspecialchars($story_item_report['story_title_main']) . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="report_text">Descrivi l'errore o l'informazione mancante:</label>
            <textarea name="report_text" id="report_text" rows="4" required minlength="10" placeholder="Sii il più specifico possibile..."></textarea>
        </div>
        <div class="form-group">
            <label for="reporter_email">La tua email (opzionale):</label>
            <input type="email" name="reporter_email" id="reporter_email" placeholder="nome@esempio.com">
            <small>Non verrà pubblicata. Potremmo usarla per chiederti chiarimenti.</small>
        </div>
        <button type="submit" class="btn">Invia Segnalazione</button>
        <button type="button" id="cancelReportBtn" class="btn btn-cancel-report">Annulla</button>
    </form>
</div>