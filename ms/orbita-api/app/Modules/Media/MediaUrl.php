<?php
namespace App\Modules\Media;
use App\Modules\Catalog\Models\Image;
use Illuminate\Support\Facades\DB;
final class MediaUrl {
    public function image(Image $image): ?string { return $this->forHash((string) $image->hash); }
    public function forHash(string $hash): ?string {
        try { MediaHash::assertSafe($hash); } catch (\Throwable) { return null; }
        $local = DB::table('orbita_media')->where('hash', $hash)->where('disk', 'local')->first();
        if ($local && $local->hash === $hash) { return rtrim(config('app.url'), '/').'/media/'.rawurlencode($hash); }
        $base = config('orbita.media_base_url');
        return $base ? rtrim($base, '/').'/'.rawurlencode(rawurldecode($hash)) : null;
    }
}
