<?php

use App\Services\EmployeeDocumentStorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('documents:cleanup-orphans {--dry-run}', function () {
    $result = app(EmployeeDocumentStorageService::class)
        ->cleanupOrphans((bool) $this->option('dry-run'));

    $this->table(
        ['Metric', 'Value'],
        [
            ['Dry run', $result['dry_run'] ? 'yes' : 'no'],
            ['Scanned files', $result['scanned_files']],
            ['Orphan files', $result['orphan_count']],
            ['Deleted files', $result['deleted_count']],
            ['Missing document records', $result['missing_document_count']],
        ],
    );

    if ($result['orphan_files'] !== []) {
        $this->line('Orphans: '.implode(', ', $result['orphan_files']));
    }

    if ($result['missing_document_ids'] !== []) {
        $this->warn('Missing document IDs: '.implode(', ', $result['missing_document_ids']));
    }

    return 0;
})->purpose('Find and remove orphaned private Employee document files.');

Schedule::command('documents:cleanup-orphans')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping();
