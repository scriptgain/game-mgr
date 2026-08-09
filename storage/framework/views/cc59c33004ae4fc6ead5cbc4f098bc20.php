
<?php echo $__env->make('errors.layout', [
    'code' => 500,
    'title' => 'Something Went Wrong At Our End',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This is a fault in the panel, not something you did.</p>'
        .'<p>Your game servers are unaffected: they run on the node and keep running whatever this panel is doing. The details have been written to the log.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/500.blade.php ENDPATH**/ ?>