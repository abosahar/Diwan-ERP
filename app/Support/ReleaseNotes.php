<?php

namespace App\Support;

/** The release notes this install has reached (a block for a later version waits for its release). */
class ReleaseNotes
{
    public static function visible(): array
    {
        $current = (string) config('app.version');
        $notes = config('release_notes', []);
        if (! preg_match('/^\d+\.\d+\.\d+/', $current)) {
            return $notes; // development checkout
        }

        return array_values(array_filter($notes, fn ($r) => version_compare($r['version'], $current, '<=')));
    }

    public static function latest(): ?string
    {
        return self::visible()[0]['version'] ?? null;
    }
}
