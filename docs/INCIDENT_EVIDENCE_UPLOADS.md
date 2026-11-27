# Incident photo and video evidence

The resident incident-report form supports evidence for missed collections and illegal dumping.

## Upload limits

| Evidence | Allowed formats | Maximum count | Combined size per incident |
| --- | --- | ---: | ---: |
| Photos | JPEG, PNG, WebP | 10 | 10 MiB |
| Video clips | MP4, WebM, MOV | 3 | 15 MiB |

An individual file is also validated on the server. The maximum video size is 15 MiB **across all video attachments**, not 15 MiB each. Existing evidence is included when a resident adds more files while an incident is awaiting review. Old single-photo records remain accessible and count toward the image quota.

The upload UI shows selected image thumbnails, video names, number of files and combined sizes. Invalid selections are blocked in JavaScript for convenience **and independently on the server** by `IncidentEvidence`.

## Private storage and access

- Files are saved to `storage/app/private/incident-evidence/{report_id}/`, **not** `public/storage`.
- A MongoDB `incident_media` collection stores the incident ID, uploader ID, MIME type, file size and private path. Its migration is additive and does not clear existing data.
- Files have authenticated preview/download routes. Resident owners, assigned collection crew, in-zone coordinators, and administrators can access the incident. Eco-aides cannot retrieve resident case evidence.
- Residents can remove files from their own cases while the status remains `Under Review`. Removed entries preserve only metadata and audit fields; the corresponding private file is deleted.
- Existing legacy `reports.photo_path` files are retained and remain downloadable.

## Windows / XAMPP PHP 8.4 settings

Check the **loaded** PHP INI file via `php --ini`, then configure these values in `C:\xampp\php\php.ini`:

```ini
file_uploads = On
upload_max_filesize = 16M
post_max_size = 32M
max_file_uploads = 20
max_execution_time = 120
```

`post_max_size` must allow the photos (10 MiB) and videos (15 MiB) **together**, plus form overhead. Restart the PHP/Apache process if needed, and check `php -i` to confirm the active limits.

If deploying behind Nginx, set `client_max_body_size 32m;` (or higher) on the relevant site configuration and reload Nginx. Review proxy/server timeouts for larger uploads. Never place private media in a publicly exposed bucket without equivalent authorization.

No `npm install`, WebSockets, or database resets are needed.

## Install / validate

```powershell
git pull origin main
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=reports
```

Verify the following locally against the running MongoDB database:

- [ ] Submit a report with 10 small photos; all thumbnails, private views and downloads work.
- [ ] Reject 11 photos, or any selection whose total exceeds 10 MiB (including existing attachments).
- [ ] Add 1–3 supported videos totaling under 15 MiB; preview and download them successfully.
- [ ] Reject files with unsupported/renamed extensions or total video size above 15 MiB.
- [ ] Add further evidence to an `Under Review` incident; reject adding files after staff action begins.
- [ ] Remove a pending attachment; verify the media entry is removed from the active gallery.
- [ ] Attempt unauthorized video and photo URLs from another resident, a different-zone coordinator, and an eco-aide; these must be denied.
- [ ] Verify legacy `photo_path` evidence still downloads, and that previously recorded incident history remains intact.
- [ ] Test the photo/video form and the checkbox/month-filter alignment on a narrow phone screen.
- [ ] Test with the actual configured PHP/Apache/Nginx limits, not just the browser.

**Do not claim these tests are passed until run on the target installation.**
