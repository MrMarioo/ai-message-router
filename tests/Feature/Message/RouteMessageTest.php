<?php

namespace Tests\Feature\Message;

use App\Ai\Agents\RouteMessageAgent;
use App\Mail\RoutedMessageMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\TestCase;

final class RouteMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_a_message_is_routed_and_the_decision_is_returned(): void
    {
        RouteMessageAgent::fake([
            new ToolCall('call-1', 'send_department_email', [
                'to' => 'help-desk@example.com',
                'subject' => 'Computer not working',
                'summary' => 'The computer does not start.',
            ]),
            'Forwarded to help-desk@example.com.',
        ]);

        $this->postJson('/api/v1/messages', [
            'email' => 'jan.nowak@example.com',
            'message' => 'Nie działa mi komputer',
        ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'department' => 'help-desk@example.com',
                    'subject' => 'Computer not working',
                    'routed_by' => 'agent',
                    'agent_reply' => 'Forwarded to help-desk@example.com.',
                ],
            ]);

        Mail::assertSent(
            RoutedMessageMail::class,
            fn (RoutedMessageMail $mail) => $mail->hasTo('help-desk@example.com') && $mail->hasReplyTo('jan.nowak@example.com'),
        );
        RouteMessageAgent::assertPrompted(
            fn ($prompt) => $prompt->contains('Nie działa mi komputer') && $prompt->contains('jan.nowak@example.com'),
        );
    }

    public function test_invalid_input_is_rejected_before_the_agent_is_called(): void
    {
        RouteMessageAgent::fake()->preventStrayPrompts();

        $this->postJson('/api/v1/messages', ['email' => 'not-an-email', 'message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'message']);

        RouteMessageAgent::assertNeverPrompted();
        Mail::assertNothingSent();
    }

    public function test_an_unreachable_model_results_in_a_502(): void
    {
        RouteMessageAgent::fake(fn () => throw ProviderConnectionException::forProvider('ollama'));

        $this->postJson('/api/v1/messages', ['email' => 'jan@example.com', 'message' => 'Hello'])
            ->assertStatus(502)
            ->assertJson(['message' => 'Could not connect to AI provider [ollama].']);

        Mail::assertNothingSent();
    }

    public function test_the_openapi_documentation_is_served_under_api_v1_docs(): void
    {
        $this->get('/api/v1/docs')->assertOk()->assertSee('AI Message Router');

        $this->getJson('/api/v1/docs.json')
            ->assertOk()
            ->assertJsonPath('info.title', 'AI Message Router')
            ->assertJsonPath('paths./messages.post.requestBody.content.application/json.schema.$ref', '#/components/schemas/RouteMessageRequest')
            ->assertJsonPath('components.schemas.RouteMessageRequest.required', ['email', 'message'])
            ->assertJsonPath('paths./messages.post.responses.200.content.application/json.schema.properties.data.properties.routed_by.enum', ['agent', 'fallback'])
            ->assertJsonPath('paths./messages.post.responses.502.description', 'The LLM could not be reached or returned an error.');
    }
}
