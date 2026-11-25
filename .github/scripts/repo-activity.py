# File Name     : .github/scripts/repo-activity.py
# Author        : Jason Lamb (with help from ChatGPT)
# Description   : Generate repository activity CSVs and a first-parent activity plot.
# Revision      : 1.0.0
# Created Date  : 2026-11-25
# Modified Date : 2026-11-25
# Changelog     :
#   Rev 1.0.0 - Export Git change counts and render a repository activity plot.
import argparse
import csv
import math
import subprocess
from collections import Counter
from datetime import datetime, timedelta, timezone
from pathlib import Path
from xml.sax.saxutils import escape

COLUMNS = ("commit", "committed_at", "kind", "subject", "files_changed",
           "lines_added", "lines_deleted", "binary_files", "folders")
PALETTE = ("#48d9b2", "#ffca70", "#82b6ff", "#ffa6cb")


def git(*args):
    return subprocess.check_output(["git", *args])


def change_counts(commit, parents):
    revisions = (parents[0], commit) if parents else (commit,)
    output = git("diff-tree", "--root", "-r", "--no-commit-id", "-M",
                 "--numstat", "-z", *revisions)
    records = iter(output.split(b"\0")[:-1])
    files = additions = deletions = binaries = 0
    folders = set()
    for record in records:
        added, deleted, path = record.split(b"\t", 2)
        # Git gives renames a blank path followed by old and new NUL-terminated paths.
        paths = (next(records), next(records)) if not path else (path,)
        files += 1
        if added == b"-":
            binaries += 1
        else:
            additions += int(added)
            deletions += int(deleted)
        for name in paths:
            folder, separator, _ = name.partition(b"/")
            folders.add(folder.decode("utf-8", errors="replace") if separator else "(root)")
    return files, additions, deletions, binaries, sorted(folders)


def activity_rows():
    if git("rev-parse", "--is-shallow-repository").strip() == b"true":
        raise SystemExit("Full Git history is required. Fetch with fetch-depth: 0.")
    landed = set(git("rev-list", "--first-parent", "HEAD").decode().splitlines())
    fields = git("log", "--reverse", "--topo-order", "-z",
                 "--format=%H%x00%P%x00%cI%x00%s", "HEAD").decode(errors="replace").split("\0")
    commits = []
    for index in range(0, len(fields) - 1, 4):
        commit, parent_shas, date, subject = fields[index:index + 4]
        parents = parent_shas.split()
        files, added, deleted, binaries, folders = change_counts(commit, parents)
        commits.append((commit, date, "merge" if len(parents) > 1 else "commit",
                        subject, files, added, deleted, binaries, folders))
    return commits, [row for row in commits if row[0] in landed]


def write_csv(path, rows):
    with path.open("w", encoding="utf-8", newline="") as file:
        writer = csv.writer(file, lineterminator="\n")
        writer.writerow(COLUMNS)
        for *fields, folders in rows:
            # Prevent user-controlled commit subjects and paths becoming spreadsheet formulas.
            subject = fields[3]
            fields[3] = "'" + subject if subject.lstrip().startswith(("=", "+", "-", "@")) else subject
            folder_text = " | ".join(folders)
            if folder_text.lstrip().startswith(("=", "+", "-", "@")):
                folder_text = "'" + folder_text
            writer.writerow([*fields, folder_text])


def project(folders):
    return folders[0] if len(folders) == 1 else "Multiple folders" if folders else "No file changes"


