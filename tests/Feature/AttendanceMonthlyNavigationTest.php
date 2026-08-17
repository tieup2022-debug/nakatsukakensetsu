<?php

namespace Tests\Feature;

use App\Services\AttendanceService;
use App\Services\WorkplaceService;
use Mockery\MockInterface;
use Tests\TestCase;

class AttendanceMonthlyNavigationTest extends TestCase
{
    public function test_monthly_page_has_previous_current_and_next_month_navigation(): void
    {
        $this->mock(WorkplaceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWorkplaceList')
                ->once()
                ->with(true)
                ->andReturn(collect([(object) ['id' => 1, 'workplace_name' => 'テスト現場']]));
        });

        $this->mock(AttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('GetAttendance')
                ->once()
                ->with('1', '2026-05-15')
                ->andReturn([
                    'workplace_id' => 1,
                    'work_date' => '2026-05-15',
                    'attendance_data' => [],
                ]);
            $mock->shouldReceive('GetAttendanceAllStaff')
                ->once()
                ->with(1, '2026-05-15')
                ->andReturn([]);
            $mock->shouldReceive('GetPdfData')
                ->once()
                ->with('2026-05-01')
                ->andReturn([
                    'attendance_table_list' => [[[
                        'staff_name' => 'テスト社員',
                        'has_midnight' => false,
                    ]]],
                    'date_list' => ['2026-05-01'],
                    'display_date' => '2026年05月01日(金)',
                    'today' => '2026年08月17日(月)',
                ]);
        });

        $response = $this->withSession(['login_user_id' => 1])
            ->get(route('top.attendance', [
                'workplace_id' => 1,
                'work_date' => '2026-05-15',
                'output_pdf' => 1,
            ]));

        $response->assertOk();
        $response->assertSee('表示月：2026年5月');
        $response->assertSee('← 前月');
        $response->assertSee('今月');
        $response->assertSee('翌月 →');
        $response->assertSee(route('top.attendance', [
            'workplace_id' => 1,
            'work_date' => '2026-04-01',
            'output_pdf' => 1,
        ]));
        $response->assertSee(route('top.attendance', [
            'workplace_id' => 1,
            'work_date' => '2026-06-01',
            'output_pdf' => 1,
        ]));
    }
}
