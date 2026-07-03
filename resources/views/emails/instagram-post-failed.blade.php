@extends('emails.layout')

@section('content')
    <p class="em-greeting">Instagram Otomatik Paylaşım</p>
    <h1 class="em-heading">Bir Gönderi Kalıcı Hataya Geçti &#9888;&#65039;</h1>

    <p class="em-text">
        Aşağıdaki Instagram gönderisi <strong>{{ $post->retry_count }} kez</strong> denenmesine rağmen
        yayınlanamadı. Cron artık otomatik olarak yeniden denemeyecek — manuel müdahale gerekiyor.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Post ID:</span> #{{ $post->id }}</p>
                @if($post->scheduled_at)
                    <p class="em-info-row"><span class="em-info-label">Planlanan Tarih:</span> {{ $post->scheduled_at->format('d.m.Y H:i') }}</p>
                @endif
                <p class="em-info-row"><span class="em-info-label">Toplam Deneme:</span> {{ $post->retry_count }}</p>
                <p class="em-info-row"><span class="em-info-label">Son Hata:</span> {{ $post->error_message ?? '—' }}</p>
            </td>
        </tr>
    </table>

    @if(!empty($post->caption))
    <hr class="em-divider">
    <p class="em-heading-sm">Caption Önizleme</p>
    <p class="em-text">{{ \Illuminate\Support\Str::limit((string) $post->caption, 280) }}</p>
    @endif

    <hr class="em-divider">

    <p class="em-text">
        Sorunu çözüp postu yönetim panelinden manuel olarak yeniden denemeye sokabilirsiniz.
        "Yeniden Dene" butonu deneme sayacını sıfırlar ve cron'un sıradaki çalışmasında yayınlamaya çalışır.
    </p>

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/instagram-posts/' . $post->id . '/edit') }}" class="em-btn">Postu Görüntüle</a>
    </div>

    <p class="em-text em-muted">
        <small>Bu bildirim, post 3 başarısız denemeden sonra <strong>tek seferlik</strong> gönderilir.
        Aynı post için tekrar mail almazsınız.</small>
    </p>
@endsection
