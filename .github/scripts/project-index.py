# File Name     : .github/scripts/project-index.py
# Author        : Jason Lamb (with help from ChatGPT)
# Description   : Generate README listings and navigation tiles from project metadata.
# Revision      : 1.0.2
# Created Date  : 2026-11-25
# Modified Date : 2026-11-25
# Changelog     :
#   Rev 1.0.2 - Preserve new-tab links for hosted project URLs.
#   Rev 1.0.1 - Read index metadata nested in existing release manifests.
#   Rev 1.0.0 - Generate both project indexes from project.json.
import argparse
import json
import re
from html import escape
from pathlib import Path
from urllib.parse import quote, urlsplit

ROOT = Path(__file__).resolve().parents[2]
CATEGORIES = {
    "games": "Games and interactive projects",
    "trackers": "Personal and group trackers",
    "tools": "Tools and dashboards",
}
FIELDS = {"_header", "name", "category", "description", "order", "url",
          "site_note", "link_title", "readme", "navigation"}
NAV_FIELDS = {"name", "category", "access", "href", "order"}


def validate_text(value):
    return isinstance(value, str) and bool(value.strip()) and not re.search(r"[\x00-\x1f\x7f]", value)


def validate_url(value, navigation=False):
    if not validate_text(value) or re.search(r'[\s\\<>"`|()]', value):
        return False
    if navigation and re.fullmatch(r"(?:\.\.)?/(?!/)[\w./-]*", value):
        return ".." not in value.split("/")[1:]
    parts = urlsplit(value)
    return parts.scheme == "https" and bool(parts.hostname) and not parts.username and not parts.password


def read_project(path):
    item = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(item, dict) and "project_index" in item:
        item = item["project_index"]
    if not isinstance(item, dict) or item.keys() - FIELDS:
        raise ValueError("expected a project object with documented fields")
    if not isinstance(item.get("_header"), dict):
        raise ValueError("expected a _header object")
    for key in ("name", "description", "site_note"):
        if (key != "site_note" or key in item) and not validate_text(item.get(key)):
            raise ValueError(f"{key} must be nonempty single-line text")
    if item.get("category") not in CATEGORIES:
        raise ValueError("category must be games, trackers, or tools")
    for key in ("readme", "link_title"):
        if key in item and type(item[key]) is not bool:
            raise ValueError(f"{key} must be true or false")
    nav = item.get("navigation")
    if "navigation" in item and not (type(nav) is bool or isinstance(nav, dict)):
        raise ValueError("navigation must be true, false, or an object")
    if isinstance(nav, dict):
        if nav.keys() - NAV_FIELDS or nav.get("category", item["category"]) not in CATEGORIES:
            raise ValueError("invalid navigation settings")
        for key in ("name", "access"):
            if key in nav and not validate_text(nav[key]):
                raise ValueError(f"navigation.{key} must be nonempty single-line text")
    item["navigation"] = {} if nav is True else nav if isinstance(nav, dict) else None
    for settings in (item, item["navigation"] or {}):
        if "order" in settings and (type(settings["order"]) is not int or settings["order"] < 0):
            raise ValueError("order must be a nonnegative integer")
    folder = quote(path.parent.name, safe="")
    default_url = f"https://jasr.me/github/{folder}/"
    item["url"] = item.get("url", default_url)
    if item["url"] is not None and not validate_url(item["url"]):
        raise ValueError("url must be an HTTPS URL or null")
    if item["navigation"] is not None:
        href = (f"../{folder}/" if item["url"] == default_url else item["url"])
        item["navigation"]["href"] = item["navigation"].get("href", href)
        if not validate_url(item["navigation"]["href"], navigation=True):
            raise ValueError("public navigation requires a valid HTTPS URL or site path")
    item["folder"] = folder
    item["docs"] = next((name for name in ("README.md", "readme.md")
                         if (path.parent / name).is_file()), None)
    return item


def replace_block(text, key, lines):
    start = f"<!-- BEGIN GENERATED {key} -->"
    end = f"<!-- END GENERATED {key} -->"
    if text.count(start) != 1 or text.count(end) != 1:
        raise ValueError(f"expected one pair of {key} markers")
    before, rest = text.split(start)
    if end not in rest:
        raise ValueError(f"reversed {key} markers")
    _, after = rest.split(end)
    return before + start + "\n" + "\n".join(lines) + "\n" + end + after


def markdown(text):
    return text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("|", "&#124;")


def readme_link(label, url, *, bold=False):
    if url.startswith("https://jasr.me/github/"):
        if bold:
            label = f"<strong>{label}</strong>"
        return f'<a href="{escape(url)}" target="_blank" rel="noopener noreferrer">{label}</a>'
    if bold:
        label = f"**{label}**"
    return f"[{label}]({url})"


def readme_rows(projects, category):
    yield "| Project and source | Site | Highlights |"
    yield "| --- | --- | --- |"
    entries = [item for item in projects if item["category"] == category and item.get("readme", True)]
    entries.sort(key=lambda item: (item.get("order", 1000), item["name"].casefold(), item["folder"]))
    for item in entries:
        folder = item["folder"]
        name = markdown(item["name"])
        url = item["url"]
        title = readme_link(name, url, bold=True) if item.get("link_title") and url else f"**{name}**"
        source = f"<br>[`{folder}/`]({folder}/)"
        if item["docs"]:
            source += f' / [README]({folder}/{item["docs"]})'
        label = markdown(item.get("site_note") or (url.removeprefix("https://").rstrip("/") if url else "—"))
        site = readme_link(label, url) if url else label
        yield f'| {title}{source} | {site} | {markdown(item["description"])} |'


def nav_tiles(projects, category):
    entries = [(item, item["navigation"]) for item in projects if item["navigation"] is not None]
    entries = [(item, nav) for item, nav in entries if nav.get("category", item["category"]) == category]
    entries.sort(key=lambda entry: (entry[1].get("order", 1000),
                                    entry[1].get("name", entry[0]["name"]).casefold(), entry[0]["folder"]))
    for item, nav in entries:
        name = escape(nav.get("name", item["name"]), quote=False)
        access = f'<small>{escape(nav["access"], quote=False)}</small>' if "access" in nav else ""
        yield f'                <li><a class="tile" href="{escape(nav["href"])}"><strong>{name}</strong>{access}</a></li>'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="Fail if tracked output needs regeneration.")
    args = parser.parse_args()
    projects = []
    for path in sorted(ROOT.glob("*/project.json")):
        try:
            projects.append(read_project(path))
        except (ValueError, TypeError) as error:
            parser.exit(1, f"{path.relative_to(ROOT)}: {error}\n")
    if not projects:
        parser.exit(1, "No project.json files found.\n")

    targets = [(ROOT / "README.md", "README", readme_rows),
               (ROOT / "projects/index.html", "NAV", nav_tiles)]
    changed = []
    for path, kind, render in targets:
        original = path.read_text(encoding="utf-8")
        rendered = original
        for category in CATEGORIES:
            rendered = replace_block(rendered, f"{kind} {category}", render(projects, category))
        if rendered != original:
            changed.append(str(path.relative_to(ROOT)))
            if not args.check:
                path.write_text(rendered, encoding="utf-8")
    if args.check and changed:
        parser.exit(1, f"Out of date: {', '.join(changed)}. Run python3 .github/scripts/project-index.py and commit the output.\n")
    print(f"Processed {len(projects)} projects. " + (f"Updated {', '.join(changed)}." if changed else "Indexes are current."))


if __name__ == "__main__":
    main()
