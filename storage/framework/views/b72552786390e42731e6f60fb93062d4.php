
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($spec['info']['title']); ?> Reference</title>
    <meta name="description" content="Every endpoint in the <?php echo e(config('brand.name')); ?> API, generated from the routes themselves.">
    <link rel="icon" type="image/svg+xml" href="<?php echo e(route('favicon.svg')); ?>">
    <?php if (isset($component)) { $__componentOriginald8148f5689903f6ad943797ae197f7c9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald8148f5689903f6ad943797ae197f7c9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tailwind-cdn','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tailwind-cdn'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald8148f5689903f6ad943797ae197f7c9)): ?>
<?php $attributes = $__attributesOriginald8148f5689903f6ad943797ae197f7c9; ?>
<?php unset($__attributesOriginald8148f5689903f6ad943797ae197f7c9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald8148f5689903f6ad943797ae197f7c9)): ?>
<?php $component = $__componentOriginald8148f5689903f6ad943797ae197f7c9; ?>
<?php unset($__componentOriginald8148f5689903f6ad943797ae197f7c9); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal5c21fda6aabe43da67ada52ce874b5b1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.accent-style','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('accent-style'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1)): ?>
<?php $attributes = $__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1; ?>
<?php unset($__attributesOriginal5c21fda6aabe43da67ada52ce874b5b1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c21fda6aabe43da67ada52ce874b5b1)): ?>
<?php $component = $__componentOriginal5c21fda6aabe43da67ada52ce874b5b1; ?>
<?php unset($__componentOriginal5c21fda6aabe43da67ada52ce874b5b1); ?>
<?php endif; ?>
    <style>
        /* Plain CSS, not Tailwind utilities, for the handful of things the CDN
           build cannot express: the two column shell, the method chips, and the
           scroll offset that stops an anchor landing under the sticky header. */
        :root { --nav: 17rem; }
        body { background: #f8fafc; }
        .docs { display: grid; grid-template-columns: var(--nav) minmax(0, 1fr); gap: 2.5rem; align-items: start; }
        @media (max-width: 1024px) { .docs { grid-template-columns: 1fr; gap: 1.5rem; } .docs-aside { position: static !important; max-height: none !important; } }
        .docs-aside { position: sticky; top: 5rem; max-height: calc(100vh - 7rem); overflow-y: auto; overscroll-behavior: contain; }
        .docs-aside::-webkit-scrollbar { width: 6px; }
        .docs-aside::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .op { scroll-margin-top: 5.5rem; }
        .m { display: inline-flex; align-items: center; justify-content: center; min-width: 3.75rem; padding: .1rem .4rem;
             border-radius: .3rem; font-size: .625rem; font-weight: 700; letter-spacing: .04em; font-family: ui-monospace, monospace; }
        .m-GET { background: #ecfdf5; color: #047857; }
        .m-POST { background: #eff6ff; color: #1d4ed8; }
        .m-PATCH, .m-PUT { background: #fffbeb; color: #b45309; }
        .m-DELETE { background: #fef2f2; color: #b91c1c; }
        /* A path is one unbroken run and will set the width floor of the page
           if it is allowed to. Nothing here ever scrolls sideways. */
        .path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
        .nav-a { display: flex; align-items: center; gap: .5rem; padding: .25rem .5rem; border-radius: .375rem;
                 font-size: .8125rem; color: #475569; text-decoration: none; }
        .nav-a:hover { background: #f1f5f9; color: #0f172a; }
        .hide { display: none !important; }
    </style>
</head>
<body class="h-full text-slate-900">

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3 min-w-0">
            <span class="text-base font-semibold tracking-tight"><?php echo e(config('brand.name')); ?> API</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-500">v<?php echo e($spec['info']['version']); ?></span>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <a href="<?php echo e(route('docs')); ?>" class="text-slate-600 hover:text-slate-900">Guide</a>
            <a href="<?php echo e(route('api.openapi')); ?>" class="text-slate-600 hover:text-slate-900">openapi.json</a>
            <a href="<?php echo e(url('/')); ?>" class="font-medium text-brand-700 hover:text-brand-800">Open The Panel</a>
        </div>
    </div>
</header>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
    <div class="docs">

        
        <aside class="docs-aside">
            <label class="sr-only" for="docs-filter">Filter endpoints</label>
            <input id="docs-filter" type="search" placeholder="Filter endpoints"
                   class="mb-3 w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">

            <nav class="space-y-4" aria-label="Endpoints">
                <a href="#start" class="nav-a font-medium text-slate-900">Getting Started</a>
                <?php $__currentLoopData = $scopes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scope => $resources): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div data-nav-scope>
                        <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-slate-400"><?php echo e($scope); ?></p>
                        <?php $__currentLoopData = $resources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resource => $operations): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div data-nav-group>
                                <a href="#<?php echo e(Str::slug($scope.'-'.$resource)); ?>" class="nav-a font-medium text-slate-700"><?php echo e($resource); ?></a>
                                <div class="ml-2 border-l border-slate-200 pl-2">
                                    <?php $__currentLoopData = $operations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <a href="#<?php echo e($op['anchor']); ?>" class="nav-a" data-nav-op
                                           data-search="<?php echo e(strtolower($op['method'].' '.$op['path'].' '.$op['summary'])); ?>">
                                            <span class="m m-<?php echo e($op['method']); ?>"><?php echo e($op['method']); ?></span>
                                            <span class="truncate"><?php echo e($op['summary']); ?></span>
                                        </a>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>
        </aside>

        <main class="min-w-0 space-y-10">

            <section id="start" class="op">
                <h1 class="text-3xl font-semibold tracking-tight">API Reference</h1>
                <div class="mt-3 space-y-3 text-slate-600">
                    <?php $__currentLoopData = explode("\n\n", $spec['info']['description']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paragraph): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        
                        <p class="leading-relaxed"><?php echo preg_replace(
                            '/`([^`]+)`/',
                            '<code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[0.85em] text-slate-800">$1</code>',
                            nl2br(e($paragraph)),
                        ); ?></p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Base URL</p>
                        <p class="path mt-1 text-sm"><?php echo e($baseUrl); ?></p>
                    </div>
                    <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Authentication</p>
                        <p class="path mt-1 text-sm">Authorization: Bearer &lt;token&gt;</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-900 p-4">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">A First Call</p>
                    <pre class="overflow-x-auto text-xs leading-relaxed text-slate-100"><code>curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     <?php echo e($baseUrl); ?>/api/client/servers</code></pre>
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Request bodies are not described yet.</p>
                    <p class="mt-1">
                        Every endpoint below lists its path and query parameters, which are generated from the routes.
                        The JSON body a write endpoint accepts is not, so those fields still have to come from the panel
                        form that does the same job. That gap is real and worth closing; it is not an oversight nobody
                        noticed.
                    </p>
                </div>
            </section>

            <?php $__currentLoopData = $scopes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scope => $resources): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <section data-scope>
                    <div class="mb-4 flex items-baseline gap-3 border-b border-slate-200 pb-2">
                        <h2 class="text-xl font-semibold capitalize tracking-tight"><?php echo e($scope); ?></h2>
                        <p class="text-sm text-slate-500">
                            <?php echo e(collect($spec['tags'])->firstWhere('name', $scope)['description'] ?? ''); ?>

                        </p>
                    </div>

                    <div class="space-y-8">
                        <?php $__currentLoopData = $resources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resource => $operations): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div data-group>
                                <h3 id="<?php echo e(Str::slug($scope.'-'.$resource)); ?>" class="op mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                                    <?php echo e($resource); ?>

                                </h3>

                                <div class="space-y-3">
                                    <?php $__currentLoopData = $operations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <article id="<?php echo e($op['anchor']); ?>" data-op
                                                 data-search="<?php echo e(strtolower($op['method'].' '.$op['path'].' '.$op['summary'])); ?>"
                                                 class="op overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-slate-100 px-4 py-3">
                                                <span class="m m-<?php echo e($op['method']); ?>"><?php echo e($op['method']); ?></span>
                                                <span class="path min-w-0 text-sm text-slate-900"><?php echo e($op['path']); ?></span>
                                                <a href="#<?php echo e($op['anchor']); ?>" class="ml-auto text-xs text-slate-400 hover:text-slate-700" aria-label="Link to this endpoint">#</a>
                                            </div>

                                            <div class="space-y-3 px-4 py-3">
                                                <p class="text-sm text-slate-700"><?php echo e($op['summary']); ?></p>

                                                <?php if($op['parameters']): ?>
                                                    <div>
                                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Parameters</p>
                                                        <dl class="divide-y divide-slate-100 rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-200">
                                                            <?php $__currentLoopData = $op['parameters']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-3 py-2">
                                                                    <dt class="path text-xs font-medium text-slate-900"><?php echo e($parameter['name']); ?></dt>
                                                                    <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-500 ring-1 ring-inset ring-slate-200">
                                                                        <?php echo e($parameter['in']); ?><?php echo e(($parameter['required'] ?? false) ? ', required' : ''); ?>

                                                                    </span>
                                                                    <dd class="min-w-0 flex-1 text-xs text-slate-500"><?php echo e($parameter['description'] ?? ''); ?></dd>
                                                                </div>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        </dl>
                                                    </div>
                                                <?php endif; ?>

                                                <details class="group">
                                                    <summary class="cursor-pointer text-xs font-medium text-brand-700 hover:text-brand-800">Show a curl example</summary>
                                                    <div class="mt-2 rounded-lg bg-slate-900 p-3">
                                                        <pre class="overflow-x-auto text-xs leading-relaxed text-slate-100"><code>curl -X <?php echo e($op['method']); ?> \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
<?php if(in_array($op['method'], ['POST', 'PATCH', 'PUT'], true)): ?>  -H "Content-Type: application/json" \
  -d '{}' \
<?php endif; ?>  "<?php echo e($baseUrl); ?><?php echo e($op['path']); ?>"</code></pre>
                                                    </div>
                                                </details>
                                            </div>
                                        </article>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <p id="docs-empty" class="hide rounded-xl bg-white px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                Nothing matches that. Try a shorter term, or part of a path such as <span class="path">backups</span>.
            </p>

            <footer class="border-t border-slate-200 pt-6 text-xs text-slate-400">
                Generated from the routes of this install, so it describes this version and no other.
                The machine-readable source is <a href="<?php echo e(route('api.openapi')); ?>" class="underline hover:text-slate-600">openapi.json</a>.
            </footer>
        </main>
    </div>
</div>

<script src="<?php echo e(asset('js/api-docs.js')); ?>" defer></script>
</body>
</html>
<?php /**PATH /var/www/gamemgr/resources/views/api-docs.blade.php ENDPATH**/ ?>