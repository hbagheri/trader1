#!/usr/bin/env python3
"""Upsert (create-or-update) a WordPress post from a Markdown file with YAML front matter.

Usage:
    wp_import_md.py <path-to-md>

Matches existing posts by `slug` (front matter). If a post with that slug exists
under the configured post_type, its content/excerpt/taxonomies/meta are updated;
otherwise a new post is created.

Rewrites any `/widgets/<file>` URL in the body to
`/wp-content/physics-content/widgets/<file>` so iframes resolve correctly.

Designed to be invoked by the watcher (`wp_watch.py`) or directly from a shell.
"""
import json
import os
import re
import subprocess
import sys
from pathlib import Path

import markdown
import yaml

# ────────────────────────────────────────────────────────────────────────
ROOT       = Path(__file__).resolve().parent.parent       # wordpress/
TOOLS_DIR  = Path(__file__).resolve().parent
WPC        = "/tmp/wpc.sh"
MAPPING    = json.load(open(TOOLS_DIR / "import-mapping.json", encoding="utf-8"))
SITE       = MAPPING["site"]
NAME2TERM  = MAPPING["names_to_terms"]
DEFAULTS   = MAPPING["front_matter_defaults"]


def log(msg: str) -> None:
    print(f"[import] {msg}", flush=True)


def wp(*args: str, input_str: str | None = None) -> str:
    """Run a wp-cli command via the wpc wrapper. Returns stdout (stripped)."""
    cmd = [WPC, SITE, *args]
    res = subprocess.run(
        cmd,
        input=input_str,
        capture_output=True,
        text=True,
    )
    if res.returncode != 0:
        raise RuntimeError(f"wp {' '.join(args[:3])}…  →  {res.stderr.strip() or res.stdout.strip()}")
    return res.stdout.strip()


# ─── <!-- QUIZ_WIDGET: file.html --> placeholder expander ───────────────
WIDGETS_DIR_FS  = ROOT / "wp-content/physics-content/widgets"
WIDGETS_URL_BASE = "/wp-content/physics-content/widgets"


def _expand_quiz_widget_placeholders(body: str) -> str:
    """Convert HTML comments like `<!-- QUIZ_WIDGET: foo-quiz.html -->` into either:
       - an <iframe> embed if the widget file exists on disk
       - a friendly "coming soon" notice if it doesn't
    """
    pattern = re.compile(r"<!--\s*QUIZ_WIDGET:\s*([\w.\-]+)\s*-->")

    def replace(m):
        fname = m.group(1).strip()
        path  = WIDGETS_DIR_FS / fname
        if path.exists():
            return (
                f'<iframe src="{WIDGETS_URL_BASE}/{fname}" '
                f'width="100%" height="420" '
                f'style="border:none;border-radius:12px" '
                f'loading="lazy" title="پرسش و پاسخ"></iframe>'
            )
        return (
            '<div style="background:#FBF6E3;border:2px dashed #D4A847;border-radius:10px;'
            'padding:20px;margin:18px 0;text-align:center;color:#5B6E32">'
            '🛠 <strong>سؤال‌های این بخش به‌زودی اضافه می‌شن.</strong><br>'
            '<small>تا اون موقع، خودت سعی کن جواب سؤال‌های کتاب رو پیدا کنی و در نظرات با ما به اشتراک بذار 💬</small>'
            '</div>'
        )
    return pattern.sub(replace, body)


# ─── :::problem block parser ────────────────────────────────────────────
def _expand_problem_blocks(text: str) -> str:
    """Transform :::problem / :::hint / :::step / :::answer fences into HTML.

    Markdown syntax:
        :::problem عنوان مسأله
        صورت مسأله...
        :::hint
        راهنمایی
        :::
        :::step عنوان گام (اختیاری)
        محتوای گام
        :::
        :::answer
        پاسخ نهایی
        :::
        :::

    Inner markdown (paragraphs, formulas, lists) is preserved as-is and
    re-rendered later by python-markdown.
    """
    md = markdown.Markdown(extensions=["tables", "fenced_code", "sane_lists", "attr_list"])

    def render_inner(s: str) -> str:
        return md.reset().convert(s.strip())

    out_lines: list[str] = []
    lines = text.split("\n")
    i = 0
    while i < len(lines):
        line = lines[i]
        m = re.match(r"^:::problem\s*(.*)$", line.strip())
        if not m:
            out_lines.append(line)
            i += 1
            continue

        title = m.group(1).strip() or "تمرین"
        body_lines: list[str] = []
        depth = 1
        i += 1
        while i < len(lines) and depth > 0:
            l = lines[i].strip()
            if l == ":::" and depth == 1:
                depth = 0
                i += 1
                break
            if re.match(r"^:::\w+", l):
                depth += 1
            elif l == ":::":
                depth -= 1
            body_lines.append(lines[i])
            i += 1

        out_lines.append(_render_problem(title, "\n".join(body_lines), render_inner))
    return "\n".join(out_lines)


