<section class="evidence-uploader" data-incident-evidence
    data-existing-images="{{ $usage['images']??0 }}"
    data-existing-image-bytes="{{ $usage['image_bytes']??0 }}"
    data-existing-videos="{{ $usage['videos']??0 }}"
    data-existing-video-bytes="{{ $usage['video_bytes']??0 }}">
    <div class="evidence-title">
        <div><h3>Photos and videos</h3>
        <p>Add clear photos or short videos of the incident. Attachments are visible only to people authorized to handle your report.</p></div>
    </div>
    <div class="evidence-upload-grid">
        <div class="evidence-upload-field">
            <label class="form-label" for="incident_images">Photos (optional)</label>
            <input class="form-control" type="file" id="incident_images" name="images[]" multiple
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <small class="help">Up to 10 photos per report; 10 MB combined (JPG, PNG, WebP).</small>
            @error('images')<small class="form-error">{{ $message }}</small>@enderror
            @error('images.*')<small class="form-error">{{ $message }}</small>@enderror
        </div>
        <div class="evidence-upload-field">
            <label class="form-label" for="incident_videos">Videos (optional)</label>
            <input class="form-control" type="file" id="incident_videos" name="videos[]" multiple
                   accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime">
            <small class="help">Up to 3 videos per report; 15 MB combined (MP4, WebM, MOV).</small>
            @error('videos')<small class="form-error">{{ $message }}</small>@enderror
            @error('videos.*')<small class="form-error">{{ $message }}</small>@enderror
        </div>
    </div>
    <div class="evidence-upload-feedback" data-evidence-feedback role="status" aria-live="polite">
        Select files to see their combined size.
    </div>
    <div class="evidence-previews" data-evidence-previews></div>
</section>
