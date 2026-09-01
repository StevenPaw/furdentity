<?php

namespace App\Api\Support;

use RuntimeException;

/**
 * Thrown by {@see ProfileImageStore} for a bad upload (unreadable image data,
 * unwritable disk, …) – caught by the controller and turned into a 422.
 */
class ProfileImageException extends RuntimeException
{
}
