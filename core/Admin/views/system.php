<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Info · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<?php
$formatBytes = function($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
};

// Calculate metrics
$diskFree = $system['disk_free'] ?? 0;
$diskTotal = $system['disk_total'] ?? 1;
$diskUsed = $diskTotal - $diskFree;
$diskPercent = min(100, round(($diskUsed / $diskTotal) * 100));

$memUsed = $system['memory_used'];
$memLimit = ini_get('memory_limit');
$memLimitBytes = preg_match('/(\d+)([MG])/i', $memLimit, $m) 
    ? $m[1] * ($m[2] === 'G' || $m[2] === 'g' ? 1073741824 : 1048576) 
    : 134217728;
$memPercent = min(100, round(($memUsed / $memLimitBytes) * 100));

$load1 = $system['load_avg'][0] ?? null;
$load5 = $system['load_avg'][1] ?? null;
$load15 = $system['load_avg'][2] ?? null;

// Check if load averages are actually available (not null/empty array)
$loadAvailable = $load1 !== null && $system['load_avg'] !== null;
if (!$loadAvailable) {
    // Try to get load from /proc/loadavg on Linux
    if (is_readable('/proc/loadavg')) {
        $loadContent = @file_get_contents('/proc/loadavg');
        if ($loadContent !== false) {
            $parts = explode(' ', trim($loadContent));
            if (count($parts) >= 3) {
                $load1 = (float) $parts[0];
                $load5 = (float) $parts[1];
                $load15 = (float) $parts[2];
                $loadAvailable = true;
            }
        }
    }
}

$cpuCount = 1;
if (is_readable('/proc/cpuinfo')) {
    $cpuCount = max(1, substr_count(file_get_contents('/proc/cpuinfo'), 'processor'));
} elseif (PHP_OS_FAMILY === 'Darwin') {
    try {
        $result = @shell_exec('sysctl -n hw.ncpu');
        $cpuCount = $result !== null ? ((int) $result ?: 1) : 1;
    } catch (\Throwable) {
        $cpuCount = 1;
    }
}

$uptime = $system['uptime'] ?? null;
$uptimeFormatted = 'Unknown';
if ($uptime !== null) {
    $days = floor($uptime / 86400);
    $hours = floor(($uptime % 86400) / 3600);
    $mins = floor(($uptime % 3600) / 60);
    if ($days > 0) {
        $uptimeFormatted = $days . 'd ' . $hours . 'h';
    } elseif ($hours > 0) {
        $uptimeFormatted = $hours . 'h ' . $mins . 'm';
    } else {
        $uptimeFormatted = $mins . ' min';
    }
}

