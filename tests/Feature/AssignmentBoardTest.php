<?php

namespace Tests\Feature;

use App\Services\AssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssignmentBoardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'assignments.master_type.staff' => '1',
            'assignments.master_type.vehicle' => '2',
            'assignments.master_type.equipment' => '3',
            'assignments.company.workplace_name' => '会社',
            'assignments.company.soumu_staff_type' => 4,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_name');
            $table->unsignedTinyInteger('staff_type');
            $table->unsignedInteger('sort_number')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('m_workplace', function (Blueprint $table): void {
            $table->id();
            $table->string('workplace_name');
            $table->boolean('active_flg')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('m_vehicle', function (Blueprint $table): void {
            $table->id();
            $table->string('vehicle_name');
            $table->unsignedTinyInteger('vehicle_type');
            $table->unsignedInteger('sort_number')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('t_assignment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workplace_id');
            $table->date('work_date');
            $table->unsignedBigInteger('master_id');
            $table->string('master_type');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['master_type', 'master_id', 'workplace_id', 'work_date']);
        });
        Schema::create('t_absence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->date('work_date');
            $table->boolean('absence_flg')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('t_attendance', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('workplace_id');
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('break_time')->nullable();
            $table->time('midnight_start_time')->nullable();
            $table->integer('midnight_break_time')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('m_staff')->insert([
            ['id' => 1, 'staff_name' => '村田 亮介', 'staff_type' => 1, 'sort_number' => 1, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'staff_name' => '住吉 正己', 'staff_type' => 2, 'sort_number' => 2, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'staff_name' => '総務担当', 'staff_type' => 4, 'sort_number' => 3, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'staff_name' => '使用停止', 'staff_type' => 3, 'sort_number' => 4, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('m_workplace')->insert([
            ['id' => 10, 'workplace_name' => '滝ノ下', 'active_flg' => true, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'workplace_name' => '吉岡', 'active_flg' => true, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 30, 'workplace_name' => '会社', 'active_flg' => true, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 40, 'workplace_name' => '終了現場', 'active_flg' => false, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('m_vehicle')->insert([
            ['id' => 101, 'vehicle_name' => '4tダンプ', 'vehicle_type' => 1, 'sort_number' => 1, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 102, 'vehicle_name' => 'バックホウ0.25', 'vehicle_type' => 2, 'sort_number' => 2, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_board_data_uses_live_masters_assignments_and_absences(): void
    {
        DB::table('t_assignment')->insert([
            'workplace_id' => 10,
            'work_date' => '2026-08-31',
            'master_id' => 1,
            'master_type' => '1',
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('t_assignment')->insert([
            [
                'workplace_id' => 10,
                'work_date' => '2026-08-31',
                'master_id' => 101,
                'master_type' => '2',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workplace_id' => 10,
                'work_date' => '2026-08-31',
                'master_id' => 102,
                'master_type' => '3',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('t_absence')->insert([
            'staff_id' => 2,
            'work_date' => '2026-09-01',
            'absence_flg' => true,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $board = app(AssignmentService::class)->getBoardData('2026-08-31', 14);

        $this->assertSame(['滝ノ下', '吉岡'], array_column($board['workplaces'], 'name'));
        $this->assertSame(['村田 亮介', '住吉 正己'], array_column($board['staff'], 'name'));
        $this->assertSame([['workplace_id' => 10, 'work_date' => '2026-08-31', 'staff_id' => 1]], $board['assignments']);
        $this->assertSame([['staff_id' => 2, 'work_date' => '2026-09-01']], $board['absences']);
        $this->assertSame(['4tダンプ', 'バックホウ0.25'], array_column($board['machines'], 'name'));
        $this->assertSame(['車両', '重機'], array_column($board['machines'], 'type_label'));
    }

    public function test_assignment_page_keeps_legacy_view_as_default(): void
    {
        $response = $this->withSession(['login_user_id' => 1])->get(route('top.assignment', [
            'work_date' => '2026-08-31',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('top.assignment')
            ->assertSee('新しい2週間ボード');
    }

    public function test_assignment_page_can_switch_to_two_week_board(): void
    {
        $response = $this->withSession(['login_user_id' => 1])->get(route('top.assignment', [
            'view' => 'board',
            'start_date' => '2026-08-31',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('top.assignment_board')
            ->assertSee('従来表示に戻す')
            ->assertSee('車両・重機予定表')
            ->assertSee('view=board', false);
    }

    public function test_assignment_pdf_preview_keeps_all_technicians_when_more_than_three_are_assigned(): void
    {
        $technicians = collect(range(1, 4))
            ->map(fn (int $number): object => (object) [
                'staff_name' => '技術者'.$number,
                'staff_type' => 1,
            ])
            ->all();

        $service = \Mockery::mock(AssignmentService::class)->makePartial();
        $service->shouldReceive('GetAssignedWorkplace')->once()->andReturn(collect([
            (object) ['workplace_id' => 10, 'workplace_name' => '滝ノ下'],
        ]));
        $service->shouldReceive('GetStaffList')->andReturnUsing(
            fn (int $staffType): array => $staffType === 1 ? $technicians : []
        );
        $service->shouldReceive('GetVehicleList')->andReturn([]);
        $service->shouldReceive('GetEquipmentList')->andReturn([]);

        $viewData = $service->getPdf('2026-08-31');

        $this->assertIsArray($viewData);
        $this->assertSame(
            ['技術者1', '技術者2', '技術者3', '技術者4'],
            $viewData['pdf_data_list'][0]['workplace1']['technitian_list']
        );

        $html = view('pdf.assignment_all', $viewData)->render();
        $this->assertStringContainsString('技術者 技術者4', $html);
    }

    public function test_place_endpoint_moves_staff_and_preserves_saved_attendance(): void
    {
        DB::table('t_assignment')->insert([
            'workplace_id' => 10,
            'work_date' => '2026-08-31',
            'master_id' => 1,
            'master_type' => '1',
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('t_attendance')->insert([
            'staff_id' => 1,
            'workplace_id' => 10,
            'work_date' => '2026-08-31',
            'start_time' => '07:30:00',
            'end_time' => '17:00:00',
            'break_time' => 90,
            'midnight_start_time' => '19:00:00',
            'midnight_break_time' => 60,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = (array) DB::table('t_attendance')->first();
        $response = $this->withSession(['login_user_id' => 1])->postJson(route('top.assignment.board.place'), [
            'staff_id' => 1,
            'workplace_id' => 20,
            'work_date' => '2026-08-31',
            'start_date' => '2026-08-31',
        ]);

        $response->assertOk()->assertJsonPath('board.assignments.0.workplace_id', 20);
        $this->assertDatabaseMissing('t_assignment', ['workplace_id' => 10, 'master_id' => 1, 'work_date' => '2026-08-31']);
        $this->assertDatabaseHas('t_assignment', ['workplace_id' => 20, 'master_id' => 1, 'work_date' => '2026-08-31']);
        $this->assertSame(1, DB::table('t_attendance')->count());
        $after = (array) DB::table('t_attendance')->first();
        $this->assertEquals(Arr::except($before, ['workplace_id', 'updated_at']), Arr::except($after, ['workplace_id', 'updated_at']));
        $this->assertSame(20, $after['workplace_id']);
    }

    public function test_legacy_unassign_then_reassign_preserves_hours(): void
    {
        $service = app(AssignmentService::class);
        $this->assertTrue($service->AssignmentUpdate(10, '2026-08-31', [1 => 1], [], []));
        DB::table('t_attendance')->insert(['staff_id' => 1, 'workplace_id' => 10, 'work_date' => '2026-08-31', 'start_time' => '08:15:00', 'enabled' => true]);
        $this->assertTrue($service->AssignmentUpdate(10, '2026-08-31', [1 => 0], [], []));
        $this->assertSame(1, DB::table('t_attendance')->count());
        $this->assertTrue($service->AssignmentUpdate(20, '2026-08-31', [1 => 1], [], []));
        $this->assertDatabaseHas('t_attendance', ['staff_id' => 1, 'workplace_id' => 20, 'start_time' => '08:15:00', 'enabled' => true]);
    }

    public function test_conflicting_attendance_blocks_move_without_changing_records(): void
    {
        $service = app(AssignmentService::class);
        $this->assertTrue($service->AssignmentUpdate(10, '2026-08-31', [1 => 1], [], []));
        foreach ([10, 20] as $workplaceId) {
            DB::table('t_attendance')->insert(['staff_id' => 1, 'workplace_id' => $workplaceId, 'work_date' => '2026-08-31', 'start_time' => '08:15:00']);
        }
        $before = DB::table('t_attendance')->get()->toArray();
        $result = $service->placeStaffOnBoard(1, 20, '2026-08-31');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('複数', $result['message']);
        $this->assertEquals($before, DB::table('t_attendance')->get()->toArray());
        $this->assertDatabaseHas('t_assignment', ['master_id' => 1, 'workplace_id' => 10]);
    }

    public function test_board_unassign_then_reassign_preserves_attendance(): void
    {
        $service = app(AssignmentService::class);
        $this->assertTrue($service->placeStaffOnBoard(1, 10, '2026-08-31')['ok']);
        DB::table('t_attendance')->insert(['staff_id' => 1, 'workplace_id' => 10, 'work_date' => '2026-08-31', 'start_time' => '08:15:00', 'enabled' => true]);
        $this->assertTrue($service->removeStaffFromBoard(1, 10, '2026-08-31')['ok']);
        $this->assertSame(1, DB::table('t_attendance')->count());
        $this->assertTrue($service->placeStaffOnBoard(1, 20, '2026-08-31')['ok']);
        $this->assertDatabaseHas('t_attendance', ['workplace_id' => 20, 'start_time' => '08:15:00', 'enabled' => true]);
    }

    public function test_absent_staff_cannot_be_placed(): void
    {
        DB::table('t_absence')->insert([
            'staff_id' => 2,
            'work_date' => '2026-09-01',
            'absence_flg' => true,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['login_user_id' => 1])->postJson(route('top.assignment.board.place'), [
            'staff_id' => 2,
            'workplace_id' => 10,
            'work_date' => '2026-09-01',
            'start_date' => '2026-08-31',
        ]);

        $response->assertStatus(409)->assertJsonPath('message', '欠勤予定のため配置できません。');
        $this->assertSame(0, DB::table('t_assignment')->count());
    }

    public function test_monday_copy_uses_previous_friday_and_skips_absent_staff(): void
    {
        DB::table('t_assignment')->insert([
            ['workplace_id' => 10, 'work_date' => '2026-09-04', 'master_id' => 1, 'master_type' => '1', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['workplace_id' => 20, 'work_date' => '2026-09-04', 'master_id' => 2, 'master_type' => '1', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['workplace_id' => 20, 'work_date' => '2026-09-07', 'master_id' => 1, 'master_type' => '1', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('t_absence')->insert([
            'staff_id' => 2,
            'work_date' => '2026-09-07',
            'absence_flg' => true,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['login_user_id' => 1])->postJson(route('top.assignment.board.copy-day'), [
            'work_date' => '2026-09-07',
            'start_date' => '2026-09-07',
        ]);

        $response->assertOk()->assertJsonPath('message', '9/4の配置をコピーしました。');
        $this->assertDatabaseHas('t_assignment', ['workplace_id' => 10, 'work_date' => '2026-09-07', 'master_id' => 1]);
        $this->assertDatabaseMissing('t_assignment', ['work_date' => '2026-09-07', 'master_id' => 2]);
    }
}
