
<?php echo $__env->make('errors.layout', [
    'code' => 419,
    'title' => 'That Page Sat Too Long',
    'showLogin' => true,
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>The form you just submitted was opened a while ago and its security token has since expired.</p>'
        .'<p>Nothing was saved and nothing was broken. Sign in again and repeat what you were doing.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/419.blade.php ENDPATH**/ ?>