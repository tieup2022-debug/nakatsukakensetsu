<?php

namespace Tests\Feature;

use App\Services\StaffService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HideInactiveStaffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_name');
            $table->unsignedTinyInteger('staff_type')->default(3);
            $table->unsignedInteger('sort_number')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('t_attendance', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->date('work_date');
        });

        Schema::create('t_assignment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('master_id');
        });

        Schema::create('t_paid_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('applicant_staff_id');
        });

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->nullable();
        });
    }

    public function test_target_staff_is_hidden_without_deleting_related_data(): void
    {
        $now = now();
        DB::table('m_staff')->insert([
            [
                'id' => 10,
                'staff_name' => '使用無　大森　恋',
                'staff_type' => 3,
                'sort_number' => 1,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 20,
                'staff_name' => '大森 恋',
                'staff_type' => 3,
                'sort_number' => 2,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('t_attendance')->insert(['staff_id' => 10, 'work_date' => '2026-07-01']);
        DB::table('t_assignment')->insert(['master_id' => 10]);
        DB::table('t_paid_leave_requests')->insert(['applicant_staff_id' => 10]);
        DB::table('m_user')->insert(['staff_id' => 10]);

        $migration = require database_path('migrations/2026_08_17_000001_hide_inactive_staff_omori_ren.php');
        $migration->up();

        $this->assertNotNull(DB::table('m_staff')->where('id', 10)->value('deleted_at'));
        $this->assertNull(DB::table('m_staff')->where('id', 20)->value('deleted_at'));
        $this->assertSame([20], app(StaffService::class)->GetStaffList()->pluck('id')->all());

        $this->assertSame(1, DB::table('t_attendance')->where('staff_id', 10)->count());
        $this->assertSame(1, DB::table('t_assignment')->where('master_id', 10)->count());
        $this->assertSame(1, DB::table('t_paid_leave_requests')->where('applicant_staff_id', 10)->count());
        $this->assertSame(1, DB::table('m_user')->where('staff_id', 10)->count());
    }
}
