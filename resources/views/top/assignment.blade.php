@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/assignment-board.css') }}?v={{ @filemtime(public_path('css/assignment-board.css')) ?: '1' }}">
@endpush

@section('content')
    <div
        class="assignment-board-page"
        id="assignmentBoardApp"
        data-data-url="{{ $boardDataUrl }}"
        data-place-url="{{ $boardPlaceUrl }}"
        data-remove-url="{{ $boardRemoveUrl }}"
        data-copy-day-url="{{ $boardCopyDayUrl }}"
        data-base-url="{{ route('top.assignment') }}"
    >
        <section class="ab-heading" aria-labelledby="assignmentBoardTitle">
            <div>
                <div class="ab-eyebrow">NAKATSUKA DX / ASSIGNMENT</div>
                <h1 id="assignmentBoardTitle">人員配置システム</h1>
                <p>人員を選び、現場と日付のマスをタップしてください。変更はその場で保存されます。</p>
            </div>
            <div class="ab-summary" aria-label="配置状況">
                <div class="ab-summary-item"><i class="ab-summary-bar is-blue"></i><span><strong id="abAssignedCount">0</strong><small>配置済み</small></span></div>
                <div class="ab-summary-item"><i class="ab-summary-bar is-amber"></i><span><strong id="abUnassignedCount">0</strong><small>未配置</small></span></div>
                <div class="ab-summary-item"><i class="ab-summary-bar is-red"></i><span><strong id="abConflictCount">0</strong><small>重複</small></span></div>
            </div>
        </section>

        <section class="ab-toolbar" aria-label="表示期間と出力">
            <div class="ab-week-nav">
                <button class="ab-icon-button" id="abPreviousWeek" type="button" aria-label="前の週">←</button>
                <div class="ab-week-label"><strong id="abWeekRange">—</strong><small>2週間表示</small></div>
                <button class="ab-icon-button" id="abNextWeek" type="button" aria-label="次の週">→</button>
                <button class="ab-button is-soft" id="abCurrentWeek" type="button">今週</button>
            </div>
            <div class="ab-toolbar-actions">
                <span class="ab-sync-status" id="abSyncStatus"><i></i><span>自動保存</span></span>
                @if (!empty($canAccessAssignmentSettings))
                    <a class="ab-button is-soft" href="{{ route('setting.assignment.manage') }}">詳細入力</a>
                @endif
                <button class="ab-button is-week" id="abPrintWeek" type="button">▣ 1週間出力</button>
                <button class="ab-button is-two-weeks" id="abPrintTwoWeeks" type="button">▣ 2週間出力</button>
            </div>
        </section>

        <div class="ab-mobile-note"><span aria-hidden="true">☝</span> 人員をタップ → 配置先をタップ。表は横にスワイプできます。</div>

        <section class="ab-layout">
            <aside class="ab-people-panel" aria-labelledby="abStaffHeading">
                <div class="ab-panel-heading">
                    <div><h2 id="abStaffHeading">スタッフ一覧</h2><span id="abStaffResultCount">0名</span></div>
                    <button class="ab-mini-button" id="abClearSelection" type="button">選択解除</button>
                </div>
                <label class="ab-search-field">
                    <span aria-hidden="true">⌕</span>
                    <input id="abStaffSearch" type="search" placeholder="名前を検索" autocomplete="off">
                </label>
                <div class="ab-segmented" role="group" aria-label="人員区分の絞り込み">
                    <button class="active" type="button" data-staff-filter="all">全員</button>
                    <button type="button" data-staff-filter="1">技術者</button>
                    <button type="button" data-staff-filter="2">OP</button>
                    <button type="button" data-staff-filter="3">作業員</button>
                </div>
                <div class="ab-drag-hint"><span aria-hidden="true">↗</span> ドラッグ、または選択して配置</div>
                <div id="abStaffList" class="ab-staff-list" aria-live="polite"></div>
                <div class="ab-unassign-zone" id="abUnassignZone" tabindex="0">
                    <span class="ab-trash-icon" aria-hidden="true">⌫</span>
                    <span><strong>配置を解除</strong><small>配置済みの人員をここへ移動</small></span>
                </div>
            </aside>

            <div class="ab-board-card">
                <div class="ab-loading" id="abLoading" hidden><span></span>最新の配置を読み込んでいます…</div>
                <div class="ab-board-scroll" id="abBoardScroll">
                    <div id="abScheduleBoard" class="ab-schedule-board" aria-label="2週間人員配置表"></div>
                </div>
                <div class="ab-legend">
                    <span><i class="ab-legend-color is-type-1"></i>技術者</span>
                    <span><i class="ab-legend-color is-type-2"></i>OP</span>
                    <span><i class="ab-legend-color is-type-3"></i>作業員</span>
                    <span><i class="ab-legend-color is-weekend"></i>土日</span>
                    <span class="ab-legend-tip">30秒ごと・画面復帰時に自動同期</span>
                </div>
            </div>
        </section>

        <div class="ab-mobile-selection" id="abMobileSelection" aria-live="polite">
            <span><small>選択中</small><strong id="abMobileSelectedName">—</strong></span>
            <span class="ab-mobile-selection-guide">配置先をタップ</span>
            <button id="abMobileCancelSelection" type="button">解除</button>
        </div>
        <div class="ab-toast-region" id="abToastRegion" aria-live="polite"></div>
    </div>

    <script type="application/json" id="assignmentBoardInitial">@json($boardData)</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/assignment-board.js') }}?v={{ @filemtime(public_path('js/assignment-board.js')) ?: '1' }}"></script>
@endpush
