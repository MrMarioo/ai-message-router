<?php

namespace App\Http\Resources;

use App\Services\Routing\RoutingResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoutingResult */
final class RoutingResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'department' => $this->department->value,
            'subject' => $this->subject,
            'routed_by' => $this->routedBy->value,
            'agent_reply' => $this->agentReply,
        ];
    }
}
