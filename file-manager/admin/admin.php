<?php
/*
===========================================================
 File: admin/admin.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   Admin File Manager UI.
   - Auto-whitelists admin IP.
   - Shows MFA status (Verified / Not Verified).
   - Lists files & versions for allowed directories.
   - Restore and Delete version actions (MFA-gated).
   - Create new text files with versioning (MFA-gated).
===========================================================
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mfa_lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Auto-whitelist any IP that successfully reaches admin.php (.htaccess protected)
add_ip_to_allowed_list($clientIP, 'admin');

$actionMessage = '';

// Handle POST actions (restore, delete, create) with MFA requirement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Any POST action here is considered "dangerous"
    mfa_require_or_redirect($_SERVER['REQUEST_URI'] ?? 'admin.php');

    $action    = $_POST['action'] ?? '';
    $directory = $_POST['directory'] ?? '';
    $file      = $_POST['file'] ?? '';

    if (!in_array($directory, $allowedDirectories, true)) {
        $actionMessage = "Invalid directory.";
    } else {
        $dirPath  = __DIR__ . '/../' . $directory . '/';
        $fullPath = $file ? realpath($dirPath . $file) : null;

        if ($action === 'delete' || $action === 'restore') {
            // Restore/Delete require a target file
            if ($fullPath === false || strpos($fullPath, realpath($dirPath)) !== 0) {
                $actionMessage = "Invalid file path.";
            } else {
                if ($action === 'delete') {
                    if (file_exists($fullPath)) {
                        if (@unlink($fullPath)) {
                            write_log($LOG_UPLOAD, "ADMIN DELETE | IP=$clientIP | DIR=$directory | FILE=$file");
                            $actionMessage = "Deleted $file.";
                        } else {
                            $actionMessage = "Failed to delete $file.";
                        }
                    } else {
                        write_log($LOG_UPLOAD, "ADMIN DELETE MISSING | IP=$clientIP | DIR=$directory | FILE=$file");
                        $actionMessage = "File $file no longer exists; removed from list.";
                    }
                } elseif ($action === 'restore') {
                    $info = pathinfo($file);
                    $name = $info['filename'];
                    $ext  = isset($info['extension']) ? "." . $info['extension'] : "";

                    // Only restore versioned files like baseName_vN.ext
                    if (preg_match('/^(.*)_v(\d+)$/', $name, $m)) {
                        $baseName   = $m[1];
                        $baseFile   = $baseName . $ext;
                        $basePath   = $dirPath . $baseFile;
                        $restoreSrc = $fullPath;

                        // If current base exists, move it to next version
                        if (file_exists($basePath)) {
                            $counter     = 1;
                            $versionPath = $dirPath . $baseName . "_v$counter" . $ext;
                            while (file_exists($versionPath)) {
                                $counter++;
                                $versionPath = $dirPath . $baseName . "_v$counter" . $ext;
                            }
                            @rename($basePath, $versionPath);
                        }

                        // Move selected version into base
                        if (file_exists($restoreSrc)) {
                            if (@rename($restoreSrc, $basePath)) {
                                write_log($LOG_UPLOAD, "ADMIN RESTORE | IP=$clientIP | DIR=$directory | RESTORED=$file -> $baseFile");
                                $actionMessage = "Restored $file as $baseFile.";
                            } else {
                                $actionMessage = "Failed to restore $file.";
                            }
                        } else {
                            write_log($LOG_UPLOAD, "ADMIN RESTORE MISSING | IP=$clientIP | DIR=$directory | FILE=$file");
                            $actionMessage = "File $file no longer exists.";
                        }
                    } else {
                        $actionMessage = "Selected file is already the current version or not a versioned file.";
                    }
                }
            }
        } elseif ($action === 'create') {
            // CREATE NEW FILE
            $newFileName = $_POST['new_filename'] ?? '';
            $content     = $_POST['file_content'] ?? '';
            $subfolder   = $_POST['subfolder'] ?? '';

            $newFileName = trim($newFileName);
            $subfolder   = trim($subfolder);

            if ($newFileName === '' || preg_match('/[\/\\]/', $newFileName)) {
                $actionMessage = "Invalid file name.";
            } else {
                $baseDir = $dirPath;
                if ($subfolder !== '') {
                    if (strpos($subfolder, '..') !== false || preg_match('/^[\/\\]/', $subfolder)) {
                        $actionMessage = "Invalid subfolder.";
                    } else {
                        $baseDir = rtrim($dirPath, "/\\") . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR;
                        if (!is_dir($baseDir)) {
                            mkdir($baseDir, 0755, true);
                        }
                    }
                }

                $targetFile = $baseDir . $newFileName;

                // Versioning similar to upload.php
                if (file_exists($targetFile)) {
                    $info = pathinfo($newFileName);
                    $base = $info['filename'];
                    $ext  = isset($info['extension']) ? "." . $info['extension'] : "";

                    $counter     = 1;
                    $versionFile = $baseDir . $base . "_v$counter" . $ext;
                    while (file_exists($versionFile)) {
                        $counter++;
                        $versionFile = $baseDir . $base . "_v$counter" . $ext;
                    }
                    @rename($targetFile, $versionFile);
                }

                if (file_put_contents($targetFile, $content) !== false) {
                    chmod($targetFile, 0644);
                    write_log($LOG_UPLOAD, "ADMIN CREATE FILE | IP=$clientIP | DIR=$directory | FILE=$newFileName | SUBFOLDER=$subfolder");
                    $actionMessage = "Created file $newFileName" . ($subfolder ? " in $subfolder" : "") . ".";
                } else {
                    $actionMessage = "Failed to create file $newFileName.";
                }
            }
        } else {
            $actionMessage = "Unknown action.";
        }
    }
}

// Build file list: group current + versions together
$groups = [];

foreach ($allowedDirectories as $dir) {
    $dirPath = __DIR__ . '/../' . $dir . '/';
    if (!is_dir($dirPath)) continue;

    $files = scandir($dirPath);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $dirPath . $f;
        if (!is_file($full)) continue;

        $info = pathinfo($f);
        $name = $info['filename'] ?? '';
        $ext  = isset($info['extension']) ? "." . $info['extension'] : "";

        if (preg_match('/^(.*)_v(\d+)$/', $name, $m)) {
            $baseKey = $m[1] . $ext;
        } else {
            $baseKey = $f;
        }

        $groups[$dir][$baseKey][] = $f;
    }
}

// MFA status for banner
$mfaConfigured = mfa_is_configured();
$mfaVerified   = mfa_is_verified();
$mfaStatusText = $mfaConfigured
    ? ($mfaVerified ? 'Verified' : 'Not Verified')
    : 'Not Configured';
$mfaStatusColor = $mfaConfigured
    ? ($mfaVerified ? 'green' : 'red')
    : 'orange';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin File Manager</title>
    <style>
        table { border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ccc; padding: 4px 8px; }
        th { background: #eee; }
        .current { font-weight: bold; }
        .mfa-banner {
            padding: 6px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <h1>Admin File Manager</h1>
    <p>
        Your IP: <strong><?php echo htmlspecialchars($clientIP); ?></strong>
        <span class="mfa-banner" style="background: <?php echo htmlspecialchars($mfaStatusColor); ?>; color: #fff;">
            MFA Status: <?php echo htmlspecialchars($mfaStatusText); ?>
        </span>
    </p>

    <p>
        <a href="upload_form.php">Go to Upload Form</a> |
        <a href="whitelistme.php">Whitelist My IP</a> |
        <a href="mfa_setup.php">MFA Setup</a>
    </p>

    <?php if ($actionMessage): ?>
        <p><strong><?php echo htmlspecialchars($actionMessage); ?></strong></p>
    <?php endif; ?>

    <?php foreach ($groups as $dir => $families): ?>
        <h2>Directory: <?php echo htmlspecialchars($dir); ?></h2>
        <table>
            <tr>
                <th>Base File</th>
                <th>Versions</th>
            </tr>
            <?php foreach ($families as $baseFile => $fileList): ?>
                <?php
                    sort($fileList);
                    $current   = $baseFile;
                    $versions  = [];
                    foreach ($fileList as $f) {
                        if ($f === $baseFile) continue;
                        $versions[] = $f;
                    }
                ?>
                <tr>
                    <td class="current">
                        <?php echo htmlspecialchars($current); ?>
                    </td>
                    <td>
                        <?php if (empty($versions)): ?>
                            <em>No older versions</em>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($versions as $vf): ?>
                                    <li>
                                        <?php echo htmlspecialchars($vf); ?>
                                        <!-- Restore -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="directory" value="<?php echo htmlspecialchars($dir); ?>">
                                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($vf); ?>">
                                            <button type="submit">Restore</button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($vf); ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="directory" value="<?php echo htmlspecialchars($dir); ?>">
                                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($vf); ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <hr>
    <h2>Create New File</h2>
    <p>MFA is required for creation. If prompted, complete MFA verification.</p>

    <form method="post">
        <input type="hidden" name="action" value="create">

        <label for="directory">Directory :</label>
        <select name="directory" id="directory">
            <?php foreach ($allowedDirectories as $dirOpt): ?>
                <option value="<?php echo htmlspecialchars($dirOpt); ?>"><?php echo htmlspecialchars($dirOpt); ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <label for="subfolder">Subfolder (optional, relative) :</label>
        <input type="text" id="subfolder" name="subfolder" placeholder="e.g. blog or pages/v1">
        <br><br>

        <label for="new_filename">File name (with extension) :</label>
        <input type="text" id="new_filename" name="new_filename" placeholder="index.html or notes.txt" required>
        <br><br>

        <label for="file_content">File content :</label><br>
        <textarea id="file_content" name="file_content" rows="15" cols="100" placeholder="Enter your HTML, JSON, config, etc. here..."></textarea>
        <br><br>

        <button type="submit">Create / Save File</button>
    </form>
</body>
</html>
