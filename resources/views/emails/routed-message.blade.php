<x-mail::message>
# New request routed to {{ $department }}

**From:** {{ $senderEmail }}

**Summary:** {{ $summary }}

## Original message

<x-mail::panel>
{{ $originalMessage }}
</x-mail::panel>

Reply directly to this email to answer the sender.

{{ config('app.name') }}
</x-mail::message>