def render_plot(rows):
    dates = [datetime.fromisoformat(row[1]).astimezone(timezone.utc) for row in rows]
    end = max(dates)
    start = end - timedelta(days=90)
    points = [(date, row) for date, row in zip(dates, rows) if date >= start]
    project_counts = Counter(project(row[8]) for _, row in points)
    project_counts.pop("Multiple folders", None)
    project_counts.pop("No file changes", None)
    top = [name for name, _ in sorted(project_counts.items(), key=lambda item: (-item[1], item[0]))[:4]]
    colors = dict(zip(top, PALETTE))
    colors["Multiple folders"] = "#eef4ff"
    highest = max(1, max(row[4] for _, row in points))
    churn = max(1, max(row[5] + row[6] for _, row in points))
    parts = [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 940 430" role="img" aria-labelledby="title desc">',
        '<title id="title">Repository activity</title>',
        '<desc id="desc">Changes landed on main during the 90 days ending at the latest commit. Height shows files changed on a logarithmic scale. Dot size shows lines added and deleted. Color shows project folder.</desc>',
        '<rect width="940" height="430" rx="18" fill="#101b30"/>',
        '<g font-family="system-ui, sans-serif" fill="#eef4ff">',
        '<text x="34" y="46" font-size="29" font-weight="700">Repo Activity</text>',
        f'<text x="35" y="74" font-size="14" fill="#a9bdd8">{len(points):,} events on main · {sum(row[5] for _, row in points):,} lines added · {sum(row[6] for _, row in points):,} deleted</text>',
        '<text x="35" y="107" font-size="12" fill="#a9bdd8">FILES CHANGED (LOG SCALE)</text>',
    ]
    ticks = sorted({0, 1, highest, *(10 ** n for n in range(1, len(str(highest))))})
    # Keep the uppermost label clear when the maximum is near an existing tick.
    if len(ticks) > 3 and math.log1p(highest) - math.log1p(ticks[-2]) < .35:
        ticks.remove(ticks[-2])
    for tick in ticks:
        y = 323 - 196 * math.log1p(tick) / math.log1p(highest)
        parts.extend([
            f'<path d="M 82 {y:.1f} H 900" stroke="#344563" stroke-width="1"/>',
            f'<text x="72" y="{y+4:.1f}" text-anchor="end" font-size="11" fill="#a9bdd8">{tick:,}</text>',
        ])
    for days in range(0, 91, 15):
        date = start + timedelta(days=days)
        x = 82 + 818 * days / 90
        parts.append(f'<text x="{x:.1f}" y="347" text-anchor="middle" font-size="11" fill="#a9bdd8">{date:%b %d}</text>')
    # Draw large changes first so smaller dots remain visible above them.
    for date, row in sorted(points, key=lambda point: point[1][5] + point[1][6], reverse=True):
        x = 82 + 818 * (date - start) / timedelta(days=90)
        y = 323 - 196 * math.log1p(row[4]) / math.log1p(highest)
        radius = 3 + 12 * math.sqrt((row[5] + row[6]) / churn)
        color = colors.get(project(row[8]), "#8395b1")
        parts.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="{radius:.1f}" fill="{color}" fill-opacity=".72" stroke="#101b30" stroke-width=".6"/>')
    legend = [*colors.items(), ("Other folders / no changes", "#8395b1")]
    for index, (name, color) in enumerate(legend):
        x = 35 + index % 3 * 302
        y = 381 + index // 3 * 22
        label = name if len(name) <= 32 else name[:31] + "…"
        # Remove control characters that Git paths can contain but XML rejects.
        label = escape("".join(char for char in label if char >= " " and char not in "\ufffe\uffff"))
        parts.append(f'<circle cx="{x}" cy="{y-4}" r="4" fill="{color}"/><text x="{x+10}" y="{y}" font-size="12">{label}</text>')
    parts.append("</g></svg>")
    return "\n".join(parts) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    commits, landed = activity_rows()
    args.output.mkdir(parents=True, exist_ok=True)
    write_csv(args.output / "commit-activity.csv", commits)
    write_csv(args.output / "main-activity.csv", landed)
    (args.output / "repo-activity.svg").write_text(render_plot(landed), encoding="utf-8")
    print(f"Wrote {len(commits):,} commits and {len(landed):,} main events.")


if __name__ == "__main__":
    main()
