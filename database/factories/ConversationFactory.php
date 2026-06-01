<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'messages' => [
                ['role' => 'user', 'content' => 'Who won the NBA championship in 1998?'],
                ['role' => 'assistant', 'content' => 'The Chicago Bulls won the 1998 NBA Championship, defeating the Utah Jazz 4-2 in the Finals.'],
            ],
        ];
    }
}
