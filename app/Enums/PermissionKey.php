<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every ability the admin panel checks.
 *
 * This enum is the source of truth: the permissions table is seeded from it and
 * the roles screen renders its matrix from it, so adding an ability means
 * adding a case here and nothing else.
 */
enum PermissionKey: string
{
    case PagesView = 'pages.view';
    case PagesManage = 'pages.manage';
    case PagesDelete = 'pages.delete';
    case BlogPostsView = 'blog-posts.view';
    case BlogPostsManage = 'blog-posts.manage';
    case BlogPostsDelete = 'blog-posts.delete';
    case BlogCategoriesView = 'blog-categories.view';
    case BlogCategoriesManage = 'blog-categories.manage';
    case BlogCategoriesDelete = 'blog-categories.delete';
    case GalleryView = 'gallery.view';
    case GalleryManage = 'gallery.manage';
    case GalleryDelete = 'gallery.delete';
    case FaqsView = 'faqs.view';
    case FaqsManage = 'faqs.manage';
    case FaqsDelete = 'faqs.delete';
    case SlidersView = 'sliders.view';
    case SlidersManage = 'sliders.manage';
    case SlidersDelete = 'sliders.delete';
    case PopupsView = 'popups.view';
    case PopupsManage = 'popups.manage';
    case PopupsDelete = 'popups.delete';
    case MenusView = 'menus.view';
    case MenusManage = 'menus.manage';
    case MenusDelete = 'menus.delete';
    case FilesView = 'files.view';
    case FilesManage = 'files.manage';
    case FilesDelete = 'files.delete';
    case EditorUpload = 'editor.upload';
    case TranslationsView = 'translations.view';
    case TranslationsManage = 'translations.manage';
    case LanguagesView = 'languages.view';
    case LanguagesManage = 'languages.manage';
    case CampaignsView = 'campaigns.view';
    case CampaignsManage = 'campaigns.manage';
    case CampaignsSend = 'campaigns.send';
    case CampaignsDelete = 'campaigns.delete';
    case SubscribersView = 'subscribers.view';
    case SubscribersManage = 'subscribers.manage';
    case MessagesView = 'messages.view';
    case MessagesReply = 'messages.reply';
    case MessagesDelete = 'messages.delete';
    case CommentsView = 'comments.view';
    case CommentsModerate = 'comments.moderate';
    case CommentsDelete = 'comments.delete';
    case PushNotificationsView = 'push-notifications.view';
    case PushNotificationsSend = 'push-notifications.send';
    case PushNotificationsDelete = 'push-notifications.delete';
    case NotificationsView = 'notifications.view';
    case NotificationsManage = 'notifications.manage';
    case NotificationsDelete = 'notifications.delete';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case UsersDelete = 'users.delete';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case RolesDelete = 'roles.delete';
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';
    case RedirectsView = 'redirects.view';
    case RedirectsManage = 'redirects.manage';
    case RedirectsDelete = 'redirects.delete';
    case CustomRoutesView = 'custom-routes.view';
    case CustomRoutesManage = 'custom-routes.manage';
    case CustomRoutesDelete = 'custom-routes.delete';
    case MailTemplatesView = 'mail-templates.view';
    case MailTemplatesManage = 'mail-templates.manage';
    case MailLogsView = 'mail-logs.view';
    case MailLogsResend = 'mail-logs.resend';
    case AuditLogsView = 'audit-logs.view';
    case BackupsView = 'backups.view';
    case BackupsManage = 'backups.manage';
    case BackupsDelete = 'backups.delete';
    case SystemHealthView = 'system-health.view';
    case QueueView = 'queue.view';
    case QueueManage = 'queue.manage';
    case AnalyticsView = 'analytics.view';
    case ReportsView = 'reports.view';
    case ReportsManage = 'reports.manage';

