# ステージング／本番へ PDF 日本語フォントを反映する手順

PDF で日本語が `?` にならないようにするため、**Blade の変更**と **フォントファイル**をサーバーに揃えます。

---

## このファイルを（ローカル）→ FTP でここに（サーバー）

**考え方:** 下の「ローカル」のファイルを、FTP の **リモート側**では **同じフォルダの並び**になるように置きます。  
リモートの「プロジェクトルート」= サーバー上で **`artisan` ファイルがあるフォルダ**（`resources` と `storage` が並ぶ場所）。

### まずメモ（自分用）

- サーバー上の Laravel プロジェクトルートのパス（FTP で開くフォルダ）:  
  `___________________________`  
  （例: `/home/サーバーID/ドメイン名/public_html` など。**人によって違います**。）

### 対応表（1行ずつコピーして転送）

| # | ローカル（この Mac のファイル） | FTP のリモート先（プロジェクトルートからの相対パス＝置く場所） |
|---|--------------------------------|----------------------------------------------------------------|
| 1 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/resources/views/pdf/partials/fonts.blade.php` | `resources/views/pdf/partials/fonts.blade.php` |
| 2 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/resources/views/pdf/attendance.blade.php` | `resources/views/pdf/attendance.blade.php` |
| 3 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/resources/views/pdf/assignment.blade.php` | `resources/views/pdf/assignment.blade.php` |
| 4 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/resources/views/pdf/attendance_monthly.blade.php` | `resources/views/pdf/attendance_monthly.blade.php` |
| 5 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/resources/views/pdf/assignment_monthly.blade.php` | `resources/views/pdf/assignment_monthly.blade.php` |
| 6 | `/Users/yuki/Documents/中塚建設/nakatsuka_new/storage/fonts/NotoSansCJKjp-Regular.otf` | `storage/fonts/NotoSansCJKjp-Regular.otf`（**転送タイプ: バイナリ**） |

**FileZilla の操作イメージ**

1. 左ペイン（ローカル）で Finder と同じように、上表の **左のパス**まで開く。  
2. 右ペイン（リモート）で、**プロジェクトルート**まで開く（そこに `app` `resources` `storage` がある状態）。  
3. 左のファイルを右の **対応するフォルダ**へドラッグ。  
   - 例: 左の `fonts.blade.php` → 右で `resources` → `views` → `pdf` → `partials` を開いたうえでドロップ。  
4. `partials` フォルダがサーバーに無いときは、先に **フォルダ `partials` を作成**してから 1 番をアップロード。

**リモートのフルパスで書くと（例）**

- プロジェクトルートが `/home/xxx/domain/public_html` なら、6 番は次と同じ意味になります。  
  `/home/xxx/domain/public_html/storage/fonts/NotoSansCJKjp-Regular.otf`

---

## 1. アップロードするもの（ローカル側の場所）

プロジェクトルート（`nakatsuka_new/`）から、次のパスを **ディレクトリ構造ごと** サーバーへ送ります。

### A. ビュー（必須）

| ローカルパス |
|-------------|
| `resources/views/pdf/partials/fonts.blade.php` |
| `resources/views/pdf/attendance.blade.php` |
| `resources/views/pdf/assignment.blade.php` |
| `resources/views/pdf/attendance_monthly.blade.php` |
| `resources/views/pdf/assignment_monthly.blade.php` |

- `partials` フォルダがサーバーに無ければ **フォルダごと** 作成してから `fonts.blade.php` を置く。

### B. フォント（必須）

| ローカルパス | サーバー上の名前（完全一致） |
|-------------|------------------------------|
| `storage/fonts/NotoSansCJKjp-Regular.otf` | `NotoSansCJKjp-Regular.otf` |

- サイズは **約 16MB**。転送に数分かかることがあります。
- ファイル名のスペル・大文字小文字を **ローカルと同じ** にしてください。

---

## 2. サーバー側の「どこ」に置くか

**Laravel のプロジェクトルート**は、次のようなフォルダです（`artisan` ファイルがある階層）。

- 例: `~/ドメイン名/public_html/` の直下に Laravel 一式がある  
- 例: `public_html` の **ひとつ上** に `app/`, `resources/`, `storage/` がある  

**重要:** ブラウザのドキュメントルート（`public` や `stage`）ではなく、**`resources/` と `storage/` が並ぶ階層**がプロジェクトルートです。

サーバー上で次が存在すれば正しい場所です。

- `プロジェクトルート/resources/views/pdf/...`
- `プロジェクトルート/storage/fonts/NotoSansCJKjp-Regular.otf`

---

## 3. FTP（FileZilla など）での手順

1. **接続**  
   - ホスト・ユーザー・パスワードはレンタルサーバー（Xserver など）の FTP 情報を使用。

2. **リモートでプロジェクトルートへ移動**  
   - いつもデプロイしている Laravel のルートフォルダを開く。

3. **フォルダを先に作る（無い場合）**  
   - `resources/views/pdf/partials/`  
   - `storage/fonts/`（多くの環境では既にある）

4. **ファイルをアップロード**  
   - 上記「1. アップロードするもの」と **同じ相対パス** で置く。

5. **バイナリ転送（重要）**  
   - `.otf` は **バイナリ**。FileZilla の場合:  
     **転送 → 転送タイプ → バイナリ** を選ぶ（または「自動」で `.otf` がバイナリ扱いになる設定）。  
   - ASCII で転送すると PDF が壊れたり、フォントが読めません。

6. **権限（パーミッション）**  
   - `storage/fonts/` は Web サーバーが読めるように **705 または 755** が一般的。  
   - `NotoSansCJKjp-Regular.otf` は **644** で可読なら問題ありません。

---

## 4. SSH で確認できる場合（任意）

サーバーに SSH がある場合、プロジェクトルートで:

```bash
ls -la storage/fonts/NotoSansCJKjp-Regular.otf
```

- ファイルサイズが **約 16MB（16467736 バイト前後）** なら、転送は成功していることが多いです。  
- サイズが極端に小さい（数 KB など）→ HTML のエラーページを誤って保存している可能性があります。

---

## 5. デプロイ後のキャッシュクリア（推奨）

SSH でプロジェクトルートに入り:

```bash
php artisan view:clear
php artisan config:clear
```

（`config:cache` を本番で使っている場合は、その後に `php artisan config:cache` も実行）

FTP のみで SSH が使えない場合、**ビューキャッシュを使っていなければ** 多くの場合、ファイルを上書きすれば次のリクエストから反映されます。  
もし古い PDF が出る場合は、サーバーパネルで **OPcache のリセット**や **数分待つ** も試してください。

---

## 6. 動作確認

1. ブラウザで **ステージング／本番** にログイン。  
2. **勤怠 PDF** などを再度ダウンロード。  
3. 日本語（「勤怠」「日付」「現場名」など）が **`?` でなく表示**されれば OK。

### 補足: アップロード済みなのにまだ `?` のとき（重要）

DomPDF は CSS の `@font-face` のうち **`format('truetype')` と書かれた行だけ** をフォント登録に使います。  
`format('opentype')` だけだと **登録されず**、日本語はすべて `?` のままです。  
`resources/views/pdf/partials/fonts.blade.php` が **truetype 指定の版**になっているか確認し、変更したら **FTP で再アップロード**してください。

### 補足: 本文は出るが見出し・表頭だけ `?` のとき

**太字**（`h1`、`th`、`font-weight: 700` など）用のフォントが別扱いになるため、通常（400）だけ登録していると **太字部分だけ Helvetica に落ちて `?`** になります。  
`fonts.blade.php` で **`font-weight: 400` と `700` の `@font-face` を両方**（同じ OTF で可）定義している版に更新し、再アップロードしてください。

まだ `?` のときは次を確認:

- [ ] `storage/fonts/NotoSansCJKjp-Regular.otf` がサーバーに **実在**するか  
- [ ] **パス・ファイル名**がローカルと一致しているか  
- [ ] `resources/views/pdf/partials/fonts.blade.php` がアップロード済みか  
- [ ] `.otf` を **バイナリ**で転送したか  

---

## 7. 本番とステージングが別ディレクトリの場合

- **ステージング用**の Laravel ルートと **本番用**の Laravel ルートは別になることが多いです。  
- **両方** に、同じファイルを同じ相対パスでアップロードしてください。

---

## 8. フォントをサーバーに直接ダウンロードする方法（代替）

PC から 16MB を上げるのが難しい場合、SSH でサーバーに入りプロジェクトルートで:

```bash
cd storage/fonts
curl -fL -O "https://github.com/notofonts/noto-cjk/raw/main/Sans/OTF/Japanese/NotoSansCJKjp-Regular.otf"
ls -la NotoSansCJKjp-Regular.otf
```

※ サーバーが **外向き HTTPS** を許可している必要があります。
