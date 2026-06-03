<?php

namespace App\Services\Contracts;

interface LLMProvider
{
    public function generate(string $prompt): string;
}
