<?php

namespace Tests\Unit\Ai;

use App\Enums\Department;
use App\Mail\RoutedMessageMail;
use Tests\TestCase;

final class RoutedMessageMailTest extends TestCase
{
    public function test_it_renders_the_original_message_and_targets_the_department(): void
    {
        $mail = new RoutedMessageMail(
            department: Department::Kadry,
            senderEmail: 'anna@example.com',
            subjectLine: 'Wniosek urlopowy',
            summary: 'The employee asks for a day off.',
            originalMessage: 'Chciałabym zgłosić urlop na jutro',
        );

        $mail->assertHasTo('kadry@example.com')
            ->assertHasReplyTo('anna@example.com')
            ->assertHasSubject('Wniosek urlopowy')
            ->assertSeeInHtml('Chciałabym zgłosić urlop na jutro')
            ->assertSeeInHtml('anna@example.com')
            ->assertSeeInHtml('The employee asks for a day off.');
    }
}
