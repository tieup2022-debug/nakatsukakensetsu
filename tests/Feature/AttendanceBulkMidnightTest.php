<?php

namespace Tests\Feature;

use App\Services\AttendanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceBulkMidnightTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'assignments.master_type.staff' => '1',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_workplace', function (Blueprint $table): void {
            $table->id();
            $table->string('workplace_name');
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('t_assignment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workplace_id');
            $table->date('work_date');
            $table->unsignedBigInteger('master_id');
            $table->string('master_type');
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
            $table->integer('break_time')->default(0);
            $table->integer('absence_flg')->default(0);
            $table->integer('midnight_minutes')->nullable();
            $table->integer('midnight_overtime_minutes')->nullable();
            $table->time('midnight_start_time')->nullable();
            $table->time('midnight_end_time')->nullable();
            $table->integer('midnight_break_time')->nullable();
            $table->boolean('midnight_break_deduct_flg')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'work_date']);
        });

        DB::table('m_workplace')->insert([
            'id' => 1,
            'workplace_name' => 'テスト現場',
            'deleted_at' => null,
        ]);
        DB::table('t_assignment')->insert([
            [
                'workplace_id' => 1,
                'work_date' => '2026-07-29',
                'master_id' => 10,
                'master_type' => '1',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workplace_id' => 1,
                'work_date' => '2026-07-29',
                'master_id' => 20,
                'master_type' => '1',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_bulk_create_saves_a_night_only_shift_and_clears_midnight_values_for_absent_staff(): void
    {
        DB::table('t_attendance')->insert([
            'staff_id' => 20,
            'workplace_id' => 1,
            'work_date' => '2026-07-29',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_time' => 60,
            'absence_flg' => 0,
            'midnight_overtime_minutes' => 45,
            'midnight_start_time' => '19:00:00',
            'midnight_end_time' => '01:30:00',
            'midnight_break_time' => 60,
            'midnight_break_deduct_flg' => 1,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['login_user_id' => 1])
            ->post(route('setting.attendance.create'), [
                'mode' => 'create',
                'workplace_id' => 1,
                'work_date' => '2026-07-29',
                'start_time' => '',
                'end_time' => '',
                'break_minutes' => '',
                'midnight_start_time' => '19:00',
                'midnight_end_time' => '01:30',
                // 未入力でもサーバー側で既定の01:00にする。
                'midnight_break_time' => '',
                'midnight_break_deduct' => '1',
                'midnight_overtime_time' => '00:30',
                'absenceStaffList' => [10 => '0', 20 => '1'],
            ]);

        $response->assertRedirect(route('setting.attendance.list', [
            'workplace_id' => 1,
            'work_date' => '2026-07-29',
        ]));
        $response->assertSessionHas('status', '保存しました');

        $worked = DB::table('t_attendance')->where('staff_id', 10)->first();
        $this->assertNull($worked->start_time);
        $this->assertNull($worked->end_time);
        $this->assertSame(0, (int) $worked->break_time);
        $this->assertSame('19:00:00', $worked->midnight_start_time);
        $this->assertSame('01:30:00', $worked->midnight_end_time);
        $this->assertSame(60, (int) $worked->midnight_break_time);
        $this->assertSame(1, (int) $worked->midnight_break_deduct_flg);
        $this->assertSame(30, (int) $worked->midnight_overtime_minutes);
        $this->assertSame(0, (int) $worked->absence_flg);

        $absent = DB::table('t_attendance')->where('staff_id', 20)->first();
        $this->assertSame(1, (int) $absent->absence_flg);
        $this->assertNull($absent->midnight_start_time);
        $this->assertNull($absent->midnight_end_time);
        $this->assertNull($absent->midnight_break_time);
        $this->assertNull($absent->midnight_overtime_minutes);
        $this->assertSame(0, (int) $absent->midnight_break_deduct_flg);
    }

    public function test_bulk_create_rejects_an_incomplete_midnight_pair(): void
    {
        $response = $this->withSession(['login_user_id' => 1])
            ->post(route('setting.attendance.create'), [
                'mode' => 'create',
                'workplace_id' => 1,
                'work_date' => '2026-07-29',
                'start_time' => '',
                'end_time' => '',
                'break_minutes' => '',
                'midnight_start_time' => '19:00',
                'midnight_end_time' => '',
                'midnight_break_time' => '',
            ]);

        $response->assertRedirect(route('setting.attendance.manage'));
        $response->assertSessionHas(
            'status',
            '出勤・退勤、深夜出勤・深夜退勤は、それぞれ両方を入力してください。'
        );
        $this->assertSame(0, DB::table('t_attendance')->count());
    }

    public function test_monthly_data_keeps_previous_night_values_when_the_following_day_is_absent(): void
    {
        Schema::create('m_staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_name');
            $table->integer('sort_number')->default(0);
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('m_attendance_defaults', function (Blueprint $table): void {
            $table->id();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('break_time')->nullable();
            $table->boolean('is_enabled')->default(true);
        });
        Schema::create('t_absence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->date('work_date');
            $table->boolean('absence_flg')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('v_attendance_all', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('workplace_id');
            $table->date('work_date');
            $table->string('workplace_name')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('break_time')->nullable();
            $table->boolean('absence_flg')->default(false);
        });

        DB::table('m_staff')->insert([
            'id' => 10,
            'staff_name' => '夜勤 太郎',
            'sort_number' => 1,
            'deleted_at' => null,
        ]);
        DB::table('m_attendance_defaults')->insert([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_time' => 60,
            'is_enabled' => true,
        ]);
        DB::table('t_attendance')->insert([
            [
                'staff_id' => 10,
                'workplace_id' => 1,
                'work_date' => '2026-08-27',
                'start_time' => null,
                'end_time' => null,
                'break_time' => 0,
                'absence_flg' => 0,
                'midnight_overtime_minutes' => null,
                'midnight_start_time' => '20:00:00',
                'midnight_end_time' => '04:30:00',
                'midnight_break_time' => 60,
                'midnight_break_deduct_flg' => 1,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'staff_id' => 10,
                'workplace_id' => 1,
                'work_date' => '2026-08-28',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_time' => 0,
                'absence_flg' => 1,
                'midnight_overtime_minutes' => null,
                'midnight_start_time' => null,
                'midnight_end_time' => null,
                'midnight_break_time' => null,
                'midnight_break_deduct_flg' => 0,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $data = app(AttendanceService::class)->GetPdfData('2026-08-01');
        $cell = $data['attendance_table_list'][0][0]['2026-08-28'];

        $this->assertSame('#absence', $cell['workplace_name']);
        $this->assertSame('', $cell['start_time']);
        $this->assertSame('', $cell['end_time']);
        $this->assertSame('01:00', $cell['break_time']);
        $this->assertSame('07:30', $cell['worked_time']);
        $this->assertSame('20:00', $cell['midnight_start']);
        $this->assertSame('04:30', $cell['midnight_end']);
        $this->assertSame('05:30', $cell['midnight_time']);

        $html = view('top.attendance_monthly', array_merge($data, [
            'display_month' => '2026年8月',
            'filter_work_date' => '2026-08-01',
            'previous_month_work_date' => '2026-07-01',
            'current_month_work_date' => '2026-08-01',
            'next_month_work_date' => '2026-09-01',
        ]))->render();

        $this->assertStringContainsString('>欠<', $html);
        $this->assertStringContainsString('>01:00<', $html);
        $this->assertStringContainsString('>07:30<', $html);
        $this->assertStringContainsString('>20:00<', $html);
        $this->assertStringContainsString('>04:30<', $html);
        $this->assertStringContainsString('>05:30<', $html);
    }
}
