<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminNotification;
use App\Models\User;
use App\Services\SessionRevoker;

final class UserObserver
{
    /**
     * Değişen bir e-posta adresi kanıtlanmamış bir adrestir.
     *
     * Doğrulama damgası adrese ait, hesaba değil. Adres değişip damga yerinde
     * kalırsa kullanıcı sahibi olmadığı bir adrese geçip "doğrulanmış"
     * kalabiliyordu — ve doğrulamaya bakan her yer (ön yüzdeki /hesabim, API'nin
     * hesap uçları, kampanya alıcı süzgeci) artık kanıtlanmamış bir adrese
     * güveniyordu.
     *
     * Kural gözlemcide çünkü adres üç ayrı yerden değişebiliyor: ön yüzdeki
     * profil formu, API'nin profil ucu ve panelden kullanıcı düzenleme. Üçüne
     * ayrı ayrı yazılsaydı dördüncüsü eklendiğinde unutulurdu.
     *
     * Kaydetmeden önce: damganın düşmesi ile adresin yazılması aynı sorguda
     * olmalı, yoksa ikisi arasında hesap bir an için yanlış adresle doğrulanmış
     * görünür.
     */
    public function updating(User $user): void
    {
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    /**
     * Deactivating an account closes the sessions it already has.
     *
     * Without this the flag would only decide who may start a session, and the
     * ones already open would run until they expired. EnsureUserIsActive turns
     * such a session away on the next request; this makes sure there is no
     * session left to turn away.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            app(SessionRevoker::class)->revoke($user);
        }

        // Yeni adres yeni bir bağlantı istiyor. Doğrulama adresinin imzası
        // e-postanın kendisinden türüyor (sha1), yani adres değiştiği anda
        // eskiden gönderilmiş bağlantı zaten çalışmaz hâle geliyor: bağlantı
        // yenilenmezse kullanıcının doğrulanmasının hiçbir yolu kalmıyor.
        //
        // Panelden yapılan değişiklikte de gönderiliyor — mail yeni adrese
        // gidiyor, yani onu kanıtlaması gereken kişiye.
        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Same for a deleted account — soft deleted users cannot be resolved by
     * the auth guard any more, but their session rows would linger.
     */
    public function deleted(User $user): void
    {
        app(SessionRevoker::class)->revoke($user);
    }

    /**
     * Cascade is handled here rather than by foreign keys.
     *
     * role_user and admin_notifications are declared restrictOnDelete, so both
     * have to be cleared before the database will remove a user for good. A
     * soft delete leaves them in place, which is what lets a restore put the
     * user back exactly as they were.
     *
     * blog_posts.user_id is nullOnDelete on purpose: removing an author must
     * not remove their content.
     */
    public function deleting(User $user): void
    {
        if (! $user->isForceDeleting()) {
            return;
        }

        $user->roles()->detach();

        $user->adminNotifications()
            ->withTrashed()
            ->each(fn (AdminNotification $notification) => $notification->forceDelete());
    }
}
