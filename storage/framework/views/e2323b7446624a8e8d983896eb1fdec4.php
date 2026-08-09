
<?php echo $__env->make('errors.layout', [
    'code' => 403,
    'title' => 'That Is Not Yours To Open',
    'detail' => $exception?->getMessage() ?: null,
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>Your account can reach this panel, but not this particular thing.</p>'
        .'<p>If it is a server somebody shared with you, the person who owns it decides which parts you can see. Ask them to widen your access on the Users tab of that server.</p>'
    ),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH /var/www/gamemgr/resources/views/errors/403.blade.php ENDPATH**/ ?>