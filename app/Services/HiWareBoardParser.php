<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

class HiWareBoardParser
{
    /**
     * @return array{page:int,total_pages:int,threads:array<int, array<string, mixed>>}
     */
    public function parseListPage(string $rawHtml, int $fallbackPage = 1): array
    {
        [$dom, $xpath, $html] = $this->document($rawHtml);
        $page = $fallbackPage;
        $totalPages = 0;
        if (preg_match('/\[\s*Page:\s*(\d+)\/(\d+)\s*\]/i', $html, $match)) {
            $page = (int) $match[1];
            $totalPages = (int) $match[2];
        }

        $threads = [];
        $linkElements = $xpath->query('//a[@href] | //*[@onclick]');
        foreach ($linkElements ?: [] as $linkElement) {
            if (! $linkElement instanceof DOMElement) {
                continue;
            }
            $href = $this->threadUrlFromElement($linkElement);
            $legacyId = $href !== null ? $this->threadLegacyIdFromUrl($href) : null;
            if ($legacyId === null || isset($threads[$legacyId])) {
                continue;
            }

            $row = $this->closest($linkElement, 'tr');
            $cells = $row ? $this->directCells($row) : [];
            $rowText = $row ? $this->flatText($row) : '';
            $title = $this->flatText($linkElement);
            $viewCount = 0;
            $likeCount = 0;
            if (preg_match('/\((\d+)\)/u', $rowText, $match)) {
                $viewCount = (int) $match[1];
            }
            if (preg_match('/(\d+)件のいいね/u', $rowText, $match)) {
                $likeCount = (int) $match[1];
            }

            $threads[$legacyId] = [
                'legacy_id' => $legacyId,
                'title' => $title,
                'author_name' => isset($cells[1]) ? $this->flatText($cells[1]) : '',
                'view_count' => $viewCount,
                'legacy_like_count' => $likeCount,
                'last_activity_at' => isset($cells[2]) ? $this->parseLegacyDate($this->flatText($cells[2])) : null,
                'detail_url' => $this->absoluteUrl($href),
            ];
        }

        return [
            'page' => $page,
            'total_pages' => $totalPages,
            'threads' => array_values($threads),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parseThreadDetail(string $rawHtml, string $sourceUrl): array
    {
        [, $xpath] = $this->document($rawHtml);
        $rootLegacyId = $this->threadLegacyIdFromUrl($sourceUrl);
        if ($rootLegacyId === null) {
            throw new RuntimeException('詳細URLから旧投稿IDを取得できません。');
        }

        $posts = [];
        $deleteLinks = $xpath->query('//a[contains(translate(@href,"WB_DELETE","wb_delete"),"wb_delete.exe")]');
        foreach ($deleteLinks ?: [] as $deleteLink) {
            if (! $deleteLink instanceof DOMElement) {
                continue;
            }
            $legacyId = $this->legacyIdFromUrl((string) $deleteLink->getAttribute('href'), 'wb_Delete.exe');
            $table = $this->closest($deleteLink, 'table');
            if ($legacyId === null || ! $table instanceof DOMElement || isset($posts[$legacyId])) {
                continue;
            }
            $posts[$legacyId] = $this->parsePostTable($table, $legacyId, $legacyId === $rootLegacyId);
        }

        if (! isset($posts[$rootLegacyId])) {
            throw new RuntimeException('詳細ページから親投稿を解析できません。旧HTML構造を確認してください。');
        }

        $root = $posts[$rootLegacyId];
        unset($posts[$rootLegacyId]);
        $root['source_url'] = $sourceUrl;
        $root['replies'] = array_values($posts);

        return $root;
    }

    public function decode(string $raw): string
    {
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');
    }

    public function parseLegacyDate(string $value): ?string
    {
        if (! preg_match("/'?(\d{2})年\s*(\d{1,2})月\s*(\d{1,2})日\s*(\d{1,2}):(\d{2})(?::(\d{2}))?/u", $value, $match)) {
            return null;
        }

        return sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            2000 + (int) $match[1],
            (int) $match[2],
            (int) $match[3],
            (int) $match[4],
            (int) $match[5],
            isset($match[6]) ? (int) $match[6] : 0,
        );
    }

    private function parsePostTable(DOMElement $table, string $legacyId, bool $isRoot): array
    {
        $fields = [];
        $bodyCandidates = [];
        foreach ($this->directRows($table) as $row) {
            $cells = $this->directCells($row);
            if (count($cells) >= 2) {
                $label = trim($this->flatText($cells[0]));
                if (in_array($label, ['日 時', '日時', 'タイトル', '投稿者'], true)) {
                    $fields[str_replace(' ', '', $label)] = $cells[1];

                    continue;
                }
            }

            if (count($cells) === 1 && strtolower($cells[0]->tagName) === 'td') {
                $text = $this->bodyText($cells[0]);
                if ($text !== '' && $text !== 'いいね!' && $text !== 'いいね!済') {
                    $bodyCandidates[] = ['node' => $cells[0], 'text' => $text];
                }
            }
        }

        usort($bodyCandidates, fn (array $a, array $b): int => mb_strlen($b['text']) <=> mb_strlen($a['text']));
        $bodyCell = $bodyCandidates[0]['node'] ?? null;
        $body = $bodyCandidates[0]['text'] ?? '';
        $titleText = isset($fields['タイトル']) ? $this->flatText($fields['タイトル']) : '';
        $viewCount = 0;
        if ($isRoot && preg_match('/\(閲覧回数[：:]\s*(\d+)回\)/u', $titleText, $match)) {
            $viewCount = (int) $match[1];
            $titleText = trim((string) preg_replace('/\s*\(閲覧回数[：:]\s*\d+回\)\s*/u', '', $titleText));
        }

        [$authorName, $authorEmail] = $this->parseAuthor($fields['投稿者'] ?? null, $isRoot);
        $attachments = [];
        if ($bodyCell instanceof DOMElement) {
            $bodyXPath = new DOMXPath($bodyCell->ownerDocument);
            $links = $bodyXPath->query('.//a[contains(translate(@href,"SHOWDATA","showdata"),"showdata.exe")]', $bodyCell);
            $index = 0;
            foreach ($links ?: [] as $link) {
                if (! $link instanceof DOMElement) {
                    continue;
                }
                $index++;
                $href = html_entity_decode((string) $link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $attachments[] = [
                    'index' => $index,
                    'source_url' => $this->absoluteUrl($href),
                    'original_name' => $this->attachmentNameFromUrl($href, $index),
                ];
            }
        }

        return [
            'legacy_id' => $legacyId,
            'title' => $titleText,
            'body' => $body,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'created_at' => isset($fields['日時']) ? $this->parseLegacyDate($this->flatText($fields['日時'])) : null,
            'view_count' => $viewCount,
            'legacy_like_count' => 0,
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array{0:string,1:?string}
     */
    private function parseAuthor(?DOMElement $cell, bool $isRoot): array
    {
        if (! $cell) {
            return ['', null];
        }
        $email = null;
        foreach ($cell->getElementsByTagName('a') as $link) {
            $href = (string) $link->getAttribute('href');
            if (str_starts_with(strtolower($href), 'mailto:')) {
                $email = trim(substr($href, 7));
                break;
            }
        }

        $text = $this->flatText($cell);
        if ($isRoot && preg_match('/^(.*?)さん(?:\s|$)/u', $text, $match)) {
            return [trim($match[1]), $email ?: null];
        }
        if (preg_match('/^(.*?)\s*\(/u', $text, $match)) {
            return [trim($match[1]), $email ?: null];
        }
        if ($email !== null) {
            $text = trim(str_replace($email, '', $text), " \t\n\r\0\x0B()さん");
        }

        return [$text, $email ?: null];
    }

    /**
     * @return array{0:DOMDocument,1:DOMXPath,2:string}
     */
    private function document(string $rawHtml): array
    {
        $html = $this->decode($rawHtml);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$dom, new DOMXPath($dom), $html];
    }

    private function legacyIdFromUrl(string $url, string $program): ?string
    {
        $pattern = '~'.preg_quote($program, '~').'\?[^#]*?\+g\+(\d{1,15})\+~i';

        return preg_match($pattern, $url, $match) ? str_pad($match[1], 15, '0', STR_PAD_LEFT) : null;
    }

    private function threadLegacyIdFromUrl(string $url): ?string
    {
        $url = rawurldecode(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $programPattern = 'wb_(?:Threadcont|ThreadContent|Threadlist)\.exe';
        $patterns = [
            '~'.$programPattern.'\?[^#]*?\+g\+(\d{1,15})\+~i',
            '~'.$programPattern.'\?[^#]*?\+g\+THREAD\+(\d{1,15})\+~i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match)) {
                return str_pad($match[1], 15, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    private function threadUrlFromElement(DOMElement $element): ?string
    {
        foreach (['href', 'onclick'] as $attribute) {
            if (! $element->hasAttribute($attribute)) {
                continue;
            }
            $value = html_entity_decode((string) $element->getAttribute($attribute), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! preg_match('~((?:https?://[^\s\'"<>]+)?(?:/?cgi-bin/(?:board/)?)?(?:\./)?wb_(?:Threadcont|ThreadContent|Threadlist)\.exe\?[^\s\'"<>)]+)~i', $value, $match)) {
                continue;
            }

            $url = $match[1];
            if (preg_match('~^(?:\./)?wb_~i', $url)) {
                $url = '/cgi-bin/Board/'.preg_replace('~^\./~', '', $url);
            }

            return $this->absoluteUrl($url);
        }

        return null;
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        return 'http://intra.e-nakatsuka.com'.(str_starts_with($url, '/') ? $url : '/'.$url);
    }

    private function attachmentNameFromUrl(string $url, int $index): string
    {
        $tail = strrchr($url, '+');
        $candidate = $tail !== false ? urldecode(substr($tail, 1)) : '';
        $candidate = $this->decode($candidate);
        $candidate = basename(str_replace('\\', '/', trim($candidate)));

        return $candidate !== '' ? $candidate : sprintf('attachment-%03d', $index);
    }

    private function closest(DOMNode $node, string $tagName): ?DOMElement
    {
        $current = $node;
        while ($current) {
            if ($current instanceof DOMElement && strtolower($current->tagName) === strtolower($tagName)) {
                return $current;
            }
            $current = $current->parentNode;
        }

        return null;
    }

    /** @return array<int, DOMElement> */
    private function directRows(DOMElement $table): array
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if (strtolower($child->tagName) === 'tr') {
                $rows[] = $child;

                continue;
            }
            if (in_array(strtolower($child->tagName), ['tbody', 'thead', 'tfoot'], true)) {
                foreach ($child->childNodes as $row) {
                    if ($row instanceof DOMElement && strtolower($row->tagName) === 'tr') {
                        $rows[] = $row;
                    }
                }
            }
        }

        return $rows;
    }

    /** @return array<int, DOMElement> */
    private function directCells(DOMElement $row): array
    {
        $cells = [];
        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        return $cells;
    }

    private function flatText(DOMNode $node): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function bodyText(DOMNode $node): string
    {
        $text = $this->bodyTextRecursive($node);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function bodyTextRecursive(DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $result .= html_entity_decode($child->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['table', 'script', 'style', 'button', 'input', 'select', 'option', 'img'], true)) {
                continue;
            }
            if ($tag === 'br') {
                $result .= "\n";

                continue;
            }
            $result .= $this->bodyTextRecursive($child);
            if (in_array($tag, ['p', 'div', 'li', 'blockquote'], true)) {
                $result .= "\n";
            }
        }

        return $result;
    }
}
