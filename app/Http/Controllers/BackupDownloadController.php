<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(string $filename, BackupService $backups): BinaryFileResponse
    {
        $path = $backups->resolvePath($filename);

        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }
}
