<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Safe, local-only demo data for manually exercising the product.
 * The Quran-like content is synthetic and deliberately labelled as such.
 */
class DemoSeeder extends Seeder
{
    private string $ownerId;

    private string $datasetId;

    private string $editionId;

    private array $ayahs = [];

    private array $words = [];

    private array $criteria = [];

    private string $penaltyRule;

    private string $directCriterion;

    private string $deductionCriterion;

    private array $activities = [];

    private array $students = [];

    private array $rubrics = [];

    private array $programs = [];

    public function run(): void
    {
        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            throw new RuntimeException('DemoSeeder hanya boleh dijalankan pada environment local/testing.');
        }

        $owner = DB::table('users')->where('email', 'demo@penilaian-tahfidz.test')->first();
        if (! $owner) {
            throw new RuntimeException('Akun demo@penilaian-tahfidz.test belum ada. Buat akun demo terlebih dahulu.');
        }
        if (DB::table('quran_datasets')->where('source_name', 'Demo sintetis — bukan mushaf')->exists()) {
            $this->command?->info('Data demo sudah tersedia; tidak ada perubahan dilakukan.');

            return;
        }

        $businessTables = ['students', 'study_groups', 'activity_types', 'quran_datasets', 'rubrics', 'programs', 'assessments', 'backup_runs'];
        $hasData = collect($businessTables)->first(fn (string $table) => DB::table($table)->exists());
        if ($hasData) {
            throw new RuntimeException("Database tidak kosong (tabel {$hasData}). DemoSeeder berhenti agar data Anda tidak tertimpa.");
        }

        $this->ownerId = (string) $owner->id;
        DB::transaction(function (): void {
            $this->seedReference();
            $this->seedMasterData();
            $this->seedRubrics();
            $this->seedPrograms();
            $this->seedAssessments();
        });

