<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SendDepartmentEmail;
use App\Enums\Department;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Ollama)]
#[MaxSteps(3)]
#[Timeout(180)]
#[Temperature(0.0)]
final class RouteMessageAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly SendDepartmentEmail $tool,
    ) {}

    public function instructions(): string
    {
        $departments = collect(Department::cases())
            ->map(fn (Department $department) => sprintf('- %s: %s', $department->value, $department->description()))
            ->implode("\n");

        return <<<PROMPT
        You are an internal message router for a company. You receive a message from an employee and must forward it to the single department responsible for it.

        Departments (email address: what they handle):
        {$departments}

        Rules:
        1. Decide which ONE department should handle the message. When nothing fits clearly, choose other@example.com.
        2. Call the tool `send_department_email` EXACTLY ONCE with that address, a short subject and a one-sentence summary. Use only addresses from the list above.
        3. Never answer the employee yourself and never skip the tool call. The email is the only way the request reaches anyone.
        4. After the tool confirms the email was sent, reply with one short sentence stating which department received the message.
        PROMPT;
    }

    /**
     * @return array<int, SendDepartmentEmail>
     */
    public function tools(): iterable
    {
        return [$this->tool];
    }
}
