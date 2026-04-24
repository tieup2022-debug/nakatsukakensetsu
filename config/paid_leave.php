<?php

/**
 * 有給申請: 承認者は社員ID（m_staff.id）。いずれか1名の承認で完了。
 * メールは m_user.email があれば優先し、なければ staff_emails を参照。
 */
return [
    'approver_staff_ids' => array_map('intval', array_filter(array_map('trim', explode(',', env('PAID_LEAVE_APPROVER_STAFF_IDS', '6,7,13,9,25,40'))))),

    /** @var array<int, string> 社員ID => メール（DBにメール列がない場合の送信用） */
    'staff_emails' => [
        6 => 'syun@e-nakatsuka.com',
        7 => 'tetsu@e-nakatsuka.com',
        13 => 'narisawa@e-nakatsuka.com',
        9 => 'setsuro@e-nakatsuka.com',
        25 => 'kikuchi@e-nakatsuka.com',
        40 => 'tieuo2022@gmail.com',
    ],
];
