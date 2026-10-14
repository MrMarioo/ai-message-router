<?php

namespace App\Services\Routing;

use App\Enums\Department;
use App\Enums\RoutedBy;

final class RoutingResult
{
    public function __construct(
        public readonly Department $department,
        public readonly string $subject,
        public readonly RoutedBy $routedBy,
        public readonly string $agentReply,
    ) {}
}
