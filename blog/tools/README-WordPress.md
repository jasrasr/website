# WordPress Scheduler

## Automatic updates from repository changes

One existing WordPress post is maintained per tracked project. For a reader-facing change, include a short public revision note in that article's source JSON in the same PR. A code-only commit without an article change does not trigger a WordPress update. The workflow does not infer features from code or generate new articles.

1. Update `blog/posts/<slug>.json` if the project's description or instructions need to change.
2. Add a factual third-person revision note with a unique stable ID and the relevant GitHub PR or full commit link. Example (replace the summary, ID and PR link for the actual change):

```powershell
./blog/tools/Add-BlogRevision.ps1 `
    -Slug 'personal-budget-tracker-php-json' `
    -Id '2026-09-29-budget-import-validation' `
    -Summary 'Updated the budget import documentation to explain validation errors.' `
    -SourceUrl 'https://github.com/jasrasr/website/pull/90'
./blog/tools/Build-Blog.ps1 -RootPath './blog'
./blog/tools/Test-AutomatedVoice.ps1
./blog/tools/Test-BlogRevisions.ps1
```

3. Commit the article and generated index/RSS with the project change. Merge the reviewed PR into `main`.
4. **Sync existing WordPress articles** automatically compares the tracked posts and updates only changed text. Scheduled posts keep their schedule; published posts stay published; both retain author, slug and original date.

The `revision_history` array is the canonical public changelog. The helper writes its HTML footer into `content_html`, so the static blog and WordPress show the same history. Each entry displays a date, summary and GitHub source link. Never edit or delete old entries; append a correction with a new ID. Repeating an identical helper command is a no-op; reusing an ID for different text fails. Rendering replaces the existing generated footer, so retries never append a second copy.

Article text changes require a new history entry in CI. Changes that do not affect readers can stay in the project's ordinary changelog without creating an article entry. The shared `website` repo is the scope of this workflow; updating another repository does not trigger it. Add the relevant article change to this repo to document an external update.

### One-time GitHub setup

In **jasrasr/website → Settings → Secrets and variables → Actions**, add these repository secrets:

| Secret | Value |
| --- | --- |
| `WORDPRESS_USERNAME` | Existing integration account with permission to edit the tracked posts |
| `WORDPRESS_APP_PASSWORD` | That account's WordPress application password |
| `WORDPRESS_BACKUP_PASSWORD` | A separate strong password for encrypting recovery backups; retain it in a password manager |

These secrets are not configured by this PR. Without them, the workflow fails before making WordPress writes. The first qualifying merge after setup syncs the current corrected text and its editorial revision note. If the merge occurs before setup, configure the secrets, then manually run **Sync existing WordPress articles** on `main`: leave **apply** false for a read-only preview, then run with **apply** true to update.

The workflow is limited to `main`, serializes sync runs, checks out the latest `main` when each run starts, and never commits back to GitHub. It compares the current article text on every run, so unchanged posts are skipped and missed intermediate commits are reconciled. Manual edits made directly in WordPress can be overwritten by the source on a later sync; keep article text edits in GitHub. Concurrent edits during a run stop that post's update.

Original WordPress content is saved before updates, encrypted, and retained as a workflow artifact for 30 days (including on a later sync failure). No plaintext draft backup is uploaded. To recover a downloaded `wp-content-backups.enc`, with `WORDPRESS_BACKUP_PASSWORD` loaded locally:

```powershell
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 -in wp-content-backups.enc -out wp-content-backups.tar.gz -pass env:WORDPRESS_BACKUP_PASSWORD
tar -xzf wp-content-backups.tar.gz
```

The extracted JSON contains original raw post text for manual restoration. Changing the encryption secret requires retaining the previous password to open older backups.

## Correcting article text on existing WordPress posts

Automated project articles follow [the neutral third-person editorial policy](../EDITORIAL.md).
Changing source JSON or deploying the static blog does **not** update existing WordPress text.
`Publish-WordPress.ps1 -UpdateExisting` handles taxonomy, not article text.

Use PowerShell 7 with the existing application-password environment variables to review text changes:

