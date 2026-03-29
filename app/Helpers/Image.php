<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;

class Image
{
    public static function uploadToPublic(UploadedFile $file, $path = 'uploads')
    {
        $filename = time() . '_' . $file->hashName();
        $file->move(public_path($path), $filename);
        return $path . '/' . $filename;
    }
}
