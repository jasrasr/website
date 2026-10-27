<?php
/* File Name: photo-upload.php
Author: Jason Lamb (with help from ChatGPT)
Description: Safe append-only multi-image uploads for one authenticated workout entry.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Stage checked images and atomically attach without replacing existing photos or workouts. */
declare(strict_types=1);

/** Check PHP's multiple-upload structure before inspecting any file paths. */
function workout_photo_upload_items(array $files): array
{
    foreach (['name','tmp_name','size','error'] as $key) {
        if (!isset($files[$key]) || !is_array($files[$key]) || !array_is_list($files[$key])) {
            throw new InvalidArgumentException('Select one or more photos to upload.');
        }
    }
    $count = count($files['name']);
    if ($count < 1 || $count > 5) {
        throw new InvalidArgumentException('Upload 1 to 5 photos at a time.');
    }
    foreach (['name','tmp_name','size','error'] as $key) {
        if (count($files[$key]) !== $count) throw new InvalidArgumentException('Invalid photo upload.');
    }
    $items = [];
    foreach ($files['name'] as $i => $name) {
        if (!is_string($name) || !is_string($files['tmp_name'][$i])
            || !is_int($files['error'][$i]) || !is_int($files['size'][$i])) {
            throw new InvalidArgumentException('Invalid photo upload.');
        }
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('A photo did not upload successfully. Try smaller files.');
        }
        if ($files['size'][$i] < 1 || $files['size'][$i] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Each photo must be JPEG, PNG or WebP and no larger than 5 MB.');
        }
        $items[] = ['tmp_name'=>$files['tmp_name'][$i], 'size'=>$files['size'][$i]];
    }
    return $items;
}

function workout_photo_upload_mime(string $path): string
{
    if (!is_file($path) || !is_readable($path)) {
        throw new InvalidArgumentException('Cannot read an uploaded photo.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extension = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'
    ][$mime] ?? null;
    if ($extension === null || @getimagesize($path) === false) {
        throw new InvalidArgumentException('Only valid JPEG, PNG or WebP images can be attached.');
    }
    return $extension;
}

/**
 * Stage new immutable image files only in this user's directory.
 * The callback exists for isolated tests; production always uses
 * move_uploaded_file, which rejects client-supplied local filesystem paths.
 */
function workout_stage_photo_uploads(
    array $files, string $storageRoot, string $userId, string $entryId,
    ?callable $mover = null
): array {
    if (preg_match('/\A[a-f0-9]{24}\z/D', $entryId) !== 1) {
        throw new InvalidArgumentException('Invalid workout entry.');
    }
    $items=workout_photo_upload_items($files);
    $root=realpath($storageRoot);
    if ($root===false || !is_dir($root)) throw new RuntimeException('Private storage is unavailable.');
    $photosRoot=$root.'/photos';
    if (is_link($photosRoot)) throw new RuntimeException('Unsafe photo storage.');
    if (!is_dir($photosRoot) && !mkdir($photosRoot,0700) && !is_dir($photosRoot)) {
        throw new RuntimeException('Cannot create private photo storage.');
    }
    $folder=$photosRoot.'/'.hash('sha256',$userId);
    if (is_link($folder)) throw new RuntimeException('Unsafe photo folder.');
    if (!is_dir($folder) && !mkdir($folder,0700) && !is_dir($folder)) {
        throw new RuntimeException('Cannot create the user photo folder.');
    }
    if (realpath($folder)!==$folder || dirname(realpath($folder))!==$photosRoot) {
        throw new RuntimeException('Invalid photo folder path.');
    }
    $mover ??= static fn(string $tmp, string $destination):bool =>
        move_uploaded_file($tmp,$destination);
    $saved = [];
    try {
        foreach ($items as $item) {
            $extension=workout_photo_upload_mime($item['tmp_name']);
            $filename=$entryId.'-'.bin2hex(random_bytes(12)).'.'.$extension;
            $destination=$folder.'/'.$filename;
            if (file_exists($destination) || is_link($destination)
                || !$mover($item['tmp_name'],$destination)) {
                throw new RuntimeException('Unable to save one of the new photos.');
            }
            @chmod($destination,0600);
            $saved[]=['filename'=>$filename,'path'=>$destination];
        }
        return $saved;
    } catch (Throwable $e) {
        foreach ($saved as $photo) if (is_file($photo['path'])) unlink($photo['path']);
        throw $e;
    }
}

/**
 * Revision checked update under JsonStore's exclusive lock.
 * Legacy photo fields are never replaced, and all other records are unchanged.
 */
function workout_apply_photo_append(array $records, string $id, string $revision, array $filenames): array
{
    if (preg_match('/\A[a-f0-9]{24}\z/D',$id)!==1
        || preg_match('/\A[a-f0-9]{64}\z/D',$revision)!==1
        || !$filenames || count($filenames)>5) {
        throw new InvalidArgumentException('Invalid photo attachment request.');
    }
    foreach ($filenames as $name) {
        if (!workout_photo_name_is_safe($name)) throw new InvalidArgumentException('Invalid saved photo name.');
    }
    if (count(array_unique($filenames))!==count($filenames)) {
        throw new InvalidArgumentException('Duplicate new photo.');
    }
    $matches=0;
    foreach ($records as $i => $record) {
        if (!is_array($record) || ($record['id']??null)!==$id)continue;
        $matches++;
        if ($matches!==1) throw new RuntimeException('Duplicate workout IDs.');
        if (!hash_equals(workout_record_revision($record),$revision)) {
            throw new WorkoutEditConflict('This workout changed elsewhere. Reload before uploading photos.');
        }
        $existing=workout_photo_names($record);
        if (count($existing)+count($filenames)>12) {
            throw new InvalidArgumentException('A workout can have at most 12 photos.');
        }
        if (array_intersect($existing,$filenames))throw new InvalidArgumentException('Photo already attached.');
        $previous=$record;
        unset($previous['edit_history']);
        $history=$record['edit_history']??[];
        if (!is_array($history))throw new RuntimeException('Invalid edit history.');
        $history[]=['edited_at'=>gmdate('c'),'previous'=>$previous];
        $record['photos']=array_merge(is_array($record['photos']??null)?$record['photos']:[],$filenames);
        $record['edit_history']=$history;
        $record['updated_at']=gmdate('c');
        $records[$i]=$record;
    }
    if ($matches!==1)throw new WorkoutEntryNotFound('Workout entry not found.');
    return $records;
}
