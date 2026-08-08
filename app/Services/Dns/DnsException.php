<?php

namespace App\Services\Dns;

/**
 * Anything a provider could not do. Every caller inside the panel catches this
 * and records it against the node; nothing in a request path is allowed to let
 * it escape, because a DNS provider having a bad afternoon must not be able to
 * take a page down with it.
 */
class DnsException extends \RuntimeException {}
