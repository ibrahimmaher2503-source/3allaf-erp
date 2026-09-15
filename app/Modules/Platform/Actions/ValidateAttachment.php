<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Data\ValidatedAttachment;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ValidateAttachment
{
    public function execute(UploadedFile|File $file, string $purpose): ValidatedAttachment
    {
        $allowedMimes = config("attachments.allowed_mimes.{$purpose}");
        $maxBytes = config("attachments.limits.{$purpose}");

        if (! is_array($allowedMimes) || ! is_int($maxBytes)) {
            throw ValidationException::withMessages(['purpose' => __('This attachment purpose is not configured.')]);
        }

        $originalFilename = $file instanceof UploadedFile
            ? $file->getClientOriginalName()
            : $file->getFilename();

        if (str_contains($originalFilename, "\0")) {
            throw ValidationException::withMessages(['file' => __('The filename contains an invalid character.')]);
        }

        $safeFilename = basename(str_replace('\\', '/', $originalFilename));
        $extension = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $realPath = $file->getRealPath();

        if ($safeFilename === '' || $extension === '' || $realPath === false || ! is_file($realPath)) {
            throw ValidationException::withMessages(['file' => __('The attachment could not be read safely.')]);
        }

        if ($sizeBytes < 1) {
            throw ValidationException::withMessages(['file' => __('Empty attachments are not allowed.')]);
        }

        if ($sizeBytes > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('The attachment exceeds the configured local limit.')]);
        }

        $declaredMimeType = $file instanceof UploadedFile
            ? (string) ($file->getClientMimeType() ?: 'application/octet-stream')
            : (string) ($file->getMimeType() ?: 'application/octet-stream');
        $detectedMimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');

        if ((! in_array($declaredMimeType, $allowedMimes, true) && $declaredMimeType !== 'application/octet-stream')
            || ! in_array($extension, $this->allowedExtensions($allowedMimes), true)
            || ! $this->signatureMatches($extension, $detectedMimeType, $allowedMimes)) {
            throw ValidationException::withMessages(['file' => __('The file extension and detected content do not match the configured purpose.')]);
        }

        if ($this->hasBlockedDoubleExtension($safeFilename)) {
            throw ValidationException::withMessages(['file' => __('Executable or script-like double extensions are not allowed.')]);
        }

        $dimensions = [];
        if (str_starts_with($detectedMimeType, 'image/')) {
            $image = @getimagesize($realPath);
            if ($image === false) {
                throw ValidationException::withMessages(['file' => __('The image signature could not be verified.')]);
            }

            $dimensions = ['width' => (int) $image[0], 'height' => (int) $image[1]];
        }

        $sha256 = hash_file('sha256', $realPath);
        if ($sha256 === false) {
            throw ValidationException::withMessages(['file' => __('The attachment hash could not be calculated.')]);
        }

        return new ValidatedAttachment(
            originalFilename: $safeFilename,
            extension: $extension,
            declaredMimeType: strtolower($declaredMimeType),
            detectedMimeType: strtolower($detectedMimeType),
            sizeBytes: $sizeBytes,
            sha256: $sha256,
            dimensions: $dimensions,
        );
    }

    /** @param array<int, string> $allowedMimes @return array<int, string> */
    private function allowedExtensions(array $allowedMimes): array
    {
        $extensions = [];
        foreach ($allowedMimes as $mime) {
            $extensions = [...$extensions, ...match ($mime) {
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
                'application/pdf' => ['pdf'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'text/csv' => ['csv'],
                default => [],
            }];
        }

        return array_values(array_unique($extensions));
    }

    /** @param array<int, string> $allowedMimes */
    private function signatureMatches(string $extension, string $detectedMime, array $allowedMimes): bool
    {
        if (in_array($detectedMime, $allowedMimes, true)) {
            return true;
        }

        // finfo commonly reports valid XLSX ZIP containers as application/zip.
        return $extension === 'xlsx'
            && in_array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $allowedMimes, true)
            && $detectedMime === 'application/zip'
            || $extension === 'csv'
            && in_array('text/csv', $allowedMimes, true)
            && $detectedMime === 'text/plain';
    }

    private function hasBlockedDoubleExtension(string $filename): bool
    {
        $parts = explode('.', strtolower($filename));
        $blocked = ['php', 'phar', 'exe', 'dll', 'js', 'mjs', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd'];

        foreach (array_slice($parts, 0, -1) as $part) {
            if (in_array($part, $blocked, true)) {
                return true;
            }
        }

        return false;
    }
}
