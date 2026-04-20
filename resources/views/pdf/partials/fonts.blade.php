@php
    // DomPDF: 日本語グリフ用（storage/fonts に OTF を配置）
    $pdfFontPath = str_replace('\\', '/', storage_path('fonts/NotoSansCJKjp-Regular.otf'));
@endphp
<style>
/* DomPDF は format('truetype') のみ登録。太字用に 400 と 700 の両方を同じファイルで登録しないと h1/th が ? になる */
@font-face {
    font-family: 'jp';
    font-style: normal;
    font-weight: 400;
    src: url('file://{{ $pdfFontPath }}') format('truetype');
}
@font-face {
    font-family: 'jp';
    font-style: normal;
    font-weight: 700;
    src: url('file://{{ $pdfFontPath }}') format('truetype');
}
body, table, th, td, h1, h2, h3, div, span, strong, b {
    font-family: 'jp', sans-serif;
}
</style>
