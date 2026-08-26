<?php

namespace Tests\Feature;

use App\Mail\PaidLeaveAppliedMail;
use App\Services\PaidLeaveService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaidLeaveNotificationEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'paid_leave.approver_staff_ids' => [],
            'paid_leave.notification_emails' => ['ogawara@e-nakatsuka.com'],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_name');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('permission')->default(3);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
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
        Schema::create('t_paid_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('applicant_staff_id');
            $table->unsignedBigInteger('applicant_user_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('leave_days', 3, 1)->default(1);
            $table->string('entry_type', 32)->default('application');
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('approved_by_staff_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        DB::table('m_staff')->insert([
            'id' => 20,
            'staff_name' => '申請者',
            'deleted_at' => null,
        ]);
    }

    public function test_paid_leave_application_is_sent_to_additional_notification_email(): void
    {
        Mail::fake();

        $request = app(PaidLeaveService::class)->createRequest(
            20,
            2,
            Carbon::parse('2026-09-01 08:00:00'),
            Carbon::parse('2026-09-01 17:00:00'),
            '私用',
            1.0
        );

        $this->assertNotFalse($request);
        Mail::assertSent(PaidLeaveAppliedMail::class, function (PaidLeaveAppliedMail $mail): bool {
            return $mail->hasTo('ogawara@e-nakatsuka.com');
        });
        Mail::assertSentCount(1);
    }
}
