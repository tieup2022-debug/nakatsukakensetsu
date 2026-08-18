<?php

namespace Tests\Feature;

use App\Mail\SystemInquiryReceivedMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemInquiryEmailNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'system_inquiry.notification_email' => 'tieup2022@gmail.com',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->string('user_name');
            $table->unsignedTinyInteger('permission')->default(3);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('t_system_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_user_name');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        DB::table('m_user')->insert([
            'id' => 10,
            'user_name' => '通知テストユーザー',
            'permission' => 3,
            'deleted_at' => null,
        ]);
    }

    public function test_new_inquiry_is_saved_and_notifies_configured_email(): void
    {
        Mail::fake();

        $response = $this->withSession(['login_user_id' => 10])
            ->post(route('inquiry.store'), [
                'body' => '勤怠画面について確認したいことがあります。',
            ]);

        $response->assertRedirect(route('inquiry.create'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('t_system_inquiries', [
            'submitted_by_user_id' => 10,
            'submitted_by_user_name' => '通知テストユーザー',
            'body' => '勤怠画面について確認したいことがあります。',
            'status' => 'pending',
        ]);

        Mail::assertSent(SystemInquiryReceivedMail::class, function (SystemInquiryReceivedMail $mail): bool {
            return $mail->hasTo('tieup2022@gmail.com')
                && $mail->submittedBy === '通知テストユーザー'
                && $mail->inquiryBody === '勤怠画面について確認したいことがあります。'
                && $mail->inquiryListUrl === route('setting.inquiry.index');
        });
    }

    public function test_invalid_notification_email_does_not_prevent_inquiry_registration(): void
    {
        Mail::fake();
        config(['system_inquiry.notification_email' => '']);

        $response = $this->withSession(['login_user_id' => 10])
            ->post(route('inquiry.store'), [
                'body' => 'メール設定がなくても登録されるお問い合わせです。',
            ]);

        $response->assertRedirect(route('inquiry.create'));
        $this->assertDatabaseHas('t_system_inquiries', [
            'body' => 'メール設定がなくても登録されるお問い合わせです。',
        ]);
        Mail::assertNothingSent();
    }

    public function test_mail_delivery_failure_does_not_prevent_inquiry_registration(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('tieup2022@gmail.com')
            ->andThrow(new \RuntimeException('Mail server is unavailable.'));

        $response = $this->withSession(['login_user_id' => 10])
            ->post(route('inquiry.store'), [
                'body' => 'メール障害時にも登録されるお問い合わせです。',
            ]);

        $response->assertRedirect(route('inquiry.create'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('t_system_inquiries', [
            'body' => 'メール障害時にも登録されるお問い合わせです。',
        ]);
    }
}