def _render_problem(title: str, body: str, render_inner) -> str:
    """Render a problem body (its inner sections) into HTML."""
    # Split body into pre-section statement and sub-blocks
    sections: list[tuple[str, str, str]] = []   # (kind, title, content)
    pre_lines: list[str] = []
    lines = body.split("\n")
    i = 0
    while i < len(lines):
        m = re.match(r"^:::(hint|step|answer)\s*(.*)$", lines[i].strip())
        if not m:
            pre_lines.append(lines[i])
            i += 1
            continue
        kind = m.group(1)
        sec_title = m.group(2).strip()
        sec_body: list[str] = []
        i += 1
        while i < len(lines):
            if lines[i].strip() == ":::":
                i += 1
                break
            sec_body.append(lines[i])
            i += 1
        sections.append((kind, sec_title, "\n".join(sec_body)))

    statement_html = render_inner("\n".join(pre_lines))

    parts = [
        '<div class="pm-problem">',
        f'<div class="pm-problem-title">{html_escape(title)}</div>',
        f'<div class="pm-problem-statement">{statement_html}</div>',
    ]

    for kind, sec_title, sec_body in sections:
        rendered = render_inner(sec_body)
        if kind == "hint":
            label = sec_title or "راهنمایی"
            parts.append(
                f'<details class="pm-hint"><summary>{html_escape(label)}</summary>'
                f'<div>{rendered}</div></details>'
            )
        elif kind == "step":
            label = sec_title or "گام"
            parts.append(
                f'<details class="pm-step"><summary>{html_escape(label)}</summary>'
                f'<div>{rendered}'
                f'<button type="button" class="pm-next-step">گام بعدی ←</button>'
                f'</div></details>'
            )
        elif kind == "answer":
            label = sec_title or "پاسخ نهایی"
            parts.append(
                f'<details class="pm-answer"><summary>{html_escape(label)}</summary>'
                f'<div>{rendered}</div></details>'
            )

    parts.append('</div>')
    return "\n".join(parts)


def html_escape(s: str) -> str:
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


# ─── self-check (خودتو بسنج) → accordion ────────────────────────────────
PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"

_PLACEHOLDER_ANSWER = (
    '<em style="color:#888">پاسخ این سؤال به‌زودی اضافه می‌شه. '
    'تا اون موقع، خودت بهش فکر کن و در نظرات پایین صفحه با ما به اشتراک بذار! 💬</em>'
)


def _normalize_digits(s: str) -> str:
    for fa, en in zip("۰۱۲۳۴۵۶۷۸۹", "0123456789"):
        s = s.replace(fa, en)
    return s


def _extract_answers_from_details(block_html: str) -> dict[int, str]:
    """Parse the answers <details> body. Python-markdown leaves the body raw
    (no <p> wrap, no `**bold**` conversion) because <details> is block-level
    HTML and we don't tag it with `markdown="1"`. So the body is just text
    interleaved with possible raw HTML.

    Strategy: split the raw body on each `**N.**` marker; everything between
    one marker and the next (or end of body) is that answer's content.
    """
    answers: dict[int, str] = {}
    # split on '**NUM.**' markers, capturing the number
    parts = re.split(r"\*\*\s*([0-9۰-۹]+)[\.\)]?\s*\*\*", block_html)
    # parts = [pre_text, num1, ans1, num2, ans2, ...]
    for i in range(1, len(parts), 2):
        try:
            n = int(_normalize_digits(parts[i]))
        except (ValueError, IndexError):
            continue
        if i + 1 >= len(parts):
            continue
        body = parts[i + 1].strip()
        # the body may include a trailing <p>...</p> for the next non-numbered
        # paragraph; preserve it. But strip any stray </details> sliver.
        body = re.sub(r"\s*</?details>\s*$", "", body).strip()
        answers[n] = body
    return answers


