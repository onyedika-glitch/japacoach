<?php

namespace App\Services\Push;

/**
 * What a push notification shows, and which screen in the app it opens when tapped.
 */
final class PushMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $route = null,
    ) {}
}
