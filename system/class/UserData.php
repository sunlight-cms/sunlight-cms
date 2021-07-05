<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
use Sunlight\Util\TemporaryFile;

class UserData
{
    /** @var int */
    private $userId;
    /** @var array */
    private $options;

    function __construct(int $userId, array $options)
    {
        $this->userId = $userId;
        $this->options = $options;
    }

    function generate(): TemporaryFile
    {
        // load account data
        $userData = DB::queryRow('SELECT * FROM ' . DB::table('user') . ' WHERE id = ' . $this->userId);

        if ($userData === false) {
            throw new \UnexpectedValueException('User not found');
        }

        // create tmp file and ZIP archive
        $tmpFile = Filesystem::createTmpFile();
        $zip = new \ZipArchive();
        $zip->open($tmpFile->getPathname(), \ZipArchive::OVERWRITE);

        // add content
        $this->addAccountInfo($zip, $userData);
        $this->addAvatar($zip, $userData['avatar']);

        // event
        Extend::call('user.data.generate', [
            'archive' => $zip,
            'user_id' => $this->userId,
            'user_data' => $userData,
            'options' => $this->options,
        ]);

        return $tmpFile;
    }

    private function addAccountInfo(\ZipArchive $zip, array $userData): void
    {
        $info = [
            'User name' => $userData['username'],
            'Display name' => $userData['publicname'],
            'Login counter' => $userData['logincounter'],
            'Registration time' => DB::datetime($userData['registertime']),
            'Activity time' => DB::datetime($userData['activitytime']),
            'IP address' => $userData['ip'],
            'Comment IP addresses' => DB::queryRows('SELECT DISTINCT ip FROM ' . DB::table('post') . ' WHERE author = ' . $this->userId, null, 'ip'),
            'email' => $userData['email'],
            'note' => $userData['note'],
        ];

        $zip->addFromString(
            'account.json',
            Json::encode($info, true, false)
        );
    }

    private function addAvatar(\ZipArchive $zip, ?string $avatar): void
    {
        if ($avatar === null) {
            return;
        }

        $avatarPath = new \SplFileInfo(User::getAvatarPath($avatar));

        $zip->addFile($avatarPath->getPathname(), sprintf('avatar.%s', $avatarPath->getExtension()));
    }
}
