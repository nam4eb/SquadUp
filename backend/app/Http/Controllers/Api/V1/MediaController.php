<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse
    {
        $message = $media->mediable;
        abort_unless($message instanceof Message && ! $message->trashed(), 404);
        abort_unless(
            $message->conversation->members()->where('user_id', $request->user()->id)->exists(),
            403
        );
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download(
            $media->path,
            $media->original_name,
            ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
