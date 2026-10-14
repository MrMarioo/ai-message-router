<?php

namespace App\Ai\Tools;

use App\Enums\Department;
use App\Mail\RoutedMessageMail;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SendDepartmentEmail implements Tool
{
    private ?Department $sentTo = null;

    private ?string $sentSubject = null;

    public function __construct(
        private readonly string $senderEmail,
        private readonly string $originalMessage,
    ) {}

    public function name(): string
    {
        return 'send_department_email';
    }

    public function description(): string
    {
        return 'Forward the user\'s request to the one department responsible for it by email. '
            .'Call it exactly once per request, after you have decided which department should handle the message.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()
                ->enum(Department::addresses())
                ->description('Email address of the department that should handle the request.')
                ->required(),
            'subject' => $schema->string()
                ->description('Short email subject (max 10 words) summarising the request, in the language of the original message.')
                ->required(),
            'summary' => $schema->string()
                ->description('One or two sentences explaining what the user needs and why this department was chosen.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        if ($this->sentTo !== null) {
            return sprintf('The email was already sent to %s. Do not call this tool again.', $this->sentTo->value);
        }

        $missing = array_diff(['to', 'subject', 'summary'], array_keys(array_filter($request->all(), 'filled')));

        if ($missing !== []) {
            return sprintf('Missing required arguments: %s. Call the tool again with all arguments.', implode(', ', $missing));
        }

        $department = Department::tryFromAddress($request['to']) ?? Department::fallback();

        Mail::to($department->value)->send(new RoutedMessageMail(
            department: $department,
            senderEmail: $this->senderEmail,
            subjectLine: (string) $request['subject'],
            summary: (string) $request['summary'],
            originalMessage: $this->originalMessage,
        ));

        $this->sentTo = $department;
        $this->sentSubject = (string) $request['subject'];

        return sprintf('Email sent to %s. Reply-To is set to %s. You are done.', $department->value, $this->senderEmail);
    }

    public function sentTo(): ?Department
    {
        return $this->sentTo;
    }

    public function sentSubject(): ?string
    {
        return $this->sentSubject;
    }
}