def _render_answer_markdown(s: str) -> str:
    """Re-run python-markdown on an extracted answer body so **bold**, bullet
    lists, etc. render correctly. The body was raw text inside a <details>
    block, which python-markdown's first pass left untouched.

    Pre-pass: ensure a blank line precedes any `- ` bullet that immediately
    follows non-blank content, so the bullet list is detected as a separate
    block rather than swallowed into the previous paragraph."""
    body = s.strip()
    body = re.sub(r"(?<=\S)\n(-\s)", r"\n\n\1", body)
    md = markdown.Markdown(extensions=["sane_lists"])
    return md.convert(body)


def _convert_self_check(html: str) -> str:
    """Find the 'خودتو بسنج' section and turn its <ol> into a styled list of
    <details> accordions.

    If a `<details><summary>...پاسخ...</summary>...</details>` block follows the
    `<ol>` and contains paragraphs starting with `**N.**`, those are extracted as
    real answers and merged into each card. Otherwise the card shows a
    placeholder asking the reader to think about it.
    """
    pattern = re.compile(
        r"(<h2[^>]*>\s*خودتو\s*بسنج[^<]*</h2>)\s*"
        r"(<ol[^>]*>.*?</ol>)"
        r"(\s*<details>\s*<summary>[^<]*(?:پاسخ|جواب)[^<]*</summary>(.*?)</details>)?",
        re.DOTALL,
    )

    def replace_block(m):
        heading = m.group(1)
        ol_text = m.group(2)
        full_details = m.group(3)  # the whole <details>...</details> (may be None)
        details_body = m.group(4)  # body inside answers <details> (may be None)

        items = re.findall(r"<li>\s*(?:<p>)?(.+?)(?:</p>)?\s*</li>", ol_text, re.DOTALL)
        if not items:
            return m.group(0)

        answers = _extract_answers_from_details(details_body) if details_body else {}

        intro = (
            '<p style="color:#5B6E32;font-size:14px;margin:6px 0 16px">'
            '👇 روی هر سؤال کلیک کن تا جوابش باز شه — اول خودت سعی کن جواب بدی!'
            '</p>'
        )
        cards = []
        for i, q in enumerate(items, 1):
            num = PERSIAN_DIGITS[i] if i < 10 else str(i)
            q_clean = q.strip()
            if i in answers:
                ans_html = _render_answer_markdown(answers[i])
            else:
                ans_html = _PLACEHOLDER_ANSWER
            cards.append(
                '<details style="background:#FAFAF5;border:1px solid #D4A847;border-radius:10px;padding:14px 18px;margin:12px 0">'
                '<summary style="cursor:pointer;font-size:16px;line-height:1.8;font-weight:600;color:#3D4548;outline:none">'
                f'<span style="display:inline-block;background:#9CAB52;color:#FFF;width:28px;height:28px;line-height:28px;border-radius:50%;text-align:center;margin-left:10px;font-weight:700">{num}</span>'
                f'{q_clean}'
                '<br><small style="color:#5B6E32;font-weight:500;font-size:13px;margin-right:38px">👆 کلیک کن برای دیدن پاسخ</small>'
                '</summary>'
                '<div style="margin-top:14px;padding-top:12px;border-top:1px dashed #D4A847;font-size:15px;line-height:1.95;color:#2A2E30">'
                f'{ans_html}'
                '</div>'
                '</details>'
            )
        return heading + "\n" + intro + "\n" + "\n".join(cards)

    return pattern.sub(replace_block, html)



# ─── path helpers ───────────────────────────────────────────────────────
def _sibling_asset_base(md_path: Path, dirname: str) -> str:
    """Return the URL prefix for a sibling asset folder (e.g. 'widgets' or 'images').

    The convention: each .md sits in an `articles/` folder, with sibling folders
    holding linked assets. We mirror that structure under /wp-content/... so
    references like src="/widgets/..." and src="/images/..." resolve.

        .../physics-content/articles/X.md             + 'widgets'
            → /wp-content/physics-content/widgets/

        .../physics-content/highschool/11/articles/X.md  + 'images'
            → /wp-content/physics-content/highschool/11/images/
    """
    parts = md_path.resolve().parts
    if "articles" not in parts:
        return f"/wp-content/physics-content/{dirname}/"
    i = parts.index("articles")
    try:
        j = parts.index("wp-content")
    except ValueError:
        return f"/wp-content/physics-content/{dirname}/"
    rel = parts[j:i] + (dirname,)
    return "/" + "/".join(rel) + "/"


