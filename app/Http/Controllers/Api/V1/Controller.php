<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiClient;
use App\Models\Site;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shared by the read-only API (SPEC 7a): the calling client, page size, and the site scope.
 */
abstract class Controller
{
    protected function client(Request $request): ApiClient
    {
        return $request->user();
    }

    protected function perPage(Request $request): int
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('ims.api.max_per_page')]]);

        return $request->integer('per_page', 50);
    }

    /**
     * The site to filter by: ?site=CODE, or the client's own site. Null means every site.
     * A client limited to one site cannot ask for another.
     */
    protected function siteId(Request $request): ?int
    {
        $request->validate(['site' => ['nullable', 'string', 'max:10']]);
        $client = $this->client($request);

        if (! $request->filled('site')) {
            return $client->site_id;
        }

        $site = Site::query()->where('code', $request->string('site'))->first()
            ?? throw new HttpException(404, 'Unknown site.');

        if ($client->site_id && $client->site_id !== $site->id) {
            throw new HttpException(403, 'This token is limited to another site.');
        }

        return $site->id;
    }

    /**
     * A record that belongs to one site must be in the client's scope.
     */
    protected function ensureInScope(Request $request, int $siteId): void
    {
        $client = $this->client($request);

        if ($client->site_id && $client->site_id !== $siteId) {
            throw new HttpException(404, 'Not found.');
        }
    }
}
