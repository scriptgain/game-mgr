{{-- The one people misread as "it is broken". It almost always means a form sat
     open longer than the session lasted. --}}
@include('errors.layout', [
    'code' => 419,
    'title' => 'That Page Sat Too Long',
    'showLogin' => true,
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>The form you just submitted was opened a while ago and its security token has since expired.</p>'
        .'<p>Nothing was saved and nothing was broken. Sign in again and repeat what you were doing.</p>'
    ),
])
