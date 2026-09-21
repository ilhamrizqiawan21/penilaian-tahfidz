<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_lifecycle_and_unique_code(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => ' A-01 ', 'name' => ' Ahmad ', 'contact' => 'Wali', 'notes' => 'Catatan'])->assertRedirect('/students');
        $student = DB::table('students')->sole();
        $this->assertSame('A-01', $student->code);
        $this->assertSame('Ahmad', $student->name);
        $this->actingAs($owner)->post('/students', ['code' => 'A-01', 'name' => 'Duplikat'])->assertSessionHasErrors('code');
        $this->actingAs($owner)->put("/students/{$student->id}", ['code' => 'A-01', 'name' => 'Ahmad Baru'])->assertRedirect('/students');
        $this->assertSame('Ahmad Baru', DB::table('students')->sole()->name);
        $this->actingAs($owner)->post("/students/{$student->id}/archive")->assertRedirect('/students');
        $this->assertNotNull(DB::table('students')->sole()->archived_at);
        $this->actingAs($owner)->get('/students?status=archived')->assertOk();
        $this->actingAs($owner)->post("/students/{$student->id}/restore")->assertRedirect('/students');
        $this->assertNull(DB::table('students')->sole()->archived_at);
    }

    public function test_group_membership_and_activity_configuration(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => 'B-01', 'name' => 'Bilal']);
        $studentId = DB::table('students')->sole()->id;
        $this->actingAs($owner)->post('/groups', ['name' => 'Kelompok A', 'student_ids' => [$studentId]])->assertRedirect('/students');
        $groupId = DB::table('study_groups')->sole()->id;
        $this->assertDatabaseCount('group_memberships', 1);
        $this->actingAs($owner)->put("/groups/{$groupId}", ['name' => 'Kelompok B', 'student_ids' => []])->assertRedirect('/students');
        $this->assertNotNull(DB::table('group_memberships')->sole()->left_at);
        $this->actingAs($owner)->post('/activity-types', ['name' => 'Setoran', 'counts_toward_progress' => '1'])->assertRedirect('/students');
        $activityId = DB::table('activity_types')->sole()->id;
        $this->actingAs($owner)->put("/activity-types/{$activityId}", ['name' => 'Murajaah', 'counts_toward_progress' => '0'])->assertRedirect('/students');
        $this->assertSame(0, DB::table('activity_types')->sole()->counts_toward_progress);
        $this->actingAs($owner)->post("/activity-types/{$activityId}/archive")->assertRedirect('/students');
        $this->assertNotNull(DB::table('activity_types')->sole()->archived_at);
    }

    public function test_guest_and_foreign_owner_cannot_read_or_mutate_master_data(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => 'C-01', 'name' => 'Cantik']);
        $studentId = DB::table('students')->sole()->id;
        $this->actingAs($owner)->post('/groups', ['name' => 'Milik Guru', 'student_ids' => [$studentId]]);
        $groupId = DB::table('study_groups')->sole()->id;
        $this->actingAs($owner)->post('/activity-types', ['name' => 'Milik Guru', 'counts_toward_progress' => '1']);
        $activityId = DB::table('activity_types')->sole()->id;
        auth()->logout();
        $this->get('/students')->assertRedirect('/login');
        $this->post('/students', ['code' => 'X', 'name' => 'X'])->assertRedirect('/login');
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->put("/students/{$studentId}", ['code' => 'X', 'name' => 'X'])->assertNotFound();
        $this->actingAs($other)->post("/students/{$studentId}/archive")->assertNotFound();
        $this->actingAs($other)->put("/groups/{$groupId}", ['name' => 'Diambil', 'student_ids' => []])->assertNotFound();
        $this->actingAs($other)->post("/groups/{$groupId}/archive")->assertNotFound();
        $this->actingAs($other)->put("/activity-types/{$activityId}", ['name' => 'Diambil', 'counts_toward_progress' => '0'])->assertNotFound();
        $this->actingAs($other)->post("/activity-types/{$activityId}/archive")->assertNotFound();
        $this->actingAs($other)->get('/students')->assertOk();
        $this->assertSame('Cantik', DB::table('students')->sole()->name);
        $this->assertSame('Milik Guru', DB::table('study_groups')->sole()->name);
    }

    public function test_invalid_student_or_group_payload_is_rejected_without_partial_write(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => '', 'name' => ''])->assertSessionHasErrors(['code', 'name']);
        $this->actingAs($owner)->post('/groups', ['name' => 'Kelompok', 'student_ids' => ['missing']])->assertSessionHasErrors('student_ids.0');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('study_groups', 0);
    }

    public function test_archiving_student_closes_membership_but_keeps_history(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => 'D-01', 'name' => 'Dina']);
        $studentId = DB::table('students')->sole()->id;
        $this->actingAs($owner)->post('/groups', ['name' => 'Kelompok', 'student_ids' => [$studentId]]);
        $membershipId = DB::table('group_memberships')->sole()->id;
        $this->actingAs($owner)->post("/students/{$studentId}/archive");
        $membership = DB::table('group_memberships')->sole();
        $this->assertSame($membershipId, $membership->id);
        $this->assertNotNull($membership->left_at);
        $this->assertNull($membership->active_student_id);
        $this->actingAs($owner)->post("/students/{$studentId}/restore");
        $this->assertDatabaseCount('group_memberships', 1);
        $this->assertNotNull(DB::table('group_memberships')->sole()->left_at);
    }
}
