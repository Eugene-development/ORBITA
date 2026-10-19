<?php
namespace App\Modules\Media;
use Illuminate\Validation\ValidationException;

/** Validate decoded forms without changing the raw legacy object basename. */
final class MediaHash {
    public static function assertSafe(string $hash): void {
        $candidate = $hash;
        for ($step = 0; $step <= strlen($hash); $step++) {
            if (! mb_check_encoding($candidate, 'UTF-8') || $candidate === '' || in_array($candidate, ['.', '..'], true)
                || preg_match('/[\/\\\\\p{Cc}]/u', $candidate)
                || preg_match('/^[a-z][a-z0-9+.-]*:/i', ltrim($candidate))) {
                throw ValidationException::withMessages(['imageHash' => 'Недопустимое имя файла.']);
            }
            $decoded = rawurldecode($candidate);
            if ($decoded === $candidate) { return; }
            $candidate = $decoded;
        }
        throw ValidationException::withMessages(['imageHash' => 'Недопустимое имя файла.']);
    }
}
