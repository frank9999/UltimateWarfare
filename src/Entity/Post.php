<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity;

use DateTimeImmutable;

class Post
{
    private int $id;
    private string $content;
    private DateTimeImmutable $createDateTime;
    private bool $edited = false;
    private Topic $topic;
    /** Null once the author deleted their account; the content is kept */
    private ?User $user = null;
    private ?User $editUser = null;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setEdited(bool $edited): void
    {
        $this->edited = $edited;
    }

    public function getEdited(): bool
    {
        return $this->edited;
    }

    public function getCreateDateTime(): DateTimeImmutable
    {
        return $this->createDateTime;
    }

    public function setCreateDateTime(DateTimeImmutable $createDateTime): void
    {
        $this->createDateTime = $createDateTime;
    }

    public function getTopic(): Topic
    {
        return $this->topic;
    }

    public function setTopic(Topic $topic): void
    {
        $this->topic = $topic;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getEditUser(): ?User
    {
        return $this->editUser;
    }

    public function setEditUser(?User $editUser): void
    {
        $this->editUser = $editUser;
    }
}