    public function label(): string
    {
        return match ($this) {
            self::PagesView => 'Sayfaları görüntüle',
            self::PagesManage => 'Sayfa ekle ve düzenle',
            self::PagesDelete => 'Sayfa sil ve geri yükle',
            self::BlogPostsView => 'İçerikleri görüntüle',
            self::BlogPostsManage => 'İçerik ekle ve düzenle',
            self::BlogPostsDelete => 'İçerik sil ve geri yükle',
            self::BlogCategoriesView => 'İçerik kategorilerini görüntüle',
            self::BlogCategoriesManage => 'İçerik kategorisi ekle ve düzenle',
            self::BlogCategoriesDelete => 'İçerik kategorisi sil',
            self::GalleryView => 'Galeriyi görüntüle',
            self::GalleryManage => 'Galeri öğesi ekle ve düzenle',
            self::GalleryDelete => 'Galeri öğesi sil',
            self::FaqsView => 'SSS görüntüle',
            self::FaqsManage => 'SSS ekle ve düzenle',
            self::FaqsDelete => 'SSS sil',
            self::SlidersView => 'Sliderları görüntüle',
            self::SlidersManage => 'Slider ekle ve düzenle',
            self::SlidersDelete => 'Slider sil',
            self::PopupsView => 'Popupları görüntüle',
            self::PopupsManage => 'Popup ekle ve düzenle',
            self::PopupsDelete => 'Popup sil',
            self::TranslationsView => 'Arayüz metinlerini görüntüle',
            self::TranslationsManage => 'Arayüz metinlerini düzenle',
            self::LanguagesView => 'Dilleri görüntüle',
            self::LanguagesManage => 'Dil ekle, düzenle ve sil',
            self::CampaignsView => 'Mail kampanyalarını görüntüle',
            self::CampaignsManage => 'Kampanya oluştur ve düzenle',
            self::CampaignsSend => 'Kampanya gönderimini başlat',
            self::CampaignsDelete => 'Kampanya sil',
            self::SubscribersView => 'Mail listesini görüntüle',
            self::SubscribersManage => 'Mail listesini düzenle ve içe aktar',
            self::MenusView => 'Menüleri görüntüle',
            self::MenusManage => 'Menü düzenle ve sırala',
            self::MenusDelete => 'Menü öğesi sil',
            self::FilesView => 'Dosyaları görüntüle',
            self::FilesManage => 'Dosya yükle ve düzenle',
            self::FilesDelete => 'Dosya sil',
            self::EditorUpload => 'Editörden görsel yükle',
            self::MessagesView => 'İletişim mesajlarını görüntüle',
            self::MessagesReply => 'İletişim mesajlarını yanıtla',
            self::MessagesDelete => 'İletişim mesajı sil',
            self::CommentsView => 'Yorumları görüntüle',
            self::CommentsModerate => 'Yorum onayla ve reddet',
            self::CommentsDelete => 'Yorum sil',
            self::PushNotificationsView => 'Push duyurularını görüntüle',
            self::PushNotificationsSend => 'Push duyurusu gönder',
            self::PushNotificationsDelete => 'Push duyurusu sil',
            self::NotificationsView => 'Bildirimleri görüntüle',
            self::NotificationsManage => 'Bildirimleri okundu işaretle',
            self::NotificationsDelete => 'Bildirim sil',
            self::UsersView => 'Kullanıcıları görüntüle',
            self::UsersManage => 'Kullanıcı ekle ve düzenle',
            self::UsersDelete => 'Kullanıcı sil ve geri yükle',
            self::RolesView => 'Rolleri görüntüle',
            self::RolesManage => 'Rol ekle, düzenle ve izin ata',
            self::RolesDelete => 'Rol sil',
            self::SettingsView => 'Ayarları görüntüle',
            self::SettingsManage => 'Ayarları değiştir',
            self::RedirectsView => 'Yönlendirmeleri görüntüle',
            self::RedirectsManage => 'Yönlendirme ekle ve düzenle',
            self::RedirectsDelete => 'Yönlendirme sil',
            self::CustomRoutesView => 'Özel adresleri görüntüle',
            self::CustomRoutesManage => 'Özel adres ekle ve düzenle',
            self::CustomRoutesDelete => 'Özel adres sil',
            self::MailTemplatesView => 'Mail şablonlarını görüntüle',
            self::MailTemplatesManage => 'Mail şablonlarını düzenle',
            self::MailLogsView => 'Mail loglarını görüntüle',
            self::MailLogsResend => 'Maili yeniden gönder',
            self::AuditLogsView => 'Aktivite loglarını görüntüle',
            self::BackupsView => 'Yedekleri görüntüle',
            self::BackupsManage => 'Yedek oluştur ve indir',
            self::BackupsDelete => 'Yedek sil',
            self::SystemHealthView => 'Sistem sağlığını görüntüle',
            self::QueueView => 'Kuyruğu görüntüle',
            self::QueueManage => 'Kuyruk işini yeniden dene ve sil',
            self::AnalyticsView => 'Analitiği görüntüle',
            self::ReportsView => 'Raporları görüntüle ve indir',
            self::ReportsManage => 'Zamanlanmış rapor tanımla',
        };
    }

