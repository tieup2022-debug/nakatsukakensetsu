<?php

namespace Tests\Feature;

use App\Services\PaidLeaveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaidLeaveHalfDayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'paid_leave.approver_staff_ids' => [],
            'paid_leave.excluded_staff_ids' => [],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->string('user_name');
            $table->unsignedTinyInteger('permission')->default(3);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('m_staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_name');
            $table->unsignedInteger('sort_number')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('t_in_app_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // 本番の改修前テーブルを再現し、既存行が1日扱いで移行されることも確認する。
        Schema::create('t_paid_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('applicant_staff_id');
            $table->unsignedBigInteger('applicant_user_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('approved_by_staff_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        DB::table('m_staff')->insert([
            [
                'id' => 10,
                'staff_name' => '管理者',
                'sort_number' => 1,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 20,
                'staff_name' => '対象社員',
                'sort_number' => 2,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('m_user')->insert([
            [
                'id' => 1,
                'user_name' => '管理ユーザー',
                'permission' => 1,
                'staff_id' => 10,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'user_name' => '一般ユーザー',
                'permission' => 3,
                'staff_id' => 20,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('t_paid_leave_requests')->insert([
            'applicant_staff_id' => 20,
            'applicant_user_id' => 2,
            'starts_at' => '2026-04-01 08:00:00',
            'ends_at' => '2026-04-01 17:00:00',
            'status' => 'approved',
            'approved_by_staff_id' => 10,
            'approved_by_user_id' => 1,
            'approved_at' => '2026-04-02 09:00:00',
            'reason' => '改修前の申請',
            'created_at' => '2026-04-01 07:00:00',
            'updated_at' => '2026-04-02 09:00:00',
        ]);

        $migration = require database_path('migrations/2026_07_21_000001_add_leave_days_and_entry_type_to_paid_leave_requests.php');
        $migration->up();
    }

    public function test_migration_keeps_existing_requests_as_one_day_applications(): void
    {
        $legacy = DB::table('t_paid_leave_requests')->first();

        $this->assertSame(1.0, (float) $legacy->leave_days);
        $this->assertSame('application', $legacy->entry_type);
    }

    public function test_admin_can_register_a_past_half_day_as_approved_without_notifications(): void
    {
        $response = $this->withSession(['login_user_id' => 1])
            ->from(route('paid-leave.index'))
            ->post(route('paid-leave.historical.store'), [
                'historical_staff_id' => 20,
                'historical_leave_date' => '2026-03-15',
                'historical_leave_days' => '0.5',
                'historical_reason' => '運用開始前の取得分',
            ]);

        $response->assertRedirect(route('paid-leave.index'));
        $response->assertSessionHas('status', '過去の有給取得実績を登録しました。');
        $this->assertDatabaseHas('t_paid_leave_requests', [
            'applicant_staff_id' => 20,
            'applicant_user_id' => 1,
            'starts_at' => '2026-03-15 00:00:00',
            'leave_days' => 0.5,
            'entry_type' => 'historical',
            'status' => 'approved',
            'approved_by_staff_id' => 10,
            'approved_by_user_id' => 1,
            'reason' => '運用開始前の取得分',
        ]);
        $this->assertSame(0, DB::table('t_in_app_notifications')->count());
    }

    public function test_non_admin_cannot_register_historical_leave(): void
    {
        $response = $this->withSession(['login_user_id' => 2])
            ->post(route('paid-leave.historical.store'), [
                'historical_staff_id' => 20,
                'historical_leave_date' => '2026-03-15',
                'historical_leave_days' => '0.5',
            ]);

        $response->assertRedirect(route('paid-leave.index'));
        $response->assertSessionHas('error');
        $this->assertSame(1, DB::table('t_paid_leave_requests')->count());
    }

    public function test_historical_entry_rejects_future_dates_and_quarter_days(): void
    {
        $futureResponse = $this->withSession(['login_user_id' => 1])
            ->from(route('paid-leave.index'))
            ->post(route('paid-leave.historical.store'), [
                'historical_staff_id' => 20,
                'historical_leave_date' => now()->addDay()->format('Y-m-d'),
                'historical_leave_days' => '0.5',
            ]);
        $futureResponse->assertSessionHasErrors('historical_leave_date');

        $quarterDayResponse = $this->withSession(['login_user_id' => 1])
            ->from(route('paid-leave.index'))
            ->post(route('paid-leave.historical.store'), [
                'historical_staff_id' => 20,
                'historical_leave_date' => now()->subDay()->format('Y-m-d'),
                'historical_leave_days' => '0.25',
            ]);
        $quarterDayResponse->assertSessionHasErrors('historical_leave_days');

        $this->assertSame(1, DB::table('t_paid_leave_requests')->count());
    }

    public function test_summary_sums_approved_pending_and_monthly_values_in_half_days(): void
    {
        DB::table('t_paid_leave_requests')->insert([
            [
                'applicant_staff_id' => 20,
                'applicant_user_id' => 2,
                'starts_at' => '2026-04-20 08:00:00',
                'ends_at' => '2026-04-20 12:00:00',
                'leave_days' => 0.5,
                'entry_type' => 'application',
                'status' => 'approved',
                'approved_by_staff_id' => 10,
                'approved_by_user_id' => 1,
                'approved_at' => '2026-04-18 09:00:00',
                'reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'applicant_staff_id' => 20,
                'applicant_user_id' => 2,
                'starts_at' => '2026-05-10 13:00:00',
                'ends_at' => '2026-05-10 17:00:00',
                'leave_days' => 0.5,
                'entry_type' => 'application',
                'status' => 'pending',
                'approved_by_staff_id' => null,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $summary = app(PaidLeaveService::class)->summarizeByStaff('2026-04-01', '2027-04-01');

        $this->assertSame(1.5, $summary[20]['approved']);
        $this->assertSame(0.5, $summary[20]['pending']);
        $this->assertSame(1.5, $summary[20]['monthly'][4]);
        $this->assertSame('2026-04-20', $summary[20]['last_approved']);
    }
}
