<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Types\Type;
use Symfony\Component\Validator\Constraints as Assert;
use function Symfony\Component\String\u;

/**
 * Defines the properties of the Comment document to represent the blog comments.
 */
#[ODM\Document]
class Comment
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: Type::STRING)]
    #[Assert\NotBlank(message: 'comment.blank')]
    #[Assert\Length(min: 5, minMessage: 'comment.too_short', max: 10000, maxMessage: 'comment.too_long')]
    private ?string $content = null;

    #[ODM\Field(type: Type::DATE)]
    private \DateTime $publishedAt;

    #[ODM\ReferenceOne(nullable: false, targetDocument: User::class)]
    private ?User $author = null;

    public function __construct()
    {
        $this->publishedAt = new \DateTime();
    }

    #[Assert\IsTrue(message: 'comment.is_spam')]
    public function isLegitComment(): bool
    {
        $containsInvalidCharacters = null !== u($this->content)->indexOf('@');

        return !$containsInvalidCharacters;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getPublishedAt(): \DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTime $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(Post $post): void
    {
        $this->post = $post;
    }
}
