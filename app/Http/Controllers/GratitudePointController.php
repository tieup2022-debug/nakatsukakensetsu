<?php

namespace App\Http\Controllers;

use App\Services\StaffService;
use Illuminate\Http\Request;

class GratitudePointController extends Controller
{
    public function __construct(
        private StaffService $staffService,
    ) {}

    /**
     * 感謝ポイント（準備中画面）
     */
    public function index(Request $request)
    {
        $staffList = $this->staffService->GetStaffList();
        if ($staffList === false || $staffList === null) {
            $staffList = collect();
        }

        return view('gratitude_points.index', [
            'staff_list' => $staffList,
            'title' => '感謝ポイント',
        ]);
    }
}
