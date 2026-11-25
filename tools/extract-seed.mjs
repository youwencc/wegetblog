// 从现有静态页面提取文章/作品数据，生成后端初始内容 seed.json
// 运行: node tools/extract-seed.mjs
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(process.argv[2] || '.');
const OUT = path.join(ROOT, 'app', 'content', 'seed.json');

const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
const pick = (re, s, i = 1) => { const m = s.match(re); return m ? m[i].trim() : ''; };
const all = (re, s) => [...s.matchAll(re)].map((m) => m[1].trim());
const stripTags = (s) => s.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();

function inner(html, className, tag = 'div') {
  const open = new RegExp(`<${tag}[^>]*class="${className}"[^>]*>`);
  const m = html.match(open);
  if (!m) return '';
  const start = m.index + m[0].length;
  const close = `</${tag}>`;
  // 粗略按同名标签配平
  let depth = 1;
  let i = start;
  while (i < html.length && depth > 0) {
    const nextOpen = html.indexOf(`<${tag}`, i);
    const nextClose = html.indexOf(close, i);
    if (nextClose === -1) break;
    if (nextOpen !== -1 && nextOpen < nextClose) { depth++; i = nextOpen + 1; }
    else { depth--; i = nextClose + close.length; }
  }
  return html.slice(start, i - close.length);
}

function parsePost(file) {
  const html = read(file);
  const slug = path.basename(file, '.html');
  const canonical = pick(/<link rel="canonical" href="([^"]+)"/, html);
  const ld = pick(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/, html);
  let ldObj = {};
  try { ldObj = JSON.parse(ld); } catch {}
  const postNode = (ldObj['@graph'] || []).find((n) => n['@type'] === 'BlogPosting') || {};
  const body = inner(html, 'prose', 'article');
  const meta = inner(html, 'post-meta', 'p');
  const tags = all(/<li><a class="tag"[^>]*>([^<]+)<\/a><\/li>/g, inner(html, 'tags', 'ul'));
  const nav = inner(html, 'post-nav', 'nav');
  const navLinks = [...nav.matchAll(/<a href="([^"]+)"[\s\S]*?<span class="dir">([^<]*)<\/span>[\s\S]*?<span class="t">([^<]*)<\/span>/g)]
    .map((m) => ({ href: m[1], dir: m[2].trim(), label: m[3].trim() }));
  const published = pick(/<meta property="article:published_time" content="(["]?)([^"]+)/, html) ||
    pick(/<time datetime="([^"]+)"/, meta);
  const words = Number((pick(/约 ([0-9,]+) 字/, meta) || '0').replace(/,/g, '')) || Number(postNode.wordCount) || 0;
  const minutes = parseInt(pick(/<time[\s\S]*?<\/time>[\s\S]*?<span>(\d+) 分钟/, meta) || postNode.timeRequired || '', 10) || 0;
  return {
    slug,
    url: `/posts/${slug}.html`,
    title: stripTags(pick(/<h1 class="post-title">([\s\S]*?)<\/h1>/, html)),
    seo_title: stripTags(pick(/<title>([\s\S]*?)<\/title>/, html)),
    description: pick(/<meta name="description" content="([^"]+)"/, html),
    eyebrow: stripTags(pick(/<p class="eyebrow">([\s\S]*?)<\/p>/, html)),
    section: pick(/<meta property="article:section" content="([^"]+)"/, html) || postNode.articleSection || '',
    tag: tags[0] || '',
    tags,
    project: stripTags((meta.match(/<span>([^<]*《[^<]*》[^<]*)<\/span>/) || [])[1] || ''),
    published_at: published,
    updated_at: postNode.dateModified || published,
    word_count: words,
    reading_minutes: minutes,
    keywords: (postNode.keywords || tags.join(', ')),
    body_html: body.trim(),
    prev: navLinks.find((l) => l.dir.includes('←') && !l.dir.includes('返回')) || null,
    next: navLinks.find((l) => l.dir.includes('→')) || null,
    nav,
    canonical,
  };
}

