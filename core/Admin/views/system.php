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
    $cpuCount = (int) shell_exec('sysctl -n hw.ncpu') ?: 1;
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
?>

<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <h1>✨ Ava <span class="version-badge">v1.0</span></h1>
        </div>
        <nav class="nav">
            <div class="nav-section">Overview</div>
            <a href="<?= $admin_url ?>" class="nav-item">
                <span class="material-symbols-rounded">dashboard</span>
                Dashboard
            </a>

            <div class="nav-section">Content</div>
            <?php foreach ($content as $type => $stats): ?>
            <a href="<?= $admin_url ?>/content/<?= $type ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($type) ?>s
                <span class="nav-count"><?= $stats['total'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Taxonomies</div>
            <?php foreach ($taxonomies as $tax => $count): 
                $tc = $taxonomyConfig[$tax] ?? [];
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= ($tc['hierarchical'] ?? false) ? 'folder' : 'sell' ?></span>
                <?= htmlspecialchars($tc['label'] ?? ucfirst($tax)) ?>
                <span class="nav-count"><?= $count ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Tools</div>
            <a href="<?= $admin_url ?>/lint" class="nav-item">
                <span class="material-symbols-rounded">check_circle</span>
                Lint Content
            </a>
            <a href="<?= $admin_url ?>/shortcodes" class="nav-item">
                <span class="material-symbols-rounded">code</span>
                Shortcodes
            </a>
            <a href="<?= $admin_url ?>/logs" class="nav-item">
                <span class="material-symbols-rounded">history</span>
                Admin Logs
            </a>
            <a href="<?= $admin_url ?>/themes" class="nav-item">
                <span class="material-symbols-rounded">palette</span>
                Themes
            </a>
            <a href="<?= $admin_url ?>/system" class="nav-item active">
                <span class="material-symbols-rounded">dns</span>
                System Info
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="material-symbols-rounded">person</span>
                <?= htmlspecialchars($user ?? 'Admin') ?>
            </div>
            <a href="<?= $admin_url ?>/logout">
                <span class="material-symbols-rounded">logout</span>
                Sign Out
            </a>
        </div>
    </aside>

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
                
                <?php $loadedExtensions = get_loaded_extensions(); sort($loadedExtensions); ?>
                <div class="card-body" style="padding-top: 0;">
                    <div class="extensions-inline">
                        <?php foreach ($loadedExtensions as $ext): ?>
                        <span class="ext-pill"><?= htmlspecialchars($ext) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                    <div class="list-item"><span class="list-label">Debug</span><span class="badge <?= $avaConfig['debug'] ? 'badge-warning' : 'badge-success' ?>"><?= $avaConfig['debug'] ? 'On' : 'Off' ?></span></div>
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
