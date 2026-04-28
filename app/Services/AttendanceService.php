<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceService
{
    private const LOCAL_ATTENDANCE_FILE = 'app/local_attendances.json';

    /**
     * 勤怠一覧 取得
     */
    public function GetAttendance($workplaceId = null, $workDate = null)
    {
        try {
            if (is_null($workDate)) {
                $workDate = date('Y-m-d');
            }

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
            if (isset($workplaceId) && isset($workDate)) {
                return DB::table('v_attendance_all')
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $workDate)
                    ->orderBy('staff_name')
                    ->get();
            }

            return false;
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
            if (!str_contains($s, '.') && $minutes <= 23) {
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
     * 勤怠一覧行に表示用の時刻を付与（未登録・NULL 時は m_attendance_defaults 相当の既定値）
     */
    public function withListDisplayTimes(iterable $rows, object $defaults): Collection
    {
        $rows = collect($rows);

        $fallbackStart = $this->formatTimeForDisplay($defaults->start_time ?? null) ?: '08:00';
        $fallbackEnd = $this->formatTimeForDisplay($defaults->end_time ?? null) ?: '17:00';
        $fallbackBreak = $this->formatTimeForDisplay($defaults->break_time ?? null) ?: '01:00';

        return $rows->map(function ($row) use ($fallbackStart, $fallbackEnd, $fallbackBreak) {
            $absent = isset($row->absence_flg) && (int) $row->absence_flg === 1;
            if ($absent) {
                $row->display_start = '';
                $row->display_end = '';
                $row->display_break = '';

                return $row;
            }

            $s = $this->formatTimeForDisplay($row->start_time ?? null);
            $e = $this->formatTimeForDisplay($row->end_time ?? null);
            $b = $this->formatTimeForDisplay($row->break_time ?? null);

            $row->display_start = $s !== '' ? $s : $fallbackStart;
            $row->display_end = $e !== '' ? $e : $fallbackEnd;
            $row->display_break = $b !== '' ? $b : $fallbackBreak;

            return $row;
        });
    }

    /**
     * 勤怠 更新
     */
    public function AttendanceUpdate($staffId, $workplaceId, $workDate, $startTime, $endTime, $breakTime, $absenceFlg = false)
    {
        try {
            if (
                isset($staffId) &&
                isset($workplaceId) &&
                isset($workDate) &&
                isset($startTime) &&
                isset($endTime) &&
                isset($breakTime)
            ) {
                $attendanceData = DB::table('t_attendance')
                    ->where('staff_id', '=', $staffId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->first();

                $breakTimeForStorage = $this->prepareBreakTimeForStorage($breakTime);

                DB::beginTransaction();

                if ($attendanceData) {
                    DB::table('t_attendance')
                        ->where('id', '=', $attendanceData->id)
                        ->whereNull('deleted_at')
                        ->update([
                            'staff_id' => $staffId,
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
                            'staff_id' => $staffId,
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

                DB::commit();

                return true;
            }

            return false;
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
                return DB::table('t_attendance')
                    ->where('staff_id', '=', $staffId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->first();
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
                if (!empty($assignedStaffIds)) {
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

            if (!isset($workDate)) {
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
            return (object)[
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_time' => '01:00:00',
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            // ローカルDB未接続でも一括入力が使えるようにフォールバック
            return (object)[
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
     * m_attendance_defaults を更新（有効行が無ければ先頭行、無ければ新規作成）
     */
    public function saveAttendanceDefaults(string $startTimeInput, string $endTimeInput, int $breakMinutes, bool $isEnabled): bool
    {
        try {
            $startHms = $this->normalizeTimeInputToHms($startTimeInput);
            $endHms = $this->normalizeTimeInputToHms($endTimeInput);
            $breakHms = $this->minutesToBreakStoreValue($breakMinutes);

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
                'break_time' => $breakHms,
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
        if (!is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeLocalJson(string $relativePath, array $rows): bool
    {
        $path = storage_path($relativePath);
        $dir = dirname($path);
        if (!is_dir($dir)) {
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
                    $fullDate = "$year-$month-" . sprintf('%02d', $day);
                    $attendanceDataList[$fullDate]['work_date'] = $fullDate;
                    $attendanceDataList[$fullDate]['week_day'] = $weekdays[date('N', strtotime($fullDate)) - 1];
                    $attendanceDataList[$fullDate]['staff_name'] = $staff->staff_name;

                    // まずは既存ビュー（安定）を主参照し、時刻欠落時のみ t_attendance から補完する。
                    $attendanceData = DB::table('v_attendance_all')
                        ->where('staff_id', '=', $staff->id)
                        ->where('work_date', '=', $fullDate)
                        ->first();

                    $attendanceRaw = DB::table('t_attendance')
                        ->where('staff_id', '=', $staff->id)
                        ->where('work_date', '=', $fullDate)
                        ->whereNull('deleted_at')
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id')
                        ->first(['workplace_id', 'start_time', 'end_time', 'break_time', 'absence_flg']);

                    if (! $attendanceData && $attendanceRaw) {
                        $attendanceData = (object) [
                            'workplace_name' => (string) ($workplaceMap[$attendanceRaw->workplace_id] ?? ''),
                            'start_time' => $attendanceRaw->start_time ?? '',
                            'end_time' => $attendanceRaw->end_time ?? '',
                            'break_time' => $attendanceRaw->break_time ?? '',
                            'absence_flg' => (int) ($attendanceRaw->absence_flg ?? 0),
                        ];
                    }

                    if ($attendanceData && !$attendanceData->absence_flg) {
                        $attendanceDataList[$fullDate]['workplace_name'] = $attendanceData->workplace_name;
                        $startTime = $this->formatTimeShort($attendanceData->start_time);
                        $endTime = $this->formatTimeShort($attendanceData->end_time);
                        $breakTime = $this->formatTimeShort($attendanceData->break_time);
                        if ($startTime === '' && $attendanceRaw) {
                            $startTime = $this->formatTimeShort($attendanceRaw->start_time ?? '');
                        }
                        if ($endTime === '' && $attendanceRaw) {
                            $endTime = $this->formatTimeShort($attendanceRaw->end_time ?? '');
                        }
                        if ($breakTime === '' && $attendanceRaw) {
                            $breakTime = $this->formatTimeShort($attendanceRaw->break_time ?? '');
                        }
                        $attendanceDataList[$fullDate]['start_time'] = $startTime;
                        $attendanceDataList[$fullDate]['end_time'] = $endTime;
                        $attendanceDataList[$fullDate]['break_time'] = $breakTime;
                        $attendanceDataList[$fullDate]['absence'] = '';
                    } else {
                        $attendanceDataList[$fullDate]['workplace_name'] = '';
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
                        $attendanceDataList[$fullDate]['absence'] = $attendanceData && $attendanceData->absence_flg ? '休み' : '';
                    }

                    $absenceExists = DB::table('t_absence')
                        ->where('staff_id', '=', $staff->id)
                        ->where('work_date', '=', $fullDate)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($attendanceData && $attendanceData->absence_flg) {
                        $attendanceDataList[$fullDate]['workplace_name'] = '#absence';
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

            if (!empty($onePageData)) {
                $attendanceTableList[] = $onePageData;
            }

            $dateList = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateList[] = "$year-$month-$day";
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
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

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
                $fullDate = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
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
                    $attendanceDataList[$fullDate]['absence'] = '休';
                } else {
                    // DB版と同じ形式に寄せる：勤怠行が無ければ空セル
                    if ($attRow) {
                        $attendanceDataList[$fullDate]['workplace_name'] = $workplaceNameById[$workplaceId] ?? '';
                        $attendanceDataList[$fullDate]['start_time'] = $this->formatTimeShort($attRow['start_time'] ?? '');
                        $attendanceDataList[$fullDate]['end_time'] = $this->formatTimeShort($attRow['end_time'] ?? '');
                        $attendanceDataList[$fullDate]['break_time'] = (string) ($attRow['break_time'] ?? '');
                        $attendanceDataList[$fullDate]['absence'] = '';
                    } else {
                        $attendanceDataList[$fullDate]['workplace_name'] = '';
                        $attendanceDataList[$fullDate]['start_time'] = '';
                        $attendanceDataList[$fullDate]['end_time'] = '';
                        $attendanceDataList[$fullDate]['break_time'] = '';
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

        if (!empty($onePageData)) {
            $attendanceTableList[] = $onePageData;
        }

        $dateList = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateList[] = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
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

    private function prepareBreakTimeForStorage($breakTime)
    {
        $raw = trim((string) ($breakTime ?? ''));
        $columnType = Schema::getColumnType('t_attendance', 'break_time');
        $isNumericBreak = in_array($columnType, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double'], true);

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
            if (!empty($staffId)) {
                $staffQuery->where('id', '=', $staffId);
            }
            if ($staffType !== null && $staffType !== '') {
                $staffQuery->where('staff_type', '=', (int) $staffType);
            }
            $staffList = $staffQuery->get();

            $summaryList = [];
            foreach ($staffList as $staff) {
                $dailyRows = DB::table('t_attendance')
                    ->where('staff_id', '=', $staff->id)
                    ->whereYear('work_date', '=', $year)
                    ->whereMonth('work_date', '=', $month)
                    ->whereNull('deleted_at')
                    ->orderBy('work_date')
                    ->get()
                    ->keyBy('work_date');

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
                        if (!$absence) {
                            $startDisplay = $this->formatTimeShort((string) ($row->start_time ?? ''));
                            $endDisplay = $this->formatTimeShort((string) ($row->end_time ?? ''));
                            $breakDisplay = $this->formatTimeShort((string) ($row->break_time ?? ''));
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

        if (!$allowDuration && ($h > 23 || $min > 59)) {
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

