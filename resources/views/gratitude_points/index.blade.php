@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">感謝ポイント</h1>
            <div class="text-muted small">
                従業員同士で感謝を贈り、ポイントがたまる機能です。誰が誰に送ったか・内容は全員が確認できる予定です。
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm mb-3" role="status">
        <strong>準備中です。</strong>
        現在は画面の確認のみ可能です。感謝の送信・ポイント付与・公開タイムラインは、順次リリース予定です。
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3">感謝を贈る（プレビュー）</h2>
                    <form action="#" method="POST" onsubmit="return false;">
                        <div class="mb-3">
                            <label class="form-label small text-muted" for="recipient_staff_id">贈りたい人</label>
                            <select
                                id="recipient_staff_id"
                                name="recipient_staff_id"
                                class="form-select"
                                disabled
                                aria-describedby="recipient_help"
                            >
                                <option value="">選択してください</option>
                                @foreach ($staff_list as $s)
                                    <option value="{{ $s->id }}">{{ $s->staff_name }}</option>
                                @endforeach
                            </select>
                            <div id="recipient_help" class="form-text">ログイン中のアカウントから、社員マスタの一覧から選べる予定です。</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted" for="message">感謝の内容</label>
                            <textarea
                                id="message"
                                name="message"
                                class="form-control"
                                rows="4"
                                placeholder="具体的な行動や助けになったことを書けるようにします"
                                disabled
                            ></textarea>
                        </div>
                        <button type="button" class="btn btn-primary" disabled>送信（準備中）</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h6 fw-semibold mb-3">みんなの感謝（準備中）</h2>
                    <div class="flex-grow-1 d-flex align-items-center justify-content-center text-center text-muted py-5 border rounded bg-light">
                        <div>
                            <div class="mb-2">公開タイムラインは準備中です</div>
                            <div class="small">送信者・受信者・メッセージが全員に見える形で表示する予定です</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
