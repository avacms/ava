<?php

declare(strict_types=1);

namespace Ava\Admin;

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;
use Ava\Support\Path;

/**
 * Admin Controller
 *
 * Dashboard + content management with security validation.
 * Content edits go through security linting to prevent XSS.
 */
final class Controller
{
    private Application $app;
    private Auth $auth;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->auth = new Auth($app->path('app/config/users.php'), $app->path('storage'));
    }

    /**
     * Get the auth instance.
     */
    public function auth(): Auth
    {
        return $this->auth;
    }

    /**
     * Login page.
     */
    public function login(Request $request): Response
    {
        // Already logged in?
        if ($this->auth->check()) {
            return Response::redirect($this->adminUrl());
        }

        $error = null;

        // Check if IP is locked out
        if ($this->auth->isLockedOut()) {
            $remaining = $this->auth->getLockoutRemaining();
            $minutes = (int) ceil($remaining / 60);
            $error = "Too many login attempts. Please try again in {$minutes} minute(s).";

            return Response::html($this->render('login', [
                'error' => $error,
                'csrf' => $this->auth->csrfToken(),
                'loginUrl' => $this->adminUrl() . '/login',
                'hasUsers' => $this->auth->hasUsers(),
                'isLockedOut' => true,
                'adminTheme' => $this->app->config('admin.theme', 'cyan'),
            ]));
        }

        // Handle login attempt
        if ($request->isMethod('POST')) {
            $csrf = $request->post('_csrf', '');
            if (!$this->auth->verifyCsrf($csrf)) {
                $error = 'Invalid request. Please try again.';
                $this->logAction('WARNING', 'Login failed: Invalid CSRF token');
            } else {
                $email = $request->post('email', '');
                $password = $request->post('password', '');

                if ($this->auth->attempt($email, $password)) {
                    $this->auth->regenerateCsrf();
                    $this->logAction('INFO', 'Login successful: ' . $email);
                    return Response::redirect($this->adminUrl());
                }

                // Set error for failed attempt
                $error = 'Invalid email or password.';
                $this->logAction('WARNING', 'Login failed for: ' . $email);
            }
        }

        return Response::html($this->render('login', [
            'error' => $error,
            'csrf' => $this->auth->csrfToken(),
            'loginUrl' => $this->adminUrl() . '/login',
            'hasUsers' => $this->auth->hasUsers(),
            'isLockedOut' => false,
            'adminTheme' => $this->app->config('admin.theme', 'cyan'),
        ]));
    }

    /**
     * Logout action.
     */
    public function logout(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return Response::redirect($this->adminUrl());
        }

        $csrf = $request->post('_csrf', '');
        if (!$this->auth->verifyCsrf($csrf)) {
            return Response::redirect($this->adminUrl() . '?error=csrf');
        }

        $user = $this->auth->user();
        $this->auth->logout();
        $this->logAction('INFO', 'Logout: ' . ($user ?? 'Unknown user'));
        return Response::redirect($this->adminUrl() . '/login');
    }

    /**
     * Dashboard - main admin page.
     */
    public function dashboard(Request $request): Response
    {
        $repository = $this->app->repository();
        
        // Get custom admin pages registered by plugins
        $customPages = Hooks::apply('admin.register_pages', [], $this->app);
        
        // Get custom sidebar items
        $customSidebarItems = Hooks::apply('admin.sidebar_items', [], $this->app);
        
        // Check for updates (cached, non-blocking)
        $updateCheck = null;
        try {
            $updater = new \Ava\Updater($this->app);
            $updateCheck = $updater->check(false); // Use cached result
        } catch (\Exception $e) {
            // Silently ignore update check failures
        }
        
        // Check for recent errors (last 24 hours)
        $recentErrorCount = $this->getRecentErrorCount();
        
        // Get media stats for dashboard
        $mediaStats = $this->getMediaStats();
        
        $data = [
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
                'timezone' => $this->app->config('site.timezone', 'UTC'),
            ],
            'cache' => $this->getCacheStatus(),
            'webpageCache' => $this->app->webpageCache()->stats(),
            'content' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyTerms' => $this->getTaxonomyTerms(),
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'system' => $this->getSystemInfoBasic(),
            'recentContent' => $this->getRecentContent(),
            'plugins' => $this->getActivePlugins(),
            'users' => $this->auth->allUsers(),
            'theme' => $this->app->config('theme', 'default'),
            'csrf' => $this->auth->csrfToken(),
            'user' => $this->auth->user(),
            'contentTypes' => $this->getContentTypeConfig(),
            'routes' => $repository->routes(),
            'customPages' => $customPages,
            'customSidebarItems' => $customSidebarItems,
            'version' => AVA_VERSION,
            'updateCheck' => $updateCheck,
            'recentErrorCount' => $recentErrorCount,
            'mediaStats' => $mediaStats,
        ];

        $layout = [
            'title' => 'Dashboard',
            'icon' => 'dashboard',
            'activePage' => 'dashboard',
            'headerActions' => $this->defaultHeaderActions('admin'),
        ];

        return Response::html($this->render('content/dashboard', $data, $layout));
    }

    /**
     * Rebuild cache action.
     */
    public function rebuild(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return Response::redirect($this->adminUrl());
        }

        // CSRF check
        $csrf = $request->post('_csrf', '');
        if (!$this->auth->verifyCsrf($csrf)) {
            return Response::redirect($this->adminUrl() . '?error=csrf');
        }

        $keepWebpageCache = $request->post('keep_webpage_cache', '') === '1';

        $start = microtime(true);
        $this->app->indexer()->rebuild(clearWebpageCache: !$keepWebpageCache);
        $elapsed = round((microtime(true) - $start) * 1000);

        $this->auth->regenerateCsrf();
        $keepFlag = $keepWebpageCache ? '&keep_webpage_cache=1' : '';
        return Response::redirect($this->adminUrl() . '?action=rebuild&time=' . $elapsed . $keepFlag);
    }

    /**
     * Flush webpage cache action.
     */
    public function flushWebpageCache(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return Response::redirect($this->adminUrl());
        }

        // CSRF check
        $csrf = $request->post('_csrf', '');
        if (!$this->auth->verifyCsrf($csrf)) {
            return Response::redirect($this->adminUrl() . '?error=csrf');
        }

        $count = $this->app->webpageCache()->clear();

        $this->auth->regenerateCsrf();
        return Response::redirect($this->adminUrl() . '?action=flush_pages&count=' . $count);
    }

    /**
     * Content list page.
     */
    public function contentList(Request $request, string $type): ?Response
    {
        $repository = $this->app->repository();
        $types = $repository->types();

        // Check if type exists
        if (!in_array($type, $types)) {
            return null; // 404
        }

        // Use allMeta() - no file content loading needed
        $allItems = $repository->allMeta($type);

        // Sort by date descending
        usort($allItems, function($a, $b) {
            $aDate = $a->date();
            $bDate = $b->date();
            if (!$aDate && !$bDate) return 0;
            if (!$aDate) return 1;
            if (!$bDate) return -1;
            return $bDate->getTimestamp() - $aDate->getTimestamp();
        });

        $contentTypes = $this->getContentTypeConfig();
        $typeConfig = $contentTypes[$type] ?? [];

        // Pagination
        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $totalItems = count($allItems);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allItems, $offset, $perPage);

        // Calculate total file size (filesystem metadata only, no content reading)
        $totalSize = 0;
        foreach ($allItems as $item) {
            if (file_exists($item->filePath())) {
                $totalSize += filesize($item->filePath());
            }
        }

        $data = [
            'type' => $type,
            'items' => $items,
            'allContent' => $this->getContentStats(),
            'typeConfig' => $typeConfig,
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'taxonomyTerms' => $this->getTaxonomyTerms(),
            'routes' => $repository->routes(),
            'contentTypes' => $contentTypes,
            'stats' => [
                'totalSize' => $totalSize,
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasMore' => $page < $totalPages,
                'hasPrev' => $page > 1,
            ],
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $archiveUrl = $typeConfig['url']['archive'] ?? null;
        $headerActions = '<a href="' . $this->adminUrl() . '/content/' . htmlspecialchars($type) . '/create" class="btn btn-primary btn-sm"><span class="material-symbols-rounded">add</span>New</a>';
        if ($archiveUrl) {
            $headerActions .= '<a href="' . htmlspecialchars($this->app->config('site.base_url') . $archiveUrl) . '" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">list</span>Archive</a>';
        }
        $headerActions .= $this->defaultHeaderActions('content');

        $layout = [
            'title' => $typeConfig['label'] ?? ucfirst($type),
            'icon' => $type === 'page' ? 'description' : 'article',
            'activePage' => 'content-' . $type,
            'headerActions' => $headerActions,
        ];

        return Response::html($this->render('content/content-list', $data, $layout));
    }

    /**
     * Taxonomy detail page.
     */
    public function taxonomyDetail(Request $request, string $taxonomy): ?Response
    {
        $repository = $this->app->repository();
        $taxonomies = $repository->taxonomies();

        // Check if taxonomy exists
        if (!in_array($taxonomy, $taxonomies)) {
            return null; // 404
        }

        $terms = $repository->terms($taxonomy);
        $taxonomyConfig = $this->getTaxonomyConfig();
        $config = $taxonomyConfig[$taxonomy] ?? [];

        // Calculate stats for each term (using cached term data, no file I/O)
        $termStats = [];
        foreach ($terms as $slug => $termData) {
            $itemCount = count($termData['items'] ?? []);
            $termStats[$slug] = [
                'name' => $termData['name'] ?? $slug,
                'slug' => $slug,
                'count' => $itemCount,
                'items' => $termData['items'] ?? [],
            ];
        }

        // Sort terms by count descending
        uasort($termStats, fn($a, $b) => $b['count'] - $a['count']);

        $data = [
            'taxonomy' => $taxonomy,
            'terms' => $termStats,
            'config' => $config,
            'allContent' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyConfig' => $taxonomyConfig,
            'routes' => $repository->routes(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $taxBase = rtrim($this->app->config('site.base_url'), '/') . ($taxonomyConfig[$taxonomy]['rewrite']['base'] ?? '/' . $taxonomy);
        $isHierarchical = $taxonomyConfig[$taxonomy]['hierarchical'] ?? false;

        $headerActions = '<a href="' . $this->adminUrl() . '/taxonomy/' . htmlspecialchars($taxonomy) . '/create" class="btn btn-primary btn-sm"><span class="material-symbols-rounded">add</span>New Term</a>';
        $headerActions .= '<a href="' . htmlspecialchars($taxBase) . '" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">visibility</span>View Archive</a>';
        $headerActions .= $this->defaultHeaderActions('content');

        $layout = [
            'title' => $taxonomyConfig[$taxonomy]['label'] ?? ucfirst($taxonomy),
            'icon' => $isHierarchical ? 'folder' : 'sell',
            'activePage' => 'taxonomy-' . $taxonomy,
            'headerActions' => $headerActions,
        ];

        return Response::html($this->render('content/taxonomy', $data, $layout));
    }

    /**
     * Create a new taxonomy term.
     */
    public function taxonomyTermCreate(Request $request, string $taxonomy): ?Response
    {
        $repository = $this->app->repository();
        $taxonomies = $repository->taxonomies();

        // Check if taxonomy exists
        if (!in_array($taxonomy, $taxonomies)) {
            return null; // 404
        }

        $taxonomyConfig = $this->getTaxonomyConfig();
        $config = $taxonomyConfig[$taxonomy] ?? [];
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            // CSRF check
            $csrf = $request->post('_csrf', '');
            if (!$this->auth->verifyCsrf($csrf)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $name = trim($request->post('name', ''));
                $slug = trim($request->post('slug', ''));
                $description = trim($request->post('description', ''));

                // Validate name
                if (empty($name)) {
                    $error = 'Term name is required.';
                } else {
                    // Generate slug if not provided
                    if (empty($slug)) {
                        $slug = $this->generateSlug($name);
                    } else {
                        $slug = $this->sanitizeSlug($slug);
                    }

                    // Validate slug format
                    if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug)) {
                        $error = 'Slug must be lowercase alphanumeric with hyphens only.';
                    } else {
                        // Check for duplicate slug
                        $existingTerms = $repository->terms($taxonomy);
                        if (isset($existingTerms[$slug])) {
                            $error = "A term with slug '{$slug}' already exists.";
                        } else {
                            // Add term to taxonomy file
                            $result = $this->addTaxonomyTerm($taxonomy, $slug, $name, $description);
                            if ($result === true) {
                                $this->auth->regenerateCsrf();
                                $this->logAction('INFO', "Created term '{$name}' in taxonomy '{$taxonomy}'");
                                
                                // Rebuild cache to include new term
                                $this->app->indexer()->rebuild();
                                
                                return Response::redirect($this->adminUrl() . '/taxonomy/' . $taxonomy . '?created=' . urlencode($name));
                            } else {
                                $error = $result;
                            }
                        }
                    }
                }
            }
        }

        $data = [
            'taxonomy' => $taxonomy,
            'config' => $config,
            'error' => $error,
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'New Term',
            'heading' => 'Create ' . ($taxonomyConfig[$taxonomy]['label'] ?? ucfirst($taxonomy)) . ' Term',
            'icon' => 'add',
            'activePage' => 'taxonomy-' . $taxonomy,
            'alertError' => $error,
            'headerActions' => '<a href="' . $this->adminUrl() . '/taxonomy/' . htmlspecialchars($taxonomy) . '" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">arrow_back</span>Back</a>',
        ];

        return Response::html($this->render('content/taxonomy-term-create', $data, $layout));
    }

    /**
     * Delete a taxonomy term.
     */
    public function taxonomyTermDelete(Request $request, string $taxonomy, string $term): ?Response
    {
        $repository = $this->app->repository();
        $taxonomies = $repository->taxonomies();

        // Check if taxonomy exists
        if (!in_array($taxonomy, $taxonomies)) {
            return null; // 404
        }

        // Check if term exists
        $existingTerms = $repository->terms($taxonomy);
        if (!isset($existingTerms[$term])) {
            return null; // 404
        }

        $termData = $existingTerms[$term];
        $itemCount = count($termData['items'] ?? []);

        if ($request->isMethod('POST')) {
            // CSRF check
            $csrf = $request->post('_csrf', '');
            if (!$this->auth->verifyCsrf($csrf)) {
                return Response::redirect($this->adminUrl() . '/taxonomy/' . $taxonomy . '?error=csrf');
            }

            // Prevent deleting terms with content (optional safety check)
            $forceDelete = $request->post('force', '') === '1';
            if ($itemCount > 0 && !$forceDelete) {
                return Response::redirect($this->adminUrl() . '/taxonomy/' . $taxonomy . '?error=has_content&term=' . urlencode($term));
            }

            // Delete the term
            $result = $this->removeTaxonomyTerm($taxonomy, $term);
            if ($result === true) {
                $this->auth->regenerateCsrf();
                $this->logAction('INFO', "Deleted term '{$term}' from taxonomy '{$taxonomy}'");
                
                // Rebuild cache
                $this->app->indexer()->rebuild();
                
                return Response::redirect($this->adminUrl() . '/taxonomy/' . $taxonomy . '?deleted=' . urlencode($termData['name'] ?? $term));
            }

            return Response::redirect($this->adminUrl() . '/taxonomy/' . $taxonomy . '?error=delete_failed');
        }

        // Show confirmation page
        $taxonomyConfig = $this->getTaxonomyConfig();

        $data = [
            'taxonomy' => $taxonomy,
            'term' => $term,
            'termData' => $termData,
            'itemCount' => $itemCount,
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'Delete Term',
            'heading' => 'Delete Term: ' . ($termData['name'] ?? $term),
            'icon' => 'delete',
            'activePage' => 'taxonomy-' . $taxonomy,
            'headerActions' => '<a href="' . $this->adminUrl() . '/taxonomy/' . htmlspecialchars($taxonomy) . '" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">arrow_back</span>Back</a>',
        ];

        return Response::html($this->render('content/taxonomy-term-delete', $data, $layout));
    }

    /**
     * Parse file content (frontmatter + body) into structured data.
     */
    private function parseFileContent(string $fileContent): array
    {
        $frontmatter = [];
        $body = $fileContent;
        
        // Normalize line endings (Windows sends \r\n)
        $fileContent = str_replace("\r\n", "\n", $fileContent);
        $fileContent = str_replace("\r", "\n", $fileContent);

        // Parse YAML frontmatter if present
        if (preg_match('/^---\n(.*?)\n---\n*/s', $fileContent, $matches)) {
            try {
                // Defense-in-depth: reject any unsupported YAML types.
                $frontmatter = \Symfony\Component\Yaml\Yaml::parse(
                    $matches[1],
                    \Symfony\Component\Yaml\Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
                ) ?? [];
            } catch (\Exception $e) {
                // Invalid YAML - return raw content
                return [
                    'frontmatter' => [],
                    'body' => $fileContent,
                    'error' => 'Invalid YAML frontmatter: ' . $e->getMessage(),
                ];
            }
            $body = substr($fileContent, strlen($matches[0]));
        } else {
            // No frontmatter found - this is an error for content files
            return [
                'frontmatter' => [],
                'body' => $fileContent,
                'error' => 'No valid frontmatter found. Content must start with --- on its own line.',
            ];
        }

        return [
            'frontmatter' => $frontmatter,
            'body' => ltrim($body),
            'error' => null,
        ];
    }

    /**
     * Create new content.
     */
    public function contentCreate(Request $request, string $type): ?Response
    {
        $repository = $this->app->repository();
        $types = $repository->types();

        // Check if type exists
        if (!in_array($type, $types)) {
            return null; // 404
        }

        $contentTypes = $this->getContentTypeConfig();
        $typeConfig = $contentTypes[$type] ?? [];
        $taxonomyConfig = $this->getTaxonomyConfig();
        $error = null;
        $securityWarnings = [];

        if ($request->isMethod('POST')) {
            $csrfError = $this->verifyInlinePostCsrf($request);
            if ($csrfError !== null) {
                $error = $csrfError;
            } else {
                // Get filename from form (without .md extension)
                $filename = trim($request->post('filename', ''));
                
                // Parse the unified file content
                $fileContent = $request->post('file_content', '');
                $parsed = $this->parseFileContent($fileContent);
                
                if ($parsed['error']) {
                    $error = $parsed['error'];
                } else {
                    $fm = $parsed['frontmatter'];
                    $body = $parsed['body'];
                    
                    $title = trim($fm['title'] ?? '');
                    $slug = trim($fm['slug'] ?? '');
                    $status = $fm['status'] ?? 'draft';
                    $date = $fm['date'] ?? '';
                    $excerpt = $fm['excerpt'] ?? '';
                    $id = $fm['id'] ?? '';
                    $categories = $fm['category'] ?? [];
                    $tags = $fm['tag'] ?? [];
                    
                    // Convert single values to arrays
                    if (is_string($categories)) $categories = [$categories];
                    if (is_string($tags)) $tags = [$tags];

                    // Validate title
                    if (empty($title)) {
                        $error = 'Title is required in frontmatter.';
                    } else {
                        // Generate slug if not provided
                        if (empty($slug)) {
                            $slug = $this->generateSlug($title);
                        }
                        
                        // Validate slug with strict security checks
                        $slugResult = $this->validateSlug($slug);
                        if (!$slugResult['valid']) {
                            $error = $slugResult['error'];
                        } else {
                            $slug = $slugResult['slug'];
                            
                            // Determine if this content type uses dates
                            $usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);
                            
                            // Generate filename if not provided
                            if (empty($filename)) {
                                $filename = ContentSecurity::generateFilename($slug, $usesDate ? ($date ?: date('Y-m-d')) : null);
                            }
                            
                            // Validate filename with strict security checks
                            $filenameResult = $this->validateFilename($filename);
                            if (!$filenameResult['valid']) {
                                $error = 'Filename: ' . $filenameResult['error'];
                            } else {
                                $filename = $filenameResult['filename'];
                                
                                // Check for duplicate (by filename, not slug - filename is the actual file)
                                $existing = $repository->get($type, $slug);
                                if ($existing !== null) {
                                    $error = "Content with slug '{$slug}' already exists.";
                                } else {
                                    // Security check on body content
                                    $security = new ContentSecurity();
                                    $securityResult = $security->validate($body);
                                    
                                    if (!$securityResult['valid']) {
                                        $securityWarnings = $securityResult['errors'];
                                        $error = $securityResult['errors'][0];
                                    } else {
                                        $securityWarnings = $securityResult['warnings'];
                                        
                                        // Validate status
                                        if (!in_array($status, ['draft', 'published', 'unlisted'])) {
                                            $status = 'draft';
                                        }

                                        // Create the content file from raw file content (using validated filename)
                                        $result = $this->createContentFileRaw($type, $typeConfig, $filename, $fileContent, $id);

                                        if ($result === true) {
                                            $this->auth->regenerateCsrf();
                                            $this->logAction('INFO', "Created {$type} '{$title}' (file: {$filename}.md, slug: {$slug})");
                                            
                                            // Rebuild cache
                                            $this->app->indexer()->rebuild();
                                            
                                            return Response::redirect($this->adminUrl() . '/content/' . $type . '?created=' . urlencode($title));
                                        } else {
                                            $error = $result;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Get available taxonomy terms for dropdowns
        $availableTerms = [];
        foreach ($typeConfig['taxonomies'] ?? [] as $taxName) {
            $availableTerms[$taxName] = $repository->terms($taxName);
        }

        $data = [
            'type' => $type,
            'typeConfig' => $typeConfig,
            'taxonomyConfig' => $taxonomyConfig,
            'availableTerms' => $availableTerms,
            'error' => $error,
            'securityWarnings' => $securityWarnings,
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'New ' . ($typeConfig['label'] ?? ucfirst($type)),
            'heading' => 'Create ' . rtrim($typeConfig['label'] ?? ucfirst($type), 's'),
            'icon' => 'add',
            'activePage' => 'content-' . $type,
            // Don't set alertError - the view handles error display inline
            'headerActions' => '<a href="https://ava.addy.zone" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">menu_book</span>Docs</a>',
        ];

        return Response::html($this->render('content/content-create', $data, $layout));
    }

    /**
     * Edit existing content.
     * 
     * @param string $file Path within content type directory without .md, using | as separator (e.g., "about|team")
     */
    public function contentEdit(Request $request, string $type, string $file): ?Response
    {
        $repository = $this->app->repository();
        $types = $repository->types();

        // Check if type exists
        if (!in_array($type, $types)) {
            return null; // 404
        }

        // Convert pipe separator to slash (allows cleaner URLs without encoding)
        $file = str_replace('|', '/', $file);

        // Security: reject any path traversal attempts
        if (str_contains($file, '..') || str_contains($file, "\0") || preg_match('#^/|^[a-zA-Z]:#', $file)) {
            return null; // 404 - attempted path traversal
        }

        $contentTypes = $this->getContentTypeConfig();
        $typeConfig = $contentTypes[$type] ?? [];

        // Build full relative path: {content_dir}/{file}.md
        $contentDir = $typeConfig['content_dir'] ?? $type . 's';
        $fullPath = $contentDir . '/' . $file . '.md';

        // Look up content by file path
        // getByPath only returns indexed content files, providing defense in depth
        $item = $repository->getByPath($fullPath);
        if ($item === null || $item->type() !== $type) {
            return null; // 404
        }

        $taxonomyConfig = $this->getTaxonomyConfig();
        $error = null;
        $securityWarnings = [];

        if ($request->isMethod('POST')) {
            $csrfError = $this->verifyInlinePostCsrf($request);
            if ($csrfError !== null) {
                $error = $csrfError;
            } else {
                // Optimistic locking: check if file was modified since form was loaded
                $expectedMtime = (int) $request->post('_file_mtime', '0');
                $currentMtime = file_exists($item->filePath()) ? filemtime($item->filePath()) : 0;
                
                if ($expectedMtime > 0 && $currentMtime > $expectedMtime) {
                    $error = 'This file was modified by another user or process. Please refresh and try again.';
                }
                
                if (!$error) {
                    // Check which editor mode was used
                    $editorMode = $request->post('_editor_mode', 'raw');
                    
                    if ($editorMode === 'focused') {
                        // Handle focused editor form submission
                        $result = $this->handleFocusedFormSubmission($request, $item, $type, $typeConfig, $file);
                        if ($result['success']) {
                            $successMessage = $result['message'];
                            // Parse the file directly to get fresh content (no need to rebuild index)
                            $parser = new \Ava\Content\Parser();
                            $filePath = $this->app->path('content/' . $fullPath);
                            if (file_exists($filePath)) {
                                $item = $parser->parseFile($filePath, $type);
                            }
                            // Redirect if filename changed
                            if (!empty($result['redirect'])) {
                                return Response::redirect($result['redirect']);
                            }
                        } else {
                            $error = $result['error'];
                            $securityWarnings = $result['warnings'] ?? [];
                        }
                    } else {
                        // Handle raw editor form submission (existing logic)
                        // Get new filename from form (without .md extension)
                        $newFilename = trim($request->post('filename', ''));
                        
                        // Parse the unified file content
                        $fileContent = $request->post('file_content', '');
                        $parsed = $this->parseFileContent($fileContent);
                        
                        if ($parsed['error']) {
                            $error = $parsed['error'];
                        } else {
                        $fm = $parsed['frontmatter'];
                        $body = $parsed['body'];
                        
                        $title = trim($fm['title'] ?? '');
                        $newSlug = trim($fm['slug'] ?? $item->slug());

                        // Validate title
                        if (empty($title)) {
                            $error = 'Title is required in frontmatter.';
                        } else {
                            // Validate slug with strict security checks
                            $slugResult = $this->validateSlug($newSlug);
                            if (!$slugResult['valid']) {
                                $error = $slugResult['error'];
                            } else {
                                $newSlug = $slugResult['slug'];
                                
                                // Get current filename (without .md)
                                $currentFilename = pathinfo(basename($item->filePath()), PATHINFO_FILENAME);
                                
                                // If no new filename provided, keep the current one
                                if (empty($newFilename)) {
                                    $newFilename = $currentFilename;
                                }
                                
                                // Validate filename with strict security checks
                                $filenameResult = $this->validateFilename($newFilename);
                                if (!$filenameResult['valid']) {
                                    $error = 'Filename: ' . $filenameResult['error'];
                                } else {
                                    $newFilename = $filenameResult['filename'];
                                    
                                    // Check for duplicate slug (if changed)
                                    if ($newSlug !== $item->slug()) {
                                        $existing = $repository->get($type, $newSlug);
                                        if ($existing !== null) {
                                            $error = "Content with slug '{$newSlug}' already exists.";
                                        }
                                    }

                                    if (!$error) {
                                        // Security check on body content
                                        $security = new ContentSecurity();
                                        $securityResult = $security->validate($body);
                                        
                                        if (!$securityResult['valid']) {
                                            $securityWarnings = $securityResult['errors'];
                                            $error = $securityResult['errors'][0];
                                        } else {
                                            $securityWarnings = $securityResult['warnings'];
                                            
                                            // Update the content file (using validated filename)
                                            $result = $this->updateContentFileRaw($item, $type, $typeConfig, $newFilename, $fileContent, $currentFilename);

                                            if ($result === true) {
                                                $this->auth->regenerateCsrf();
                                                $this->logAction('INFO', "Updated {$type} '{$title}' (file: {$newFilename}.md, slug: {$newSlug})");
                                                
                                                // Rebuild cache
                                                $this->app->indexer()->rebuild();
                                                
                                                // Stay on same page with success message
                                                $successMessage = "'{$title}' saved successfully.";
                                                
                                                // If filename changed, redirect to new URL with updated file path
                                                if ($newFilename !== $currentFilename) {
                                                    // Compute new file path (same directory, new filename) - use pipe separator
                                                    $dir = dirname($file);
                                                    $newFile = ($dir === '.' ? '' : $dir . '/') . $newFilename;
                                                    $newFileParam = str_replace('/', '|', $newFile);
                                                    return Response::redirect($this->adminUrl() . '/content/' . $type . '/edit?file=' . urlencode($newFileParam) . '&saved=1');
                                                }
                                            } else {
                                                $error = $result;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    } // end else (raw mode)
                }
            }
        }

        // Get available taxonomy terms for dropdowns
        $availableTerms = [];
        foreach ($typeConfig['taxonomies'] ?? [] as $taxName) {
            $availableTerms[$taxName] = $repository->terms($taxName);
        }

        // Get current file modification time for optimistic locking
        $fileMtime = file_exists($item->filePath()) ? filemtime($item->filePath()) : 0;
        
        // For URLs, convert slashes back to pipes
        $fileParam = str_replace('/', '|', $file);

        // Editor mode: 'raw' for raw file editing, 'focused' for structured fields
        // Default to focused mode unless explicitly set or user prefers raw
        $mode = $request->query('mode', 'raw');
        
        // Create field renderer for focused mode
        $fieldRenderer = null;
        if ($mode === 'focused') {
            $fieldRenderer = new \Ava\Fields\FieldRenderer($this->app);
        }
        
        $data = [
            'type' => $type,
            'item' => $item,
            'file' => $file,
            'fileParam' => $fileParam,
            'typeConfig' => $typeConfig,
            'taxonomyConfig' => $taxonomyConfig,
            'availableTerms' => $availableTerms,
            'error' => $error,
            'successMessage' => $successMessage ?? null,
            'securityWarnings' => $securityWarnings,
            'csrf' => $this->auth->csrfToken(),
            'fileMtime' => $fileMtime,
            'fieldRenderer' => $fieldRenderer,
            'app' => $this->app,
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'Edit: ' . $item->title(),
            'heading' => 'Edit ' . rtrim($typeConfig['label'] ?? ucfirst($type), 's'),
            'icon' => 'edit',
            'activePage' => 'content-' . $type,
            // Don't set alertError - the view handles error display inline
            'headerActions' => '<a href="https://ava.addy.zone" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">menu_book</span>Docs</a>',
        ];

        // Choose view based on mode
        $viewName = ($mode === 'focused') ? 'content/content-edit-focused' : 'content/content-edit';

        return Response::html($this->render($viewName, $data, $layout));
    }

    /**
     * Delete content.
     * 
     * @param string $file Path within content type directory without .md, using | as separator (e.g., "about|team")
     */
    public function contentDelete(Request $request, string $type, string $file): ?Response
    {
        $repository = $this->app->repository();
        $types = $repository->types();

        // Check if type exists
        if (!in_array($type, $types)) {
            return null; // 404
        }

        // Convert pipe separator to slash (allows cleaner URLs without encoding)
        $file = str_replace('|', '/', $file);

        // Security: reject any path traversal attempts
        if (str_contains($file, '..') || str_contains($file, "\0") || preg_match('#^/|^[a-zA-Z]:#', $file)) {
            return null; // 404 - attempted path traversal
        }

        $contentTypes = $this->getContentTypeConfig();
        $typeConfig = $contentTypes[$type] ?? [];

        // Build full relative path: {content_dir}/{file}.md
        $contentDir = $typeConfig['content_dir'] ?? $type . 's';
        $fullPath = $contentDir . '/' . $file . '.md';

        // Look up content by file path
        // getByPath only returns indexed content files, providing defense in depth
        $item = $repository->getByPath($fullPath);
        if ($item === null || $item->type() !== $type) {
            return null; // 404
        }

        if ($request->isMethod('POST')) {
            // CSRF check
            $csrf = $request->post('_csrf', '');
            if (!$this->auth->verifyCsrf($csrf)) {
                return Response::redirect($this->adminUrl() . '/content/' . $type . '?error=csrf');
            }

            // Confirm deletion - user types filename with .md suffix
            $confirm = $request->post('confirm', '');
            if ($confirm !== $file . '.md') {
                $fileParam = str_replace('/', '|', $file);
                return Response::redirect($this->adminUrl() . '/content/' . $type . '/delete?file=' . urlencode($fileParam) . '&error=confirm');
            }

            // Delete the file
            $filePath = $item->filePath();
            if (file_exists($filePath)) {
                // Create backup before deletion
                $backupDir = $this->app->configPath('storage') . '/backups';
                if (!is_dir($backupDir)) {
                    @mkdir($backupDir, 0755, true);
                }
                $safeFilename = str_replace(['/', '\\'], '_', $file);
                $backupPath = $backupDir . '/' . $type . '_' . $safeFilename . '_' . date('Y-m-d_His') . '.md';
                @copy($filePath, $backupPath);

                if (@unlink($filePath)) {
                    $this->auth->regenerateCsrf();
                    $this->logAction('INFO', "Deleted {$type} '{$item->title()}' (file: {$file}). Backup: {$backupPath}");
                    
                    // Rebuild cache
                    $this->app->indexer()->rebuild();
                    
                    return Response::redirect($this->adminUrl() . '/content/' . $type . '?deleted=' . urlencode($item->title()));
                }
            }

            return Response::redirect($this->adminUrl() . '/content/' . $type . '?error=delete_failed');
        }

        // Show confirmation page
        // For URLs, convert slashes back to pipes
        $fileParam = str_replace('/', '|', $file);
        
        $data = [
            'type' => $type,
            'item' => $item,
            'file' => $file,
            'fileParam' => $fileParam,
            'typeConfig' => $typeConfig,
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'Delete: ' . $item->title(),
            'heading' => 'Delete ' . rtrim($typeConfig['label'] ?? ucfirst($type), 's'),
            'icon' => 'delete',
            'activePage' => 'content-' . $type,
            'headerActions' => '<a href="' . $this->adminUrl() . '/content/' . htmlspecialchars($type) . '" class="btn btn-secondary btn-sm"><span class="material-symbols-rounded">arrow_back</span>Back</a>',
        ];

        return Response::html($this->render('content/content-delete', $data, $layout));
    }

    // -------------------------------------------------------------------------
    // Content and Taxonomy Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a URL-safe slug from a title.
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'untitled';
    }

    /**
     * Validate a user-provided slug.
     * Returns the validated slug or null with error message.
     * 
     * @param string $slug The slug to validate
     * @return array{valid: bool, slug: string, error: ?string}
     */
    private function validateSlug(string $slug): array
    {
        return ContentSecurity::validateSlug($slug);
    }

    /**
     * Validate a user-provided filename (without extension).
     * 
     * @param string $filename The filename to validate
     * @return array{valid: bool, filename: string, error: ?string}
     */
    private function validateFilename(string $filename): array
    {
        return ContentSecurity::validateFilename($filename);
    }

    /**
     * Verify CSRF token for a POST request, returning a user-friendly error string on failure.
     * Intended for inline form flows (where we show an error on the same page).
     */
    private function verifyInlinePostCsrf(Request $request): ?string
    {
        $csrf = $request->post('_csrf', '');
        if (!$this->auth->verifyCsrf($csrf)) {
            return 'Invalid request. Please try again.';
        }

        return null;
    }

    /**
     * Sanitize a user-provided slug (legacy compatibility, use validateSlug for new code).
     */
    private function sanitizeSlug(string $slug): string
    {
        $result = ContentSecurity::validateSlug($slug);
        return $result['valid'] ? $result['slug'] : 'untitled';
    }

    /**
     * Add a term to a taxonomy file.
     */
    private function addTaxonomyTerm(string $taxonomy, string $slug, string $name, string $description): bool|string
    {
        $contentBase = $this->app->configPath('content');
        $taxonomiesPath = Path::join($contentBase, '_taxonomies');

        if (!Path::isInside($taxonomiesPath, $contentBase)) {
            return 'Invalid taxonomies directory.';
        }

        $filePath = Path::join($taxonomiesPath, $taxonomy . '.yml');
        if (!Path::isInside($filePath, $taxonomiesPath)) {
            return 'Invalid taxonomy.';
        }

        // Ensure directory exists
        if (!is_dir($taxonomiesPath)) {
            if (!@mkdir($taxonomiesPath, 0755, true)) {
                return 'Failed to create taxonomies directory.';
            }
        }

        // Load existing terms
        $terms = [];
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return 'Failed to read taxonomy file.';
            }

            try {
                $terms = \Symfony\Component\Yaml\Yaml::parse(
                    $content,
                    \Symfony\Component\Yaml\Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
                ) ?? [];
            } catch (\Throwable) {
                return 'Invalid taxonomy file format.';
            }

            if (!is_array($terms)) {
                return 'Invalid taxonomy file format.';
            }
        }

        // Add new term
        $newTerm = [
            'slug' => $slug,
            'name' => $name,
        ];
        if (!empty($description)) {
            $newTerm['description'] = $description;
        }
        $terms[] = $newTerm;

        // Write back with exclusive lock for concurrent safety
        $yaml = \Symfony\Component\Yaml\Yaml::dump($terms, 2, 2);
        if (file_put_contents($filePath, $yaml, LOCK_EX) === false) {
            return 'Failed to write taxonomy file.';
        }

        return true;
    }

    /**
     * Remove a term from a taxonomy file.
     */
    private function removeTaxonomyTerm(string $taxonomy, string $slug): bool|string
    {
        $contentBase = $this->app->configPath('content');
        $taxonomiesPath = Path::join($contentBase, '_taxonomies');

        if (!Path::isInside($taxonomiesPath, $contentBase)) {
            return 'Invalid taxonomies directory.';
        }

        $filePath = Path::join($taxonomiesPath, $taxonomy . '.yml');
        if (!Path::isInside($filePath, $taxonomiesPath)) {
            return 'Invalid taxonomy.';
        }

        if (!file_exists($filePath)) {
            return 'Taxonomy file not found.';
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return 'Failed to read taxonomy file.';
        }

        try {
            $terms = \Symfony\Component\Yaml\Yaml::parse(
                $content,
                \Symfony\Component\Yaml\Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            ) ?? [];
        } catch (\Throwable) {
            return 'Invalid taxonomy file format.';
        }

        if (!is_array($terms)) {
            return 'Invalid taxonomy file format.';
        }

        // Filter out the term
        $terms = array_filter($terms, fn($term) => ($term['slug'] ?? '') !== $slug);
        $terms = array_values($terms);

        // Write back with exclusive lock for concurrent safety
        $yaml = \Symfony\Component\Yaml\Yaml::dump($terms, 2, 2);
        if (file_put_contents($filePath, $yaml, LOCK_EX) === false) {
            return 'Failed to write taxonomy file.';
        }

        return true;
    }

    /**
     * Create a content file from raw file content (frontmatter + body).
     */
    private function createContentFileRaw(string $type, array $typeConfig, string $slug, string $fileContent, string $id = ''): bool|string
    {
        $contentDir = $typeConfig['content_dir'] ?? $type . 's';
        $contentBase = $this->app->configPath('content');
        $contentPath = Path::join($contentBase, $contentDir);

        if (!Path::isInside($contentPath, $contentBase)) {
            return 'Invalid content directory.';
        }

        // Ensure directory exists
        if (!is_dir($contentPath)) {
            if (!@mkdir($contentPath, 0755, true)) {
                return 'Failed to create content directory.';
            }
        }

        $filePath = Path::join($contentPath, $slug . '.md');

        if (!Path::isInside($filePath, $contentPath)) {
            return 'Invalid slug.';
        }

        // Check if file already exists
        if (file_exists($filePath)) {
            return 'A file with this slug already exists.';
        }

        // If no ID in content, inject one
        if (empty($id) && !preg_match('/^id:\s*.+$/m', $fileContent)) {
            $newId = \Ava\Support\Ulid::generate();
            $fileContent = preg_replace('/^---\n/', "---\nid: {$newId}\n", $fileContent);
        }

        if (file_put_contents($filePath, $fileContent, LOCK_EX) === false) {
            return 'Failed to write content file.';
        }

        return true;
    }

    /**
     * Update a content file from raw file content (frontmatter + body).
     */
    private function updateContentFileRaw(\Ava\Content\Item $item, string $type, array $typeConfig, string $newSlug, string $fileContent, string $originalSlug): bool|string
    {
        $contentDir = $typeConfig['content_dir'] ?? $type . 's';
        $contentBase = $this->app->configPath('content');
        $contentPath = Path::join($contentBase, $contentDir);

        if (!Path::isInside($contentPath, $contentBase)) {
            return 'Invalid content directory.';
        }

        $originalFilePath = $item->filePath();
        $newFilePath = Path::join($contentPath, $newSlug . '.md');

        if (!Path::isInside($newFilePath, $contentPath)) {
            return 'Invalid slug.';
        }

        if (!Path::isInside($originalFilePath, $contentBase)) {
            return 'Invalid original file path.';
        }

        // If slug changed, check new file doesn't exist
        if ($newSlug !== $originalSlug && file_exists($newFilePath)) {
            return 'A file with the new slug already exists.';
        }

        // Write to new path with exclusive lock for concurrent safety
        if (file_put_contents($newFilePath, $fileContent, LOCK_EX) === false) {
            return 'Failed to write content file.';
        }

        // If slug changed, delete old file
        if ($newSlug !== $originalSlug && $originalFilePath !== $newFilePath) {
            @unlink($originalFilePath);
        }

        return true;
    }

    /**
     * Handle focused editor form submission.
     * Converts structured field data to YAML frontmatter.
     */
    private function handleFocusedFormSubmission(
        Request $request, 
        \Ava\Content\Item $item, 
        string $type, 
        array $typeConfig, 
        string $file
    ): array {
        // Get submitted fields
        $fields = $request->post('fields', []);
        $newFilename = trim($request->post('filename', ''));
        
        // Get current filename
        $currentFilename = pathinfo(basename($item->filePath()), PATHINFO_FILENAME);
        
        // If no new filename provided, keep the current one
        if (empty($newFilename)) {
            $newFilename = $currentFilename;
        }
        
        // Validate filename
        $filenameResult = $this->validateFilename($newFilename);
        if (!$filenameResult['valid']) {
            return ['success' => false, 'error' => 'Filename: ' . $filenameResult['error'], 'warnings' => []];
        }
        $newFilename = $filenameResult['filename'];
        
        // Extract and validate core fields
        $title = trim($fields['title'] ?? '');
        if (empty($title)) {
            return ['success' => false, 'error' => 'Title is required.', 'warnings' => []];
        }
        
        $newSlug = trim($fields['slug'] ?? $item->slug());
        $slugResult = $this->validateSlug($newSlug);
        if (!$slugResult['valid']) {
            return ['success' => false, 'error' => $slugResult['error'], 'warnings' => []];
        }
        $newSlug = $slugResult['slug'];
        
        // Check for duplicate slug
        if ($newSlug !== $item->slug()) {
            $existing = $this->app->repository()->get($type, $newSlug);
            if ($existing !== null) {
                return ['success' => false, 'error' => "Content with slug '{$newSlug}' already exists.", 'warnings' => []];
            }
        }
        
        // Get body content
        $body = $fields['body'] ?? $item->rawContent();
        unset($fields['body']);
        
        // Security check on body content
        $security = new ContentSecurity();
        $securityResult = $security->validate($body);
        
        if (!$securityResult['valid']) {
            return ['success' => false, 'error' => $securityResult['errors'][0], 'warnings' => $securityResult['errors']];
        }
        
        // Validate custom fields using FieldValidator
        $fieldValidator = new \Ava\Fields\FieldValidator($this->app);
        $fieldErrors = $fieldValidator->getErrors($fields, $type);
        if (!empty($fieldErrors)) {
            return ['success' => false, 'error' => $fieldErrors[0], 'warnings' => []];
        }
        
        // Build frontmatter array
        $frontmatter = [];
        
        // Preserve ID if exists
        $id = trim($fields['id'] ?? $item->id() ?? '');
        if (!empty($id)) {
            $frontmatter['id'] = $id;
        }
        
        // Core fields
        $frontmatter['title'] = $title;
        $frontmatter['slug'] = $newSlug;
        $frontmatter['status'] = $fields['status'] ?? $item->status();
        
        // Date (if content type uses dates)
        $usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);
        if ($usesDate && !empty($fields['date'])) {
            $frontmatter['date'] = $fields['date'];
        }
        
        // Taxonomies
        foreach ($typeConfig['taxonomies'] ?? [] as $taxName) {
            $taxValue = $fields[$taxName] ?? [];
            if (is_string($taxValue)) {
                // Comma-separated input
                $taxValue = array_filter(array_map('trim', explode(',', $taxValue)));
            }
            if (!empty($taxValue)) {
                if (count($taxValue) === 1) {
                    $frontmatter[$taxName] = $taxValue[0];
                } else {
                    $frontmatter[$taxName] = $taxValue;
                }
            }
        }
        
        // Excerpt
        if (!empty($fields['excerpt'])) {
            $frontmatter['excerpt'] = $fields['excerpt'];
        }
        
        // SEO fields
        if (!empty($fields['meta_title'])) {
            $frontmatter['meta_title'] = $fields['meta_title'];
        }
        if (!empty($fields['meta_description'])) {
            $frontmatter['meta_description'] = $fields['meta_description'];
        }
        if (!empty($fields['og_image'])) {
            $frontmatter['og_image'] = $fields['og_image'];
        }
        if (!empty($fields['noindex'])) {
            $frontmatter['noindex'] = true;
        }
        
        // Advanced fields
        if (!empty($fields['template'])) {
            $frontmatter['template'] = $fields['template'];
        }
        if (isset($fields['cache']) && !$fields['cache']) {
            $frontmatter['cache'] = false;
        }
        if (!empty($fields['redirect_from'])) {
            $redirects = array_filter($fields['redirect_from'], fn($v) => !empty($v));
            if (!empty($redirects)) {
                $frontmatter['redirect_from'] = array_values($redirects);
            }
        }
        
        // Custom fields (from field definitions in content type config)
        $fieldDefinitions = $typeConfig['fields'] ?? [];
        foreach ($fieldDefinitions as $fieldName => $fieldConfig) {
            if (isset($fields[$fieldName]) && $fields[$fieldName] !== '' && $fields[$fieldName] !== null) {
                $frontmatter[$fieldName] = $fieldValidator->prepareForStorage(
                    [$fieldName => $fields[$fieldName]], 
                    $type
                )[$fieldName];
            }
        }
        
        // Convert frontmatter to YAML
        $yaml = \Symfony\Component\Yaml\Yaml::dump($frontmatter, 2, 2);
        
        // Build file content
        $fileContent = "---\n" . trim($yaml) . "\n---\n\n" . $body;
        
        // Update the file
        $result = $this->updateContentFileRaw($item, $type, $typeConfig, $newFilename, $fileContent, $currentFilename);
        
        if ($result !== true) {
            return ['success' => false, 'error' => $result, 'warnings' => []];
        }
        
        $this->auth->regenerateCsrf();
        $this->logAction('INFO', "Updated {$type} '{$title}' (file: {$newFilename}.md, slug: {$newSlug}) via focused editor");
        
        // Return success with optional redirect
        $redirect = null;
        if ($newFilename !== $currentFilename) {
            $dir = dirname($file);
            $newFile = ($dir === '.' ? '' : $dir . '/') . $newFilename;
            $newFileParam = str_replace('/', '|', $newFile);
            $redirect = $this->adminUrl() . '/content/' . $type . '/edit?file=' . urlencode($newFileParam) . '&mode=focused&saved=1';
        }
        
        return [
            'success' => true, 
            'message' => "'{$title}' saved successfully.", 
            'warnings' => $securityResult['warnings'] ?? [],
            'redirect' => $redirect,
        ];
    }

    /**
     * Lint content action.
     */
    public function lint(Request $request): Response
    {
        $errors = $this->app->indexer()->lint();

        $data = [
            'errors' => $errors,
            'valid' => empty($errors),
        ];

        $layout = [
            'title' => 'Lint Content',
            'icon' => 'check_circle',
            'activePage' => 'lint',
            'headerActions' => $this->defaultHeaderActions('cli'),
        ];

        return Response::html($this->render('content/lint', $data, $layout));
    }

    /**
     * System info page.
     */
    public function system(Request $request): Response
    {
        $repository = $this->app->repository();
        
        $data = [
            'system' => $this->getSystemInfo(),
            'content' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'contentTypes' => $this->getContentTypeConfig(),
            'cache' => $this->getCacheStatus(),
            'cacheFiles' => $this->getCacheFilesInfo(),
            'plugins' => $this->getActivePlugins(),
            'theme' => $this->app->config('theme', 'default'),
            'routes' => $repository->routes(),
            'avaConfig' => $this->getAvaConfig(),
            'directories' => $this->getDirectoryStatus(),
            'hooks' => $this->getHooksInfo(),
            'pathAliases' => $this->getPathAliases(),
            'debugInfo' => $this->getDebugInfo(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
                'timezone' => $this->app->config('site.timezone', 'UTC'),
            ],
            'csrf' => $this->auth->csrfToken(),
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'System Info',
            'icon' => 'dns',
            'activePage' => 'system',
            'headerActions' => $this->defaultHeaderActions('admin'),
            'scripts' => <<<'JS'
function clearErrorLog() {
    if (!confirm('Clear error log?')) return;
    var btn = document.getElementById('clearBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded">hourglass_empty</span> Clearing...';
    fetch(document.querySelector('[data-admin-url]')?.dataset.adminUrl + '/clear-errors' || '/admin/clear-errors', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + document.querySelector('[name=_csrf]')?.value
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) location.reload();
        else {
            alert('Failed: ' + (d.error || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">delete_sweep</span> Clear';
        }
    })
    .catch(function(e) {
        alert('Error: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded">delete_sweep</span> Clear';
    });
}
JS
        ];

        return Response::html($this->render('content/system', $data, $layout));
    }

    /**
     * Theme page - overview of theme system, current theme, and shortcodes reference.
     */
    public function theme(Request $request): Response
    {
        $currentTheme = $this->app->config('theme', 'default');
        $themesPath = $this->app->configPath('themes');
        $themeInfo = $this->getThemeInfo($currentTheme, $themesPath);
        $availableThemes = $this->getAvailableThemes($themesPath);

        // Get registered shortcodes from the engine
        $shortcodesEngine = $this->app->shortcodes();
        $shortcodeTags = $shortcodesEngine->tags();

        // Get available snippets
        $snippets = $this->getAvailableSnippets();

        $data = [
            'currentTheme' => $currentTheme,
            'themeInfo' => $themeInfo,
            'availableThemes' => $availableThemes,
            'themesPath' => $themesPath,
            'shortcodes' => $shortcodeTags,
            'snippets' => $snippets,
            'content' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
                'timezone' => $this->app->config('site.timezone', 'UTC'),
            ],
            'csrf' => $this->auth->csrfToken(),
            'user' => $this->auth->user(),
        ];

        $scripts = <<<'JS'
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const text = this.dataset.copy;
        navigator.clipboard.writeText(text).then(() => {
            const icon = this.querySelector('.material-symbols-rounded');
            icon.textContent = 'check';
            this.classList.add('copied');
            setTimeout(() => {
                icon.textContent = 'content_copy';
                this.classList.remove('copied');
            }, 1500);
        });
    });
});
JS;

        $layout = [
            'title' => 'Theme',
            'icon' => 'palette',
            'activePage' => 'theme',
            'headerActions' => $this->defaultHeaderActions('theming'),
            'scripts' => $scripts,
        ];

        return Response::html($this->render('content/theme', $data, $layout));
    }

    /**
     * Admin logs page.
     */
    public function logs(Request $request): Response
    {
        $logs = $this->getAdminLogs();

        $data = [
            'logs' => $logs,
            'users' => $this->app->loadConfig('users'),
            'content' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
                'timezone' => $this->app->config('site.timezone', 'UTC'),
            ],
            'csrf' => $this->auth->csrfToken(),
            'user' => $this->auth->user(),
        ];

        $layout = [
            'title' => 'Admin Logs',
            'icon' => 'history',
            'activePage' => 'logs',
            'headerActions' => $this->defaultHeaderActions('admin'),
        ];

        return Response::html($this->render('content/logs', $data, $layout));
    }

    /**
     * Media uploader page.
     */
    public function media(Request $request): Response
    {
        $uploader = new MediaUploader($this->app);

        // Check if media is enabled
        if (!$uploader->isEnabled()) {
            return Response::html('<h1>Media uploads are disabled</h1><p>Enable them in your configuration.</p>', 403);
        }

        $error = null;
        $success = null;
        $uploadedFiles = [];

        // Handle file upload
        if ($request->isMethod('POST')) {
            // CSRF check
            $csrf = $request->post('_csrf', '');
            if (!$this->auth->verifyCsrf($csrf)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $files = $_FILES['media'] ?? null;

                if ($files === null || empty($files['name'][0])) {
                    $error = 'No files selected.';
                } else {
                    // Determine target folder
                    $folderMode = $request->post('folder_mode', 'date');
                    $targetFolder = null;

                    if ($folderMode === 'existing') {
                        $targetFolder = $request->post('existing_folder', '');
                        if ($targetFolder === '') {
                            $targetFolder = null;
                        }
                    } elseif ($folderMode === 'root') {
                        $targetFolder = null;
                    }
                    // 'date' mode uses null with default date organization

                    $useDate = ($folderMode === 'date');

                    // Handle multiple files
                    $totalFiles = count($files['name']);
                    $successCount = 0;
                    $errors = [];

                    for ($i = 0; $i < $totalFiles; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }

                        $fileData = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i],
                        ];

                        $result = $uploader->upload($fileData, $targetFolder, $useDate);

                        if ($result['success']) {
                            $successCount++;
                            $uploadedFiles[] = $result;
                            $relativePath = $result['path'] ?? $result['filename'] ?? 'unknown';
                            $this->logAction('INFO', "Uploaded media: {$result['filename']} to {$relativePath}");
                        } else {
                            $errors[] = "{$files['name'][$i]}: {$result['error']}";
                            $this->logAction('WARNING', "Media upload failed: {$files['name'][$i]} - {$result['error']}");
                        }
                    }

                    if ($successCount > 0) {
                        $success = "Uploaded {$successCount} file" . ($successCount > 1 ? 's' : '') . " successfully.";
                    }

                    if (!empty($errors)) {
                        $error = implode("\n", $errors);
                    }

                    // Regenerate CSRF after successful upload
                    $this->auth->regenerateCsrf();
                }
            }
        }

        // Get folder list and recent uploads
        $existingFolders = $uploader->getExistingFolders();
        $uploadLimits = $uploader->getUploadLimits();

        // Get current folder from query string for browsing
        $currentFolder = $request->query('folder', '');
        $allFiles = $uploader->listFiles($currentFolder !== '' ? $currentFolder : null);

        // Sorting
        $sortBy = $request->query('sort', 'date');
        $sortDir = $request->query('dir', 'desc');
        
        usort($allFiles, function ($a, $b) use ($sortBy, $sortDir) {
            $result = match ($sortBy) {
                'name' => strcasecmp($a['name'], $b['name']),
                'size' => $a['size'] <=> $b['size'],
                default => $a['modified'] <=> $b['modified'], // date
            };
            return $sortDir === 'desc' ? -$result : $result;
        });

        // Pagination
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));
        $totalFiles = count($allFiles);
        $totalPages = max(1, (int) ceil($totalFiles / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $currentFiles = array_slice($allFiles, $offset, $perPage);

        // Get media alias for shortlinks
        $mediaAlias = '@media:';
        $aliases = $this->app->config('paths.aliases', []);
        foreach ($aliases as $alias => $path) {
            if ($path === '/media/' || $path === '/media') {
                $mediaAlias = $alias;
                break;
            }
        }

        $data = [
            'error' => $error,
            'success' => $success,
            'uploadedFiles' => $uploadedFiles,
            'existingFolders' => $existingFolders,
            'uploadLimits' => $uploadLimits,
            'currentFolder' => $currentFolder,
            'currentFiles' => $currentFiles,
            'organizeByDate' => $uploader->isOrganizeByDateEnabled(),
            'dateFolder' => $uploader->getDateFolder(),
            'mediaPath' => $uploader->getMediaPath(),
            'isWritable' => $uploader->isWritable(),
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
            ],
            'user' => $this->auth->user(),
            // Sorting and pagination
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalFiles' => $totalFiles,
            'perPage' => $perPage,
            'mediaAlias' => $mediaAlias,
        ];

        $layout = [
            'title' => 'Media',
            'icon' => 'image',
            'activePage' => 'media',
            'alertSuccess' => $success,
            'alertError' => $error,
            'headerActions' => $this->defaultHeaderActions('admin'),
        ];

        return Response::html($this->render('content/media', $data, $layout));
    }

    /**
     * Media API - returns JSON for media picker.
     *
     * GET /admin/api/media?folder=optional-folder
     * Returns: { files: [...], folders: [...] }
     */
    public function mediaApi(Request $request): Response
    {
        $uploader = new MediaUploader($this->app);

        if (!$uploader->isEnabled()) {
            return Response::json(['error' => 'Media uploads are disabled'], 403);
        }

        $folder = $request->query('folder', '');
        $files = $uploader->listFiles($folder !== '' ? $folder : null);
        $folders = $uploader->getExistingFolders();

        // Add URL to each file for media picker
        $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
        $mediaPath = ltrim($uploader->getMediaPath(), '/');

        $fileData = array_map(function ($file) use ($baseUrl, $mediaPath) {
            // Build URL from the file path
            $path = $file['path'] ?? $file['name'];
            $url = $baseUrl . '/' . $mediaPath . '/' . ltrim($path, '/');

            return [
                'name' => $file['name'],
                'path' => $path,
                'url' => $url,
                'size' => $file['size'] ?? 0,
                'modified' => $file['modified'] ?? 0,
            ];
        }, $files);

        return Response::json([
            'files' => $fileData,
            'folders' => $folders,
        ]);
    }

    // -------------------------------------------------------------------------
    // Data gathering
    // -------------------------------------------------------------------------

    private function getCacheStatus(): array
    {
        $cachePath = $this->app->configPath('storage') . '/cache';
        $fingerprintPath = $cachePath . '/fingerprint.json';

        $status = [
            'mode' => $this->app->config('content_index.mode', 'auto'),
            'fresh' => false,
            'built_at' => null,
            'size' => 0,
            'files' => 0,
            'cache_files' => [],
        ];

        if (file_exists($fingerprintPath)) {
            $status['fresh'] = $this->app->indexer()->isCacheFresh();
        }

        if (file_exists($cachePath . '/content_index.bin')) {
            $status['built_at'] = date('Y-m-d H:i:s', filemtime($cachePath . '/content_index.bin'));
        }

        // Calculate cache directory size and individual file sizes
        $cacheFileNames = [
            'content_index.bin' => 'Full Index',
            'slug_lookup.bin' => 'Slug Lookup',
            'recent_cache.bin' => 'Recent Cache',
            'routes.bin' => 'Routes',
            'tax_index.bin' => 'Taxonomies',
        ];

        if (is_dir($cachePath)) {
            $files = glob($cachePath . '/*');
            $status['files'] = count($files);
            $totalSize = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size = filesize($file);
                    $totalSize += $size;
                    $basename = basename($file);
                    if (isset($cacheFileNames[$basename])) {
                        $status['cache_files'][$cacheFileNames[$basename]] = $size;
                    }
                }
            }
            $status['size'] = $totalSize;
        }

        return $status;
    }

    private function getContentStats(): array
    {
        $repository = $this->app->repository();
        $stats = [];

        foreach ($repository->types() as $type) {
            $stats[$type] = [
                'total' => $repository->count($type),
                'published' => $repository->count($type, 'published'),
                'draft' => $repository->count($type, 'draft'),
            ];
        }

        return $stats;
    }

    private function getTaxonomyStats(): array
    {
        $repository = $this->app->repository();
        $stats = [];

        foreach ($repository->taxonomies() as $taxonomy) {
            $terms = $repository->terms($taxonomy);
            $stats[$taxonomy] = count($terms);
        }

        return $stats;
    }

    private function getTaxonomyTerms(): array
    {
        $repository = $this->app->repository();
        $allTerms = [];

        foreach ($repository->taxonomies() as $taxonomy) {
            $allTerms[$taxonomy] = $repository->terms($taxonomy);
        }

        return $allTerms;
    }

    private function getContentTypeConfig(): array
    {
        $configPath = $this->app->path('app/config/content_types.php');
        if (file_exists($configPath)) {
            return require $configPath;
        }
        return [];
    }

    private function getTaxonomyConfig(): array
    {
        $configPath = $this->app->path('app/config/taxonomies.php');
        if (file_exists($configPath)) {
            return require $configPath;
        }
        return [];
    }

    private function getAvaConfig(): array
    {
        $debug = $this->app->config('debug', []);
        return [
            'site_name' => $this->app->config('site.name'),
            'base_url' => $this->app->config('site.base_url'),
            'timezone' => $this->app->config('site.timezone', 'UTC'),
            'theme' => $this->app->config('theme', 'default'),
            'cache_mode' => $this->app->config('content_index.mode', 'auto'),
            'admin_enabled' => $this->app->config('admin.enabled', false),
            'admin_path' => $this->app->config('admin.path', '/admin'),
            'debug' => is_array($debug) ? ($debug['enabled'] ?? false) : (bool) $debug,
            'debug_display' => is_array($debug) ? ($debug['display_errors'] ?? false) : false,
            'debug_log' => is_array($debug) ? ($debug['log_errors'] ?? true) : true,
            'debug_level' => is_array($debug) ? ($debug['level'] ?? 'errors') : 'errors',
            'content_types' => count($this->getContentTypeConfig()),
            'taxonomies' => count($this->getTaxonomyConfig()),
            'plugins' => count($this->getActivePlugins()),
        ];
    }

    /**
     * Lightweight system info for dashboard (no directory scanning).
     */
    private function getSystemInfoBasic(): array
    {
        $opcacheEnabled = function_exists('opcache_get_status');
        $opcacheStats = null;
        if ($opcacheEnabled) {
            $status = @opcache_get_status(false);
            if ($status) {
                $opcacheStats = [
                    'enabled' => $status['opcache_enabled'] ?? false,
                    'hit_rate' => isset($status['opcache_statistics']['opcache_hit_rate']) 
                        ? round($status['opcache_statistics']['opcache_hit_rate'], 2) : 0,
                ];
            }
        }

        return [
            'php_version' => PHP_VERSION,
            'memory_used' => memory_get_usage(true),
            'opcache' => $opcacheStats,
            'request_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        ];
    }

    /**
     * Full system info (for system page).
     */
    private function getSystemInfo(): array
    {
        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        
        // Get system uptime from /proc/uptime (Linux)
        $uptime = null;
        $uptimeFile = '/proc/uptime';
        if (file_exists($uptimeFile) && is_readable($uptimeFile)) {
            $content = @file_get_contents($uptimeFile);
            if ($content !== false) {
                $parts = explode(' ', trim($content));
                $uptime = (float) $parts[0];
            }
        }

        // Get network interfaces if available
        $networkInfo = [];
        if (function_exists('net_get_interfaces')) {
            $interfaces = @net_get_interfaces();
            if ($interfaces) {
                foreach ($interfaces as $name => $iface) {
                    if (isset($iface['unicast']) && $name !== 'lo') {
                        foreach ($iface['unicast'] as $addr) {
                            if (isset($addr['address']) && filter_var($addr['address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                                $networkInfo[$name] = $addr['address'];
                            }
                        }
                    }
                }
            }
        }

        // Get network transfer stats from /proc/net/dev (Linux)
        $networkTransfer = [];
        $netDevFile = '/proc/net/dev';
        if (file_exists($netDevFile) && is_readable($netDevFile)) {
            $lines = @file($netDevFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        $parts = preg_split('/\s+/', trim($line));
                        $iface = rtrim($parts[0], ':');
                        if ($iface !== 'lo' && isset($parts[1], $parts[9])) {
                            $networkTransfer[$iface] = [
                                'rx_bytes' => (int) $parts[1],
                                'tx_bytes' => (int) $parts[9],
                            ];
                        }
                    }
                }
            }
        }

        // OPcache info
        $opcacheEnabled = function_exists('opcache_get_status');
        $opcacheStats = null;
        if ($opcacheEnabled) {
            $status = @opcache_get_status(false);
            if ($status) {
                $opcacheStats = [
                    'enabled' => $status['opcache_enabled'] ?? false,
                    'memory_used' => $status['memory_usage']['used_memory'] ?? 0,
                    'memory_free' => $status['memory_usage']['free_memory'] ?? 0,
                    'hit_rate' => isset($status['opcache_statistics']['opcache_hit_rate']) 
                        ? round($status['opcache_statistics']['opcache_hit_rate'], 2) : 0,
                    'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
                ];
            }
        }

        // Disk usage for various directories
        $contentPath = $this->app->configPath('content');
        $contentSize = $this->getDirectorySize($contentPath);
        $storagePath = $this->app->configPath('storage');
        $storageSize = $this->getDirectorySize($storagePath);
        $themesPath = $this->app->configPath('themes');
        $themesSize = $this->getDirectorySize($themesPath);
        $snippetsPath = $this->app->configPath('snippets');
        $snippetsSize = $this->getDirectorySize($snippetsPath);
        $appPath = $this->app->path('app');
        $appSize = $this->getDirectorySize($appPath);
        $mediaPath = $this->app->config('admin.media.path', 'public/media');
        $mediaFullPath = $this->app->path($mediaPath);
        $mediaSize = is_dir($mediaFullPath) ? $this->getDirectorySize($mediaFullPath) : 0;

        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'memory_used' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_free' => disk_free_space($this->app->path()),
            'disk_total' => disk_total_space($this->app->path()),
            'content_size' => $contentSize,
            'storage_size' => $storageSize,
            'themes_size' => $themesSize,
            'snippets_size' => $snippetsSize,
            'app_size' => $appSize,
            'media_size' => $mediaSize,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'os' => PHP_OS_FAMILY . ' ' . php_uname('r'),
            'hostname' => gethostname(),
            'uptime' => $uptime,
            'load_avg' => $loadAvg,
            'network' => $networkInfo,
            'network_transfer' => $networkTransfer,
            'opcache' => $opcacheStats,
            'extensions' => get_loaded_extensions(),
            'extensions_check' => [
                'yaml' => extension_loaded('yaml'),
                'mbstring' => extension_loaded('mbstring'),
                'json' => extension_loaded('json'),
                'curl' => extension_loaded('curl'),
                'gd' => extension_loaded('gd'),
                'intl' => extension_loaded('intl'),
                'opcache' => $opcacheEnabled,
            ],
            'zend_version' => zend_version(),
            'include_path' => get_include_path(),
            'request_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        ];
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) return 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    private function getRecentContent(int $limit = 5): array
    {
        // Use recentMeta() - no file I/O, just cached metadata
        return $this->app->repository()->recentMeta($limit);
    }

    private function getAvailableSnippets(): array
    {
        $snippetsPath = $this->app->configPath('snippets');
        $snippets = [];

        if (is_dir($snippetsPath)) {
            $files = glob($snippetsPath . '/*.php');
            foreach ($files as $file) {
                $name = basename($file, '.php');
                $snippets[$name] = [
                    'name' => $name,
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                ];
            }
        }

        return $snippets;
    }

    private function getAdminLogs(int $limit = 100): array
    {
        $logsPath = $this->app->configPath('storage') . '/logs/admin.log';
        $logs = [];

        if (file_exists($logsPath)) {
            $lines = file($logsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines); // Most recent first
            $lines = array_slice($lines, 0, $limit);

            foreach ($lines as $line) {
                // Parse log line: [YYYY-MM-DD HH:MM:SS] LEVEL: message | IP: x.x.x.x | UA: ...
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+?)(?:\s*\|\s*IP:\s*([^\|]+))?(?:\s*\|\s*UA:\s*(.+))?$/', $line, $m)) {
                    $logs[] = [
                        'timestamp' => $m[1],
                        'level' => $m[2],
                        'message' => trim($m[3]),
                        'ip' => isset($m[4]) ? trim($m[4]) : null,
                        'user_agent' => isset($m[5]) ? trim($m[5]) : null,
                    ];
                } else {
                    // Fallback for unstructured lines
                    $logs[] = [
                        'timestamp' => '',
                        'level' => 'INFO',
                        'message' => $line,
                        'ip' => null,
                        'user_agent' => null,
                    ];
                }
            }
        }

        return $logs;
    }

    /**
     * Log an admin action.
     */
    public function logAction(string $level, string $message, bool $includeClientInfo = true): void
    {
        $logsPath = $this->app->configPath('storage') . '/logs';
        
        if (!is_dir($logsPath)) {
            @mkdir($logsPath, 0755, true);
        }

        $logFile = $logsPath . '/admin.log';
        $timestamp = date('Y-m-d H:i:s');

        $level = strtoupper(preg_replace('/[^A-Z]/', '', $level));
        if ($level === '') {
            $level = 'INFO';
        }

        $message = trim(preg_replace('/[\r\n\t]+/', ' ', $message));
        
        $logLine = "[{$timestamp}] {$level}: {$message}";
        
        if ($includeClientInfo) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ip = trim(preg_replace('/[\r\n\t\s]+/', ' ', $ip));

            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $ua = trim(preg_replace('/[\r\n\t]+/', ' ', $ua));
            if (strlen($ua) > 512) {
                $ua = substr($ua, 0, 512) . '…';
            }
            $logLine .= " | IP: {$ip} | UA: {$ua}";
        }
        
        $logLine .= "\n";

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    private function getActivePlugins(): array
    {
        $plugins = $this->app->config('plugins', []);
        return is_array($plugins) ? $plugins : [];
    }

    /**
     * Get cache files information.
     */
    private function getCacheFilesInfo(): array
    {
        $cachePath = $this->app->configPath('storage') . '/cache';
        $formatBytes = function($bytes) {
            if ($bytes === 0) return '0 B';
            $units = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            while ($bytes >= 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }
            return round($bytes, 2) . ' ' . $units[$i];
        };

        $files = [
            'content_index.bin' => ['description' => 'Full content index', 'backend' => 'array'],
            'content_index.sqlite' => ['description' => 'SQLite content index', 'backend' => 'sqlite'],
            'slug_lookup.bin' => ['description' => 'Fast single-item lookups'],
            'recent_cache.bin' => ['description' => 'Top 200 items per type'],
            'tax_index.bin' => ['description' => 'Taxonomy terms index'],
            'routes.bin' => ['description' => 'Compiled route mappings'],
            'fingerprint.json' => ['description' => 'Content change detection'],
        ];

        $result = [];
        foreach ($files as $filename => $info) {
            $path = $cachePath . '/' . $filename;
            $exists = file_exists($path);
            
            $entry = [
                'filename' => $filename,
                'description' => $info['description'],
                'exists' => $exists,
                'size' => $exists ? filesize($path) : 0,
                'size_formatted' => $exists ? $formatBytes(filesize($path)) : '—',
                'modified' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
                'backend' => $info['backend'] ?? null,
            ];

            // For binary files, detect serialization format
            if ($exists && str_ends_with($filename, '.bin')) {
                $fp = @fopen($path, 'r');
                if ($fp) {
                    $prefix = fread($fp, 3);
                    fclose($fp);
                    $entry['format'] = match($prefix) {
                        'IG:' => 'igbinary',
                        'SZ:' => 'serialize',
                        default => 'unknown',
                    };
                }
            }

            $result[$filename] = $entry;
        }

        // Count page cache files
        $pagesCachePath = $cachePath . '/pages';
        $pageCount = 0;
        $pagesSize = 0;
        if (is_dir($pagesCachePath)) {
            $files = glob($pagesCachePath . '/*.html');
            $pageCount = count($files);
            foreach ($files as $file) {
                $pagesSize += filesize($file);
            }
        }

        $result['pages'] = [
            'filename' => 'pages/',
            'description' => 'Cached HTML pages',
            'exists' => $pageCount > 0,
            'count' => $pageCount,
            'size' => $pagesSize,
            'size_formatted' => $formatBytes($pagesSize),
            'modified' => null,
        ];

        return $result;
    }

    /**
     * Get directory status information with permissions.
     */
    private function getDirectoryStatus(): array
    {
        $directories = [
            'content' => [
                'key' => 'content',
                'description' => 'Content files (Markdown)',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'themes' => [
                'key' => 'themes',
                'description' => 'Theme templates and assets',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'plugins' => [
                'key' => 'plugins',
                'description' => 'Plugin extensions',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'snippets' => [
                'key' => 'snippets',
                'description' => 'PHP snippets for shortcodes',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'storage' => [
                'key' => 'storage',
                'description' => 'Cache, logs, temp files',
                'recommended' => '0775',
                'writable_needed' => true,
            ],
            'storage/cache' => [
                'key' => 'storage',
                'subdir' => 'cache',
                'description' => 'Content index cache',
                'recommended' => '0775',
                'writable_needed' => true,
            ],
            'storage/logs' => [
                'key' => 'storage',
                'subdir' => 'logs',
                'description' => 'Application logs',
                'recommended' => '0775',
                'writable_needed' => true,
            ],
            'public' => [
                'path' => 'public',
                'description' => 'Web root directory',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'public/media' => [
                'path' => 'public/media',
                'description' => 'Media uploads',
                'recommended' => '0775',
                'writable_needed' => true,
            ],
            'app' => [
                'path' => 'app',
                'description' => 'Application hooks & config',
                'recommended' => '0755',
                'writable_needed' => false,
            ],
            'app/config' => [
                'path' => 'app/config',
                'description' => 'Configuration files (contains credentials)',
                'recommended' => '0750',
                'writable_needed' => false,
            ],
        ];

        $status = [];
        foreach ($directories as $name => $info) {
            // Determine path
            if (isset($info['path'])) {
                $path = $this->app->path($info['path']);
            } else {
                $basePath = $this->app->configPath($info['key']);
                $path = isset($info['subdir']) ? $basePath . '/' . $info['subdir'] : $basePath;
            }

            $exists = file_exists($path);
            $isDir = is_dir($path);
            $isWritable = $exists && is_writable($path);
            $isReadable = $exists && is_readable($path);
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : null;
            $owner = $exists ? (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] ?? fileowner($path) : fileowner($path)) : null;
            $group = $exists ? (function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] ?? filegroup($path) : filegroup($path)) : null;

            $status[$name] = [
                'path' => $path,
                'relative' => str_replace($this->app->path() . '/', '', $path),
                'description' => $info['description'],
                'exists' => $exists,
                'is_dir' => $isDir,
                'readable' => $isReadable,
                'writable' => $isWritable,
                'permissions' => $perms,
                'recommended' => $info['recommended'],
                'writable_needed' => $info['writable_needed'],
                'owner' => $owner,
                'group' => $group,
                'ok' => $exists && $isDir && (!$info['writable_needed'] || $isWritable),
            ];
        }

        return $status;
    }

    /**
     * Get registered hooks information.
     */
    private function getHooksInfo(): array
    {
        // Get currently registered hooks from plugins/themes
        $activeFilters = Hooks::getRegisteredFilters();
        $activeActions = Hooks::getRegisteredActions();

        return [
            'active_filters' => $activeFilters,
            'active_actions' => $activeActions,
        ];
    }

    /**
     * Get path aliases from config.
     */
    private function getPathAliases(): array
    {
        return $this->app->config('paths.aliases', []);
    }

    /**
     * Get debug information including recent error log entries.
     */
    private function getDebugInfo(): array
    {
        $debug = $this->app->config('debug', []);
        $enabled = is_array($debug) ? ($debug['enabled'] ?? false) : (bool) $debug;
        $displayErrors = is_array($debug) ? ($debug['display_errors'] ?? false) : false;
        $logErrors = is_array($debug) ? ($debug['log_errors'] ?? true) : true;
        $level = is_array($debug) ? ($debug['level'] ?? 'errors') : 'errors';

        $errorLogPath = $this->app->path('storage/logs/error.log');
        $recentErrors = [];
        $errorLogSize = 0;
        $errorLogLines = 0;

        if (file_exists($errorLogPath)) {
            $errorLogSize = filesize($errorLogPath);
            
            // Read last portion of error log
            $fp = @fopen($errorLogPath, 'r');
            if ($fp) {
                fseek($fp, -min($errorLogSize, 16384), SEEK_END);
                $chunk = fread($fp, 16384);
                fclose($fp);
                
                $lines = explode("\n", trim($chunk));
                $errorLogLines = count(file($errorLogPath));
                
                // Parse in forward order to attach traces to their parent errors
                $allErrors = [];
                $currentError = null;
                
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    // Parse log line: [timestamp] LEVEL: message
                    if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
                        // Save previous error if exists
                        if ($currentError !== null) {
                            $allErrors[] = $currentError;
                        }
                        $currentError = [
                            'time' => $m[1],
                            'level' => $m[2],
                            'message' => $m[3],
                        ];
                    } elseif ($currentError !== null && str_starts_with(trim($line), '#')) {
                        // Stack trace line - append to current error message
                        $currentError['message'] .= "\n" . $line;
                    }
                    // Skip other continuation lines (Stack trace: header, etc.)
                }
                
                // Don't forget the last error
                if ($currentError !== null) {
                    $allErrors[] = $currentError;
                }
                
                // Take the last 10 errors (most recent)
                $recentErrors = array_slice(array_reverse($allErrors), 0, 10);
            }
        }

        return [
            'enabled' => $enabled,
            'display_errors' => $displayErrors,
            'log_errors' => $logErrors,
            'level' => $level,
            'error_log_path' => 'storage/logs/error.log',
            'error_log_size' => $errorLogSize,
            'error_log_lines' => $errorLogLines,
            'recent_errors' => $recentErrors,
            'php_error_reporting' => error_reporting(),
            'php_display_errors' => ini_get('display_errors'),
            'request_time' => defined('AVA_START') ? round((microtime(true) - AVA_START) * 1000, 2) : null,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Get information about a specific theme.
     */
    private function getThemeInfo(string $themeName, string $themesPath): array
    {
        $themePath = $themesPath . '/' . $themeName;
        
        if (!is_dir($themePath)) {
            return ['error' => 'Theme directory not found'];
        }

        $info = [
            'name' => $themeName,
            'path' => $themePath,
            'has_theme_php' => file_exists($themePath . '/theme.php'),
            'templates' => [],
            'assets' => [],
            'total_size' => 0,
        ];

        // Scan templates
        $templatesPath = $themePath . '/templates';
        if (is_dir($templatesPath)) {
            $templates = glob($templatesPath . '/*.php');
            foreach ($templates as $template) {
                $name = basename($template, '.php');
                $info['templates'][$name] = [
                    'file' => basename($template),
                    'size' => filesize($template),
                    'modified' => filemtime($template),
                ];
                $info['total_size'] += filesize($template);
            }
        }

        // Scan assets
        $assetsPath = $themePath . '/assets';
        if (is_dir($assetsPath)) {
            $info['assets'] = $this->scanThemeAssets($assetsPath);
            foreach ($info['assets'] as $asset) {
                $info['total_size'] += $asset['size'];
            }
        }

        return $info;
    }

    /**
     * Scan theme assets directory recursively.
     */
    private function scanThemeAssets(string $path, string $prefix = ''): array
    {
        $assets = [];
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $path . '/' . $item;
            $relativePath = $prefix ? $prefix . '/' . $item : $item;

            if (is_dir($fullPath)) {
                $assets = array_merge($assets, $this->scanThemeAssets($fullPath, $relativePath));
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $assets[] = [
                    'file' => $relativePath,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath),
                    'type' => $this->getAssetType($ext),
                    'url' => '/theme/' . $relativePath,
                ];
            }
        }

        return $assets;
    }

    /**
     * Get asset type from extension.
     */
    private function getAssetType(string $ext): string
    {
        return match ($ext) {
            'css' => 'stylesheet',
            'js' => 'javascript',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico' => 'image',
            'woff', 'woff2', 'ttf', 'eot', 'otf' => 'font',
            'json' => 'data',
            default => 'other',
        };
    }

    /**
     * Get list of available themes.
     */
    private function getAvailableThemes(string $themesPath): array
    {
        $themes = [];

        if (!is_dir($themesPath)) {
            return $themes;
        }

        $items = scandir($themesPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $themePath = $themesPath . '/' . $item;
            if (is_dir($themePath) && is_dir($themePath . '/templates')) {
                $themes[] = [
                    'name' => $item,
                    'has_theme_php' => file_exists($themePath . '/theme.php'),
                    'template_count' => count(glob($themePath . '/templates/*.php')),
                    'has_assets' => is_dir($themePath . '/assets'),
                ];
            }
        }

        return $themes;
    }

    /**
     * Get common sidebar data for all admin views.
     */
    private function getSidebarData(): array
    {
        return [
            'content' => $this->getContentStats(),
            'taxonomies' => $this->getTaxonomyStats(),
            'taxonomyConfig' => $this->getTaxonomyConfig(),
            'customPages' => Hooks::apply('admin.register_pages', [], $this->app),
            'version' => defined('AVA_VERSION') ? AVA_VERSION : '1.0.0',
            'user' => $this->auth->user(),
            'csrf' => $this->auth->csrfToken(),
            'site' => [
                'name' => $this->app->config('site.name'),
                'url' => $this->app->config('site.base_url'),
                'timezone' => $this->app->config('site.timezone', 'UTC'),
            ],
            'adminTheme' => $this->app->config('admin.theme', 'cyan'),
        ];
    }

    /**
     * Render a core admin page using the layout.
     * 
     * @param string $view View name (without .php) in views/ directory
     * @param array $data Data to pass to the view
     * @param array $layout Layout options (title, icon, headerActions, etc.)
     */
    private function render(string $view, array $data, array $layout = []): string
    {
        $data['admin_url'] = $this->adminUrl();
        $data['ava'] = $this->app;

        // Merge in sidebar data (view data takes precedence)
        $sidebarData = $this->getSidebarData();
        $data = array_merge($sidebarData, $data);

        // If layout options provided, use the layout system
        if (!empty($layout)) {
            // Render content view
            extract($data);
            ob_start();
            include __DIR__ . '/views/' . $view . '.php';
            $pageContent = ob_get_clean();

            // Build layout data
            $layoutData = array_merge($sidebarData, [
                'admin_url' => $this->adminUrl(),
                'ava' => $this->app,
                'pageTitle' => $layout['title'] ?? 'Admin',
                'pageHeading' => $layout['heading'] ?? $layout['title'] ?? 'Admin',
                'pageIcon' => $layout['icon'] ?? 'dashboard',
                'activePage' => $layout['activePage'] ?? '',
                'headerActions' => $layout['headerActions'] ?? '',
                'alertSuccess' => $layout['alertSuccess'] ?? null,
                'alertError' => $layout['alertError'] ?? null,
                'alertWarning' => $layout['alertWarning'] ?? null,
                'pageContent' => $pageContent,
                'pageScripts' => $layout['scripts'] ?? null,
            ]);

            extract($layoutData);
            ob_start();
            include __DIR__ . '/views/_layout.php';
            return ob_get_clean();
        }

        // Standalone view (login page only - no layout wrapper needed)
        extract($data);
        ob_start();
        include __DIR__ . '/views/' . $view . '.php';
        return ob_get_clean();
    }

    /**
     * Render a plugin admin page using the admin layout.
     * 
     * This method allows plugins to define only their main content,
     * while the admin layout (header, sidebar, footer, scripts) is
     * automatically wrapped around it.
     * 
     * @param array $options Page options:
     *   - 'title' (string): Page title for browser tab
     *   - 'heading' (string): Main heading (defaults to title)
     *   - 'icon' (string): Material icon name
     *   - 'activePage' (string): Sidebar highlight identifier
     *   - 'headerActions' (string): HTML for header action buttons
     *   - 'alertSuccess' (string): Success message to display
     *   - 'alertError' (string): Error message to display
     *   - 'alertWarning' (string): Warning message to display
     *   - 'scripts' (string): Additional JavaScript code
     * @param string $content The main page content HTML
     * @return Response
     */
    public function renderPluginPage(array $options, string $content): Response
    {
        $sidebarData = $this->getSidebarData();
        
        $data = array_merge($sidebarData, [
            'admin_url' => $this->adminUrl(),
            'ava' => $this->app,
            'pageTitle' => $options['title'] ?? 'Plugin',
            'pageHeading' => $options['heading'] ?? $options['title'] ?? 'Plugin',
            'pageIcon' => $options['icon'] ?? 'extension',
            'activePage' => $options['activePage'] ?? '',
            'headerActions' => $options['headerActions'] ?? '',
            'alertSuccess' => $options['alertSuccess'] ?? null,
            'alertError' => $options['alertError'] ?? null,
            'alertWarning' => $options['alertWarning'] ?? null,
            'pageContent' => $content,
            'pageScripts' => $options['scripts'] ?? null,
        ]);

        extract($data);

        ob_start();
        include __DIR__ . '/views/_layout.php';
        $html = ob_get_clean();

        return Response::html($html);
    }

    /**
     * Clear error log.
     */
    public function clearErrorLog(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return Response::json(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        // Verify CSRF
        $csrf = $request->post('_csrf', '');
        if (!$this->auth->verifyCsrf($csrf)) {
            return Response::json(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $logFile = $this->app->path('storage/logs/error.log');
        
        if (file_exists($logFile)) {
            file_put_contents($logFile, '', LOCK_EX);
            $this->logAction('INFO', 'Error log cleared');
        }

        return Response::json(['success' => true]);
    }

    /**
     * Get count of errors in the last 24 hours.
     */
    private function getRecentErrorCount(): int
    {
        $errorLogPath = $this->app->path('storage/logs/error.log');
        
        if (!file_exists($errorLogPath)) {
            return 0;
        }
        
        $cutoff = time() - 86400; // 24 hours ago
        $count = 0;
        
        $fp = @fopen($errorLogPath, 'r');
        if (!$fp) {
            return 0;
        }
        
        while (($line = fgets($fp)) !== false) {
            // Parse log line: [timestamp] LEVEL: message
            if (preg_match('/^\[([^\]]+)\]\s+(\w+):/', $line, $m)) {
                $timestamp = strtotime($m[1]);
                if ($timestamp !== false && $timestamp >= $cutoff) {
                    $count++;
                }
            }
        }
        
        fclose($fp);
        return $count;
    }

    /**
     * Get media upload stats for dashboard.
     */
    private function getMediaStats(): array
    {
        $uploader = new MediaUploader($this->app);
        
        if (!$uploader->isEnabled()) {
            return ['enabled' => false];
        }
        
        $limits = $uploader->getUploadLimits();
        $folders = $uploader->getExistingFolders(false); // Include date folders for count
        
        return [
            'enabled' => true,
            'writable' => $uploader->isWritable(),
            'processor' => $limits['has_imagick'] ? 'ImageMagick' : ($limits['has_gd'] ? 'GD' : null),
            'max_size' => $limits['max_file_size_formatted'],
            'folder_count' => count($folders),
            'extensions' => $limits['allowed_extensions'],
        ];
    }

    /**
     * Get the Application instance for plugins.
     */
    public function app(): Application
    {
        return $this->app;
    }

    /**
     * Get default header actions HTML.
     *
     * @param string $docsPath Unused, kept for backwards compatibility
     */
    private function defaultHeaderActions(string $docsPath = ''): string
    {
        // View Site is now in topbar, so default header actions is empty
        return '';
    }

    private function adminUrl(): string
    {
        return $this->app->config('admin.path', '/admin');
    }
}
