<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FileManager — orijinal görsel ve WebP boyut takibi.
 *
 * UploadService::uploadImage() varsayılan davranışta WebP'ye çevirir.
 * Yeni: yüklenen JPG/PNG'nin orijinali ayrı path'te saklanır + WebP boyutu
 * loglanır → kullanıcı kıyaslayabilir + her iki formatı kopyalayabilir.
 *
 * Şema:
 *   - file_size      → ZATEN orijinal bytes (yüklenen dosyanın boyutu)
 *   - webp_size      → YENİ — WebP'ye çevrilmiş halinin boyutu (görsel)
 *   - stored_path    → ZATEN WebP path (görsel) veya orijinal path (diğer)
 *   - original_path  → YENİ — orijinal JPG/PNG path (sadece görsel; diğer
 *                      dosyalarda NULL — stored_path zaten orijinal)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table): void {
            $table->string('original_path', 255)
                ->nullable()
                ->after('stored_path');
            $table->unsignedBigInteger('webp_size')
                ->nullable()
                ->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table): void {
            $table->dropColumn(['original_path', 'webp_size']);
        });
    }
};
