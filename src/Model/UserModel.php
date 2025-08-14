<?php

namespace App\Model;

class UserModel
{

    private string $uuid;
    private string $fullName;
    private string $email;
    private string $password;
    private StatusCodeEnum $status;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getStatus(): StatusCodeEnum
    {
        return $this->status;
    }

    public function setStatus(StatusCodeEnum $status): void
    {
        $this->status = $status;
    }

    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'password' => $this->password,
            'status' => $this->status->value,
        ];
    }

}
