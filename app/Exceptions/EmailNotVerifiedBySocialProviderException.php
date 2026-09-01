<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\SocialProvider;
use Exception;

/**
 * Sosyal kimlikteki adres o sağlayıcı tarafından doğrulanmamış ve aynı
 * adresle sitede zaten bir hesap var.
 *
 * Bağlamak hesap devralma olurdu: o adresi kendi sağlayıcı hesabına yazan
 * biri, buradaki hesabın sahibi olurdu. Yeni hesap açmak da yanlış — aynı
 * adresle ikinci bir hesap doğardı. Geriye tek doğru cevap kalıyor: kişi
 * şifresiyle girsin, hesabını kendi eliyle bağlasın.
 */
final class EmailNotVerifiedBySocialProviderException extends Exception
{
    public function __construct(
        public readonly SocialProvider $provider,
    ) {
        parent::__construct('Social provider did not verify the e-mail address.');
    }
}
