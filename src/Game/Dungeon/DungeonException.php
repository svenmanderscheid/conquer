<?php
declare(strict_types=1);
namespace Conquer\Game\Dungeon;

final class DungeonException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode,string $message,public readonly int $status=400)
    { parent::__construct($message,$status); }
}
