<?php
namespace App\Services;

use App\Models\{IncidentMedia,Report,User};
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncidentEvidence
{
    public const MAX_IMAGES = 10;
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    public const MAX_VIDEOS = 3;
    public const MAX_VIDEO_BYTES = 15 * 1024 * 1024;

    /** Count active uploads and any legacy single photo toward the same limits. */
    public function usage(?Report $report): array
    {
        $usage = ['images'=>0, 'image_bytes'=>0, 'videos'=>0, 'video_bytes'=>0];
        if (! $report) return $usage;

        foreach (IncidentMedia::where('report_id',(string)$report->id)->whereNull('removed_at')->get() as $media) {
            if ($media->kind === 'image') {
                $usage['images']++;
                $usage['image_bytes'] += (int)$media->size_bytes;
            } elseif ($media->kind === 'video') {
                $usage['videos']++;
                $usage['video_bytes'] += (int)$media->size_bytes;
            }
        }
        if ($report->photo_path && Storage::disk('local')->exists($report->photo_path)) {
            $usage['images']++;
            $usage['image_bytes'] += Storage::disk('local')->size($report->photo_path);
        }
        return $usage;
    }

    /**
     * Validate each uploaded file AND aggregate limits on the server.
     * Only accepted image/video MIME types are stored on the private disk.
     */
    public function validateUploads(Request $request,?Report $report=null): array
    {
        $request->validate([
            'images' => ['nullable','array','max:10'],
            'images.*' => ['required','file','image','mimes:jpg,jpeg,png,webp','max:10240'],
            'videos' => ['nullable','array','max:3'],
            'videos.*' => ['required','file','mimetypes:video/mp4,video/webm,video/quicktime','max:15360'],
        ]);

        $images = $request->file('images', []);
        $videos = $request->file('videos', []);
        $images = is_array($images) ? $images : [];
        $videos = is_array($videos) ? $videos : [];

        $usage = $this->usage($report);
        $imageBytes = array_sum(array_map(fn(UploadedFile $file) => $file->getSize(), $images));
        $videoBytes = array_sum(array_map(fn(UploadedFile $file) => $file->getSize(), $videos));

        if ($usage['images']+count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images'=>'A report may contain up to 10 photos. Remove an existing photo before adding more.']);
        }
        if ($usage['image_bytes']+$imageBytes > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages(['images'=>'The combined size of all photos must not exceed 10 MB.']);
        }
        if ($usage['videos']+count($videos) > self::MAX_VIDEOS) {
            throw ValidationException::withMessages(['videos'=>'A report may contain up to 3 videos.']);
        }
        if ($usage['video_bytes']+$videoBytes > self::MAX_VIDEO_BYTES) {
            throw ValidationException::withMessages(['videos'=>'The combined size of all videos must not exceed 15 MB.']);
        }
        return ['images'=>$images, 'videos'=>$videos];
    }

    /** Roll back attachments created by this operation if any file/database write fails. */
    public function storeUploads(Report $report, User $user, array $uploads): void
    {
        $created = [];
        $paths = [];
        try {
            foreach (['images'=>'image', 'videos'=>'video'] as $key=>$kind) {
                foreach ($uploads[$key] ?? [] as $file) {
                    /** @var UploadedFile $file */
                    $path = $file->store('incident-evidence/'.(string)$report->id, 'local');
                    if (! $path) throw new \RuntimeException('Could not save evidence securely.');
                    $paths[] = $path;
                    $name = basename(str_replace('\\','/',$file->getClientOriginalName()));
                    $created[] = IncidentMedia::create([
                        'report_id' => (string)$report->id,
                        'uploaded_by' => (string)$user->id,
                        'kind' => $kind,
                        'storage_path' => $path,
                        'original_name' => Str::limit($name,180,''),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            foreach ($created as $media) $media->delete();
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
