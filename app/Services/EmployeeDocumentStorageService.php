<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeDocumentStorageService
{
    public const DISK = 'employee_documents';

    public function create(Employee $employee, User $uploader, array $validated): EmployeeDocument
    {
        $file = $validated['file'];
        $stored = $this->storeFile($employee, $file);

        try {
            return DB::transaction(fn () => EmployeeDocument::create([
                'employee_id' => $employee->id,
                'uploaded_by' => $uploader->id,
                ...Arr::except($validated, ['file']),
                ...$stored,
                'version' => 1,
            ]));
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($stored['file_path']);

            throw $exception;
        }
    }

    public function updateMetadata(EmployeeDocument $document, array $validated): EmployeeDocument
    {
        return DB::transaction(function () use ($document, $validated): EmployeeDocument {
            $locked = EmployeeDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked->update($validated);

            return $locked->refresh();
        });
    }

    public function replace(EmployeeDocument $document, User $uploader, UploadedFile $file): EmployeeDocument
    {
        $stored = $this->storeFile($document->employee, $file);
        $oldDisk = $document->disk;
        $oldPath = $document->file_path;

        try {
            $updated = DB::transaction(function () use ($document, $uploader, $stored): EmployeeDocument {
                $locked = EmployeeDocument::query()
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $locked->update([
                    'uploaded_by' => $uploader->id,
                    ...$stored,
                    'version' => $locked->version + 1,
                ]);

                return $locked->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($stored['file_path']);

            throw $exception;
        }

        try {
            if ($oldPath && Storage::disk($oldDisk)->exists($oldPath)) {
                Storage::disk($oldDisk)->delete($oldPath);
            }
        } catch (Throwable $exception) {
            Log::warning('Employee document old file cleanup failed.', [
                'document_id' => $document->id,
                'disk' => $oldDisk,
                'file_path' => $oldPath,
                'error' => $exception->getMessage(),
            ]);
        }

        return $updated;
    }

    public function delete(EmployeeDocument $document): void
    {
        DB::transaction(fn () => $document->delete());
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        if (! Storage::disk($document->disk)->exists($document->file_path)) {
            throw new RuntimeException('Document file is missing.');
        }

        return Storage::disk($document->disk)->download(
            $document->file_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function cleanupOrphans(bool $dryRun = false): array
    {
        $disk = Storage::disk(self::DISK);
        $knownPaths = EmployeeDocument::query()
            ->pluck('file_path')
            ->filter()
            ->flip();
        $allFiles = collect($disk->allFiles());
        $orphans = $allFiles
            ->reject(fn (string $path) => $knownPaths->has($path))
            ->values();

        if (! $dryRun && $orphans->isNotEmpty()) {
            $disk->delete($orphans->all());
        }

        $missingRecords = EmployeeDocument::query()
            ->get(['id', 'file_path'])
            ->filter(fn (EmployeeDocument $document) => ! $disk->exists($document->file_path))
            ->pluck('id')
            ->values();

        return [
            'dry_run' => $dryRun,
            'scanned_files' => $allFiles->count(),
            'orphan_files' => $orphans->all(),
            'orphan_count' => $orphans->count(),
            'deleted_count' => $dryRun ? 0 : $orphans->count(),
            'missing_document_ids' => $missingRecords->all(),
            'missing_document_count' => $missingRecords->count(),
        ];
    }

    private function storeFile(Employee $employee, UploadedFile $file): array
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $storedName = Str::uuid().'.'.$extension;
        $directory = 'employees/'.$employee->id;
        $filePath = $file->storeAs($directory, $storedName, self::DISK);

        if (! is_string($filePath) || $filePath === '') {
            throw new RuntimeException('Document file could not be stored.');
        }

        return [
            'disk' => self::DISK,
            'file_path' => $filePath,
            'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    private function safeOriginalName(string $name): string
    {
        $safe = preg_replace('/[\r\n]+/', '', basename($name));

        return mb_substr($safe ?: 'document', 0, 255);
    }
}
