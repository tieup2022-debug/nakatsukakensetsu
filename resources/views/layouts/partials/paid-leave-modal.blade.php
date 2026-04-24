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
                    <p class="text-muted small">複数日にまたがる場合は、<strong>開始日時</strong>と<strong>終了日時</strong>に全体の範囲を指定してください。承認は指定された承認者のうち<strong>いずれか1名</strong>が行います。</p>
                    <div class="mb-3">
                        <label class="form-label small text-muted">開始日時</label>
                        <input type="text" name="starts_at" class="form-control js-paid-leave-datetime" required autocomplete="off" placeholder="日時を選択">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">終了日時</label>
                        <input type="text" name="ends_at" class="form-control js-paid-leave-datetime" required autocomplete="off" placeholder="日時を選択">
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