def _widget_base_for(md_path: Path) -> str:
    return _sibling_asset_base(md_path, "widgets")


def _images_base_for(md_path: Path) -> str:
    return _sibling_asset_base(md_path, "images")


# ─── parsing ────────────────────────────────────────────────────────────
def parse_md(path: Path) -> dict:
    raw = path.read_text(encoding="utf-8")
    m = re.match(r"^---\s*\n(.+?)\n---\s*\n(.*)$", raw, re.DOTALL)
    if not m:
        raise ValueError(f"{path}: no YAML front matter")
    meta = yaml.safe_load(m.group(1)) or {}
    body = m.group(2)
    body = re.sub(r"^\s*#\s+.*\n", "", body, count=1)   # strip leading H1
    body = _expand_quiz_widget_placeholders(body)        # turn <!-- QUIZ_WIDGET: x.html --> into iframe/notice
    body = _expand_problem_blocks(body)                  # convert :::problem fences
    md = markdown.Markdown(extensions=[
        "tables", "fenced_code", "sane_lists", "attr_list", "md_in_html",
    ])
    html = md.convert(body)
    html = _convert_self_check(html)
    # rewrite local asset paths — base depends on the .md file's location
    widget_base = _widget_base_for(path)
    html = html.replace('src="/widgets/', f'src="{widget_base}')
    html = html.replace('](/widgets/',   f']({widget_base}')
    images_base = _images_base_for(path)
    html = html.replace('src="/images/', f'src="{images_base}')
    html = html.replace('](/images/',   f']({images_base}')
    # extract excerpt = first paragraph plain text
    excerpt = ""
    ex = re.search(r"<p>(.+?)</p>", html, re.DOTALL)
    if ex:
        txt = re.sub(r"<[^>]+>", "", ex.group(1))
        excerpt = re.sub(r"\s+", " ", txt).strip()[:240]
    return {"meta": meta, "html": html, "excerpt": excerpt}


# ─── taxonomy resolution ────────────────────────────────────────────────
SLUG_RE = re.compile(r"^[a-z0-9][a-z0-9_-]*$")


def resolve_term(value: str, expected_tax: str | None = None) -> tuple[str, str] | None:
    """Given a front-matter value, return (taxonomy, slug) or None.

    Resolution order:
      1. mapping table by exact name
      2. if value matches slug regex AND expected_tax is given, use it as-is
    """
    if not value:
        return None
    if value in NAME2TERM:
        m = NAME2TERM[value]
        return (m["taxonomy"], m["slug"])
    if expected_tax and SLUG_RE.match(value):
        return (expected_tax, value)
    return None


def ensure_term_exists(taxonomy: str, slug: str) -> int | None:
    """Look up term_id by slug. Return None if not present."""
    try:
        out = wp("term", "list", taxonomy, f"--slug={slug}", "--field=term_id")
    except RuntimeError:
        return None
    return int(out) if out.strip().isdigit() else None


# ─── DB lookup ──────────────────────────────────────────────────────────
def find_existing(slug: str, post_type: str) -> int | None:
    out = wp(
        "post", "list",
        f"--post_type={post_type}",
        f"--name={slug}",
        "--post_status=any",
        "--field=ID",
    )
    return int(out) if out.strip().isdigit() else None


