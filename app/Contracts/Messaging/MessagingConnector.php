<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

use App\Data\Messaging\ConnectorSessionResult;
use App\Data\Messaging\MessageData;
use App\Data\Messaging\QrCodeResult;
use App\Data\Messaging\SendMessageResult;

interface MessagingConnector
{
    public function createSession(array $data): ConnectorSessionResult;

    public function initializeSession(string $sessionReference): ConnectorSessionResult;

    public function getQrCode(string $sessionReference): QrCodeResult;

    public function getStatus(string $sessionReference): ConnectorSessionResult;

    public function send(MessageData $message): SendMessageResult;

    public function reconcile(string $requestId): SendMessageResult;

    public function restart(string $sessionReference): ConnectorSessionResult;

    public function disconnect(string $sessionReference): ConnectorSessionResult;

    public function logout(string $sessionReference): ConnectorSessionResult;

    public function delete(string $sessionReference): void;
}
