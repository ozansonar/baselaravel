{{-- TinyMCE 8 - Mail Template Editor --}}
{{-- Seçici modalı: kampanya editörüyle aynı bileşen. --}}
@include('partials.admin.file-picker')

@push('scripts')
<script src="{{ asset('assets/vendor/tinymce/tinymce.min.js') }}" referrerpolicy="origin"></script>
<script>
(function () {
    'use strict';

    var selector = '#body';
    var editorElement = document.querySelector(selector);
    if (!editorElement) return;

    tinymce.init({
        selector: selector,
        license_key: 'gpl',
        language: 'tr',
        language_url: '{{ asset("assets/vendor/tinymce/langs/tr.js") }}',
        base_url: '{{ asset("assets/vendor/tinymce") }}',

        skin: 'oxide-dark',
        content_css: 'dark',

        height: 550,
        min_height: 400,
        resize: true,

        plugins: [
            'advlist',
            'autolink',
            'charmap',
            'code',
            'fullscreen',
            'help',
            'image',
            'link',
            'lists',
            'preview',
            'searchreplace',
            'table',
            'visualblocks',
            'wordcount'
        ],

        toolbar: [
            'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor removeformat',
            'alignleft aligncenter alignright | bullist numlist | link image table | charmap hr',
            'mailclasses | searchreplace visualblocks code fullscreen preview'
        ],

        menubar: 'file edit view insert format tools table',

        block_formats: 'Paragraf=p; Başlık 1=h1; Başlık 2=h2; Başlık 3=h3; Başlık 4=h4; Div=div',

        style_formats: [
            {
                title: 'E-posta Sınıfları',
                items: [
                    { title: 'Selamlama (em-greeting)', selector: 'p', classes: 'em-greeting' },
                    { title: 'Ana Başlık (em-heading)', selector: 'h1,h2,h3', classes: 'em-heading' },
                    { title: 'Alt Başlık (em-heading-sm)', selector: 'h1,h2,h3,h4', classes: 'em-heading-sm' },
                    { title: 'Metin (em-text)', selector: 'p', classes: 'em-text' },
                    { title: 'Küçük Metin (em-text-sm)', selector: 'p,span,small', classes: 'em-text-sm' },
                    { title: 'Buton (em-btn)', selector: 'a', classes: 'em-btn' },
                    { title: 'Buton Kapsayıcı (em-btn-wrap)', selector: 'div', classes: 'em-btn-wrap' },
                    { title: 'Bilgi Kutusu (em-info-box)', selector: 'div,table', classes: 'em-info-box' },
                    { title: 'Vurgu (em-highlight)', selector: 'div,table', classes: 'em-highlight' },
                    { title: 'Ayırıcı (em-divider)', selector: 'hr', classes: 'em-divider' },
                    { title: 'Tablo (em-table)', selector: 'table', classes: 'em-table' },
                    { title: 'Badge (em-badge)', selector: 'span', classes: 'em-badge' }
                ]
            }
        ],

        style_formats_merge: false,

        content_style: [
            'body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.7; color: #e0e0e0; padding: 16px; }',
            // Editörde taşan bir görsel mailde de taşıyor; sınır yazarken görünsün.
            'img { max-width: 100%; height: auto; }',
            'table { max-width: 100%; }',
            '.em-greeting { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }',
            '.em-heading { font-size: 24px; font-weight: 700; color: #f1f5f9; margin-bottom: 8px; }',
            '.em-heading-sm { font-size: 18px; font-weight: 600; color: #e2e8f0; }',
            '.em-text { font-size: 15px; color: #cbd5e1; line-height: 1.7; }',
            '.em-text-sm { font-size: 13px; color: #94a3b8; }',
            '.em-btn { display: inline-block; padding: 12px 28px; background: #14b8a6; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }',
            '.em-btn-wrap { text-align: center; margin: 20px 0; }',
            '.em-info-box { background: rgba(34,197,94,0.08); border-left: 4px solid #22c55e; padding: 12px 16px; border-radius: 6px; }',
            '.em-highlight { background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2); padding: 12px 16px; border-radius: 6px; }',
            '.em-divider { border: none; border-top: 1px solid #334155; margin: 20px 0; }',
            '.em-table { width: 100%; border-collapse: collapse; }',
            '.em-table td, .em-table th { padding: 10px 12px; border: 1px solid #334155; }',
            '.em-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: rgba(20,184,166,0.15); color: #14b8a6; }'
        ].join(' '),

        link_default_target: '_blank',
        link_assume_external_targets: true,
        table_responsive_width: true,
        browser_spellcheck: true,
        // "Bir resim arayın" düğmesi panelin dosya seçicisini açıyor; kampanya
        // editörüyle aynı davransın diye burada da tanımlı.
        file_picker_types: 'image',
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

        contextmenu: 'link image table',
        promotion: false,
        branding: false,
        paste_data_images: false,
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style]',
        entity_encoding: 'raw',

        setup: function (editor) {
            editor.ui.registry.addMenuButton('mailclasses', {
                text: 'E-posta Sınıfları',
                icon: 'color-swatch-remove-color',
                fetch: function (callback) {
                    var items = [
                        { type: 'menuitem', text: 'em-greeting', onAction: function () { editor.formatter.toggle('custom-greeting'); } },
                        { type: 'menuitem', text: 'em-heading', onAction: function () { editor.formatter.toggle('custom-heading'); } },
                        { type: 'menuitem', text: 'em-text', onAction: function () { editor.formatter.toggle('custom-text'); } },
                        { type: 'menuitem', text: 'em-text-sm', onAction: function () { editor.formatter.toggle('custom-text-sm'); } },
                        { type: 'menuitem', text: 'em-btn (link seç)', onAction: function () { editor.formatter.toggle('custom-btn'); } },
                        { type: 'menuitem', text: 'em-btn-wrap', onAction: function () { editor.formatter.toggle('custom-btn-wrap'); } },
                        { type: 'menuitem', text: 'em-info-box', onAction: function () { editor.formatter.toggle('custom-info-box'); } },
                        { type: 'menuitem', text: 'em-highlight', onAction: function () { editor.formatter.toggle('custom-highlight'); } },
                        { type: 'menuitem', text: 'em-badge', onAction: function () { editor.formatter.toggle('custom-badge'); } }
                    ];
                    callback(items);
                }
            });

            editor.on('init', function () {
                editor.formatter.register({
                    'custom-greeting': { block: 'p', classes: 'em-greeting' },
                    'custom-heading': { block: 'h1', classes: 'em-heading' },
                    'custom-text': { block: 'p', classes: 'em-text' },
                    'custom-text-sm': { block: 'p', classes: 'em-text-sm' },
                    'custom-btn': { selector: 'a', classes: 'em-btn' },
                    'custom-btn-wrap': { block: 'div', classes: 'em-btn-wrap' },
                    'custom-info-box': { block: 'div', classes: 'em-info-box' },
                    'custom-highlight': { block: 'div', classes: 'em-highlight' },
                    'custom-badge': { inline: 'span', classes: 'em-badge' }
                });
            });

            editor.on('change', function () {
                editor.save();
            });
        }
    });
})();
</script>
@endpush
