<?php

use Cloudinary\Cloudinary;

class UploadHelper
{
    private static function cloudinary()
    {
        $config =
            require __DIR__ . '/../../config/cloudinary.php';

        return new Cloudinary([

            'cloud' => [

                'cloud_name' => $config['cloud_name'],

                'api_key' => $config['api_key'],

                'api_secret' => $config['api_secret'],
            ]
        ]);
    }

    public static function uploadImage(
        $filePath
    ) {

        $cloudinary =
            self::cloudinary();

        $result =
            $cloudinary
                ->uploadApi()
                ->upload(
                    $filePath,
                    [

                        'folder' =>
                            'b2b-marketplace/products',

                        'resource_type' =>
                            'image'
                    ]
                );

        return [

            'url' =>
                $result['secure_url'],

            'public_id' =>
                $result['public_id']
        ];
    }

    public static function uploadDocument(
        $file,
        $userId,
        $documentType
    ) {

        if (
            empty($file) ||
            empty($file['tmp_name'])
        ) {
            throw new Exception(
                'File upload không hợp lệ'
            );
        }

        $cloudinary = self::cloudinary();

        $extension = pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        );


        $fileName =
            time() . '_' . uniqid();

        $folder =
            "b2b-marketplace/documents/user_{$userId}";

        $publicId =
            "{$documentType}_{$fileName}";

        $result =
            $cloudinary
                ->uploadApi()
                ->upload(
                    $file['tmp_name'],
                    [

                        'folder' => $folder,

                        'public_id' => $publicId,

                        'resource_type' => 'auto',

                        'overwrite' => true,
                    ]
                );

        return [

            'type' => $documentType,

            'url' => $result['secure_url'],

            'public_id' => $result['public_id'],

            'format' => $result['format'] ?? $extension,
        ];
    }
}