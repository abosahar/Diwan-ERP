<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Updates of a local install (Windows, Mac, Linux made by the installers): finds
 * the latest GitHub release and asks the installed update script to apply it.
 * The script does the work in the background and keeps the system running; this
 * class only drops a request in a folder the script watches and reads the
 * status file the script writes.
 */
class SelfUpdate
{
    public const REPO = 'turkialawlqy/Diwan-ERP';

    /** Local installs only: the script that applies updates exists nowhere else. */
    public function enabled(): bool
    {
        return (bool) config('app.local_install');
    }

    public function current(): string
    {
        return trim((string) @file_get_contents(base_path('VERSION'))) ?: '0';
    }

    /** @return array{version: string, notes: string, url: string}|null */
    public function latest(): ?array
    {
        return Cache::remember('self-update:latest', now()->addHours(6), function () {
            try {
                $response = Http::timeout(5)->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->get('https://api.github.com/repos/'.self::REPO.'/releases/latest');
            } catch (\Throwable) {
                return null;
            }
            if (! $response->ok() || ! $response->json('tag_name')) {
                return null;
            }

            return [
                'version' => ltrim((string) $response->json('tag_name'), 'vV'),
                'notes' => mb_substr((string) $response->json('body'), 0, 1500),
                'url' => (string) $response->json('html_url'),
            ];
        });
    }

    public function available(): bool
    {
        $latest = $this->latest();

        return $latest !== null && version_compare($latest['version'], $this->current(), '>');
    }

    public function forget(): void
    {
        Cache::forget('self-update:latest');
    }

    protected function queueDir(): string
    {
        return storage_path('app/update-queue');
    }

    protected function statusFile(): string
    {
        return storage_path('app/update-status.json');
    }

    /** @return array{state: string, from?: string, to?: string, message?: string, at?: int}|null */
    public function status(): ?array
    {
        $json = is_file($this->statusFile()) ? json_decode((string) file_get_contents($this->statusFile()), true) : null;

        return is_array($json) ? $json : null;
    }

    public function running(): bool
    {
        $status = $this->status();

        return $status && in_array($status['state'], ['requested', 'running'], true) && ($status['at'] ?? 0) > time() - 1800;
    }

    /** Ask the update script to run now (it watches the queue folder; Windows starts a scheduled task). */
    public function request(): void
    {
        File::ensureDirectoryExists($this->queueDir());
        file_put_contents($this->queueDir().'/request', (string) time());
        file_put_contents($this->statusFile(), json_encode(['state' => 'requested', 'from' => $this->current(), 'to' => $this->latest()['version'] ?? '', 'message' => '', 'at' => time()]));

        if (PHP_OS_FAMILY === 'Windows') {
            @pclose(popen('schtasks /run /tn "Diwan Update"', 'r'));
        }
    }
}
