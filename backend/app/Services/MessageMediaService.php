<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MessageMediaService
{
    public function attach(Message $message, User $uploader, UploadedFile $file): Media
    {
        $disk = config('media.disk');
        $path = $file->store("chat/{$message->conversation_id}", $disk);

        try {
            return $message->media()->create([
                'uploaded_by' => $uploader->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function messageType(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
    }
}
