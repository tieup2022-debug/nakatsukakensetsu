<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v_attendance_all は従来、t_assignment 側の現場と t_attendance.workplace_id が一致しないと
 * LEFT JOIN で勤怠時刻が NULL になり、画面上は初期値(08:00等)に見える。
 * サブクエリで「同一 staff・日付の t_attendance のうち、配置現場と一致する行を優先し、無ければ最新 id」
 * を 1 行に絞って JOIN する。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_attendance_all');
        DB::statement("
            CREATE VIEW v_attendance_all AS
            SELECT
                vas.workplace_id AS workplace_id
                , vas.workplace_name AS workplace_name
                , vas.work_date AS work_date
                , vas.staff_id AS staff_id
                , vas.staff_name AS staff_name
                , vas.staff_type AS staff_type
                , vas.sort_number AS sort_number
                , tat.start_time AS start_time
                , tat.end_time AS end_time
                , tat.break_time AS break_time
                , tat.absence_flg AS absence_flg
            FROM
                v_assignment_staff vas
                LEFT JOIN t_attendance tat
                    ON tat.id = (
                        SELECT t2.id
                        FROM t_attendance t2
                        WHERE t2.staff_id = vas.staff_id
                          AND t2.work_date = vas.work_date
                          AND t2.deleted_at IS NULL
                        ORDER BY (t2.workplace_id = vas.workplace_id) DESC, t2.id DESC
                        LIMIT 1
                    )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_attendance_all');
        DB::statement("
            CREATE VIEW v_attendance_all AS
            SELECT
                vas.workplace_id AS workplace_id
                , vas.workplace_name AS workplace_name
                , vas.work_date AS work_date
                , vas.staff_id AS staff_id
                , vas.staff_name AS staff_name
                , vas.staff_type AS staff_type
                , vas.sort_number AS sort_number
                , tat.start_time AS start_time
                , tat.end_time AS end_time
                , tat.break_time AS break_time
                , tat.absence_flg AS absence_flg
            FROM
                v_assignment_staff vas
                LEFT JOIN t_attendance tat
                    ON 1 = 1
                    AND vas.workplace_id = tat.workplace_id
                    AND vas.work_date = tat.work_date
                    AND vas.staff_id = tat.staff_id
                    AND tat.deleted_at IS NULL
        ");
    }
};
