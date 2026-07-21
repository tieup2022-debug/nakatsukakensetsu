<div class="modal fade" id="paidLeaveModal" tabindex="-1" aria-labelledby="paidLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paidLeaveModalLabel">有給休暇の申請</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <form method="POST" action="{{ route('paid-leave.store') }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">休む日、取得日数とその日の開始・終了時刻を入力してください。承認は指定された承認者のうちいずれか1名が行います。</p>
                    <div class="mb-3">
                        <label class="form-label small text-muted">休む日</label>
                        <input type="date" name="leave_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">取得日数</label>
                        <select name="leave_days" class="form-select" required>
                            <option value="1">1日</option>
                            <option value="0.5">0.5日</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">開始時刻</label>
                        <input type="text" name="start_time" class="form-control js-timepicker" required autocomplete="off" inputmode="numeric" placeholder="例）08:00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">終了時刻</label>
                        <input type="text" name="end_time" class="form-control js-timepicker" required autocomplete="off" inputmode="numeric" placeholder="例）17:00">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">事由（任意）</label>
                        <textarea name="reason" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">送信する</button>
                </div>
            </form>
        </div>
    </div>
</div>
