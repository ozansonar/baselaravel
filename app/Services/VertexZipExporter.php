<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VertexGeneration;
use Illuminate\Support\Collection;
use ZipArchive;

final class VertexZipExporter
{
    /**
     * @param  Collection<int, VertexGeneration>  $generations
     */
    public function export(Collection $generations): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'vtx_zip_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Geçici dosya oluşturulamadı.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('ZIP dosyası oluşturulamadı.');
        }

        $usedNames = [];
        $tmpJpgPaths = [];

        foreach ($generations as $generation) {
            if (! $generation->output_image_path) {
                continue;
            }

            $srcPath = public_path('uploads/' . $generation->output_image_path);
            if (! is_file($srcPath)) {
                continue;
            }

            $baseName = $this->uniqueName($usedNames, $generation);

            $zip->addFile($srcPath, 'webp/' . $baseName . '.webp');

            $jpgPath = $this->convertToJpg($srcPath, $generation->id);
            if ($jpgPath !== null) {
                $zip->addFile($jpgPath, 'jpeg/' . $baseName . '.jpg');
                $tmpJpgPaths[] = $jpgPath;
            }
        }

        $zip->close();

        foreach ($tmpJpgPaths as $path) {
            @unlink($path);
        }

        return $tmpPath;
    }

    /**
     * @param  Collection<int, VertexGeneration>  $generations
     */
    public function exportJpgOnly(Collection $generations): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'vtx_zip_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Geçici dosya oluşturulamadı.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('ZIP dosyası oluşturulamadı.');
        }

        $usedNames = [];
        $tmpJpgPaths = [];

        foreach ($generations as $generation) {
            if (! $generation->output_image_path) {
                continue;
            }

            $srcPath = public_path('uploads/' . $generation->output_image_path);
            if (! is_file($srcPath)) {
                continue;
            }

            $baseName = $this->uniqueName($usedNames, $generation);

            $jpgPath = $this->convertToJpg($srcPath, $generation->id);
            if ($jpgPath !== null) {
                $zip->addFile($jpgPath, $baseName . '.jpg');
                $tmpJpgPaths[] = $jpgPath;
            }
        }

        $zip->close();

        foreach ($tmpJpgPaths as $path) {
            @unlink($path);
        }

        return $tmpPath;
    }

    private function convertToJpg(string $srcPath, int $generationId): ?string
    {
        $image = match (true) {
            str_ends_with($srcPath, '.webp') => @imagecreatefromwebp($srcPath),
            str_ends_with($srcPath, '.png')  => @imagecreatefrompng($srcPath),
            default                          => @imagecreatefromstring((string) file_get_contents($srcPath)),
        };

        if ($image === false) {
            return null;
        }

        $tmpPath = sys_get_temp_dir() . '/vtx_jpg_' . $generationId . '.jpg';

        imagejpeg($image, $tmpPath, 100);
        imagedestroy($image);

        return $tmpPath;
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function uniqueName(array &$usedNames, VertexGeneration $generation): string
    {
        $promptSlug = $generation->prompt?->name
            ? mb_substr($this->slugify($generation->prompt->name), 0, 30)
            : 'vertex';

        $base = $promptSlug . '-' . $generation->id;

        if (! isset($usedNames[$base])) {
            $usedNames[$base] = true;

            return $base;
        }

        $i = 2;
        while (isset($usedNames[$base . '-' . $i])) {
            $i++;
        }

        $name = $base . '-' . $i;
        $usedNames[$name] = true;

        return $name;
    }

    private function slugify(string $text): string
    {
        $map = [
            'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
        ];
        $text = strtr($text, $map);
        $text = (string) preg_replace('/[^a-zA-Z0-9]+/', '-', $text);

        return trim(strtolower($text), '-');
    }
}
