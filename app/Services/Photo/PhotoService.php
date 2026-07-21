<?php

namespace App\Services\Photo;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoService
{
    private string $disk;

    private string $path;

    private int $maxFileSize;

    public function __construct()
    {
        $this->disk = config('photo.disk', 'public');
        $this->path = config('photo.path', 'photos');
        $this->maxFileSize = config('photo.max_file_size_kb', 5120) * 1024; // Convert to bytes
    }

    /**
     * Process and upload a photo
     *
     * @param UploadedFile $file
     * @param string|null $customName Optional custom filename
     * @return string The path to the stored photo
     */
    public function upload(UploadedFile $file, ?string $customName = null): string
    {
        // Validate file
        $this->validateFile($file);

        // Generate filename
        $filename = $customName ?? $this->generateFilename($file);

        // Store the file
        $fullPath = $this->path . '/' . $filename;
        
        Storage::disk($this->disk)->putFileAs(
            $this->path,
            $file,
            $filename
        );

        return $fullPath;
    }

    /**
     * Delete a photo from storage
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    /**
     * Get the full URL for a photo path
     *
     * @param string $path
     * @return string
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Validate the uploaded file
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png'];
        
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only JPG, JPEG, and PNG are allowed.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size.');
        }
    }

    /**
     * Generate a unique filename
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = Str::random(40);
        
        return $basename . '.' . $extension;
    }
}
