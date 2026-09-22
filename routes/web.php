<?php

use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudyGroupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/mushaf/prototype', fn () => Inertia::render('MushafPrototype'))->name('mushaf.prototype');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('/rubrics', [RubricController::class, 'index'])->name('rubrics.index');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/students/{student}', [ReportController::class, 'student'])->name('reports.students.show');
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'create'])->middleware('throttle:5,1');
    Route::post('/backups/restore/preview', [BackupController::class, 'preview'])->middleware('throttle:5,1');
    Route::get('/backups/restores/{restore}', [BackupController::class, 'review'])->name('backups.restores.show');
    Route::post('/backups/restores/{restore}/confirm', [BackupController::class, 'confirm'])->middleware('throttle:5,1');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download']);
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessments/{assessment}/work', [AssessmentController::class, 'work']);
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
    Route::put('/assessments/{assessment}/draft', [AssessmentController::class, 'save']);
    Route::post('/assessments/{assessment}/finalize', [AssessmentController::class, 'finalize']);
    Route::post('/assessments/{assessment}/cancel', [AssessmentController::class, 'cancel']);
    Route::post('/assessment-records/{record}/revisions', [AssessmentController::class, 'revise']);
    Route::post('/assessment-records/{record}/void', [AssessmentController::class, 'void']);
    Route::post('/rubrics', [RubricController::class, 'store']);
    Route::put('/rubrics/{rubric}/versions/{version}', [RubricController::class, 'update']);
    Route::post('/rubrics/{rubric}/versions', [RubricController::class, 'clone']);
    Route::post('/rubrics/{rubric}/versions/{version}/publish', [RubricController::class, 'publish']);
    Route::post('/rubrics/{rubric}/versions/{version}/preview', [RubricController::class, 'preview']);
    Route::post('/rubrics/{rubric}/archive', [RubricController::class, 'archive']);
    Route::post('/programs/preview', [ProgramController::class, 'preview']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::post('/programs/{program}/archive', [ProgramController::class, 'archive']);
    Route::post('/programs/{program}/versions', [ProgramController::class, 'clone']);
    Route::put('/programs/{program}/versions/{version}', [ProgramController::class, 'update']);
    Route::post('/programs/{program}/versions/{version}/publish', [ProgramController::class, 'publish']);
    Route::post('/programs/{program}/enrollments', [ProgramController::class, 'enroll']);
    Route::post('/programs/{program}/enrollments/{enrollment}/transfer', [ProgramController::class, 'transfer']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::put('/students/{student}', [StudentController::class, 'update']);
    Route::post('/students/{student}/archive', [StudentController::class, 'archive']);
    Route::post('/students/{student}/restore', [StudentController::class, 'restore']);
    Route::post('/groups', [StudyGroupController::class, 'store']);
    Route::put('/groups/{group}', [StudyGroupController::class, 'update']);
    Route::post('/groups/{group}/archive', [StudyGroupController::class, 'archive']);
    Route::post('/activity-types', [ActivityTypeController::class, 'store']);
    Route::put('/activity-types/{activityType}', [ActivityTypeController::class, 'update']);
    Route::post('/activity-types/{activityType}/archive', [ActivityTypeController::class, 'archive']);
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