$loadColor = function($val) use ($cpuCount) {
    $normalized = $val / max(1, $cpuCount);
    if ($normalized < 0.5) return 'success';
    if ($normalized < 0.8) return 'warning';
    return 'danger';
};
$activePage = 'system';
?>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="layout">
    <main class="main">
        <div class="mobile-header">
            <button class="menu-btn" onclick="toggleSidebar()">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <h1>System Info</h1>
        </div>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded">dns</span>
                System Info
            </h2>
            <div class="header-actions">
                <a href="https://adamgreenough.github.io/ava/" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">menu_book</span>
                    <span class="hide-mobile">Docs</span>
                </a>
                <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">open_in_new</span>
                    <span class="hide-mobile">View Site</span>
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">schedule</span> Uptime</div>
                <div class="stat-value"><?= $uptimeFormatted ?></div>
                <div class="stat-meta">Since boot</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">memory</span> Memory</div>
                <div class="stat-value <?= $memPercent > 80 ? 'text-danger' : ($memPercent > 50 ? 'text-warning' : 'text-success') ?>"><?= $memPercent ?>%</div>
                <div class="stat-meta"><?= $formatBytes($memUsed) ?> / <?= $memLimit ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">storage</span> Disk</div>
                <div class="stat-value <?= $diskPercent > 90 ? 'text-danger' : ($diskPercent > 75 ? 'text-warning' : 'text-success') ?>"><?= $diskPercent ?>%</div>
                <div class="stat-meta"><?= $formatBytes($diskUsed) ?> used</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">speed</span> Load</div>
                <?php if ($loadAvailable): ?>
                <div class="stat-value <?= $load1 > $cpuCount ? 'text-danger' : 'text-success' ?>"><?= number_format($load1, 2) ?></div>
                <?php else: ?>
                <div class="stat-value text-tertiary">N/A</div>
                <?php endif; ?>
                <div class="stat-meta"><?= $cpuCount ?> CPU<?= $cpuCount > 1 ? 's' : '' ?></div>
            </div>
        </div>

        <!-- Server Load & Memory -->
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">speed</span> Server Load</span>
                    <span class="badge badge-muted"><?= $cpuCount ?> cores</span>
                </div>
                <div class="card-body">
                    <?php if ($loadAvailable): ?>
                    <div class="progress-row">
                        <span class="label">1 min</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $loadColor($load1) ?>" style="width: <?= min(100, ($load1 / max(1, $cpuCount)) * 100) ?>%"></div></div></div>
                        <span class="value"><?= number_format($load1, 2) ?></span>
                    </div>
                    <div class="progress-row">
                        <span class="label">5 min</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $loadColor($load5) ?>" style="width: <?= min(100, ($load5 / max(1, $cpuCount)) * 100) ?>%"></div></div></div>
                        <span class="value"><?= number_format($load5, 2) ?></span>
                    </div>
                    <div class="progress-row">
                        <span class="label">15 min</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $loadColor($load15) ?>" style="width: <?= min(100, ($load15 / max(1, $cpuCount)) * 100) ?>%"></div></div></div>
                        <span class="value"><?= number_format($load15, 2) ?></span>
                    </div>
                    <?php else: ?>
                    <p class="text-tertiary text-sm">Load average not available on this system.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">memory</span> Memory</span>
                    <span class="badge badge-muted"><?= $memLimit ?> limit</span>
                </div>
                <div class="card-body">
                    <?php $memPeak = $system['memory_peak']; $peakPercent = min(100, round(($memPeak / $memLimitBytes) * 100)); ?>
                    <div class="progress-row">
                        <span class="label">Current</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $memPercent > 80 ? 'danger' : ($memPercent > 50 ? 'warning' : 'success') ?>" style="width: <?= $memPercent ?>%"></div></div></div>
                        <span class="value"><?= $formatBytes($memUsed) ?></span>
                    </div>
                    <div class="progress-row">
                        <span class="label">Peak</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill accent" style="width: <?= $peakPercent ?>%"></div></div></div>
                        <span class="value"><?= $formatBytes($memPeak) ?></span>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Available</span>
                        <span class="list-value"><?= $formatBytes($memLimitBytes - $memUsed) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Storage & PHP -->
        <div class="grid grid-2 mt-4">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">storage</span> Storage</span>
                    <span class="badge <?= $diskPercent > 90 ? 'badge-danger' : 'badge-success' ?>"><?= $diskPercent ?>%</span>
                </div>
                <div class="card-body">
                    <div class="progress-row">
                        <span class="label">Disk</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $diskPercent > 90 ? 'danger' : 'success' ?>" style="width: <?= $diskPercent ?>%"></div></div></div>
                        <span class="value"><?= $formatBytes($diskUsed) ?></span>
                    </div>
                    <div class="list-item"><span class="list-label">Free</span><span class="list-value"><?= $formatBytes($diskFree) ?></span></div>
                    <div class="list-item"><span class="list-label">Total</span><span class="list-value"><?= $formatBytes($diskTotal) ?></span></div>
                    <div class="list-item"><span class="list-label">Content</span><span class="list-value"><?= $formatBytes($system['content_size'] ?? 0) ?></span></div>
                    <div class="list-item"><span class="list-label">Cache</span><span class="list-value"><?= $formatBytes($system['storage_size'] ?? 0) ?></span></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">code</span> PHP</span>
                    <span class="badge badge-accent"><?= PHP_VERSION ?></span>
                </div>
                <div class="card-body">
                    <div class="list-item"><span class="list-label">SAPI</span><code><?= php_sapi_name() ?></code></div>
                    <div class="list-item"><span class="list-label">Memory Limit</span><span class="list-value"><?= ini_get('memory_limit') ?></span></div>
                    <div class="list-item"><span class="list-label">Max Execution</span><span class="list-value"><?= ini_get('max_execution_time') ?>s</span></div>
                    <div class="list-item"><span class="list-label">Upload Max</span><span class="list-value"><?= ini_get('upload_max_filesize') ?></span></div>
                </div>
            </div>
        </div>

        <!-- Cache Files -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">database</span> Cache Files</span>
                <?php 
                $activeBackend = $cacheFiles['content_index.sqlite']['exists'] ? 'sqlite' : 'array';
                $totalCacheSize = array_sum(array_column($cacheFiles, 'size'));
                ?>
                <span class="badge badge-muted"><?= $formatBytes($totalCacheSize) ?> total</span>
            </div>
            <div class="table-wrap">
                <table class="dir-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Description</th>
                            <th>Size</th>
                            <th>Format</th>
                            <th>Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cacheFiles as $file): 
                            // Skip SQLite if using array backend, and vice versa
                            if (isset($file['backend'])) {
                                if ($file['backend'] === 'sqlite' && $activeBackend === 'array' && !$file['exists']) continue;
                                if ($file['backend'] === 'array' && $activeBackend === 'sqlite' && !$file['exists']) continue;
                            }
                        ?>
                        <tr>
                            <td>
                                <code class="text-sm"><?= htmlspecialchars($file['filename']) ?></code>
                            </td>
                            <td class="text-dim text-sm"><?= htmlspecialchars($file['description']) ?></td>
                            <td>
                                <?php if ($file['exists']): ?>
                                    <?php if (isset($file['count'])): ?>
                                        <span class="text-sm"><?= number_format($file['count']) ?> files (<?= $file['size_formatted'] ?>)</span>
                                    <?php else: ?>
                                        <span class="text-sm"><?= $file['size_formatted'] ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($file['format'])): ?>
                                    <span class="badge <?= $file['format'] === 'igbinary' ? 'badge-success' : 'badge-muted' ?>"><?= $file['format'] ?></span>
                                <?php elseif (str_ends_with($file['filename'], '.json')): ?>
                                    <span class="badge badge-muted">json</span>
                                <?php elseif (str_ends_with($file['filename'], '.sqlite')): ?>
                                    <span class="badge badge-accent">sqlite</span>
                                <?php elseif (str_ends_with($file['filename'], '/')): ?>
                                    <span class="badge badge-muted">html</span>
                                <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($file['modified']): ?>
                                    <span class="text-xs text-tertiary"><?= $file['modified'] ?></span>
                                <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PHP Extensions Checklist -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">extension</span> PHP Extensions</span>
                <?php
                // Check opcache properly (it's a Zend extension)
                $opcacheInstalled = function_exists('opcache_get_status') && (
                    (PHP_SAPI !== 'cli' && ini_get('opcache.enable')) ||
                    (PHP_SAPI === 'cli' && ini_get('opcache.enable_cli'))
                );
                
                $requiredExts = [
                    'mbstring' => ['required' => true, 'desc' => 'UTF-8 text handling', 'loaded' => extension_loaded('mbstring')],
                    'json' => ['required' => true, 'desc' => 'Config and API responses', 'loaded' => extension_loaded('json')],
                    'ctype' => ['required' => true, 'desc' => 'String validation', 'loaded' => extension_loaded('ctype')],
                ];
                $recommendedExts = [
                    'igbinary' => ['required' => false, 'desc' => 'Faster cache serialization (15× faster, 90% smaller)', 'loaded' => extension_loaded('igbinary')],
                    'opcache' => ['required' => false, 'desc' => 'Opcode caching for production', 'loaded' => $opcacheInstalled],
                    'curl' => ['required' => false, 'desc' => 'HTTP requests for updates', 'loaded' => extension_loaded('curl')],
                    'gd' => ['required' => false, 'desc' => 'Image processing', 'loaded' => extension_loaded('gd')],
                    'intl' => ['required' => false, 'desc' => 'Internationalization', 'loaded' => extension_loaded('intl')],
                ];
                $allExts = array_merge($requiredExts, $recommendedExts);
                $requiredOk = count(array_filter($requiredExts, fn($e) => $e['loaded']));
                $recommendedOk = count(array_filter($recommendedExts, fn($e) => $e['loaded']));
                ?>
                <span class="badge <?= $requiredOk === count($requiredExts) ? 'badge-success' : 'badge-danger' ?>">
                    <?= $requiredOk ?>/<?= count($requiredExts) ?> required
                </span>
            </div>
            <div class="table-wrap">
                <table class="dir-table">
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>Purpose</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allExts as $ext => $info): 
                            $loaded = $info['loaded'];
                            $isRequired = $info['required'];
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($ext) ?></code></td>
                            <td class="text-dim text-sm"><?= htmlspecialchars($info['desc']) ?></td>
                            <td>
                                <span class="badge <?= $isRequired ? 'badge-accent' : 'badge-muted' ?>">
                                    <?= $isRequired ? 'Required' : 'Recommended' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($loaded): ?>
                                    <span class="badge badge-success">
                                        <span class="material-symbols-rounded" style="font-size: 14px;">check</span>
                                        Installed
                                    </span>
                                <?php elseif ($isRequired): ?>
                                    <span class="badge badge-danger">
                                        <span class="material-symbols-rounded" style="font-size: 14px;">close</span>
                                        Missing
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <span class="material-symbols-rounded" style="font-size: 14px;">remove</span>
                                        Not installed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- OPcache & Server -->
        <div class="grid grid-2 mt-4">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">bolt</span> OPcache</span>
                    <span class="badge <?= ($system['opcache']['enabled'] ?? false) ? 'badge-success' : 'badge-muted' ?>">
                        <?= ($system['opcache']['enabled'] ?? false) ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($system['opcache'] && $system['opcache']['enabled']): 
                        $opcache = $system['opcache'];
                        $hitRate = $opcache['hit_rate'];
                    ?>
                    <div class="progress-row">
                        <span class="label">Hit Rate</span>
                        <div class="bar"><div class="progress-bar"><div class="progress-fill <?= $hitRate > 90 ? 'success' : 'warning' ?>" style="width: <?= $hitRate ?>%"></div></div></div>
                        <span class="value"><?= $hitRate ?>%</span>
                    </div>
                    <div class="list-item"><span class="list-label">Cached Scripts</span><span class="list-value"><?= number_format($opcache['cached_scripts'] ?? 0) ?></span></div>
                    <div class="list-item"><span class="list-label">Memory Used</span><span class="list-value"><?= $formatBytes($opcache['memory_used']) ?></span></div>
                    <?php else: ?>
                    <p class="text-dim text-sm">OPcache not enabled. Enable for better performance.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">dns</span> Server</span>
                </div>
                <div class="card-body">
                    <div class="list-item"><span class="list-label">Software</span><span class="list-value"><?= htmlspecialchars(explode('/', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')[0]) ?></span></div>
                    <div class="list-item"><span class="list-label">OS</span><span class="list-value"><?= PHP_OS_FAMILY ?></span></div>
                    <div class="list-item"><span class="list-label">Hostname</span><code class="text-xs"><?= htmlspecialchars($system['hostname'] ?? 'Unknown') ?></code></div>
                    <div class="list-item"><span class="list-label">Timezone</span><span class="list-value"><?= date_default_timezone_get() ?></span></div>
                </div>
            </div>
        </div>

        <!-- Ava Config & Content Stats -->
        <div class="grid grid-2 mt-4">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">settings</span> Ava Config</span>
                </div>
                <div class="card-body">
                    <div class="list-item"><span class="list-label">Site Name</span><span class="list-value"><?= htmlspecialchars($avaConfig['site_name'] ?? 'Untitled') ?></span></div>
                    <div class="list-item"><span class="list-label">Theme</span><span class="badge badge-accent"><?= htmlspecialchars($avaConfig['theme']) ?></span></div>
                    <div class="list-item"><span class="list-label">Cache Mode</span><code><?= htmlspecialchars($avaConfig['cache_mode']) ?></code></div>
                    <div class="list-item"><span class="list-label">Admin Path</span><code><?= htmlspecialchars($avaConfig['admin_path']) ?></code></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">analytics</span> Content Stats</span>
                </div>
                <div class="card-body">
                    <div class="list-item"><span class="list-label">Content Types</span><span class="list-value"><?= $avaConfig['content_types'] ?></span></div>
                    <div class="list-item"><span class="list-label">Taxonomies</span><span class="list-value"><?= $avaConfig['taxonomies'] ?></span></div>
                    <div class="list-item"><span class="list-label">Plugins</span><span class="list-value"><?= $avaConfig['plugins'] ?></span></div>
                    <div class="list-item"><span class="list-label">Cache</span><span class="badge <?= $cache['fresh'] ? 'badge-success' : 'badge-warning' ?>"><?= $cache['fresh'] ? 'Fresh' : 'Stale' ?></span></div>
                    <div class="list-item"><span class="list-label">Last Built</span><span class="list-value text-sm"><?= htmlspecialchars($cache['built_at'] ?? 'Never') ?></span></div>
                </div>
            </div>
        </div>

        <!-- Debug & Performance -->
        <div class="grid grid-2 mt-4">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">bug_report</span> Debug Mode</span>
                    <span class="badge <?= $debugInfo['enabled'] ? 'badge-warning' : 'badge-success' ?>"><?= $debugInfo['enabled'] ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <div class="card-body">
                    <div class="list-item"><span class="list-label">Display Errors</span><span class="badge <?= $debugInfo['display_errors'] ? 'badge-error' : 'badge-success' ?>"><?= $debugInfo['display_errors'] ? 'Yes' : 'No' ?></span></div>
                    <div class="list-item"><span class="list-label">Log Errors</span><span class="badge <?= $debugInfo['log_errors'] ? 'badge-success' : 'badge-muted' ?>"><?= $debugInfo['log_errors'] ? 'Yes' : 'No' ?></span></div>
                    <div class="list-item"><span class="list-label">Error Level</span><code><?= htmlspecialchars($debugInfo['level']) ?></code></div>
                    <div class="list-item"><span class="list-label">Error Log</span><code class="text-xs"><?= htmlspecialchars($debugInfo['error_log_path']) ?></code></div>
                    <?php if ($debugInfo['error_log_size'] > 0): ?>
                    <div class="list-item"><span class="list-label">Log Size</span><span class="list-value"><?= number_format($debugInfo['error_log_size'] / 1024, 1) ?> KB (<?= number_format($debugInfo['error_log_lines']) ?> lines)</span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">speed</span> Request Performance</span>
                </div>
                <div class="card-body">
                    <?php if ($debugInfo['request_time'] !== null): ?>
                    <div class="list-item"><span class="list-label">Request Time</span><span class="list-value"><?= $debugInfo['request_time'] ?> ms</span></div>
                    <?php endif; ?>
                    <div class="list-item"><span class="list-label">Memory Used</span><span class="list-value"><?= number_format($debugInfo['memory_usage'] / 1024 / 1024, 2) ?> MB</span></div>
                    <div class="list-item"><span class="list-label">Memory Peak</span><span class="list-value"><?= number_format($debugInfo['memory_peak'] / 1024 / 1024, 2) ?> MB</span></div>
                    <div class="list-item"><span class="list-label">PHP Error Reporting</span><code class="text-xs"><?= $debugInfo['php_error_reporting'] ?></code></div>
                </div>
            </div>
        </div>

        <?php if (!empty($debugInfo['recent_errors'])): ?>
        <!-- Recent Errors -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">error</span> Recent Errors</span>
                <span class="badge badge-error"><?= count($debugInfo['recent_errors']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="160">Time</th>
                            <th width="80">Level</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debugInfo['recent_errors'] as $error): 
                            $hasTrace = str_contains($error['message'], "\n");
                            $mainMessage = $hasTrace ? explode("\n", $error['message'])[0] : $error['message'];
                            $trace = $hasTrace ? implode("\n", array_slice(explode("\n", $error['message']), 1)) : '';
                        ?>
                        <tr>
                            <td><code class="text-xs"><?= htmlspecialchars($error['time']) ?></code></td>
                            <td>
                                <span class="badge <?= match($error['level']) {
                                    'ERROR', 'EXCEPTION' => 'badge-error',
                                    'WARNING' => 'badge-warning',
                                    'NOTICE', 'DEPRECATED' => 'badge-muted',
                                    default => 'badge-muted',
                                } ?>"><?= htmlspecialchars($error['level']) ?></span>
                            </td>
                            <td>
                                <code class="text-xs" style="word-break: break-all;"><?= htmlspecialchars($mainMessage) ?></code>
                                <?php if ($hasTrace): ?>
                                <details style="margin-top: var(--sp-2);">
                                    <summary class="text-xs text-tertiary" style="cursor: pointer;">Stack trace</summary>
                                    <pre class="text-xs text-tertiary" style="margin-top: var(--sp-1); white-space: pre-wrap; font-size: 10px;"><?= htmlspecialchars($trace) ?></pre>
                                </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Directory Status -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">folder</span> Directory Status</span>
                <?php 
                // Count truly OK directories (exists, is_dir, writable if needed, correct perms)
                $dirOk = count(array_filter($directories, function($d) {
                    if (!$d['exists'] || !$d['is_dir']) return false;
                    if ($d['writable_needed'] && !$d['writable']) return false;
                    if ($d['permissions'] !== $d['recommended']) return false;
                    return true;
                })); 
                ?>
                <span class="badge <?= $dirOk === count($directories) ? 'badge-success' : 'badge-warning' ?>"><?= $dirOk ?>/<?= count($directories) ?> OK</span>
            </div>
            <div class="table-wrap">
                <table class="dir-table">
                    <thead>
                        <tr>
                            <th>Directory</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Perms</th>
                            <th>Recommended</th>
                            <th>Owner</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directories as $name => $dir): ?>
                        <tr>
                            <td>
                                <span class="dir-path">
                                    <span class="material-symbols-rounded"><?= $dir['exists'] ? 'folder' : 'folder_off' ?></span>
                                    <code><?= htmlspecialchars($dir['relative']) ?></code>
                                </span>
                            </td>
                            <td class="text-dim text-sm"><?= htmlspecialchars($dir['description']) ?></td>
                            <td>
                                <?php 
                                $permMismatch = $dir['exists'] && $dir['permissions'] !== $dir['recommended'];
                                if (!$dir['exists']): ?>
                                    <span class="badge badge-warning">Missing</span>
                                <?php elseif (!$dir['is_dir']): ?>
                                    <span class="badge badge-danger">Not dir</span>
                                <?php elseif ($dir['writable_needed'] && !$dir['writable']): ?>
                                    <span class="badge badge-danger">Not writable</span>
                                <?php elseif ($permMismatch): ?>
                                    <span class="badge badge-warning">Perms differ</span>
                                <?php else: ?>
                                    <span class="badge badge-success">OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dir['exists']): ?>
                                    <code class="<?= $dir['permissions'] !== $dir['recommended'] ? 'perm-diff' : '' ?>"><?= $dir['permissions'] ?></code>
                                <?php else: ?>
                                    <span class="text-dim">—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= $dir['recommended'] ?></code></td>
                            <td class="text-dim text-sm"><?= $dir['exists'] && $dir['owner'] ? htmlspecialchars($dir['owner'] . ':' . $dir['group']) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Path Aliases & Hooks -->
        <div class="grid grid-2 mt-4">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">link</span> Path Aliases</span>
                    <span class="badge badge-muted"><?= count($pathAliases) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($pathAliases)): ?>
                        <?php foreach ($pathAliases as $alias => $expansion): ?>
                        <div class="list-item">
                            <code><?= htmlspecialchars($alias) ?></code>
                            <span class="list-value text-sm"><?= htmlspecialchars($expansion) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-dim text-sm">No path aliases configured.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">webhook</span> Plugin Hooks</span>
                    <?php $activeHooks = count($hooks['active_filters']) + count($hooks['active_actions']); ?>
                    <span class="badge <?= $activeHooks > 0 ? 'badge-success' : 'badge-muted' ?>"><?= $activeHooks ?> active</span>
                </div>
                <details>
                    <summary>
                        <span class="material-symbols-rounded">chevron_right</span>
                        Filters
                        <span class="badge badge-muted count"><?= count($hooks['filters']) ?></span>
                    </summary>
                    <div class="detail-content">
                        <div class="hook-list">
                            <?php foreach ($hooks['filters'] as $hook => $desc): 
                                $isActive = in_array($hook, $hooks['active_filters']);
                            ?>
                            <div class="hook-item">
                                <code><?= htmlspecialchars($hook) ?></code>
                                <?php if ($isActive): ?><span class="badge badge-success">active</span><?php endif; ?>
                                <span class="hook-desc"><?= htmlspecialchars($desc) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
                <details>
                    <summary>
                        <span class="material-symbols-rounded">chevron_right</span>
                        Actions
                        <span class="badge badge-muted count"><?= count($hooks['actions']) ?></span>
                    </summary>
                    <div class="detail-content">
                        <div class="hook-list">
                            <?php foreach ($hooks['actions'] as $hook => $desc): 
                                $isActive = in_array($hook, $hooks['active_actions']);
                            ?>
                            <div class="hook-item">
                                <code><?= htmlspecialchars($hook) ?></code>
                                <?php if ($isActive): ?><span class="badge badge-success">active</span><?php endif; ?>
                                <span class="hook-desc"><?= htmlspecialchars($desc) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('open');
}
</script>
</body>
</html>