# ─── main upsert ────────────────────────────────────────────────────────
def upsert(md_path: Path) -> None:
    parsed = parse_md(md_path)
    meta   = parsed["meta"]

    title   = meta.get("title") or md_path.stem
    slug    = meta.get("slug")  or md_path.stem
    excerpt = parsed["excerpt"]
    html    = parsed["html"]
    pt      = meta.get("post_type", DEFAULTS["post_type"])
    status  = meta.get("status",    DEFAULTS["status"])

    # build taxonomy plan
    tax_assignments: dict[str, list[str]] = {}
    for key in ("level", "chapter", "branch"):
        v = meta.get(key)
        if not v:
            continue
        resolved = resolve_term(v, expected_tax=key)
        if not resolved:
            log(f"  ! unknown {key}={v!r} — skipping (add it to import-mapping.json)")
            continue
        tax, slugv = resolved
        if not ensure_term_exists(tax, slugv):
            log(f"  ! term '{slugv}' in {tax} doesn't exist in DB — skipping")
            continue
        tax_assignments.setdefault(tax, []).append(slugv)

    # always also include the default level if not already present
    if "level" not in tax_assignments and DEFAULTS.get("level"):
        d = resolve_term(DEFAULTS["level"], expected_tax="level")
        if d and ensure_term_exists(*d):
            tax_assignments["level"] = [d[1]]

    keywords = meta.get("keywords") or []
    if keywords and isinstance(keywords, list):
        tax_assignments["post_tag"] = [str(k) for k in keywords]

    # find existing post
    existing_id = find_existing(slug, pt)

    # write content to a tmp file (passing big content as arg is fragile)
    body_path = Path("/tmp/wp_import_body.html")
    body_path.write_text(html, encoding="utf-8")
    body_arg = f"--post_content={html}"

    if existing_id:
        log(f"  update post #{existing_id}  ({pt}/{slug})")
        wp("post", "update", str(existing_id),
           f"--post_title={title}",
           f"--post_excerpt={excerpt}",
           f"--post_status={status}",
           body_arg)
        post_id = existing_id
    else:
        log(f"  create new post  ({pt}/{slug})")
        out = wp("post", "create",
                 f"--post_type={pt}",
                 f"--post_status={status}",
                 f"--post_title={title}",
                 f"--post_name={slug}",
                 f"--post_excerpt={excerpt}",
                 body_arg,
                 "--porcelain")
        post_id = int(out.splitlines()[-1].strip())
        log(f"    → ID {post_id}")

    # assign taxonomies (set = replace)
    for tax, slugs in tax_assignments.items():
        try:
            wp("post", "term", "set", str(post_id), tax, *slugs)
            log(f"    set {tax}: {', '.join(slugs)}")
        except RuntimeError as e:
            log(f"    ! failed setting {tax}: {e}")

    # custom fields
    for key, label in [("chapter", "chapter"), ("section", "section"),
                       ("order",   "lesson_order"), ("reading_time", "reading_time")]:
        v = meta.get(key)
        if v is not None:
            wp("post", "meta", "update", str(post_id), label, str(v))

    # Yoast SEO meta: description + focus keyword (first keyword)
    if excerpt:
        wp("post", "meta", "update", str(post_id), "_yoast_wpseo_metadesc", excerpt[:156])
    kws = meta.get("keywords") or []
    if kws and isinstance(kws, list) and kws[0]:
        wp("post", "meta", "update", str(post_id), "_yoast_wpseo_focuskw", str(kws[0]))

    # Video posts: pass through youtube_id (mu-plugin handles embed + thumbnail)
    if pt == "video":
        yt = meta.get("youtube_id") or meta.get("youtube") or meta.get("video_id")
        if yt:
            wp("post", "meta", "update", str(post_id), "youtube_id", str(yt))
            log(f"    youtube_id={yt}")

    # ─── global_order = chapter_order × 100 + lesson_order ───
    # Lets the front-end sort by book order across chapters.
    lesson_order = 0
    try:
        lesson_order = int(meta.get("order") or 0)
    except (TypeError, ValueError):
        lesson_order = 0

    chapter_order = 0
    # find the most-specific (child) chapter term for this post and pull its meta
    chapter_slugs = tax_assignments.get("chapter") or []
    for cs in chapter_slugs:
        tid = ensure_term_exists("chapter", cs)
        if not tid:
            continue
        try:
            v = wp("term", "meta", "get", str(tid), "chapter_order").strip()
            if v and v.isdigit() and int(v) > chapter_order:
                chapter_order = int(v)
        except RuntimeError:
            pass

    global_order = chapter_order * 100 + lesson_order
    wp("post", "meta", "update", str(post_id), "global_order", str(global_order))
    log(f"    global_order={global_order}  (chapter={chapter_order}, lesson={lesson_order})")

    # flush cache so changes show immediately
    try:
        wp("cache", "flush")
    except RuntimeError:
        pass

    log(f"  ✓ done  →  https://physicsme.ir/articles/{slug}/")


# ─── CLI ────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("usage: wp_import_md.py <file.md>")
    p = Path(sys.argv[1]).resolve()
    if not p.exists():
        sys.exit(f"not found: {p}")
    log(f"importing {p}")
    upsert(p)
