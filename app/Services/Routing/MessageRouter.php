<?php

namespace App\Services\Routing;

use App\Ai\Agents\RouteMessageAgent;
use App\Ai\Tools\SendDepartmentEmail;
use App\Enums\Department;
use App\Enums\RoutedBy;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;
use Psr\Log\LoggerInterface;

final class MessageRouter
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function route(string $senderEmail, string $message): RoutingResult
    {
        $tool = new SendDepartmentEmail(senderEmail: $senderEmail, originalMessage: $message);
        $agent = RouteMessageAgent::make(tool: $tool);

        $response = $agent->prompt(prompt: $this->buildPrompt(senderEmail: $senderEmail, message: $message));

        if ($tool->sentTo() === null) {
            $this->logger->warning('Routing agent finished without calling the email tool, retrying once.', ['reply' => (string) $response]);

            $response = $agent->prompt(prompt: $this->buildRetryPrompt(senderEmail: $senderEmail, message: $message));
        }

        if ($tool->sentTo() === null) {
            return $this->fallback(tool: $tool, message: $message, response: $response);
        }

        return new RoutingResult(
            department: $tool->sentTo(),
            subject: (string) $tool->sentSubject(),
            routedBy: RoutedBy::Agent,
            agentReply: trim((string) $response),
        );
    }

    private function fallback(SendDepartmentEmail $tool, string $message, AgentResponse $response): RoutingResult
    {
        $this->logger->error('Routing agent did not call the email tool after a retry, routing to the fallback department.', ['reply' => (string) $response]);

        $subject = sprintf('[Unclassified] %s', Str::limit($message, 60));

        $tool->handle(new Request([
            'to' => Department::fallback()->value,
            'subject' => $subject,
            'summary' => 'The AI agent could not classify this message, so it was routed to the fallback department automatically.',
        ]));

        return new RoutingResult(
            department: Department::fallback(),
            subject: $subject,
            routedBy: RoutedBy::Fallback,
            agentReply: trim((string) $response),
        );
    }

    private function buildPrompt(string $senderEmail, string $message): string
    {
        return sprintf("Sender: %s\n\nMessage:\n%s", $senderEmail, $message);
    }

    private function buildRetryPrompt(string $senderEmail, string $message): string
    {
        return sprintf(
            "You did not call the send_department_email tool. You MUST call it now, exactly once, for the message below. Do not reply with text until the tool has been called.\n\nSender: %s\n\nMessage:\n%s",
            $senderEmail,
            $message,
        );
    }
}
