<?php

namespace Tests\Feature\Config;

use Tests\TestCase;

class CloudflareR2FilesystemConfigTest extends TestCase
{
    public function test_r2_disk_is_available_with_s3_driver(): void
    {
        $disk = config('filesystems.disks.r2');

        $this->assertIsArray($disk);
        $this->assertSame('s3', $disk['driver']);
    }
}

