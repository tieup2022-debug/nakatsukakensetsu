<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttendanceService
{
    private const LOCAL_ATTENDANCE_FILE = 'app/local_attendances.json';

    /**
     * 一覧・更新クエリ用に作業日を Y-m-d へ揃える（空は今日、解釈不能も今日にフォールバック）。
     */
    private function coerceWorkDateYmd($workDate): string
    {
        if ($workDate === null || $workDate === '') {
            return date('Y-m-d');
        }
        try {
            return Carbon::parse((string) $workDate, config('app.timezone'))->format('Y-m-d');
        } catch (\Throwable) {
            return date('Y-m-d');
        }
    }

    /**
     * work_date 列が DATE / DATETIME どちらでも同日一致させる（= だけだと既存行を拾えない環境がある）。
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    private function whereWorkDateEquals($query, string $workDateNorm)
    {
        return $query->whereRaw('DATE(work_date) = ?', [$workDateNorm]);
    }

    /**
     * work_date 列の型・形式差（DATE / DATETIME / 文字列）でも同日を拾う。
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyWorkDateScope($query, string $workDateNorm)
    {
        return $query->where(function ($q) use ($workDateNorm) {
            $this->whereWorkDateEquals($q, $workDateNorm);
            $q->orWhere('work_date', '=', $workDateNorm)
                ->orWhere('work_date', 'like', $workDateNorm.' %');
        });
    }

    /**
     * 指定社員・指定日が欠勤か（t_attendance の欠勤フラグまたは t_absence）。
     */
    public function staffIsAbsentOnDate(int $staffId, string $workDate): bool
    {
        return $this->isStaffAbsentOnDate($staffId, $workDate);
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return Collection<int, object>
     */
    public function enforceAbsentStateOnRows(iterable $rows, string $workDate): Collection
    {
        $workDateNorm = $this->coerceWorkDateYmd($workDate);

        return collect($rows)->map(function ($row) use ($workDateNorm) {
            $sid = (int) ($row->staff_id ?? 0);
            if ($sid > 0 && $this->isStaffAbsentOnDate($sid, $workDateNorm)) {
                $row->absence_flg = 1;
                $row->start_time = null;
                $row->end_time = null;
                $row->break_time = null;
                $row->display_start = '';
                $row->display_end = '';
                $row->display_break = '';
            }

            return $row;
        })->values();
    }

    /**
     * 指定社員・指定日が欠勤か（t_attendance の欠勤フラグまたは t_absence）。
     */
    private function isStaffAbsentOnDate(int $staffId, string $workDate): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        $workDateNorm = $this->coerceWorkDateYmd($workDate);

        try {
            $attendanceAbsentQuery = DB::table('t_attendance')
                ->where('staff_id', '=', $staffId)
                ->whereNull('deleted_at')
                ->where('absence_flg', '!=', 0);
            $this->applyWorkDateScope($attendanceAbsentQuery, $workDateNorm);
            if ($attendanceAbsentQuery->exists()) {
                return true;
            }

            $absenceQuery = DB::table('t_absence')
                ->where('staff_id', '=', $staffId)
                ->whereNull('deleted_at');
            $this->applyWorkDateScope($absenceQuery, $workDateNorm);

            return $absenceQuery->exists();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 勤怠一覧 取得
     */
    public function GetAttendance($workplaceId = null, $workDate = null)
    {
        try {
            $workDate = $this->coerceWorkDateYmd($workDate);

            if (is_null($workplaceId)) {
                $attendanceData = DB::table('t_attendance')
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->orderBy('workplace_id', 'asc')
                    ->first();

                if ($attendanceData) {
                    $workplaceId = $attendanceData->workplace_id;
                } else {
                    $workplaceId = null;
                }
            }

            $attendance = DB::table('v_attendance')
                ->where('workplace_id', '=', $workplaceId)
                ->where('work_date', '=', $workDate)
                ->orderBy('staff_type', 'ASC')
                ->orderBy('staff_name')
                ->get();

            return [
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'attendance_data' => $attendance,
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 勤怠 全社員の勤怠を取得
     */
    public function GetAttendanceAllStaff($workplaceId, $workDate)
    {
        try {
            if ($workplaceId === null || $workplaceId === '' || $workDate === null || $workDate === '') {
                return false;
            }
            $workDate = $this->coerceWorkDateYmd($workDate);

            $query = DB::table('v_attendance_all')
                ->where('workplace_id', '=', $workplaceId);
            $this->whereWorkDateEquals($query, $workDate);

            return $query->orderBy('staff_name')->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            // ローカルDB未接続でも「勤怠一括入力」の対象社員リストを表示できるようにする
            if (app()->environment('local')) {
                return $this->getLocalAttendanceAllStaff((int) $workplaceId, (string) $workDate);
            }

            return false;
        }
    }

    /**
     * ビュー由来の一覧行に、実テーブル t_attendance の時刻を上書きする。
     *
     * v_attendance_all は「配置現場と t_attendance.workplace_id が一致」で JOIN するため、
     * 現場が変わった直後など JOIN が外れて時刻が NULL → 画面既定(08:00等)になることがある。
     * 保存後も古い表示のままに見える原因になるため、staff_id + work_date で実レコードを優先する。
     *
     * 同一日に複数行ある場合は「今開いている現場」と workplace_id が一致する行を最優先し、
     * 無ければ id 降順（従来どおり）。DB が過去の別現場行と混在している環境での取り違えを防ぐ。
     *
     * @param  iterable<int, object>  $rows
     * @param  int|string|null  $preferredWorkplaceId  トップ画面で選択中の現場
     * @return Collection<int, object>
     */
    public function overlayTAttendanceTimes(iterable $rows, string $workDate, $preferredWorkplaceId = null): Collection
    {
        $collection = collect($rows);
        if ($collection->isEmpty()) {
            return $collection;
        }

        try {
            $workDateNorm = Carbon::parse($workDate)->format('Y-m-d');
        } catch (\Throwable) {
            return $collection;
        }

        $ids = $collection->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $collection;
        }

        try {
            $dbQuery = DB::table('t_attendance')
                ->whereIn('staff_id', $ids)
                ->whereNull('deleted_at');
            $this->applyWorkDateScope($dbQuery, $workDateNorm);
            $dbRows = $dbQuery->orderByDesc('id')->get();

            $byStaff = collect($this->pickBestAttendanceRowPerStaff($dbRows, $preferredWorkplaceId));
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return $collection;
        }

        $collection = $collection->map(function ($row) use ($byStaff) {
            $sid = (int) ($row->staff_id ?? 0);
            $raw = $byStaff->get($sid);
            if (! $raw) {
                return $row;
            }

            $row->absence_flg = $raw->absence_flg;
            $absent = isset($raw->absence_flg) && (int) $raw->absence_flg !== 0;
            if ($absent) {
                $row->start_time = null;
                $row->end_time = null;
                $row->break_time = null;
            } else {
                $row->start_time = $raw->start_time;
                $row->end_time = $raw->end_time;
                $row->break_time = $raw->break_time;
            }

            return $row;
        })->values();

        return $this->overlayAbsenceFlags($collection, $workDateNorm);
    }

    /**
     * t_absence に登録がある日は、一覧表示を必ず欠勤扱いに揃える。
     *
     * @param  iterable<int, object>  $rows
     * @return Collection<int, object>
     */
    public function overlayAbsenceFlags(iterable $rows, string $workDate): Collection
    {
        $collection = collect($rows);
        if ($collection->isEmpty()) {
            return $collection;
        }

        $workDateNorm = $this->coerceWorkDateYmd($workDate);
        $ids = $collection->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($ids === []) {
            return $collection;
        }

        try {
            $absenceQuery = DB::table('t_absence')
                ->whereIn('staff_id', $ids)
                ->whereNull('deleted_at');
            $this->applyWorkDateScope($absenceQuery, $workDateNorm);
            $absenceIds = $absenceQuery->pluck('staff_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $attendanceAbsentQuery = DB::table('t_attendance')
                ->whereIn('staff_id', $ids)
                ->whereNull('deleted_at')
                ->where('absence_flg', '!=', 0);
            $this->applyWorkDateScope($attendanceAbsentQuery, $workDateNorm);
            $attendanceAbsentIds = $attendanceAbsentQuery->pluck('staff_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $absenceIds = array_values(array_unique(array_merge($absenceIds, $attendanceAbsentIds)));
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return $collection;
        }

        if ($absenceIds === []) {
            return $collection;
        }
        $absenceSet = array_fill_keys($absenceIds, true);

        return $collection->map(function ($row) use ($absenceSet) {
            $sid = (int) ($row->staff_id ?? 0);
            if ($sid > 0 && isset($absenceSet[$sid])) {
                $row->absence_flg = 1;
                $row->start_time = null;
                $row->end_time = null;
                $row->break_time = null;
            }

            return $row;
        })->values();
    }

    /**
     * 勤怠トップのフォーム用: 同一 staff_id の行が複数あると input name が重複し、
     * POST では最後の行の値だけが送られるため、社員1人1行にまとめる。
     *
     * @param  iterable<int, object>  $rows
     * @return Collection<int, object>
     */
    public function uniqueAttendanceRowsForForm(iterable $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($r) => (int) ($r->staff_id ?? 0) > 0)
            ->sortBy(function ($r) {
                $absentFirst = (isset($r->absence_flg) && (int) $r->absence_flg !== 0) ? 0 : 1;

                return [$absentFirst, (int) ($r->sort_number ?? 99999), (int) ($r->staff_id ?? 0)];
            })
            ->unique(fn ($r) => (int) ($r->staff_id ?? 0))
            ->values();
    }

    /**
     * トップ勤怠保存用: 同一日内の t_attendance を社員IDで引く。
     * 複数行ある場合は preferredWorkplaceId に一致する行を優先（オーバーレイと同じ基準）。
     *
     * @param  array<int>  $staffIds
     * @param  int|string|null  $preferredWorkplaceId
     * @return array<int, object>
     */
    public function getAttendanceRowsByStaffForDate(array $staffIds, string $workDate, $preferredWorkplaceId = null): array
    {
        if ($staffIds === []) {
            return [];
        }

        $workDateNorm = $this->coerceWorkDateYmd($workDate);

        try {
            $rowsQuery = DB::table('t_attendance')
                ->whereIn('staff_id', $staffIds)
                ->whereNull('deleted_at');
            $this->applyWorkDateScope($rowsQuery, $workDateNorm);
            $rows = $rowsQuery->orderByDesc('id')->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return [];
        }

        return $this->pickBestAttendanceRowPerStaff($rows, $preferredWorkplaceId);
    }

    /**
     * 同一 staff_id・同一日の t_attendance が複数あるとき 1 行に絞る。
     *
     * @param  iterable<int, object>  $rows
     * @param  int|string|null  $preferredWorkplaceId
     * @return array<int, object>
     */
    private function pickBestAttendanceRowPerStaff(iterable $rows, $preferredWorkplaceId): array
    {
        $wp = (int) ($preferredWorkplaceId ?? 0);

        $grouped = collect($rows)->groupBy(fn ($r) => (int) ($r->staff_id ?? 0));

        $map = [];
        foreach ($grouped as $sidKey => $group) {
            $sid = (int) $sidKey;
            if ($sid <= 0) {
                continue;
            }
            $chosen = $this->chooseBestAttendanceRowFromList($group->values(), $wp);
            if ($chosen) {
                $map[$sid] = $chosen;
            }
        }

        return $map;
    }

    /**
     * 同一社員の複数行から表示・更新対象の1行を選ぶ。
     * 欠勤行（absence_flg=1）を最優先し、次に選択中現場一致、最後に id 降順。
     *
     * @param  iterable<int, object>  $rows
     */
    private function chooseBestAttendanceRowFromList(iterable $rows, int $preferredWorkplaceId): ?object
    {
        $list = collect($rows)->sortByDesc(fn ($r) => (int) ($r->id ?? 0))->values();
        if ($list->isEmpty()) {
            return null;
        }

        $absentRows = $list->filter(fn ($r) => (int) ($r->absence_flg ?? 0) !== 0);
        if ($absentRows->isNotEmpty()) {
            if ($preferredWorkplaceId > 0) {
                $wpAbsent = $absentRows->first(fn ($r) => (int) ($r->workplace_id ?? 0) === $preferredWorkplaceId);
                if ($wpAbsent) {
                    return $wpAbsent;
                }
            }

            return $absentRows->first();
        }

        if ($preferredWorkplaceId > 0) {
            $wpMatch = $list->first(fn ($r) => (int) ($r->workplace_id ?? 0) === $preferredWorkplaceId);
            if ($wpMatch) {
                return $wpMatch;
            }
        }

        return $list->first();
    }

    /**
     * 月次表向け: staff+日付の t_attendance から最適な1行を選ぶ（配置現場優先、別現場に時刻だけある行も拾う）
     */
    private function resolveAttendanceRawForMonthly(int $staffId, string $fullDate, $preferredWorkplaceId): ?object
    {
        $rawQuery = DB::table('t_attendance')
            ->where('staff_id', '=', $staffId)
            ->whereNull('deleted_at');
        $this->applyWorkDateScope($rawQuery, $this->coerceWorkDateYmd($fullDate));
        $rawRows = $rawQuery->orderByDesc('id')
            ->get(['id', 'workplace_id', 'start_time', 'end_time', 'break_time', 'absence_flg']);

        if ($rawRows->isEmpty()) {
            return null;
        }

        $bestMap = $this->pickBestAttendanceRowPerStaff($rawRows, $preferredWorkplaceId);
        $chosen = $bestMap[$staffId] ?? null;

        // 欠勤行（時刻なし）を、別現場の出勤時刻あり行より優先する
        $absentRow = $rawRows->first(fn ($r) => (int) ($r->absence_flg ?? 0) !== 0);
        if ($absentRow) {
            return $absentRow;
        }

        if ($chosen && $this->formatTimeShort($chosen->start_time ?? '') !== '') {
            return $chosen;
        }

        $withTimes = $rawRows->first(fn ($r) => $this->formatTimeShort($r->start_time ?? '') !== '');

        return $withTimes ?? $chosen;
    }

    /**
     * 勤怠一覧・帳票向け: DB の時刻値を HH:MM に正規化（日時型や TIME 文字列に対応）
     */
    public function formatTimeForDisplay($time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        $s = trim((string) $time);
        if ($s === '') {
            return '';
        }

        // 旧/環境差異の互換:
        // - "90"   => 01:30（分）
        // - "1.5"  => 01:30（時間）
        // - "1"    => 01:00（時間）
        if (is_numeric($s)) {
            $num = (float) $s;
            if ($num < 0) {
                return '';
            }
            $minutes = str_contains($s, '.') ? (int) round($num * 60) : (int) $num;
            if ($minutes <= 0) {
                return '00:00';
            }
            // 整数で 0-23 は「時間」扱い（旧データ互換）
            if (! str_contains($s, '.') && $minutes <= 23) {
                $minutes *= 60;
            }
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            return sprintf('%02d:%02d', $hours, $mins);
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?/', $s, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/', $s, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    /**
     * 休憩時間の表示用整形（HH:MM）。
     *
     * break_time 列は「分」を整数で保存する（60=1時間, 15=15分）。
     * formatTimeForDisplay は 23 以下の整数を「時間」と解釈する旧データ互換があり、
     * 15 分休憩が 15:00（15時間）になってしまうため、休憩は必ず分として解釈する。
     */
    public function formatBreakForDisplay($break): string
    {
        if ($break === null || $break === '') {
            return '';
        }
        $s = trim((string) $break);
        if ($s === '') {
            return '';
        }

        $minutes = $this->breakStoreValueToMinutes($s);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 勤怠一覧行に表示用の時刻を付与（未登録・NULL 時は m_attendance_defaults 相当の既定値）
     */
    public function withListDisplayTimes(iterable $rows, object $defaults): Collection
    {
        $rows = collect($rows);

        $fallbackStart = $this->formatTimeForDisplay($defaults->start_time ?? null) ?: '08:00';
        $fallbackEnd = $this->formatTimeForDisplay($defaults->end_time ?? null) ?: '17:00';
        $fallbackBreak = $this->formatBreakForDisplay($defaults->break_time ?? null) ?: '01:00';

        return $rows->map(function ($row) use ($fallbackStart, $fallbackEnd, $fallbackBreak) {
            $absent = isset($row->absence_flg) && (int) $row->absence_flg !== 0;
            if ($absent) {
                $row->display_start = '';
                $row->display_end = '';
                $row->display_break = '';

                return $row;
            }

            $s = $this->formatTimeForDisplay($row->start_time ?? null);
            $e = $this->formatTimeForDisplay($row->end_time ?? null);
            $b = $this->formatBreakForDisplay($row->break_time ?? null);

            $row->display_start = $s !== '' ? $s : $fallbackStart;
            $row->display_end = $e !== '' ? $e : $fallbackEnd;
            $row->display_break = $b !== '' ? $b : $fallbackBreak;

            return $row;
        });
    }

    /**
     * t_absence を t_attendance の欠勤状態と同期（失敗しても勤怠本体の保存は継続する）。
     */
    private function syncAbsenceTable(int $staffId, string $workDateNorm, bool $isAbsent): void
    {
        try {
            if (! Schema::hasTable('t_absence')) {
                return;
            }

            $scopedQuery = DB::table('t_absence')->where('staff_id', '=', $staffId);
            if (Schema::hasColumn('t_absence', 'deleted_at')) {
                $scopedQuery->whereNull('deleted_at');
            }
            $this->applyWorkDateScope($scopedQuery, $workDateNorm);

            if ($isAbsent) {
                $absenceRow = $scopedQuery->first();
                if ($absenceRow) {
                    $update = ['updated_at' => now()];
                    if (Schema::hasColumn('t_absence', 'absence_flg')) {
                        $update['absence_flg'] = 1;
                    }
                    DB::table('t_absence')->where('id', '=', (int) $absenceRow->id)->update($update);
                } else {
                    $insert = [
                        'staff_id' => $staffId,
                        'work_date' => $workDateNorm,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('t_absence', 'absence_flg')) {
                        $insert['absence_flg'] = 1;
                    }
                    DB::table('t_absence')->insert($insert);
                }
            } else {
                $scopedQuery->delete();
            }
        } catch (\Exception $e) {
            Log::warning('AttendanceUpdate: t_absence の同期に失敗（t_attendance は保存済み）', [
                'staff_id' => $staffId,
                'work_date' => $workDateNorm,
                'is_absent' => $isAbsent,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 勤怠 更新
     */
    public function AttendanceUpdate($staffId, $workplaceId, $workDate, $startTime, $endTime, $breakTime, $absenceFlg = false)
    {
        try {
            // 個別編集では休憩未入力や type=time の未送信で null になり得るため、時刻は空文字に正規化して扱う
            if ($staffId === null || $staffId === '' || $workplaceId === null || $workplaceId === '' || $workDate === null || $workDate === '') {
                return false;
            }

            try {
                $workDateNorm = Carbon::parse((string) $workDate)->format('Y-m-d');
            } catch (\Throwable) {
                return false;
            }

            $absenceForDb = (int) ((bool) $absenceFlg);

            // 欠勤時に start_time/end_time へ空文字を書くと MySQL の TIME 型で例外になり、
            // APP_ENV=local では DB を更新せず upsertLocalAttendance だけ成功して「欠勤保存したのに反映されない」になる。
            if ($absenceForDb === 1) {
                // start_time / end_time は NOT NULL の環境があるため NULL は使わない（画面は absence_flg で時刻非表示）
                $payload = [
                    'workplace_id' => (int) $workplaceId,
                    'work_date' => $workDateNorm,
                    'break_time' => $this->prepareBreakTimeForStorage(''),
                    'absence_flg' => 1,
                    'updated_at' => now(),
                ];
            } else {
                $startTime = (string) ($startTime ?? '');
                $endTime = (string) ($endTime ?? '');
                $breakTime = (string) ($breakTime ?? '');

                $startTime = $this->normalizeClockForSqlTime($startTime);
                $endTime = $this->normalizeClockForSqlTime($endTime);
                $breakTimeForStorage = $this->prepareBreakTimeForStorage($breakTime);

                $payload = [
                    'workplace_id' => (int) $workplaceId,
                    'work_date' => $workDateNorm,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'break_time' => $breakTimeForStorage,
                    'absence_flg' => 0,
                    'updated_at' => now(),
                ];
            }

            DB::beginTransaction();

            $candidateQuery = DB::table('t_attendance')
                ->where('staff_id', '=', (int) $staffId)
                ->whereNull('deleted_at');
            $this->applyWorkDateScope($candidateQuery, $workDateNorm);
            $candidateRows = $candidateQuery->orderByDesc('id')->get();

            $rowId = null;
            if ($absenceForDb === 1) {
                // 欠勤は「日単位」なので、同一 staff_id / work_date の複数行をすべて欠勤化する。
                // 1行だけ更新すると、表示側が別行（欠勤=0）を拾って初期値に見えることがある。
                if ($candidateRows->isNotEmpty()) {
                    $ids = $candidateRows->pluck('id')->map(fn ($id) => (int) $id)->all();
                    DB::table('t_attendance')
                        ->whereIn('id', $ids)
                        ->update([
                            'workplace_id' => (int) $workplaceId,
                            'break_time' => $this->prepareBreakTimeForStorage(''),
                            'absence_flg' => 1,
                            'updated_at' => now(),
                        ]);

                    $chosenByStaff = $this->pickBestAttendanceRowPerStaff($candidateRows, (int) $workplaceId);
                    $row = $chosenByStaff[(int) $staffId] ?? $candidateRows->first();
                    $rowId = $row ? (int) $row->id : null;
                    if ($rowId) {
                        DB::table('t_attendance')
                            ->where('id', '=', $rowId)
                            ->update([
                                'workplace_id' => (int) $workplaceId,
                                'work_date' => $workDateNorm,
                            ]);
                    }
                } else {
                    $insertRow = array_merge($payload, [
                        'staff_id' => (int) $staffId,
                        'start_time' => $this->normalizeClockForSqlTime('08:00'),
                        'end_time' => $this->normalizeClockForSqlTime('17:00'),
                        'deleted_at' => null,
                        'created_at' => now(),
                    ]);
                    $rowId = (int) DB::table('t_attendance')->insertGetId($insertRow);
                }
            } else {
                // 通常勤務に戻すときは同日全行の欠勤フラグを解除（別行だけ欠勤のまま残るのを防ぐ）
                if ($candidateRows->isNotEmpty()) {
                    $ids = $candidateRows->pluck('id')->map(fn ($id) => (int) $id)->all();
                    DB::table('t_attendance')
                        ->whereIn('id', $ids)
                        ->update([
                            'absence_flg' => 0,
                            'updated_at' => now(),
                        ]);
                }

                // 通常勤務は、現場一致を優先した1行だけを時刻付きで更新する。
                $chosenByStaff = $this->pickBestAttendanceRowPerStaff($candidateRows, (int) $workplaceId);
                $row = $chosenByStaff[(int) $staffId] ?? null;
                if ($row) {
                    $rowId = (int) $row->id;
                    DB::table('t_attendance')
                        ->where('id', '=', $rowId)
                        ->update($payload);
                } else {
                    $insertRow = array_merge($payload, [
                        'staff_id' => (int) $staffId,
                        'deleted_at' => null,
                        'created_at' => now(),
                    ]);
                    $rowId = (int) DB::table('t_attendance')->insertGetId($insertRow);
                }
            }
            if (! $rowId || $rowId <= 0) {
                DB::rollBack();

                return false;
            }

            $fresh = DB::table('t_attendance')->where('id', '=', $rowId)->first();
            if (! $fresh) {
                DB::rollBack();

                return false;
            }

            if ($absenceForDb === 1) {
                if ((int) ($fresh->absence_flg ?? 0) === 0) {
                    DB::rollBack();
                    Log::warning('AttendanceUpdate: 欠勤フラグがDBに反映されませんでした', [
                        'row_id' => $rowId,
                        'staff_id' => (int) $staffId,
                        'work_date' => $workDateNorm,
                        'absence_flg_actual' => $fresh->absence_flg ?? null,
                    ]);

                    return false;
                }
            } else {
                $startTime = (string) ($payload['start_time'] ?? '');
                $endTime = (string) ($payload['end_time'] ?? '');
                foreach (['start_time' => $startTime, 'end_time' => $endTime] as $column => $expected) {
                    if ($expected === '') {
                        continue;
                    }
                    $got = $this->normalizeClockForSqlTime($this->formatTimeForDisplay($fresh->{$column} ?? ''));
                    $expectedHm = substr($expected, 0, 5);
                    $gotHm = substr($got, 0, 5);
                    if ($gotHm !== '' && $expectedHm !== $gotHm) {
                        Log::warning('AttendanceUpdate: DB上の時刻が保存値と一致しません（保存は継続）', [
                            'row_id' => $rowId,
                            'staff_id' => (int) $staffId,
                            'work_date' => $workDateNorm,
                            'column' => $column,
                            'expected' => $expected,
                            'actual_raw' => $fresh->{$column} ?? null,
                            'actual_norm' => $got,
                        ]);
                    }
                }
            }

            Log::info('AttendanceUpdate persisted row', [
                'row_id' => (int) $rowId,
                'staff_id' => (int) $staffId,
                'workplace_id' => (int) $workplaceId,
                'work_date' => (string) $workDateNorm,
                'requested_absence' => (int) $absenceForDb,
                'fresh_absence' => (int) ($fresh->absence_flg ?? 0),
                'fresh_start' => $fresh->start_time ?? null,
                'fresh_end' => $fresh->end_time ?? null,
                'fresh_break' => $fresh->break_time ?? null,
            ]);

            $this->syncAbsenceTable((int) $staffId, $workDateNorm, $absenceForDb === 1);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $this->upsertLocalAttendance(
                    (int) $staffId,
                    (int) $workplaceId,
                    (string) $workDate,
                    (string) $startTime,
                    (string) $endTime,
                    (string) $breakTime,
                    (int) $absenceFlg
                );

                return true;
            }

            return false;
        }
    }

    /**
     * 勤怠 個別に勤怠を取得
     */
    public function GetAttendanceStaff($staffId, $workplaceId, $workDate)
    {
        $result = null;

        try {
            if (isset($staffId) && isset($workplaceId) && isset($workDate)) {
                $workDateNorm = $this->coerceWorkDateYmd($workDate);
                $query = DB::table('t_attendance')
                    ->where('staff_id', '=', $staffId)
                    ->whereNull('deleted_at');
                $this->whereWorkDateEquals($query, $workDateNorm);
                $rows = $query->orderByDesc('id')->get();
                $map = $this->pickBestAttendanceRowPerStaff($rows, (int) $workplaceId);

                return $map[(int) $staffId] ?? $rows->first();
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getLocalAttendanceStaff((int) $staffId, (int) $workplaceId, (string) $workDate);
            }

            return false;
        }
    }

    /**
     * 勤怠 新規登録（一括）
     */
    public function AttendanceCreate($workplaceId, $workDate, $startTime, $endTime, $breakTime, $absenceStaffList = null)
    {
        try {
            DB::beginTransaction();
            $breakTimeForStorage = $this->prepareBreakTimeForStorage($breakTime);

            if (isset($workplaceId) && isset($workDate) && isset($startTime) && isset($endTime) && isset($breakTime)) {
                $assignedStaffList = DB::table('t_assignment')
                    ->where('master_type', '=', config('assignments.master_type.staff'))
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($assignedStaffList as $assignedStaff) {
                    if (isset($absenceStaffList)) {
                        $absenceFlg = isset($absenceStaffList[$assignedStaff->master_id]) && intval($absenceStaffList[$assignedStaff->master_id]) === 1;
                    } else {
                        $absenceFlg = false;
                    }

                    $existsCheck = DB::table('t_attendance')
                        ->where('staff_id', '=', $assignedStaff->master_id)
                        ->where('work_date', '=', $workDate)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($existsCheck) {
                        DB::table('t_attendance')
                            ->where('id', '=', $existsCheck->id)
                            ->update([
                                'staff_id' => $assignedStaff->master_id,
                                'workplace_id' => $workplaceId,
                                'work_date' => $workDate,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'break_time' => $breakTimeForStorage,
                                'absence_flg' => $absenceFlg,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('t_attendance')->upsert(
                            [[
                                'staff_id' => $assignedStaff->master_id,
                                'workplace_id' => $workplaceId,
                                'work_date' => $workDate,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'break_time' => $breakTimeForStorage,
                                'absence_flg' => $absenceFlg,
                                'deleted_at' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]],
                            ['staff_id', 'work_date'],
                            ['workplace_id', 'start_time', 'end_time', 'break_time', 'absence_flg', 'deleted_at', 'updated_at']
                        );
                    }
                }

                DB::commit();

                return true;
            }

            DB::rollback();

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            // ローカルDB未接続時は、ローカルJSONへ保存して画面反映できるようにする
            if (app()->environment('local')) {
                $assignedStaffIds = $this->getLocalAssignedStaffIds((int) $workplaceId, (string) $workDate);
                if (! empty($assignedStaffIds)) {
                    foreach (array_keys($assignedStaffIds) as $staffId) {
                        $absenceRaw = $absenceStaffList[$staffId] ?? 0;
                        if (is_array($absenceRaw)) {
                            $absenceRaw = end($absenceRaw);
                        }
                        $absenceFlg = intval($absenceRaw);
                        $this->upsertLocalAttendance(
                            (int) $staffId,
                            (int) $workplaceId,
                            (string) $workDate,
                            (string) $startTime,
                            (string) $endTime,
                            (string) $breakTime,
                            (int) $absenceFlg
                        );
                    }
                }

                return true;
            }

            return false;
        }
    }

    /**
     * 勤怠 社員/現場/作業日を指定して削除
     */
    public function AttendanceDelete($staffId, $workplaceId, $workDate)
    {
        try {
            if (isset($staffId) && isset($workplaceId) && isset($workDate)) {
                DB::beginTransaction();

                DB::table('t_attendance')
                    ->where('staff_id', '=', $staffId)
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->delete();

                DB::commit();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $this->deleteLocalAttendance((int) $staffId, (int) $workplaceId, (string) $workDate);

                return true;
            }

            return false;
        }
    }

    /**
     * 欠勤者一覧取得
     */
    public function GetAbsenceStaffList($workDate)
    {
        try {
            $staffList = [];

            if (! isset($workDate)) {
                return false;
            }

            $notAssignStaffList = DB::table('m_staff')
                ->whereNull('deleted_at')
                ->whereNotIn('id', function ($query) use ($workDate) {
                    $query->select('staff_id')
                        ->from('v_attendance')
                        ->where('work_date', $workDate)
                        ->groupBy('staff_id');
                })
                ->orderBy('sort_number')
                ->get();

            foreach ($notAssignStaffList as $staff) {
                $absence = DB::table('t_absence')
                    ->where('staff_id', '=', $staff->id)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->first();

                $absenceFlg = $absence ? intval($absence->absence_flg) : 0;

                $staffList[] = [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->staff_name,
                    'work_date' => $workDate,
                    'absence_flg' => $absenceFlg,
                ];
            }

            return $staffList;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 欠勤者一覧更新
     *
     * @param  array<string, mixed>  $staffList
     * @return 'success'|'attendance_conflict'|'failed'
     */
    public function UpdateAbsence($workDate, $staffList)
    {
        try {
            DB::beginTransaction();

            foreach ($staffList as $staffId => $absenceRaw) {
                $staffId = (int) $staffId;
                if ($staffId <= 0) {
                    continue;
                }

                $absenceFlgRaw = $absenceRaw;
                if (is_array($absenceFlgRaw)) {
                    $absenceFlgRaw = end($absenceFlgRaw);
                }
                $absenceFlg = (int) $absenceFlgRaw;

                $existsCheck = DB::table('v_attendance_all')
                    ->where('staff_id', '=', $staffId)
                    ->where('work_date', '=', $workDate)
                    ->exists();

                if ($existsCheck && $absenceFlg === 1) {
                    DB::rollback();

                    return 'attendance_conflict';
                }

                $absence = DB::table('t_absence')
                    ->where('staff_id', '=', $staffId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->first();

                if ($absenceFlg === 1) {
                    if ($absence) {
                        DB::table('t_absence')
                            ->where('staff_id', '=', $staffId)
                            ->where('work_date', '=', $workDate)
                            ->whereNull('deleted_at')
                            ->update([
                                'absence_flg' => 1,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('t_absence')->insert([
                            'staff_id' => $staffId,
                            'work_date' => $workDate,
                            'absence_flg' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($absenceFlg === 0) {
                    if ($absence) {
                        DB::table('t_absence')
                            ->where('staff_id', '=', $staffId)
                            ->where('work_date', '=', $workDate)
                            ->whereNull('deleted_at')
                            ->delete();
                    }
                }
            }

            DB::commit();

            return 'success';
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);

            return 'failed';
        }
    }

    /**
     * 勤怠入力フォーム初期値取得
     */
    public function GetDefaults()
    {
        try {
            $defaults = DB::table('m_attendance_defaults')
                ->where('is_enabled', '=', true)
                ->first();

            if ($defaults) {
                return $defaults;
            }

            // DBに初期値テーブルが無い/無効化の場合も最低限の値を返す
            return (object) [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_time' => '01:00:00',
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            // ローカルDB未接続でも一括入力が使えるようにフォールバック
            return (object) [
                // 添付イメージの初期値（出退勤/休憩）
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_time' => '01:00:00',
            ];
        }
    }

    /**
     * 初期値マスタの編集用に 1 行取得（有効行があればそれ、なければ先頭行）
     */
    public function getAttendanceDefaultsSettingsRow(): ?object
    {
        try {
            $row = DB::table('m_attendance_defaults')
                ->where('is_enabled', '=', true)
                ->orderBy('id')
                ->first();

            if ($row) {
                return $row;
            }

            return DB::table('m_attendance_defaults')->orderBy('id')->first();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return null;
        }
    }

    /**
     * 休憩の DB 値（HH:MM(:SS) や分）を分に変換（初期値画面・一括入力と揃える）
     */
    public function breakStoreValueToMinutes(?string $breakTime): int
    {
        if ($breakTime === null || $breakTime === '') {
            return 60;
        }

        $breakTimeStr = trim((string) $breakTime);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $breakTimeStr, $m) === 1) {
            return ((int) $m[1]) * 60 + ((int) $m[2]);
        }

        if (is_numeric($breakTimeStr)) {
            return (int) $breakTimeStr;
        }

        return 60;
    }

    /**
     * 分を休憩用 TIME 文字列（HH:MM:00）へ
     */
    public function minutesToBreakStoreValue(int $minutes): string
    {
        $mins = max(0, $minutes);
        $hours = intdiv($mins, 60);
        $minutesOnly = $mins % 60;

        return sprintf('%02d:%02d:00', $hours, $minutesOnly);
    }

    /**
     * m_attendance_defaults.break_time の列型に合わせて休憩値を整形（INT 列には分の整数）
     */
    private function prepareAttendanceDefaultsBreakForStorage(int $breakMinutes): int|string
    {
        $mins = max(0, $breakMinutes);
        try {
            $columnType = Schema::getColumnType('m_attendance_defaults', 'break_time');
        } catch (\Throwable) {
            $columnType = 'integer';
        }
        $columnTypeLower = strtolower((string) $columnType);
        $isNumericBreak = str_contains($columnTypeLower, 'int')
            || in_array($columnTypeLower, ['decimal', 'float', 'double', 'real', 'numeric'], true);

        if ($isNumericBreak) {
            return $mins;
        }

        return $this->minutesToBreakStoreValue($mins);
    }

    /**
     * m_attendance_defaults を更新（有効行が無ければ先頭行、無ければ新規作成）
     */
    public function saveAttendanceDefaults(string $startTimeInput, string $endTimeInput, int $breakMinutes, bool $isEnabled): bool
    {
        try {
            $startHms = $this->normalizeTimeInputToHms($startTimeInput);
            $endHms = $this->normalizeTimeInputToHms($endTimeInput);
            $breakForStorage = $this->prepareAttendanceDefaultsBreakForStorage($breakMinutes);

            DB::beginTransaction();

            $row = DB::table('m_attendance_defaults')
                ->where('is_enabled', '=', true)
                ->orderBy('id')
                ->first();

            if (! $row) {
                $row = DB::table('m_attendance_defaults')->orderBy('id')->first();
            }

            $payload = [
                'start_time' => $startHms,
                'end_time' => $endHms,
                'break_time' => $breakForStorage,
                'is_enabled' => $isEnabled,
                'updated_at' => now(),
            ];

            if ($row) {
                DB::table('m_attendance_defaults')
                    ->where('id', '=', $row->id)
                    ->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('m_attendance_defaults')->insert($payload);
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    private function normalizeTimeInputToHms(string $input): string
    {
        $s = trim($input);
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $s, $m) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m) === 1) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        throw new \InvalidArgumentException('Invalid time: '.$input);
    }

    /**
     * ローカルDB未接続でも「勤怠一括入力」「勤怠一覧」で使えるように、
     * local_assignments.json + local_staff.json (+ local_attendances.json) から返す
     *
     * @return array<int, object>
     */
    private function getLocalAttendanceAllStaff(int $workplaceId, string $workDate): array
    {
        $assignments = $this->readLocalJson('app/local_assignments.json');

        $staffs = $this->readLocalJson('app/local_staff.json');
        $attendanceRows = $this->readLocalJson(self::LOCAL_ATTENDANCE_FILE);

        $masterTypeStaff = (string) config('assignments.master_type.staff');

        $staffById = [];
        foreach ($staffs as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $staffById[$id] = $row;
            }
        }

        $attendanceByKey = [];
        foreach ($attendanceRows as $row) {
            if ((int) ($row['workplace_id'] ?? 0) !== $workplaceId) {
                continue;
            }
            if ((string) ($row['work_date'] ?? '') !== $workDate) {
                continue;
            }
            $sid = (int) ($row['staff_id'] ?? 0);
            if ($sid > 0) {
                $attendanceByKey[$sid] = $row;
            }
        }

        $targets = [];
        foreach ($assignments as $row) {
            if ((int) ($row['workplace_id'] ?? 0) !== $workplaceId) {
                continue;
            }
            if ((string) ($row['work_date'] ?? '') !== $workDate) {
                continue;
            }
            if ((string) ($row['master_type'] ?? '') !== $masterTypeStaff) {
                continue;
            }
            $sid = (int) ($row['master_id'] ?? 0);
            if ($sid > 0 && isset($staffById[$sid])) {
                $targets[$sid] = $staffById[$sid];
            }
        }

        // sort_number 順
        uasort($targets, function ($a, $b) {
            return ((int) ($a['sort_number'] ?? 0)) <=> ((int) ($b['sort_number'] ?? 0));
        });

        $result = [];
        foreach ($targets as $sid => $staffRow) {
            $att = $attendanceByKey[$sid] ?? [];
            $result[] = (object) [
                'staff_id' => $sid,
                'staff_name' => (string) ($staffRow['staff_name'] ?? ''),
                'absence_flg' => intval($att['absence_flg'] ?? 0),
                'start_time' => (string) ($att['start_time'] ?? ''),
                'end_time' => (string) ($att['end_time'] ?? ''),
                'break_time' => (string) ($att['break_time'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return array|object|false
     */
    private function getLocalAttendanceStaff(int $staffId, int $workplaceId, string $workDate)
    {
        $rows = $this->readLocalJson(self::LOCAL_ATTENDANCE_FILE);
        foreach ($rows as $row) {
            if ((int) ($row['staff_id'] ?? 0) === $staffId
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId
                && (string) ($row['work_date'] ?? '') === $workDate) {
                $staff = $this->readLocalJson('app/local_staff.json');
                $name = '';
                foreach ($staff as $s) {
                    if ((int) ($s['id'] ?? 0) === $staffId) {
                        $name = (string) ($s['staff_name'] ?? '');
                        break;
                    }
                }

                return (object) [
                    'staff_id' => $staffId,
                    'staff_name' => $name,
                    'start_time' => (string) ($row['start_time'] ?? ''),
                    'end_time' => (string) ($row['end_time'] ?? ''),
                    'break_time' => (string) ($row['break_time'] ?? ''),
                    'absence_flg' => intval($row['absence_flg'] ?? 0),
                ];
            }
        }

        return false;
    }

    /**
     * @return array<int, true>
     */
    private function getLocalAssignedStaffIds(int $workplaceId, string $workDate): array
    {
        $assignments = $this->readLocalJson('app/local_assignments.json');
        $targets = [];

        $masterTypeStaff = (string) config('assignments.master_type.staff');
        foreach ($assignments as $row) {
            if ((int) ($row['workplace_id'] ?? 0) !== $workplaceId) {
                continue;
            }
            if ((string) ($row['work_date'] ?? '') !== $workDate) {
                continue;
            }
            if ((string) ($row['master_type'] ?? '') !== $masterTypeStaff) {
                continue;
            }
            $sid = (int) ($row['master_id'] ?? 0);
            if ($sid > 0) {
                $targets[$sid] = true;
            }
        }

        return $targets;
    }

    private function upsertLocalAttendance(
        int $staffId,
        int $workplaceId,
        string $workDate,
        string $startTime,
        string $endTime,
        string $breakTime,
        int $absenceFlg
    ): void {
        $rows = $this->readLocalJson(self::LOCAL_ATTENDANCE_FILE);

        $found = false;
        foreach ($rows as &$row) {
            if ((int) ($row['staff_id'] ?? 0) === $staffId
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId
                && (string) ($row['work_date'] ?? '') === $workDate) {
                $row['start_time'] = $startTime;
                $row['end_time'] = $endTime;
                $row['break_time'] = $breakTime;
                $row['absence_flg'] = $absenceFlg;
                $row['updated_at'] = now()->toDateTimeString();
                $found = true;
                break;
            }
        }
        unset($row);

        if (! $found) {
            $nextId = 1;
            foreach ($rows as $r) {
                $nextId = max($nextId, (int) ($r['id'] ?? 0) + 1);
            }
            $rows[] = [
                'id' => $nextId,
                'staff_id' => $staffId,
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'break_time' => $breakTime,
                'absence_flg' => $absenceFlg,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        $this->writeLocalJson(self::LOCAL_ATTENDANCE_FILE, $rows);
    }

    private function deleteLocalAttendance(int $staffId, int $workplaceId, string $workDate): void
    {
        $rows = $this->readLocalJson(self::LOCAL_ATTENDANCE_FILE);
        $filtered = [];
        foreach ($rows as $row) {
            if ((int) ($row['staff_id'] ?? 0) === $staffId
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId
                && (string) ($row['work_date'] ?? '') === $workDate) {
                continue;
            }
            $filtered[] = $row;
        }

        $this->writeLocalJson(self::LOCAL_ATTENDANCE_FILE, $filtered);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readLocalJson(string $relativePath): array
    {
        $path = storage_path($relativePath);
        if (! is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        if (! is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeLocalJson(string $relativePath, array $rows): bool
    {
        $path = storage_path($relativePath);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return @file_put_contents($path, $payload) !== false;
    }

    /**
     * 月次勤怠表 PDF 用データ取得
     */
    public function GetPdfData($workDate)
    {
        try {
            $weekdays = getJapaneseWeekdaysShort();

            $defaults = $this->GetDefaults();
            $fallbackStart = $this->formatTimeShort((string) ($defaults->start_time ?? '')) ?: '08:00';
            $fallbackEnd = $this->formatTimeShort((string) ($defaults->end_time ?? '')) ?: '17:00';
            $fallbackBreak = $this->formatBreakForDisplay($defaults->break_time ?? '') ?: '01:00';

            $year = date('Y', strtotime($workDate));
            $month = date('m', strtotime($workDate));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $attendanceTableList = [];

            $staffList = DB::table('m_staff')
                ->whereNull('deleted_at')
                ->orderBy('sort_number')
                ->get();
            $workplaceMap = DB::table('m_workplace')
                ->whereNull('deleted_at')
                ->pluck('workplace_name', 'id');

            $onePageData = [];
            $cnt = 0;

            foreach ($staffList as $staff) {
                $attendanceDataList = [];

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $fullDate = "$year-$month-".sprintf('%02d', $day);
                    $attendanceDataList[$fullDate]['work_date'] = $fullDate;
                    $attendanceDataList[$fullDate]['week_day'] = $weekdays[date('N', strtotime($fullDate)) - 1];
                    $attendanceDataList[$fullDate]['staff_name'] = $staff->staff_name;

                    // 月次表はビュー1行目だと別現場の通常勤務が先に返ることがあるため、欠勤は実テーブルで先に判定する。
                    if ($this->isStaffAbsentOnDate((int) $staff->id, $fullDate)) {
                        $attendanceDataList[$fullDate]['workplace_name'] = '#absence';
                        $attendanceDataList[$fullDate]['workplace_id'] = 0;
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
                        $attendanceDataList[$fullDate]['worked_time'] = '';
                        $attendanceDataList[$fullDate]['absence'] = '休み';

                        continue;
                    }

                    // まずは既存ビュー（安定）を主参照し、時刻欠落時のみ t_attendance から補完する。
                    $viewQuery = DB::table('v_attendance_all')
                        ->where('staff_id', '=', $staff->id);
                    $this->whereWorkDateEquals($viewQuery, $fullDate);
                    $attendanceData = $viewQuery->first();

                    $preferredWp = (int) ($attendanceData->workplace_id ?? 0);
                    $attendanceRaw = $this->resolveAttendanceRawForMonthly((int) $staff->id, $fullDate, $preferredWp);

                    if (! $attendanceData && $attendanceRaw) {
                        $attendanceData = (object) [
                            'workplace_id' => (int) ($attendanceRaw->workplace_id ?? 0),
                            'workplace_name' => (string) ($workplaceMap[$attendanceRaw->workplace_id] ?? ''),
                            'start_time' => $attendanceRaw->start_time ?? '',
                            'end_time' => $attendanceRaw->end_time ?? '',
                            'break_time' => $attendanceRaw->break_time ?? '',
                            'absence_flg' => (int) ($attendanceRaw->absence_flg ?? 0),
                        ];
                    }

                    $isAbsentDay = ($attendanceRaw && (int) ($attendanceRaw->absence_flg ?? 0) !== 0)
                        || ($attendanceData && (int) ($attendanceData->absence_flg ?? 0) !== 0);

                    if ($attendanceData && ! $isAbsentDay) {
                        $startTime = $this->formatTimeShort($attendanceData->start_time);
                        $endTime = $this->formatTimeShort($attendanceData->end_time);
                        $breakTime = $this->formatBreakForDisplay($attendanceData->break_time);

                        // v_attendance_all は配置現場ベースのため、勤怠トップで保存した t_attendance と現場・時刻がずれる。
                        // 実レコードがある日は t_attendance を優先して月次・PDF と入力内容を一致させる。
                        if ($attendanceRaw) {
                            $wpTa = (string) ($workplaceMap[(int) ($attendanceRaw->workplace_id ?? 0)] ?? '');
                            $attendanceDataList[$fullDate]['workplace_name'] = $wpTa !== ''
                                ? $wpTa
                                : (string) ($attendanceData->workplace_name ?? '');
                            $attendanceDataList[$fullDate]['workplace_id'] = (int) ($attendanceRaw->workplace_id ?? 0);
                            $stR = $this->formatTimeShort($attendanceRaw->start_time ?? '');
                            $enR = $this->formatTimeShort($attendanceRaw->end_time ?? '');
                            $brR = $this->formatBreakForDisplay($attendanceRaw->break_time ?? '');
                            if ($stR !== '') {
                                $startTime = $stR;
                            }
                            if ($enR !== '') {
                                $endTime = $enR;
                            }
                            if ($brR !== '') {
                                $breakTime = $brR;
                            }
                        } else {
                            $attendanceDataList[$fullDate]['workplace_name'] = (string) ($attendanceData->workplace_name ?? '');
                            $attendanceDataList[$fullDate]['workplace_id'] = (int) ($attendanceData->workplace_id ?? 0);
                        }

                        // 現場はあるが DB に保存済みの時刻が無い日だけ、月次表では参考として初期時間を表示
                        $wpNameForFallback = (string) ($attendanceDataList[$fullDate]['workplace_name'] ?? '');
                        $hasStoredTimes = $attendanceRaw && (
                            $this->formatTimeShort($attendanceRaw->start_time ?? '') !== ''
                            || $this->formatTimeShort($attendanceRaw->end_time ?? '') !== ''
                            || $this->formatTimeShort($attendanceRaw->break_time ?? '') !== ''
                        );
                        if ($wpNameForFallback !== '' && ! $hasStoredTimes) {
                            // 未入力日の参考表示は、過去・未来に関わらず初期時間マスタ（m_attendance_defaults）を使う。
                            // 日付が過去になると 08:00 等の固定値へ切り替わり、先週まで 7:30 だった表示が変わってしまうため固定値は使わない。
                            if ($startTime === '') {
                                $startTime = $fallbackStart;
                            }
                            if ($endTime === '') {
                                $endTime = $fallbackEnd;
                            }
                            if ($breakTime === '') {
                                $breakTime = $fallbackBreak;
                            }
                        }

                        $attendanceDataList[$fullDate]['start_time'] = $startTime;
                        $attendanceDataList[$fullDate]['end_time'] = $endTime;
                        $attendanceDataList[$fullDate]['break_time'] = $breakTime;
                        $attendanceDataList[$fullDate]['worked_time'] = $this->workedTimeDisplay($startTime, $endTime, $breakTime);
                        $attendanceDataList[$fullDate]['absence'] = '';
                    } else {
                        $attendanceDataList[$fullDate]['workplace_name'] = '';
                        $attendanceDataList[$fullDate]['workplace_id'] = 0;
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
                        $attendanceDataList[$fullDate]['worked_time'] = '';
                        $attendanceDataList[$fullDate]['absence'] = $attendanceData && $attendanceData->absence_flg ? '休み' : '';
                    }

                    $absenceExistsQuery = DB::table('t_absence')
                        ->where('staff_id', '=', $staff->id)
                        ->whereNull('deleted_at');
                    $this->whereWorkDateEquals($absenceExistsQuery, $fullDate);
                    $absenceExists = $absenceExistsQuery->exists();

                    if ($isAbsentDay) {
                        $attendanceDataList[$fullDate]['workplace_name'] = '#absence';
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
                        $attendanceDataList[$fullDate]['worked_time'] = '';
                    }
                    if ($absenceExists) {
                        $attendanceDataList[$fullDate]['workplace_name'] = '#absence';
                    }
                }

                $attendanceDataList['staff_name'] = $staff->staff_name;
                $onePageData[] = $attendanceDataList;

                if ($cnt >= 4) {
                    $attendanceTableList[] = $onePageData;
                    $onePageData = [];
                    $cnt = 0;
                } else {
                    $cnt++;
                }
            }

            if (! empty($onePageData)) {
                $attendanceTableList[] = $onePageData;
            }

            $dateList = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                // セル参照キーは $fullDate（Y-m-d ゼロ埋め）と一致させる（2026-05-1 だと blade 側でヒットしない）
                $dateList[] = "$year-$month-".sprintf('%02d', $day);
            }

            $payload = [
                'attendance_table_list' => $attendanceTableList ?: [],
                'date_list' => $dateList,
                'display_date' => formatJapaneseDate($workDate),
                'today' => formatJapaneseDate(date('Y-m-d')),
            ];

            // ローカルでDBが空（例外にならず空配列で返る）でも、月次表は local_* から組み立てる
            if (app()->environment('local') && empty($payload['attendance_table_list'])) {
                return $this->getLocalPdfData($workDate);
            }

            return $payload;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getLocalPdfData($workDate);
            }

            return false;
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getLocalPdfData(string $workDate)
    {
        // ローカル用：DBが無くても月次表が出せるように
        $staffs = $this->readLocalJson('app/local_staff.json');
        $assignments = $this->readLocalJson('app/local_assignments.json');
        $workplaces = $this->readLocalJson('app/local_workplaces.json');
        $attendances = $this->readLocalJson(self::LOCAL_ATTENDANCE_FILE);

        if (empty($staffs)) {
            return false;
        }

        $workDate = (string) $workDate;
        $year = date('Y', strtotime($workDate));
        $month = date('m', strtotime($workDate));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);

        $workplaceNameById = [];
        foreach ($workplaces as $w) {
            $id = (int) ($w['id'] ?? 0);
            if ($id > 0) {
                $workplaceNameById[$id] = (string) ($w['workplace_name'] ?? '');
            }
        }

        $assignmentWorkplaceByStaffAndDate = [];
        foreach ($assignments as $row) {
            if ((string) ($row['work_date'] ?? '') === '' || (string) ($row['master_type'] ?? '') === '') {
                continue;
            }
            if ((int) ($row['master_type'] ?? 0) !== (int) config('assignments.master_type.staff')) {
                // master_type は json の都合で "1"/1 が混在し得るので、両方int比較
            }
            $masterType = (string) ($row['master_type'] ?? '');
            if ($masterType !== (string) config('assignments.master_type.staff')) {
                continue;
            }

            $wid = (int) ($row['workplace_id'] ?? 0);
            $sid = (int) ($row['master_id'] ?? 0);
            $d = (string) ($row['work_date'] ?? '');

            if ($wid > 0 && $sid > 0 && $d !== '') {
                $assignmentWorkplaceByStaffAndDate[$sid][$d] = $wid;
            }
        }

        $attendanceByStaffWorkplaceDate = [];
        foreach ($attendances as $row) {
            $sid = (int) ($row['staff_id'] ?? 0);
            $wid = (int) ($row['workplace_id'] ?? 0);
            $d = (string) ($row['work_date'] ?? '');
            if ($sid > 0 && $wid > 0 && $d !== '') {
                $attendanceByStaffWorkplaceDate[$sid][$wid][$d] = $row;
            }
        }

        $weekdays = getJapaneseWeekdaysShort();

        $attendanceTableList = [];
        $onePageData = [];
        $cnt = 0;

        foreach ($staffs as $staff) {
            $staffId = (int) ($staff['id'] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $staffName = (string) ($staff['staff_name'] ?? '');

            $attendanceDataList = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $fullDate = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
                $attendanceDataList[$fullDate]['work_date'] = $fullDate;
                $attendanceDataList[$fullDate]['week_day'] = $weekdays[date('N', strtotime($fullDate)) - 1] ?? '';
                $attendanceDataList[$fullDate]['staff_name'] = $staffName;

                $workplaceId = $assignmentWorkplaceByStaffAndDate[$staffId][$fullDate] ?? 0;
                $attRow = null;
                if ($workplaceId > 0) {
                    $attRow = $attendanceByStaffWorkplaceDate[$staffId][$workplaceId][$fullDate] ?? null;
                }

                if ($attRow && intval($attRow['absence_flg'] ?? 0) === 1) {
                    $attendanceDataList[$fullDate]['workplace_name'] = '#absence';
                    $attendanceDataList[$fullDate]['start_time'] = '';
                    $attendanceDataList[$fullDate]['end_time'] = '';
                    $attendanceDataList[$fullDate]['break_time'] = '';
                    $attendanceDataList[$fullDate]['worked_time'] = '';
                    $attendanceDataList[$fullDate]['absence'] = '休';
                } else {
                    // DB版と同じ形式に寄せる：勤怠行が無ければ空セル
                    if ($attRow) {
                        $startDisp = $this->formatTimeShort($attRow['start_time'] ?? '');
                        $endDisp = $this->formatTimeShort($attRow['end_time'] ?? '');
                        $breakDisp = $this->formatBreakForDisplay($attRow['break_time'] ?? '');
                        $attendanceDataList[$fullDate]['workplace_name'] = $workplaceNameById[$workplaceId] ?? '';
                        $attendanceDataList[$fullDate]['start_time'] = $startDisp;
                        $attendanceDataList[$fullDate]['end_time'] = $endDisp;
                        $attendanceDataList[$fullDate]['break_time'] = $breakDisp;
                        $attendanceDataList[$fullDate]['worked_time'] = $this->workedTimeDisplay($startDisp, $endDisp, $breakDisp);
                        $attendanceDataList[$fullDate]['absence'] = '';
                    } else {
                        $attendanceDataList[$fullDate]['workplace_name'] = '';
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
                        $attendanceDataList[$fullDate]['worked_time'] = '';
                        $attendanceDataList[$fullDate]['absence'] = '';
                    }
                }
            }

            $attendanceDataList['staff_name'] = $staffName;
            $onePageData[] = $attendanceDataList;

            if ($cnt >= 4) {
                $attendanceTableList[] = $onePageData;
                $onePageData = [];
                $cnt = 0;
            } else {
                $cnt++;
            }
        }

        if (! empty($onePageData)) {
            $attendanceTableList[] = $onePageData;
        }

        $dateList = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateList[] = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        }

        return [
            'attendance_table_list' => $attendanceTableList ?: [],
            'date_list' => $dateList,
            'display_date' => formatJapaneseDate($workDate),
            'today' => formatJapaneseDate(date('Y-m-d')),
        ];
    }

    private function formatTimeShort($time): string
    {
        // 月次表も一覧と同じロジックで時刻を正規化する（H:MM / HH:MM / HH:MM:SS / datetime すべて対応）
        return $this->formatTimeForDisplay($time);
    }

    /**
     * MySQL TIME 向けに H:i または H:i:s を統一する。
     */
    private function normalizeClockForSqlTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) === 1) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})(?:\.\d+)?$/', $time, $m) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $time;
    }

    private function prepareBreakTimeForStorage($breakTime)
    {
        $raw = trim((string) ($breakTime ?? ''));
        try {
            $columnType = Schema::getColumnType('t_attendance', 'break_time');
        } catch (\Throwable) {
            $columnType = 'integer';
        }
        // MySQL の INT は getColumnType が "int" になることがあり、従来リストに無いと TIME 文字列のまま送って
        // break_time が整数（分）の列で Data truncated → 例外 → ロールバックし end_time も保存されない。
        $columnTypeLower = strtolower((string) $columnType);
        $isNumericBreak = str_contains($columnTypeLower, 'int')
            || in_array($columnTypeLower, ['decimal', 'float', 'double', 'real', 'numeric'], true);

        if (! $isNumericBreak) {
            return $raw;
        }

        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/', $raw, $m) === 1) {
            return ((int) $m[1] * 60) + (int) $m[2];
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            return (int) $raw;
        }

        return 0;
    }

    /**
     * 個人別集計向け: 同一社員・同一日の複数行から1行を選ぶ。
     * 月次表（resolveAttendanceRawForMonthly）と同じ優先順位:
     * 欠勤行 → 出勤時刻あり行 → それ以外（いずれも新しい行を優先）。
     *
     * @param  Collection<int, object>  $rows
     */
    private function pickBestDailyRowForSummary($rows): ?object
    {
        $sorted = collect($rows)->sortByDesc(fn ($r) => (int) ($r->id ?? 0))->values();
        if ($sorted->isEmpty()) {
            return null;
        }

        $absentRow = $sorted->first(fn ($r) => (int) ($r->absence_flg ?? 0) !== 0);
        if ($absentRow) {
            return $absentRow;
        }

        $withTimes = $sorted->first(fn ($r) => $this->formatTimeShort((string) ($r->start_time ?? '')) !== '');

        return $withTimes ?? $sorted->first();
    }

    /**
     * 個人別の月次集計（勤怠管理画面向け）
     *
     * @return array<string, mixed>|false
     */
    public function GetPersonalMonthlySummary($workDate, $staffId = null, $staffType = null)
    {
        try {
            $baseDate = $workDate ?: date('Y-m-d');
            $year = (int) date('Y', strtotime($baseDate));
            $month = (int) date('m', strtotime($baseDate));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $dateList = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateList[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            $staffQuery = DB::table('m_staff')
                ->whereNull('deleted_at')
                ->orderBy('staff_type')
                ->orderBy('sort_number')
                ->orderBy('id');
            if (! empty($staffId)) {
                $staffQuery->where('id', '=', $staffId);
            }
            if ($staffType !== null && $staffType !== '') {
                $staffQuery->where('staff_type', '=', (int) $staffType);
            }
            $staffList = $staffQuery->get();

            $summaryList = [];
            foreach ($staffList as $staff) {
                // 同一社員・同一日に複数行（旧システムの現場別行など）があるため、
                // keyBy で1行に潰さず、月次表と同じ基準で日ごとに最適な1行を選ぶ。
                $dailyRows = DB::table('t_attendance')
                    ->where('staff_id', '=', $staff->id)
                    ->whereYear('work_date', '=', $year)
                    ->whereMonth('work_date', '=', $month)
                    ->whereNull('deleted_at')
                    ->orderBy('work_date')
                    ->get()
                    ->groupBy(fn ($r) => Carbon::parse($r->work_date)->format('Y-m-d'))
                    ->map(fn ($rows) => $this->pickBestDailyRowForSummary($rows))
                    ->filter();

                $personal = [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->staff_name,
                    'weekday_work_days' => 0,
                    'holiday_work_days' => 0,
                    'normal_minutes' => 0,
                    'overtime_minutes' => 0,
                    'holiday_minutes' => 0,
                    'midnight_minutes' => 0,
                    'daily' => [],
                ];

                foreach ($dateList as $date) {
                    $dow = (int) date('N', strtotime($date));
                    $isSaturday = ($dow === 6);
                    $isSunday = ($dow === 7);
                    $row = $dailyRows->get($date);

                    $normalMinutes = 0;
                    $overtimeMinutes = 0;
                    $holidayMinutes = 0;
                    $midnightMinutes = 0;
                    $absence = false;
                    $startDisplay = '';
                    $endDisplay = '';
                    $breakMinutes = 0;
                    $workedMinutes = 0;

                    if ($row) {
                        $absence = intval($row->absence_flg ?? 0) === 1;
                        if (! $absence) {
                            $startDisplay = $this->formatTimeShort((string) ($row->start_time ?? ''));
                            $endDisplay = $this->formatTimeShort((string) ($row->end_time ?? ''));
                            $breakDisplay = $this->formatBreakForDisplay($row->break_time ?? '');
                            $breakMinutes = $this->timeToMinutes($breakDisplay, true) ?? 0;
                            $workedMinutes = $this->calcWorkedMinutes(
                                $startDisplay,
                                $endDisplay,
                                $breakDisplay
                            );

                            if ($workedMinutes > 0) {
                                if ($isSunday) {
                                    // 日曜は出勤時のみ「休日出勤日」カウントし、全時間を休日時間に集計
                                    $holidayMinutes = $workedMinutes;
                                    $personal['holiday_work_days']++;
                                } elseif ($isSaturday) {
                                    // 土曜は全時間を時間外として集計
                                    $overtimeMinutes = $workedMinutes;
                                } else {
                                    // 平日は8時間まで通常、8時間超を時間外として集計
                                    $normalMinutes = min(480, $workedMinutes);
                                    $overtimeMinutes = max(0, $workedMinutes - 480);
                                    $personal['weekday_work_days']++;
                                }

                                $midnightMinutes = $this->calcMidnightMinutes(
                                    $startDisplay,
                                    $endDisplay
                                );
                            }
                        }
                    }

                    $personal['normal_minutes'] += $normalMinutes;
                    $personal['overtime_minutes'] += $overtimeMinutes;
                    $personal['holiday_minutes'] += $holidayMinutes;
                    $personal['midnight_minutes'] += $midnightMinutes;

                    $personal['daily'][$date] = [
                        'start' => $startDisplay,
                        'end' => $endDisplay,
                        'break' => $this->minutesToHourDecimal($breakMinutes),
                        'worked' => $this->minutesToHourDecimal($workedMinutes),
                        'normal' => $this->minutesToHourDecimal($normalMinutes),
                        'overtime' => $this->minutesToHourDecimal($overtimeMinutes),
                        'holiday' => $this->minutesToHourDecimal($holidayMinutes),
                        'midnight' => $this->minutesToHourDecimal($midnightMinutes),
                        'absence' => $absence,
                    ];
                }

                $summaryList[] = $personal;
            }

            return [
                'date_list' => $dateList,
                'staff_list' => $staffList,
                'summary_list' => $summaryList,
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 月次表「実働」用: 出勤・退勤・休憩から実働時間を HH:MM で返す（算出不能・0 は空文字）。
     */
    private function workedTimeDisplay(string $startTime, string $endTime, string $breakTime): string
    {
        if (trim($startTime) === '' || trim($endTime) === '') {
            return '';
        }
        $minutes = $this->calcWorkedMinutes($startTime, $endTime, $breakTime);
        if ($minutes <= 0) {
            return '';
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function calcWorkedMinutes(string $startTime, string $endTime, string $breakTime): int
    {
        $start = $this->timeToMinutes($startTime);
        $end = $this->timeToMinutes($endTime);
        if ($start === null || $end === null) {
            return 0;
        }

        if ($end < $start) {
            $end += 24 * 60;
        }

        $breakMinutes = $this->timeToMinutes($breakTime, true) ?? 0;

        return max(0, ($end - $start) - $breakMinutes);
    }

    private function calcMidnightMinutes(string $startTime, string $endTime): int
    {
        $start = $this->timeToMinutes($startTime);
        $end = $this->timeToMinutes($endTime);
        if ($start === null || $end === null) {
            return 0;
        }

        if ($end < $start) {
            $end += 24 * 60;
        }

        $midnight1 = $this->overlapMinutes($start, $end, 0, 300);
        $midnight2 = $this->overlapMinutes($start, $end, 1320, 1440);
        $midnight3 = $this->overlapMinutes($start, $end, 1440, 1740);

        return $midnight1 + $midnight2 + $midnight3;
    }

    private function overlapMinutes(int $start, int $end, int $rangeStart, int $rangeEnd): int
    {
        $s = max($start, $rangeStart);
        $e = min($end, $rangeEnd);

        return max(0, $e - $s);
    }

    private function timeToMinutes(string $time, bool $allowDuration = false): ?int
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        // 旧データ互換:
        // - "1"   => 1時間（60分）
        // - "1.5" => 1.5時間（90分）
        // - "60"  => 60分
        if ($allowDuration && is_numeric($time)) {
            $num = (float) $time;
            if ($num < 0) {
                return null;
            }
            if (str_contains($time, '.')) {
                return (int) round($num * 60);
            }
            $intNum = (int) $num;
            if ($intNum <= 23) {
                return $intNum * 60;
            }

            return $intNum;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m) !== 1) {
            return null;
        }

        $h = (int) $m[1];
        $min = (int) $m[2];

        if (! $allowDuration && ($h > 23 || $min > 59)) {
            return null;
        }
        if ($allowDuration && $min > 59) {
            return null;
        }

        return ($h * 60) + $min;
    }

    private function minutesToHourDecimal(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0.00';
        }

        return number_format($minutes / 60, 2, '.', '');
    }
}
