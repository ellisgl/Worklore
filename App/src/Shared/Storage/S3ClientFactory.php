<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use Aws\S3\S3Client;

/**
 * The S3 client behind the media.s3 storage. An empty endpoint means AWS
 * itself; an empty key means the AWS default credential chain.
 */
final class S3ClientFactory
{
    public static function create(string $region, string $endpoint, string $key, string $secret, bool $pathStyle): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => $region,
            'use_path_style_endpoint' => $pathStyle,
        ];
        if ('' !== $endpoint) {
            $config['endpoint'] = $endpoint;
        }
        if ('' !== $key) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        return new S3Client($config);
    }
}
