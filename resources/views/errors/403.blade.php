{{-- The message the customer sees most, because it is what a subuser hits when
     they open a tab their permissions do not cover. Saying "denied" and nothing
     else sends them to support; naming the reason usually does not. --}}
@include('errors.layout', [
    'code' => 403,
    'title' => 'That Is Not Yours To Open',
    'detail' => $exception?->getMessage() ?: null,
    'slot' => new \Illuminate\Support\HtmlString(
        '<p>Your account can reach this panel, but not this particular thing.</p>'
        .'<p>If it is a server somebody shared with you, the person who owns it decides which parts you can see. Ask them to widen your access on the Users tab of that server.</p>'
    ),
])
