<?php

namespace App\Http\Controllers;

use App\Services\UtilizationRateService;
use Illuminate\Http\Request;

class SettingUtilizationRateController extends Controller
{
    private UtilizationRateService $utilizationRateService;

    public function __construct(UtilizationRateService $utilizationRateService)
    {
        $this->utilizationRateService = $utilizationRateService;
    }

    public function getUtilizationRate(Request $request)
    {
        $year = $request->input('year') ?? date('Y');
        $type = $request->query('type');
        $sort = $request->query('sort');
        $direction = $request->query('direction');

        $list = $this->utilizationRateService->GetUtilizationRateList($year, $type, $sort, $direction);

        return view('setting.utilizationrate.index')->with([
            'year' => $year,
            'select_year_list' => $this->utilizationRateService->GetSelectYearList() ?? [],
            'utilization_rate_list' => $list === false || $list === null ? [] : $list,
            'type' => $type,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}

