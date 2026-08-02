<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;

class UseDateFolder
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof RotatingFileHandler) {
                $handler->setFilenameFormat('{date}/{filename}', 'Y-m-d');
            }
        }
    }
}
