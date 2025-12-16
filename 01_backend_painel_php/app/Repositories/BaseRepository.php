<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories;

use PDO;

abstract class BaseRepository
{
    public function __construct(
        protected PDO $pdo
    ) {
    }
}