function parseWork(file, listHtml) {
  const html = read(file);
  const slug = path.basename(file, '.html');
  const canonical = pick(/<link rel="canonical" href="([^"]+)"/, html);
  const ld = pick(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/, html);
  let ldObj = {};
  try { ldObj = JSON.parse(ld); } catch {}
  const app = (ldObj['@graph'] || []).find((n) => n['@type'] === 'SoftwareApplication' || n['@type'] === 'VideoGame') || {};
  const body = inner(html, 'prose', 'article');
  const specs = [...inner(html, 'spec', 'dl').matchAll(/<div><dt>([^<]+)<\/dt><dd>([\s\S]*?)<\/dd><\/div>/g)]
    .map((m) => ({ label: m[1].trim(), value: stripTags(m[2]) }));
  const meta = [...inner(html, 'post-meta', 'p').matchAll(/<span[^>]*>([^<]+)<\/span>/g)].map((m) => m[1].trim());
  const related = inner(html, 'about-body');
  const nav = inner(html, 'post-nav', 'nav');
  const navLinks = [...nav.matchAll(/<a href="([^"]+)"[\s\S]*?<span class="dir">([^<]*)<\/span>[\s\S]*?<span class="t">([^<]*)<\/span>/g)]
    .map((m) => ({ href: m[1], dir: m[2].trim(), label: m[3].trim() }));

  // 从 works.html 提取列表条目的描述、要点、状态、周期、指标、标记图形
  let list = { desc: '', points: [], status: '', status_class: '', period: '', metric: '', en: '', mark_svg: '', mark_class: '' };
  if (listHtml) {
    const itemRe = new RegExp(`<article class="work">[\s\S]*?href="works/${slug}.html"[\s\S]*?</article>`, 'm');
    // 更稳：按 <li> 切分后匹配 slug
    const chunks = listHtml.split('<article class="work">').filter((c) => c.includes(`href="works/${slug}.html"`));
    const chunk = chunks[0] || '';
    if (chunk) {
      list.en = stripTags(pick(/<span class="work-en">([\s\S]*?)<\/span>/, chunk));
      list.desc = stripTags(pick(/<p class="work-desc">([\s\S]*?)<\/p>/, chunk));
      list.points = [...chunk.matchAll(/<li>([^<]+)<\/li>/g)].map((m) => m[1].trim()).filter((t) => t && !t.includes('work'));
      const st = chunk.match(/<span class="status([^"]*)">([^<]+)<\/span>/);
      list.status = st ? st[2].trim() : '';
      list.status_class = st ? st[1].trim() : '';
      const metas = [...chunk.matchAll(/<div class="work-meta">[\s\S]*?<\/div>\s*<\/article>/g)];
      const metaBlock = inner(chunk, 'work-meta');
      list.period = stripTags((metaBlock.match(/<div>([^<]*)<\/div>/) || [])[1] || '');
      const divs = [...metaBlock.matchAll(/<div>([^<]*)<\/div>/g)].map((m) => m[1].trim());
      list.metric = divs[1] || '';
      const mark = chunk.match(/<div class="work-mark([^"]*)"[\s\S]*?<svg[\s\S]*?<\/svg>/);
      if (mark) {
        list.mark_class = mark[1].trim();
        list.mark_svg = (mark[0].match(/<svg[\s\S]*?<\/svg>/) || [''])[0];
      }
    }
  }

  return {
    slug,
    url: `/works/${slug}.html`,
    title: stripTags(pick(/<h1 class="post-title">([\s\S]*?)<\/h1>/, html)),
    seo_title: stripTags(pick(/<title>([\s\S]*?)<\/title>/, html)),
    description: pick(/<meta name="description" content="([^"]+)"/, html),
    eyebrow: stripTags(pick(/<p class="eyebrow">([\s\S]*?)<\/p>/, html)),
    lede: stripTags(pick(/<p class="page-lede"[^>]*>([\s\S]*?)<\/p>/, html)),
    meta,
    specs,
    body_html: body.trim(),
    related_html: related.trim(),
    app_type: app['@type'] || 'SoftwareApplication',
    application_category: app.applicationCategory || '',
    operating_system: app.operatingSystem || '',
    version: app.softwareVersion || '',
    published_at: '',
    updated_at: '',
    sort_order: 0,
    list,
    prev: navLinks.find((l) => l.dir.includes('←')) || null,
    next: navLinks.find((l) => l.dir.includes('→')) || null,
    canonical,
  };
}

const posts = fs.readdirSync(path.join(ROOT, 'posts')).filter((f) => f.endsWith('.html')).map((f) => parsePost(`posts/${f}`));
const worksList = read('works.html');
const works = fs.readdirSync(path.join(ROOT, 'works')).filter((f) => f.endsWith('.html')).map((f, i) => {
  const w = parseWork(`works/${f}`, worksList);
  w.sort_order = i;
  return w;
});

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify({ generated_at: new Date().toISOString(), posts, works }, null, 2), 'utf8');
console.log(`posts: ${posts.length}, works: ${works.length} -> ${OUT}`);
for (const p of posts) console.log(' post', p.slug, p.title, p.published_at, p.tags.join('/'), p.body_html.length);
for (const w of works) console.log(' work', w.slug, w.title, w.list.points.join('/'), w.specs.length, w.body_html.length);
