@props([
    // config/export.php içindeki liste anahtarı
    'export',
    // Süzgeçlere uyan toplam kayıt: PDF tavanı aşıldığında kullanıcıyı dosya
    // üretilmeden uyarmak için. Bilinmiyorsa sunucu tarafı yine yakalar.
    'total' => null,
    // Adres satırında değil de yolda taşınan seçimler (açık menü gibi) buradan
    // eklenir; yoksa dosya ekrandakinden geniş iner.
    'params' => [],
])

@php
    // Ekrandaki süzgeçler dosyaya aynen yansısın; sayfa numarası anlamsız,
    // dosyaya tek sayfa değil listenin tamamı iner.
    $exportQuery = array_merge(request()->except('page'), $params);
    $pdfLimit = (int) config('export.pdf_row_limit');
@endphp

<div class="dropdown export-menu" {{ $attributes }}>
    <button class="btn-glass dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i> Dışa Aktar
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item"
               href="{{ route('admin.export', array_merge(['key' => $export, 'format' => 'excel'], $exportQuery)) }}">
                <i class="bi bi-file-earmark-excel me-2"></i> Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item"
               href="{{ route('admin.export', array_merge(['key' => $export, 'format' => 'csv'], $exportQuery)) }}">
                <i class="bi bi-filetype-csv me-2"></i> CSV
            </a>
        </li>
        <li>
            <a class="dropdown-item js-export-pdf"
               href="{{ route('admin.export', array_merge(['key' => $export, 'format' => 'pdf'], $exportQuery)) }}"
               @if($total !== null) data-row-count="{{ $total }}" @endif
               data-row-limit="{{ $pdfLimit }}">
                <i class="bi bi-file-earmark-pdf me-2"></i> PDF
            </a>
        </li>
    </ul>
</div>
