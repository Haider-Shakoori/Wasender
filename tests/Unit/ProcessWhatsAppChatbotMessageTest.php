<?php

namespace Tests\Unit;

use App\Jobs\ProcessWhatsAppChatbotMessage;
use Tests\TestCase;

final class ProcessWhatsAppChatbotMessageTest extends TestCase
{
    public function test_chatbot_processing_uses_its_configured_queue(): void
    {
        config(['chatbots.queue' => 'chatbot-test']);

        $job = new ProcessWhatsAppChatbotMessage(123);

        $this->assertSame('chatbot-test', $job->queue);
    }
}
