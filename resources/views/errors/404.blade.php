@include('errors.layout', [
    'code' => 404,
    'title' => 'There Is Nothing Here',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This address does not point at anything.</p>'
        .'<p>If you followed a link from somewhere in the panel, the thing it pointed at has probably been deleted since.</p>'
    ),
])
