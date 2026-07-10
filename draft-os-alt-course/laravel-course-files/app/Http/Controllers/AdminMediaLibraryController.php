<?php

namespace App\Http\Controllers;

use App\Models\LearnerMediaPin;
use App\Models\MediaAsset;
use App\Services\MediaAccessService;
use App\Services\MediaImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class AdminMediaLibraryController extends Controller
{
    public function __construct(
        private MediaAccessService $access,
        private MediaImageProcessor $processor
    ) {}

    public function index(Request $request): View
    {
        $this->access->assertCanUseMediaLibrary();

        return view('admin.media-library', [
            'courseId' => (int) $request->query('course_id', 0),
        ]);
    }

    public function apiList(Request $request): JsonResponse
    {
        $this->access->assertCanUseMediaLibrary();

        $learnerId = $this->access->currentLearnerId();
        $scope = (string) $request->query('scope', 'mine');
        $courseId = (int) $request->query('course_id', 0);
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(60, max(12, (int) $request->query('per_page', 24)));

        $query = MediaAsset::query()->orderByDesc('id');

        if ($scope === 'course' && $courseId > 0) {
            $this->access->assertCanUpload($courseId);
            $query->where('course_id', $courseId);
        } elseif ($scope === 'mine') {
            $pinnedIds = LearnerMediaPin::query()
                ->where('learner_id', $learnerId)
                ->pluck('media_asset_id');
            $query->where(function ($w) use ($learnerId, $pinnedIds) {
                $w->where('uploaded_by_learner_id', $learnerId);
                if ($pinnedIds->isNotEmpty()) {
                    $w->orWhereIn('id', $pinnedIds);
                }
            });
        } else {
            $query->where('uploaded_by_learner_id', $learnerId);
        }

        if ($q !== '') {
            $query->where('original_filename', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'ok' => true,
            'items' => collect($paginator->items())->map(fn (MediaAsset $a) => $this->assetPayload($a))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $courseId = $request->input('course_id');
        $courseId = $courseId !== null && $courseId !== '' ? (int) $courseId : null;
        $this->access->assertCanUpload($courseId);

        $request->validate([
            'file' => 'required|file|max:'.((int) config('media.max_upload_bytes', 10485760) / 1024),
            'course_id' => 'nullable|integer|min:1',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $uuid = MediaAsset::newUuid();

        try {
            $processed = $this->processor->processAndStore($file, $uuid, $courseId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $asset = MediaAsset::query()->create([
            'uuid' => $uuid,
            'uploaded_by_learner_id' => $this->access->currentLearnerId(),
            'course_id' => $courseId,
            'storage_path' => $processed['full_path'],
            'thumb_path' => $processed['thumb_path'],
            'original_filename' => (string) $file->getClientOriginalName(),
            'mime' => $processed['mime'],
            'width' => $processed['width'],
            'height' => $processed['height'],
            'bytes' => $processed['bytes'],
        ]);

        LearnerMediaPin::query()->firstOrCreate(
            [
                'learner_id' => $this->access->currentLearnerId(),
                'media_asset_id' => (int) $asset->id,
            ],
            ['source' => LearnerMediaPin::SOURCE_UPLOAD]
        );

        return response()->json([
            'ok' => true,
            'asset' => $this->assetPayload($asset),
        ]);
    }

    public function pin(string $uuid): JsonResponse
    {
        $this->access->assertCanUseMediaLibrary();
        $asset = MediaAsset::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canPinAsset($asset), 403);

        LearnerMediaPin::query()->firstOrCreate(
            [
                'learner_id' => $this->access->currentLearnerId(),
                'media_asset_id' => (int) $asset->id,
            ],
            ['source' => LearnerMediaPin::SOURCE_COURSE_IMPORT]
        );

        return response()->json(['ok' => true, 'asset' => $this->assetPayload($asset)]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $asset = MediaAsset::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canDeleteAsset($asset), 403);

        if ($asset->storage_path && Storage::disk('local')->exists($asset->storage_path)) {
            Storage::disk('local')->delete($asset->storage_path);
        }
        if ($asset->thumb_path && Storage::disk('local')->exists($asset->thumb_path)) {
            Storage::disk('local')->delete($asset->thumb_path);
        }

        LearnerMediaPin::query()->where('media_asset_id', (int) $asset->id)->delete();
        $asset->delete();

        return response()->json(['ok' => true]);
    }

  /**
   * @return array<string, mixed>
   */
    private function assetPayload(MediaAsset $asset): array
    {
        return [
            'uuid' => $asset->uuid,
            'url' => $asset->adminFileUrl(),
            'public_url' => $asset->publicUrl(),
            'thumb_url' => $asset->adminThumbUrl(),
            'markdown' => $asset->markdownSnippet(),
            'original_filename' => $asset->original_filename,
            'width' => (int) $asset->width,
            'height' => (int) $asset->height,
            'bytes' => (int) $asset->bytes,
            'course_id' => $asset->course_id,
            'is_large' => $asset->isLarge(),
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
