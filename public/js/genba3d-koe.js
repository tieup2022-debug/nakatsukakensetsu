/*
 * 現場3D「現場の声」
 *  - Genba3dKoe.renderList: 書き込みの一覧（返信・対応状況・削除）を組み立てる。現場ページと一覧ページで共用。
 *  - 現場ページ（#genba3dKoe があるとき）: 書き込み欄、3Dモデルへのピン立て、ピンの表示と移動。
 *
 * 3Dモデルは同じドメインの iframe なので、中の window.__viewer（camera / controls / scene）と THREE を直接使う。
 * 3Dモデルのページ自体には手を入れずに、ピンの層を iframe の中に足して重ねる。
 */
(function () {
    'use strict';

    var KOE = window.Genba3dKoe = {};
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');

    function api(url, method, body) {
        var opt = {
            method: method || 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
            }
        };
        if (body !== undefined) {
            opt.headers['Content-Type'] = 'application/json';
            opt.body = JSON.stringify(body);
        }
        return window.fetch(url, opt).then(function (res) {
            return res.text().then(function (t) {
                var data = null;
                try { data = JSON.parse(t); } catch (e) { /* ログイン画面などが返ってきた */ }
                var message = data && data.message;
                if (data && data.errors) {
                    var first = Object.keys(data.errors)[0];
                    if (first) message = data.errors[first][0];
                }
                if (!res.ok && !message) {
                    message = (res.status === 419 || res.status === 401 || !data)
                        ? 'ログインの有効期限が切れたようです。ページを読み込み直してください。'
                        : '保存できませんでした。少し時間をおいて、もう一度お試しください。';
                }
                return { ok: res.ok && !!data, status: res.status, data: data, message: message };
            });
        }, function () {
            return { ok: false, status: 0, data: null, message: '通信できませんでした。電波の状態を確かめて、もう一度お試しください。' };
        });
    }
    KOE.api = api;

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined && text !== null) e.textContent = text;
        return e;
    }
    function fill(tmpl, id) { return String(tmpl).replace('__ID__', String(id)); }

    /**
     * 書き込みの一覧を描く。
     * ctx: { isMaster, statuses, urls: {reply, status, destroy}, onChanged(), onPin(item)?, pinHref(item)?, showSite, highlightId }
     */
    KOE.renderList = function (box, items, ctx) {
        box.textContent = '';
        if (!items.length) {
            box.appendChild(el('p', 'koe-empty', ctx.emptyText || 'まだ書き込みはありません。'));
            return;
        }
        items.forEach(function (it) { box.appendChild(card(it, ctx)); });
    };

    function card(it, ctx) {
        var c = el('article', 'koe-card is-' + it.status);
        c.id = 'koe-item-' + it.id;
        c.dataset.id = it.id;
        if (ctx.highlightId && String(ctx.highlightId) === String(it.id)) c.classList.add('is-flash');

        var head = el('div', 'koe-head');
        head.appendChild(el('span', 'koe-no', 'No.' + it.id));
        head.appendChild(el('span', 'koe-kind is-' + it.kind, it.kindLabel));
        head.appendChild(el('span', 'koe-status is-' + it.status, it.statusLabel));
        head.appendChild(el('span', 'koe-date', it.createdAt));
        c.appendChild(head);

        var meta = el('div', 'koe-meta');
        meta.appendChild(el('span', 'koe-author', it.author));
        if (ctx.showSite) meta.appendChild(el('span', 'koe-where', it.siteName + '・' + it.tabLabel));
        else if (it.tab !== 'model') meta.appendChild(el('span', 'koe-where', it.tabLabel));
        c.appendChild(meta);

        c.appendChild(el('div', 'koe-body', it.body));

        if (it.pin && it.tab === 'model') {
            var label = '📍 3Dで場所を見る' + (it.pin.part ? '（' + it.pin.part + '）' : '');
            if (ctx.onPin) {
                var pb = el('button', 'btn btn-link btn-sm koe-pinlink', label);
                pb.type = 'button';
                pb.addEventListener('click', function () { ctx.onPin(it); });
                c.appendChild(pb);
            } else if (ctx.pinHref) {
                var a = el('a', 'btn btn-link btn-sm koe-pinlink', label);
                a.href = ctx.pinHref(it);
                c.appendChild(a);
            }
        }

        if (it.replies.length) {
            var rs = el('div', 'koe-replies');
            it.replies.forEach(function (r) {
                var rr = el('div', 'koe-reply' + (r.isMaster ? ' is-master' : ''));
                var rh = el('div', 'koe-reply-head');
                rh.appendChild(el('span', 'koe-author', r.author + (r.isMaster ? '（管理者）' : '')));
                rh.appendChild(el('span', 'koe-date', r.createdAt));
                rr.appendChild(rh);
                rr.appendChild(el('div', 'koe-body', r.body));
                rs.appendChild(rr);
            });
            c.appendChild(rs);
        }

        // 操作: 返信・対応状況（管理者）・削除
        var act = el('div', 'koe-actions');
        var replyBtn = el('button', 'btn btn-outline-secondary btn-sm', '返信');
        replyBtn.type = 'button';
        act.appendChild(replyBtn);
        if (ctx.isMaster) {
            var sel = el('select', 'form-select form-select-sm koe-status-select');
            sel.setAttribute('aria-label', 'No.' + it.id + ' の対応状況');
            Object.keys(ctx.statuses).forEach(function (k) {
                var o = el('option', null, ctx.statuses[k]);
                o.value = k;
                if (k === it.status) o.selected = true;
                sel.appendChild(o);
            });
            sel.addEventListener('change', function () {
                sel.disabled = true;
                api(fill(ctx.urls.status, it.id), 'PATCH', { status: sel.value }).then(function (r) {
                    if (!r.ok) window.alert(r.message);
                    ctx.onChanged();
                });
            });
            act.appendChild(sel);
        }
        if (it.canDelete) {
            var del = el('button', 'btn btn-link btn-sm text-danger ms-auto', '消す');
            del.type = 'button';
            del.addEventListener('click', function () {
                if (!window.confirm('No.' + it.id + ' の書き込みを消します。よろしいですか？')) return;
                api(fill(ctx.urls.destroy, it.id), 'DELETE').then(function (r) {
                    if (!r.ok) window.alert(r.message);
                    ctx.onChanged();
                });
            });
            act.appendChild(del);
        }
        c.appendChild(act);

        var form = el('form', 'koe-reply-form');
        form.hidden = true;
        var ta = el('textarea', 'form-control form-control-sm');
        ta.rows = 2;
        ta.maxLength = 1000;
        ta.placeholder = ctx.isMaster ? '返信（書いた人に通知されます）' : '返信を入力';
        ta.setAttribute('aria-label', 'No.' + it.id + ' への返信');
        var send = el('button', 'btn btn-primary btn-sm', '送信');
        send.type = 'submit';
        var row = el('div', 'd-flex gap-2 align-items-end');
        row.appendChild(ta);
        row.appendChild(send);
        form.appendChild(row);
        var err = el('div', 'koe-error');
        form.appendChild(err);
        replyBtn.addEventListener('click', function () {
            form.hidden = !form.hidden;
            if (!form.hidden) ta.focus();
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = ta.value.trim();
            if (!text) { ta.focus(); return; }
            send.disabled = true;
            api(fill(ctx.urls.reply, it.id), 'POST', { body: text }).then(function (r) {
                send.disabled = false;
                if (!r.ok) { err.textContent = r.message; return; }
                ctx.onChanged();
            });
        });
        c.appendChild(form);

        return c;
    }

    /* ------------------------------------------------------------------ */
    /* 現場ページ                                                          */
    /* ------------------------------------------------------------------ */
    var root = document.getElementById('genba3dKoe');
    if (!root) return;

    var D = root.dataset;
    var frame = document.getElementById('genba3dFrame');
    var listBox = document.getElementById('genba3dKoeList');
    var form = document.getElementById('genba3dKoeForm');
    var input = document.getElementById('genba3dKoeInput');
    var sendBtn = document.getElementById('genba3dKoeSend');
    var formErr = document.getElementById('genba3dKoeError');
    var pinBtn = document.getElementById('genba3dKoePinBtn');
    var pinNote = document.getElementById('genba3dKoePinNote');
    var pinClear = document.getElementById('genba3dKoePinClear');
    var countBadge = document.getElementById('genba3dKoeCount');
    var banner = document.getElementById('genba3dKoeBanner');
    var bannerText = document.getElementById('genba3dKoeBannerText');
    var showDone = document.getElementById('genba3dKoeShowDone');
    var canPin = D.canPin === '1';
    var statuses = JSON.parse(D.statuses || '{}');
    var urls = { reply: D.replyUrl, status: D.statusUrl, destroy: D.destroyUrl };

    var items = [];
    var isMaster = false;
    var filter = 'active';
    var draftPin = null;
    var pinMode = false;
    var highlightId = new URLSearchParams(window.location.search).get('koe');
    var pendingFly = highlightId;

    function offcanvas() {
        return window.bootstrap ? window.bootstrap.Offcanvas.getOrCreateInstance(root) : null;
    }
    function openPanel() { var o = offcanvas(); if (o) o.show(); }

    function visibleItems() {
        return items.filter(function (it) {
            if (filter === 'active') return it.status === 'open' || it.status === 'doing';
            if (filter === 'closed') return it.status === 'done' || it.status === 'skip';
            if (filter === 'mine') return it.own;
            return true;
        });
    }

    function render() {
        var active = items.filter(function (it) { return it.status === 'open' || it.status === 'doing'; }).length;
        countBadge.textContent = active ? String(active) : '';
        countBadge.hidden = !active;
        KOE.renderList(listBox, visibleItems(), {
            isMaster: isMaster,
            statuses: statuses,
            urls: urls,
            highlightId: highlightId,
            emptyText: filter === 'active' ? '未対応の書き込みはありません。' : 'まだ書き込みはありません。',
            onChanged: load,
            onPin: canPin ? function (it) { flyTo(it.pin); flashPin(it.id); closeOnSmall(); } : null,
            pinHref: canPin ? null : function (it) { return fill(D.modelUrl, it.id); }
        });
        syncPins();
    }

    function load() {
        return api(D.listUrl).then(function (r) {
            if (!r.ok) { listBox.textContent = r.message; return; }
            items = r.data.items || [];
            isMaster = !!r.data.isMaster;
            render();
            if (highlightId) {
                var c = document.getElementById('koe-item-' + highlightId);
                if (c) {
                    var it = items.filter(function (x) { return String(x.id) === String(highlightId); })[0];
                    if (it && filter === 'active' && it.status !== 'open' && it.status !== 'doing') { filter = 'all'; setFilterButtons(); render(); }
                    openPanel();
                    window.setTimeout(function () { var cc = document.getElementById('koe-item-' + highlightId); if (cc) cc.scrollIntoView({ block: 'center' }); }, 350);
                }
            }
        });
    }

    function setFilterButtons() {
        Array.prototype.forEach.call(root.querySelectorAll('[data-koe-filter]'), function (b) {
            var on = b.dataset.koeFilter === filter;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-koe-filter]'), function (b) {
        b.addEventListener('click', function () { filter = b.dataset.koeFilter; highlightId = null; setFilterButtons(); render(); });
    });

    // 書き込み
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        formErr.textContent = '';
        if (!text) { formErr.textContent = '内容を入力してください。'; input.focus(); return; }
        var kind = (form.querySelector('input[name="koe-kind"]:checked') || {}).value || 'request';
        sendBtn.disabled = true;
        api(D.storeUrl, 'POST', { kind: kind, body: text, tab: D.tab, pin: draftPin }).then(function (r) {
            sendBtn.disabled = false;
            if (!r.ok) { formErr.textContent = r.message; return; }
            input.value = '';
            setDraft(null);
            highlightId = r.data.id;
            filter = 'active';
            setFilterButtons();
            load();
        });
    });

    /* ---------------- 3Dモデルとのやりとり ---------------- */
    function viewer() {
        try {
            var w = frame && frame.contentWindow;
            var v = w && w.__viewer;
            if (v && v.camera && v.scene && w.THREE) return { w: w, v: v, T: w.THREE, doc: w.document };
        } catch (e) { /* 読めないとき（読み込み途中など） */ }
        return null;
    }
    function canvasOf(doc) {
        var best = null, area = 0;
        Array.prototype.forEach.call(doc.querySelectorAll('canvas'), function (c) {
            var r = c.getBoundingClientRect();
            if (r.width * r.height > area) { area = r.width * r.height; best = c; }
        });
        return best;
    }

    var layerDoc = null, layer = null, markers = {}, draftMarker = null, hooked = null;
    function ensureLayer(V) {
        if (layerDoc === V.doc && layer && layer.isConnected) return true;
        var doc = V.doc;
        if (!doc.body) return false;
        var st = doc.createElement('style');
        st.textContent = [
            '#koe-layer{position:fixed;inset:0;pointer-events:none;z-index:40;overflow:hidden}',
            '.koe-pin{position:absolute;transform:translate(-50%,-100%);pointer-events:auto;cursor:pointer;display:flex;flex-direction:column;align-items:center;font:600 11px/1 system-ui,sans-serif}',
            '.koe-pin b{display:block;min-width:24px;padding:4px 6px;border-radius:12px;background:#dc2626;color:#fff;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);text-align:center}',
            '.koe-pin i{display:block;width:2px;height:10px;background:#dc2626}',
            '.koe-pin.is-doing b,.koe-pin.is-doing i{background:#2563eb}',
            '.koe-pin.is-done b,.koe-pin.is-done i,.koe-pin.is-skip b,.koe-pin.is-skip i{background:#64748b}',
            '.koe-pin.is-done,.koe-pin.is-skip{opacity:.8}',
            '.koe-pin.is-draft b,.koe-pin.is-draft i{background:#f59e0b}',
            '.koe-pin.is-flash b{animation:koeflash 1s ease-in-out 3}',
            '@keyframes koeflash{50%{transform:scale(1.5)}}',
            'body.koe-pinmode canvas{cursor:crosshair!important}'
        ].join('\n');
        doc.head.appendChild(st);
        layer = doc.createElement('div');
        layer.id = 'koe-layer';
        doc.body.appendChild(layer);
        layerDoc = doc;
        markers = {};
        draftMarker = null;
        hookCanvas(V);
        return true;
    }

    function makeMarker(doc, text, cls) {
        var m = doc.createElement('div');
        m.className = 'koe-pin ' + cls;
        var b = doc.createElement('b');
        b.textContent = text;
        m.appendChild(b);
        m.appendChild(doc.createElement('i'));
        layer.appendChild(m);
        return m;
    }

    function syncPins() {
        var V = viewer();
        if (!canPin || !V || !ensureLayer(V)) return;
        var keep = {};
        items.forEach(function (it) {
            if (!it.pin || it.tab !== 'model') return;
            var closed = it.status === 'done' || it.status === 'skip';
            if (closed && !(showDone && showDone.checked) && String(it.id) !== String(highlightId)) return;
            keep[it.id] = true;
            var m = markers[it.id];
            if (!m) {
                m = markers[it.id] = makeMarker(V.doc, String(it.id), '');
                m.addEventListener('click', function (e) {
                    e.stopPropagation();
                    highlightId = it.id;
                    var cur = items.filter(function (x) { return x.id === it.id; })[0];
                    if (cur && filter === 'active' && (cur.status === 'done' || cur.status === 'skip')) { filter = 'all'; setFilterButtons(); }
                    render();
                    openPanel();
                    window.setTimeout(function () { var c = document.getElementById('koe-item-' + it.id); if (c) c.scrollIntoView({ block: 'center' }); }, 300);
                });
            }
            m.className = 'koe-pin is-' + it.status + (m.classList.contains('is-flash') ? ' is-flash' : '');
            m.title = 'No.' + it.id + '　' + it.kindLabel + '（' + it.statusLabel + '）\n' + it.body.slice(0, 80);
            m._p = it.pin.p;
        });
        Object.keys(markers).forEach(function (id) {
            if (!keep[id]) { markers[id].remove(); delete markers[id]; }
        });
        if (draftPin) {
            if (!draftMarker) draftMarker = makeMarker(V.doc, '新', 'is-draft');
            draftMarker._p = draftPin.p;
        } else if (draftMarker) { draftMarker.remove(); draftMarker = null; }
    }

    // 毎フレーム、ピンの3D位置を画面の位置に直す
    var tmpV = null;
    function tick() {
        window.requestAnimationFrame(tick);
        if (!canPin || !layer) return;
        var V = viewer();
        if (!V || V.doc !== layerDoc) { if (V) syncPins(); return; }
        var canvas = canvasOf(V.doc);
        if (!canvas) return;
        var rect = canvas.getBoundingClientRect();
        tmpV = tmpV || new V.T.Vector3();
        var all = Object.keys(markers).map(function (k) { return markers[k]; });
        if (draftMarker) all.push(draftMarker);
        all.forEach(function (m) {
            if (!m._p) return;
            tmpV.set(m._p[0], m._p[1], m._p[2]).project(V.v.camera);
            var x = rect.left + (tmpV.x + 1) / 2 * rect.width;
            var y = rect.top + (1 - tmpV.y) / 2 * rect.height;
            var out = tmpV.z > 1 || tmpV.z < -1 || x < rect.left || x > rect.right || y < rect.top || y > rect.bottom;
            m.style.display = out ? 'none' : '';
            if (!out) { m.style.left = x + 'px'; m.style.top = y + 'px'; }
        });
    }
    window.requestAnimationFrame(tick);

    // ピン立て: キャンバスを（ドラッグでなく）タップした位置の面を拾う
    function hookCanvas(V) {
        var canvas = canvasOf(V.doc);
        if (!canvas || hooked === canvas) return;
        hooked = canvas;
        var down = null;
        canvas.addEventListener('pointerdown', function (e) { down = { x: e.clientX, y: e.clientY }; }, true);
        canvas.addEventListener('pointerup', function (e) {
            if (!pinMode || !down) return;
            var moved = Math.abs(e.clientX - down.x) + Math.abs(e.clientY - down.y);
            down = null;
            if (moved > 8) return;
            var hit = pick(viewer(), canvas, e.clientX, e.clientY);
            if (!hit) { bannerText.textContent = 'そこには部材がありません。モデルの上をタップしてください。'; return; }
            var nameEl = V.doc.getElementById('info-name');
            var before = nameEl ? nameEl.textContent : '';
            // ページ側の「部材をクリック」の表示が更新されるのを待ってから、部材名を拾う
            window.setTimeout(function () {
                var part = hit.part;
                if (!part && nameEl && nameEl.textContent !== before) part = nameEl.textContent.trim();
                var cam = V.v.camera.position, tgt = V.v.controls && V.v.controls.target;
                setDraft({
                    p: [hit.point.x, hit.point.y, hit.point.z].map(r3),
                    cam: [cam.x, cam.y, cam.z].map(r3),
                    tgt: tgt ? [tgt.x, tgt.y, tgt.z].map(r3) : null,
                    part: part ? part.slice(0, 100) : null
                });
                if (!draftPin.tgt) { delete draftPin.tgt; delete draftPin.cam; }
                if (!draftPin.part) delete draftPin.part;
                exitPinMode();
                openPanel();
                window.setTimeout(function () { input.focus(); }, 300);
            }, 80);
        }, true);
    }
    function r3(x) { return Math.round(x * 1000) / 1000; }

    function pick(V, canvas, cx, cy) {
        if (!V) return null;
        var T = V.T, r = canvas.getBoundingClientRect();
        var ndc = new T.Vector2((cx - r.left) / r.width * 2 - 1, -((cy - r.top) / r.height) * 2 + 1);
        var rc = new T.Raycaster();
        rc.setFromCamera(ndc, V.v.camera);
        var hits = rc.intersectObjects(V.v.scene.children, true).filter(function (h) { return usable(h); });
        if (!hits.length) return null;
        // 水面のような半透明の面より、その奥の部材を優先する
        var solid = hits.filter(function (h) { var m = mat(h.object); return !(m && m.transparent && m.opacity < 0.6); })[0];
        var h = solid || hits[0];
        return { point: h.point, part: partName(h.object) };
    }
    function mat(o) { return Array.isArray(o.material) ? o.material[0] : o.material; }
    function usable(h) {
        var o = h.object;
        if (!o.isMesh) return false;
        for (var p = o; p; p = p.parent) if (p.visible === false) return false;
        var m = mat(o);
        if (!m || m.visible === false || (m.transparent && m.opacity < 0.12)) return false;
        // 断面で切って見えていない部分は拾わない
        var planes = m.clippingPlanes || [];
        for (var i = 0; i < planes.length; i++) if (planes[i].distanceToPoint(h.point) < -1e-4) return false;
        return true;
    }
    function partName(o) {
        for (var p = o; p; p = p.parent) {
            var u = p.userData || {};
            var c = (u.o && u.o.name) || (u.item && u.item.name) || (u.info && typeof u.info === 'object' && (u.info.name || u.info.title));
            if (c) return String(c);
        }
        return null;
    }

    function setDraft(pin) {
        draftPin = pin;
        if (pinNote) {
            pinNote.textContent = pin ? ('📍 ピンを立てました' + (pin.part ? '（' + pin.part + '）' : '')) : '';
            pinClear.hidden = !pin;
            pinBtn.textContent = pin ? '📍 置き直す' : '📍 3Dにピンを立てる';
        }
        syncPins();
    }

    function enterPinMode() {
        var V = viewer();
        if (!V) { formErr.textContent = '3Dモデルの読み込みが終わってから、もう一度押してください。'; return; }
        ensureLayer(V);
        pinMode = true;
        V.doc.body.classList.add('koe-pinmode');
        bannerText.textContent = '📍 ピンを立てる場所を、3Dモデルの上でタップしてください（回転・拡大はそのままできます）';
        banner.hidden = false;
        var o = offcanvas(); if (o) o.hide();
        frame.scrollIntoView({ block: 'nearest' });
    }
    function exitPinMode() {
        pinMode = false;
        banner.hidden = true;
        var V = viewer();
        if (V) V.doc.body.classList.remove('koe-pinmode');
    }

    function flyTo(pin) {
        var V = viewer();
        if (!V || !pin) return false;
        var cam = V.v.camera, ctl = V.v.controls, T = V.T;
        var p = new T.Vector3(pin.p[0], pin.p[1], pin.p[2]);
        var toTgt = pin.tgt ? new T.Vector3(pin.tgt[0], pin.tgt[1], pin.tgt[2]) : p.clone();
        var toCam;
        if (pin.cam) toCam = new T.Vector3(pin.cam[0], pin.cam[1], pin.cam[2]);
        else {
            var off = cam.position.clone().sub(ctl ? ctl.target : p);
            toCam = p.clone().add(off);
        }
        var fromCam = cam.position.clone(), fromTgt = ctl ? ctl.target.clone() : p.clone();
        var t0 = performance.now(), dur = 700;
        (function step(now) {
            var k = Math.min(1, (now - t0) / dur);
            k = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
            cam.position.lerpVectors(fromCam, toCam, k);
            if (ctl) { ctl.target.lerpVectors(fromTgt, toTgt, k); ctl.update(); } else cam.lookAt(toTgt);
            if (typeof V.v.redraw === 'function') V.v.redraw();
            else if (typeof V.v.render === 'function') V.v.render();
            if (k < 1) V.w.requestAnimationFrame(step);
        })(t0);
        return true;
    }
    function flashPin(id) {
        var m = markers[id];
        if (!m) { highlightId = id; syncPins(); m = markers[id]; }
        if (!m) return;
        m.classList.remove('is-flash');
        void m.offsetWidth;
        m.classList.add('is-flash');
    }
    function closeOnSmall() {
        if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) { var o = offcanvas(); if (o) o.hide(); }
    }

    if (pinBtn) {
        pinBtn.addEventListener('click', enterPinMode);
        pinClear.addEventListener('click', function () { setDraft(null); });
    }
    document.getElementById('genba3dKoeBannerCancel').addEventListener('click', function () { exitPinMode(); openPanel(); });
    if (showDone) showDone.addEventListener('change', syncPins);

    // 3Dモデルの読み込みを待って、ピンを出す（?koe=番号 で開いたときは、その場所へ移動）
    if (canPin && frame) {
        var tries = 0;
        var waitViewer = window.setInterval(function () {
            tries++;
            var V = viewer();
            if (V && canvasOf(V.doc)) {
                syncPins();
                if (pendingFly && items.length) {
                    var it = items.filter(function (x) { return String(x.id) === String(pendingFly); })[0];
                    if (it && it.pin) { flyTo(it.pin); flashPin(it.id); }
                    pendingFly = null;
                }
                if (!pendingFly) window.clearInterval(waitViewer);
            }
            if (tries > 60) window.clearInterval(waitViewer);
        }, 300);
        frame.addEventListener('load', function () { layer = null; layerDoc = null; hooked = null; window.setTimeout(syncPins, 500); });
    }

    setFilterButtons();
    load();
})();
