@include('errors.layout', [
    'code' => 401,
    'title' => 'Please Sign In',
    'showLogin' => true,
    'slot' => new \Illuminate\Support\HtmlString('<p>This page needs you to be signed in.</p>'),
])
