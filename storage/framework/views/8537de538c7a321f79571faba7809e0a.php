<?php echo $__env->make('errors.layout', [
    'code' => 429,
    'title' => 'Slow Down A Moment',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>That came through faster than this panel accepts, so the rest has been held back.</p>'
        .'<p>Wait a minute and try again. Nothing has been lost.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/429.blade.php ENDPATH**/ ?>