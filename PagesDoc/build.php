<?php

/*
 * PagesDoc: turns the project's README.md and docs/*.md into linked HTML pages in Diwan's look.
 *
 *   php PagesDoc/build.php            → PagesDoc/site/index.html
 *
 * The home page is README.md (what the system is), each docs/*.md a guide, and PagesDoc/pages/*.md
 * pages written for this site only. Uses the project's own league/commonmark (vendor/), so
 * `composer install` must have run. Settings (groups, order, icons, descriptions) are in PagesDoc/config.php.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final class PagesDoc
{
    /** @var array<int, array<string, mixed>> guides in display order */
    private array $pages = [];

    /** @var array<string, int> file name => index in */
    private array $byFile = [];

    /** README.md, the home page */
    private ?array $home = null;

    /** @var array<string, MarkdownConverter> 'docs' escapes raw HTML; 'site' (PagesDoc's own pages) keeps it */
    private array $markdown = [];

    private string $version;

    public function __construct(private array $config)
    {
        foreach (['docs' => 'escape', 'site' => 'allow'] as $kind => $html) {
            $environment = new Environment(['html_input' => $html, 'allow_unsafe_links' => false]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
            $this->markdown[$kind] = new MarkdownConverter($environment);
        }
        $this->version = trim((string) @file_get_contents(__DIR__.'/../VERSION')) ?: '';
    }

    public function run(): void
    {
        $this->collect();
        foreach ($this->pages as $i => $page) {
            $this->pages[$i] = $this->convert($page);
        }
        if ($this->home) {
            $this->home = $this->convert($this->home);
        }

        $out = rtrim($this->config['output'], '/');
        if (! is_dir($out.'/assets') && ! mkdir($out.'/assets', 0777, true)) {
            throw new RuntimeException("Cannot create $out/assets");
        }
        foreach (glob($out.'/*.html') ?: [] as $old) {
            unlink($old);
        }
        foreach (['style.css', 'app.js'] as $asset) {
            copy(__DIR__.'/src/'.$asset, $out.'/assets/'.$asset);
        }
        file_put_contents($out.'/assets/search-index.js', $this->searchIndex());

        foreach ($this->pages as $i => $page) {
            file_put_contents($out.'/'.$page['slug'].'.html', $this->docPage($i));
        }
        file_put_contents($out.'/index.html', $this->homePage());

        $sections = array_sum(array_map(fn ($p) => $p['stats']['sections'], $this->pages));
        fwrite(STDOUT, sprintf("PagesDoc: home + %d pages, %d sections → %s/index.html\n", count($this->pages), $sections, realpath($out)));
    }

    // ------------------------------------------------------------------ reading the sources

    private function collect(): void
    {
        $sources = [];
        foreach (glob(rtrim($this->config['source'], '/').'/*.md') ?: [] as $path) {
            $sources[basename($path)] = ['path' => $path, 'repo_path' => 'docs/'.basename($path), 'kind' => 'docs'];
        }
        if (! $sources) {
            throw new RuntimeException('No .md files in '.$this->config['source']);
        }
        $extra = $this->config['extra'] ?? null;
        foreach ($extra && is_dir($extra) ? (glob(rtrim($extra, '/').'/*.md') ?: []) : [] as $path) {
            $sources[basename($path)] ??= ['path' => $path, 'repo_path' => null, 'kind' => 'site'];
        }
        $order = array_flip(array_keys($this->config['pages']));
        uksort($sources, fn ($a, $b) => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        foreach ($sources as $file => $source) {
            $this->byFile[$file] = count($this->pages);
            $this->pages[] = $this->page($file, $source, $this->config['pages'][$file] ?? []);
        }

        $home = $this->config['home'] ?? null;
        if ($home && is_file($home['source'])) {
            $file = basename($home['source']);
            // README's links are relative to the repository root, not to docs/.
            $this->home = ['is_home' => true, 'base' => ''] + $this->page($file, ['path' => $home['source'], 'repo_path' => $file, 'kind' => 'docs'], $home + ['slug' => 'index', 'icon' => 'home']);
        }
    }

    private function page(string $file, array $source, array $meta): array
    {
        $markdown = strtr((string) file_get_contents($source['path']), [
            '{{repo}}' => $this->config['repo'],
            '{{branch}}' => $this->config['branch'],
            '{{version}}' => $this->version,
        ]);
        $title = preg_match('/^#\s+(.+?)\s*#*\s*$/mu', $markdown, $m)
            ? html_entity_decode(trim(strip_tags($this->markdown['docs']->convert($m[1])->getContent())), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : pathinfo($file, PATHINFO_FILENAME);
        $group = $meta['group'] ?? 'other';

        return [
            'file' => $file,
            'kind' => $source['kind'],
            'repo_path' => $source['repo_path'],
            'base' => 'docs/',
            'is_home' => false,
            'markdown' => $markdown,
            'title' => $title,
            'slug' => $meta['slug'] ?? strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($file, PATHINFO_FILENAME))),
            'group' => isset($this->config['groups'][$group]) ? $group : 'other',
            'nav' => $meta['nav'] ?? null,
            'icon' => $meta['icon'] ?? 'book',
            'audience' => $meta['audience'] ?? null,
            'summary' => $meta['summary'] ?? null,
            'drop' => $meta['drop'] ?? [],
            'updated' => $this->updatedAt($source['path']),
        ];
    }

    /** Day of the last commit that touched the file (in the committer's time zone), else its modification day. */
    private function updatedAt(string $path): string
    {
        $dir = escapeshellarg(dirname($path));
        $date = trim((string) @shell_exec("git -C $dir log -1 --format=%cs -- ".escapeshellarg(basename($path)).' 2>/dev/null'));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d', (int) filemtime($path));
    }

    private function convert(array $page): array
    {
        $html = $this->markdown[$page['kind']]->convert($page['markdown'])->getContent();

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"?><div id="pagesdoc-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $dom->encoding = 'UTF-8';
        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="pagesdoc-root"]')->item(0);
        $this->replaceAliases($xpath, $root);

        // The first heading is the page title (shown in the hero), and an opening paragraph is its lede,
        // unless it introduces what follows (ends with a colon).
        $page['lede'] = null;
        $first = $this->firstElement($root);
        if ($first && $first->nodeName === 'h1') {
            $root->removeChild($first);
            $first = $this->firstElement($root);
            if ($first && $first->nodeName === 'p' && ! preg_match('/[:：]\s*$/u', $first->textContent)) {
                $page['lede'] = $this->inner($dom, $first);
                $root->removeChild($first);
            }
        }

        $this->demoteExtraTitles($dom, $root);
        $this->dropSections($root, array_merge(['المحتويات'], $page['drop']));

        $page['toc'] = $this->headings($dom, $xpath, $root);
        $this->links($dom, $xpath, $root, $page['base']);
        $page['search'] = $this->sections($root, $page);
        $cards = $page['is_home'] ? $this->homeCards($xpath, $root) : [];
        foreach (iterator_to_array($xpath->query('.//*[@data-text]', $root)) as $h) {
            $h->removeAttribute('data-text');
        }
        $this->blocks($dom, $xpath, $root);

        $text = trim(preg_replace('/\s+/u', ' ', $root->textContent));
        $page['stats'] = [
            'sections' => count(array_filter($page['toc'], fn ($h) => $h['level'] === 2)),
            'tables' => $xpath->query('.//table', $root)->length,
            'code' => $xpath->query('.//pre', $root)->length,
            'minutes' => max(1, (int) ceil(count(preg_split('/\s+/u', $text)) / 180)),
        ];

        $body = '';
        foreach ($root->childNodes as $node) {
            $body .= $dom->saveHTML($node);
        }
        $body = preg_replace_callback('/%%ICON:([a-z-]+)%%/', fn ($m) => $this->icon($m[1]), strtr($body, $cards));
        $page['body'] = $this->highlight($body);

        return $page;
    }

    private function firstElement(DOMNode $parent): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }

    /** @return DOMElement[] direct element children, as a stable list */
    private function children(DOMNode $parent): array
    {
        return array_values(array_filter(iterator_to_array($parent->childNodes), fn ($n) => $n instanceof DOMElement));
    }

    private function inner(DOMDocument $dom, DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return trim($html);
    }

    private function rename(DOMDocument $dom, DOMElement $el, string $tag): DOMElement
    {
        $new = $dom->createElement($tag);
        foreach (iterator_to_array($el->attributes) as $attr) {
            $new->setAttribute($attr->name, $attr->value);
        }
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }

    /** The repositories in `repo_aliases` become `repo` wherever the docs name them: links, shown addresses, commands. */
    private function replaceAliases(DOMXPath $xpath, DOMElement $root): void
    {
        $aliases = array_map(fn ($r) => preg_quote($r, '#'), $this->config['repo_aliases'] ?? []);
        if (! $aliases) {
            return;
        }
        $pattern = '#(?<=github\.com/|githubusercontent\.com/)(?:'.implode('|', $aliases).')(?![\w-])#i';
        foreach (iterator_to_array($xpath->query('.//a[@href]', $root)) as $a) {
            $a->setAttribute('href', preg_replace($pattern, $this->config['repo'], $a->getAttribute('href')));
        }
        foreach (iterator_to_array($xpath->query('.//text()', $root)) as $text) {
            $text->data = preg_replace($pattern, $this->config['repo'], $text->data);
        }
    }

    /** A second top-level title (API.md has «Webhooks») becomes a section, its headings one level down. */
    private function demoteExtraTitles(DOMDocument $dom, DOMElement $root): void
    {
        $shift = false;
        foreach ($this->children($root) as $el) {
            if ($el->nodeName === 'h1') {
                $this->rename($dom, $el, 'h2');
                $shift = true;
            } elseif ($shift && in_array($el->nodeName, ['h2', 'h3', 'h4', 'h5'], true)) {
                $this->rename($dom, $el, 'h'.((int) $el->nodeName[1] + 1));
            }
        }
    }

    /** Sections the site replaces: a hand-written «المحتويات» (the page has its own), README's list of guides... */
    private function dropSections(DOMElement $root, array $titles): void
    {
        $dropping = false;
        foreach ($this->children($root) as $el) {
            if ($el->nodeName === 'h2') {
                $dropping = in_array(trim($el->textContent), $titles, true);
            }
            if ($dropping) {
                $root->removeChild($el);
            }
        }
        // Section headings carry their own divider: a rule right before one (or at the end) is redundant.
        foreach ($this->children($root) as $el) {
            $next = $el->nextSibling;
            while ($next && ! $next instanceof DOMElement) {
                $next = $next->nextSibling;
            }
            if ($el->nodeName === 'hr' && (! $next || $next->nodeName === 'h2')) {
                $root->removeChild($el);
            }
        }
    }

    /** Ids like GitHub's (the docs link to them), number badges for steps, and the table of contents. */
    private function headings(DOMDocument $dom, DOMXPath $xpath, DOMElement $root): array
    {
        $toc = [];
        $used = [];
        foreach (iterator_to_array($xpath->query('.//h2|.//h3|.//h4', $root)) as $h) {
            $text = trim(preg_replace('/\s+/u', ' ', $h->textContent));
            $id = $base = $this->slug($text);
            for ($n = 1; isset($used[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
            $h->setAttribute('data-text', $text); // for sections() and homeCards(), before the badge and anchor change textContent
            $level = (int) $h->nodeName[1];

            $num = null;
            $label = $text;
            $first = $h->firstChild;
            if ($level === 2 && $first instanceof DOMText
                && preg_match('/^\s*(?:(الخطوة)\s+(\d+)\s*[:：]|(\d+)\.)\s*/u', $first->data, $m)) {
                $num = $m[2] !== '' ? $m[2] : $m[3];
                $first->data = mb_substr($first->data, mb_strlen($m[0]));
                $label = trim(mb_substr($text, mb_strlen(rtrim($m[0]))));
                $badge = $dom->createElement('span', $num);
                $badge->setAttribute('class', 'h-num');
                if ($m[1] !== '') {
                    $badge->setAttribute('data-label', $m[1]);
                }
                $h->insertBefore($badge, $first);
                $h->setAttribute('class', 'numbered');
            }

            $anchor = $dom->createElement('a', '#');
            $anchor->setAttribute('class', 'anchor');
            $anchor->setAttribute('href', '#'.$id);
            $anchor->setAttribute('aria-label', 'رابط مباشر لهذا القسم');
            $h->appendChild($anchor);

            if ($level <= 3) {
                $toc[] = ['level' => $level, 'id' => $id, 'text' => $text, 'label' => $label, 'num' => $num];
            }
        }

        return $toc;
    }

    private function slug(string $text): string
    {
        $slug = mb_strtolower(trim($text), 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{M}\p{Nd}\p{Nl}\s_-]+/u', '', $slug);

        return preg_replace('/\s/u', '-', $slug);
    }

    /** One search entry per section (intro, h2, h3), with its plain text. */
    private function sections(DOMElement $root, array $page): array
    {
        $entries = [];
        $current = ['id' => '', 'h' => $page['title'], 't' => ''];
        foreach ($this->children($root) as $el) {
            if (in_array($el->nodeName, ['h2', 'h3'], true)) {
                $entries[] = $current;
                $current = ['id' => $el->getAttribute('id'), 'h' => $el->getAttribute('data-text'), 't' => ''];
            } else {
                $current['t'] .= ' '.$el->textContent;
            }
        }
        $entries[] = $current;
        if ($page['lede']) {
            $entries[0]['t'] = strip_tags($page['lede']).' '.$entries[0]['t'];
        }

        return array_values(array_filter(array_map(function ($e) {
            $e['t'] = mb_substr(trim(preg_replace('/\s+/u', ' ', $e['t'])), 0, 700);

            return $e;
        }, $entries), fn ($e) => $e['id'] === '' ? $e['t'] !== '' : true));
    }

    /** Links between the sources become links between pages; other repository files open on GitHub. */
    private function links(DOMDocument $dom, DOMXPath $xpath, DOMElement $root, string $base): void
    {
        $blob = 'https://github.com/'.$this->config['repo'].'/blob/'.$this->config['branch'].'/';
        $homeFile = $this->home['file'] ?? null;
        foreach (iterator_to_array($xpath->query('.//a[@href]', $root)) as $a) {
            $href = $a->getAttribute('href');
            if ($a->getAttribute('class') === 'anchor' || str_starts_with($href, '#')) {
                continue;
            }
            if (preg_match('#^(https?:|mailto:)#i', $href)) {
                $this->external($a);

                continue;
            }
            [$path, $fragment] = array_pad(explode('#', $href, 2), 2, null);
            $path = rawurldecode($path);
            $file = basename($path);
            $target = $file === $homeFile ? ['slug' => 'index', 'title' => $this->config['home']['nav'] ?? 'الرئيسية']
                : (! str_starts_with($path, '../') && isset($this->byFile[$file]) ? $this->pages[$this->byFile[$file]] : null);
            if ($target) {
                $a->setAttribute('href', $target['slug'].'.html'.($fragment !== null ? '#'.$fragment : ''));
                // A link that only shows the file name reads better as the page's title.
                $shown = trim($a->textContent);
                if ($shown === $file || str_ends_with($shown, '/'.$file)) {
                    $a->nodeValue = '';
                    $a->appendChild($dom->createTextNode($target['title']));
                }

                continue;
            }
            // Anything else in the repository (deploy/nginx.conf, .env.production.example...) opens on GitHub.
            $inRepo = str_starts_with($path, '../') ? substr($path, 3) : $base.$path;
            $a->setAttribute('href', $blob.$inRepo.($fragment !== null ? '#'.$fragment : ''));
            $this->external($a);
        }
    }

    private function external(DOMElement $a): void
    {
        $a->setAttribute('target', '_blank');
        $a->setAttribute('rel', 'noopener');
        $a->setAttribute('class', trim($a->getAttribute('class').' ext'));
    }

    /** README's install and modules tables shown as cards; returns placeholder => HTML. */
    private function homeCards(DOMXPath $xpath, DOMElement $root): array
    {
        $home = $this->config['home'];
        $cards = [];

        if ($table = $this->sectionTable($root, $home['install'] ?? '')) {
            // Each row links its guide, and maybe a file to download directly (Diwan-Setup.bat).
            $downloads = [];
            foreach (iterator_to_array($xpath->query('.//tbody/tr', $table)) as $tr) {
                $guide = $download = null;
                foreach (iterator_to_array($xpath->query('.//a[@href]', $tr)) as $a) {
                    $href = $a->getAttribute('href');
                    if (preg_match('#^[\w-]+\.html$#', $href)) {
                        $guide = $href;
                    } elseif (str_contains($href, '/releases/')) {
                        $download = ['url' => $href, 'name' => trim($a->textContent)];
                    }
                }
                if ($guide && $download) {
                    $downloads[$guide] = $download;
                }
            }
            $cards[$this->placeholder($table, 'install')] = $this->installCards($downloads);
        }

        if ($table = $this->sectionTable($root, $home['modules'] ?? '')) {
            $modules = [];
            foreach (iterator_to_array($xpath->query('.//tbody/tr', $table)) as $tr) {
                $cells = $this->children($tr);
                if (count($cells) >= 2) {
                    $modules[] = [trim($cells[0]->textContent), $this->splitItems(trim(preg_replace('/\s+/u', ' ', $cells[1]->textContent)))];
                }
            }
            $cards[$this->placeholder($table, 'modules')] = $this->moduleCards($modules);
        }

        return $cards;
    }

    /** The first table in the section whose heading reads $title. */
    private function sectionTable(DOMElement $root, string $title): ?DOMElement
    {
        $inside = false;
        foreach ($this->children($root) as $el) {
            if ($el->nodeName === 'h2') {
                if ($inside) {
                    break;
                }
                $inside = $el->getAttribute('data-text') === $title;
            } elseif ($inside && $el->nodeName === 'table') {
                return $el;
            }
        }

        return null;
    }

    private function placeholder(DOMElement $el, string $name): string
    {
        $div = $el->ownerDocument->createElement('div');
        $div->setAttribute('data-block', $name);
        $el->parentNode->replaceChild($div, $el);

        return '<div data-block="'.$name.'"></div>';
    }

    /** «أ، ب (ج، د)، هـ» → [أ, ب (ج، د), هـ]: commas inside parentheses stay. */
    private function splitItems(string $text): array
    {
        $items = [];
        $depth = 0;
        $current = '';
        foreach (mb_str_split($text) as $ch) {
            $depth += $ch === '(' ? 1 : ($ch === ')' && $depth > 0 ? -1 : 0);
            if ($ch === '،' && $depth === 0) {
                $items[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $ch;
        }
        $items[] = trim($current);

        return array_values(array_filter($items, fn ($i) => $i !== ''));
    }

    private function installCards(array $downloads): string
    {
        $html = '<div class="qs-grid">';
        foreach ($this->config['quick_start'] as $q) {
            if (! isset($this->byFile[$q['page']])) {
                continue;
            }
            $url = $this->pages[$this->byFile[$q['page']]]['slug'].'.html';
            $download = $downloads[$url] ?? null;
            $html .= '<div class="qs-card">'
                .'<span class="qs-icon">'.$this->icon($q['icon']).'</span>'
                .'<a class="qs-title" href="'.$url.'">'.$this->e($q['title']).'</a>'
                .'<span class="qs-how">'.$this->e($q['how']).'</span>'
                .'<span class="qs-foot"><span class="tag">'.$this->icon('clock').$this->e($q['time']).'</span>'
                .'<span class="tag level-'.($q['level'] === 'سهل' ? 'easy' : 'mid').'">'.$this->e($q['level']).'</span>'
                .($download ? '<a class="qs-dl" href="'.$this->e($download['url']).'" title="تنزيل '.$this->e($download['name']).' مباشرة">'.$this->icon('download').'تنزيل مباشر</a>' : '')
                .'<span class="qs-go">'.$this->icon('arrow-left').'</span></span></div>';
        }

        return $html.'</div>';
    }

    private function moduleCards(array $modules): string
    {
        $icons = $this->config['home']['module_icons'] ?? [];
        $html = '<div class="modules">';
        foreach ($modules as [$title, $items]) {
            $html .= '<div class="module-card"><div class="module-head">'
                .'<span class="module-icon">'.$this->icon($icons[$title] ?? 'blocks').'</span>'
                .'<h3 class="module-title">'.$this->e($title).'</h3>'
                .'<span class="module-count">'.$this->e($this->plural(count($items), ['ميزة واحدة', 'ميزتان', '%d ميزات', '%d ميزة'])).'</span></div>'
                .'<ul class="module-list">'.implode('', array_map(fn ($item) => '<li>'.$this->e($item).'</li>', $items)).'</ul></div>';
        }

        return $html.'</div>';
    }

    private function blocks(DOMDocument $dom, DOMXPath $xpath, DOMElement $root): void
    {
        foreach (iterator_to_array($xpath->query('.//table', $root)) as $table) {
            // Three or more columns become stacked cards on phones: each cell is labelled with its column.
            $heads = array_map(fn ($th) => trim($th->textContent), iterator_to_array($xpath->query('.//thead//th', $table)));
            $stack = count($heads) >= 3;
            if ($stack) {
                foreach (iterator_to_array($xpath->query('.//tbody/tr', $table)) as $tr) {
                    foreach ($this->children($tr) as $i => $td) {
                        if (($heads[$i] ?? '') !== '') {
                            $td->setAttribute('data-label', $heads[$i]);
                        }
                    }
                }
            }
            $box = $dom->createElement('div');
            $box->setAttribute('class', $stack ? 'tablebox stack' : 'tablebox');
            $table->parentNode->replaceChild($box, $table);
            $box->appendChild($table);
        }

        foreach (iterator_to_array($xpath->query('.//blockquote', $root)) as $quote) {
            $lead = $xpath->query('.//strong', $quote)->item(0)?->textContent ?? '';
            $warn = preg_match('/تنبيه|انتبه|لا تستعمل|لا تغيّر|أهم سر|قبل الإنتاج/u', $lead.' '.mb_substr($quote->textContent, 0, 40));
            $quote->setAttribute('class', 'callout '.($warn ? 'warn' : 'note'));
        }

        $languages = ['bash' => 'Bash', 'sh' => 'Shell', 'powershell' => 'PowerShell', 'json' => 'JSON', 'php' => 'PHP',
            'dotenv' => '.env', 'cron' => 'cron', 'ini' => 'INI', 'yaml' => 'YAML', 'js' => 'JavaScript', 'sql' => 'SQL',
            'markdown' => 'Markdown', 'md' => 'Markdown', 'text' => 'نص'];
        foreach (iterator_to_array($xpath->query('.//pre', $root)) as $pre) {
            $code = $xpath->query('./code', $pre)->item(0);
            $lang = $code && preg_match('/language-([\w-]+)/', $code->getAttribute('class'), $m) ? strtolower($m[1]) : '';
            if ($code && in_array($lang, ['', 'bash', 'sh', 'powershell', 'dotenv', 'cron', 'ini'], true)) {
                $code->setAttribute('data-hl', $lang === '' ? 'plain' : $lang);
            }

            $figure = $dom->createElement('div');
            $figure->setAttribute('class', 'code');
            $bar = $dom->createElement('div');
            $bar->setAttribute('class', 'code-bar');
            $label = $dom->createElement('span', $languages[$lang] ?? ($lang !== '' ? $lang : 'مثال'));
            $label->setAttribute('class', 'code-lang');
            $button = $dom->createElement('button');
            $button->setAttribute('type', 'button');
            $button->setAttribute('class', 'copy');
            $button->setAttribute('data-copy', '');
            $button->setAttribute('aria-label', 'نسخ');
            $button->appendChild($dom->createTextNode('%%ICON:copy%%%%ICON:check%%'));
            $button->appendChild($dom->createElement('span', 'نسخ'));
            $bar->appendChild($label);
            $bar->appendChild($button);

            $pre->parentNode->replaceChild($figure, $pre);
            $figure->appendChild($bar);
            $figure->appendChild($pre);
            $pre->setAttribute('dir', 'ltr');
        }
    }

    /** Light touch: comments and .env keys in shell, PowerShell and settings examples. */
    private function highlight(string $html): string
    {
        return preg_replace_callback('#(<code[^>]*data-hl="([a-z]+)"[^>]*>)(.*?)(</code>)#s', function ($m) {
            $lines = array_map(function ($line) use ($m) {
                if (preg_match('/^(\s*)(#.*)$/', $line, $c)) {
                    return $c[1].'<span class="c">'.$c[2].'</span>';
                }
                if (in_array($m[2], ['plain', 'dotenv', 'ini'], true) && preg_match('/^([A-Z][A-Z0-9_]*)(=)(.*)$/', $line, $k)) {
                    $line = '<span class="k">'.$k[1].'</span>'.$k[2].$k[3];
                }
                if (preg_match('/^(.*?\S)(\s{2,})(#\s.*)$/', $line, $c)) {
                    return $c[1].$c[2].'<span class="c">'.$c[3].'</span>';
                }

                return $line;
            }, explode("\n", $m[3]));

            return $m[1].implode("\n", $lines).$m[4];
        }, $html);
    }

    // ------------------------------------------------------------------ output

    private function searchIndex(): string
    {
        $index = [];
        foreach ($this->home['search'] ?? [] as $entry) {
            $index[] = ['p' => $this->home['title'], 'g' => 'الرئيسية',
                'u' => 'index.html'.($entry['id'] !== '' ? '#'.$entry['id'] : ''), 'h' => $entry['h'], 't' => $entry['t']];
        }
        foreach ($this->pages as $page) {
            foreach ($page['search'] as $entry) {
                $index[] = ['p' => $page['title'], 'g' => $this->config['groups'][$page['group']]['title'],
                    'u' => $page['slug'].'.html'.($entry['id'] !== '' ? '#'.$entry['id'] : ''), 'h' => $entry['h'], 't' => $entry['t']];
            }
        }

        return '// Generated by PagesDoc/build.php from README.md and docs/*.md; do not edit.'."\n"
            .'window.PAGESDOC_INDEX = '.json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n";
    }

    private function docPage(int $i): string
    {
        $page = $this->pages[$i];
        $group = $this->config['groups'][$page['group']];
        $prev = $this->pages[$i - 1] ?? ($this->home ? ['slug' => 'index', 'title' => $this->config['home']['nav'] ?? 'الرئيسية'] : null);
        $next = $this->pages[$i + 1] ?? null;
        $lede = $page['lede'] ?? ($page['summary'] !== null ? $this->e($page['summary']) : '');

        $meta = [];
        if ($page['audience']) {
            $meta[] = $this->chip('users', 'لـ '.$page['audience']);
        }
        $meta[] = $this->chip('clock', $this->plural($page['stats']['minutes'], ['دقيقة قراءة', 'دقيقتا قراءة', '%d دقائق قراءة', '%d دقيقة قراءة']));
        if ($page['stats']['sections']) {
            $meta[] = $this->chip('list', $this->plural($page['stats']['sections'], ['قسم واحد', 'قسمان', '%d أقسام', '%d قسماً']));
        }
        $meta[] = $this->chip('calendar', 'آخر تحديث '.$this->date($page['updated']));

        $toc = $this->tocList($page['toc']);
        $content = <<<HTML
<section class="hero hero-doc">
  {$this->watermark()}
  <div class="wrap">
    <nav class="crumbs" aria-label="مسار التنقل"><a href="index.html">الرئيسية</a><span aria-hidden="true">‹</span><span>{$this->e($group['title'])}</span></nav>
    <div class="hero-row">
      <div class="hero-icon">{$this->icon($page['icon'])}</div>
      <div class="hero-text">
        <h1>{$this->e($page['title'])}</h1>
        <p class="lede">{$lede}</p>
      </div>
    </div>
    <div class="meta">{$this->join($meta)}</div>
  </div>
</section>
<div class="layout wrap">
  {$this->sideNav($page['file'])}
  <main class="content" id="main">
    {$this->mobileToc($toc)}
    <article class="prose">
{$page['body']}
    </article>
    {$this->sourceRow($page)}
    {$this->pager($prev, $next)}
  </main>
  {$this->tocAside($toc)}
</div>
HTML;

        return $this->layout($page['title'].' · '.$this->config['site']['name'], $page['summary'] ?? $page['title'], $content);
    }

    private function homePage(): string
    {
        $site = $this->config['site'];
        $home = $this->home;
        $title = $home['title'] ?? $site['title'];
        $lede = $home['lede'] ?? $this->e($site['lede']);

        $total = fn (string $key) => array_sum(array_map(fn ($p) => $p['stats'][$key], $this->pages));
        $stats = [
            [count($this->pages), ['دليل', 'دليلان', 'أدلة', 'دليلاً']],
            [$total('sections'), ['قسم مفصّل', 'قسمان مفصّلان', 'أقسام مفصّلة', 'قسماً مفصّلاً']],
            [$total('tables'), ['جدول مرجعي', 'جدولان مرجعيان', 'جداول مرجعية', 'جدولاً مرجعياً']],
            [$total('code'), ['أمر جاهز للنسخ', 'أمران جاهزان للنسخ', 'أوامر جاهزة للنسخ', 'أمراً جاهزاً للنسخ']],
        ];
        $statsHtml = $this->join(array_map(fn ($s) => '<div class="stat"><strong>'.$s[0].'</strong><span>'.$this->e($this->plural($s[0], $s[1], false)).'</span></div>', $stats));

        // After README: every guide by group, then how to send suggestions.
        $guides = '';
        foreach ($this->config['groups'] as $key => $group) {
            $pages = array_filter($this->pages, fn ($p) => $p['group'] === $key);
            if (! $pages) {
                continue;
            }
            $cards = '';
            foreach ($pages as $p) {
                $cards .= '<a class="doc-card" href="'.$p['slug'].'.html">'
                    .'<span class="doc-top"><span class="doc-icon">'.$this->icon($p['icon']).'</span>'
                    .($p['audience'] ? '<span class="doc-aud">'.$this->e($p['audience']).'</span>' : '').'</span>'
                    .'<span class="doc-title">'.$this->e($p['title']).'</span>'
                    .'<span class="doc-sum">'.$this->e($p['summary'] ?? strip_tags($p['lede'] ?? '')).'</span>'
                    .'<span class="doc-foot"><span>'.$this->icon('clock').$this->plural($p['stats']['minutes'], ['دقيقة', 'دقيقتان', '%d دقائق', '%d دقيقة']).'</span>'
                    .($p['stats']['sections'] ? '<span>'.$this->icon('list').$this->plural($p['stats']['sections'], ['قسم', 'قسمان', '%d أقسام', '%d قسماً']).'</span>' : '')
                    .'<span class="doc-go">'.$this->icon('arrow-left').'</span></span></a>';
            }
            $groupLede = $group['lede'] !== '' ? '<p class="group-lede">'.$this->e($group['lede']).'</p>' : '';
            $guides .= '<div class="guide-group"><h3 class="group-title">'.$this->e($group['title']).'</h3>'.$groupLede.'<div class="doc-grid">'.$cards.'</div></div>';
        }

        $toc = array_merge($home['toc'] ?? [], [['level' => 2, 'id' => 'guides', 'text' => 'تصفّح الأدلة', 'label' => 'تصفّح الأدلة', 'num' => null]]);
        $feedback = $this->config['feedback'] ?? null;
        $cta = '';
        if ($feedback && isset($this->byFile[$feedback])) {
            $toc[] = ['level' => 2, 'id' => 'feedback', 'text' => 'أرسل اقتراحك', 'label' => 'أرسل اقتراحك', 'num' => null];
            $cta = '<section class="cta"><span class="cta-icon">'.$this->icon('bulb').'</span>'
                .'<div class="cta-text"><h2 id="feedback">لديك فكرة لتطوير «الديوان»؟</h2>'
                .'<p>أرسل اقتراحك أو ملاحظتك من صفحة «طلبات المميزات» داخل النظام، فيصل إلى مطوّري المشروع على GitHub مباشرة.</p></div>'
                .'<a class="cta-btn" href="'.$this->pages[$this->byFile[$feedback]]['slug'].'.html">طريقة إرسال الاقتراحات '.$this->icon('arrow-left').'</a></section>';
        }
        $tocHtml = $this->tocList($toc);

        $version = $this->version !== '' ? 'الإصدار '.$this->e($this->version).' · ' : '';
        $body = $home['body'] ?? '';
        $content = <<<HTML
<section class="hero hero-home">
  {$this->watermark()}
  <div class="wrap">
    <div class="eyebrow">{$version}{$this->e($site['tagline'])}</div>
    <h1>{$this->e($title)}</h1>
    <p class="lede">{$lede}</p>
    <button type="button" class="hero-search" data-open-search>
      {$this->icon('search')}<span>ابحث في كل الأدلة: تثبيت، نسخ احتياطي، فاتورة، Webhooks...</span><kbd>Ctrl K</kbd>
    </button>
    <div class="stats">{$statsHtml}</div>
  </div>
</section>
<div class="layout wrap">
  {$this->sideNav('index')}
  <main class="content" id="main">
    {$this->mobileToc($tocHtml)}
    <article class="prose">
{$body}
    </article>
    <section class="home-guides">
      <h2 class="section-title" id="guides">تصفّح الأدلة</h2>
      <p class="group-lede">بعد أن تعرّفت على النظام، اختر الدليل الذي تحتاجه. كل الأدلة متاحة أيضاً من القائمة الجانبية ومن البحث.</p>
      {$guides}
    </section>
    {$cta}
    {$this->sourceRow($home)}
    {$this->pager(null, $this->pages[0] ?? null)}
  </main>
  {$this->tocAside($tocHtml)}
</div>
HTML;

        return $this->layout($title.' · '.$site['tagline'], strip_tags($lede), $content, 'home');
    }

    private function sourceRow(?array $page): string
    {
        if (! $page) {
            return '';
        }
        $github = $page['repo_path'] !== null
            ? '<a class="ext" href="'.$this->e('https://github.com/'.$this->config['repo'].'/blob/'.$this->config['branch'].'/'.$page['repo_path']).'" target="_blank" rel="noopener">عرض على GitHub</a>'
            : '';
        $name = $page['repo_path'] ?? 'PagesDoc/pages/'.$page['file'];

        return '<div class="source-row"><span>'.$this->icon('file').' المصدر: <code>'.$this->e($name).'</code></span>'
            .'<span class="source-actions">'.$github.'<button type="button" class="link-btn" data-print>'.$this->icon('printer').' طباعة / PDF</button></span></div>';
    }

    private function pager(?array $prev, ?array $next): string
    {
        if (! $prev && ! $next) {
            return '';
        }

        return '<nav class="pager" aria-label="التنقل بين الصفحات">'
            .($prev ? '<a class="pager-card prev" href="'.$prev['slug'].'.html"><span class="pager-dir">'.$this->icon('arrow-right').' السابق</span><b>'.$this->e($prev['title']).'</b></a>' : '<span></span>')
            .($next ? '<a class="pager-card next" href="'.$next['slug'].'.html"><span class="pager-dir">التالي '.$this->icon('arrow-left').'</span><b>'.$this->e($next['title']).'</b></a>' : '<span></span>')
            .'</nav>';
    }

    private function layout(string $title, string $description, string $content, string $kind = 'doc'): string
    {
        $site = $this->config['site'];
        $repo = 'https://github.com/'.$this->config['repo'];
        $version = $this->version !== ''
            ? '<a class="ver" href="'.$this->e($repo).'/tags" target="_blank" rel="noopener" title="كل الإصدارات (Tags) على GitHub">v'.$this->e($this->version).'</a>'
            : '';
        $feedback = $this->config['feedback'] ?? null;
        $feedbackLink = $feedback && isset($this->byFile[$feedback])
            ? '<a href="'.$this->pages[$this->byFile[$feedback]]['slug'].'.html">اقترح ميزة</a>'
            : '';
        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->e($title)}</title>
<meta name="description" content="{$this->e($description)}">
<meta name="generator" content="PagesDoc">
<link rel="icon" href="data:image/svg+xml,{$this->faviconSvg()}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script>try{var t=localStorage.getItem('pagesdoc-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body class="page-{$kind}" id="top">
<a class="skip" href="#main">تخطَّ إلى المحتوى</a>
<header class="topbar">
  <div class="topbar-in">
    <button type="button" class="icon-btn nav-toggle" data-open-nav aria-label="فهرس الأدلة">{$this->icon('menu')}</button>
    <a class="brand" href="index.html" aria-label="{$this->e($site['name'])}: الصفحة الرئيسية للتوثيق">
      {$this->logo()}
      <span class="brand-text"><b>{$this->e($site['name'])}</b><small>{$this->e($site['tagline'])}</small></span>
    </a>
    {$version}
    <button type="button" class="searchbox" data-open-search aria-label="بحث في التوثيق">
      {$this->icon('search')}<span>ابحث في التوثيق...</span><kbd>Ctrl K</kbd>
    </button>
    <div class="top-actions">
      <button type="button" class="icon-btn" data-theme-toggle aria-label="تبديل الوضع الليلي">{$this->icon('moon', 'only-light')}{$this->icon('sun', 'only-dark')}</button>
      <a class="icon-btn" href="{$this->e($repo)}" target="_blank" rel="noopener" aria-label="المستودع على GitHub" title="المستودع على GitHub">{$this->icon('code')}</a>
    </div>
  </div>
</header>
{$content}
<footer class="footer">
  <div class="wrap footer-in">
    <div class="footer-brand">{$this->logo()}<div><b>{$this->e($site['name'])}</b><span>نظام إدارة الأعمال المتكامل · {$this->e($site['tagline'])}</span></div></div>
    <div class="footer-links">
      <a href="index.html">الرئيسية</a>
      {$feedbackLink}
      <a href="{$this->e($repo)}/releases" target="_blank" rel="noopener">الإصدارات</a>
      <a href="{$this->e($repo)}/tags" target="_blank" rel="noopener">الوسوم (Tags)</a>
      <a href="{$this->e($repo)}/issues" target="_blank" rel="noopener">طلب مساعدة</a>
    </div>
    <div class="footer-note">مولّد من <code>README.md</code> و<code>docs/*.md</code> بواسطة PagesDoc · {$year}</div>
  </div>
</footer>
<div class="search" data-search hidden>
  <div class="search-backdrop" data-close-search></div>
  <div class="search-panel" role="dialog" aria-modal="true" aria-label="بحث في التوثيق">
    <div class="search-head">
      {$this->icon('search')}
      <input type="search" placeholder="ابحث في كل الأدلة..." aria-label="عبارة البحث" autocomplete="off" spellcheck="false" data-search-input>
      <button type="button" class="esc" data-close-search>Esc</button>
    </div>
    <div class="search-results" data-search-results></div>
    <div class="search-foot"><span><kbd>↑</kbd><kbd>↓</kbd> للتنقل</span><span><kbd>Enter</kbd> للفتح</span><span><kbd>Esc</kbd> للإغلاق</span></div>
  </div>
</div>
<div class="nav-backdrop" data-close-nav></div>
<script src="assets/search-index.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
HTML;
    }

    private function sideNav(string $current): string
    {
        $homeActive = $current === 'index' ? ' active" aria-current="page' : '';
        $html = '<aside class="sidenav" aria-label="فهرس الأدلة"><div class="sidenav-in">'
            .'<div class="sidenav-head"><span>كل الأدلة</span><button type="button" class="icon-btn" data-close-nav aria-label="إغلاق">'.$this->icon('x').'</button></div>'
            .'<a class="side-home'.$homeActive.'" href="index.html">'.$this->icon('home').'<span>'.$this->e($this->config['home']['nav'] ?? 'الرئيسية').'</span></a>';
        foreach ($this->config['groups'] as $key => $group) {
            $pages = array_filter($this->pages, fn ($p) => $p['group'] === $key);
            if (! $pages) {
                continue;
            }
            $html .= '<div class="side-group"><div class="side-title">'.$this->e($group['title']).'</div>';
            foreach ($pages as $p) {
                $active = $p['file'] === $current ? ' class="active" aria-current="page"' : '';
                $html .= '<a href="'.$p['slug'].'.html"'.$active.'>'.$this->icon($p['icon']).'<span>'.$this->e($p['nav'] ?? $p['title']).'</span></a>';
            }
            $html .= '</div>';
        }

        return $html.'</div></aside>';
    }

    private function tocList(array $toc): string
    {
        if (! $toc) {
            return '<p class="toc-empty">صفحة قصيرة بلا أقسام.</p>';
        }
        $html = '<ul class="toc-list">';
        foreach ($toc as $h) {
            $num = $h['num'] !== null ? '<span class="toc-num">'.$this->e($h['num']).'</span>' : '';
            $html .= '<li class="l'.$h['level'].'"><a href="#'.$this->e($h['id']).'">'.$num.'<span>'.$this->e($h['label']).'</span></a></li>';
        }

        return $html.'</ul>';
    }

    private function mobileToc(string $toc): string
    {
        return '<details class="toc-mobile"><summary>'.$this->icon('list').'في هذه الصفحة'.$this->icon('chevron', 'chev').'</summary>'.$toc.'</details>';
    }

    private function tocAside(string $toc): string
    {
        return '<aside class="toc" aria-label="في هذه الصفحة"><div class="toc-in"><div class="toc-title">في هذه الصفحة</div>'
            .$toc.'<a class="toc-top" href="#top">'.$this->icon('arrow-up').' العودة للأعلى</a></div></aside>';
    }

    // ------------------------------------------------------------------ small helpers

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function join(array $parts): string
    {
        return implode('', $parts);
    }

    private function chip(string $icon, string $text): string
    {
        return '<span class="chip">'.$this->icon($icon).$this->e($text).'</span>';
    }

    /** Arabic counting: [one, two, 3–10, 11+]; %d is the number. */
    private function plural(int $n, array $forms, bool $withNumber = true): string
    {
        $form = match (true) {
            $n === 1 => $forms[0],
            $n === 2 => $forms[1],
            $n % 100 >= 3 && $n % 100 <= 10 => $forms[2],
            default => $forms[3],
        };

        return $withNumber ? sprintf($form, $n) : str_replace('%d ', '', $form);
    }

    /** 2026-10-30 → «30 أكتوبر 2026». */
    private function date(string $day): string
    {
        $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        [$y, $m, $d] = array_map('intval', explode('-', $day));

        return $d.' '.$months[$m - 1].' '.$y;
    }

    private function logo(): string
    {
        return '<span class="logo" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span>';
    }

    private function watermark(): string
    {
        return '<svg class="watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';
    }

    private function faviconSvg(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><defs><linearGradient id="g" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#0f766e"/><stop offset=".55" stop-color="#0d9488"/><stop offset="1" stop-color="#34d399"/></linearGradient></defs>'
            .'<rect width="32" height="32" rx="8" fill="url(#g)"/><g transform="translate(6.4 6.4) scale(.8)" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></g></svg>';

        return rawurlencode($svg);
    }

    private function icon(string $name, string $class = ''): string
    {
        static $paths = [
            'compass' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
            'desktop' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4M3 10h18M12 4v6"/>',
            'laptop' => '<rect x="4.5" y="5" width="15" height="10.5" rx="1.5"/><path d="M2.5 19h19"/>',
            'terminal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9.5l3 2.5-3 2.5M12.5 15H17"/>',
            'history' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5v4h4M12 7.5V12l3 2"/>',
            'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6"/>',
            'cloud' => '<path d="M7 18.5a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 18 11a3.75 3.75 0 0 1-.5 7.5"/><path d="M12 13v7M9 15.5l3-3 3 3"/>',
            'shield' => '<path d="M12 3l7.5 3v5.5c0 4.6-3.2 8.4-7.5 9.5-4.3-1.1-7.5-4.9-7.5-9.5V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
            'receipt' => '<path d="M6 3h12v18l-2.5-1.6L13 21l-2.5-1.6L8 21l-2-1.4z"/><path d="M9 8h6M9 11.5h6M9 15h3.5"/>',
            'factory' => '<path d="M3 20.5V10l5 3v-3l5 3v-3l5 3V4h3v16.5z"/><path d="M7 17h2M12 17h2"/>',
            'code' => '<path d="M8.5 8l-4.5 4 4.5 4M15.5 8l4.5 4-4.5 4M13.5 5l-3 14"/>',
            'blocks' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
            'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
            'calculator' => '<rect x="5" y="2.5" width="14" height="19" rx="2"/><path d="M8.5 6.5h7v3h-7zM8.5 13h.01M12 13h.01M15.5 13h.01M8.5 16.5h.01M12 16.5h.01M15.5 16.5h.01"/>',
            'cart' => '<path d="M3 4h2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.2"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
            'truck' => '<path d="M3 6h11v9.5H3zM14 9.5h3.5l3 3.2v2.8H14z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
            'box' => '<path d="M3.5 7.8L12 3.5l8.5 4.3v8.4L12 20.5l-8.5-4.3z"/><path d="M3.5 7.8L12 12.2l8.5-4.4M12 12.2v8.3"/>',
            'store' => '<path d="M4.5 10v10h15V10"/><path d="M3 9.5L4.8 4.5h14.4L21 9.5a2.25 2.25 0 0 1-4.5.5 2.25 2.25 0 0 1-4.5 0 2.25 2.25 0 0 1-4.5 0A2.25 2.25 0 0 1 3 9.5z"/><path d="M10 20v-5h4v5"/>',
            'building' => '<path d="M3 21h18M5 21V7l6-3.5V21M11 21V9.5h8V21M14 13h2M14 16.5h2M7.5 9.5h1M7.5 13h1M7.5 16.5h1"/>',
            'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>',
            'bulb' => '<path d="M9.5 18h5M10.5 21h3"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0 0 12 3z"/>',
            'pencil' => '<path d="M4 20l1.2-4.6L15.6 5a2.1 2.1 0 0 1 3 3L8.2 18.4z"/><path d="M13.6 7l3 3"/>',
            'send' => '<path d="M20.5 3.5L10 14"/><path d="M20.5 3.5L14 20.5l-4-6.5-6.5-4z"/>',
            'thumb' => '<path d="M7 10.5V20H4v-9.5zM7 10.5l3.8-7a2 2 0 0 1 2.7 2.4L12.6 10H18a2 2 0 0 1 2 2.3l-1.2 6A2.2 2.2 0 0 1 16.6 20H7"/>',
            'download' => '<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19.5h14"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4L6 18M18 6l1.4-1.4"/>',
            'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
            'list' => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
            'users' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.2a3.5 3.5 0 0 1 0 6.6M18.5 14.2A6.5 6.5 0 0 1 21.5 20"/>',
            'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
            'printer' => '<path d="M7 8V3.5h10V8"/><rect x="3.5" y="8" width="17" height="8.5" rx="1.5"/><path d="M7 14h10v6.5H7z"/>',
            'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
            'chevron' => '<path d="M6 9l6 6 6-6"/>',
            'home' => '<path d="M3.5 11L12 4l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/>',
            'copy' => '<rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 8.5V5.5a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h3"/>',
            'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        ];

        return '<svg class="i i-'.$name.($class !== '' ? ' '.$class : '').'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            .($paths[$name] ?? $paths['book']).'</svg>';
    }
}

(new PagesDoc(require __DIR__.'/config.php'))->run();
