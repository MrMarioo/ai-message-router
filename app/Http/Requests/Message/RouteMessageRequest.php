<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

final class RouteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Email address of the sender. The routed email gets this address in its Reply-To header.
             *
             * @example jan.nowak@example.com
             */
            'email' => ['required', 'string', 'email:rfc', 'max:254'],

            /**
             * Free-form message to classify and forward, in any language.
             *
             * @example Nie działa mi komputer
             */
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
