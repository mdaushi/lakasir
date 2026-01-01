<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UpdateChecker
{
    private string $url;

    public function __construct()
    {
        $this->url = config('updater.url');
    }

    public function getCurrentVersion(): string
    {
        $versionFile = base_path('version.txt');

        if (! file_exists($versionFile)) {
            return 'Unknown';
        }

        return trim(file_get_contents($versionFile));
    }

    private function fetchAndCacheApiResponse(): ?array
    {
        return cache()->remember('api_response', now()->addMinutes(60 * 8), function () {
            $response = Http::timeout(30)->get($this->url);

            if (! $response->ok()) {
                return null;
            }

            return $response->json();
        });
    }

    public function getLatestVersion(): ?string
    {
        $response = $this->fetchAndCacheApiResponse();

        return $response ? ltrim($response['tag_name'], 'v') : null;
    }

    public function isUpdateAvailable(): bool
    {
        $current = $this->getCurrentVersion();
        $latest = $this->getLatestVersion();

        return $latest && version_compare($latest, $current, '>');
    }

    public function getChangelog(): ?string
    {
        $response = $this->fetchAndCacheApiResponse();

        return $response ? $response['body'] : null;
    }

    public function getChangelogLines(): array
    {
        $response = $this->fetchAndCacheApiResponse();

        if (! $response) {
            return [];
        }

        $body = $response['body'];

        return array_filter(preg_split('/\r\n|\r|\n/', $body));
    }
}
