# PDF 用日本語フォント

勤怠・配置などの PDF で日本語を表示するため、次のファイルをこのディレクトリに置いてください。

- **ファイル名**: `NotoSansCJKjp-Regular.otf`

## 入手方法（例）

1. [Noto CJK（Google Fonts / notofonts）](https://github.com/notofonts/noto-cjk) から **Noto Sans CJK JP Regular** の OTF を取得する  
2. 上記ファイル名にリネームして `storage/fonts/NotoSansCJKjp-Regular.otf` として保存する

## サーバー（ステージング本番）へ

- ローカルと同様に **`storage/fonts/NotoSansCJKjp-Regular.otf`** をアップロードする  
- ファイルサイズが大きい（約16MB）ため、FTP や rsync で転送してください

フォントが無いと DomPDF は日本語を `?` で表示します。
