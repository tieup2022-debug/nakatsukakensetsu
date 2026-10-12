<style>
    /* 現場の声（書き込み・返信の一覧）。現場ページと一覧ページで共用 */
    .koe-card {
        border: 1px solid #e2e8f0;
        border-left: 4px solid #dc2626;
        border-radius: 10px;
        background: #fff;
        padding: .65rem .8rem;
        margin-bottom: .7rem;
    }
    .koe-card.is-doing { border-left-color: #2563eb; }
    .koe-card.is-done, .koe-card.is-skip { border-left-color: #94a3b8; background: #f8fafc; }
    .koe-card.is-flash { box-shadow: 0 0 0 3px #fde68a; }
    .koe-head { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: .78rem; }
    .koe-no { font-weight: 700; color: #334155; }
    .koe-kind, .koe-status { padding: .1rem .45rem; border-radius: 999px; font-weight: 600; }
    .koe-kind.is-request { background: #fee2e2; color: #991b1b; }
    .koe-kind.is-info { background: #dcfce7; color: #166534; }
    .koe-kind.is-other { background: #e2e8f0; color: #334155; }
    .koe-status.is-open { background: #fef3c7; color: #92400e; }
    .koe-status.is-doing { background: #dbeafe; color: #1e40af; }
    .koe-status.is-done { background: #e2e8f0; color: #334155; }
    .koe-status.is-skip { background: #f1f5f9; color: #64748b; }
    .koe-date { margin-left: auto; color: #64748b; }
    .koe-meta { display: flex; flex-wrap: wrap; gap: .5rem; font-size: .8rem; color: #475569; margin: .25rem 0; }
    .koe-where { color: #64748b; }
    .koe-body { white-space: pre-wrap; overflow-wrap: anywhere; font-size: .93rem; line-height: 1.6; color: #0f172a; }
    .koe-pinlink { padding: .15rem 0; font-size: .85rem; text-decoration: none; }
    .koe-replies { margin-top: .5rem; border-top: 1px dashed #e2e8f0; padding-top: .4rem; }
    .koe-reply { margin: .35rem 0 .35rem .8rem; padding: .4rem .6rem; border-radius: 8px; background: #f1f5f9; }
    .koe-reply.is-master { background: #eff6ff; }
    .koe-reply-head { display: flex; gap: .5rem; font-size: .76rem; color: #475569; }
    .koe-reply .koe-body { font-size: .88rem; }
    .koe-actions { display: flex; align-items: center; gap: .5rem; margin-top: .5rem; }
    .koe-status-select { width: auto; }
    .koe-reply-form { margin-top: .5rem; }
    .koe-reply-form textarea { resize: vertical; }
    .koe-error { color: #b91c1c; font-size: .8rem; margin-top: .25rem; }
    .koe-empty { color: #64748b; font-size: .9rem; margin: .5rem 0; }
</style>
