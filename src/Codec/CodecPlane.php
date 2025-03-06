<?php

namespace App\Codec;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ApiResource(shortName: 'codec_planes', provider: CodecState::class, processor: CodecState::class)]
class CodecPlane
{
    public string $id;

    #[NotBlank]
    public string $name;

    #[NotBlank]
    public \DateTimeInterface $createdAt;
}