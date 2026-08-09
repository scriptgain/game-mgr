<?php echo $__env->make('errors.layout', [
    'code' => 404,
    'title' => 'There Is Nothing Here',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This address does not point at anything.</p>'
        .'<p>If you followed a link from somewhere in the panel, the thing it pointed at has probably been deleted since.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/404.blade.php ENDPATH**/ ?>