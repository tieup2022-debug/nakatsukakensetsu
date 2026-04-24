@php
    $dismissOc = !empty($dismissOffcanvas);
@endphp
<div class="card border-0 shadow-sm mb-3" style="background: linear-gradient(135deg, #1e40af 0%, #312e81 100%);">
    <div class="card-body p-3 text-white">
        <div class="small text-white-50 text-uppercase mb-1">有給休暇</div>
        <div class="fw-semibold mb-2 lh-sm">申請はこちら<br><span class="small fw-normal text-white-50">（開始〜終了・時間単位）</span></div>
        <button type="button" class="btn btn-light btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#paidLeaveModal">
            申請フォームを開く
        </button>
        <div class="mt-2 small">
            <a href="{{ route('paid-leave.mine') }}" class="text-white-50" @if($dismissOc) data-bs-dismiss="offcanvas" @endif>申請一覧</a>
            @if (!empty($canApprovePaidLeave))
                <span class="text-white-50 mx-1">|</span>
                <a href="{{ route('paid-leave.approvals') }}" class="text-warning" @if($dismissOc) data-bs-dismiss="offcanvas" @endif>承認待ち</a>
            @endif
        </div>
    </div>
</div>
