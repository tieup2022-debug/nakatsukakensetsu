<?php

namespace Tests\Feature;

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
}
