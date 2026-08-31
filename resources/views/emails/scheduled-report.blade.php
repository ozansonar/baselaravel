@extends('emails.layout')

@section('content')
    <h2>{{ $report_title ?? '' }}</h2>

    <p>{{ $frequency ?? '' }} çalışan raporunuz ekte.</p>

    <p><strong>Tarih aralığı:</strong> {{ $report_range ?? '' }}</p>

    <p class="text-muted">
        Bu raporu almayı bırakmak için panelden Raporlar &rarr; Zamanlanan Raporlar
        bölümündeki tanımı kapatabilirsiniz.
    </p>
@endsection
