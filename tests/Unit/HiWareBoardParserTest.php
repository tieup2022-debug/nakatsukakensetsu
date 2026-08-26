<?php

namespace Tests\Unit;

use App\Services\HiWareBoardParser;
use PHPUnit\Framework\TestCase;

class HiWareBoardParserTest extends TestCase
{
    public function test_it_parses_shift_jis_list_rows_and_total_pages(): void
    {
        $html = <<<'HTML'
        <html><head><meta http-equiv="Content-Type" content="text/html; charset=shift_jis"></head><body>
        <ul><li>[ Page: 1/1232 ]</li></ul>
        <table>
          <tr>
            <td><a href="/cgi-bin/board/wb_Threadcont.exe?board/gyomu.ini+g+000000000065203+THREAD++ALL">工事日報：豊浜急傾斜</a>(21) <span>4件のいいね!</span></td>
            <td>中塚 隆太朗</td><td>[最終更新日:'26年 8月25日 18:02]</td>
          </tr>
        </table>
        </body></html>
        HTML;
        $raw = mb_convert_encoding($html, 'SJIS-win', 'UTF-8');

        $result = (new HiWareBoardParser)->parseListPage($raw);

        $this->assertSame(1, $result['page']);
        $this->assertSame(1232, $result['total_pages']);
        $this->assertCount(1, $result['threads']);
        $this->assertSame('000000000065203', $result['threads'][0]['legacy_id']);
        $this->assertSame('工事日報：豊浜急傾斜', $result['threads'][0]['title']);
        $this->assertSame(21, $result['threads'][0]['view_count']);
        $this->assertSame(4, $result['threads'][0]['legacy_like_count']);
        $this->assertSame('2026-08-25 18:02:00', $result['threads'][0]['last_activity_at']);
    }

    public function test_it_parses_javascript_thread_links_with_alternate_argument_order(): void
    {
        $html = <<<'HTML'
        <html><body><table><tr>
          <td><a href="#" onclick="location.href='wb_Threadlist.exe?board/gyomu.ini+g+THREAD+65203+ALL'">工事日報</a></td>
          <td>中塚 隆太朗</td><td>'26年 8月25日 18:02</td>
        </tr></table></body></html>
        HTML;

        $result = (new HiWareBoardParser)->parseListPage($html);

        $this->assertCount(1, $result['threads']);
        $this->assertSame('000000000065203', $result['threads'][0]['legacy_id']);
        $this->assertSame(
            'http://intra.e-nakatsuka.com/cgi-bin/Board/wb_Threadlist.exe?board/gyomu.ini+g+THREAD+65203+ALL',
            $result['threads'][0]['detail_url'],
        );
    }

    public function test_it_parses_parent_reply_body_and_attachment(): void
    {
        $html = <<<'HTML'
        <html><body>
        <table id="parent">
          <tr><th><a href="/cgi-bin/board/wb_Delete.exe?board/gyomu.ini+g+000000000000014+THREAD+t+ALL">削除</a></th></tr>
          <tr><th>日 時</th><td>'01年 4月19日 17:54:39</td></tr>
          <tr><th>タイトル</th><td>中塚建設掲示板について (閲覧回数：18回)</td></tr>
          <tr><th>投稿者</th><td>住吉 秀美さん <a href="mailto:sumiyoshi@example.test">sumiyoshi@example.test</a></td></tr>
          <tr><td><table><tr><td><a href="/cgi-bin/Board/ShowData.exe?x+%74%65%73%74%2E%70%64%66"><img alt="IMAGE"></a></td></tr></table>一行目<br>二行目</td></tr>
          <tr><td><input type="button" value="いいね!"></td></tr>
        </table>
        <table id="reply-wrapper"><tr><td></td><td>
          <table id="reply">
            <tr><th><a href="/cgi-bin/board/wb_Delete.exe?board/gyomu.ini+g+000000000000018+THREAD+t+ALL">削除</a></th></tr>
            <tr><td>タイトル</td><td>Re(1):中塚建設掲示板について</td></tr>
            <tr><td>投稿者</td><td>木村 修(<a href="mailto:osamu@example.test">osamu@example.test</a>)</td></tr>
            <tr><td>日 時</td><td>'01年 4月19日 18:35</td></tr>
            <tr><td>&gt;引用文<br>僕も今日初返信です。</td></tr>
          </table>
        </td></tr></table>
        </body></html>
        HTML;
        $raw = mb_convert_encoding($html, 'SJIS-win', 'UTF-8');

        $result = (new HiWareBoardParser)->parseThreadDetail(
            $raw,
            'http://intra.e-nakatsuka.com/cgi-bin/board/wb_Threadcont.exe?board/gyomu.ini+g+000000000000014+THREAD++ALL'
        );

        $this->assertSame('000000000000014', $result['legacy_id']);
        $this->assertSame('中塚建設掲示板について', $result['title']);
        $this->assertSame("一行目\n二行目", $result['body']);
        $this->assertSame('住吉 秀美', $result['author_name']);
        $this->assertSame('sumiyoshi@example.test', $result['author_email']);
        $this->assertSame(18, $result['view_count']);
        $this->assertSame('test.pdf', $result['attachments'][0]['original_name']);
        $this->assertCount(1, $result['replies']);
        $this->assertSame('000000000000018', $result['replies'][0]['legacy_id']);
        $this->assertSame('木村 修', $result['replies'][0]['author_name']);
        $this->assertSame(">引用文\n僕も今日初返信です。", $result['replies'][0]['body']);
        $this->assertSame('2001-04-19 18:35:00', $result['replies'][0]['created_at']);
    }
}
