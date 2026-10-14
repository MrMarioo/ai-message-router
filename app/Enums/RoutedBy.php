<?php

namespace App\Enums;

enum RoutedBy: string
{
    case Agent = 'agent';
    case Fallback = 'fallback';
}
