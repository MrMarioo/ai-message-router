<?php

namespace Tests\Feature\Routing;

use App\Ai\Agents\RouteMessageAgent;
use App\Enums\Department;
use App\Enums\RoutedBy;
use App\Mail\RoutedMessageMail;
use App\Services\Routing\MessageRouter;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Responses\Data\ToolCall;
use RuntimeException;
use Tests\TestCase;

final class MessageRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_agent_decision_is_executed_through_the_email_tool(): void
    {
        RouteMessageAgent::fake([
            new ToolCall('call-1', 'send_department_email', [
                'to' => 'kadry@example.com',
                'subject' => 'Wniosek urlopowy',
                'summary' => 'The employee wants a day off tomorrow.',
            ]),
            'Forwarded to kadry@example.com.',
        ]);

        $result = app(MessageRouter::class)->route(
            senderEmail: 'anna.kowalska@example.com',
            message: 'Chciałabym zgłosić urlop na jutro',
        );

        $this->assertSame(Department::Kadry, $result->department);
        $this->assertSame(RoutedBy::Agent, $result->routedBy);
        $this->assertSame('Wniosek urlopowy', $result->subject);
        $this->assertSame('Forwarded to kadry@example.com.', $result->agentReply);

        Mail::assertSent(
            RoutedMessageMail::class,
            fn (RoutedMessageMail $mail) => $mail->hasTo('kadry@example.com')
                && $mail->hasReplyTo('anna.kowalska@example.com'),
        );
        Mail::assertSentCount(1);
        RouteMessageAgent::assertPromptedTimes(1);
        RouteMessageAgent::assertPrompted(fn ($prompt) => $prompt->contains('Chciałabym zgłosić urlop na jutro'));
    }

    public function test_it_retries_once_when_the_agent_answers_without_calling_the_tool(): void
    {
        RouteMessageAgent::fake([
            'I think this belongs to IT.',
            new ToolCall('call-1', 'send_department_email', [
                'to' => 'it@example.com',
                'subject' => 'VPN outage',
                'summary' => 'VPN does not connect.',
            ]),
            'Forwarded to it@example.com.',
        ]);

        $result = app(MessageRouter::class)->route(senderEmail: 'jan@example.com', message: 'VPN nie działa');

        $this->assertSame(Department::It, $result->department);
        $this->assertSame(RoutedBy::Agent, $result->routedBy);
        RouteMessageAgent::assertPromptedTimes(2);
        Mail::assertSentCount(1);
    }

    public function test_it_falls_back_to_other_when_the_agent_never_calls_the_tool(): void
    {
        RouteMessageAgent::fake(['No idea.', 'Still no idea.']);

        $result = app(MessageRouter::class)->route(senderEmail: 'jan@example.com', message: 'Hmm');

        $this->assertSame(Department::Other, $result->department);
        $this->assertSame(RoutedBy::Fallback, $result->routedBy);
        $this->assertStringContainsString('Hmm', $result->subject);

        Mail::assertSent(
            RoutedMessageMail::class,
            fn (RoutedMessageMail $mail) => $mail->hasTo('other@example.com') && $mail->hasReplyTo('jan@example.com'),
        );
        Mail::assertSentCount(1);
    }

    public function test_provider_failures_propagate(): void
    {
        RouteMessageAgent::fake(fn () => throw new RuntimeException('Connection refused'));

        $this->expectException(RuntimeException::class);

        app(MessageRouter::class)->route(senderEmail: 'jan@example.com', message: 'Hello');
    }
}
