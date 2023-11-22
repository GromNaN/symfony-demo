<?php

namespace App\MongoDB\Codec;

use App\MongoDB\Document\User;
use MongoDB\BSON\Document;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mongodb.document_codec')]
#[AsTaggedItem(User::class)]
class UserCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function canDecode($value): bool
    {
        return $value instanceof Document;
    }

    public function decode($value): User
    {
        return new User(
            fullName:  $value->fullName,
            username: $value->username,
            email: $value->email,
            password: $value->password,
            roles: $value->roles->toPHP([]),
            id: $value->_id,
        );
    }

    public function canEncode($value): bool
    {
        return $value instanceof User;
    }

    public function encode($value): Document
    {
        assert($value instanceof User, sprintf('Expected instance of %s, got %s', User::class, get_debug_type($value)));

        return Document::fromPHP([
            '_id' => $value->id,
            'fullName' => $value->fullName,
            'username' => $value->username,
            'email' => $value->email,
            'password' => $value->password,
            'roles' => array_values($value->roles),
        ]);
    }
}