    public function group(): PermissionGroup
    {
        return match ($this) {
            self::PagesView => PermissionGroup::Content,
            self::PagesManage => PermissionGroup::Content,
            self::PagesDelete => PermissionGroup::Content,
            self::BlogPostsView => PermissionGroup::Content,
            self::BlogPostsManage => PermissionGroup::Content,
            self::BlogPostsDelete => PermissionGroup::Content,
            self::BlogCategoriesView => PermissionGroup::Content,
            self::BlogCategoriesManage => PermissionGroup::Content,
            self::BlogCategoriesDelete => PermissionGroup::Content,
            self::GalleryView => PermissionGroup::Content,
            self::GalleryManage => PermissionGroup::Content,
            self::GalleryDelete => PermissionGroup::Content,
            self::FaqsView => PermissionGroup::Content,
            self::FaqsManage => PermissionGroup::Content,
            self::FaqsDelete => PermissionGroup::Content,
            self::SlidersView => PermissionGroup::Content,
            self::SlidersManage => PermissionGroup::Content,
            self::SlidersDelete => PermissionGroup::Content,
            self::PopupsView => PermissionGroup::Content,
            self::PopupsManage => PermissionGroup::Content,
            self::PopupsDelete => PermissionGroup::Content,
            self::TranslationsView,
            self::TranslationsManage,
            self::LanguagesView,
            self::LanguagesManage => PermissionGroup::System,
            self::CampaignsView,
            self::CampaignsManage,
            self::CampaignsSend,
            self::CampaignsDelete,
            self::SubscribersView,
            self::SubscribersManage => PermissionGroup::Communication,
            self::MenusView => PermissionGroup::Content,
            self::MenusManage => PermissionGroup::Content,
            self::MenusDelete => PermissionGroup::Content,
            self::FilesView => PermissionGroup::Media,
            self::FilesManage => PermissionGroup::Media,
            self::FilesDelete => PermissionGroup::Media,
            self::EditorUpload => PermissionGroup::Media,
            self::MessagesView => PermissionGroup::Communication,
            self::MessagesReply => PermissionGroup::Communication,
            self::MessagesDelete => PermissionGroup::Communication,
            self::CommentsView => PermissionGroup::Communication,
            self::CommentsModerate => PermissionGroup::Communication,
            self::CommentsDelete => PermissionGroup::Communication,
            self::PushNotificationsView => PermissionGroup::Communication,
            self::PushNotificationsSend => PermissionGroup::Communication,
            self::PushNotificationsDelete => PermissionGroup::Communication,
            self::NotificationsView => PermissionGroup::Communication,
            self::NotificationsManage => PermissionGroup::Communication,
            self::NotificationsDelete => PermissionGroup::Communication,
            self::UsersView => PermissionGroup::System,
            self::UsersManage => PermissionGroup::System,
            self::UsersDelete => PermissionGroup::System,
            self::RolesView => PermissionGroup::System,
            self::RolesManage => PermissionGroup::System,
            self::RolesDelete => PermissionGroup::System,
            self::SettingsView => PermissionGroup::System,
            self::SettingsManage => PermissionGroup::System,
            self::CustomRoutesView => PermissionGroup::System,
            self::CustomRoutesManage => PermissionGroup::System,
            self::CustomRoutesDelete => PermissionGroup::System,
            self::RedirectsView => PermissionGroup::System,
            self::RedirectsManage => PermissionGroup::System,
            self::RedirectsDelete => PermissionGroup::System,
            self::MailTemplatesView => PermissionGroup::System,
            self::MailTemplatesManage => PermissionGroup::System,
            self::MailLogsView => PermissionGroup::System,
            self::MailLogsResend => PermissionGroup::System,
            self::AuditLogsView => PermissionGroup::System,
            self::BackupsView => PermissionGroup::System,
            self::BackupsManage => PermissionGroup::System,
            self::BackupsDelete => PermissionGroup::System,
            self::SystemHealthView => PermissionGroup::System,
            self::QueueView => PermissionGroup::System,
            self::QueueManage => PermissionGroup::System,
            self::AnalyticsView => PermissionGroup::System,
            self::ReportsView => PermissionGroup::System,
            self::ReportsManage => PermissionGroup::System,
        };
    }

    /**
     * @return array<string, array<int, self>>
     */
    public static function groupedByGroup(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$case->group()->value][] = $case;
        }

        return $grouped;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
