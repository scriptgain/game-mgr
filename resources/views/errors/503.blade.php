@include('errors.layout', [
    'code' => 503,
    'title' => 'Down For A Moment',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This panel is being updated and will be back shortly.</p>'
        .'<p>Your game servers are not affected. They run on the node and keep running while this is happening.</p>'
    ),
])
