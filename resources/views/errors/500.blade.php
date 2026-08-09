{{-- No exception message here on purpose. A 500 is an unhandled fault and its
     message can carry a query, a path or a credential; the log is where it
     belongs, not the customer's screen. --}}
@include('errors.layout', [
    'code' => 500,
    'title' => 'Something Went Wrong At Our End',
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>This is a fault in the panel, not something you did.</p>'
        .'<p>Your game servers are unaffected: they run on the node and keep running whatever this panel is doing. The details have been written to the log.</p>'
    ),
])
