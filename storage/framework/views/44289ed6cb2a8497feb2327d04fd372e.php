<?php echo $__env->make('errors.layout', [
    'code' => 503,
    'title' => 'Down For A Moment',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This panel is being updated and will be back shortly.</p>'
        .'<p>Your game servers are not affected. They run on the node and keep running while this is happening.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/503.blade.php ENDPATH**/ ?>