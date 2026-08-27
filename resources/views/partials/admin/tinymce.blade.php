{{-- TinyMCE 8 (Self-hosted, GPL) --}}
@php
    $tinymceSelector = $tinymceSelector ?? '#content';
@endphp

{{-- Seçici modalı editörle birlikte geliyor: ikisi aynı yapılandırmayı
     paylaştığı için editörün olduğu her ekranda düğme çalışır durumda olsun. --}}
@include('partials.admin.file-picker')

@push('scripts')
<script src="{{ asset('assets/vendor/tinymce/tinymce.min.js') }}" referrerpolicy="origin"></script>
<script>
(function () {
    'use strict';

    var selector = '{{ $tinymceSelector }}';
    var editorElement = document.querySelector(selector);
    if (!editorElement) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    tinymce.init({
        selector: selector,
        license_key: 'gpl',
        language: 'tr',
        language_url: '{{ asset("assets/vendor/tinymce/langs/tr.js") }}',
        base_url: '{{ asset("assets/vendor/tinymce") }}',

        // Dark theme
        skin: 'oxide-dark',
        content_css: 'dark',

        // Dimensions
        height: 500,
        min_height: 400,
        resize: true,

        // Plugins
        plugins: [
            'accordion',
            'advlist',
            'anchor',
            'autolink',
            'autosave',
            'charmap',
            'code',
            'codesample',
            'directionality',
            'emoticons',
            'fullscreen',
            'help',
            'image',
            'importcss',
            'insertdatetime',
            'link',
            'lists',
            'media',
            'nonbreaking',
            'pagebreak',
            'preview',
            'quickbars',
            'save',
            'searchreplace',
            'table',
            'visualblocks',
            'visualchars',
            'wordcount'
        ],

        // Toolbar
        toolbar: [
            'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor removeformat',
            'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | charmap emoticons codesample blockquote hr',
            'searchreplace | visualblocks code fullscreen preview | help'
        ],

        // Menu bar
        menubar: 'file edit view insert format tools table help',

        // Quick toolbars
        quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote',
        quickbars_insert_toolbar: 'quickimage quicktable hr',

        // Content style
        content_style: 'body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.7; color: #e0e0e0; } img { max-width: 100%; height: auto; } table { max-width: 100%; }',

        // Image upload
        images_upload_handler: function (blobInfo) {
            return new Promise(function (resolve, reject) {
                var formData = new FormData();
                formData.append('upload', blobInfo.blob(), blobInfo.filename());
                formData.append('_token', csrfToken);

                fetch('{{ route("admin.upload.ckeditor") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Yükleme hatası: ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.url) {
                        resolve(data.url);
                    } else {
                        reject('Sunucudan geçerli URL alınamadı.');
                    }
                })
                .catch(function (err) {
                    reject(err.message || 'Yükleme başarısız');
                });
            });
        },

        // "Bir resim arayın" düğmesi: kendi dosya seçicimizi açıyor.
        //
        // TinyMCE'nin GPL derlemesinde dosya yöneticisi yok (o özellik ücretli
        // paketlerde). Bu kanca, hangi yönetici kullanılırsa kullanılsın
        // bağlantı noktası; buraya panelin kendi seçicisi takılı.
        file_picker_callback: function (callback, value, meta) {
            if (!window.FilePicker) {
                return;
            }

            window.FilePicker.open({
                type: meta.filetype === 'image' ? 'image' : '',
                onSelect: function (dosya) {
                    callback(dosya.url, { alt: '', title: dosya.name });
                }
            });
        },

        // File picker for images
        file_picker_types: 'image',
        automatic_uploads: true,
        images_reuse_filename: true,

        // Görsel adresleri kök göreli yazılıyor (/uploads/...).
        //
        // TinyMCE varsayılanı belgeye göreli yol üretiyordu: /admin/kampanyalar/yeni
        // sayfasında eklenen bir görsel "../../uploads/..." olarak kaydediliyordu.
        // Bu yol sayfa adresine bağlı olduğu için taşındığında kırılıyor ve daha
        // kötüsü kampanya mailinde hiç çalışmıyordu: CampaignMail görselleri maile
        // gömerken yalnızca "/uploads/" ile başlayan yolları tanıyor, göreli yol
        // eşleşmediği için görsel sessizce dışarıda kalıyordu.
        relative_urls: false,
        remove_script_host: true,
        document_base_url: '{{ rtrim(url('/'), '/') }}/',

        // Link options
        link_default_target: '_blank',
        link_assume_external_targets: true,

        // Table options
        table_responsive_width: true,

        // Autosave
        autosave_interval: '30s',
        autosave_restore_when_empty: true,
        autosave_retention: '30m',

        // Other settings
        browser_spellcheck: true,
        contextmenu: 'link image table',
        promotion: false,
        branding: false,

        // Paste
        paste_data_images: true,

        // Code sample languages
        codesample_languages: [
            { text: 'HTML/XML', value: 'markup' },
            { text: 'JavaScript', value: 'javascript' },
            { text: 'CSS', value: 'css' },
            { text: 'PHP', value: 'php' },
            { text: 'Python', value: 'python' },
            { text: 'Java', value: 'java' },
            { text: 'C#', value: 'csharp' },
            { text: 'Bash', value: 'bash' },
            { text: 'SQL', value: 'sql' },
            { text: 'JSON', value: 'json' }
        ],

        // Setup callback
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
})();
</script>
@endpush
