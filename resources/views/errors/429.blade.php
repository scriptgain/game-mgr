@include('errors.layout', [
    'code' => 429,
    'title' => 'Slow Down A Moment',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>That came through faster than this panel accepts, so the rest has been held back.</p>'
        .'<p>Wait a minute and try again. Nothing has been lost.</p>'
    ),
])
