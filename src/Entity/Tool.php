<?php

namespace App\Entity;

use App\Repository\ToolRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ToolRepository::class)]
#[ORM\Table(name: 'tools')]
#[ORM\UniqueConstraint(name: 'UNIQ_TOOL_SERIAL_NUMBER', fields: ['serialNumber'])]
#[UniqueEntity(fields: ['serialNumber'], message: 'Serial number is already in use')]
class Tool
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $serialNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: false)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Assert\LessThan(100000000, message: 'The daily rent cost is too high.')]
    private ?string $dailyRentCost = null;

    #[ORM\Column(length: 20, enumType: ToolStatus::class, options: ['default' => 'available'])]
    private ToolStatus $status = ToolStatus::Available;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function getDailyRentCost(): ?string
    {
        return $this->dailyRentCost;
    }

    public function setDailyRentCost(?string $dailyRentCost): static
    {
        $this->dailyRentCost = $dailyRentCost;

        return $this;
    }

    public function getStatus(): ToolStatus
    {
        return $this->status;
    }

    public function setStatus(ToolStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
