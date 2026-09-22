<?php

namespace App\Http\Controllers;

use App\Services\PrivateBackups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(Request $request, PrivateBackups $backups): Response
    {
        return Inertia::render('Backups/Index', ['backups' => $backups->list($request->user()->id)]);
    }

    public function create(Request $request, PrivateBackups $backups): RedirectResponse
    {
        $input = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']]);
        $result = $backups->create($request->user()->id, $input['password']);

        return to_route('backups.index')->with('success', 'Backup terenkripsi siap diunduh: '.$result['id']);
    }

    public function download(Request $request, string $backup, PrivateBackups $backups): BinaryFileResponse
    {
        return response()->download($backups->downloadPath($request->user()->id, $backup), "penilaian-tahfidz-$backup.pthbackup", [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, PrivateBackups $backups): RedirectResponse
    {
        $input = $request->validate([
            'archive' => ['required', 'file', 'max:65536'],
            'password' => ['required', 'string'],
        ]);
        $result = $backups->stage($request->user()->id, $input['archive'], $input['password']);

        return to_route('backups.restores.show', $result['id']);
    }

    public function review(Request $request, string $restore, PrivateBackups $backups): Response
    {
        return Inertia::render('Backups/Review', $backups->staged($request->user()->id, $restore));
    }

    public function confirm(Request $request, string $restore, PrivateBackups $backups): RedirectResponse
    {
        $input = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:GANTI DATA'],
        ]);
        $result = $backups->restore($request->user()->id, $restore, $input['password']);
        $request->session()->regenerate();

        return to_route('backups.index')->with('success', 'Restore selesai. Backup sebelum restore: '.$result['pre_restore_backup_id']);
    }
}
