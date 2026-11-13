<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup\Exceptions;

use RuntimeException;

/**
 * Thrown when a backup cannot run (a missing bucket or directory, an
 * unsupported engine) or the dump tool fails.
 */
class DatabaseBackupException extends RuntimeException {}
