/**
 * Mail Campaigns — list page
 */
document.addEventListener('DOMContentLoaded', function () {
    initAudiencePanels();
});

/**
 * Delete confirmation modal: point the form at the right campaign.
 */
function openDeleteModal(id, name) {
    var form = document.getElementById('deleteForm');
    var label = document.getElementById('deleteCampaignName');
    if (!form) return;

    form.action = window.location.pathname.replace(/\/$/, '') + '/' + id;
    if (label) label.textContent = name;

    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

/**
 * Show only the options that belong to the selected audience, so the form does
 * not ask for an Excel file when the list is being typed by hand.
 */
function initAudiencePanels() {
    var radios = document.querySelectorAll('.js-audience-radio');
    if (!radios.length) return;

    function apply() {
        var selected = document.querySelector('.js-audience-radio:checked');
        var value = selected ? selected.value : null;

        document.querySelectorAll('.js-audience-panel').forEach(function (panel) {
            panel.style.display = panel.getAttribute('data-audience') === value ? '' : 'none';
        });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', apply);
    });

    apply();
}

/**
 * Attachments are removed by their own form, not the campaign form, so the file
 * is gone even if the campaign edit is abandoned.
 */
function removeAttachment(id) {
    var form = document.getElementById('attachmentForm');
    if (!form || !window.campaignAttachmentUrl) return;

    form.action = window.campaignAttachmentUrl.replace('ATTACHMENT_ID', id);
    form.submit();
}
