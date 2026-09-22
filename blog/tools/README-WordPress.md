# WordPress Scheduler

Revision: 1.0.0  
Modified: 2026-09-16

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
