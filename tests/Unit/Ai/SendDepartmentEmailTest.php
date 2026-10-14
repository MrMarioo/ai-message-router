<?php

namespace Tests\Unit\Ai;

use App\Ai\Tools\SendDepartmentEmail;
use App\Enums\Department;
use App\Mail\RoutedMessageMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

final class SendDepartmentEmailTest extends TestCase
{
    private SendDepartmentEmail $tool;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tool = new SendDepartmentEmail(
            senderEmail: 'jan.nowak@example.com',
            originalMessage: 'Nie działa mi komputer',
        );
    }

    public function test_it_emails_the_requested_department_with_reply_to_set_to_the_sender(): void
    {
        $this->tool->handle(new Request([
            'to' => 'help-desk@example.com',
            'subject' => 'Computer not working',
            'summary' => 'The user reports their computer does not work.',
        ]));

        Mail::assertSent(
            RoutedMessageMail::class,
            fn (RoutedMessageMail $mail) => $mail->hasTo('help-desk@example.com')
                && $mail->hasReplyTo('jan.nowak@example.com')
                && $mail->hasSubject('Computer not working'),
        );

        $this->assertSame(Department::HelpDesk, $this->tool->sentTo());
        $this->assertSame('Computer not working', $this->tool->sentSubject());
    }

    public function test_an_address_outside_the_allowed_list_falls_back_to_other(): void
    {
        $this->tool->handle(new Request([
            'to' => 'ceo@example.com',
            'subject' => 'Hello',
            'summary' => 'Something',
        ]));

        Mail::assertSent(RoutedMessageMail::class, fn (RoutedMessageMail $mail) => $mail->hasTo('other@example.com'));
        $this->assertSame(Department::Other, $this->tool->sentTo());
    }

    public function test_it_sends_at_most_one_email_per_request(): void
    {
        $arguments = ['to' => 'it@example.com', 'subject' => 'VPN', 'summary' => 'VPN down'];

        $this->tool->handle(new Request($arguments));
        $reply = $this->tool->handle(new Request($arguments));

        Mail::assertSentCount(1);
        $this->assertStringContainsString('already', (string) $reply);
    }

    public function test_missing_arguments_are_reported_back_to_the_model_instead_of_sending(): void
    {
        $reply = $this->tool->handle(new Request(['subject' => 'No recipient']));

        Mail::assertNothingSent();
        $this->assertNull($this->tool->sentTo());
        $this->assertStringContainsString('to', (string) $reply);
    }

    public function test_the_model_sees_the_tool_under_a_stable_name(): void
    {
        $this->assertSame('send_department_email', $this->tool->name());
    }
}