```powershell
./blog/tools/Test-AutomatedVoice.ps1
./blog/tools/Sync-WordPressContent.ps1 -SiteUrl 'https://jasonlamb.me' -Slug 'personal-budget-tracker-php-json'
```

Then apply the reviewed correction to the example post:

```powershell
./blog/tools/Sync-WordPressContent.ps1 -SiteUrl 'https://jasonlamb.me' -Slug 'personal-budget-tracker-php-json' -Commit
```

Omit `-Slug` to preview all tracked project articles; add `-Commit` to apply that batch.
The command reads each tracked ID using authenticated edit context and verifies its slug and site.
The account must have edit permission for the existing posts; an Author account cannot necessarily edit posts belonging to a different author.
Preflight failure stops the batch before any writes. Identical content is skipped.
Only title, excerpt and body are updated. No posts are created, media uploaded, or author, slug, status, schedule, tags or categories sent in the update payload.
The leading static cover figure is removed, as in the original publisher; the existing featured image stays assigned.

Commit mode saves the original raw posts under `.wordpress-content-backups` in the user's home directory before the first write. `-BackupPath` can override this; keep backups outside a public web root and outside source control, since they can contain private drafts. Retain the backup for manual restoration if needed. Each post is reread immediately before its update and the command stops if it changed since preflight. This reduces concurrent-edit risk but is not a server-side transaction or an atomic batch: previous successful updates remain applied after a later failure. Rerunning skips text that already matches.

The preview lists changed fields, not a full textual diff; review the source changes in the PR. Commit mode reads current source and WordPress content again, so rerun the preview if either has changed.

Revision: 1.1.0
Modified: 2026-09-29

`Publish-WordPress.ps1` publishes the existing JSON articles in `blog/posts` to a self-hosted WordPress site through the built-in WordPress REST API. It is separate from the static blog build.

## What it does

- Uses `blog/posts/*.json` as the source of truth.
- Requires `title`, `slug`, `excerpt`, `content_html`, and `cover.src`.
- Uploads the cover image to WordPress Media and sets it as the featured image.
- Removes the leading static-blog `post-cover` figure from WordPress content by default so the image is not duplicated.
- Schedules posts Monday-Friday starting tomorrow.
- Uses random posting times from 8:00 AM through 5:00 PM Eastern.
- Guarantees at least one scheduled post on each weekday used by the schedule.
- Defaults to at most two posts per day, so about 40 posts span about four workweeks.
- Keeps WordPress post IDs, media IDs, schedule dates, and status in `wordpress-state.json`.
- Checks WordPress by slug before creating a post when credentials are supplied, providing a second duplicate-protection layer.
- Can update existing draft, pending, or scheduled posts by slug without changing their schedule when `-UpdateExisting` is used.
- In `-UpdateExisting` mode, published, deleted, missing, and otherwise ineligible posts are not scheduled or recreated.
- Can append a standard `GitHub` tag to every processed post with `-AddGitHubTag`.
- Reads an optional `categories` array from a source JSON file; categories are never created unless `-CreateMissingCategories` is explicitly supplied.
- Runs as a dry run unless `-Commit` is specified.

## WordPress setup

Create a dedicated WordPress user for this integration rather than using an administrator account. The account must be able to upload media and publish/schedule posts. A stock Contributor role is not sufficient for scheduling. Use the least-privileged role or custom role that provides the required capabilities.

In WordPress, create an **Application Password** for that user. Do not put the application password in this repository.

Confirm the WordPress site timezone is set to **New York / Eastern Time** under WordPress Settings > General.

## PowerShell setup

From the repository root:

```powershell
$env:WORDPRESS_USERNAME = 'wordpress-api-user'
$env:WORDPRESS_APP_PASSWORD = 'xxxx xxxx xxxx xxxx xxxx xxxx'
```

Dry-run preview:

```powershell
./blog/tools/Publish-WordPress.ps1 -SiteUrl 'https://your-wordpress-site.example'
```

Create the scheduled posts after reviewing the preview:

