<?php

namespace App\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'emails')]
class Email extends DomainEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: 'integer')]
    private int $clientId;

    #[ORM\Column(name: 'loan_id', type: 'integer', nullable: true)]
    private ?int $loanId = null;

    #[ORM\Column(name: 'email_template_id', type: 'integer', nullable: true)]
    private ?int $emailTemplateId = null;

    #[ORM\Column(name: 'receiver_email', type: 'string', length: 255)]
    private string $receiverEmail;

    #[ORM\Column(name: 'sender_email', type: 'string', length: 255)]
    private string $senderEmail;

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(name: 'file_ids', type: 'json', nullable: true)]
    private ?array $fileIds = null;

    #[ORM\Column(name: 'body_s3_path', type: 'string', length: 512, nullable: true)]
    private ?string $bodyS3Path = null;

    #[ORM\Column(name: 'file_s3_paths', type: 'json', nullable: true)]
    private ?array $fileS3Paths = null;

    #[ORM\Column(name: 'is_migrated_s3', type: 'smallint', options: ['default' => 0])]
    private int $isMigratedS3 = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function setClientId(int $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getLoanId(): ?int
    {
        return $this->loanId;
    }

    public function setLoanId(?int $loanId): self
    {
        $this->loanId = $loanId;

        return $this;
    }

    public function getEmailTemplateId(): ?int
    {
        return $this->emailTemplateId;
    }

    public function setEmailTemplateId(?int $emailTemplateId): self
    {
        $this->emailTemplateId = $emailTemplateId;

        return $this;
    }

    public function getReceiverEmail(): string
    {
        return $this->receiverEmail;
    }

    public function setReceiverEmail(string $receiverEmail): self
    {
        $this->receiverEmail = $receiverEmail;

        return $this;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): self
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getFileIds(): ?array
    {
        return $this->fileIds;
    }

    public function setFileIds(?array $fileIds): self
    {
        $this->fileIds = $fileIds;

        return $this;
    }

    public function getBodyS3Path(): ?string
    {
        return $this->bodyS3Path;
    }

    public function setBodyS3Path(?string $bodyS3Path): self
    {
        $this->bodyS3Path = $bodyS3Path;

        return $this;
    }

    public function getFileS3Paths(): ?array
    {
        return $this->fileS3Paths;
    }

    public function setFileS3Paths(?array $fileS3Paths): self
    {
        $this->fileS3Paths = $fileS3Paths;

        return $this;
    }

    public function getIsMigratedS3(): int
    {
        return $this->isMigratedS3;
    }

    public function setIsMigratedS3(int $state): self
    {
        $this->isMigratedS3 = $state;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }
}

