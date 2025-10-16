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

use App\Repository\PostRepository;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Defines the properties of the Post document to represent the blog posts.
 *
 * See https://symfony.com/doc/current/doctrine.html#creating-an-entity-class
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
#[ODM\Document(repositoryClass: PostRepository::class)]
#[ODM\UniqueIndex(keys: ['slug' => 1], options: ['unique' => true])]
#[ODM\Index(['order' => 'desc', 'tags.name' => 'asc'])]
#[Unique(fields: ['slug'], message: 'post.slug_unique', errorPath: 'title')]
class Post
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ODM\Field]
    private ?string $slug = null;

    #[ODM\Field]
    #[Assert\NotBlank(message: 'post.blank_summary')]
    #[Assert\Length(max: 255)]
    private ?string $summary = null;

    #[ODM\Field]
    #[Assert\NotBlank(message: 'post.blank_content')]
    #[Assert\Length(min: 10, minMessage: 'post.too_short_content')]
    private ?string $content = null;

    #[ODM\Field]
    private \DateTimeImmutable $publishedAt;

    #[ODM\ReferenceOne(nullable: false, targetDocument: User::class)]
    private ?User $author = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ODM\ReferenceMany(targetDocument: Comment::class)]
    #[Assert\Type(type: Collection::class, message: 'post.invalid_tags')]
    private Collection $comments;

    /**
     * @var Collection<int, Tag>
     */
    #[ODM\EmbedMany(targetDocument: Tag::class)]
    #[Assert\Count(max: 4, maxMessage: 'post.too_many_tags')]
    #[Assert\Type(type: Collection::class, message: 'post.invalid_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getPublishedAt(): \DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeInterface $publishedAt): void
    {
        if ($publishedAt instanceof \DateTime) {
            $publishedAt = \DateTimeImmutable::createFromMutable($publishedAt);
        }
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

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        $comment->setPost($this);

        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    public function removeComment(Comment $comment): void
    {
        $this->comments->removeElement($comment);
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function addTag(Tag ...$tags): void
    {
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