        $this->command?->info('Data demo siap. Konten mushaf memakai data sintetis dan bukan sumber Al-Qur’an produksi.');
    }

    private function seedReference(): void
    {
        $now = now();
        $this->datasetId = (string) Str::ulid();
        DB::table('quran_datasets')->insert([
            'id' => $this->datasetId, 'source_name' => 'Demo sintetis — bukan mushaf', 'version' => 'demo-1',
            'riwayah' => 'Synthetic', 'checksum' => hash('sha256', 'penilaian-tahfidz-demo-1'),
            'validation_status' => 'active', 'license_metadata' => 'Konten sintetis untuk uji UI dan alur.', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $surahs = [];
        foreach ([1 => 'Alur Demo', 2 => 'Latihan Demo'] as $number => $name) {
            $id = (string) Str::ulid();
            $surahs[$number] = $id;
            DB::table('surahs')->insert(['id' => $id, 'dataset_id' => $this->datasetId, 'number' => $number, 'name_local' => $name, 'ayah_count' => 12]);
            for ($ayah = 1; $ayah <= 12; $ayah++) {
                $ayahId = (string) Str::ulid();
                $order = (($number - 1) * 12) + $ayah;
                $this->ayahs[$number.':'.$ayah] = ['id' => $ayahId, 'order' => $order];
                DB::table('ayahs')->insert(['id' => $ayahId, 'dataset_id' => $this->datasetId, 'surah_id' => $id, 'number' => $ayah, 'global_order' => $order, 'text_uthmani' => "Teks sintetis demo {$number}:{$ayah} — bukan ayat Al-Qur’an"]);
            }
        }
        foreach ([1 => ['1:1', '1:12'], 2 => ['2:1', '2:12']] as $juz => [$start, $end]) {
            DB::table('juz_ranges')->insert(['id' => (string) Str::ulid(), 'dataset_id' => $this->datasetId, 'juz_number' => $juz, 'start_ayah_id' => $this->ayahs[$start]['id'], 'end_ayah_id' => $this->ayahs[$end]['id']]);
        }
        $this->editionId = (string) Str::ulid();
        DB::table('mushaf_editions')->insert(['id' => $this->editionId, 'dataset_id' => $this->datasetId, 'name' => 'Edisi demo sintetis', 'version' => 'demo-1', 'status' => 'active', 'checksum' => hash('sha256', 'demo-edition-1'), 'license_metadata' => 'Sintetis; bukan mushaf produksi.', 'created_at' => $now, 'updated_at' => $now]);
        foreach ($this->ayahs as $key => $ayah) {
            for ($position = 1; $position <= 4; $position++) {
                $wordId = (string) Str::ulid();
                $this->words[$key.':'.$position] = $wordId;
                DB::table('edition_words')->insert(['id' => $wordId, 'edition_id' => $this->editionId, 'ayah_id' => $ayah['id'], 'position' => $position, 'source_word_key' => 'demo-'.$key.'-'.$position, 'text_or_glyph' => "kata demo {$key}:{$position}", 'token_kind' => 'word']);
            }
        }
    }

    private function seedMasterData(): void
    {
        $now = now();
        foreach (range(1, 12) as $number) {
            $id = (string) Str::ulid();
            $this->students[$number] = $id;
            DB::table('students')->insert(['id' => $id, 'owner_id' => $this->ownerId, 'code' => sprintf('DEMO-%03d', $number), 'name' => ['Ahmad Fikri', 'Aisyah Zahra', 'Bilal Hasan', 'Dina Rahma', 'Fauzan Ali', 'Hana Putri', 'Ilham Maulana', 'Jihan Safa', 'Khalid Umar', 'Laila Nisa', 'Musa Rafi', 'Nadia Husna'][$number - 1], 'contact' => $number <= 3 ? 'Kontak demo' : null, 'notes' => 'Data contoh untuk latihan.', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['Setoran baru' => true, 'Murajaah' => false, 'Ujian pekanan' => true] as $name => $counts) {
            $id = (string) Str::ulid();
            $this->activities[$name] = $id;
            DB::table('activity_types')->insert(['id' => $id, 'owner_id' => $this->ownerId, 'name' => $name, 'counts_toward_progress' => $counts, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['Halaqah Pagi' => [1, 2, 3, 4], 'Halaqah Sore' => [5, 6, 7, 8], 'Kelas Akhir Pekan' => [9, 10, 11, 12]] as $name => $members) {
            $groupId = (string) Str::ulid();
            DB::table('study_groups')->insert(['id' => $groupId, 'owner_id' => $this->ownerId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($members as $number) {
                DB::table('group_memberships')->insert(['id' => (string) Str::ulid(), 'group_id' => $groupId, 'student_id' => $this->students[$number], 'active_student_id' => $this->students[$number], 'joined_at' => $now]);
            }
        }
    }

    private function seedRubrics(): void
    {
        $now = now();
        foreach ([['Rubrik Setoran Demo', '80'], ['Rubrik Murajaah Demo', '75'], ['Rubrik Ujian Demo', '70']] as [$name, $threshold]) {
            $rubricId = (string) Str::ulid();
            $versionId = (string) Str::ulid();
            $this->rubrics[$name] = $versionId;
            DB::table('rubrics')->insert(['id' => $rubricId, 'owner_id' => $this->ownerId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('rubric_versions')->insert(['id' => $versionId, 'rubric_id' => $rubricId, 'version' => 1, 'name_snapshot' => 'Versi demo 1', 'status' => 'published', 'pass_threshold' => $threshold, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $direct = (string) Str::ulid();
            $deduction = (string) Str::ulid();
            $rule = (string) Str::ulid();
            DB::table('criteria')->insert([
                ['id' => $direct, 'rubric_version_id' => $versionId, 'name' => 'Kelancaran', 'description' => 'Nilai langsung latihan demo.', 'method' => 'direct', 'min_score' => 0, 'max_score' => 100, 'weight' => 60, 'sort_order' => 1],
                ['id' => $deduction, 'rubric_version_id' => $versionId, 'name' => 'Ketelitian', 'description' => 'Potongan dari anotasi kesalahan.', 'method' => 'deduction', 'min_score' => 0, 'max_score' => 100, 'weight' => 40, 'sort_order' => 2],
            ]);
            DB::table('mistake_rules')->insert(['id' => $rule, 'criterion_id' => $deduction, 'name' => 'Tersendat', 'severity_label' => 'Ringan', 'deduction_points' => 5]);
            DB::table('mistake_rules')->insert(['id' => (string) Str::ulid(), 'criterion_id' => $deduction, 'name' => 'Lupa lanjutan', 'severity_label' => 'Sedang', 'deduction_points' => 10]);
            foreach ([['Cukup', 0, 75], ['Baik', 75, 90], ['Sangat baik', 90, 100]] as $index => [$label, $lower, $upper]) {
                DB::table('grade_bands')->insert(['id' => (string) Str::ulid(), 'rubric_version_id' => $versionId, 'label' => $label, 'lower_bound' => $lower, 'upper_bound' => $upper, 'sort_order' => $index + 1]);
            }
            if ($name === 'Rubrik Setoran Demo') {
                $this->directCriterion = $direct;
                $this->deductionCriterion = $deduction;
                $this->penaltyRule = $rule;
            }
            $this->criteria[$versionId] = [$direct, $deduction];
        }
    }

    private function seedPrograms(): void
    {
        $now = now();
        foreach (['Target Dasar Demo' => ['1:1', '1:12'], 'Target Lanjutan Demo' => ['2:1', '2:12']] as $name => [$start, $end]) {
            $programId = (string) Str::ulid();
            $versionId = (string) Str::ulid();
            $this->programs[$name] = [$programId, $versionId];
            DB::table('programs')->insert(['id' => $programId, 'owner_id' => $this->ownerId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('program_versions')->insert(['id' => $versionId, 'program_id' => $programId, 'version' => 1, 'dataset_id' => $this->datasetId, 'name_snapshot' => 'Versi demo 1', 'description' => 'Target sintetis untuk mencoba program.', 'status' => 'published', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('program_ranges')->insert(['id' => (string) Str::ulid(), 'program_version_id' => $versionId, 'start_ayah_id' => $this->ayahs[$start]['id'], 'end_ayah_id' => $this->ayahs[$end]['id'], 'sort_order' => 1]);
        }
        foreach (range(1, 6) as $number) {
            $this->enroll($this->programs['Target Dasar Demo'][0], $this->programs['Target Dasar Demo'][1], $this->students[$number]);
        }
        foreach (range(7, 12) as $number) {
            $this->enroll($this->programs['Target Lanjutan Demo'][0], $this->programs['Target Lanjutan Demo'][1], $this->students[$number]);
        }
    }

    private function enroll(string $programId, string $versionId, string $studentId): void
    {
        DB::table('enrollments')->insert(['id' => (string) Str::ulid(), 'student_id' => $studentId, 'program_id' => $programId, 'program_version_id' => $versionId, 'active_student_id' => $studentId, 'started_at' => now()->subDays(30)]);
    }

    private function seedAssessments(): void
    {
        foreach (range(1, 6) as $number) {
            $activity = $number === 3 ? 'Murajaah' : 'Setoran baru';
            $score = $number === 2 ? '68.00' : ($number === 3 ? '88.00' : '92.00');
            $this->assessment($this->students[$number], $this->activities[$activity], $this->rubrics[$activity === 'Murajaah' ? 'Rubrik Murajaah Demo' : 'Rubrik Setoran Demo'], $score, $score !== '68.00', $number <= 6 ? ($number <= 3 ? '1:1' : '1:4') : '1:1');
        }
        $this->assessment($this->students[1], $this->activities['Setoran baru'], $this->rubrics['Rubrik Setoran Demo'], '76.00', false, '1:2', true);
        $this->assessment($this->students[4], $this->activities['Setoran baru'], $this->rubrics['Rubrik Setoran Demo'], '94.00', true, '1:5', false, true);
        $this->draft($this->students[8]);
        $this->voidRecord($this->students[10]);
    }

    private function assessment(string $studentId, string $activityId, string $rubricVersionId, string $score, bool $passed, string $start, bool $superseded = false, bool $void = false): void
    {
        $now = now()->subDays(random_int(1, 20));
        $recordId = (string) Str::ulid();
        $assessmentId = (string) Str::ulid();
        DB::table('assessment_records')->insert(['id' => $recordId, 'owner_id' => $this->ownerId, 'student_id' => $studentId, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('assessments')->insert(['id' => $assessmentId, 'record_id' => $recordId, 'revision_number' => 1, 'enrollment_id' => $this->enrollmentFor($studentId), 'activity_type_id' => $activityId, 'activity_name_snapshot' => $this->activityName($activityId), 'counts_toward_progress_snapshot' => $this->activityCounts($activityId), 'rubric_version_id' => $rubricVersionId, 'edition_id' => $this->editionId, 'status' => $superseded ? 'superseded' : 'final', 'assessed_at' => $now, 'lock_version' => 1, 'final_score' => $score, 'passed' => $passed, 'grade_label_snapshot' => $passed ? 'Sangat baik' : 'Cukup', 'finalized_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $this->ranges($assessmentId, $start);
        foreach ($this->criteria[$rubricVersionId] as $index => $criterionId) {
            DB::table('criterion_scores')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'criterion_id' => $criterionId, 'direct_input' => $index === 0 ? $score : null, 'computed_raw' => $score, 'normalized_score' => $score, 'weighted_score' => $score]);
        }
        if ($activityId === $this->activities['Setoran baru']) {
            $this->annotation($assessmentId, $start);
        }
        DB::table('assessment_records')->where('id', $recordId)->update(['current_final_id' => $assessmentId, 'voided_at' => $void ? now() : null, 'void_reason' => $void ? 'Data demo dibatalkan' : null]);
    }

    private function draft(string $studentId): void
    {
        $recordId = (string) Str::ulid();
        $id = (string) Str::ulid();
        $now = now();
        $rubric = $this->rubrics['Rubrik Setoran Demo'];
        DB::table('assessment_records')->insert(['id' => $recordId, 'owner_id' => $this->ownerId, 'student_id' => $studentId, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('assessments')->insert(['id' => $id, 'record_id' => $recordId, 'active_draft_record_id' => $recordId, 'revision_number' => 1, 'enrollment_id' => $this->enrollmentFor($studentId), 'activity_type_id' => $this->activities['Setoran baru'], 'activity_name_snapshot' => 'Setoran baru', 'counts_toward_progress_snapshot' => true, 'rubric_version_id' => $rubric, 'edition_id' => $this->editionId, 'status' => 'draft', 'lock_version' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $this->ranges($id, '1:7');
        foreach ($this->criteria[$rubric] as $criterionId) {
            DB::table('criterion_scores')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $id, 'criterion_id' => $criterionId]);
        }
    }

    private function voidRecord(string $studentId): void
    {
        $this->assessment($studentId, $this->activities['Setoran baru'], $this->rubrics['Rubrik Setoran Demo'], '84.00', true, '2:2', false, true);
    }

    private function ranges(string $assessmentId, string $start): void
    {
        $end = ((int) explode(':', $start)[1] + 2 <= 12) ? explode(':', $start)[0].':'.((int) explode(':', $start)[1] + 2) : $start;
        foreach (['planned', 'actual'] as $kind) {
            DB::table('assessment_ranges')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'kind' => $kind, 'start_ayah_id' => $this->ayahs[$start]['id'], 'end_ayah_id' => $this->ayahs[$end]['id'], 'sort_order' => 1]);
        }
    }

    private function annotation(string $assessmentId, string $ayah): void
    {
        DB::table('annotations')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'client_event_id' => (string) Str::ulid(), 'ayah_id' => $this->ayahs[$ayah]['id'], 'edition_word_id' => $this->words[$ayah.':1'], 'kind' => 'penalty', 'mistake_rule_id' => $this->penaltyRule, 'note' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function enrollmentFor(string $studentId): string
    {
        return (string) DB::table('enrollments')->where('student_id', $studentId)->whereNull('ended_at')->value('id');
    }

    private function activityName(string $id): string
    {
        return (string) DB::table('activity_types')->where('id', $id)->value('name');
    }

    private function activityCounts(string $id): bool
    {
        return (bool) DB::table('activity_types')->where('id', $id)->value('counts_toward_progress');
    }
}
