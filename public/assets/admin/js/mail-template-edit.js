/**
 * Mail Template Edit Page
 */
document.addEventListener('DOMContentLoaded', function () {
    initCopyButtons();
    initVariableInsert();
    initPreview();
});

/**
 * Copy variable to clipboard when copy button clicked.
 */
function initCopyButtons() {
    document.querySelectorAll('.mt-var-copy').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var text = this.getAttribute('data-clipboard');
            navigator.clipboard.writeText(text).then(function () {
                var icon = btn.querySelector('i');
                icon.className = 'bi bi-check-lg text-success';
                setTimeout(function () {
                    icon.className = 'bi bi-clipboard';
                }, 1500);
            });
        });
    });
}

/**
 * Insert variable at cursor position in TinyMCE editor.
 */
function initVariableInsert() {
    document.querySelectorAll('.mt-var-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            if (e.target.closest('.mt-var-copy')) return;

            var varText = this.getAttribute('data-var');
            var editor = typeof tinymce !== 'undefined' ? tinymce.get('body') : null;

            if (editor) {
                editor.insertContent(varText);
                editor.focus();
            } else {
                var textarea = document.getElementById('body');
                if (!textarea) return;
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var value = textarea.value;
                textarea.value = value.substring(0, start) + varText + value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + varText.length;
                textarea.focus();
            }
        });
    });
}

/**
 * Preview template with example data via AJAX.
 */
function initPreview() {
    var btnPreview = document.getElementById('btnPreview');
    if (!btnPreview) return;

    btnPreview.addEventListener('click', function () {
        var editor = typeof tinymce !== 'undefined' ? tinymce.get('body') : null;
        var subject = document.getElementById('subject').value;
        var body = editor ? editor.getContent() : document.getElementById('body').value;
        var form = document.getElementById('templateForm');
        var action = form.getAttribute('action');
        var previewUrl = action.replace(/\/mail-templates\/(\d+)$/, '/mail-templates/$1/preview');

        btnPreview.disabled = true;
        btnPreview.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Yükleniyor...';

        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ subject: subject, body: body })
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            document.getElementById('previewSubject').textContent = data.subject;

            var frame = document.getElementById('previewFrame');
            var doc = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(data.html);
            doc.close();

            var modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        })
        .catch(function (err) {
            console.error('Preview error:', err);
            alert('Önizleme yüklenirken hata oluştu.');
        })
        .finally(function () {
            btnPreview.disabled = false;
            btnPreview.innerHTML = '<i class="bi bi-eye me-1"></i> Önizle';
        });
    });
}
