<?php
namespace App\Modules\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MediaController
{
    public function upload(Request $request): JsonResponse
    {
        Gate::authorize('catalog-crud');
        if (! config('orbita.media_uploads_enabled')) { throw new HttpException(503, 'Загрузка изображений ожидает настройки хранилища.'); }
        $request->validate(['image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240']]);
        $file = $request->file('image');
        $extension = match ($file->getMimeType()) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => throw ValidationException::withMessages(['image' => 'Недопустимый формат.']) };
        $size = getimagesize($file->getRealPath());
        if (! $size || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 40000000) { throw ValidationException::withMessages(['image' => 'Изображение слишком большое или повреждено.']); }
        $hash = (string) Str::uuid().'.'.$extension;
        $path = trim(config('orbita.media_prefix'), '/').'/'.$hash;
        $stream = fopen($file->getRealPath(), 'rb');
        try { Storage::disk(config('orbita.media_disk'))->writeStream($path, $stream, ['visibility' => 'public', 'ContentType' => $file->getMimeType()]); }
        finally { if (is_resource($stream)) { fclose($stream); } }
        DB::table('orbita_media')->insert(['hash' => $hash, 'path' => $path, 'disk' => config('orbita.media_disk'), 'mime' => $file->getMimeType(), 'bytes' => $file->getSize(), 'uploaded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['hash' => $hash, 'url' => app(MediaUrl::class)->forHash($hash)], 201);
    }
    public function show(string $hash): \Symfony\Component\HttpFoundation\Response {
        abort_unless((bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(jpg|png|webp)$/Di', $hash), 404);
        $media = DB::table('orbita_media')->where('hash', $hash)->where('disk', 'local')->first();
        abort_unless($media && $media->hash === $hash && $media->path === 'images/'.$hash
            && in_array($media->mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);
        return Storage::disk('local')->response($media->path, $hash, ['Content-Type' => $media->mime,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable'], 'inline');
    }
}
