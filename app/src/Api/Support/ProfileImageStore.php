<?php

namespace App\Api\Support;

use App\Model\User;
use Intervention\Image\ImageManager;
use SilverStripe\Assets\Image;
use SilverStripe\Versioned\Versioned;
use Throwable;

/**
 * Stores a user's avatar/background upload as the `AvatarImage`/
 * `BackgroundImage` {@see User::$has_one} relation – the same field a CMS
 * admin's {@see \SilverStripe\AssetAdmin\Forms\UploadField} on
 * {@see User::getCMSFields()} targets, so either path leaves the file
 * visible and editable in both places.
 *
 * The upload always reuses the user's existing File record for that slot
 * (rather than creating a new one each time) and writes it at the exact
 * same fixed filename – profileimages/{handle}/{slot}.jpg – so a re-upload
 * overwrites in place instead of accumulating extra File rows. Publishing
 * (see {@see self::store()}) matters for more than visibility: an
 * unpublished/draft-only versioned File is served from a content-hash
 * "protected" path, not the flat public one this relies on.
 */
final class ProfileImageStore
{
    public const string SLOT_AVATAR = 'profileimage';

    public const string SLOT_BACKGROUND = 'backgroundimage';

    private const array RELATIONS = [
        self::SLOT_AVATAR => 'AvatarImage',
        self::SLOT_BACKGROUND => 'BackgroundImage',
    ];

    // Caps resolution (not just file size) to keep the data cost of every
    // profile view down – nobody needs a multi-megapixel source image for a
    // card-sized avatar or a background strip.
    private const int MAX_WIDTH = 500;

    /**
     * Resizes (down only, never up – see {@see \Intervention\Image\Interfaces\ImageInterface::scaleDown()})
     * and re-encodes $binaryImage as a JPEG, then writes it into the user's
     * existing `AvatarImage`/`BackgroundImage` File record for this slot
     * (creating one only the first time).
     */
    public static function store(User $user, string $slot, string $binaryImage): void
    {
        $manager = ImageManager::gd();

        try {
            $image = $manager->read($binaryImage);
        } catch (Throwable) {
            throw new ProfileImageException('Could not read image data');
        }

        $image->scaleDown(width: self::MAX_WIDTH);

        $relation = self::RELATIONS[$slot];
        $file = $user->{$relation}();

        if (!$file->exists()) {
            $file = Image::create();
        }

        $filename = 'profileimages/' . $user->Handle . '/' . $slot . '.jpg';
        $file->setFromString((string) $image->toJpeg(85), $filename);
        $file->write();

        if ($file->hasExtension(Versioned::class)) {
            $file->publishSingle();
        }

        $user->{$relation . 'ID'} = $file->ID;
        $user->write();
    }
}
