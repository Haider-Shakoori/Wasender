<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class CanonicalWhatsAppDispatchArchitectureTest extends TestCase
{
    public function test_automation_chatbot_and_template_services_do_not_access_connector_directly(): void
    {
        foreach ([
            app_path('Services/Automations'),
            app_path('Services/Chatbots'),
            app_path('Services/Templates'),
        ] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                $this->assertStringNotContainsString(
                    'Contracts\\Messaging\\MessagingConnector',
                    $contents,
                    "{$file->getFilename()} must create WhatsAppMessage records instead of bypassing the canonical dispatch queue.",
                );

                $this->assertStringNotContainsString(
                    'WhatsAppConnectorClient',
                    $contents,
                    "{$file->getFilename()} must not call the WhatsApp connector directly.",
                );
            }
        }
    }
}
