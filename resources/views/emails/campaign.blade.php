@extends('emails.layout')

@section('content')
    @if($isTest ?? false)
        {{-- Only ever shown on a preview send, so the tester can tell the two apart. --}}
        <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr><td class="em-info-box-td">
                <p class="em-text-sm">{!! __('mail.campaign.test_notice') !!}</p>
            </td></tr>
        </table>
    @endif

    {{-- Written in the panel editor and stored as HTML; images are absolute
         URLs so they load in the recipient's mail client. --}}
    {!! $emailBody !!}

    @if(!empty($unsubscribeUrl))
        <hr class="em-divider">
        <p class="em-text-sm" style="text-align: center;">
            {{-- Bağlantı cümlenin içinde: hangi kelimenin bağlantı olduğu dile
                 göre değişiyor, cümleyi parçalara bölmek çeviriyi bozardı. --}}
            {!! __('mail.campaign.unsubscribe', [
                'link' => '<a href="' . e($unsubscribeUrl) . '">' . e(__('mail.campaign.unsubscribe_link')) . '</a>',
            ]) !!}
        </p>
    @endif
@endsection