```powershell
./blog/tools/Publish-WordPress.ps1 -SiteUrl 'https://your-wordpress-site.example' -Commit
```

If the WordPress account is also allowed to create tags:

```powershell
./blog/tools/Publish-WordPress.ps1 -SiteUrl 'https://your-wordpress-site.example' -CreateMissingTags -Commit
```

To synchronize tags on existing scheduled or draft posts and append the `GitHub` tag:

```powershell
./blog/tools/Publish-WordPress.ps1 -SiteUrl 'https://your-wordpress-site.example' -UpdateExisting -AddGitHubTag -CreateMissingTags -Commit
```

To add one category to already-published WordPress posts that match local JSON slugs:

```powershell
./blog/tools/Add-WordPressCategoryToPublished.ps1 -SiteUrl 'https://your-wordpress-site.example' -CategoryName 'Technology'
```

The published-post category script is also dry-run by default. Add `-Commit` only after reviewing the table. It preserves existing categories and appends the requested category. Use `-ExcludeSlug` to skip specific posts. Use `-AllPublished` only when the category should be added to every published WordPress post, including posts that do not have a local JSON source.

Categories are opt-in per source article:

```json
"categories": ["Technology"]
```

The publisher resolves existing categories but does not create missing ones by default. Add `-CreateMissingCategories` only when the account has permission to manage categories and the new category is intentional.

By default, posts are randomly assigned to schedule slots. Use `-PreservePostOrder` to schedule them in their existing article-date order instead.

## State file

`wordpress-state.json` is intentionally committed to the repository. It is WordPress-specific and is not consumed by `Build-Blog.ps1` or the static blog pages.

After a real publishing run, commit the updated state file so future runs know which source articles are already represented in WordPress.

If media upload succeeds but post creation fails, the media ID is written to state immediately. A later retry will reuse that media item rather than uploading another copy.

## Safety behavior

The script will skip a source article if any required field or the local cover-image file is missing. In commit mode it also checks WordPress for the same slug before uploading media or creating a new post.

The first run should be done as a dry run. After that, a useful test is to temporarily point `-PostsPath` to a folder containing one copied article and confirm the resulting scheduled post, excerpt, tags, and featured image before scheduling the full backlog.

## Category and tag maintenance

`Edit-WordPressTaxonomy.ps1` lists, renames, merges, and deletes WordPress categories or tags. It uses the same `WORDPRESS_USERNAME` and `WORDPRESS_APP_PASSWORD` environment variables as the publisher. Listing is read-only, and every other operation is a dry run unless `-Commit` is supplied.

```powershell
# List all tags.
./blog/tools/Edit-WordPressTaxonomy.ps1 -SiteUrl 'https://your-wordpress-site.example' -Taxonomy Tag

# Preview and then perform a rename.
./blog/tools/Edit-WordPressTaxonomy.ps1 -SiteUrl 'https://your-wordpress-site.example' -Taxonomy Category -Operation Rename -SourceName 'Techy Tips' -TargetName 'Technology'
./blog/tools/Edit-WordPressTaxonomy.ps1 -SiteUrl 'https://your-wordpress-site.example' -Taxonomy Category -Operation Rename -SourceName 'Techy Tips' -TargetName 'Technology' -Commit

# Preview merging one tag into another. The committed run updates affected posts
# before deleting the source tag.
./blog/tools/Edit-WordPressTaxonomy.ps1 -SiteUrl 'https://your-wordpress-site.example' -Taxonomy Tag -Operation Merge -SourceName '#powershell' -TargetName 'PowerShell'

# Preview deleting an unused category.
./blog/tools/Edit-WordPressTaxonomy.ps1 -SiteUrl 'https://your-wordpress-site.example' -Taxonomy Category -Operation Delete -SourceName 'Testing'
```

For `Rename`, use `-NewSlug` only when the slug should be explicitly changed. A rename refuses to create a duplicate name; use `Merge` when the destination term already exists. If WordPress contains duplicate names, select terms unambiguously with `-SourceId` and, for a merge, `-TargetId`. Merge and delete previews show every affected post before any changes are made.
