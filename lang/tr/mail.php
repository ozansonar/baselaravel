<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| E-posta metinleri
|--------------------------------------------------------------------------
|
| Gönderilen her mailin konusu ve gövdesi.
|
| Çalışan kurulumda gövde mail_templates tablosundan geliyor (Admin > Mail
| Şablonları) ve orada her dilin kendi satırı var; buradaki metinler o satır
| bulunamadığında —şablon kapatılmış, dil sonradan eklenmiş— devreye giren
| Blade karşılığı. Konu satırı ise şablon yoksa doğrudan buradan okunuyor.
|
| Kısacası bu dosya son savunma hattı: koda gömülü Türkçe metin kalmasın diye
| var. Yeni bir dil eklenirken lang/{code}/mail.php de kopyalanmalı.
|
*/

return [

    // Mail altbilgisi. Panelden bir metin girilmişse o kazanıyor; bu satır
    // ayar boşken devreye giriyor ve alıcının dilinde yazıyor.
    'footer_text' => 'Sizinle çalışmaktan mutluluk duyuyoruz.',

    'common' => [
        'greeting'   => 'Merhaba',
        'security'   => 'Güvenlik',
        'date'       => 'Tarih',
        'email'      => 'E-posta',
        'subject'    => 'Konu',
        'post'       => 'Yazı',
    ],

    'welcome' => [
        'subject'      => 'Hoş Geldiniz - :site',
        'heading'      => 'Hoş Geldiniz, :name!',
        'lead'         => ':site ailesine katıldığınız için teşekkür ederiz. Aramıza hoş geldiniz! Size yardımcı olmaktan mutluluk duyarız.',
        'features'     => 'Hesabınızla neler yapabilirsiniz?',
        'feature_profile' => '<strong>Profil bilgilerinizi</strong> yönetin',
        'feature_content' => '<strong>İçeriklerimizi</strong> keşfedin',
        'feature_news'    => '<strong>Yeni yazılardan</strong> haberdar olun',
        'feature_contact' => '<strong>Bizimle iletişimde</strong> kalın',
        'explore'      => 'Siteyi Keşfet',
        'outro'        => 'Herhangi bir sorunuz varsa bize iletişim sayfamızdan ulaşabilirsiniz. İyi çalışmalar dileriz!',
    ],

    'verify' => [
        'subject'  => 'E-posta Adresinizi Doğrulayın - :site',
        'heading'  => 'E-posta Adresinizi Doğrulayın',
        'lead'     => ':name, hesabınızı kullanmaya başlamak için aşağıdaki butona tıklayarak e-posta adresinizi doğrulayın.',
        'button'   => 'E-postamı Doğrula',
        'fallback' => 'Buton çalışmıyorsa bu adresi tarayıcınıza yapıştırabilirsiniz:',
        'ignore'   => 'Bu hesabı siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.',
    ],

    'reset' => [
        'subject'  => 'Şifre Sıfırlama - :site',
        'heading'  => 'Şifre Sıfırlama Talebi',
        'lead'     => 'Merhaba, hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:',
        'button'   => 'Şifremi Sıfırla',
        'expires'  => 'Bu şifre sıfırlama bağlantısı :minutes dakika içinde geçerliliğini yitirecektir.',
        'ignore'   => 'Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz. Hesabınız güvende.',
        'fallback' => 'Butona tıklayamıyorsanız aşağıdaki bağlantıyı tarayıcınıza kopyalayıp yapıştırın:',
    ],

    'reset_code' => [
        'subject' => 'Şifre Sıfırlama Kodunuz - :site',
        'heading' => 'Şifre Sıfırlama Kodunuz',
        'lead'    => 'Merhaba, hesabınız için bir şifre sıfırlama talebi aldık. Uygulamadaki alana aşağıdaki kodu girin:',
        'expires' => 'Bu kod :minutes dakika içinde geçerliliğini yitirecektir.',
        'ignore'  => 'Eğer şifre sıfırlama talebinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz. Kodu kimseyle paylaşmayın; ekibimiz sizden bu kodu asla istemez.',
    ],

    'email_changed' => [
        'subject'   => 'Hesabınızın e-posta adresi değiştirildi - :site',
        'heading'   => 'Hesabınızın e-posta adresi değiştirildi',
        'lead'      => 'Merhaba :name, hesabınızın e-posta adresi :date tarihinde değiştirildi.',
        'previous'  => 'Eski adres',
        'new'       => 'Yeni adres',
        'was_you'   => 'Bu değişikliği siz yaptıysanız yapmanız gereken bir şey yok; bu bilgilendirme mailini yok sayabilirsiniz.',
        'was_not_you' => '<strong>Bu değişikliği siz yapmadıysanız hesabınız başkasının eline geçmiş olabilir.</strong> Bildirimler ve şifre sıfırlama bağlantıları artık yeni adrese gideceği için hesabı kendi başınıza geri almanız mümkün olmayabilir. Vakit kaybetmeden bize ulaşın:',
        'last_mail' => 'Bu mail, adresi hesabınızdan kaldırılmadan önce son kez size gönderildi.',
    ],

    'contact_notification' => [
        'subject'  => 'Yeni İletişim Mesajı - :subject',
        'eyebrow'  => 'İletişim',
        'heading'  => 'Yeni İletişim Mesajı',
        'lead'     => 'Web sitesi üzerinden yeni bir iletişim mesajı alındı.',
        'from'     => 'Gönderen',
        'phone'    => 'Telefon',
        'body'     => 'Mesaj İçeriği',
        'outro'    => 'Bu mesajı yönetim panelinden görüntüleyebilir ve yanıtlayabilirsiniz.',
        'button'   => 'Mesajı Görüntüle',
    ],

    'contact_reply' => [
        'subject'  => 'Re: :subject',
        'greeting' => 'Merhaba :name,',
        'heading'  => 'Mesajınıza Yanıt',
        'lead'     => 'İletişim formundan gönderdiğiniz mesajınız için teşekkür ederiz. Yanıtımız aşağıdadır:',
        'original' => 'Orijinal Mesajınız',
        'outro'    => 'Başka sorularınız varsa bu e-postayı yanıtlayabilir veya web sitemiz üzerinden bize ulaşabilirsiniz.',
    ],

    'comment_admin' => [
        'subject'  => 'Yeni Yorum: :post - :site',
        'eyebrow'  => 'Yorum',
        'heading'  => 'Yeni Yorum Geldi',
        'lead'     => 'Bir blog yazısına yeni bir yorum yapıldı. Yorum onaya düştü; onaylanana kadar sitede görünmüyor.',
        'author'   => 'Yazan',
        'body'     => 'Yorum İçeriği',
        'button'   => 'Yorumu İncele',
    ],

    'comment_received' => [
        'subject' => 'Yorumunuz Alındı - :site',
        'heading' => 'Yorumunuz Alındı',
        'lead'    => 'Yorumunuz bize ulaştı ve değerlendirme aşamasında. Onaylandığında yazının altında yayınlanacak ve size ayrıca haber vereceğiz.',
        'body'    => 'Yorumunuz',
        'ignore'  => 'Bu yorumu siz yazmadıysanız bu e-postayı yok sayabilirsiniz.',
    ],

    'comment_approved' => [
        'subject' => 'Yorumunuz Yayınlandı - :site',
        'heading' => 'Yorumunuz Yayınlandı',
        'lead'    => 'Yorumunuz onaylandı ve artık yazının altında herkes tarafından görülebiliyor. Katkınız için teşekkür ederiz.',
        'body'    => 'Yorumunuz',
    ],

    'campaign' => [
        'test_notice' => '<strong>Bu bir test gönderimidir.</strong> Alıcı listesine gönderilmemiştir.',
        'unsubscribe' => 'Bu e-postayı almak istemiyorsanız :link.',
        'unsubscribe_link' => 'abonelikten çıkabilirsiniz',
    ],

    'report' => [
        'subject' => ':title - :site',
        'eyebrow' => 'Rapor',
        'lead'    => ':frequency çalışan raporunuz bu e-postanın ekinde.',
        'range'   => 'Tarih aralığı',
        'outro'   => 'Bu raporu almayı bırakmak için panelden Raporlar → Zamanlanan Raporlar bölümündeki tanımı kapatabilirsiniz.',
    ],

    'test' => [
        'subject' => ':site — Test E-postası',
        'eyebrow' => 'Test E-postası',
        'outro'   => 'Bu e-posta, SMTP ayarlarınızın doğru çalışıp çalışmadığını test etmek amacıyla gönderilmiştir.',
    ],

];
