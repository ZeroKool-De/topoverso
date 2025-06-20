<?php
// topolinolib/includes/comic_detail_parts/page_scripts.php
?>
<script>
// Funzione globale per i tab (riutilizzabile)
function openDetailTab(evt, tabName) {
    let i, tabcontent, tablinks;
    const tabContainer = evt.currentTarget.closest('.tabs-container');
    if (!tabContainer) return;

    tabcontent = tabContainer.querySelectorAll('.tab-pane');
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove('active');
    }
    
    tablinks = tabContainer.querySelectorAll('.tab-link');
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove('active');
    }
    
    const targetPane = document.getElementById(tabName);
    if (targetPane) {
        targetPane.classList.add('active');
    }

    if (evt && evt.currentTarget) {
      evt.currentTarget.classList.add('active');
    }
    
    if (history.replaceState) {
        const newHash = '#' + tabName;
        if (window.location.hash !== newHash) {
             history.replaceState(null, null, newHash);
        }
    }
}
// Rendi la funzione accessibile globalmente per l'attributo onclick
window.openDetailTab = openDetailTab;

// GESTIONE SCROLL - DEVE ESSERE PRIMA DI DOMContentLoaded
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

// Forza subito lo scroll in alto, poi vedremo se dobbiamo fare eccezioni
window.scrollTo(0, 0);

