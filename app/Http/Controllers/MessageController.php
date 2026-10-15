<?php

namespace App\Http\Controllers;

use App\Http\Requests\Message\RouteMessageRequest;
use App\Http\Resources\RoutingResultResource;
use App\Services\Routing\MessageRouter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageRouter $router,
    ) {}

    /**
     * Route a message to a department
     *
     * Classifies the message with a local LLM agent (Ollama). The agent itself calls the
     * `send_department_email` tool, which emails the chosen department with `Reply-To`
     * set to the sender. The call is synchronous and typically takes a few seconds on CPU.
     *
     * `routed_by` is `agent` when the model called the tool itself, or `fallback` when it
     * failed to do so twice and the message was forwarded to `other@example.com` instead.
     */
    #[Response(HttpResponse::HTTP_OK, 'The message was forwarded to a department.', type: 'array{data: array{department: string, subject: string, routed_by: "agent"|"fallback", agent_reply: string}}')]
    #[Response(HttpResponse::HTTP_BAD_GATEWAY, 'The LLM could not be reached or returned an error.', type: 'array{message: string}')]
    public function store(RouteMessageRequest $request): JsonResponse
    {
        $result = $this->router->route(
            senderEmail: $request->string(key: 'email')->toString(),
            message: $request->string(key: 'message')->toString(),
        );

        return response()->json(
            data: ['data' => RoutingResultResource::make($result)->resolve()],
            status: HttpResponse::HTTP_OK,
        );
    }
}
