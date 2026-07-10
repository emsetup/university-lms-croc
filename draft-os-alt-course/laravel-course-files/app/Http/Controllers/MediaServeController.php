<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\MediaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class MediaServeController extends Controller
{
    public function __construct(
        private MediaAccessService $access
    ) {}

    public function show(Request $request, string $uuid): BinaryFileResponse|Response
    {
        $asset = MediaAsset::query()->where('uuid', $uuid)->firstOrFail();
        $this->access->assertCanViewAsset($asset);

        return $this->fileResponse($asset->storage_path, $asset->mime, $asset->original_filename);
    }

    public function thumb(Request $request, string $uuid): BinaryFileResponse|Response
    {
        $asset = MediaAsset::query()->where('uuid', $uuid)->firstOrFail();
        $this->access->assertCanViewAsset($asset);

        $path = $asset->thumb_path ?: $asset->storage_path;

        return $this->fileResponse($path, $asset->mime, 'thumb-'.$asset->original_filename);
    }

    private function fileResponse(string $relativePath, string $mime, string $downloadName): BinaryFileResponse|Response
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        $response = response()->file($disk->path($relativePath));
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Cache-Control', 'private, max-age=86400');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
