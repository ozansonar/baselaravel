{{-- Belge başlığı ve tablonun açılışı. Satırlar ayrı yazımlarla ekleniyor:
     mPDF tek seferde 1 MB'tan büyük HTML kabul etmiyor. --}}
<div class="doc-head">
    <h1 class="doc-title">{{ $title }}</h1>
    <p class="doc-meta">{{ $project }} · {{ $generatedAt }} · {{ number_format($rowCount, 0, ',', '.') }} kayıt</p>
</div>

@if($rowCount === 0)
    <p class="doc-empty">Bu süzgeçlerle eşleşen kayıt yok.</p>
@else
    <table class="doc-table">
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th width="{{ $widths[$loop->index] }}%">{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
@endif
