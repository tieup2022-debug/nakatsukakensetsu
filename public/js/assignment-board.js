(function () {
    'use strict';

    var app = document.getElementById('assignmentBoardApp');
    var initialNode = document.getElementById('assignmentBoardInitial');
    if (!app || !initialNode) return;

    var boardData;
    try {
        boardData = JSON.parse(initialNode.textContent || '{}');
    } catch (error) {
        boardData = {};
    }

    var selectedStaffId = null;
    var staffFilter = 'all';
    var staffSearch = '';
    var dragPayload = null;
    var requestInFlight = false;
    var printRestore = null;
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var els = {
        board: document.getElementById('abScheduleBoard'),
        boardScroll: document.getElementById('abBoardScroll'),
        staffList: document.getElementById('abStaffList'),
        staffSearch: document.getElementById('abStaffSearch'),
        staffResult: document.getElementById('abStaffResultCount'),
        range: document.getElementById('abWeekRange'),
        assigned: document.getElementById('abAssignedCount'),
        unassigned: document.getElementById('abUnassignedCount'),
        conflict: document.getElementById('abConflictCount'),
        syncStatus: document.getElementById('abSyncStatus'),
        loading: document.getElementById('abLoading'),
        unassign: document.getElementById('abUnassignZone'),
        mobileSelection: document.getElementById('abMobileSelection'),
        mobileSelectedName: document.getElementById('abMobileSelectedName'),
        toast: document.getElementById('abToastRegion')
    };

    var urls = {
        data: app.dataset.dataUrl,
        place: app.dataset.placeUrl,
        remove: app.dataset.removeUrl,
        copyDay: app.dataset.copyDayUrl,
        base: app.dataset.baseUrl
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseDate(value) { return new Date(String(value) + 'T00:00:00'); }
    function pad(value) { return String(value).padStart(2, '0'); }
    function iso(date) { return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()); }
    function addDays(date, amount) { var copy = new Date(date); copy.setDate(copy.getDate() + amount); return copy; }
    function datesInBoard() {
        var start = parseDate(boardData.start_date);
        return Array.from({length: Number(boardData.days || 14)}, function (_, index) { return addDays(start, index); });
    }
    function staffById(id) { return (boardData.staff || []).find(function (person) { return Number(person.id) === Number(id); }); }
    function initials(name) { return String(name || '').replace(/^【[^】]+】/, '').trim().slice(0, 1) || '人'; }
    function assignmentKey(dateString, workplaceId) { return dateString + '|' + workplaceId; }

    function assignmentsByCell() {
        var map = new Map();
        (boardData.assignments || []).forEach(function (row) {
            var key = assignmentKey(row.work_date, row.workplace_id);
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(Number(row.staff_id));
        });
        return map;
    }

    function absenceSet() {
        return new Set((boardData.absences || []).map(function (row) { return row.work_date + '|' + row.staff_id; }));
    }

    function render() {
        renderRange();
        renderStaff();
        renderBoard();
        renderSummary();
        document.body.classList.toggle('staff-selected', Boolean(selectedStaffId));
        els.mobileSelection.classList.toggle('active', Boolean(selectedStaffId));
        var selected = selectedStaffId ? staffById(selectedStaffId) : null;
        els.mobileSelectedName.textContent = selected ? selected.name : '—';
    }

    function renderRange() {
        var start = parseDate(boardData.start_date);
        var end = parseDate(boardData.end_date);
        var endYear = start.getFullYear() === end.getFullYear() ? '' : end.getFullYear() + '年';
        els.range.textContent = start.getFullYear() + '年' + (start.getMonth() + 1) + '月' + start.getDate() + '日 — ' + endYear + (end.getMonth() + 1) + '月' + end.getDate() + '日';
    }

    function renderStaff() {
        var needle = staffSearch.trim().toLowerCase();
        var assignedIds = new Set((boardData.assignments || []).map(function (row) { return Number(row.staff_id); }));
        var people = (boardData.staff || []).filter(function (person) {
            var matchesType = staffFilter === 'all' || String(person.type) === staffFilter;
            var matchesName = !needle || (String(person.name) + String(person.type_label)).toLowerCase().indexOf(needle) !== -1;
            return matchesType && matchesName;
        });

        els.staffResult.textContent = people.length + '名';
        els.staffList.innerHTML = people.map(function (person) {
            var done = assignedIds.has(Number(person.id));
            return '<div class="ab-staff-card type-' + Number(person.type) + (Number(selectedStaffId) === Number(person.id) ? ' selected' : '') + '" draggable="true" data-staff-id="' + Number(person.id) + '" tabindex="0" role="button" aria-pressed="' + (Number(selectedStaffId) === Number(person.id)) + '">' +
                '<span class="ab-avatar">' + escapeHtml(initials(person.name)) + '</span>' +
                '<span class="ab-staff-meta"><strong>' + escapeHtml(person.name) + '</strong><small>' + escapeHtml(person.type_label) + '</small></span>' +
                '<span class="ab-staff-state' + (done ? ' done' : '') + '">' + (done ? '配置あり' : '未配置') + '</span>' +
                '</div>';
        }).join('') || '<div class="ab-drag-hint">該当する人員はいません</div>';

        els.staffList.querySelectorAll('.ab-staff-card').forEach(function (card) {
            card.addEventListener('click', function () { selectStaff(Number(card.dataset.staffId)); });
            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectStaff(Number(card.dataset.staffId));
                }
            });
            card.addEventListener('dragstart', function (event) {
                dragPayload = {staffId: Number(card.dataset.staffId), source: null};
                card.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('text/plain', card.dataset.staffId);
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                dragPayload = null;
                clearDragState();
            });
        });
    }

    function renderBoard() {
        var dates = datesInBoard();
        var byCell = assignmentsByCell();
        var staffMap = new Map((boardData.staff || []).map(function (person) { return [Number(person.id), person]; }));
        var absent = absenceSet();
        var workplaces = boardData.workplaces || [];
        var mobile = window.matchMedia('(max-width: 767.98px)').matches;
        var firstColumn = mobile ? 132 : 190;
        var dayWidth = mobile ? 108 : 118;

        els.board.style.gridTemplateColumns = firstColumn + 'px repeat(' + dates.length + ', ' + dayWidth + 'px)';
        els.board.style.minWidth = (firstColumn + dates.length * dayWidth) + 'px';

        var html = '<div class="ab-corner-cell"><div><strong>現場名</strong><small>' + workplaces.length + '現場・2週間</small></div><span aria-hidden="true">↕</span></div>';
        dates.forEach(function (date, dayIndex) {
            var dateString = iso(date);
            var weekday = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            var weekend = date.getDay() === 0 || date.getDay() === 6;
            var dayClass = date.getDay() === 0 ? ' sunday' : date.getDay() === 6 ? ' saturday' : '';
            var assigned = new Set();
            (boardData.assignments || []).forEach(function (row) { if (row.work_date === dateString) assigned.add(Number(row.staff_id)); });
            var availableCount = (boardData.staff || []).filter(function (person) { return !absent.has(dateString + '|' + person.id); }).length;
            var unassigned = Math.max(0, availableCount - assigned.size);
            html += '<div class="ab-date-cell' + (weekend ? ' weekend' : '') + dayClass + '" data-day-index="' + dayIndex + '">' +
                '<div class="day">' + weekday + '曜日</div><div class="number">' + (date.getMonth() + 1) + '/' + date.getDate() + '</div>' +
                '<button class="ab-copy-day" type="button" data-copy-date="' + dateString + '">▧ 前日コピー</button>' +
                '<div class="ab-day-unassigned' + (unassigned === 0 ? ' complete' : '') + '">' + (unassigned === 0 ? '全員配置済' : '未配置 ' + unassigned + '名') + '</div></div>';
        });

        workplaces.forEach(function (site, siteIndex) {
            var color = ['#2873d5', '#e77b2d', '#21a66f', '#8a4de8', '#d94e67', '#64748b', '#0ea5a7', '#ca8a04'][siteIndex % 8];
            html += '<div class="ab-site-cell" style="--site-color:' + color + '"><div class="ab-site-name">' + escapeHtml(site.name) + '</div><div class="ab-site-meta"><span>登録現場</span></div></div>';
            dates.forEach(function (date, dayIndex) {
                var dateString = iso(date);
                var ids = byCell.get(assignmentKey(dateString, site.id)) || [];
                var weekend = date.getDay() === 0 || date.getDay() === 6;
                html += '<div class="ab-drop-cell' + (weekend ? ' weekend' : '') + '" data-day-index="' + dayIndex + '" data-workplace-id="' + Number(site.id) + '" data-work-date="' + dateString + '">' + ids.map(function (staffId) {
                    var person = staffMap.get(Number(staffId));
                    if (!person) return '';
                    return '<div class="ab-assignment-chip type-' + Number(person.type) + '" draggable="true" data-staff-id="' + Number(person.id) + '" data-workplace-id="' + Number(site.id) + '" data-work-date="' + dateString + '" title="' + escapeHtml(person.name + ' / ' + person.type_label) + '"><span>' + escapeHtml(person.name) + '</span><button class="ab-remove-chip" type="button" aria-label="' + escapeHtml(person.name) + 'の配置を解除">×</button></div>';
                }).join('') + '</div>';
            });
        });
        els.board.innerHTML = html;

        els.board.querySelectorAll('.ab-drop-cell').forEach(function (cell) {
            cell.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = dragPayload && dragPayload.source ? 'move' : 'copy';
                cell.classList.add('drag-over');
            });
            cell.addEventListener('dragleave', function () { cell.classList.remove('drag-over'); });
            cell.addEventListener('drop', function (event) {
                event.preventDefault();
                cell.classList.remove('drag-over');
                if (dragPayload) placeStaff(dragPayload.staffId, Number(cell.dataset.workplaceId), cell.dataset.workDate);
            });
            cell.addEventListener('click', function (event) {
                if (selectedStaffId && !event.target.closest('.ab-assignment-chip')) {
                    placeStaff(selectedStaffId, Number(cell.dataset.workplaceId), cell.dataset.workDate);
                }
            });
        });

        els.board.querySelectorAll('.ab-assignment-chip').forEach(function (chip) {
            chip.addEventListener('dragstart', function (event) {
                dragPayload = {
                    staffId: Number(chip.dataset.staffId),
                    source: {workplaceId: Number(chip.dataset.workplaceId), workDate: chip.dataset.workDate}
                };
                chip.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', chip.dataset.staffId);
                event.stopPropagation();
            });
            chip.addEventListener('dragend', function () {
                chip.classList.remove('dragging');
                dragPayload = null;
                clearDragState();
            });
            var remove = chip.querySelector('.ab-remove-chip');
            if (remove) remove.addEventListener('click', function (event) {
                event.stopPropagation();
                removeStaff(Number(chip.dataset.staffId), Number(chip.dataset.workplaceId), chip.dataset.workDate);
            });
        });

        els.board.querySelectorAll('.ab-copy-day').forEach(function (button) {
            button.addEventListener('click', function () { copyPreviousDay(button.dataset.copyDate); });
        });
    }

    function renderSummary() {
        var assignedIds = new Set((boardData.assignments || []).map(function (row) { return Number(row.staff_id); }));
        var conflicts = 0;
        datesInBoard().forEach(function (date) {
            var counts = {};
            (boardData.assignments || []).forEach(function (row) {
                if (row.work_date === iso(date)) counts[row.staff_id] = (counts[row.staff_id] || 0) + 1;
            });
            conflicts += Object.values(counts).filter(function (count) { return count > 1; }).length;
        });
        els.assigned.textContent = assignedIds.size;
        els.unassigned.textContent = Math.max(0, (boardData.staff || []).length - assignedIds.size);
        els.conflict.textContent = conflicts;
    }

    function selectStaff(staffId) {
        selectedStaffId = Number(selectedStaffId) === Number(staffId) ? null : Number(staffId);
        render();
        if (selectedStaffId) {
            var person = staffById(selectedStaffId);
            toast((person ? person.name : '人員') + 'を選択しました。配置先のマスをタップしてください。');
            if (window.matchMedia('(max-width: 767.98px)').matches) {
                window.setTimeout(function () {
                    document.querySelector('.ab-board-card').scrollIntoView({behavior: 'smooth', block: 'start'});
                }, 100);
            }
        }
    }

    async function placeStaff(staffId, workplaceId, workDate) {
        var absent = absenceSet().has(workDate + '|' + staffId);
        if (absent) {
            toast('欠勤予定のため配置できません。', 'warning');
            return;
        }
        var saved = await mutate(urls.place, {staff_id: staffId, workplace_id: workplaceId, work_date: workDate});
        if (saved) {
            selectedStaffId = null;
            render();
        }
    }

    async function removeStaff(staffId, workplaceId, workDate) {
        await mutate(urls.remove, {staff_id: staffId, workplace_id: workplaceId, work_date: workDate});
    }

    async function copyPreviousDay(workDate) {
        var date = parseDate(workDate);
        if (!window.confirm((date.getMonth() + 1) + '/' + date.getDate() + 'の配置を前稼働日からコピーしますか？\n現在の人員配置は上書きされます。')) return;
        await mutate(urls.copyDay, {work_date: workDate});
    }

    async function mutate(url, payload) {
        if (requestInFlight) return false;
        setLoading(true, '保存中');
        try {
            payload.start_date = boardData.start_date;
            var response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify(payload)
            });
            if (response.status === 401 || response.redirected) {
                window.location.href = urls.base;
                return false;
            }
            var body = await response.json();
            if (!response.ok) throw new Error(body.message || '処理に失敗しました。');
            if (body.board) boardData = body.board;
            render();
            toast(body.message || '保存しました。', 'success');
            setSyncState('saved');
            return true;
        } catch (error) {
            toast(error.message || '通信に失敗しました。', 'warning');
            setSyncState('error');
            return false;
        } finally {
            setLoading(false);
        }
    }

    async function refreshBoard(showOverlay) {
        if (requestInFlight) return;
        requestInFlight = true;
        if (showOverlay) setLoading(true, '同期中'); else setSyncState('loading');
        try {
            var response = await fetch(urls.data + '?start_date=' + encodeURIComponent(boardData.start_date), {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            });
            if (response.status === 401 || response.redirected) {
                window.location.href = urls.base;
                return;
            }
            if (!response.ok) throw new Error('最新データを取得できませんでした。');
            var fresh = await response.json();
            var changed = fresh.revision !== boardData.revision;
            boardData = fresh;
            if (changed || showOverlay) render();
            setSyncState('saved');
        } catch (error) {
            setSyncState('error');
            if (showOverlay) toast(error.message, 'warning');
        } finally {
            if (showOverlay) setLoading(false); else requestInFlight = false;
        }
    }

    function setLoading(isLoading, label) {
        requestInFlight = isLoading;
        els.loading.hidden = !isLoading;
        if (isLoading) {
            var text = els.loading.lastChild;
            if (text && text.nodeType === Node.TEXT_NODE) text.textContent = label === '保存中' ? '変更を保存しています…' : '最新の配置を読み込んでいます…';
        }
        if (isLoading) setSyncState('loading');
    }

    function setSyncState(state) {
        els.syncStatus.classList.toggle('is-loading', state === 'loading');
        els.syncStatus.classList.toggle('is-error', state === 'error');
        var label = els.syncStatus.querySelector('span');
        if (label) label.textContent = state === 'loading' ? '同期中' : state === 'error' ? '同期エラー' : '自動保存';
    }

    function toast(message, type) {
        var item = document.createElement('div');
        item.className = 'ab-toast' + (type ? ' ' + type : '');
        item.textContent = message;
        els.toast.appendChild(item);
        window.setTimeout(function () { item.remove(); }, 3000);
    }

    function clearDragState() {
        document.querySelectorAll('.ab-drop-cell.drag-over, .ab-unassign-zone.drag-over').forEach(function (node) { node.classList.remove('drag-over'); });
    }

    function navigateWeek(offset) {
        var target = addDays(parseDate(boardData.start_date), offset);
        window.location.href = urls.base + '?start_date=' + encodeURIComponent(iso(target));
    }

    function currentMonday() {
        var now = new Date();
        var day = now.getDay();
        return addDays(now, day === 0 ? -6 : 1 - day);
    }

    function printBoard(days) {
        if (printRestore) return;
        var hidden = [];
        els.board.querySelectorAll('[data-day-index]').forEach(function (node) {
            if (Number(node.dataset.dayIndex) >= days) {
                hidden.push(node);
                node.hidden = true;
            }
        });
        var oldColumns = els.board.style.gridTemplateColumns;
        var oldMinWidth = els.board.style.minWidth;
        els.board.style.gridTemplateColumns = '160px repeat(' + days + ', 1fr)';
        els.board.style.minWidth = '0';
        printRestore = function () {
            hidden.forEach(function (node) { node.hidden = false; });
            els.board.style.gridTemplateColumns = oldColumns;
            els.board.style.minWidth = oldMinWidth;
            printRestore = null;
        };
        window.addEventListener('afterprint', printRestore, {once: true});
        window.print();
        window.setTimeout(function () { if (printRestore) printRestore(); }, 1000);
    }

    els.staffSearch.addEventListener('input', function (event) { staffSearch = event.target.value; renderStaff(); });
    document.querySelectorAll('[data-staff-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            staffFilter = button.dataset.staffFilter;
            document.querySelectorAll('[data-staff-filter]').forEach(function (item) { item.classList.toggle('active', item === button); });
            renderStaff();
        });
    });
    document.getElementById('abClearSelection').addEventListener('click', function () { selectedStaffId = null; render(); });
    document.getElementById('abMobileCancelSelection').addEventListener('click', function () { selectedStaffId = null; render(); });
    document.getElementById('abPreviousWeek').addEventListener('click', function () { navigateWeek(-7); });
    document.getElementById('abNextWeek').addEventListener('click', function () { navigateWeek(7); });
    document.getElementById('abCurrentWeek').addEventListener('click', function () { window.location.href = urls.base + '?start_date=' + encodeURIComponent(iso(currentMonday())); });
    document.getElementById('abPrintWeek').addEventListener('click', function () { printBoard(7); });
    document.getElementById('abPrintTwoWeeks').addEventListener('click', function () { printBoard(14); });

    els.unassign.addEventListener('dragover', function (event) { event.preventDefault(); els.unassign.classList.add('drag-over'); });
    els.unassign.addEventListener('dragleave', function () { els.unassign.classList.remove('drag-over'); });
    els.unassign.addEventListener('drop', function (event) {
        event.preventDefault();
        els.unassign.classList.remove('drag-over');
        if (dragPayload && dragPayload.source) {
            removeStaff(dragPayload.staffId, dragPayload.source.workplaceId, dragPayload.source.workDate);
        } else {
            toast('未配置の人員です。', 'warning');
        }
        dragPayload = null;
    });

    window.addEventListener('resize', function () { renderBoard(); });
    window.setInterval(function () { if (!document.hidden) refreshBoard(false); }, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshBoard(false); });
    window.addEventListener('focus', function () { refreshBoard(false); });

    render();
})();
