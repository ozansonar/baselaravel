<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FileManager modülü — genel dosya kütüphanesi.
 *
 * Admin elle yüklediği herhangi bir dosyayı (PDF, Word, Excel, görsel,
 * video, ZIP) saklar. Public URL üzerinden blog/sayfa içinde linklenir.
 *
 * Modele bağlı görsel alanlarından (slider, blog, sayfa görselleri)
 * BAĞIMSIZ — onlar UploadService ile ilgili kaydın kolonuna yazılır,
 * bu tablo serbest dosya kütüphanesi içindir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table): void {
            $table->id();
            $table->string('original_name', 255);                        // Yüklenen orijinal dosya adı
            $table->string('stored_path', 255);                          // public/uploads/files/... relative path
            $table->string('mime_type', 100);                            // application/pdf vs.
            $table->string('extension', 10);                             // pdf, docx, jpg
            $table->unsignedBigInteger('file_size');                     // bytes
            $table->string('category', 30);                              // image|document|video|audio|archive|other
            $table->string('title', 191)->nullable();                    // Admin için açıklama
            $table->text('alt_text')->nullable();                        // SEO/accessibility (görseller için)
            $table->string('hash', 64)->nullable();                      // sha256 — duplicate kontrolü
            $table->unsignedInteger('width')->nullable();                // Sadece görsel
            $table->unsignedInteger('height')->nullable();               // Sadece görsel
            $table->unsignedInteger('download_count')->default(0);       // Analytics
            $table->boolean('is_public')->default(true);                 // İleride private dosyalar için
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Index'ler
            $table->index('category');
            $table->index('extension');
            $table->index('hash');
            $table->index('created_at');
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
