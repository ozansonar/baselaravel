/**
 * Mail Campaigns — list page.
 *
 * Form tarafı (hedef kitle bölümleri, elle alıcı satırları, Excel önizlemesi)
 * campaign-form.js dosyasında.
 */

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