document.addEventListener('DOMContentLoaded', function () {
    
    // Determina se abbiamo un hash valido per una storia
    const hash = window.location.hash;
    let targetStoryItem = null;
    let hasValidStoryHash = false;
    
    if (hash && hash.startsWith('#story-item-')) {
        const storyIdFromHash = hash.substring('#story-item-'.length);
        targetStoryItem = document.getElementById('story-item-' + storyIdFromHash);
        if (targetStoryItem) {
            hasValidStoryHash = true;
        }
    }
    
    // Se non abbiamo un hash valido per una storia, assicuriamoci di essere in alto
    if (!hasValidStoryHash) {
        // Forza scroll in alto con più tentativi
        setTimeout(() => window.scrollTo(0, 0), 0);
        setTimeout(() => window.scrollTo(0, 0), 50);
        setTimeout(() => window.scrollTo(0, 0), 100);
    }

    // Funzioni per l'accordion
    function openPanel(item, trigger, content, scrollIntoView = false) {
        if (!item || !trigger || !content) return;
        content.removeAttribute('hidden');
        requestAnimationFrame(() => {
            content.style.maxHeight = content.scrollHeight + "px";
            if (scrollIntoView) {
                let offset = 20; // Offset base per non andare troppo in alto
                const mainHeader = document.querySelector('header');
                if (mainHeader && getComputedStyle(mainHeader).position === 'sticky') { 
                    offset += mainHeader.offsetHeight; 
                }
                
                // Calcola la posizione del trigger (non dell'intero item)
                const triggerRect = trigger.getBoundingClientRect();
                const absoluteTriggerTop = triggerRect.top + window.pageYOffset;
                
                // Posizione target: mostra il trigger nella parte alta del viewport
                const targetScrollPosition = Math.max(0, absoluteTriggerTop - offset);
                
                setTimeout(() => { 
                    window.scrollTo({ 
                        top: targetScrollPosition, 
                        behavior: 'smooth'
                    }); 
                }, 100); // Aumentato il delay per dare tempo all'animazione
            }
        });
        trigger.setAttribute('aria-expanded', 'true'); item.classList.add('is-open');
    }

    function closePanel(item, trigger, content) {
        if (!item || !trigger || !content) return;
        content.style.maxHeight = null;
        trigger.setAttribute('aria-expanded', 'false'); item.classList.remove('is-open');
        setTimeout(() => { if (trigger.getAttribute('aria-expanded') === 'false') { content.setAttribute('hidden', ''); }}, 300);
        
        // Ritorna una Promise che si risolve quando l'animazione di chiusura è completata
        return new Promise(resolve => {
            setTimeout(resolve, 300);
        });
    }

    const accordionContainers = document.querySelectorAll('.stories-accordion-container');
    accordionContainers.forEach(container => {
        async function closeAllOtherPanelsInContainer(currentItem) {
            const allItems = container.querySelectorAll('.story-accordion-item');
            const closingPromises = [];
            
            allItems.forEach(otherItem => {
                if (otherItem !== currentItem) {
                    const otherTrigger = otherItem.querySelector('.story-accordion-trigger');
                    const otherContent = otherItem.querySelector('.story-accordion-content');
                    if (otherTrigger && otherTrigger.getAttribute('aria-expanded') === 'true') {
                        closingPromises.push(closePanel(otherItem, otherTrigger, otherContent));
                    }
                }
            });
            
            // Aspetta che tutte le chiusure siano completate
            if (closingPromises.length > 0) {
                await Promise.all(closingPromises);
            }
        }

        container.addEventListener('click', async function(event) {
            const trigger = event.target.closest('.story-accordion-trigger');
            if (!trigger) return;

            if (event.target.tagName === 'A' && event.target.closest('.story-title-text')) {
                return;
            }

            const item = trigger.closest('.story-accordion-item');
            const content = item.querySelector('.story-accordion-content');
            const isCurrentlyOpen = trigger.getAttribute('aria-expanded') === 'true';

            if (isCurrentlyOpen) {
                closePanel(item, trigger, content);
            } else {
                // Prima chiudi tutti gli altri pannelli e aspetta che finiscano
                await closeAllOtherPanelsInContainer(item);
                // Poi apri il nuovo pannello con scroll
                openPanel(item, trigger, content, true);
            }
        });
    });

    // Gestione tab iniziale
    let initialTabId = 'descrizione-panel';

    if (hash) {
        const hashTargetId = hash.substring(1);
        const targetAccordionItem = document.getElementById(hashTargetId);
        
        if (targetAccordionItem && targetAccordionItem.classList.contains('story-accordion-item')) {
            const parentTabPane = targetAccordionItem.closest('.tab-pane');
            if (parentTabPane) {
                initialTabId = parentTabPane.id;
            }
        } else if (document.getElementById(hashTargetId) && document.getElementById(hashTargetId).classList.contains('tab-pane')) {
            initialTabId = hashTargetId;
        }
    }
    
    const initialButton = document.querySelector(`.tabs-container .tab-link[onclick*="'${initialTabId}'"]`);
    if(initialButton) {
        openDetailTab({ currentTarget: initialButton }, initialTabId);
    }

    // Gestione apertura accordion per story hash - solo se abbiamo un target valido
    if (hasValidStoryHash && targetStoryItem) {
        const targetTrigger = targetStoryItem.querySelector('.story-accordion-trigger');
        const targetContent = targetStoryItem.querySelector('.story-accordion-content');
        
        const parentContainer = targetStoryItem.closest('.stories-accordion-container');
        if(parentContainer){
            const allItemsInParent = parentContainer.querySelectorAll('.story-accordion-item');
            allItemsInParent.forEach(otherItem => {
                if (otherItem !== targetStoryItem) {
                     const otherTrigger = otherItem.querySelector('.story-accordion-trigger');
                     const otherContent = otherItem.querySelector('.story-accordion-content');
                     if (otherTrigger && otherTrigger.getAttribute('aria-expanded') === 'true') {
                         closePanel(otherItem, otherTrigger, otherContent);
                     }
                }
            });
        }
        
        setTimeout(() => {
            openPanel(targetStoryItem, targetTrigger, targetContent, true);
        }, 350); // Aumentato il delay per dare tempo alle eventuali chiusure
    } else {
        // Solo se non abbiamo un hash di storia specifico, apri la prima storia se è l'unica
        accordionContainers.forEach(container => {
             const allStoryItemsInContainer = container.querySelectorAll('.story-accordion-item');
             if (allStoryItemsInContainer.length === 1) {
                const firstItem = allStoryItemsInContainer[0];
                const trigger = firstItem.querySelector('.story-accordion-trigger');
                const content = firstItem.querySelector('.story-accordion-content');
                if (trigger && content) {
                    const isAlreadyOpen = trigger.getAttribute('aria-expanded') === 'true';
                    if (!isAlreadyOpen) {
                        openPanel(firstItem, trigger, content, false);
                    }
                }
             }
        });
    }
    
    const imageModal = document.getElementById('imageModal'); const modalImageElement = document.getElementById('modalImageContent'); const modalCaptionElement = document.getElementById('imageModalCaption'); const closeModalButton = document.querySelector('.image-modal-close'); function openImageModal(imageElement) { if (imageModal && modalImageElement && modalCaptionElement && imageElement && imageElement.src && !imageElement.src.includes('placeholder')) { imageModal.style.display = "block"; modalImageElement.src = imageElement.src; modalCaptionElement.innerHTML = imageElement.dataset.modalCaption || imageElement.alt || "Immagine Ingrandita"; document.body.style.overflow = 'hidden'; } } const allClickableImages = document.querySelectorAll('.clickable-image'); allClickableImages.forEach(img => { img.addEventListener('click', function() { openImageModal(this); }); }); function closeModalFunction() { if (imageModal) { imageModal.style.display = "none"; if(modalImageElement) modalImageElement.src = ""; if(modalCaptionElement) modalCaptionElement.innerHTML = ""; document.body.style.overflow = 'auto'; }} if (closeModalButton) closeModalButton.addEventListener('click', closeModalFunction); if (imageModal) { imageModal.addEventListener('click', function(event) { if (event.target === imageModal) { closeModalFunction(); }}); } document.addEventListener('keydown', function(event) { if (event.key === "Escape" && imageModal && imageModal.style.display === "block") { closeModalFunction(); }});
    const coversData = <?php echo json_encode($all_covers_for_js); ?>; let currentCoverIndex = 0; const coverImageEl = document.getElementById('currentComicCover'); const coverCaptionEl = document.getElementById('coverCaptionDisplay'); const coverIndicatorEl = document.getElementById('coverIndicatorDisplay'); const prevCoverBtn = document.getElementById('prevCoverBtn'); const nextCoverBtn = document.getElementById('nextCoverBtn'); function updateCoverDisplay() { if (!coverImageEl || !coversData[currentCoverIndex]) return; coverImageEl.src = coversData[currentCoverIndex].path; coverImageEl.alt = coversData[currentCoverIndex].alt; coverImageEl.dataset.modalCaption = coversData[currentCoverIndex].caption; if (coverCaptionEl) coverCaptionEl.textContent = coversData[currentCoverIndex].caption; if (coverIndicatorEl && coversData.length > 1) coverIndicatorEl.textContent = (currentCoverIndex + 1) + " / " + coversData.length; if (coverImageEl.src.includes('placeholder')) { coverImageEl.classList.remove('clickable-image'); coverImageEl.style.cursor = 'default'; } else { coverImageEl.classList.add('clickable-image'); coverImageEl.style.cursor = 'pointer'; } } if (coversData.length > 0) { updateCoverDisplay(); } const hasMultipleActualCovers = coversData.filter(c => !c.path.includes('placeholder')).length > 1; if (coversData.length > 1 && hasMultipleActualCovers) { if(prevCoverBtn) prevCoverBtn.style.display = 'block'; if(nextCoverBtn) nextCoverBtn.style.display = 'block'; if(coverIndicatorEl) coverIndicatorEl.style.display = 'block'; } else { if(prevCoverBtn) prevCoverBtn.style.display = 'none'; if(nextCoverBtn) nextCoverBtn.style.display = 'none'; if(coverIndicatorEl) coverIndicatorEl.style.display = 'none'; } if(prevCoverBtn) prevCoverBtn.addEventListener('click', function() { currentCoverIndex--; if (currentCoverIndex < 0) currentCoverIndex = coversData.length - 1; updateCoverDisplay(); }); if(nextCoverBtn) nextCoverBtn.addEventListener('click', function() { currentCoverIndex++; if (currentCoverIndex >= coversData.length) currentCoverIndex = 0; updateCoverDisplay(); });
    const gadgetImagesData = <?php echo json_encode($all_gadget_images_for_js); ?>; let currentGadgetImageIndex = 0; const gadgetImageEl = document.getElementById('currentGadgetImage'); const gadgetCaptionEl = document.getElementById('gadgetCaptionDisplay'); const gadgetIndicatorEl = document.getElementById('gadgetIndicatorDisplay'); const prevGadgetBtn = document.getElementById('prevGadgetBtn'); const nextGadgetBtn = document.getElementById('nextGadgetBtn'); function updateGadgetDisplay() { if (!gadgetImageEl || !gadgetImagesData[currentGadgetImageIndex]) return; const currentGadget = gadgetImagesData[currentGadgetImageIndex]; gadgetImageEl.src = currentGadget.path; gadgetImageEl.alt = currentGadget.alt; gadgetImageEl.dataset.modalCaption = currentGadget.caption; if (gadgetCaptionEl) gadgetCaptionEl.textContent = currentGadget.caption; if (gadgetIndicatorEl && gadgetImagesData.length > 1) { gadgetIndicatorEl.textContent = (currentGadgetImageIndex + 1) + " / " + gadgetImagesData.length; gadgetIndicatorEl.style.display = 'block';} else if(gadgetIndicatorEl){ gadgetIndicatorEl.style.display = 'none'; } if (gadgetImageEl.src.includes('placeholder')) { gadgetImageEl.classList.remove('clickable-image'); gadgetImageEl.style.cursor = 'default'; } else { gadgetImageEl.classList.add('clickable-image'); gadgetImageEl.style.cursor = 'pointer'; } } if (gadgetImageEl && gadgetImagesData.length > 0) { updateGadgetDisplay(); const hasMultipleActualGadgetImages = gadgetImagesData.filter(g => g.path && !g.path.includes('placeholder')).length > 1; if (gadgetImagesData.length > 1 && hasMultipleActualGadgetImages) { if(prevGadgetBtn) prevGadgetBtn.style.display = 'block'; if(nextGadgetBtn) nextGadgetBtn.style.display = 'block'; if(gadgetIndicatorEl) gadgetIndicatorEl.style.display = 'block'; } else { if(prevGadgetBtn) prevGadgetBtn.style.display = 'none'; if(nextGadgetBtn) nextGadgetBtn.style.display = 'none'; if(gadgetIndicatorEl) gadgetIndicatorEl.style.display = 'none'; }} if(prevGadgetBtn) { prevGadgetBtn.addEventListener('click', function() { currentGadgetImageIndex--; if (currentGadgetImageIndex < 0) { currentGadgetImageIndex = gadgetImagesData.length - 1; } updateGadgetDisplay(); });} if(nextGadgetBtn) { nextGadgetBtn.addEventListener('click', function() { currentGadgetImageIndex++; if (currentGadgetImageIndex >= gadgetImagesData.length) { currentGadgetImageIndex = 0; } updateGadgetDisplay(); });}
    const toggleReportBtn = document.getElementById('toggleReportFormBtn'); const reportFormContainer = document.getElementById('reportErrorFormContainer'); const cancelReportBtn = document.getElementById('cancelReportBtn'); const storySelectGroup = document.getElementById('storySelectGroupReport'); const reportTypeRadios = document.querySelectorAll('input[name="report_type_target"]'); function toggleStorySelectReport(show) { if (storySelectGroup) { storySelectGroup.style.display = show ? 'block' : 'none'; const storySelectInput = storySelectGroup.querySelector('select'); if (storySelectInput) { storySelectInput.required = show; if (!show) storySelectInput.value = ''; }}} if (toggleReportBtn && reportFormContainer) { toggleReportBtn.addEventListener('click', function() { const isVisible = reportFormContainer.style.display === 'block'; reportFormContainer.style.display = isVisible ? 'none' : 'block'; if (!isVisible) { const generalRadio = document.querySelector('input[name="report_type_target"][value="general"]'); if(generalRadio) generalRadio.checked = true; toggleStorySelectReport(false); reportFormContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }});} if (cancelReportBtn && reportFormContainer) { cancelReportBtn.addEventListener('click', function() { reportFormContainer.style.display = 'none'; const form = reportFormContainer.querySelector('form'); if (form) form.reset(); toggleStorySelectReport(false); });} reportTypeRadios.forEach(radio => { radio.addEventListener('change', function() { toggleStorySelectReport(this.value === 'story'); });}); const initialReportType = document.querySelector('input[name="report_type_target"]:checked'); toggleStorySelectReport(initialReportType && initialReportType.value === 'story'); const feedbackAnchor = document.getElementById('report-feedback-anchor'); if (feedbackAnchor && <?php echo json_encode(!empty($report_message)); ?>) { feedbackAnchor.scrollIntoView({ behavior: 'smooth', block: 'start' });}
    const allRatingForms = document.querySelectorAll('.star-rating-form'); allRatingForms.forEach(form => { const starInputs = form.querySelectorAll('input[type="radio"]'); starInputs.forEach(input => { input.addEventListener('change', function() { form.submit(); }); }); });
});
</script>