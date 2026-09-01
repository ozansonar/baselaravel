@extends('emails.layout')

@section('content')
    <p class="em-greeting">{{ __('mail.report.eyebrow') }}</p>
    <h1 class="em-heading">{{ $report_title ?? '' }}</h1>

    <p class="em-text">{{ __('mail.report.lead', ['frequency' => $frequency ?? '']) }}</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">{{ __('mail.report.range') }}:</span> {{ $report_range ?? '' }}</p>
            </td>
        </tr>
    </table>

    <p class="em-text-sm">{{ __('mail.report.outro') }}</p>
@endsection
